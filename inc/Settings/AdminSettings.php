<?php
/**
 * AdminSettings: DX-friendly bridge to WordPress Settings API using RegisterOptions.
 *
 * @package Ran\PluginLib\Settings
 * @author  Ran Plugin Lib <bnjmnrsh@gmail.com>
 * @license GPL-2.0+ <http://www.gnu.org/licenses/gpl-2.0.txt>
 * @link    https://github.com/RocketsAreNostalgic
 * @since   0.2.0
 */

declare(strict_types=1);

namespace Ran\PluginLib\Settings;

use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Settings\AdminSettingsMenuGroupBuilder; //
use Ran\PluginLib\Options\Storage\StorageContext;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Options\OptionScope;
use Ran\PluginLib\Forms\Validation\ValidatorPipelineService;
use Ran\PluginLib\Forms\Services\FormsErrorHandlerInterface;
use Ran\PluginLib\Forms\Services\AdminFormsErrorHandler;
use Ran\PluginLib\Forms\Renderer\FormMessageHandler;
use Ran\PluginLib\Forms\Renderer\FormElementRenderer;
use Ran\PluginLib\Forms\FormsService;
use Ran\PluginLib\Forms\FormsCore;
use Ran\PluginLib\Forms\ErrorNoticeRenderer;
use Ran\PluginLib\Forms\Components\Elements\Button\Builder as ButtonBuilder;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use Ran\PluginLib\Forms\Component\ComponentLoader;
use Ran\PluginLib\Config\ConfigInterface;

/**
 * Admin settings facade that coordinates Settings API registration with a scoped `RegisterOptions`
 * instance and shared `FormsService` rendering session.
 *
 * Responsibilities:
 * - Collect menu groups, pages, sections, fields, and groups prior to hook registration.
 * - Resolve scope-aware storage contexts via the injected `RegisterOptions` implementation.
 * - Render component-driven admin forms and enqueue captured assets through the active session.
 *
 * Scope Support:
 * - Site scope: Single site or individual sites in multisite installations
 * - Network scope: Network-wide settings in multisite installations
 * - Blog scope is NOT supported (use AdminSettingsMultisiteHandler for cross-blog administration)
 */
class AdminSettings extends FormsCore {
	/**
	 * Base context and storage captured from the injected RegisterOptions instance.
	 * Retained so subsequent renders and saves can derive storage defaults.
	 */
	protected StorageContext $base_context;
	protected string $base_storage;

	/**
	 * Admin menu group metadata storage.
	 * Each group stores menu metadata for WordPress admin menu registration.
	 *
	 * @var array<string, array{meta:array, pages?:array}>
	 */
	private array $menu_groups = array();

	/**
	 * Reverse lookup map of page slugs to their owning menu group and page identifiers.
	 * Used by `render()` to retrieve metadata when handling menu callbacks.
	 *
	 * @var array<string, array{group:string, page:string}>
	 */
	private array $pages = array();

	/**
	 * Default submit zone for admin forms.
	 */
	private const DEFAULT_SUBMIT_ZONE = 'primary-controls';

	/** Constructor.
	 *
	 * Standard initialization sequence:
	 * 1. Logger resolution and assignment
	 * 2. Scope validation (context-specific)
	 * 3. Base property assignment (options, context, main_option, config)
	 * 4. Component and view setup
	 * 5. Context-specific template registration
	 * 6. Service initialization (FormsService, renderers, handlers)
	 * 7. Form session configuration with context-specific defaults
	 *
	 * @param RegisterOptions $options The base RegisterOptions instance.
	 * @param ComponentManifest $components The shared ComponentManifest instance.
	 * @param ConfigInterface|null $config Optional Config for namespace resolution and component registration.
	 * @param Logger|null $logger Optional logger instance.
	 */
	public function __construct(
		RegisterOptions $options,
		ComponentManifest $components,
		?ConfigInterface $config = null,
		?Logger $logger = null
	) {
		// Phase 1: Logger resolution
		// RegisterOptions will lazy instantiate a logger if none is provided
		$this->logger = $logger instanceof Logger ? $logger : $options->get_logger();

		// Phase 2: Scope validation (AdminSettings requires Site scope)
		$context = $options->get_storage_context();
		if ($context->scope !== OptionScope::Site) {
			$received = $context->scope instanceof OptionScope ? $context->scope->value : 'unknown';
			$this->logger->error('AdminSettings requires site context; received ' . $received . '.');
			throw new \InvalidArgumentException('AdminSettings requires site context; received ' . $received . '.');
		}

		// Phase 3: Base property assignment
		$this->base_options = $options;
		$this->base_context = $context;
		$this->main_option  = $options->get_main_option_name();
		$this->config       = $config;

		// Phase 4: Component and view setup
		$this->components = $components;
		$this->views      = $components->get_component_loader();

		// Phase 5: Context-specific template registration
		// AdminSettings only has page-level templates, everything else uses shared defaults
		$aliases = $this->views->aliases();
		if (!isset($aliases['admin.root-wrapper'])) {
			$this->views->register('admin.root-wrapper', '../../Settings/templates/admin/root-wrapper.php');
		}

		// Phase 6: Service initialization
		$this->form_service    = new FormsService($this->components, $this->logger);
		$this->field_renderer  = new FormElementRenderer($this->components, $this->form_service, $this->views, $this->logger);
		$this->message_handler = new FormMessageHandler($this->logger);
		$this->field_renderer->set_message_handler($this->message_handler);

		// Phase 7: Form session configuration with context-specific defaults
		$this->_start_form_session();

		// AdminSettings only overrides root page templates, everything else uses system defaults
		$this->form_session->set_form_defaults(array(
			'root-wrapper' => 'admin.root-wrapper',
		));
	}

