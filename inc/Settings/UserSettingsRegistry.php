<?php
/**
 * User Settings Registry
 *
 * Lightweight registry for user profile settings with lazy loading.
 * Parallel to AdminMenuRegistry but for user scope.
 *
 * @package Ran\PluginLib\Settings
 * @since   0.2.0
 */

declare(strict_types=1);

namespace Ran\PluginLib\Settings;

use Ran\PluginLib\Util\WPWrappersTrait;
use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Settings\UserSettings;
use Ran\PluginLib\Options\Storage\StorageContext;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Forms\ErrorNoticeRenderer;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use Ran\PluginLib\Forms\Component\ComponentLoader;
use Ran\PluginLib\Config\ConfigInterface;

/**
 * Lightweight registry for user profile settings.
 *
 * Registers WordPress profile hooks immediately but defers expensive
 * dependency creation (RegisterOptions, ComponentManifest) until
 * a profile page is actually rendered.
 */
class UserSettingsRegistry implements SettingsRegistryInterface {
	use WPWrappersTrait;

	/**
	 * @var string Main option key for storage.
	 */
	private string $option_key;

	/**
	 * @var StorageContext Storage context (user scope).
	 */
	private StorageContext $storage_context;

	/**
	 * @var bool Whether to autoload options.
	 */
	private bool $autoload;

	/**
	 * @var Logger Logger instance.
	 */
	private Logger $logger;

	/**
	 * @var ConfigInterface|null Plugin configuration.
	 */
	private ?ConfigInterface $config;

	/**
	 * @var RegisterOptions|null Lazily created options instance.
	 */
	private ?RegisterOptions $options = null;

	/**
	 * @var UserSettings|null Lazily created settings instance.
	 */
	private ?UserSettings $settings = null;

	/**
	 * @var array<string, array> Collection metadata indexed by slug.
	 */
	private array $collections = array();

	/**
	 * @var array<string, callable> Render callbacks indexed by collection slug.
	 */
	private array $render_callbacks = array();

	/**
	 * @var bool Whether hooks have been registered.
	 */
	private bool $hooks_registered = false;

	/**
	 * Tracks which collection render callbacks have already run in this request.
	 *
	 * @var array<string,bool>
	 */
	private array $render_callbacks_ran = array();

	/**
	 * The user_id used for the current request's preflight build.
	 */
	private ?int $preflight_user_id = null;

	/**
	 * Constructor.
	 *
	 * @param string               $option_key      Main option key for storage.
	 * @param StorageContext       $storage_context Storage context (must be user scope).
	 * @param bool                 $autoload        Whether to autoload options.
	 * @param Logger               $logger          Logger instance.
	 * @param ConfigInterface|null $config          Plugin configuration.
	 */
	public function __construct(
		string $option_key,
		StorageContext $storage_context,
		bool $autoload,
		Logger $logger,
		?ConfigInterface $config = null
	) {
		$this->option_key      = $option_key;
		$this->storage_context = $storage_context;
		$this->autoload        = $autoload;
		$this->logger          = $logger;
		$this->config          = $config;
	}

	public function _preflight_enqueue_assets($hook_suffix = ''): void {
		$pagenow = $GLOBALS['pagenow'] ?? '';
		if ($pagenow !== 'profile.php' && $pagenow !== 'user-edit.php') {
			return;
		}

		$user_id = $this->_resolve_profile_user_id();
		if ($user_id <= 0) {
			return;
		}

		// Ensure dependencies exist early so assets can enqueue in <head>
		$this->_ensure_settings($user_id);
		if ($this->settings === null) {
			return;
		}

		if ($this->preflight_user_id !== $user_id) {
			$this->render_callbacks_ran = array();
			$this->preflight_user_id    = $user_id;
		}

		foreach ($this->collections as $slug => $meta) {
			if (($this->render_callbacks_ran[$slug] ?? false) === true) {
				continue;
			}
			$callback = $this->render_callbacks[$slug] ?? null;
			$builder  = $this->settings->collection($slug, null, $meta);
			if ($callback !== null) {
				try {
					$callback($builder);
				} catch (\Throwable $e) {
					$this->logger->error('user_settings_registry.preflight_render_callback_error', array(
						'collection' => $slug,
						'message'    => $e->getMessage(),
						'file'       => $e->getFile(),
						'line'       => $e->getLine(),
					));
				}
			}
			$builder->end_collection();
			$this->render_callbacks_ran[$slug] = true;
		}

		$aliases = $this->settings->collect_used_component_aliases();
		$this->settings->get_component_manifest()->enqueue_assets_for_aliases($aliases);
	}

