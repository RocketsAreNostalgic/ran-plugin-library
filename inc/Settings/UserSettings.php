<?php
/**
 * UserSettings: DX-friendly user profile settings UI using RegisterOptions.
 *
 * @package Ran\PluginLib\Settings
 * @author  Ran Plugin Lib <bnjmnrsh@gmail.com>
 * @license GPL-2.0+ <http://www.gnu.org/licenses/gpl-2.0.txt>
 * @link    https://github.com/RocketsAreNostalgic
 * @since   0.2.0
 */

declare(strict_types=1);

namespace Ran\PluginLib\Settings;

use WP_User;
use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Options\Storage\StorageContext;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Options\OptionScope;
use Ran\PluginLib\Forms\Services\FormsErrorHandlerInterface;
use Ran\PluginLib\Forms\Services\FormsCallbackInvoker;
use Ran\PluginLib\Forms\Services\AdminFormsErrorHandler;
use Ran\PluginLib\Forms\Renderer\FormMessageHandler;
use Ran\PluginLib\Forms\Renderer\FormElementRenderer;
use Ran\PluginLib\Forms\FormsService;
use Ran\PluginLib\Forms\FormsCore;
use Ran\PluginLib\Forms\ErrorNoticeRenderer;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use Ran\PluginLib\Config\ConfigInterface;

/**
 * User profile settings facade that bridges WordPress profile hooks with a scoped `RegisterOptions`
 * instance and shared `FormsService` rendering session.
 *
 * Responsibilities:
 * - Manage collections, sections, fields, and groups before WordPress renders the profile UI.
 * - Resolve per-user storage contexts by cloning the injected `RegisterOptions` with user-specific overrides.
 * - Render component-driven profile fields and enqueue captured assets through the active session.
 *
 * Note: WordPress core continues to own capability checks (`edit_user`) and the profile lifecycle. Only the
 * validation and write-policy portions of `RegisterOptions` are exercised here.
 *
 * Likewise WordPress core User setting page provides its own submission block, so UserSettings does not implement
 * a save handler.
 */
class UserSettings extends FormsCore {
	/**
	 * Base context, storage and global captured from the injected RegisterOptions instance.
	 * Retained so subsequent renders and saves can derive user_id/storage defaults.
	 */
	protected StorageContext $base_context;
	protected string $base_storage;
	protected bool $base_global; // Flag from RegisterOptions as fallback for dynamic storage context resolution.

	/**
	 * Collection metadata storage for user profile collections.
	 * Collections represent different groupings of user profile sections (typically 'profile').
	 * Each collection contains template callback and display order information.
	 *
	 * @var array<string, array{template:?callable, order:int}>
	 */
	protected array $collections = array();