	/**
	 * Create a simple settings page under the WordPress Settings menu.
	 *
	 * UserSettings collerary is the collection() method.
	 *
	 * This is a convenience method for the common case of adding a single settings page.
	 * For multiple pages or custom menu groups, use menu_group() instead.
	 *
	 * @param string $page_slug The page slug (used in URL)
	 * @param string|callable|null $template Root template override (registered key or callable)
	 * @param array<string,mixed> $args Additional metadata: heading, menu_title, capability, parent, order, description, icon, position
	 *
	 * @return AdminSettingsPageBuilder
	 */
	public function settings_page(
		string $page_slug,
		string|callable|null $template = null,
		array $args = array()
	): AdminSettingsPageBuilder {
		$heading    = (string) ($args['heading'] ?? ($args['title'] ?? ucwords(str_replace(array('-', '_'), ' ', $page_slug))));
		$menu_title = (string) ($args['menu_title'] ?? ($args['label'] ?? $heading));
		$capability = (string) ($args['capability'] ?? 'manage_options');
		$parent     = array_key_exists('parent', $args) ? $args['parent'] : 'options-general.php';
		$order      = isset($args['order']) ? max(0, (int) $args['order']) : 0;

		$group_slug = $page_slug . '_settings_group';
		$group      = $this->menu_group($group_slug)
			->heading($heading)
			->menu_label($menu_title)
			->capability($capability)
			->parent($parent)
			->icon($args['icon'] ?? null)
			->position($args['position'] ?? null);

		$page_args = array(
			'heading'     => $heading,
			'menu_title'  => $menu_title,
			'capability'  => $capability,
			'order'       => $order,
			'description' => $args['description'] ?? null,
		);
		// Pass through optional rendering metadata
		if (isset($args['style'])) {
			$page_args['style'] = $args['style'];
		}
		if (isset($args['before'])) {
			$page_args['before'] = $args['before'];
		}
		if (isset($args['after'])) {
			$page_args['after'] = $args['after'];
		}

		$page = $group->page($page_slug, $template, $page_args);

		return $page;
	}

	/**
	 * Begin a menu group definition.
	 *
	 * @param string $group_slug Unique slug for this menu group.
	 * @return AdminSettingsMenuGroupBuilder
	 */
	public function menu_group(string $group_slug): AdminSettingsMenuGroupBuilder {
		$existing      = $this->menu_groups[$group_slug]['meta'] ?? null;
		$default_title = ucwords(str_replace(array('-', '_'), ' ', $group_slug));
		$heading       = $existing['heading']    ?? $default_title;
		$menu_title    = $existing['menu_title'] ?? $heading;

		$initial_meta = array(
		    'heading'    => $heading,
		    'menu_title' => $menu_title,
		    'capability' => $existing['capability'] ?? 'manage_options',
		    'order'      => isset($this->menu_groups[$group_slug]['pages']) ? count($this->menu_groups[$group_slug]['pages']) : 0,
		);

		// Create update function for immediate data flow
		$updateFn = $this->_create_update_function();

		return new AdminSettingsMenuGroupBuilder($this, $group_slug, $initial_meta, $updateFn);
	}

	/**
	 * Fluent alias returning the current settings instance.
	 *
	 * Enables chaining like end_group()->end() to match UserSettings API.
	 */
	public function end(): static {
		return $this;
	}

	/**
	 * Get the base RegisterOptions instance.
	 *
	 * Useful for schema registration in on_render callbacks.
	 *
	 * @return RegisterOptions
	 */
	public function get_base_options(): RegisterOptions {
		return $this->base_options;
	}

	/**
	 * Check if AdminSettings should load at all.
	 *
	 * Returns true only if we're in admin context.
	 * Called BEFORE the builder callback runs to avoid unnecessary work.
	 *
	 * @return bool True if should load, false to skip entirely.
	 */
	protected function _should_load(): bool {
		return $this->_do_is_admin();
	}