	private function _resolve_profile_user_id(): int {
		$pagenow = $GLOBALS['pagenow'] ?? '';
		if ($pagenow === 'user-edit.php') {
			$user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
			if ($user_id > 0) {
				return $user_id;
			}
		}

		if (isset($GLOBALS['profileuser']) && $GLOBALS['profileuser'] instanceof \WP_User) {
			return (int) $GLOBALS['profileuser']->ID;
		}

		return (int) $this->_do_get_current_user_id();
	}

	/**
	 * Get the logger instance.
	 *
	 * @return Logger
	 */
	public function get_logger(): Logger {
		return $this->logger;
	}

	/**
	 * Register settings with a builder callback.
	 *
	 * @param callable $callback Receives this registry instance.
	 * @return void
	 */
	public function register(callable $callback): void {
		// Run callback to collect collection definitions (lightweight)
		try {
			$callback($this);
		} catch (\Throwable $e) {
			$this->logger->error('user_settings_registry.register_callback_error', array(
				'message' => $e->getMessage(),
				'file'    => $e->getFile(),
				'line'    => $e->getLine(),
			));

			// Show error via admin_notices so site doesn't crash
			add_action('admin_notices', function () use ($e) {
				ErrorNoticeRenderer::render($e, 'UserSettingsRegistry Error');
			});
			return; // Don't register hooks if callback failed
		}

		// Register WordPress hooks for profile pages
		$this->_register_hooks();

		$this->logger->debug('user_settings_registry.registered', array(
			'option_key'  => $this->option_key,
			'collections' => array_keys($this->collections),
		));
	}

	/**
	 * Start defining a collection.
	 *
	 * @param string $slug Collection slug/identifier.
	 * @return UserCollectionBuilder
	 */
	public function collection(string $slug): UserCollectionBuilder {
		return new UserCollectionBuilder($this, $slug);
	}

	/**
	 * Get the option key.
	 *
	 * @return string
	 */
	public function get_option_key(): string {
		return $this->option_key;
	}

	/**
	 * Store collection metadata and render callback.
	 *
	 * @internal Called by UserCollectionBuilder.
	 *
	 * @param string        $slug     Collection slug.
	 * @param array         $meta     Collection metadata.
	 * @param callable|null $callback Render callback.
	 * @return void
	 */
	public function _store_collection(string $slug, array $meta, ?callable $callback): void {
		$this->collections[$slug]      = $meta;
		$this->render_callbacks[$slug] = $callback;
	}

	/**
	 * Register WordPress hooks for profile pages.
	 *
	 * @return void
	 */
	private function _register_hooks(): void {
		if ($this->hooks_registered) {
			return;
		}

		// Render hooks
		$this->_do_add_action('show_user_profile', array($this, '_render_collections'), 10, 1);
		$this->_do_add_action('edit_user_profile', array($this, '_render_collections'), 10, 1);

		// Save hooks
		$this->_do_add_action('personal_options_update', array($this, '_save_collections'), 10, 1);
		$this->_do_add_action('edit_user_profile_update', array($this, '_save_collections'), 10, 1);

		$this->_do_add_action('admin_enqueue_scripts', array($this, '_preflight_enqueue_assets'), 10, 1);

		$this->hooks_registered = true;

		$this->logger->debug('user_settings_registry.hooks_registered', array(
			'option_key' => $this->option_key,
		));
	}