	/**
	 * Constructor.
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

		// Phase 2: Scope validation (UserSettings requires User scope)
		$context = $options->get_storage_context();
		if ($context->scope !== OptionScope::User) {
			$received = $context->scope instanceof OptionScope ? $context->scope->value : 'unknown';
			$this->logger->error('UserSettings::__construct received non-user scope RegisterOptions; rejecting.', array('scope' => $received));
			throw new \InvalidArgumentException('UserSettings requires user context; received ' . $received . '.');
		}

		// Phase 3: Base property assignment
		$this->base_options = $options;
		$this->base_context = $context;
		$this->main_option  = $options->get_main_option_name();
		$this->config       = $config;
		// UserSettings-specific: storage and global flags for user context resolution
		$this->base_storage = strtolower($context->user_storage ?? 'meta') === 'option' ? 'option' : 'meta';
		$this->base_global  = $this->base_storage                          === 'option' ? (bool) ($context->user_global ?? false) : false;

		// Phase 4: Component and view setup
		$this->components = $components;
		$this->views      = $components->get_component_loader();

		// Phase 5: Context-specific template registration
		// UserSettings registers complete template hierarchy for profile forms
		// Use absolute paths to ensure templates are found regardless of ComponentLoader's base directory
		$templates_dir = __DIR__ . '/templates/user';
		$this->views->register_absolute('user.root-wrapper', $templates_dir . '/root-wrapper.php');
		$this->views->register_absolute('user.section-wrapper', $templates_dir . '/section-wrapper.php');
		$this->views->register_absolute('user.group-wrapper', $templates_dir . '/group-wrapper.php');
		$this->views->register_absolute('user.fieldset-wrapper', $templates_dir . '/fieldset-wrapper.php');
		$this->views->register_absolute('user.field-wrapper', $templates_dir . '/field-wrapper.php');
		$this->views->register_absolute('fieldset-field-wrapper', $templates_dir . '/field-wrapper.php');

		// Phase 6: Service initialization
		$this->form_service    = new FormsService($this->components, $this->logger);
		$this->field_renderer  = new FormElementRenderer($this->components, $this->form_service, $this->views, $this->logger);
		$this->message_handler = new FormMessageHandler($this->logger);
		$this->field_renderer->set_message_handler($this->message_handler);

		// Phase 7: Form session configuration with context-specific defaults
		$this->_start_form_session();

		// UserSettings overrides all template levels for profile-specific rendering
		$this->form_session->set_form_defaults(array(
			'root-wrapper'           => 'user.root-wrapper',
			'section-wrapper'        => 'user.section-wrapper',
			'group-wrapper'          => 'user.group-wrapper',
			'fieldset-wrapper'       => 'user.fieldset-wrapper',
			'field-wrapper'          => 'user.field-wrapper',
			'fieldset-field-wrapper' => 'user.field-wrapper',
		));
	}

	/**
	 * Add a profile collection (new group) onto the user profile page.
	 *
	 * The AdminSettings collerary is the page() method.
	 *
	 * @param string $id_slug The collection id, defaults to 'profile'.
	 * @param string|callable|null $template Root template override (registered key or callable).
	 * @param array<string,mixed> $args Additional metadata: heading, description, order.
	 *
	 * @return UserSettingsCollectionBuilder
	 */
	public function collection(
		string $id_slug = 'profile',
		string|callable|null $template = null,
		array $args = array()
	): UserSettingsCollectionBuilder {
		$heading     = (string) ($args['heading'] ?? ($this->collections[$id_slug]['heading'] ?? ucwords(str_replace(array('-', '_'), ' ', $id_slug))));
		$description = array_key_exists('description', $args) ? $args['description'] : ($this->collections[$id_slug]['description'] ?? null);
		$order       = isset($args['order']) ? max(0, (int) $args['order']) : ($this->collections[$id_slug]['order'] ?? 10);

		// Start with base meta, then merge in all args to preserve style, before, after, etc.
		$initial_meta = array_merge(
			$args,
			array(
				'order'       => $order,
				'heading'     => $heading,
				'description' => $description,
			)
		);

		$updateFn = $this->_create_update_function();

		$builder = new UserSettingsCollectionBuilder(
			$this,
			$id_slug,
			$initial_meta,
			$updateFn
		);

		if ($template !== null) {
			$builder->template($template);
		}

		return $builder;
	}

	/**
	 * Fluent alias returning the current settings instance.
	 *
	 * Enables chaining like end_collection()->end() to match AdminSettings API.
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
	 * Check if UserSettings should load at all.
	 *
	 * Returns true only if we're on a profile page.
	 * Called BEFORE the builder callback runs to avoid unnecessary work.
	 *
	 * @return bool True if should load, false to skip entirely.
	 */
	protected function _should_load(): bool {
		return $this->_is_profile_page();
	}

	/**
	 * @var bool Whether to skip hook registration (when used via UserSettingsRegistry).
	 */
	private bool $skip_hooks = false;

	/**
	 * Tell UserSettings to skip registering WordPress hooks.
	 *
	 * Used by UserSettingsRegistry which handles hook registration itself.
	 *
	 * @return void
	 */
	public function skip_hook_registration(): void {
		$this->skip_hooks = true;
	}

