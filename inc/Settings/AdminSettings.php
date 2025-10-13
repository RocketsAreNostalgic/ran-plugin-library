<?php
/**
 * Settings: DX-friendly bridge to WordPress Settings API using RegisterOptions.
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
use Ran\PluginLib\Settings\AdminSettingsMenuGroupBuilder;
use Ran\PluginLib\Settings\AdminSettingsInterface;
use Ran\PluginLib\Options\Storage\StorageContext;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Options\OptionScope;
use Ran\PluginLib\Forms\Renderer\FormMessageHandler;
use Ran\PluginLib\Forms\Renderer\FormFieldRenderer;
use Ran\PluginLib\Forms\FormServiceSession;
use Ran\PluginLib\Forms\FormService;
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
	use WPWrappersTrait;
	use ComponentManifestAwareTrait;
	use ComponentRenderingTrait;

	private RegisterOptions $base_options;
	private string $main_option;
	private Logger $logger;
	private ComponentLoader $views;
	private ComponentManifest $components;
	private FormService $form_service;
	private FormFieldRenderer $field_renderer;
	private FormMessageHandler $message_handler;
	private ?array $pending_values = null;

	/**
	 * Accumulates pending admin menu groups prior to WordPress hook registration.
	 * Each group stores menu metadata plus its registered pages for later bootstrapping.
	 *
	 * @var array<string,array{meta:array,page_index?:array,pages:array}>
	 */
	private array $menu_groups = array();

	/**
	 * Reverse lookup map of page slugs to their owning menu group and page identifiers.
	 * Used by `render_page()` to retrieve metadata when handling menu callbacks.
	 *
	 * @var array<string,array{group:string,page:string}>
	 */
	private array $page_index = array();

	/** @var array<string,array<string,array{title:string,description_cb:?callable,order:int,index:int}>> */
	private array $sections = array();
	/** @var array<string,array<string,array<int,array{id:string,label:string,component:string,component_context:array<string,mixed>,order:int,index:int}>>> */
	private array $fields = array();
	/** @var array<string,array<string,array{group_id:string,fields:array<int,array{id:string,label:string,component:string,component_context:array<string,mixed>,order:int,index:int}>,before:?callable,after:?callable,order:int,index:int}>> */
	private array $groups = array();

	private int $__section_index = 0;
	private int $__field_index   = 0;
	private int $__group_index   = 0;

	/** Constructor.
	 *
	 * @param RegisterOptions $options The base RegisterOptions instance.
	 * @param Logger|null $logger Optional logger instance.
	 * @param ComponentManifest|null $components Optional component manifest.
	 * @param FormService|null $form_service Optional form service.
	 * @param FormFieldRenderer|null $field_renderer Optional field renderer.
	 * @param FormMessageHandler|null $message_handler Optional message handler.
	 */
	public function __construct(
		RegisterOptions $options,
		?Logger $logger = null,
		?ComponentManifest $components = null,
		?FormService $form_service = null,
		?FormFieldRenderer $field_renderer = null,
		?FormMessageHandler $message_handler = null
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
		$this->views       = new ComponentLoader(__DIR__ . '/views');
		$this->components  = $components instanceof ComponentManifest
			? $components
			: new ComponentManifest(new ComponentLoader(dirname(__DIR__) . '/Forms/Components'), $this->logger);
		$this->form_service = $form_service instanceof FormService ? $form_service : new FormService($this->components);

		// Initialize shared infrastructure
		$this->field_renderer = $field_renderer instanceof FormFieldRenderer
			? $field_renderer
			: new FormFieldRenderer($this->components, $this->form_service, $this->views, $this->logger);
		$this->message_handler = $message_handler instanceof FormMessageHandler
			? $message_handler
			: new FormMessageHandler($this->logger);

		// Configure template overrides for AdminSettings context
		$this->field_renderer->set_template_overrides(array(
			'field-wrapper' => 'shared.field-wrapper',
			'section'       => 'shared.section',
			'form-wrapper'  => 'shared.form-wrapper'
		));

		$this->start_form_session();
	}

	/**
	 * Start a new form session.
	 */
	private function start_form_session(): void {
		$this->form_session = $this->form_service->start_session();
	}

	/**
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

	/**
	 * Boot admin: register settings, sections, fields, and menu pages.
	 */
	public function boot(): void {
		// Register setting and sections/fields
		$this->_do_add_action('admin_init', function () {
			$this->_register_setting();
			foreach ($this->menu_groups as $group) {
				foreach ($group['pages'] as $page_slug => $page) {
					$sections = $page['sections'];
					uasort($sections, function ($a, $b) {
						return ($a['order'] <=> $b['order']) ?: ($a['index'] <=> $b['index']);
					});
					foreach ($sections as $section_id => $meta) {
						$desc = $meta['description_cb'] ?? function () {
						};
						$this->_do_add_settings_section($section_id, $meta['title'], $desc, $page_slug);

						$rawFields = $page['fields'][$section_id] ?? array();
						$groups    = $page['groups'][$section_id] ?? array();
						uasort($groups, function ($a, $b) {
							return ($a['order'] <=> $b['order']) ?: ($a['index'] <=> $b['index']);
						});
						usort($rawFields, function ($a, $b) {
							return ($a['order'] <=> $b['order']) ?: ($a['index'] <=> $b['index']);
						});

						$combined = array();
						foreach ($groups as $gmeta) {
							$combined[] = array('type' => 'group', 'order' => $gmeta['order'], 'index' => $gmeta['index'], 'group' => $gmeta);
						}
						foreach ($rawFields as $f) {
							$combined[] = array('type' => 'field', 'order' => $f['order'], 'index' => $f['index'], 'field' => $f);
						}
						usort($combined, function ($a, $b) {
							return ($a['order'] <=> $b['order']) ?: ($a['index'] <=> $b['index']);
						});

						foreach ($combined as $item) {
							if ($item['type'] === 'group') {
								$g = $item['group'];
								if (is_callable($g['before'])) {
									$beforeCb = $g['before'];
									$this->_do_add_settings_field($g['group_id'] . '__start', '', $beforeCb, $page_slug, $section_id);
								}
								$gf = $g['fields'];
								usort($gf, function ($a, $b) {
									return ($a['order'] <=> $b['order']) ?: ($a['index'] <=> $b['index']);
								});
								foreach ($gf as $f) {
									$this->_register_settings_field($page_slug, $section_id, $f);
								}
								if (is_callable($g['after'])) {
									$afterCb = $g['after'];
									$this->_do_add_settings_field($g['group_id'] . '__end', '', $afterCb, $page_slug, $section_id);
								}
							} else {
								$f = $item['field'];
								$this->_register_settings_field($page_slug, $section_id, $f);
							}
						}
					}
				}
			}
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
							$this->render_page($first_page_slug);
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
							$this->render_page($first_page_slug);
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
							$this->render_page($first_page_slug);
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
							$this->render_page($page_slug);
						}
					);
				}
			}
		});
	}

	/**
	 * Render a registered settings page using the template or a default form shell.
	 */
	public function render_page(string $page_id_or_slug, ?array $context = null): void {
		$this->start_form_session();

		if (!isset($this->page_index[$page_id_or_slug])) {
			echo '<div class="wrap"><h1>Settings</h1><p>Unknown settings page.</p></div>';
			return;
		}
		$ref     = $this->page_index[$page_id_or_slug];
		$meta    = $this->menu_groups[$ref['group']]['pages'][$ref['page']]['meta'];
		$schema  = $this->base_options->get_schema();
		$group   = $this->main_option . '_group';
		$options = $this->_do_get_option($this->main_option, array());
		$payload = array_merge($context ?? array(), array(
		    'group'           => $group,
		    'page_slug'       => $page_id_or_slug,
		    'page_meta'       => $meta,
		    'schema'          => $schema,
		    'options'         => $options,
		    'section_meta'    => $this->sections[$ref['page']] ?? array(),
		    'values'          => $options,
		    'render_fields'   => fn () => $this->_do_settings_fields($group),
		    'render_sections' => fn () => $this->_do_settings_sections($page_id_or_slug),
		    'render_submit'   => fn () => $this->_do_submit_button(),
		    'errors_by_field' => $this->message_handler->get_all_messages(),
		));

		if (is_callable($meta['template'])) {
			($meta['template'])($payload);
			$this->_enqueue_component_assets();
			return;
		}

		$this->_render_default_collection_template($payload);
		$this->_enqueue_component_assets();
	}

	/**
	 * Resolve warning messages captured during the most recent sanitize pass for a field ID.
	 *
	 * @return array<int,string>
	 */
	private function get_messages_for_field(string $field_id): array {
		$key      = $this->_do_sanitize_key($field_id);
		$messages = $this->message_handler->get_messages_for_field($key);
		return $messages ?? array(
			'warnings' => array(),
			'notices'  => array(),
		);
	}

	/**
	 * Retrieve structured validation messages captured during the most recent operation.
	 *
	 * @return array<string, array{warnings: array<int, string>, notices: array<int, string>}>
	 */
	public function take_messages(): array {
		$messages = $this->message_handler->get_all_messages();
		$this->message_handler->clear();
		return $messages;
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

	/**
		* Register a field with the Settings API, supporting component-aware definitions.
		*
		* @param string $page_slug
		* @param string $section_id
		* @param array<string,mixed> $field
		*/
	protected function _register_settings_field(string $page_slug, string $section_id, array $field): void {
		$field_id = isset($field['id']) ? (string) $field['id'] : '';
		if ($field_id === '') {
			return;
		}

		$label     = isset($field['label']) ? (string) $field['label'] : '';
		$component = isset($field['component']) && is_string($field['component']) ? trim($field['component']) : '';
		if ($component === '') {
			throw new \InvalidArgumentException(sprintf('Field "%s" on page "%s" requires a component alias.', $field_id, $page_slug));
		}
		$context = $field['component_context'] ?? array();
		if (!is_array($context)) {
			throw new \InvalidArgumentException(sprintf('Field "%s" on page "%s" must provide an array component_context.', $field_id, $page_slug));
		}

		// Inject component validators automatically
		$this->_inject_component_validators($field_id, $component);

		$main_option = $this->main_option;
		$callback    = function () use ($component, $field_id, $label, $context, $main_option) {
			$this->start_form_session();

			// Get stored values
			$storedValues = $this->_do_get_option($main_option, array());
			$values       = is_array($storedValues) ? $storedValues : array();

			// Use effective values from message handler (handles pending values)
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

				$out = $this->field_renderer->render_field_component(
					$component,
					$field_id,
					$label,
					$field_context,
					$effective_values,
					'direct-output' // AdminSettings handles its own page wrapper
				);

				echo $out;
			} catch (\Throwable $e) {
				$this->logger->error('AdminSettings: Field rendering failed', array(
					'field_id'  => $field_id,
					'component' => $component,
					'exception' => $e
				));
				echo '<p class="error">Field rendering failed: ' . esc_html($e->getMessage()) . '</p>';
			}
		};

		$this->_render_default_collection_template($context);
		$this->_enqueue_component_assets();
	}

	/**
	 * Register the setting with WordPress Settings API and wire sanitize callback.
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
	 * Render the default admin settings template when no custom template is provided.
	 *
	 * @param array<string,mixed> $context
	 */
	protected function _render_default_collection_template(array $context): void {
		try {
			echo $this->views->render('admin.default-page', $context);
		} catch (\LogicException $e) {
			$this->logger->error('AdminSettings default template render failed.', array('message' => $e->getMessage()));
			throw $e;
		}
	}
}