	/**
	 * Ensure UserSettings instance exists.
	 *
	 * Creates RegisterOptions and ComponentManifest on first call.
	 * If the storage context has a deferred user_id (null), it will be
	 * resolved from the provided user_id parameter.
	 *
	 * @param int|null $user_id Optional user ID from hook context.
	 * @return void
	 */
	private function _ensure_settings(?int $user_id = null): void {
		if ($this->settings !== null) {
			return;
		}

		// Resolve storage context with user_id if deferred
		$context = $this->_resolve_storage_context($user_id);

		$this->logger->debug('user_settings_registry.creating_settings', array(
			'option_key' => $this->option_key,
			'user_id'    => $context->user_id,
		));

		// Create RegisterOptions
		$this->options = new RegisterOptions(
			$this->option_key,
			$context,
			$this->autoload,
			$this->logger
		);

		// Create ComponentManifest
		$componentDir = new ComponentLoader(
			dirname(__DIR__) . '/Forms/Components',
			$this->logger
		);

		if ($this->config !== null) {
			$registrations = array();
			foreach ($this->collections as $meta) {
				if (!is_array($meta)) {
					continue;
				}
				$register_components = $meta['register_components'] ?? array();
				if (!is_array($register_components)) {
					continue;
				}
				foreach ($register_components as $options) {
					if (!is_array($options)) {
						continue;
					}
					$registrations[] = $options;
				}
			}
			$componentDir->register_components_batch($registrations, $this->config);
		}
		$manifest = new ComponentManifest($componentDir, $this->logger);

		// Create UserSettings
		$this->settings = new UserSettings(
			$this->options,
			$manifest,
			$this->config,
			$this->logger
		);

		// Tell UserSettings not to register its own hooks (we handle them)
		$this->settings->skip_hook_registration();
	}

	/**
	 * Render all collections on profile page.
	 *
	 * @internal Called by WordPress show_user_profile/edit_user_profile hooks.
	 *
	 * @param \WP_User $user The user being edited.
	 * @return void
	 */
	public function _render_collections($user): void {
		if (!($user instanceof \WP_User)) {
			return;
		}

		// Create expensive dependencies NOW, passing user_id from hook context
		$this->_ensure_settings((int) $user->ID);

		if ($this->settings === null) {
			$this->logger->error('user_settings_registry.settings_creation_failed');
			return;
		}

		foreach ($this->collections as $slug => $meta) {
			if (($this->render_callbacks_ran[$slug] ?? false) === true) {
				continue;
			}
			$callback = $this->render_callbacks[$slug] ?? null;
			$builder  = $this->settings->collection($slug, null, $meta);
			if ($callback !== null) {
				try {
					$callback($builder);
				} catch (\Throwable $e) {
					$this->logger->error('user_settings_registry.render_callback_error', array(
						'collection' => $slug,
						'message'    => $e->getMessage(),
						'file'       => $e->getFile(),
						'line'       => $e->getLine(),
					));
					ErrorNoticeRenderer::renderWithContext($e, 'UserSettings Error', 'collection', $slug);
					continue;
				}
			}
			$builder->end_collection();
			$this->render_callbacks_ran[$slug] = true;
		}

		// Boot and render
		$this->settings->boot();
		foreach (array_keys($this->collections) as $slug) {
			$this->settings->__render($slug, array('user' => $user));
		}
	}

	/**
	 * Save all collections on profile update.
	 *
	 * @internal Called by WordPress personal_options_update/edit_user_profile_update hooks.
	 *
	 * @param int $user_id The user ID being updated.
	 * @return void
	 */
	public function _save_collections(int $user_id): void {
		// Create expensive dependencies if not already done, passing user_id from hook context
		$this->_ensure_settings($user_id);

		if ($this->settings === null) {
			return;
		}

		// Run all render callbacks to define schema before saving
		$this->_run_all_render_callbacks();

		// Get the POST payload
		$main_option = $this->options->get_main_option_name();
		$payload     = isset($_POST[$main_option]) && is_array($_POST[$main_option])
			? $_POST[$main_option]
			: array();

		// Process file uploads - WordPress profile form has enctype added via JS
		$payload = $this->settings->process_file_uploads($payload);

		// UserSettings handles save via __save_settings
		$this->settings->__save_settings($payload, array('user_id' => $user_id));
	}