	/**
	 * Boot user settings: register collections, sections, fields and save handlers.
	 *
	 * By default, uses lazy loading - skips hook registration if not on a profile page.
	 * Set $eager to true to force immediate hook registration regardless of context.
	 *
	 * @param bool $eager If true, skip lazy loading checks and always register hooks.
	 * @return void
	 */
	public function boot(bool $eager = false): void {
		// Note: The profile page check now happens in _should_load() before the callback runs.
		// This boot() method is only called if _should_load() returned true (or eager=true).

		$hooks = array();

		// 1. Render hooks (show_user_profile, edit_user_profile)
		foreach ($this->collections as $id_slug => $meta) {
			$order  = (int) ($meta['order'] ?? 10);
			$order  = $order < 0 ? 0 : $order;
			$render = function ($user) use ($id_slug) {
				if (!($user instanceof \WP_User)) {
					return;
				}
				$this->__render($id_slug, array('user' => $user));
			};

			$hooks[] = array(
				'hook'          => 'show_user_profile',
				'callback'      => $render,
				'priority'      => $order,
				'accepted_args' => 1,
			);
			$hooks[] = array(
				'hook'          => 'edit_user_profile',
				'callback'      => $render,
				'priority'      => $order,
				'accepted_args' => 1,
			);
		}

		// 2. Save hooks (personal_options_update, edit_user_profile_update)
		$save = function ($user_id) {
			$user_id = (int) $user_id;
			if (!$this->_do_current_user_can('edit_user', $user_id)) {
				return; // silent deny to match WP conventions
			}
			$payload = isset($_POST[$this->main_option]) && is_array($_POST[$this->main_option]) ? $_POST[$this->main_option] : array();

			// Handle file uploads - WordPress profile form already has enctype="multipart/form-data"
			$payload = $this->_process_file_uploads($payload);

			$this->__save_settings($payload, array('user_id' => $user_id));
		};

		// User saving their own profile
		$hooks[] = array(
			'hook'          => 'personal_options_update',
			'callback'      => $save,
			'priority'      => 10,
			'accepted_args' => 1,
		);
		// Admin saving another user's profile
		$hooks[] = array(
			'hook'          => 'edit_user_profile_update',
			'callback'      => $save,
			'priority'      => 10,
			'accepted_args' => 1,
		);

		// 3. File upload enctype injection (admin_enqueue_scripts)
		// This must happen in boot() because WordPress's #your-profile form needs
		// the enctype attribute set before our fields render
		$has_file_uploads = array_filter(
			array_keys($this->collections),
			fn(string $id): bool => $this->_container_has_file_uploads($id)
		);
		if ($has_file_uploads !== array()) {
			$hooks[] = array(
				'hook'     => 'admin_enqueue_scripts',
				'callback' => function (string $hook_suffix): void {
					if (!in_array($hook_suffix, array('profile.php', 'user-edit.php'), true)) {
						return;
					}
					/**
					 * Filter whether to inject enctype="multipart/form-data" on the profile form.
					 *
					 * @param bool $inject Whether to inject the enctype attribute. Default true.
					 * @param UserSettings $instance The UserSettings instance.
					 */
					if (!$this->_do_apply_filter('ran_plugin_lib_user_settings_inject_enctype', true, $this)) {
						return;
					}
					$this->_do_wp_add_inline_script(
						'jquery',
						'jQuery(function($){$("#your-profile").attr("enctype","multipart/form-data");});',
						'after'
					);
				},
				'priority'      => 10,
				'accepted_args' => 1,
			);
		}

		// Skip hook registration if managed by UserSettingsRegistry
		if (!$this->skip_hooks) {
			$this->_register_action_hooks($hooks);
		}
	}

	/**
	 * Check if current request is for a user profile page.
	 *
	 * @return bool True if we're on profile.php or user-edit.php.
	 */
	protected function _is_profile_page(): bool {
		// Check pagenow for profile pages
		$pagenow = $GLOBALS['pagenow'] ?? '';
		return in_array($pagenow, array('profile.php', 'user-edit.php'), true);
	}

	// Internal Private Protected

