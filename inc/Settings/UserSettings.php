<?php
/**
 * UserSettings: DX-friendly user profile settings UI with RegisterOptions.
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
use Ran\PluginLib\Settings\UserSettingsInterface;
use Ran\PluginLib\Settings\ComponentRenderingTrait;
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
 * User profile settings facade that bridges WordPress profile hooks with a scoped `RegisterOptions`
 * instance and shared `FormService` rendering session.
 *
 * Responsibilities:
 * - Manage collections, sections, fields, and groups before WordPress renders the profile UI.
 * - Resolve per-user storage contexts by cloning the injected `RegisterOptions` with user-specific overrides.
 * - Render component-driven profile fields and enqueue captured assets through the active session.
 *
 * Note: WordPress core continues to own capability checks (`edit_user`) and the profile lifecycle. Only the
 * validation and write-policy portions of `RegisterOptions` are exercised here.
 */
class UserSettings implements UserSettingsInterface {
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
	 * Base context, storage and global captured from the injected RegisterOptions instance.
	 * Retained so subsequent renders and saves can derive user_id/storage defaults.
	 */
	private StorageContext $base_context;
	private string $base_storage;
	private bool $base_global;

	/** @var array<string, array<string, string>> Template overrides by collection ID and template type */
	private array $collection_template_overrides = array();

	/** @var array<string, array<string, string>> Template overrides by section ID and template type */
	private array $section_template_overrides = array();

	/** @var array<string, string> Default template overrides for this UserSettings instance */
	private array $default_template_overrides = array();

	/**
	 * $collections accumulates pending collections prior to WordPress hook registration.
	 * This is the functional equivalent of $menu_groups of $pages in AdminSettings.
	 *
	 * @var array<string, array{template:?callable, priority:int}> //collection_id => meta (typically only 'profile')
	 */
	private array $collections = array();

	/**
	 * $sections accumulates pending sections prior to WordPress hook registration.
	 *
	 * @var array<string, array<string, array{title:string, description_cb:?callable, order:int, index:int}>>
	 */
	private array $sections = array(); // [collection_id][section_id]
	/** @var array<string, array<string, array{id:string,label:string,component:string,component_context:array<string,mixed>, order:int, index:int}>>> */
	private array $fields = array();   // [collection_id][section_id] => list
	/** @var array<string, array<string, array{group_id:string, fields:array<int,array{id:string,label:string,component:string,component_context:array<string,mixed>, order:int, index:int}>, before:?callable, after:?callable, order:int, index:int}>> */
	private array $groups = array();   // [page_id][section_id][group_id]

	private int $__section_index = 0;
	private int $__field_index   = 0;
	private int $__group_index   = 0;

	/**
	 * Constructor.
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
		$this->logger = $logger instanceof Logger ? $logger : $options->get_logger();

		$context = $options->get_storage_context();
		if ($context->scope !== OptionScope::User) {
			$received = $context->scope instanceof OptionScope ? $context->scope->value : 'unknown';
			$this->logger->warning('UserSettings::__construct received non-user scope RegisterOptions; rejecting.', array('scope' => $received));
			throw new \InvalidArgumentException('UserSettings requires user context; received ' . $received . '.');
		}
		$this->base_options = $options;
		$this->base_context = $context;

		$this->main_option  = $options->get_main_option_name();
		$this->base_storage = strtolower($context->user_storage ?? 'meta') === 'option' ? 'option' : 'meta';
		$this->base_global  = $this->base_storage                          === 'option' ? (bool) ($context->user_global ?? false) : false;
		$this->views        = new ComponentLoader(__DIR__ . '/views');
		$this->components   = $components instanceof ComponentManifest
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

		// Configure template overrides for UserSettings context (table-based)
		$this->field_renderer->set_template_overrides(array(
			'field-wrapper'      => 'user.field-row',
			'section'            => 'user.section',
			'collection-wrapper' => 'user.collection-wrapper'
		));

		// Set table-optimized defaults for UserSettings
		$this->default_template_overrides = array(
			'collection-wrapper' => 'user.collection-wrapper',
			'section'            => 'user.section',
			'field-wrapper'      => 'user.field-row',
		);

		$this->start_form_session();
	}

	/**
	 * Start a new form session.
	 */
	private function start_form_session(): void {
		$this->form_session = $this->form_service->start_session();
	}