	/**
	 * Boot admin: register settings, sections, fields, and menu pages.
	 *
	 * By default, uses lazy loading - skips hook registration if not in admin context.
	 * Set $eager to true to force immediate hook registration regardless of context.
	 *
	 * @param bool $eager If true, skip lazy loading checks and always register hooks.
	 * @return void
	 */
	public function boot(bool $eager = false): void {
		// Note: The admin context check now happens in _should_load() before the callback runs.
		// This boot() method is only called if _should_load() returned true (or eager=true).

		$this->logger->debug('admin_settings.boot.entry', array(
			'main_option'    => $this->main_option,
			'eager'          => $eager,
			'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
		));

		// Register setting immediately - MUST happen before options.php processes the form
		// WordPress's options.php calls update_option() which triggers the sanitize callback
		// BEFORE admin_init fires, so we cannot defer this registration.
		$this->__register_setting();

		$hooks = array();

		// File upload handling and validation message restoration (admin_init)
		// Only run on our pages or options.php (save handler)
		$hooks[] = array(
			'hook'     => 'admin_init',
			'callback' => function () {
				if (!$this->_is_our_admin_page()) {
					return;
				}
				// Handle file uploads early - before options.php processes the form
				// This injects uploaded file data into $_POST so it reaches the sanitize callback
				$this->_handle_file_uploads();

				// Restore validation messages from our transient (persisted during POST).
				// Must happen on admin_init AFTER the redirect from options.php completes.
				// This feeds them into our message_handler for field-level display.
				$this->_restore_form_messages();
			},
		);

		// Note: Menu registration is handled by AdminMenuRegistry, not here.
		// AdminSettings is now page-content-only (sections, fields, validation, rendering).

		$this->_register_action_hooks($hooks);
	}

	/**
	 * Check if current request is for one of our admin pages or the options.php save handler.
	 *
	 * @return bool True if we're on one of our pages or processing a save.
	 */
	protected function _is_our_admin_page(): bool {
		// Always run on options.php (save handler) - check if our option is being saved
		$pagenow = $GLOBALS['pagenow'] ?? '';
		if ($pagenow === 'options.php') {
			// Check if this is a save for our option group
			$option_page = $_POST['option_page'] ?? '';
			return $option_page === $this->main_option . '_group';
		}

		// Check if we're on one of our registered pages
		$page = $_GET['page'] ?? '';
		if ($page === '') {
			return false;
		}

		return isset($this->pages[$page]);
	}

	// Internal Private Protected

	/**
	 * Render a registered settings page using the template or a default form shell.
	 *
	 * @internal WordPress callback for rendering admin settings.
	 *
	 * @param string $id_slug The page id, defaults to 'profile'.
	 * @param array|null $context Optional context.
	 *
	 * @return void
	 */
	public function __render(string $id_slug, ?array $context = null): void {
		if (!isset($this->pages[$id_slug])) {
			ErrorNoticeRenderer::renderSimpleNotice('Unknown settings page: ' . $id_slug);
			return;
		}
		$this->_start_form_session();

		$ref  = $this->_resolve_page_reference($id_slug);
		$meta = $ref['meta'];

		$bundle = $this->_resolve_schema_bundle($this->base_options, array(
			'intent'    => 'render',
			'page_slug' => $id_slug,
		));
		$internalSchema = $bundle['schema'];
		$group          = $this->main_option . '_group';
		$options        = $this->_do_get_option($this->main_option, array());
		$sections       = $this->sections[$ref['page']] ?? array();

		// Get effective values from message handler (handles pending values)
		$effective_values = $this->message_handler->get_effective_values($options);

		// Render before/after callbacks for the page
		$before_html = $this->_render_callback_output($meta['before'] ?? null, array(
			'container_id' => $id_slug,
			'values'       => $effective_values,
		)) ?? '';
		$after_html = $this->_render_callback_output($meta['after'] ?? null, array(
			'container_id' => $id_slug,
			'values'       => $effective_values,
		)) ?? '';

		$rendered_content = $this->_render_default_sections_wrapper($id_slug, $sections, $effective_values);
		$submit_controls  = $this->_get_submit_controls_for_page($id_slug);

		$page_style = isset($meta['style']) ? trim((string) $meta['style']) : '';

		// Check if page has file upload fields
		$has_files = $this->_container_has_file_uploads($ref['page']);

		$payload = array(
			...($context ?? array()),
			'heading'           => $meta['heading']     ?? '',
			'description'       => $meta['description'] ?? '',
			'group'             => $group,
			'page_slug'         => $id_slug,
			'page_meta'         => $meta,
			'style'             => $page_style,
			'options'           => $options,
			'section_meta'      => $sections,
			'values'            => $effective_values,
			'inner_html'        => $rendered_content,
			'submit_controls'   => $submit_controls,
			'render_submit'     => fn (): string => $this->_render_default_submit_controls($id_slug, $submit_controls),
			'messages_by_field' => $this->message_handler->get_all_messages(),
			'has_files'         => $has_files,
			'before'            => $before_html,
			'after'             => $after_html,
		);

		$schemaSummary = $this->_build_schema_summary($internalSchema);

		$this->logger->debug('admin_settings.render.payload', array(
			'page'     => $id_slug,
			'heading'  => $payload['heading'],
			'group'    => $group,
			'has_meta' => array_keys($meta),
			'callback' => $this->form_session->get_root_template_callback($id_slug) !== null,
		));
		$this->logger->debug('admin_settings.render.schema_trace', array(
			'page'   => $id_slug,
			'fields' => $schemaSummary,
		));

		// Enqueue Forms base CSS JIT during render
		/**
		 * Filter the Forms base stylesheet URL.
		 *
		 * Return an empty string to disable the default stylesheet,
		 * or a different URL to use a custom stylesheet.
		 *
		 * @param string $css_url The default stylesheet URL.
		 * @param string $id_slug The page being rendered.
		 * @param AdminSettings $instance The AdminSettings instance.
		 */
		$base_css_url = $this->_do_apply_filter(
			'ran_plugin_lib_forms_base_stylesheet_url',
			$this->_do_plugins_url('../Forms/assets/forms.base.css', __FILE__),
			$id_slug,
			$this
		);
		if ($base_css_url !== '') {
			$this->_do_wp_enqueue_style(
				'ran-plugin-lib-forms-base',
				$base_css_url,
				array(),
				'1.0.0'
			);
		}

		// Enqueue AdminSettings CSS JIT during render
		/**
		 * Filter the AdminSettings stylesheet URL.
		 *
		 * Return an empty string to disable the default stylesheet,
		 * or a different URL to use a custom stylesheet.
		 *
		 * @param string $css_url The default stylesheet URL.
		 * @param string $id_slug The page being rendered.
		 * @param AdminSettings $instance The AdminSettings instance.
		 */
		$css_url = $this->_do_apply_filter(
			'ran_plugin_lib_admin_settings_stylesheet_url',
			$this->_do_plugins_url('assets/admin.settings.css', __FILE__),
			$id_slug,
			$this
		);
		if ($css_url !== '') {
			$this->_do_wp_enqueue_style(
				'ran-plugin-lib-admin-settings',
				$css_url,
				array('ran-plugin-lib-forms-base'),
				'1.0.0'
			);
		}

		$this->_finalize_render($id_slug, $payload, array('page_slug' => $id_slug));
	}