	/**
	 * Render a profile collection.
	 *
	 * @internal WordPress callback for rendering user settings.
	 *
	 * @param string $id_slug The collection id, defaults to 'profile'.
	 * @param array|null $context Optional context.
	 *
	 * @return void
	 */
	public function __render(string $id_slug = 'profile', ?array $context = null): void {
		if (!isset($this->collections[$id_slug])) {
			ErrorNoticeRenderer::renderSimpleNotice('Unknown settings collection: ' . $id_slug);
			return;
		}
		$this->_start_form_session();

		// Resolve user_id early - needed for message restoration and options resolution
		$user_id = $this->_resolve_user_id($context ?? array());

		// Restore validation messages from previous POST (if any).
		// This enables field-level inline messages after the POST/redirect/GET cycle.
		// Pass user_id since we're viewing a specific user's profile (not necessarily current user).
		$this->_restore_form_messages($user_id);

		$collection_meta = $this->collections[$id_slug];
		$sections        = $this->sections[$id_slug] ?? array();
		$resolvedOptions = $this->resolve_options($context);
		$options         = $resolvedOptions->get_options();
		$bundle          = $this->_resolve_schema_bundle($resolvedOptions, array(
			'intent'       => 'render',
			'collection'   => $id_slug,
			'user_id'      => $user_id,
			'storage_kind' => $resolvedOptions->get_storage_context()->user_storage ?? '',
			'global'       => ($resolvedOptions->get_storage_context()->user_global ?? false) ? '1' : '0',
		));
		$internalSchema = $bundle['schema'];

		// Render before/after callbacks for the collection
		$before_html = $this->_render_callback_output($collection_meta['before'] ?? null, array(
			'field_id'     => '',
			'container_id' => $id_slug,
			'root_id'      => $id_slug,
			'section_id'   => '',
			'group_id'     => '',
			'value'        => null,
			'values'       => $options,
		)) ?? '';
		$after_html = $this->_render_callback_output($collection_meta['after'] ?? null, array(
			'field_id'     => '',
			'container_id' => $id_slug,
			'root_id'      => $id_slug,
			'section_id'   => '',
			'group_id'     => '',
			'value'        => null,
			'values'       => $options,
		))                                                            ?? '';
		$collection_description_raw = $collection_meta['description'] ?? '';
		$collection_description     = '';
		if (is_callable($collection_description_raw)) {
			$description_ctx = array(
				'field_id'     => '',
				'container_id' => $id_slug,
				'root_id'      => $id_slug,
				'section_id'   => '',
				'group_id'     => '',
				'value'        => null,
				'values'       => $options,
			);
			$resolved_description   = (string) FormsCallbackInvoker::invoke($collection_description_raw, $description_ctx);
			$collection_description = trim($resolved_description);
		} else {
			$collection_description = trim((string) $collection_description_raw);
		}
		$collection_style_raw = $collection_meta['style'] ?? '';
		$collection_style     = '';
		if (is_callable($collection_style_raw)) {
			$style_ctx = array(
				'field_id'     => '',
				'container_id' => $id_slug,
				'root_id'      => $id_slug,
				'section_id'   => '',
				'group_id'     => '',
				'value'        => null,
				'values'       => $options,
			);
			$resolved_style   = (string) FormsCallbackInvoker::invoke($collection_style_raw, $style_ctx);
			$collection_style = trim($resolved_style);
		} else {
			$collection_style = trim((string) $collection_style_raw);
		}

		// Check if collection has file upload fields
		$has_files = $this->_container_has_file_uploads($id_slug);

		$payload = array(
			...($context ?? array()),
			'heading'     => $collection_meta['heading'] ?? '',
			'description' => $collection_description,
			'style'       => $collection_style,
			...array(
				'id_slug'           => $id_slug,
				'collection_meta'   => $collection_meta,
				'sections'          => $sections,
				'values'            => $options,
				'inner_html'        => $this->_render_default_sections_wrapper($id_slug, $sections, $options),
				'messages_by_field' => $this->message_handler->get_all_messages(),
				'has_files'         => $has_files,
				'before'            => $before_html,
				'after'             => $after_html,
			),
		);

		$schemaSummary = $this->_build_schema_summary($internalSchema);

		$this->logger->debug('user_settings.render.payload', array(
			'collection' => $id_slug,
			'heading'    => $payload['heading'],
			'has_meta'   => array_keys($collection_meta),
		));
		$this->logger->debug('user_settings.render.schema_trace', array(
			'collection' => $id_slug,
			'fields'     => $schemaSummary,
		));

		// Enqueue Forms base CSS JIT during render
		/**
		 * Filter the Forms base stylesheet URL.
		 *
		 * Return an empty string to disable the default stylesheet,
		 * or a different URL to use a custom stylesheet.
		 *
		 * @param string $css_url The default stylesheet URL.
		 * @param string $id_slug The collection being rendered.
		 * @param UserSettings $instance The UserSettings instance.
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

		// Enqueue UserSettings CSS JIT during render
		/**
		 * Filter the UserSettings stylesheet URL.
		 *
		 * Return an empty string to disable the default stylesheet,
		 * or a different URL to use a custom stylesheet.
		 *
		 * @param string $css_url The default stylesheet URL.
		 * @param string $id_slug The collection being rendered.
		 * @param UserSettings $instance The UserSettings instance.
		 */
		$css_url = $this->_do_apply_filter(
			'ran_plugin_lib_user_settings_stylesheet_url',
			$this->_do_plugins_url('assets/user.settings.css', __FILE__),
			$id_slug,
			$this
		);
		if ($css_url !== '') {
			$this->_do_wp_enqueue_style(
				'ran-plugin-lib-user-settings',
				$css_url,
				array('ran-plugin-lib-forms-base'),
				'1.0.0'
			);
		}

		$this->_finalize_render($id_slug, $payload);
	}