	/**
	 * Shared interface: resolve RegisterOptions for user scope.
	 * Callers should provide context to override defaults when available.
	 *
	 * @param array<string,mixed>|null $context Optional context overrides.
	 *
	 * @return RegisterOptions
	 */
	public function resolve_options(?array $context = null): RegisterOptions {
		$resolved = $this->_resolve_context($context);
		return $this->base_options->with_context($resolved['storage']);
	}

	/**
	 * Resolve the correctly scoped RegisterOptions instance for current user context.
	 *
	 * @param array<string,mixed> $context
	 * @return array{storage: StorageContext, user_id: int, storage_kind: string, global: bool}
	 */
	protected function _resolve_context(array $context): array {
		$context = $context ?? array();

		$scope = SettingsScopeHelper::parseScope($context) ?? OptionScope::User;
		$scope = SettingsScopeHelper::requireAllowed($scope, OptionScope::User);

		$userId  = $this->_resolve_user_id($context);
		$storage = $this->_resolve_storage_kind($context);
		$global  = $this->_resolve_global_flag($context, $storage);

		return array(
		    'storage'      => StorageContext::forUser($userId, $storage, $global),
		    'user_id'      => $userId,
		    'storage_kind' => $storage,
		    'global'       => $global,
		);
	}

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
	 * Augment the component context with the main option name.
	 *
	 * @param array<string,mixed> $context
	 * @param array<string,mixed> $values
	 *
	 * @return array<string,mixed>
	 */
	protected function _augment_component_context(array $context, array $values): array {
		$context['_option'] = $this->main_option;
		return $context;
	}

	/**
	 * Add a profile collection (new group) onto the user profile page.
	 *
	 * @param string $collection_id The collection id, defaults to 'profile'.
	 * @param callable|null $template An optional collection template.
	 *
	 * @return UserSettingsCollectionBuilder
	 */
	public function add_collection(string $collection_id = 'profile', ?callable $template = null): UserSettingsCollectionBuilder {
		if (!isset($this->collections[$collection_id])) {
			$this->collections[$collection_id] = array(
			    'template' => $template,
			    'priority' => 10,
			);
		} else {
			if ($template !== null) {
				$this->collections[$collection_id]['template'] = $template;
			}
			if (!isset($this->collections[$collection_id]['priority'])) {
				$this->collections[$collection_id]['priority'] = 10;
			}
		}

		$commit = function (string $pid, array $sections, array $fields, array $groups): void {
			if (!isset($this->sections[$pid])) {
				$this->sections[$pid] = array();
			}
			foreach ($sections as $sid => $meta) {
				$this->sections[$pid][$sid] = array(
				    'title'          => (string) $meta['title'],
				    'description_cb' => $meta['description_cb'] ?? null,
				    'order'          => (int) ($meta['order'] ?? 0),
				    'index'          => $this->__section_index++,
				);
			}
			if (!isset($this->fields[$pid])) {
				$this->fields[$pid] = array();
			}
			foreach ($fields as $sid => $list) {
				if (!isset($this->fields[$pid][$sid])) {
					$this->fields[$pid][$sid] = array();
				}
				foreach ($list as $f) {
					$fid       = isset($f['id']) ? (string) $f['id'] : '';
					$flabel    = isset($f['label']) ? (string) $f['label'] : '';
					$component = isset($f['component']) && is_string($f['component']) ? trim($f['component']) : '';
					if ($fid === '' || $component === '') {
						throw new \InvalidArgumentException(sprintf('UserSettings field "%s" in collection "%s" requires component metadata.', $fid !== '' ? $fid : 'unknown', $pid));
					}
					$context = $f['component_context'] ?? array();
					if (!is_array($context)) {
						throw new \InvalidArgumentException(sprintf('UserSettings field "%s" in collection "%s" must provide array component_context.', $fid, $pid));
					}

					// Inject component validators automatically
					$this->_inject_component_validators($fid, $component);

					$this->fields[$pid][$sid][] = array(
					    'id'                => $fid,
					    'label'             => $flabel,
					    'component'         => $component,
					    'component_context' => $context,
					    'order'             => (int) ($f['order'] ?? 0),
					    'index'             => $this->__field_index++,
					);
				}
			}
			if (!isset($this->groups[$pid])) {
				$this->groups[$pid] = array();
			}
			foreach ($groups as $sid => $map) {
				if (!isset($this->groups[$pid][$sid])) {
					$this->groups[$pid][$sid] = array();
				}
				foreach ($map as $gid => $g) {
					$normalized_fields = array();
					foreach ($g['fields'] as $field) {
						$fid       = isset($field['id']) ? (string) $field['id'] : '';
						$component = isset($field['component']) && is_string($field['component']) ? trim($field['component']) : '';
						if ($fid === '' || $component === '') {
							throw new \InvalidArgumentException(sprintf('UserSettings group field "%s" in group "%s" requires component metadata.', $fid !== '' ? $fid : 'unknown', $gid));
						}
						$context = $field['component_context'] ?? array();
						if (!is_array($context)) {
							throw new \InvalidArgumentException(sprintf('UserSettings group field "%s" in group "%s" must provide array component_context.', $fid, $gid));
						}

						// Inject component validators automatically
						$this->_inject_component_validators($fid, $component);

						$normalized_fields[] = array(
						    'id'                => $fid,
						    'label'             => isset($field['label']) ? (string) $field['label'] : '',
						    'component'         => $component,
						    'component_context' => $context,
						    'order'             => (int) ($field['order'] ?? 0),
						    'index'             => $this->__field_index++,
						);
					}
					$this->groups[$pid][$sid][$gid] = array(
					    'group_id' => (string) $g['group_id'],
					    'fields'   => $normalized_fields,
					    'before'   => $g['before'] ?? null,
					    'after'    => $g['after']  ?? null,
					    'order'    => (int) ($g['order'] ?? 0),
					    'index'    => $this->__group_index++,
					);
				}
			}
		};

		$setPriority = function (string $pid, int $priority): void {
			if (!isset($this->collections[$pid])) {
				$this->collections[$pid] = array(
				    'template' => null,
				    'priority' => max(0, $priority),
				);
				return;
			}
			$this->collections[$pid]['priority'] = max(0, $priority);
		};

		return new UserSettingsCollectionBuilder($this, $collection_id, $template, $commit, $setPriority);
	}

