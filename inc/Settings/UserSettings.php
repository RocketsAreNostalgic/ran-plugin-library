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
use Ran\PluginLib\Forms\FormServiceSession;
use Ran\PluginLib\Forms\FormService;
use Ran\PluginLib\Forms\FormBaseTrait;
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
class UserSettings implements SettingsInterface {
	use FormBaseTrait;
	use WPWrappersTrait;

	protected ComponentLoader $views;

	/**
	 * Base context, storage and global captured from the injected RegisterOptions instance.
	 * Retained so subsequent renders and saves can derive user_id/storage defaults.
	 */
	protected StorageContext $base_context;
	protected string $base_storage;
	protected bool $base_global;

	/**
	 * Accumulates pending collections prior to WordPress hook registration.
	 * This is the functional equivalent of menu_groups in AdminSettings.
	 * Collections represent different groupings of user profile sections (typically 'profile').
	 *
	 * @var array<string, array{template:?callable, priority:int}>
	 */
	protected array $collections = array();

	/**
	 * Constructor.
	 *
	 * @param RegisterOptions $options The base RegisterOptions instance.
	 * @param ComponentManifest|Logger $components_or_logger Component manifest (preferred) or Logger (backward compatibility).
	 * @param Logger|ComponentManifest|null $logger_or_components Optional logger or ComponentManifest.
	 */
	public function __construct(
		RegisterOptions $options,
		ComponentManifest $components,
		?Logger $logger = null
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
		$this->components   = $components;
		$this->views        = $components->get_component_loader();

		// Register UserSettings template overrides on the shared ComponentLoader
		$this->views->register('user.collection-wrapper', '../../Settings/views/user/collection-wrapper.php');
		$this->views->register('user.section-wrapper', '../../Settings/views/user/section-wrapper.php');
		$this->views->register('user.group-wrapper', '../../Settings/views/user/group-wrapper.php');
		$this->views->register('user.field-wrapper', '../../Settings/views/user/field-wrapper.php');

		// Initialize UserSettings-specific infrastructure
		$this->form_service    = new FormService($this->components);
		$this->field_renderer  = new FormElementRenderer($this->components, $this->form_service, $this->views, $this->logger);
		$this->message_handler = new FormMessageHandler($this->logger);

		// Configure template overrides for UserSettings context (table-based)
		$this->field_renderer->set_template_overrides(array(
			'form-wrapper'    => 'user.collection-wrapper',
			'section-wrapper' => 'user.section-wrapper',
			'group-wrapper'   => 'user.group-wrapper',
			'field-wrapper'   => 'user.field-wrapper',
		));

		// Set table-optimized defaults for UserSettings
		$this->default_template_overrides = array(
			'collection-wrapper' => 'user.collection-wrapper',
			'section'            => 'user.section',
			'field-wrapper'      => 'user.field-row',
		);

		$this->_start_form_session();
	}

	/**
	 * Boot user settings: register collections, sections, fields and save handlers.
	 *
	 * @return void
	 */
	public function boot(): void {
		// Render on profile screens per registered page using configured priority
		foreach ($this->collections as $id_slug => $meta) {
			$priority = (int) ($meta['priority'] ?? 10);
			$priority = $priority < 0 ? 0 : $priority;
			$render   = function ($user) use ($id_slug) {
				if (!($user instanceof \WP_User)) {
					return;
				}
				$this->render($id_slug, array('user' => $user));
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

	/** 🚨
	 * Add a profile collection (new group) onto the user profile page.
	 *
	 * The AdminSettings collerary is the page() method.
	 *
	 * @param string $id_slug The collection id, defaults to 'profile'.
	 * @param callable|null $template An optional collection template.
	 *
	 * @return UserSettingsCollectionBuilder
	 */
	public function collection(string $id_slug = 'profile', ?callable $template = null): UserSettingsCollectionBuilder {
		if (!isset($this->collections[$id_slug])) {
			$this->collections[$id_slug] = array(
			    'template' => $template,
			    'priority' => 10,
			);
		} else {
			if ($template !== null) {
				$this->collections[$id_slug]['template'] = $template;
			}
			if (!isset($this->collections[$id_slug]['priority'])) {
				$this->collections[$id_slug]['priority'] = 10;
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

		return new UserSettingsCollectionBuilder($this, $id_slug, $template, $commit, $setPriority);
	}

	/** 🚨
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

		$payload = array_merge($context ?? array(), array(
			'id_slug'         => $id_slug,
			'collection_meta' => $collection_meta,
			'sections'        => $sections,
			'values'          => $effective_values,
			'content'         => $this->_render_default_sections_wrapper($id_slug, $sections, $effective_values),
			'errors_by_field' => $this->message_handler->get_all_messages(),
		));

		// Use custom template if provided, otherwise use default
		if (is_callable($collection_meta['template'])) {
			($collection_meta['template'])($payload);
		} else {
			$this->_render_default_root($payload);
		}

		$this->form_session->enqueue_assets();
	}

	// Protected

	/**
	 * Get system default template for UserSettings context.
	 *
	 * @param string $template_type The template type.
	 *
	 * @return string The default template key.
	 */
	protected function _get_system_default_template(string $template_type): string {
		$defaults = array(
			'collection' => 'form-wrapper',
			'section'    => 'section-wrapper',
			'group'      => 'group-wrapper',
			'field'      => 'field-wrapper',
		);
		return $defaults[$template_type] ?? 'field-wrapper';
	}

	/**
	 * Render the default user collection template markup.
	 *
	 * @param array $context Template context.
	 * @return void
	 */
	protected function _render_default_root(array $context): void {
		echo $this->views->render('user.default-collection', $context);
	}

	/**
	 * Context specific fender a field wrapper warning.
	 * Can be customised for tables based layouts etc.
	 *
	 * @return string Rendered field HTML.
	 */
	protected function _render_default_field_wrapper_warning($message) {
		return '<tr><td colspan="2" class="error">Field rendering failed: ' . esc_html($message) . '</td></tr>';
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
}