	// WP Settings API hooks

	/**
	 * Register the setting with WordPress Settings API and wire sanitize callback.
	 *
	 * This enables the options.php save flow while using custom template rendering.
	 * We register the main setting but NOT individual fields/sections since we use
	 * custom templates instead of do_settings_fields().
	 *
	 * Uses a default group derived from the main option name.
	 *
	 * @internal
	 */
	public function __register_setting(): void {
		$group       = $this->base_options->get_main_option_name() . '_group';
		$option_name = $this->base_options->get_main_option_name();

		// Log before registration
		$this->logger->debug('admin_settings.register_setting.before', array(
			'group'          => $group,
			'option'         => $option_name,
			'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
			'is_post'        => ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST',
		));

		$this->_do_register_setting($group, $option_name, array(
			'sanitize_callback' => array($this, '__sanitize'),
		));

		$this->logger->debug('admin_settings.register_setting.after', array(
			'group'  => $group,
			'option' => $option_name,
		));
	}

	/**
	 * Settings API sanitize callback: validate and normalize payload per schema.
	 * This does NOT persist directly; it returns the normalized array back to the Settings API
	 * to save.
	 *
	 * As the Settings API handels row persistnace, we use a temporary RegisterOptions instance
	 * to validate and normalize the payload. The subsequent `stage_options()` call reuses the
	 * schema registered on the injected `RegisterOptions` instance, so each submitted option key is
	 * sanitized and validated before WordPress persists the grouped option.
	 *
	 * Scope note: WordPress posts settings for the current admin context only. Thus the callback
	 * distinguishes between site and network scopes via `_do_is_network_admin()`. AdminSettings
	 * does not support Blog scope - it operates in either site-specific or network-wide contexts.
	 * For cross-blog administration, use AdminSettingsMultisiteHandler or custom integration.
	 *
	 * Implementation detail: to avoid merging with existing stored values during
	 * normalization, we instantiate a temporary RegisterOptions instance using a
	 * dedicated option key and the same scope. This allows us to reuse the
	 * schema sanitizers/validators without touching the real row. The subsequent
	 * `stage_options()` call reuses the schema registered on the injected
	 * `RegisterOptions` instance, so each submitted option key is sanitized and
	 * validated before WordPress persists the grouped option.
	 *
	 * Additionally, capability checks are enforced by the Settings API (via
	 * `register_setting()` and `options.php`) prior to invoking this callback,
	 * guaranteeing only authorized users reach this point.
	 *
	 * @param mixed $raw Incoming value for the grouped main option (expected array)
	 * @return array<string, mixed>
	 */
	public function __sanitize($raw): array {
		$this->logger->debug('admin_settings.__sanitize.entry', array(
			'main_option'  => $this->main_option,
			'payload_keys' => is_array($raw) ? array_keys($raw) : 'not_array',
		));

		$payload  = is_array($raw) ? $raw : array();
		$previous = $this->_do_get_option($this->main_option, array());
		$previous = is_array($previous) ? $previous : array();

		$this->_prepare_validation_messages($payload);

		$scope    = $this->_do_is_network_admin() ? OptionScope::Network : OptionScope::Site;
		$resolved = $this->_resolve_context(array('scope' => $scope));
		$tmp      = $this->base_options->with_context($resolved['storage']);

		$bundle = $this->_resolve_schema_bundle($tmp, array(
			'intent'    => 'sanitize',
			'page_slug' => $this->main_option,
		));

		// Consolidate bundle sources into single registration call
		$merged = $this->_merge_schema_bundle_sources($bundle);
		if (!empty($merged['merged_schema'])) {
			$tmp->__register_internal_schema(
				$merged['merged_schema'],
				$merged['metadata'],
				$merged['queued_validators'],
				$merged['queued_sanitizers'],
				$merged['defaults_for_seeding']
			);
		}

		// Seed defaults for missing keys (register_schema handles seeding + telemetry)
		if (!empty($merged['defaults_for_seeding'])) {
			$tmp->register_schema($merged['defaults_for_seeding']);
		}

		$policy = $this->base_options->get_write_policy();
		if ($policy !== null) {
			$tmp->with_policy($policy);
		}
		// Stage options and check for validation failures
		$tmp->stage_options($payload);
		$messages = $this->_process_validation_messages($tmp);

		if ($this->_has_validation_failures()) {
			$this->_log_validation_failure(
				'AdminSettings::_sanitize validation failed; returning previous option payload.',
				array(
					'previous_payload'    => $previous,
					'validation_messages' => $messages,
				)
			);

			// Persist messages for display after redirect using our own transient mechanism.
			// This is more reliable than WordPress's settings_errors which has timing issues.
			$this->_persist_form_messages($messages);

			return $previous;
		}

		$result = $tmp->get_options();
		$this->_log_validation_success('AdminSettings::_sanitize returning sanitized payload.', array(
			'sanitized_payload' => $result,
		));

		// Persist notices even when validation passes (e.g., sanitizer feedback messages)
		if (!empty($messages)) {
			$this->_persist_form_messages($messages);
		}

		$this->_clear_pending_validation();

		return $result;
	}