	/**
	 * Boot user settings: register collections, sections, fields and save handlers.
	 *
	 * @return void
	 */
	public function boot(): void {
		// Render on profile screens per registered page using configured priority
		foreach ($this->collections as $collection_id => $meta) {
			$priority = (int) ($meta['priority'] ?? 10);
			$priority = $priority < 0 ? 0 : $priority;
			$render   = function ($user) use ($collection_id) {
				if (!($user instanceof \WP_User)) {
					return;
				}
				$this->render_collection($collection_id, array('user' => $user));
			};
			// User views their own profile
			$this->_do_add_action('show_user_profile', $render, $priority, 1);
			// Admin views another user's profile
			$this->_do_add_action('edit_user_profile', $render, $priority, 1);
		}

		// Save handlers
		$save = function ($user_id) {
			$user_id = (int) $user_id;
			if (!$this->_do_current_user_can('edit_user', $user_id)) {
				return; // silent deny to match WP conventions
			}
			$payload = isset($_POST[$this->main_option]) && is_array($_POST[$this->main_option]) ? $_POST[$this->main_option] : array();
			$this->save_settings($payload, array('user_id' => $user_id));
		};
		// User updates their own profile
		$this->_do_add_action('personal_options_update', $save, 10, 1);
		// Admin updates another user's profile
		$this->_do_add_action('edit_user_profile_update', $save, 10, 1);
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

		// Clear previous messages and set pending values
		$this->message_handler->clear();
		$this->message_handler->set_pending_values($payload);

		$previous_options = $opts->get_options();

		// Stage options and check for validation failures
		$opts->stage_options($payload);
		$messages = $opts->take_messages();

		// Set messages in FormMessageHandler
		$this->message_handler->set_messages($messages);

		// Check if there are validation failures (warnings)
		if ($this->message_handler->has_validation_failures()) {
			$this->logger->info('UserSettings::save_settings validation failed; aborting persistence.', array(
				'user_id'       => $user_id,
				'warning_count' => $this->message_handler->get_warning_count()
			));

			// Restore previous options since validation failed
			$opts->clear();
			$opts->stage_options($previous_options);
			return;
		}

		// Only commit if validation passed
		$success = $opts->commit_merge();
		if ($success) {
			// Clear pending values on success
			$this->message_handler->set_pending_values(null);
			$this->pending_values = null;
		} else {
			// This shouldn't happen since we already checked for validation failures,
			// but handle it just in case
			$this->logger->warning('UserSettings::save_settings commit_merge failed unexpectedly.', array(
				'user_id' => $user_id
			));
			$opts->clear();
			$opts->stage_options($previous_options);
		}
	}

