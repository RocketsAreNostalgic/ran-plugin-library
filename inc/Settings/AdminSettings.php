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
use Ran\PluginLib\Settings\ComponentRenderingTrait;
use Ran\PluginLib\Settings\AdminSettingsMenuGroupBuilder; //
use Ran\PluginLib\Settings\AdminSettingsInterface;
use Ran\PluginLib\Options\Storage\StorageContext;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Options\OptionScope;
use Ran\PluginLib\Forms\Renderer\FormMessageHandler;
use Ran\PluginLib\Forms\Renderer\FormFieldRenderer;
use Ran\PluginLib\Forms\FormServiceSession;
use Ran\PluginLib\Forms\FormService;
use Ran\PluginLib\Forms\FormBaseTrait;
use Ran\PluginLib\Forms\Component\ComponentManifestAwareTrait;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use Ran\PluginLib\Forms\Component\ComponentLoader;

/**
 * Admin settings facade that coordinates Settings API registration with a scoped `RegisterOptions`
 * instance and shared `FormService` rendering session.
 *
 * Responsibilities:
 * - Collect menu groups, pages, sections, fields, and groups prior to hook registration.
 * - Resolve scope-aware storage contexts via the injected `RegisterOptions` implementation.
 * - Render component-driven admin forms and enqueue captured assets through the active session.
 */
class AdminSettings implements AdminSettingsInterface {
	use FormBaseTrait;
	use WPWrappersTrait;
	use ComponentRenderingTrait;
	use ComponentManifestAwareTrait;

	protected string $main_option;
	protected ?array $pending_values = null;
	protected ComponentLoader $views;
	protected ComponentManifest $components;
	protected FormService $form_service;
	protected FormFieldRenderer $field_renderer;
	protected FormMessageHandler $message_handler;
	protected ?FormServiceSession $form_session = null;
	protected RegisterOptions $base_options;
	protected Logger $logger;

	/**
	 * Accumulates pending admin menu groups prior to WordPress hook registration.
	 * Each group stores menu metadata plus its registered pages for later bootstrapping.
	 *
	 * @var array<string, array{meta:array, page_index?:array, pages:array}>
	 */
	private array $menu_groups = array();

	/**
	 * Reverse lookup map of page slugs to their owning menu group and page identifiers.
	 * Used by `render()` to retrieve metadata when handling menu callbacks.
	 *
	 * @var array<string, array{group:string, page:string}>
	 */
	private array $page_index = array();

	// Settings structure: sections, fields, and groups organized by page

	/** @var array<string, array<string, array{title:string, description_cb:?callable, order:int, index:int}>> */
	private array $sections = array();
	/** @var array<string, array<string, array<int, array{id:string, label:string, component:string, component_context:array<string,mixed>, order:int, index:int}>>> */
	private array $fields = array();
	/** @var array<string, array<string, array{group_id:string, fields:array<int, array{id:string, label:string, component:string, component_context:array<string,mixed>, order:int, index:int}>, before:?callable, after:?callable, order:int, index:int}>> */
	private array $groups = array();

	// Template override system: hierarchical template customization
	/** @var array<string, array<string, string>> */
	private array $default_template_overrides = array();
	/** @var array<string, array<string, string>> */
	private array $root_template_overrides = array();
	/** @var array<string, array<string, string>> */
	private array $section_template_overrides = array();
	/** @var array<string, array<string, string>> */
	private array $group_template_overrides = array();
	/** @var array<string, array<string, string>> */
	private array $field_template_overrides = array();


	private int $__section_index = 0;
	private int $__group_index   = 0;
	private int $__field_index   = 0;

