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

use Ran\PluginLib\Util\WPWrappersTrait;
use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Options\Storage\StorageContext;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Options\OptionScope;
use Ran\PluginLib\Forms\Renderer\FormMessageHandler;
use Ran\PluginLib\Forms\Renderer\FormElementRenderer;
use Ran\PluginLib\Forms\FormsService;
use Ran\PluginLib\Forms\FormsInterface;
use Ran\PluginLib\Forms\FormsBaseTrait;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use Ran\PluginLib\Forms\Component\ComponentLoader;


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
class UserSettings implements FormsInterface {
	use FormsBaseTrait;
	use WPWrappersTrait;

	protected ComponentLoader $views;
	protected RegisterOptions $base_options;

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
		// UserSettings-specific: storage and global flags for user context resolution
		$this->base_storage = strtolower($context->user_storage ?? 'meta') === 'option' ? 'option' : 'meta';
		$this->base_global  = $this->base_storage                          === 'option' ? (bool) ($context->user_global ?? false) : false;

		// Phase 4: Component and view setup
		$this->components = $components;
		$this->views      = $components->get_component_loader();

		// Phase 5: Context-specific template registration
		// UserSettings registers complete template hierarchy for profile forms
		$this->views->register('user.root-wrapper', '../../Settings/templates/user/root-wrapper.php');
		$this->views->register('user.section-wrapper', '../../Settings/templates/user/section-wrapper.php');
		$this->views->register('user.group-wrapper', '../../Settings/templates/user/group-wrapper.php');
		$this->views->register('user.field-wrapper', '../../Settings/templates/user/field-wrapper.php');

		// Phase 6: Service initialization
		$this->form_service    = new FormsService($this->components);
		$this->field_renderer  = new FormElementRenderer($this->components, $this->form_service, $this->views, $this->logger);
		$this->message_handler = new FormMessageHandler($this->logger);