	/**
	 * Render the default user collection template markup.
	 *
	 * @param array $context Template context.
	 */
	protected function _render_default_collection_template(array $context): void {
		try {
			echo $this->views->render('user.default-collection', $context);
		} catch (\LogicException $e) {
			$this->logger->error('UserSettings default template render failed.', array('message' => $e->getMessage()));
			throw $e;
		}
	}

	/**
	 * Render the default sections and fields markup for a user collection.
	 *
	 * @param string $collection_id Collection identifier.
	 * @param array  $sections      Section metadata map.
	 * @param array  $values        Current option values.
	 *
	 * @return string Rendered HTML table markup.
	 */
	protected function _render_default_collection_sections(string $collection_id, array $sections, array $values): string {
		$prepared_sections = array();
		$groups_map        = $this->groups[$collection_id] ?? array();
		$fields_map        = $this->fields[$collection_id] ?? array();
		$profile_user      = $GLOBALS['profileuser']       ?? null;

		foreach ($sections as $section_id => $meta) {
			$groups = $groups_map[$section_id] ?? array();
			$fields = $fields_map[$section_id] ?? array();
			uasort($groups, function ($a, $b) {
				return ($a['order'] <=> $b['order']) ?: ($a['index'] <=> $b['index']);
			});
			usort($fields, function ($a, $b) {
				return ($a['order'] <=> $b['order']) ?: ($a['index'] <=> $b['index']);
			});

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
			usort($items, function ($a, $b) {
				return ($a['type'] === 'group' ? 0 : 1) <=> ($b['type'] === 'group' ? 0 : 1);
			});

			$prepared_sections[] = array(
			    'title'          => (string) $meta['title'],
			    'description_cb' => $meta['description_cb'] ?? null,
			    'items'          => $items,
			);
		}

		return $this->views->render('section.table', array(
			'sections'       => $prepared_sections,
			'row_renderer'   => fn (array $field): string => $this->_render_field_row($field, $values, $profile_user),
			'group_renderer' => fn (array $group): string => $this->_render_group_row($group, $values, $profile_user),
		));
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

				$this->logger->debug('UserSettings: Component validator injected', array(
					'field_id'  => $field_id,
					'component' => $component
				));
			}
		}
	}

	/**
		* Render a single table row for a field definition via template.
		*
		* @param array<string,mixed>   $field
		* @param array<string,mixed>   $values
		* @param \WP_User|null         $profile_user
		*/
	protected function _render_field_row(array $field, array $values, ?\WP_User $profile_user): string {
		if (empty($field)) {
			return '';
		}

		$field_id  = isset($field['id']) ? (string) $field['id'] : '';
		$label     = isset($field['label']) ? (string) $field['label'] : '';
		$component = isset($field['component']) && is_string($field['component']) ? trim($field['component']) : '';

		if ($component === '') {
			$this->logger->error('UserSettings field missing component metadata.', array('field' => $field_id));
			throw new \InvalidArgumentException(sprintf('UserSettings field "%s" requires a component alias.', $field_id ?: 'unknown'));
		}

		$context = $field['component_context'] ?? array();
		if (!is_array($context)) {
			$this->logger->error('UserSettings field provided a non-array component_context.', array('field' => $field_id));
			throw new \InvalidArgumentException(sprintf('UserSettings field "%s" must provide an array component_context.', $field_id ?: 'unknown'));
		}

		// Get messages for this field from message handler
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
				$values,
				array($field_id => $field_messages)
			);

			$content = $this->field_renderer->render_field_component(
				$component,
				$field_id,
				$label,
				$field_context,
				$values,
				'direct-output' // UserSettings handles its own table wrapper
			);

			if ($content === '') {
				return '';
			}

			// Use the existing user.field-row template with the new context structure
			return $this->views->render('user.field-row', array(
				'label'               => $label,
				'content'             => $content,
				'field_id'            => $field_id,
				'component_html'      => $content,
				'validation_warnings' => $field_messages['warnings'] ?? array(),
				'display_notices'     => $field_messages['notices']  ?? array()
			));
		} catch (\Throwable $e) {
			$this->logger->error('UserSettings: Field rendering failed', array(
				'field_id'  => $field_id,
				'component' => $component,
				'exception' => $e
			));
			return '<tr><td colspan="2" class="error">Field rendering failed: ' . esc_html($e->getMessage()) . '</td></tr>';
		}
	}

	/**
	* Render a single table row for a group definition via template.
	*
	* @param array<string,mixed>   $group
	* @param array<string,mixed>   $values
	* @param \WP_User|null         $profile_user
	*/
	protected function _render_group_row(array $group, array $values, ?\WP_User $profile_user): string {
		$rows = array();

		if (isset($group['before']) && is_callable($group['before'])) {
			ob_start();
			($group['before'])();
			$rows[] = (string) ob_get_clean();
		}

		foreach ($group['fields'] ?? array() as $field) {
			$rows[] = $this->_render_field_row($field, $values, $profile_user);
		}

		if (isset($group['after']) && is_callable($group['after'])) {
			ob_start();
			($group['after'])();
			$rows[] = (string) ob_get_clean();
		}

		return $this->views->render('user.group-rows', array(
			'rows' => $rows,
		));
	}

	/**
	 * Renders a new secton within the WordPress user profile page.
	 *
	 * @param string   $collection_id The collection ID.
	 * @param ?array   $context         Optional context overrides.
	 */
	public function render_collection(string $collection_id = 'profile', ?array $context = null): void {
		$context = $context ?? array();
		if (!isset($this->collections[$collection_id])) {
			return;
		}
		$template = $this->collections[$collection_id]['template'] ?? null;
		$user     = $context['user']                               ?? null;
		if (!($user instanceof \WP_User)) {
			return;
		}
		$effective_global = $context['global'] ?? $this->base_global;
		$opts             = $this->resolve_options(array(
			'user_id' => $user->ID,
			'storage' => $this->base_storage,
			'global'  => $effective_global,
		));
		$values = $this->message_handler->get_effective_values($opts->get_options());

		// Prepare sorted sections/fields/groups
		$sections = $this->sections[$collection_id] ?? array();
		uasort($sections, function ($a, $b) {
			return ($a['order'] <=> $b['order']) ?: ($a['index'] <=> $b['index']);
		});

		$title = isset($context['collection_title']) ? (string) $context['collection_title'] : '';

		$this->start_form_session();

		$currentValues = $values;
		$context       = array(
		    'collection_id'    => $collection_id,
		    'collection_title' => $title,
		    'main_option'      => $this->main_option,
		    'options'          => $currentValues,
		    'schema'           => array(),
		    'logger'           => $this->logger,
		    'user'             => $user,
		    'render'           => function () use ($collection_id, $sections, $currentValues) {
		    	$markup = $this->_render_default_collection_sections($collection_id, $sections, $currentValues);
		    	$this->_enqueue_component_assets();
		    	return $markup;
		    },
		);

		if (is_callable($template)) {
			$template($context, $user);
			$this->_enqueue_component_assets();
			return;
		}

		$this->_render_default_collection_template($context);
		$this->_enqueue_component_assets();
	}

	/**
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

	/**
	 * Retrieve captured validation warnings for a given field ID.
	 *
	 * @return array<string, array{warnings: array<int, string>, notices: array<int, string>}>
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
	 * Set template overrides for a specific collection.
	 *
	 * @param string $collection_id The collection ID.
	 * @param array<string, string> $template_overrides Template overrides map.
	 *
	 * @return void
	 */
	public function set_collection_template_overrides(string $collection_id, array $template_overrides): void {
		$this->collection_template_overrides[$collection_id] = $template_overrides;
		$this->logger->debug('UserSettings: Collection template overrides set', array(
			'collection_id' => $collection_id,
			'overrides'     => array_keys($template_overrides)
		));
	}

	/**
	 * Set template overrides for a specific section.
	 *
	 * @param string $section_id The section ID.
	 * @param array<string, string> $template_overrides Template overrides map.
	 *
	 * @return void
	 */
	public function set_section_template_overrides(string $section_id, array $template_overrides): void {
		$this->section_template_overrides[$section_id] = $template_overrides;
		$this->logger->debug('UserSettings: Section template overrides set', array(
			'section_id' => $section_id,
			'overrides'  => array_keys($template_overrides)
		));
	}

	/**
	 * Set default template overrides for this UserSettings instance.
	 *
	 * @param array<string, string> $template_overrides Template overrides map.
	 *
	 * @return void
	 */
	public function set_default_template_overrides(array $template_overrides): void {
		$this->default_template_overrides = array_merge($this->default_template_overrides, $template_overrides);
		$this->logger->debug('UserSettings: Default template overrides set', array(
			'overrides' => array_keys($template_overrides)
		));
	}

	/**
	 * Resolve template with hierarchical fallback for UserSettings context.
	 *
	 * @param string $template_type The template type (e.g., 'field-wrapper', 'section', 'collection-wrapper').
	 * @param array<string, mixed> $context Additional context for template resolution.
	 *
	 * @return string The resolved template key.
	 */
	public function resolve_template(string $template_type, array $context = array()): string {
		// 1. Check field-level override
		if (isset($context['field_template_override'])) {
			return $context['field_template_override'];
		}

		// 2. Check section-level override
		if (isset($context['section_id']) && isset($this->section_template_overrides[$context['section_id']][$template_type])) {
			return $this->section_template_overrides[$context['section_id']][$template_type];
		}

		// 3. Check collection-level override
		if (isset($context['collection_id']) && isset($this->collection_template_overrides[$context['collection_id']][$template_type])) {
			return $this->collection_template_overrides[$context['collection_id']][$template_type];
		}

		// 4. Check class instance defaults
		if (isset($this->default_template_overrides[$template_type])) {
			return $this->default_template_overrides[$template_type];
		}

		// 5. Global system defaults for UserSettings (table-optimized)
		return $this->get_system_default_template($template_type);
	}

	/**
	 * Get system default template for UserSettings context.
	 *
	 * @param string $template_type The template type.
	 *
	 * @return string The default template key.
	 */
	private function get_system_default_template(string $template_type): string {
		$defaults = array(
			'collection-wrapper' => 'user.collection-wrapper',
			'section'            => 'user.section',
			'field-wrapper'      => 'user.field-row',
		);
		return $defaults[$template_type] ?? 'user.field-row';
	}

	/**
	 * Get default template overrides for this UserSettings instance.
	 *
	 * @return array<string, string>
	 */
	public function get_default_template_overrides(): array {
		return $this->default_template_overrides;
	}

	/**
	 * Get collection template overrides for a specific collection.
	 *
	 * @param string $collection_id The collection ID.
	 *
	 * @return array<string, string>
	 */
	public function get_collection_template_overrides(string $collection_id): array {
		return $this->collection_template_overrides[$collection_id] ?? array();
	}

	/**
	 * Get section template overrides for a specific section.
	 *
	 * @param string $section_id The section ID.
	 *
	 * @return array<string, string>
	 */
	public function get_section_template_overrides(string $section_id): array {
		return $this->section_template_overrides[$section_id] ?? array();
	}

	/**
	 * Validate a template override key.
	 *
	 * @param string $template_key The template key to validate.
	 * @param string $template_type The template type for context.
	 *
	 * @return bool True if valid, false otherwise.
	 */
	public function validate_template_override(string $template_key, string $template_type): bool {
		// Check if template exists in ComponentLoader
		try {
			// Try to render with empty context to check if template exists
			$this->views->render($template_key, array());
			return true;
		} catch (\LogicException $e) {
			$this->logger->warning('UserSettings: Invalid template override', array(
				'template_key'  => $template_key,
				'template_type' => $template_type,
				'error'         => $e->getMessage()
			));
			return false;
		}
	}
}