	// WP hooks
	/**
	 * Normalize and persist posted values for a user.
	 *
	 * @internal WordPress callback for saving user settings.
	 *
	 * @param array<string,mixed> $payload The posted values.
	 * @param array<string,mixed> $context The context for the save operation.
	 */
	public function __save_settings(array $payload, array $context): void {
		$user_id = isset($context['user_id']) ? (int) $context['user_id'] : 0;
		if ($user_id <= 0) {
			return;
		}
		$storage = isset($context['storage']) ? strtolower((string) $context['storage']) : $this->base_storage;
		$storage = $storage === 'option' ? 'option' : 'meta';
		$global  = $storage === 'option' ? (bool) ($context['global'] ?? ($storage === $this->base_storage ? $this->base_global : false)) : false;
		$opts    = $this->resolve_options(array(
			'user_id' => $user_id,
			'storage' => $storage,
			'global'  => $global,
		));

		$this->_prepare_validation_messages($payload);

		$bundle = $this->_resolve_schema_bundle($opts, array(
			'intent'       => 'save',
			'user_id'      => $user_id,
			'storage_kind' => $storage,
			'global'       => $global ? '1' : '0',
		));

		// Consolidate bundle sources into single registration call
		$merged = $this->_merge_schema_bundle_sources($bundle);
		if (!empty($merged['merged_schema'])) {
			$opts->__register_internal_schema(
				$merged['merged_schema'],
				$merged['metadata'],
				$merged['queued_validators'],
				$merged['queued_sanitizers'],
				$merged['defaults_for_seeding']
			);
		}

		// Seed defaults for missing keys (register_schema handles seeding + telemetry)
		if (!empty($merged['defaults_for_seeding'])) {
			$opts->register_schema($merged['defaults_for_seeding']);
		}

		// Stage options and check for validation failures
		$opts->stage_options($payload);
		$messages = $this->_process_validation_messages($opts);

		if ($this->_has_validation_failures()) {
			$this->_log_validation_failure(
				'UserSettings::_save_settings validation failed; aborting persistence.',
				array_merge(
					array(
						'user_id'                  => $user_id,
						'validation_message_count' => is_array($messages) ? count($messages) : 0,
						'validation_message_keys'  => is_array($messages) ? array_keys($messages) : array(),
					),
					ErrorNoticeRenderer::isVerboseDebug() ? array(
						'validation_messages' => $messages,
					) : array()
				)
			);

			// Persist messages for display after redirect using our own transient mechanism.
			// This is more reliable than WordPress's settings_errors which has timing issues.
			// Pass user_id since we're editing a specific user's profile (not necessarily current user).
			$this->_persist_form_messages($messages, $user_id);

			// On validation failure, do NOT modify storage - just return.
			// The previous values remain in the database unchanged.
			return;
		}

		$opts->commit_merge();
		// Note: commit_merge returns false when no changes were made (WordPress behavior).
		// This is not a failure - the data is already correct in the database.
		// We only need to clear pending validation state on success or no-change.

		// Persist notices even when validation passes (e.g., sanitizer feedback messages)
		if (!empty($messages)) {
			$this->_persist_form_messages($messages, $user_id);
		}

		$this->_clear_pending_validation();
	}