		// Phase 7: Form session configuration with context-specific defaults
		$this->_start_form_session();
		// UserSettings overrides all template levels for profile-specific rendering
		$this->form_session->set_form_defaults(array(
			'root-wrapper'    => 'user.root-wrapper',
			'section-wrapper' => 'user.section-wrapper',
			'group-wrapper'   => 'user.group-wrapper',
			'field-wrapper'   => 'user.field-wrapper',
		));
	}

	/**
	 * Boot user settings: register collections, sections, fields and save handlers.
	 *
	 * @return void
	 */
	public function boot(): void {
		$hooks = array();

		foreach ($this->collections as $id_slug => $meta) {
			$order  = (int) ($meta['order'] ?? 10);
			$order  = $order < 0 ? 0 : $order;
			$render = function ($user) use ($id_slug) {
				if (!($user instanceof \WP_User)) {
					return;
				}
				$this->render($id_slug, array('user' => $user));
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

		$save = function ($user_id) {
			$user_id = (int) $user_id;
			if (!$this->_do_current_user_can('edit_user', $user_id)) {
				return; // silent deny to match WP conventions
			}
			$payload = isset($_POST[$this->main_option]) && is_array($_POST[$this->main_option]) ? $_POST[$this->main_option] : array();
			$this->save_settings($payload, array('user_id' => $user_id));
		};

		$hooks[] = array(
			'hook'          => 'personal_options_update',
			'callback'      => $save,
			'priority'      => 10,
			'accepted_args' => 1,
		);
		$hooks[] = array(
			'hook'          => 'edit_user_profile_update',
			'callback'      => $save,
			'priority'      => 10,
			'accepted_args' => 1,
		);

		$this->_register_action_hooks($hooks);
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

		$initial_meta = array(
		    'order'       => $order,
		    'heading'     => $heading,
		    'description' => $description,
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
	public function end(): self {
		return $this;
	}

	/**
	 * Normalize and persist posted values for a user.
	 *
	 * @param int $user_id
	 * @param mixed $raw
	 */
	public function save_settings(array $payload, array $context): void {
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

		$previous_options = $opts->get_options();

		$schema = $opts->_get_schema_internal();
		if (!empty($schema)) {
			$session = $this->get_form_session();
			if ($session !== null) {
				$bucketed = $this->_assemble_bucketed_schema($session);
				if (!empty($bucketed['schema'])) {
					$queued = $this->_drain_queued_component_validators();
					$opts->_register_internal_schema($bucketed['schema'], $bucketed['metadata'], $queued);
				}
			}
			$opts->_register_internal_schema($schema);
			$defaults = array();
			foreach ($schema as $normalizedKey => $entry) {
				if (\is_array($entry) && array_key_exists('default', $entry)) {
					$defaults[$normalizedKey] = array('default' => $entry['default']);
				}
			}
			if (!empty($defaults)) {
				$opts->register_schema($defaults);
			}
			$this->_flush_queued_component_validators();
		}

		// Stage options and check for validation failures
		$opts->stage_options($payload);
		$messages = $this->_process_validation_messages($opts);

		if ($this->_has_validation_failures()) {
			$this->_log_validation_failure(
				'UserSettings::save_settings validation failed; aborting persistence.',
				array(
					'user_id'             => $user_id,
					'validation_messages' => $messages,
				)
			);

			$opts->clear();
			$opts->stage_options($previous_options);
			return;
		}

		$success = $opts->commit_merge();
		if ($success) {
			$this->_clear_pending_validation();
		} else {
			$this->_log_validation_failure(
				'UserSettings::save_settings commit_merge failed unexpectedly.',
				array('user_id' => $user_id),
				'warning'
			);
			$opts->clear();
			$opts->stage_options($previous_options);
		}
	}

	/**
	 * Render a profile collection.
	 *
	 * @param string $id_slug The collection id, defaults to 'profile'.
	 * @param array|null $context Optional context.
	 *
	 * @return void
	 */
	public function render(string $id_slug = 'profile', ?array $context = null): void {
		if (!isset($this->collections[$id_slug])) {
			return; // Collection not registered
		}
		$this->_start_form_session();

		$collection_meta = $this->collections[$id_slug];
		$sections        = $this->sections[$id_slug] ?? array();
		$options         = $this->resolve_options($context)->get_options();

		// Get effective values from message handler (handles pending values)
		$effective_values = $this->message_handler->get_effective_values($options);

		$payload = array(
			...($context ?? array()),
			'heading'     => $collection_meta['heading']     ?? '',
			'description' => $collection_meta['description'] ?? '',
			...array(
				'id_slug'           => $id_slug,
				'collection_meta'   => $collection_meta,
				'sections'          => $sections,
				'values'            => $effective_values,
				'content'           => $this->_render_default_sections_wrapper($id_slug, $sections, $effective_values),
				'messages_by_field' => $this->message_handler->get_all_messages(),
			),
		);
		$this->logger->debug('user_settings.render.payload', array(
			'collection' => $id_slug,
			'heading'    => $payload['heading'],
			'has_meta'   => array_keys($collection_meta),
			'callback'   => $this->form_session->get_root_template_callback($id_slug) !== null,
		));

		$callback = $this->form_session->get_root_template_callback($id_slug);
		if ($callback !== null) {
			ob_start();
			$callback($payload);
			echo (string) ob_get_clean();
		} else {
			echo $this->form_session->render_element('root-wrapper', $payload, array(
				'root_id' => $id_slug,
			));
		}

		$this->form_session->enqueue_assets();
	}

	// Protected
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
		$scope  = SettingsScopeHelper::parseScope($context) ?? OptionScope::User;
		$scope  = SettingsScopeHelper::requireAllowed($scope, OptionScope::User);

		$storage = $this->_resolve_storage_kind($context);
		$global  = $this->_resolve_global_flag($context, $storage);

		$result = array(
		    'storage'      => StorageContext::forUser($userId, $storage, $global),
		    'user_id'      => $userId,
		    'storage_kind' => $storage,
		    'global'       => $global,
		);
		$this->logger->debug('settings.builder.context.resolved', array(
			'user_id'      => $result['user_id'],
			'storage_kind' => $result['storage_kind'],
			'global'       => $result['global'],
			'scope'        => $scope instanceof OptionScope ? $scope->value : (string) $scope,
		));

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
				$this->_handle_context_update($type, $data);
				break;
			default:
				// Log unknown update type (default behavior from FormsBaseTrait)
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

				if ($container_id === '') {
					$this->logger->warning('UserSettings: Collection update missing container_id', $data);
					return;
				}

				// Store collection metadata
				if (!isset($this->collections[$container_id])) {
					$this->collections[$container_id] = array();
				}
				$this->collections[$container_id] = array_merge($this->collections[$container_id], $collection_data);
				$this->logger->debug('settings.builder.collection.updated', array(
					'container_id' => $container_id,
					'collection'   => $this->collections[$container_id],
				));
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
}