	// Resolvers

	/**
	 * Resolve the scope for the admin context.
	 *
	 * Determines whether we're operating in Site or Network scope based on
	 * context parameters and current admin environment.
	 *
	 * Note: AdminSettings only supports Site and Network scopes. Blog scope
	 * is not supported as admin settings pages operate in either site-specific
	 * or network-wide contexts, not cross-blog administration contexts.
	 *
	 * @param array<string,mixed> $context Resolution context
	 * @return OptionScope The resolved scope (Site or Network only)
	 */
	protected function _resolve_scope(array $context): OptionScope {
		$baseContext = $this->base_options->get_storage_context();
		$scope       = SettingsScopeHelper::parse_scope($context);

		// Default scope resolution based on admin context
		if (!$scope instanceof OptionScope) {
			$scope = $baseContext->scope ?? ($this->_do_is_network_admin() ? OptionScope::Network : OptionScope::Site);
		}

		// Validate scope is allowed for AdminSettings (Site and Network only)
		$scope = SettingsScopeHelper::require_allowed($scope, OptionScope::Site, OptionScope::Network);

		return $scope;
	}

	/**
	 * Resolve the correctly scoped storage context for current admin context.
	 *
	 * This is the main orchestration method that:
	 * 1. Resolves the target scope (Site or Network)
	 * 2. Creates appropriate storage context for that scope
	 *
	 * AdminSettings only supports Site and Network scopes. For cross-blog
	 * administration, use AdminSettingsMultisiteHandler or custom integration.
	 *
	 * @param array<string,mixed> $context Resolution context
	 * @return array{storage: StorageContext, scope: OptionScope}
	 */
	protected function _resolve_context(array $context): array {
		$context = $context ?? array();

		// Step 1: Resolve scope
		$scope = $this->_resolve_scope($context);

		// Step 2: Create storage context based on scope
		return match ($scope) {
			OptionScope::Site    => $this->_create_site_storage_context(),
			OptionScope::Network => $this->_create_network_storage_context(),
		};
	}

	/**
	 * Resolve canonical page metadata from the menu_groups map using the reverse lookup.
	 *
	 * @param string $page_slug
	 * @return array{group:string,page:string,meta:array<string,mixed>}
	 */
	protected function _resolve_page_reference(string $page_slug): array {
		if (!isset($this->pages[$page_slug])) {
			throw new \InvalidArgumentException('Unknown admin settings page: ' . $page_slug);
		}
		$ref  = $this->pages[$page_slug];
		$meta = $this->menu_groups[$ref['group']]['pages'][$ref['page']]['meta'] ?? array();
		return array(
			'group' => $ref['group'],
			'page'  => $ref['page'],
			'meta'  => $meta,
		);
	}

	// Resolve context helpers