	/**
	 * Run all render callbacks to define schema for all collections.
	 *
	 * Called during POST save when we don't know which collection the form
	 * submission originated from. WordPress profile pages can have multiple
	 * collections, so ALL collection schemas must be registered to ensure
	 * any submitted field can be validated.
	 *
	 * Performance implications:
	 * - On POST: All collections' render callbacks execute, registering all fields.
	 *   The resulting schema bundle is cached per-request via FormsCore::__schema_bundle_cache.
	 * - On GET: Only the rendered collection's callback runs, registering only
	 *   that collection's fields.
	 *
	 * This mirrors AdminMenuRegistry::_run_all_render_callbacks() behavior.
	 * Debug logs for components without defaults are gated behind RAN_VERBOSE_DEBUG.
	 *
	 * @see AdminMenuRegistry::_run_all_render_callbacks() Admin equivalent
	 * @see FormsCore::_resolve_schema_bundle() Schema bundle caching
	 *
	 * @return void
	 */
	private function _run_all_render_callbacks(): void {
		if ($this->settings === null) {
			return;
		}

		foreach ($this->collections as $slug => $meta) {
			$callback = $this->render_callbacks[$slug] ?? null;

			// Create collection builder context
			// Second param is template (null = default), third is args including heading
			$builder = $this->settings->collection(
				$slug,
				null,
				$meta
			);

			// Run the on_render callback to define sections/fields/schema
			if ($callback !== null) {
				try {
					$callback($builder);
				} catch (\Throwable $e) {
					$this->logger->error('user_settings_registry.render_callback_error', array(
						'collection' => $slug,
						'message'    => $e->getMessage(),
						'file'       => $e->getFile(),
						'line'       => $e->getLine(),
					));
					// During save, we can't display HTML - error is logged
				}
			}

			// End the collection
			$builder->end_collection();
		}
	}

	/**
	 * Get the RegisterOptions instance (for schema registration).
	 *
	 * @return RegisterOptions|null
	 */
	public function get_options(): ?RegisterOptions {
		$this->_ensure_settings();
		return $this->options;
	}

	/**
	 * Resolve the storage context, creating a new one with user_id if deferred.
	 *
	 * If the original storage context has a null user_id (deferred resolution),
	 * this method creates a new StorageContext with the provided user_id.
	 * Otherwise, returns the original context unchanged.
	 *
	 * @param int|null $user_id User ID from hook context.
	 * @return StorageContext Resolved storage context with valid user_id.
	 * @throws \InvalidArgumentException If user_id cannot be resolved.
	 */
	private function _resolve_storage_context(?int $user_id): StorageContext {
		// If original context already has a user_id, use it
		if ($this->storage_context->user_id !== null) {
			return $this->storage_context;
		}

		// Deferred resolution: need to determine user_id now
		$resolved_user_id = $user_id;

		// Fallback chain if no user_id provided
		if ($resolved_user_id === null || $resolved_user_id <= 0) {
			// Try global profileuser (set by WordPress on profile pages)
			if (isset($GLOBALS['profileuser']) && $GLOBALS['profileuser'] instanceof \WP_User) {
				$resolved_user_id = (int) $GLOBALS['profileuser']->ID;
			}
		}

		if ($resolved_user_id === null || $resolved_user_id <= 0) {
			// Last resort: current user
			$resolved_user_id = (int) $this->_do_get_current_user_id();
		}

		if ($resolved_user_id <= 0) {
			$this->logger->error('user_settings_registry.cannot_resolve_user_id', array(
				'option_key' => $this->option_key,
			));
			throw new \InvalidArgumentException('UserSettingsRegistry: Cannot resolve user_id for deferred storage context.');
		}

		// Create new context with resolved user_id
		return StorageContext::forUserId(
			$resolved_user_id,
			$this->storage_context->user_storage,
			$this->storage_context->user_global
		);
	}
}