	// Resolvers

	/**
	 * Resolve the user id for the user context.
	 *
	 * @param array<string,mixed> $context
	 *
	 * @return int
	 */
	protected function _resolve_user_id(array $context): int {
		if (isset($context['user_id'])) {
			$userId = (int) $context['user_id'];
		} elseif ($this->base_context->user_id !== null) {
			$userId = (int) $this->base_context->user_id;
		} elseif (isset($GLOBALS['profileuser']) && $GLOBALS['profileuser'] instanceof \WP_User) {
			$userId = (int) $GLOBALS['profileuser']->ID;
		} else {
			$userId = (int) $this->_do_get_current_user_id();
		}

		if ($userId <= 0) {
			$this->logger->warning('UserSettings::resolve_options requires a valid user_id.');
			throw new \InvalidArgumentException('UserSettings::resolve_options requires a valid user_id.');
		}

		return $userId;
	}

	/**
	 * Resolve the storage kind for the user context.
	 *
	 * @param array<string,mixed> $context
	 *
	 * @return string
	 */
	protected function _resolve_storage_kind(array $context): string {
		$storage = isset($context['storage']) ? strtolower((string) $context['storage']) : $this->base_storage;
		if ($storage !== 'meta' && $storage !== 'option') {
			$this->logger->warning('UserSettings::resolve_options: storage must be \'meta\' or \'option\'.');
			throw new \InvalidArgumentException("UserSettings::resolve_options: storage must be 'meta' or 'option'.");
		}

		return $storage;
	}

	/**
	 * Resolve the global flag for the user context.
	 *
	 * @param array<string,mixed> $context
	 * @param string $storage
	 *
	 * @return bool
	 */
	protected function _resolve_global_flag(array $context, string $storage): bool {
		if ($storage !== 'option') {
			return false;
		}

		$base = $storage === $this->base_storage ? $this->base_global : false;
		return (bool) ($context['global'] ?? $base);
	}

	/**
	 * Resolve the correctly scoped RegisterOptions instance for current user context.
	 *
	 * @param array<string,mixed> $context
	 * @return array{storage: StorageContext, user_id: int, storage_kind: string, global: bool}
	 */
	protected function _resolve_context(array $context): array {
		$context = $context ?? array();

		$userId = $this->_resolve_user_id($context);
		$scope  = SettingsScopeHelper::parse_scope($context) ?? OptionScope::User;
		$scope  = SettingsScopeHelper::require_allowed($scope, OptionScope::User);

		$storage = $this->_resolve_storage_kind($context);
		$global  = $this->_resolve_global_flag($context, $storage);

		$result = array(
		    'storage'      => StorageContext::forUserId($userId, $storage, $global),
		    'user_id'      => $userId,
		    'storage_kind' => $storage,
		    'global'       => $global,
		);

		return $result;
	}

	// Handlers

	/**
	 * Handle UserSettings-specific update types.
	 *
	 * @param string $type The update type
	 * @param array $data Update data
	 * @return void
	 */
	protected function _handle_custom_update(string $type, array $data): void {
		switch ($type) {
			case 'collection':
			case 'collection_commit':
				$this->_handle_context_update($type, $data);
				break;
			default:
				// Log unknown update type (default behavior from FormsCore)
				$this->logger->warning('UserSettings: Unknown update type received', array(
					'type'      => $type,
					'data_keys' => array_keys($data)
				));
				break;
		}
	}