	/**
	 * Create storage context for Site scope.
	 *
	 * @return array{storage: StorageContext, scope: OptionScope}
	 */
	protected function _create_site_storage_context(): array {
		return array(
			'storage' => StorageContext::forSite(),
			'scope'   => OptionScope::Site,
		);
	}

	/**
	 * Create storage context for Network scope.
	 *
	 * @return array{storage: StorageContext, scope: OptionScope}
	 */
	protected function _create_network_storage_context(): array {
		return array(
			'storage' => StorageContext::forNetwork(),
			'scope'   => OptionScope::Network,
		);
	}

	// Handlers

	/**
	 * Handle AdminSettings-specific update types.
	 *
	 * @param string $type The update type
	 * @param array $data Update data
	 * @return void
	 */
	protected function _handle_custom_update(string $type, array $data): void {
		switch ($type) {
			case 'menu_group':
				$this->_handle_menu_group_update($data);
				break;
			case 'menu_group_commit':
				$this->_handle_menu_group_commit($data);
				break;
			case 'page':
				$this->_handle_context_update($type, $data);
				break;
			default:
				// Log unknown update type (default behavior from FormsCore)
				$this->logger->warning('AdminSettings: Unknown update type received', array(
					'type'      => $type,
					'data_keys' => array_keys($data)
				));
				break;
		}
	}

	/**
	 * Handle menu group update from builders.
	 *
	 * @param array $data Menu group update data
	 * @return void
	 */
	protected function _handle_menu_group_update(array $data): void {
		$container_id = $data['container_id'] ?? '';
		$group_data   = $data['group_data']   ?? array();

		if ($container_id === '') {
			$this->logger->warning('AdminSettings: Menu group update missing container_id', $data);
			return;
		}

		// Store menu group metadata
		if (!isset($this->menu_groups[$container_id])) {
			$this->menu_groups[$container_id] = array(
				'meta'  => array(),
				'pages' => array()
			);
		}
		$this->menu_groups[$container_id]['meta'] = $group_data;
	}

	/**
	 * Handle menu group commit from builders.
	 * This finalizes the group after all pages have been added.
	 *
	 * @param array $data Menu group commit data
	 * @return void
	 */
	protected function _handle_menu_group_commit(array $data): void {
		$container_id = $data['container_id'] ?? '';

		if ($container_id === '') {
			$this->logger->warning('AdminSettings: Menu group commit missing container_id', $data);
			return;
		}

		// Group is now complete - all pages should have been added via page updates
		// Update reverse lookup for all pages in this group
		if (isset($this->menu_groups[$container_id]['pages'])) {
			foreach (array_keys($this->menu_groups[$container_id]['pages']) as $page_slug) {
				$this->pages[$page_slug] = array(
					'group' => $container_id,
					'page'  => $page_slug
				);
			}
		}
		$this->logger->debug('settings.builder.menu_group.committed', array(
			'container_id' => $container_id,
			'pages'        => isset($this->menu_groups[$container_id]['pages']) ? array_keys($this->menu_groups[$container_id]['pages']) : array(),
		));
	}

	/**
	 * Handle page update from builders.
	 *
	 * @param array $data Page update data
	 * @return void
	 */
	protected function _handle_context_update(string $type, array $data): void {
		// Route AdminSettings-specific types to custom handler
		if (in_array($type, array('menu_group', 'menu_group_commit'), true)) {
			$this->_handle_custom_update($type, $data);
			return;
		}

		if ($type !== 'page') {
			$this->logger->warning('AdminSettings: Unsupported context update type received', array(
				'type'      => $type,
				'data_keys' => array_keys($data)
			));
			return;
		}
		$container_id = $data['container_id'] ?? '';
		$page_data    = $data['page_data']    ?? array();
		if (array_key_exists('style', $page_data)) {
			$page_data['style'] = trim((string) $page_data['style']);
		}
		$group_id = $data['group_id'] ?? '';

		if ($container_id === '' || $group_id === '') {
			$this->logger->warning('AdminSettings: Page update missing required IDs', $data);
			return;
		}

		// Ensure group exists
		if (!isset($this->menu_groups[$group_id])) {
			$this->menu_groups[$group_id] = array(
				'meta'  => array(),
				'pages' => array()
			);
		}

		// Store page data in the group
		$this->menu_groups[$group_id]['pages'][$container_id] = array(
			'meta' => $page_data
		);

		// Maintain master page lookup for rendering and tests (references only; metadata stays on menu_groups)
		$this->pages[$container_id] = array(
			'group' => $group_id,
			'page'  => $container_id,
		);
	}

	/**
	 * Retrieve submit controls metadata for the given page.
	 *
	 * @param string $page_slug
	 * @return array<string,mixed> Canonical submit-controls payload when defined, otherwise empty array.
	 */
	protected function _get_submit_controls_for_page(string $page_slug): array {
		return $this->submit_controls[$page_slug] ?? array();
	}

