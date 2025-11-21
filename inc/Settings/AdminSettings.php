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

use Ran\PluginLib\Util\WPWrappersTrait;
use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Settings\AdminSettingsMenuGroupBuilder; //
use Ran\PluginLib\Options\Storage\StorageContext;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Options\OptionScope;
use Ran\PluginLib\Forms\Validation\ValidatorPipelineService;
use Ran\PluginLib\Forms\Renderer\FormMessageHandler;
use Ran\PluginLib\Forms\Renderer\FormElementRenderer;
use Ran\PluginLib\Forms\FormsService;
use Ran\PluginLib\Forms\FormsInterface;
use Ran\PluginLib\Forms\FormsBaseTrait;
use Ran\PluginLib\Forms\Components\Elements\Button\Builder as ButtonBuilder;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use Ran\PluginLib\Forms\Component\ComponentLoader;

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
class AdminSettings implements FormsInterface {
	use FormsBaseTrait;
	use WPWrappersTrait;

	protected ComponentLoader $views;
	protected RegisterOptions $base_options;

	private const DEFAULT_SUBMIT_ZONE = 'default-submit-zone';

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

	/** Constructor.
	 *
	 * Standard initialization sequence:
	 * 1. Logger resolution and assignment
	 * 2. Scope validation (context-specific)
	 * 3. Base property assignment (options, context, main_option)
	 * 4. Component and view setup
	 * 5. Context-specific template registration
	 * 6. Service initialization (FormsService, renderers, handlers)
	 * 7. Form session configuration with context-specific defaults
	 *
	 * @param RegisterOptions $options The base RegisterOptions instance.
	 * @param ComponentManifest $components The shared ComponentManifest instance.
	 * @param Logger|null $logger Optional logger instance.
	 */
	public function __construct(
		RegisterOptions $options,
		ComponentManifest $components,
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
	 * Retrieve submit controls metadata for the given page.
	 *
	 * @param string $page_slug
	 * @return array{zone_id:string,before:?callable,after:?callable,controls:array<int,array{id:string,label:string,component:string,component_context:array<string,mixed>,order:int}>}
	 */
	private function get_submit_controls_for_page(string $page_slug): array {
		return $this->submit_controls[$page_slug] ?? array(
			'zone_id'  => self::DEFAULT_SUBMIT_ZONE,
			'before'   => null,
			'after'    => null,
			'controls' => array(),
		);
	}

	/**
	 * Render submit controls for the current page.
	 *
	 * @param array{zone_id:string,before:?callable,after:?callable,controls:array<int,array{id:string,label:string,component:string,component_context:array<string,mixed>,order:int}>} $submit_controls
	 * @return string
	 */
	private function render_submit_controls(string $page_slug, array $submit_controls): string {
		$zone_id  = $submit_controls['zone_id']  ?? self::DEFAULT_SUBMIT_ZONE;
		$controls = $submit_controls['controls'] ?? array();
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

		if (empty($controls)) {
			return $this->render_default_submit_controls($page_slug);
		}

		return $this->render_submit_zone($page_slug, $zone_id, $submit_controls, $controls);
	}

	/**
	 * Render a single submit controls zone.
	 *
	 * @param array{zone_id?:string,before:?callable,after:?callable,controls?:array<int,array{id:string,label:string,component:string,component_context:array<string,mixed>,order:int}>} $zone_meta
	 * @param array<int,array{id:string,label:string,component:string,component_context:array<string,mixed>,order:int}> $controls
	 * @return string
	 */
	private function render_submit_zone(string $page_slug, string $zone_id, array $zone_meta, array $controls): string {
		if ($this->form_session === null) {
			$this->_start_form_session();
		}

		$content = '';
		foreach ($controls as $control) {
			$content .= $this->render_submit_control($control);
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
				'zone_id' => $zone_id,
				'content' => $content_with_callbacks,
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
	private function render_submit_control(array $control): string {
		if ($this->form_session === null) {
			$this->_start_form_session();
		}

		$component  = $control['component']         ?? '';
		$context    = $control['component_context'] ?? array();
		$control_id = $control['id']                ?? '';
		$label      = $control['label']             ?? '';

		$context['field_id']  = $control_id;
		$context['_field_id'] = $control_id;
		$context['label']     = $label;
		$context['_label']    = $label;

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
	 * Render default submit controls used when no custom definition exists.
	 */
	private function render_default_submit_controls(string $page_slug): string {
		$button = (new ButtonBuilder('default-primary', 'Save Changes'))
			->type('submit')
			->variant('primary');

		$payload = $button->to_array();

		return $this->render_submit_zone(
			$page_slug,
			self::DEFAULT_SUBMIT_ZONE,
			array('before' => null, 'after' => null),
			array(
				array(
					'id'                => $payload['id'],
					'label'             => $payload['label'],
					'component'         => $payload['component'],
					'component_context' => array_merge($payload['component_context'], array('type' => 'submit')),
					'order'             => $payload['order'],
				),
			)
		);
	}

	/**
	 * Boot admin: register settings, sections, fields, and menu pages.
	 *
	 * @return void
	 */
	public function boot(): void {
		$hooks = array(
			array(
				'hook'     => 'admin_init',
				'callback' => function () {
					$this->_register_setting();
					// Note: We don't register individual fields/sections with WordPress Settings API
					// because we use custom template rendering instead of do_settings_fields()
				},
			),
			array(
				'hook'     => 'admin_menu',
				'callback' => function () {
					foreach ($this->menu_groups as $group_slug => $group) {
						$meta  = $group['meta'];
						$pages = $group['pages'];
						if (empty($pages)) {
							continue;
						}

						$first_page_slug = array_key_first($pages);
						$submenu_parent  = $meta['parent'];
						$skip_first      = false;

						if ($meta['parent'] === null) {
							$this->_do_add_menu_page(
								$meta['heading'],
								$meta['menu_title'],
								$meta['capability'],
								$group_slug,
								function () use ($first_page_slug) {
									$this->render($first_page_slug);
								},
								$meta['icon'],
								$meta['position']
							);
							$submenu_parent = $group_slug;
							$skip_first     = true;
						} elseif ($meta['parent'] === 'options-general.php') {
							$this->_do_add_options_page(
								$meta['heading'],
								$meta['menu_title'],
								$meta['capability'],
								$group_slug,
								function () use ($first_page_slug) {
									$this->render($first_page_slug);
								},
								$meta['position']
							);
							$submenu_parent = 'options-general.php';
							$skip_first     = true;
						} else {
							$this->_do_add_submenu_page(
								$meta['parent'],
								$meta['heading'],
								$meta['menu_title'],
								$meta['capability'],
								$group_slug,
								function () use ($first_page_slug) {
									$this->render($first_page_slug);
								}
							);
							$submenu_parent = $meta['parent'];
							$skip_first     = true;
						}

						foreach ($pages as $page_slug => $page_meta) {
							if ($skip_first && $page_slug === $first_page_slug) {
								continue;
							}
							$this->_do_add_submenu_page(
								$submenu_parent,
								$page_meta['meta']['heading'],
								$page_meta['meta']['menu_title'],
								$page_meta['meta']['capability'],
								$page_slug,
								function () use ($page_slug) {
									$this->render($page_slug);
								}
							);
						}
					}
				},
			),
		);

		$this->_register_action_hooks($hooks);
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
	 * Render a registered settings page using the template or a default form shell.
	 */
	public function render(string $id_slug, ?array $context = null): void {
		if (!isset($this->pages[$id_slug])) {
			echo '<div class="wrap"><h1>Settings</h1><p>Unknown settings page.</p></div>';
			return;
		}
		$this->_start_form_session();

		$ref  = $this->pages[$id_slug];
		$meta = $this->menu_groups[$ref['group']]['pages'][$ref['page']]['meta'];

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

		$rendered_content = $this->_render_default_sections_wrapper($id_slug, $sections, $effective_values);
		$submit_controls  = $this->get_submit_controls_for_page($id_slug);

		$payload = array(
			...($context ?? array()),
			'heading'           => $meta['heading']     ?? '',
			'description'       => $meta['description'] ?? '',
			'group'             => $group,
			'page_slug'         => $id_slug,
			'page_meta'         => $meta,
			'options'           => $options,
			'section_meta'      => $sections,
			'values'            => $effective_values,
			'content'           => $rendered_content,
			'submit_controls'   => $submit_controls,
			'render_submit'     => fn (): string => $this->render_submit_controls($id_slug, $submit_controls),
			'messages_by_field' => $this->message_handler->get_all_messages(),
		);

		$schemaSummary = array();
		foreach ($internalSchema as $key => $entry) {
			if (!is_array($entry)) {
				continue;
			}
			$sanitizeComponentCount = 0;
			$sanitizeSchemaCount    = 0;
			$validateComponentCount = 0;
			$validateSchemaCount    = 0;
			if (isset($entry['sanitize']) && is_array($entry['sanitize'])) {
				$componentBucket = $entry['sanitize'][ValidatorPipelineService::BUCKET_COMPONENT] ?? null;
				$schemaBucket    = $entry['sanitize'][ValidatorPipelineService::BUCKET_SCHEMA]    ?? null;
				if (is_array($componentBucket)) {
					$sanitizeComponentCount = count($componentBucket);
				}
				if (is_array($schemaBucket)) {
					$sanitizeSchemaCount = count($schemaBucket);
				}
			}
			if (isset($entry['validate']) && is_array($entry['validate'])) {
				$componentBucket = $entry['validate'][ValidatorPipelineService::BUCKET_COMPONENT] ?? null;
				$schemaBucket    = $entry['validate'][ValidatorPipelineService::BUCKET_SCHEMA]    ?? null;
				if (is_array($componentBucket)) {
					$validateComponentCount = count($componentBucket);
				}
				if (is_array($schemaBucket)) {
					$validateSchemaCount = count($schemaBucket);
				}
			}
			$schemaSummary[$key] = array(
				'sanitize_component_count' => $sanitizeComponentCount,
				'sanitize_schema_count'    => $sanitizeSchemaCount,
				'validate_component_count' => $validateComponentCount,
				'validate_schema_count'    => $validateSchemaCount,
				'has_default'              => array_key_exists('default', $entry),
			);
		}

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

		$callback = $this->form_session->get_root_template_callback($id_slug);
		if ($callback !== null) {
			ob_start();
			$callback($payload);
			echo (string) ob_get_clean();
		} else {
			echo $this->form_session->render_element('root-wrapper', $payload, array(
				'root_id'   => $id_slug,
				'page_slug' => $id_slug,
			));
		}
		$this->form_session->enqueue_assets();
	}

	// Protected

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
	public function _register_setting(): void {
		$group = $this->base_options->get_main_option_name() . '_group';
		$this->_do_register_setting($group, $this->base_options->get_main_option_name(), array(
			'sanitize_callback' => array($this, '_sanitize'),
		));
		$this->logger->debug('admin_settings.register_setting', array(
			'group'  => $group,
			'option' => $this->base_options->get_main_option_name(),
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
	public function _sanitize($raw): array {
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

		if (!empty($bundle['bucketed_schema'])) {
			$tmp->_register_internal_schema($bundle['bucketed_schema'], $bundle['metadata'], $bundle['queued_validators']);
		}
		if (!empty($bundle['schema'])) {
			$tmp->_register_internal_schema($bundle['schema']);
		}
		if (!empty($bundle['defaults'])) {
			$tmp->register_schema($bundle['defaults']);
			$tmp->_register_internal_schema($bundle['defaults']);
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
			return $previous;
		}

		$result = $tmp->get_options();
		$this->_log_validation_success('AdminSettings::_sanitize returning sanitized payload.', array(
			'sanitized_payload' => $result,
		));

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
		$scope       = SettingsScopeHelper::parseScope($context);

		// Default scope resolution based on admin context
		if (!$scope instanceof OptionScope) {
			$scope = $baseContext->scope ?? ($this->_do_is_network_admin() ? OptionScope::Network : OptionScope::Site);
		}

		// Validate scope is allowed for AdminSettings (Site and Network only)
		$scope = SettingsScopeHelper::requireAllowed($scope, OptionScope::Site, OptionScope::Network);

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

	// Update handlers

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
				// Log unknown update type (default behavior from FormsBaseTrait)
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
		$this->logger->debug('settings.builder.menu_group.updated', array(
			'container_id' => $container_id,
			'heading'      => $group_data['heading']    ?? null,
			'menu_title'   => $group_data['menu_title'] ?? null,
			'capability'   => $group_data['capability'] ?? null,
			'parent'       => $group_data['parent']     ?? null,
			'icon'         => $group_data['icon']       ?? null,
			'position'     => $group_data['position']   ?? null,
			'order'        => $group_data['order']      ?? null,
		));
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
		if ($type !== 'page') {
			$this->logger->warning('AdminSettings: Unsupported context update type received', array(
				'type'      => $type,
				'data_keys' => array_keys($data)
			));
			return;
		}
		$container_id = $data['container_id'] ?? '';
		$page_data    = $data['page_data']    ?? array();
		$group_id     = $data['group_id']     ?? '';

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

		// Maintain master page lookup for rendering and tests
		$this->pages[$container_id] = array(
			'group' => $group_id,
			'page'  => $container_id,
			'meta'  => $page_data,
		);
		$this->logger->debug('settings.builder.page.updated', array(
			'group_id'    => $group_id,
			'page_slug'   => $container_id,
			'heading'     => $page_data['heading']     ?? null,
			'description' => $page_data['description'] ?? null,
			'menu_title'  => $page_data['menu_title']  ?? null,
			'capability'  => $page_data['capability']  ?? null,
			'order'       => $page_data['order']       ?? null,
			'template'    => $page_data['template']    ?? null,
		));
	}
}
