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
use Ran\PluginLib\Forms\Renderer\FormMessageHandler;
use Ran\PluginLib\Forms\Renderer\FormElementRenderer;
use Ran\PluginLib\Forms\FormServiceSession;
use Ran\PluginLib\Forms\FormService;
use Ran\PluginLib\Forms\FormBaseTrait;
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
class AdminSettings implements SettingsInterface {
	use FormBaseTrait;
	use WPWrappersTrait;


	protected ComponentLoader $views;
	protected RegisterOptions $base_options;

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
	private array $pages = array();

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
		$this->logger       = $logger instanceof Logger ? $logger : $options->get_logger();
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
		$this->form_service    = new FormService($this->components);
		$this->field_renderer  = new FormElementRenderer($this->components, $this->form_service, $this->views, $this->logger);
		$this->message_handler = new FormMessageHandler($this->logger);

		// Configure template overrides for AdminSettings context
		$this->field_renderer->set_template_overrides(array(
			'form-wrapper'    => 'admin.default-page',
			'section-wrapper' => 'shared.section-wrapper',
			'group-wrapper'   => 'shared.group-wrapper',
			'field-wrapper'   => 'shared.field-wrapper',
		));

		$this->_start_form_session();
	}

	/**
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

	/**
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
					unset($this->pages[$existingPage]);
				}
			}
			$this->menu_groups[$slug] = array(
			    'meta'  => $meta,
			    'pages' => $pages,
			);
			foreach (array_keys($pages) as $page_slug) {
				$this->pages[$page_slug] = array('group' => $slug, 'page' => $page_slug);
			}
		});
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

		$ref      = $this->pages[$id_slug];
		$meta     = $this->menu_groups[$ref['group']]['pages'][$ref['page']]['meta'];
		$schema   = $this->base_options->get_schema();
		$group    = $this->main_option . '_group';
		$options  = $this->_do_get_option($this->main_option, array());
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
			$this->form_session->enqueue_assets();
			return;
		}

		$this->_render_default_root($payload);
		$this->form_session->enqueue_assets();
	}

	// Protected

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

	/**
	 * Get system default template for a template type.
	 *
	 * @param string $template_type The template type.
	 *
	 * @return string The system default template key.
	 */
	protected function _get_system_default_template(string $template_type): string {
		$defaults = array(
			'page' => 'form-wrapper',
		);
		return $defaults[$template_type] ?? 'shared.default-wrapper';
	}

	/**
	 * Render the default page template markup.
	 *
	 * @param array<string,mixed> $context
	 * @return void
	 */
	protected function _render_default_root(array $context):void {
		echo $this->views->render('form-wrapper', $context);
	}

	/**
	 * Context specific fender a field wrapper warning.
	 * Can be customised for tables based layouts etc.
	 *
	 * @return string Rendered field HTML.
	 */
	protected function _render_default_field_wrapper_warning($message) {
		return '<div class="error">Field rendering failed: ' . esc_html($message) . '</div>';
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

	/**
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