	/**
	 * Ensure submit controls fallback is applied when builders have not seeded any controls.
	 *
	 * @param string $page_slug
	 * @param array{zone_id:string,before:?callable,after:?callable,controls:array<int,array{id:string,label:string,component:string,component_context:array<string,mixed>,order:int}>} $submit_controls
	 * @param bool $hadCanonical Whether canonical controls existed prior to fallback
	 * @return array{zone_id:string,before:?callable,after:?callable,controls:array<int,array{id:string,label:string,component:string,component_context:array<string,mixed>,order:int}>}
	 */
	protected function _ensure_submit_controls_fallback(string $page_slug, array $submit_controls, bool $hadCanonical): array {
		// Log at INFO level when fallback provides default button (normal path for pages without explicit submit_controls())
		// Log at DEBUG level with full context for diagnostics
		$reason = $hadCanonical ? 'empty_controls' : 'default_button';
		$this->logger->info('admin_settings.submit_controls.default_applied', array(
			'page'   => $page_slug,
			'reason' => $reason,
		));
		$this->logger->debug('admin_settings.submit_controls.fallback_details', array(
			'page'          => $page_slug,
			'reason'        => $reason,
			'had_canonical' => $hadCanonical,
			'zone_id'       => $submit_controls['zone_id'] ?? self::DEFAULT_SUBMIT_ZONE,
		));

		$button = (new ButtonBuilder('default-primary', 'Save Changes'))
			->type('submit')
			->variant('primary')
			->to_array();
		$controls = array(
			array(
				'id'                => $button['id'],
				'label'             => $button['label'],
				'component'         => $button['component'],
				'component_context' => array_merge($button['component_context'], array('type' => 'submit')),
				'order'             => $button['order'],
			),
		);

		$submit_controls['zone_id']  = $submit_controls['zone_id'] ?? self::DEFAULT_SUBMIT_ZONE;
		$submit_controls['before']   = $submit_controls['before']  ?? null;
		$submit_controls['after']    = $submit_controls['after']   ?? null;
		$submit_controls['controls'] = $controls;

		$this->submit_controls[$page_slug] = $submit_controls;

		return $submit_controls;
	}

	// Render helpers

	/**
	 * Render a single submit controls zone.
	 *
	 * @param array{zone_id?:string,before:?callable,after:?callable,controls?:array<int,array{id:string,label:string,component:string,component_context:array<string,mixed>,order:int}>} $zone_meta
	 * @param array<int,array{id:string,label:string,component:string,component_context:array<string,mixed>,order:int}> $controls
	 * @return string
	 */
	protected function _render_submit_zone(string $page_slug, string $zone_id, array $zone_meta, array $controls): string {
		if ($this->form_session === null) {
			$this->_start_form_session();
		}

		$content = '';
		foreach ($controls as $control) {
			$content .= $this->_render_submit_control($control);
		}

		$callback_context = array(
			'container_id' => $page_slug,
			'zone_id'      => $zone_id,
			'controls'     => $controls,
		);

		$before_markup = $this->_render_callback_output($zone_meta['before'] ?? null, $callback_context) ?? '';
		$after_markup  = $this->_render_callback_output($zone_meta['after'] ?? null, $callback_context)  ?? '';

		$content_with_callbacks = $before_markup . $content . $after_markup;

		return $this->form_session->render_element(
			'submit-controls-wrapper',
			array(
				'zone_id'    => $zone_id,
				'inner_html' => $content_with_callbacks,
			),
			array(
				'root_id' => $page_slug,
				'zone_id' => $zone_id,
			)
		);
	}

	/**
	 * Render a submit control button via component manifest.
	 *
	 * @param array{id:string,label:string,component:string,component_context:array<string,mixed>,order:int} $control
	 * @return string
	 */
	protected function _render_submit_control(array $control): string {
		if ($this->form_session === null) {
			$this->_start_form_session();
		}

		$component  = $control['component']         ?? '';
		$context    = $control['component_context'] ?? array();
		$control_id = $control['id']                ?? '';
		$label      = $control['label']             ?? '';

		$context['field_id'] = $control_id;
		$context['label']    = $label;

		try {
			$this->logger->debug('admin_settings.submit_control.render.start', array(
				'control_id'   => $control_id,
				'component'    => $component,
				'context_keys' => array_keys($context),
			));
			return $this->field_renderer->render_component_with_assets(
				$component,
				$context,
				$this->form_session
			);
		} catch (\Throwable $e) {
			$this->logger->warning('AdminSettings: Submit control rendering failed', array(
				'control_id' => $control_id,
				'component'  => $component,
				'error'      => $e->getMessage(),
			));
			return '';
		}
	}