	/** Constructor.
	 *
	 * @param RegisterOptions $options The base RegisterOptions instance.
	 * @param ComponentManifest $components Component manifest (required).
	 * @param Logger|null $logger Optional logger instance.
	 */
	public function __construct(
		RegisterOptions $options,
		ComponentManifest $components,
		?Logger $logger = null
	) {
		// RegisterOptions will lazy instantiate a logger if none is provided
		$this->logger = $logger instanceof Logger ? $logger : $options->get_logger();
		$this->base_options = $options;

		$context = $options->get_storage_context();
		if ($context->scope !== OptionScope::Site) {
			$received = $context->scope instanceof OptionScope ? $context->scope->value : 'unknown';
			$this->logger->error('AdminSettings requires site context; received ' . $received . '.');
			throw new \InvalidArgumentException('AdminSettings requires site context; received ' . $received . '.');
		}

		$this->main_option = $options->get_main_option_name();
		$this->components  = $components;
		$this->views       = $components->get_component_loader();

		// Register AdminSettings template overrides
		$this->views->register('admin.default-page', '../../Settings/views/admin/default-page.php');

		// Initialize AdminSettings-specific infrastructure
		$this->form_service = new FormService($this->components);
		$this->field_renderer = new FormFieldRenderer($this->components, $this->form_service, $this->views, $this->logger);
		$this->message_handler = new FormMessageHandler($this->logger);

		// Configure template overrides for AdminSettings context
		$this->field_renderer->set_template_overrides(array(
			'form-wrapper'  => 'admin.default-page',
		));

		$this->_start_form_session();
	}

	/** ✅✅
	 * Resolve the correctly scoped RegisterOptions instance for current admin context.
	 * Callers can chain fluent API on the returned object.
	 *
	 * @param array<string,mixed>|null $context Optional resolution context.
	 *
	 * @return RegisterOptions
	 */
	public function resolve_options(?array $context = null): RegisterOptions {
		$resolved = $this->_resolve_context($context ?? array());
		return $this->base_options->with_context($resolved['storage']);
	}