	/**
	 * Handle collection update from builders.
	 *
	 * @param array $data Collection update data
	 * @return void
	 */
	protected function _handle_context_update(string $type, array $data): void {
		switch ($type) {
			case 'collection':
				$container_id    = $data['container_id']    ?? '';
				$collection_data = $data['collection_data'] ?? array();
				if (array_key_exists('style', $collection_data)) {
					$style = $collection_data['style'];
					if (is_string($style)) {
						$collection_data['style'] = trim($style);
					}
				}

				if ($container_id === '') {
					$this->logger->warning('UserSettings: Collection update missing container_id', $data);
					return;
				}

				// Store collection metadata
				if (!isset($this->collections[$container_id])) {
					$this->collections[$container_id] = array();
				}
				$this->collections[$container_id] = array_merge($this->collections[$container_id], $collection_data);
				break;
			case 'collection_commit':
				$container_id = $data['container_id'] ?? '';
				if ($container_id === '') {
					$this->logger->warning('UserSettings: Collection commit missing container_id', $data);
					return;
				}
				if (!isset($this->collections[$container_id])) {
					$this->logger->warning('UserSettings: Collection commit received for unknown container', array('container_id' => $container_id));
					return;
				}
				$sections = isset($this->sections[$container_id]) ? array_keys($this->sections[$container_id]) : array();
				$this->logger->debug('settings.builder.collection.committed', array(
					'container_id' => $container_id,
					'sections'     => $sections,
				));
				break;
			default:
				$this->logger->warning('UserSettings: Unsupported context update type received', array(
					'type'      => $type,
					'data_keys' => array_keys($data)
				));
		}
	}

	//

	/**
	 * Get the template alias for rendering sections.
	 *
	 * UserSettings uses table-based section templates for WordPress profile pages.
	 *
	 * @return string Template alias for section wrapper
	 */
	protected function _get_section_template(): string {
		return 'user.section-wrapper';
	}

	/**
	 * Process file uploads from $_FILES and merge into payload.
	 *
	 * WordPress profile form does NOT have enctype="multipart/form-data" by default,
	 * so we add it via JavaScript when file upload fields are present.
	 *
	 * @param array<string,mixed> $payload The current payload from $_POST.
	 * @return array<string,mixed> The payload with processed file data merged in.
	 */
	protected function _process_file_uploads(array $payload): array {
		$processed = $this->_process_uploaded_files();
		return array_merge($payload, $processed);
	}

	/**
	 * Public method to process file uploads and merge into payload.
	 *
	 * Used by UserSettingsRegistry when handling saves externally.
	 *
	 * @param array<string,mixed> $payload The current payload from $_POST.
	 * @return array<string,mixed> The payload with processed file data merged in.
	 */
	public function process_file_uploads(array $payload): array {
		return $this->_process_file_uploads($payload);
	}

	protected function _get_error_handler(): FormsErrorHandlerInterface {
		return new AdminFormsErrorHandler();
	}

	/**
	 * Extract collection IDs from the current builder state for error fallback.
	 *
	 * @return array<string> List of collection IDs that were being registered.
	 */
	protected function _extract_page_slugs_from_session(): array {
		return array_keys($this->collections);
	}

	/**
	 * Register fallback error display for UserSettings.
	 *
	 * Shows a brief inline notice in the profile page where collections would render.
	 * The full error details are shown via admin_notices (handled by base trait).
	 *
	 * @param \Throwable $e The caught exception or error.
	 * @param string $hook The WordPress hook or context where the error occurred.
	 * @param bool $is_dev Whether we're in development mode (show full details).
	 * @return void
	 */
	protected function _register_error_fallback_pages(\Throwable $e, string $hook, bool $is_dev): void {
		$render_error = function () use ($is_dev) {
			ErrorNoticeRenderer::renderInlinePlaceholder('User Settings Error — See admin notice above for details.', $is_dev);
		};

		// Register on profile hooks so placeholder shows where collections would be
		$this->_do_add_action('show_user_profile', $render_error, 10, 1);
		$this->_do_add_action('edit_user_profile', $render_error, 10, 1);
	}

	protected function _get_form_type_suffix(): string {
		return 'user';
	}
}