	/**
	 * Render submit controls for the current page.
	 *
	 * @param array{zone_id:string,before:?callable,after:?callable,controls:array<int,array{id:string,label:string,component:string,component_context:array<string,mixed>,order:int}>} $submit_controls
	 * @return string
	 */
	protected function _render_default_submit_controls(string $page_slug, array $submit_controls): string {
		$zone_id       = $submit_controls['zone_id']  ?? self::DEFAULT_SUBMIT_ZONE;
		$controls      = $submit_controls['controls'] ?? array();
		$hadCanonical  = isset($this->submit_controls[$page_slug]);
		$needsFallback = empty($controls);

		if ($needsFallback) {
			$submit_controls = $this->_ensure_submit_controls_fallback($page_slug, $submit_controls, $hadCanonical);
			$controls        = $submit_controls['controls'];
		}
		$this->logger->debug('admin_settings.submit_controls.render', array(
			'page'     => $page_slug,
			'zone_id'  => $zone_id,
			'controls' => array_map(static function (array $control): array {
				return array(
					'id'        => $control['id']        ?? null,
					'component' => $control['component'] ?? null,
					'order'     => $control['order']     ?? null,
				);
			}, $controls),
		));

		return $this->_render_submit_zone($page_slug, $zone_id, $submit_controls, $controls);
	}

	/**
	 * Handle file uploads early in the request lifecycle.
	 *
	 * WordPress Settings API doesn't natively support file uploads because options.php
	 * only processes $_POST data through the sanitize callback. This method intercepts
	 * file uploads, processes them, and injects the results into $_POST so they reach
	 * the sanitize callback.
	 *
	 * @return void
	 */
	protected function _handle_file_uploads(): void {
		$this->logger->debug('admin_settings._handle_file_uploads.entry', array(
			'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
			'main_option'    => $this->main_option,
		));

		// Only process on POST requests
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			return;
		}

		// Check if this is our option being saved
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$option_page = $_POST['option_page'] ?? '';
		$this->logger->debug('admin_settings._handle_file_uploads.option_page_check', array(
			'option_page'    => $option_page,
			'expected_group' => $this->main_option . '_group',
			'match'          => $option_page === $this->main_option . '_group',
		));
		if ($option_page !== $this->main_option . '_group') {
			return;
		}

		// Process uploaded files using shared utility
		$processed = $this->_process_uploaded_files();

		// Inject results into $_POST so they reach the sanitize callback
		foreach ($processed as $fieldKey => $result) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			if (!isset($_POST[$this->main_option])) {
				$_POST[$this->main_option] = array();
			}
			$_POST[$this->main_option][$fieldKey] = $result;
		}
	}

	/**
	 * Extract page slugs from the current builder state for error fallback pages.
	 *
	 * @return array<string> List of page slugs that were being registered.
	 */
	protected function _extract_page_slugs_from_session(): array {
		$slugs = array();

		// Extract from menu_groups (includes settings_page which creates a group)
		foreach ($this->menu_groups as $group_id => $group) {
			$slugs[] = $group_id;
			if (!empty($group['pages'])) {
				foreach (array_keys($group['pages']) as $page_id) {
					$slugs[] = $page_id;
				}
			}
		}

		// Also include from pages reverse lookup
		foreach (array_keys($this->pages) as $page_id) {
			$slugs[] = $page_id;
		}

		return array_unique($slugs);
	}

	/**
	 * Register fallback admin pages that display the error for pages that failed to build.
	 *
	 * This ensures users see the error on the page they were trying to access,
	 * rather than getting "Sorry, you are not allowed to access this page."
	 *
	 * @param \Throwable $e The caught exception or error.
	 * @param string $hook The WordPress hook or context where the error occurred.
	 * @param bool $is_dev Whether we're in development mode (show full details).
	 * @return void
	 */
	protected function _register_error_fallback_pages(\Throwable $e, string $hook, bool $is_dev): void {
		$page_slugs = $this->_extract_page_slugs_from_session();

		$add_menu_page = function (
			string $page_title,
			string $menu_title,
			string $capability,
			string $menu_slug,
			callable $callback,
			string $icon_url = '',
			?int $position = null
		): void {
			$this->_do_add_menu_page($page_title, $menu_title, $capability, $menu_slug, $callback, $icon_url, $position);
		};

		$add_submenu_page = function (
			string $parent_slug,
			string $page_title,
			string $menu_title,
			string $capability,
			string $menu_slug,
			callable $callback
		): void {
			$this->_do_add_submenu_page($parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback);
		};

		$did_action = function (string $hook): bool {
			return (bool) $this->_do_did_action($hook);
		};

		$add_action = function (string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): void {
			$this->_do_add_action($hook, $callback, $priority, $accepted_args);
		};

		$this->_get_error_handler()->register_admin_menu_fallback_pages(
			$page_slugs,
			$is_dev,
			$add_menu_page,
			$add_submenu_page,
			$did_action,
			$add_action
		);
	}

	protected function _get_form_type_suffix(): string {
		return 'admin';
	}

	protected function _get_error_handler(): FormsErrorHandlerInterface {
		return new AdminFormsErrorHandler();
	}

	/**
	 * Public wrapper to restore form validation messages from transient.
	 *
	 * Called by AdminMenuRegistry after creating the AdminSettings instance,
	 * since the admin_init hook has already fired by that point.
	 *
	 * @return bool True if messages were restored, false if none found.
	 */
	public function restore_form_messages(): bool {
		return $this->_restore_form_messages();
	}
}