	/** ✅❌
	 * Boot admin: register settings, sections, fields, and menu pages.
	 */
	public function boot(): void {
		// Register setting for save flow (options.php submission)
		$this->_do_add_action('admin_init', function () {
			$this->_register_setting();
			// Note: We don't register individual fields/sections with WordPress Settings API
			// because we use custom template rendering instead of do_settings_fields()
		});

		$this->_do_add_action('admin_menu', function () {
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
						$meta['page_title'],
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
						$meta['page_title'],
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
						$meta['page_title'],
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
						$page_meta['meta']['page_title'],
						$page_meta['meta']['menu_title'],
						$page_meta['meta']['capability'],
						$page_slug,
						function () use ($page_slug) {
							$this->render($page_slug);
						}
					);
				}
			}
		});
	}

	/** 🚨
	 * Create a simple settings page under the WordPress Settings menu.
	 *
	 * UserSettings collerary is the collection() method.
	 *
	 * This is a convenience method for the common case of adding a single settings page.
	 * For multiple pages or custom menu groups, use menu_group() instead.
	 *
	 * @param string $page_slug The page slug (used in URL)
	 * @param string $page_title The page title (shown in browser title and page header)
	 * @param string $menu_title The menu title (shown in WordPress admin sidebar)
	 *
	 * @return AdminSettingsPageBuilder
	 */
	public function page(string $page_slug, string $page_title, string $menu_title): AdminSettingsPageBuilder {
		// Create a menu group for this page under Settings menu
		$group_slug = $page_slug . '_settings_group';

		return $this->menu_group($group_slug)
			->page_heading($page_title)
			->menu_label($menu_title)
			->capability('manage_options')
			->parent('options-general.php')  // Default to Settings menu
			->page($page_slug);
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
		$page_title    = $existing['page_title'] ?? $default_title;
		$menu_title    = $existing['menu_title'] ?? $page_title;

		$initial_meta = array(
		    'page_title' => $page_title,
		    'menu_title' => $menu_title,
		    'capability' => $existing['capability'] ?? 'manage_options',
		    'parent'     => array_key_exists('parent', $existing ?? array()) ? $existing['parent'] : null,
		    'icon'       => $existing['icon']     ?? null,
		    'position'   => $existing['position'] ?? null,
		);

		return new AdminSettingsMenuGroupBuilder($this, $group_slug, $initial_meta, function (string $slug, array $meta, array $pages): void {
			if (isset($this->menu_groups[$slug])) {
				foreach (array_keys($this->menu_groups[$slug]['pages']) as $existingPage) {
					unset($this->page_index[$existingPage]);
				}
			}
			$this->menu_groups[$slug] = array(
			    'meta'  => $meta,
			    'pages' => $pages,
			);
			foreach (array_keys($pages) as $page_slug) {
				$this->page_index[$page_slug] = array('group' => $slug, 'page' => $page_slug);
			}
		});
	}

	/** ✅❌
	 * Render a registered settings page using the template or a default form shell.
	 */
	public function render(string $id_slug, ?array $context = null): void {
		if (!isset($this->page_index[$id_slug])) {
			echo '<div class="wrap"><h1>Settings</h1><p>Unknown settings page.</p></div>';
			return;
		}
		$this->_start_form_session();

		$ref     = $this->page_index[$id_slug];
		$meta    = $this->menu_groups[$ref['group']]['pages'][$ref['page']]['meta'];
		$schema  = $this->base_options->get_schema();
		$group   = $this->main_option . '_group';
		$options = $this->_do_get_option($this->main_option, array());
		$sections = $this->sections[$ref['page']] ?? array();

		// Get effective values from message handler (handles pending values)
		$effective_values = $this->message_handler->get_effective_values($options);

		$rendered_content = $this->_render_default_sections_wrapper($id_slug, $sections, $effective_values);

		$payload = array_merge($context ?? array(), array(
		    'group'           => $group,
		    'page_slug'       => $id_slug,
		    'page_meta'       => $meta,
		    'schema'          => $schema,
		    'options'         => $options,
		    'section_meta'    => $sections,
		    'values'          => $effective_values,
		    'content'         => $rendered_content,
		    'render_submit'   => fn () => $this->_do_submit_button(),
		    'errors_by_field' => $this->message_handler->get_all_messages(),
		));

		if (is_callable($meta['template'])) {
			($meta['template'])($payload);
			$this->_enqueue_component_assets();
			return;
		}

		$this->_render_default_root($payload);
		$this->_enqueue_component_assets();
	}

	/** ✅✅
	 * Retrieve structured validation messages captured during the most recent operation.
	 *
	 * @return array<string, array{warnings: array<int, string>, notices: array<int, string>}>
	 */
	public function take_messages(): array {
		$messages = $this->message_handler->get_all_messages();
		$this->message_handler->clear();
		return $messages ?? array(
			'warnings' => array(),
			'notices'  => array(),
		);
	}

	/** ✅✅⚠️
	 * Set default template overrides for this AdminSettings instance.
	 *
	 * @param array<string, string> $template_overrides Template overrides keyed by template type.
	 *
	 * @return void
	 */
	public function set_default_template_overrides(array $template_overrides): void {
		$this->default_template_overrides = array_merge($this->default_template_overrides, $template_overrides);
		$this->logger->debug('AdminSettings: Default template overrides set', array(
			'overrides' => $template_overrides
		));
	}

	/** ✅✅
	 * Get default template overrides for this AdminSettings instance.
	 *
	 * @return array<string, string>
	 */
	public function get_default_template_overrides(): array {
		return $this->default_template_overrides;
	}

	/** ✅✅⚠️
	 * Set template overrides for the root container (page for AdminSettings).
	 *
	 * @param string $root_id_slug The root container ID (page slug).
	 * @param array<string, string> $template_overrides Template overrides keyed by template type.
	 *
	 * @return void
	 */
	public function set_root_template_overrides(string $root_id_slug, array $template_overrides): void {
		$this->root_template_overrides[$root_id_slug] = $template_overrides;
		$this->logger->debug('AdminSettings: Root template overrides set', array(
			'id_slug' => $root_id_slug,
			'overrides' => array_keys($template_overrides)
		));
	}

	/** ✅✅
	 * Get template overrides for the root container (page for AdminSettings).
	 *
	 * @param string $root_id_slug The root container ID (page slug).
	 *
	 * @return array<string, string>
	 */
	public function get_root_template_overrides(string $root_id_slug): array {
		return $this->root_template_overrides[$root_id_slug] ?? array();
	}

	/** ✅✅⚠️
	 * Set template overrides for a specific section.
	 *
	 * @param string $section_id The section ID.
	 * @param array<string, string> $template_overrides Template overrides keyed by template type.
	 *
	 * @return void
	 */
	public function set_section_template_overrides(string $section_id, array $template_overrides): void {
		$this->section_template_overrides[$section_id] = $template_overrides;
		$this->logger->debug('AdminSettings: Section template overrides set', array(
			'section_id' => $section_id,
			'overrides'  => $template_overrides
		));
	}

	/** ✅✅
	 * Get template overrides for a specific section.
	 *
	 * @param string $section_id The section ID.
	 *
	 * @return array<string, string>
	 */
	public function get_section_template_overrides(string $section_id): array {
		return $this->section_template_overrides[$section_id] ?? array();
	}

	/** ✅✅⚠️
	 * Set template overrides for a specific group.
	 *
	 * @param string $group_id The group ID.
	 * @param array<string, string> $template_overrides Template overrides keyed by template type.
	 *
	 * @return void
	 */
	public function set_group_template_overrides(string $group_id, array $template_overrides): void {
		$this->group_template_overrides[$group_id] = $template_overrides;
		$this->logger->debug('AdminSettings: Group template overrides set', array(
			'group_id'  => $group_id,
			'overrides' => $template_overrides
		));
	}

	/** ✅✅
	 * Get template overrides for a specific group.
	 *
	 * @param string $group_id The group ID.
	 *
	 * @return array<string, string>
	 */
	public function get_group_template_overrides(string $group_id): array {
		return $this->group_template_overrides[$group_id] ?? array();
	}

	/** ✅✅⚠️
	 * Set template overrides for a specific field.
	 *
	 * @param string $field_id The field ID.
	 * @param array<string, string> $template_overrides Template overrides keyed by template type.
	 *
	 * @return void
	 */
	public function set_field_template_overrides(string $field_id, array $template_overrides): void {
		$this->field_template_overrides[$field_id] = $template_overrides;
		$this->logger->debug('AdminSettings: Field template overrides set', array(
			'field_id'  => $field_id,
			'overrides' => $template_overrides
		));
	}

	/** ✅✅
	 * Get template overrides for a specific field.
	 *
	 * @param string $field_id The field ID.
	 *
	 * @return array<string, string>
	 */
	public function get_field_template_overrides(string $field_id): array {
		return $this->field_template_overrides[$field_id] ?? array();
	}

	/** ✅✅⚠️
	 * Resolve template with hierarchical fallback.
	 *
	 * @param string $template_type The template type (e.g., 'page', 'section', 'group', 'field-wrapper').
	 * @param array<string, mixed> $context Resolution context containing field_id, section_id, page_slug, etc.
	 *
	 * @return string The resolved template key.
	 */
	public function resolve_template(string $template_type, array $context = array()): string {
		// 1. Check field-level override (highest priority)
		if (isset($context['field_id'])) {
			$field_overrides = $this->get_field_template_overrides($context['field_id']);
			if (isset($field_overrides[$template_type])) {
				$this->logger->debug('AdminSettings: Template resolved via field override', array(
					'template_type' => $template_type,
					'template'      => $field_overrides[$template_type],
					'field_id'      => $context['field_id']
				));
				return $field_overrides[$template_type];
			}
		}

		// 2. Check group-level override
		if (isset($context['group_id'])) {
			$group_overrides = $this->get_group_template_overrides($context['group_id']);
			if (isset($group_overrides[$template_type])) {
				$this->logger->debug('AdminSettings: Template resolved via group override', array(
					'template_type' => $template_type,
					'template'      => $group_overrides[$template_type],
					'group_id'      => $context['group_id']
				));
				return $group_overrides[$template_type];
			}
		}

		// 3. Check section-level override
		if (isset($context['section_id'])) {
			$section_overrides = $this->get_section_template_overrides($context['section_id']);
			if (isset($section_overrides[$template_type])) {
				$this->logger->debug('AdminSettings: Template resolved via section override', array(
					'template_type' => $template_type,
					'template'      => $section_overrides[$template_type],
					'section_id'    => $context['section_id']
				));
				return $section_overrides[$template_type];
			}
		}

		// 4. Check page-level override
		if (isset($context['id_slug'])) {
			$page_overrides = $this->get_root_template_overrides($context['page_slug']);
			if (isset($page_overrides[$template_type])) {
				$this->logger->debug('AdminSettings: Template resolved via page override', array(
					'template_type' => $template_type,
					'template'      => $page_overrides[$template_type],
					'page_slug'     => $context['page_slug']
				));
				return $page_overrides[$template_type];
			}
		}

		// 5. Check class instance defaults
		if (isset($this->default_template_overrides[$template_type])) {
			$this->logger->debug('AdminSettings: Template resolved via class default', array(
				'template_type' => $template_type,
				'template'      => $this->default_template_overrides[$template_type]
			));
			return $this->default_template_overrides[$template_type];
		}

		// 6. System defaults (lowest priority)
		$system_default = $this->_get_system_default_template($template_type);
		$this->logger->debug('AdminSettings: Template resolved via system default', array(
			'template_type' => $template_type,
			'template'      => $system_default
		));
		return $system_default;
	}

	// Protected

	/** ✅✅
	 * Start a new form session.
	 */
	protected function _start_form_session(): void {
		$this->form_session = $this->form_service->start_session();
	}

	/**
	 * Register the setting with WordPress Settings API and wire sanitize callback.
	 *
	 * This enables the options.php save flow while using custom template rendering.
	 * We register the main setting but NOT individual fields/sections since we use
	 * custom templates instead of do_settings_fields().
	 *
	 * Uses a default group derived from the main option name.
	 */
	public function _register_setting(): void {
		$group = $this->base_options->get_main_option_name() . '_group';
		$this->_do_register_setting($group, $this->base_options->get_main_option_name(), array(
			'sanitize_callback' => array($this, '_sanitize'),
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
	 * distinguishes between site and network scopes via `_do_is_network_admin()`. Blog scope requires
	 * an explicit `blog_id` and is handled by callers that deliberately pass that context to
	 * `resolve_context()` when they truly need cross-blog administration. This tooling has not been
	 * extended to handle cross-network administration of other blogs.
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

		// Clear previous messages and set pending values
		$this->message_handler->clear();
		$this->message_handler->set_pending_values($payload);

		$scope    = $this->_do_is_network_admin() ? OptionScope::Network : OptionScope::Site;
		$resolved = $this->_resolve_context(array('scope' => $scope));
		$tmp      = $this->base_options->with_context($resolved['storage']);
		$schema   = $this->base_options->get_schema();
		if (!empty($schema)) {
			$tmp->register_schema($schema);
		}
		$policy = $this->base_options->get_write_policy();
		if ($policy !== null) {
			$tmp->with_policy($policy);
		}
		// Stage options and check for validation failures
		$tmp->stage_options($payload);
		$messages = $tmp->take_messages();

		// Set messages in FormMessageHandler
		$this->message_handler->set_messages($messages);

		// Check if there are validation failures (warnings)
		if ($this->message_handler->has_validation_failures()) {
			$this->logger->info('AdminSettings::_sanitize validation failed; returning previous option payload.', array(
				'warning_count'       => $this->message_handler->get_warning_count(),
				'previous_payload'    => $previous,
				'validation_messages' => $messages
			));
			return $previous;
		}

		// Only return sanitized data if validation passed
		$result = $tmp->get_options();
		$this->logger->debug('AdminSettings::_sanitize returning sanitized payload.', array(
			'sanitized_payload' => $result
		));

		// Clear pending values on success
		$this->message_handler->set_pending_values(null);
		$this->pending_values = null;

		return $result;
	}

	/** ✅✅
	 * Resolve warning messages captured during the most recent sanitize pass for a field ID.
	 *
	 * @return array<int,string>
	 */
	protected function _get_messages_for_field(string $field_id): array {
		$key      = $this->_do_sanitize_key($field_id);
		$messages = $this->message_handler->get_messages_for_field($key);
		return $messages ?? array(
			'warnings' => array(),
			'notices'  => array(),
		);
	}

	/** ✅❌
	 * Get system default template for a template type.
	 *
	 * @param string $template_type The template type.
	 *
	 * @return string The system default template key.
	 */
	protected function _get_system_default_template(string $template_type): string {
		$defaults = array(
			'page' => 'admin.default-page',
		);
		return $defaults[$template_type] ?? 'shared.default-wrapper';
	}

	/** ✅❌
	 * Render the default page template markup.
	 *
	 * @param array<string,mixed> $context
	 * @return void
	 */
	protected function _render_default_root(array $context) {
		echo $this->views->render('admin.default-page', $context);
	}

	/** ✅✅
	 * Render sections and fields for an admin page.
	 *
	 * @param string $root_id_slug Page identifier.
	 * @param array  $sections  Section metadata map.
	 * @param array  $values    Current option values.
	 *
	 * @return string Rendered HTML markup.
	 */
	protected function _render_default_sections_wrapper(string $id_slug, array $sections, array $values): string {
		$prepared_sections = array();
		$groups_map = $this->groups[$id_slug] ?? array();
		$fields_map = $this->fields[$id_slug] ?? array();

		foreach ($sections as $section_id => $meta) {
			$groups = $groups_map[$section_id] ?? array();
			$fields = $fields_map[$section_id] ?? array();

			// Sort groups and fields by order
			uasort($groups, function ($a, $b) {
				return ($a['order'] <=> $b['order']) ?: ($a['index'] <=> $b['index']);
			});
			usort($fields, function ($a, $b) {
				return ($a['order'] <=> $b['order']) ?: ($a['index'] <=> $b['index']);
			});

			// Combine groups and fields
			$items = array();
			foreach ($groups as $group) {
				$group_fields = $group['fields'];
				usort($group_fields, function ($a, $b) {
					return ($a['order'] <=> $b['order']) ?: ($a['index'] <=> $b['index']);
				});
				$items[] = array(
					'type'   => 'group',
					'before' => $group['before'] ?? null,
					'after'  => $group['after']  ?? null,
					'fields' => $group_fields,
				);
			}
			foreach ($fields as $field) {
				$items[] = array('type' => 'field', 'field' => $field);
			}

			// Sort items (groups first, then fields)
			usort($items, function ($a, $b) {
				return ($a['type'] === 'group' ? 0 : 1) <=> ($b['type'] === 'group' ? 0 : 1);
			});

			$prepared_sections[] = array(
				'title'          => (string) $meta['title'],
				'description_cb' => $meta['description_cb'] ?? null,
				'items'          => $items,
			);
		}

		return $this->views->render('section', array(
			'sections'       => $prepared_sections,
			'group_renderer' => fn (array $group): string => $this->_render_default_group_wraper($group, $values),
			'field_renderer' => fn (array $field): string => $this->_render_default_field_wrapper($field, $values),
		));
	}

	/** ✅✅
	 * Render a group of fields for admin settings.
	 *
	 * @param array<string,mixed> $group
	 * @param array<string,mixed> $values
	 *
	 * @return string Rendered group HTML.
	 */
	protected function _render_default_group_wraper(array $group, array $values): string {
		$content_parts = array();

		if (isset($group['before']) && is_callable($group['before'])) {
			ob_start();
			($group['before'])();
			$content_parts[] = (string) ob_get_clean();
		}

		foreach ($group['fields'] ?? array() as $field) {
			$content_parts[] = $this->_render_default_field_wrapper($field, $values);
		}

		if (isset($group['after']) && is_callable($group['after'])) {
			ob_start();
			($group['after'])();
			$content_parts[] = (string) ob_get_clean();
		}

		return $this->views->render('group-wrapper', array(
			'content_parts' => $content_parts,
		));
	}

	/** ✅
	 * Render a single field wrappper
	 *
	 * @param array<string,mixed> $field
	 * @param array<string,mixed> $values
	 *
	 * @return string Rendered field HTML.
	 */
	protected function _render_default_field_wrapper(array $field, array $values): string {
		if (empty($field)) {
			return '';
		}

		$field_id  = isset($field['id']) ? (string) $field['id'] : '';
		$label     = isset($field['label']) ? (string) $field['label'] : '';
		$component = isset($field['component']) && is_string($field['component']) ? trim($field['component']) : '';

		if ($component === '') {
			$this->logger->error('AdminSettings field missing component metadata.', array('field' => $field_id));
			throw new \InvalidArgumentException(sprintf('AdminSettings field "%s" requires a component alias.', $field_id ?: 'unknown'));
		}

		$context = $field['component_context'] ?? array();
		if (!is_array($context)) {
			$this->logger->error('AdminSettings field provided a non-array component_context.', array('field' => $field_id));
			throw new \InvalidArgumentException(sprintf('AdminSettings field "%s" must provide an array component_context.', $field_id ?: 'unknown'));
		}

		// Get effective values from message handler (handles pending values)
		$effective_values = $this->message_handler->get_effective_values($values);

		// Get messages for this field
		$field_messages = $this->message_handler->get_messages_for_field($field_id);

		// Prepare field configuration
		$field_config = array(
			'field_id'          => $field_id,
			'component'         => $component,
			'label'             => $label,
			'component_context' => $context
		);

		// Use FormFieldRenderer for consistent field processing
		try {
			$field_context = $this->field_renderer->prepare_field_context(
				$field_config,
				$effective_values,
				array($field_id => $field_messages)
			);

			$content = $this->field_renderer->render_field_component(
				$component,
				$field_id,
				$label,
				$field_context,
				$effective_values,
				'direct-output' // AdminSettings handles its own wrapper
			);

			if ($content === '') {
				return '';
			}

			// Use shared field-wrapper template
			return $this->views->render('field-wrapper', array(
				'label'               => $label,
				'content'             => $content,
				'field_id'            => $field_id,
				'component_html'      => $content,
				'validation_warnings' => $field_messages['warnings'] ?? array(),
				'display_notices'     => $field_messages['notices']  ?? array()
			));
		} catch (\Throwable $e) {
			$this->logger->error('AdminSettings: Field rendering failed', array(
				'field_id'  => $field_id,
				'component' => $component,
				'exception' => $e
			));
			return '<div class="error">Field rendering failed: ' . esc_html($e->getMessage()) . '</div>';
		}
	}

	/** ✅✅⚠️
	 * Discover and inject component validators for a field.
	 *
	 * @param string $field_id Field identifier
	 * @param string $component Component name
	 * @return void
	 */
	protected function _inject_component_validators(string $field_id, string $component): void {
		// Get component validator factories from ComponentManifest
		$validator_factories = $this->components->validator_factories();

		if (isset($validator_factories[$component])) {
			$validator_factory = $validator_factories[$component];

			// Create the validator instance
			if (is_callable($validator_factory)) {
				$validator_instance = $validator_factory();

				// Create a callable wrapper that adapts ValidatorInterface to RegisterOptions signature
				$validator_callable = function($value, callable $emitWarning) use ($validator_instance): bool {
					return $validator_instance->validate($value, array(), $emitWarning);
				};

				// Inject the validator at the beginning of the validation chain
				$this->base_options->prepend_validator($field_id, $validator_callable);

				$this->logger->debug('AdminSettings: Component validator injected', array(
					'field_id'  => $field_id,
					'component' => $component
				));
			}
		}
	}

	// Resolvers

	/**
	 * Resolve the correctly scoped RegisterOptions instance for current admin context.
	 *
	 * @param array<string,mixed> $context
	 * @return array{storage: StorageContext, scope: OptionScope}
	 */
	protected function _resolve_blog_scope(array $context, StorageContext $baseContext): array {
		$blogId = isset($context['blog_id']) ? (int) $context['blog_id'] : ($baseContext->blog_id ?? 0);
		if ($blogId <= 0) {
			$blogId = $this->_do_get_current_blog_id();
		}
		if ($blogId <= 0) {
			throw new \InvalidArgumentException('AdminSettings requires a valid blog_id for blog scope.');
		}

		return array(
		    'storage' => StorageContext::forBlog($blogId),
		    'scope'   => OptionScope::Blog,
		);
	}

	/** ✅❌
	 * Resolve the correctly scoped RegisterOptions instance for current admin context.
	 *
	 * @param array<string,mixed> $context
	 * @return array{storage: StorageContext, scope: OptionScope}
	 */
	protected function _resolve_context(array $context): array {
		$context = $context ?? array();

		$baseContext = $this->base_options->get_storage_context();
		$scope       = SettingsScopeHelper::parseScope($context);
		if (!$scope instanceof OptionScope) {
			$scope = $baseContext->scope ?? ($this->_do_is_network_admin() ? OptionScope::Network : OptionScope::Site);
		}

		$scope = SettingsScopeHelper::requireAllowed($scope, OptionScope::Site, OptionScope::Network, OptionScope::Blog);

		return match ($scope) {
			OptionScope::Site => array(
			    'storage' => StorageContext::forSite(),
			    'scope'   => OptionScope::Site,
			),
			OptionScope::Network => array(
			    'storage' => StorageContext::forNetwork(),
			    'scope'   => OptionScope::Network,
			),
			OptionScope::Blog => $this->_resolve_blog_scope($context, $baseContext),
		};
	}

}
