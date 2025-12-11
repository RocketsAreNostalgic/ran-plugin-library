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

		$this->hooks_registered = true;

		$this->logger->debug('user_settings_registry.hooks_registered', array(
			'option_key' => $this->option_key,
		));
	}

	/**
	 * Ensure UserSettings instance exists.
	 *
	 * Creates RegisterOptions and ComponentManifest on first call.
	 *
	 * @return void
	 */
	private function _ensure_settings(): void {
		if ($this->settings !== null) {
			return;
		}

		$this->logger->debug('user_settings_registry.creating_settings', array(
			'option_key' => $this->option_key,
		));

		// Create RegisterOptions
		$this->options = new RegisterOptions(
			$this->option_key,
			$this->storage_context,
			$this->autoload,
			$this->logger
		);

		// Create ComponentManifest
		$componentDir = new ComponentLoader(
			dirname(__DIR__) . '/Forms/Components',
			$this->logger
		);
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

		// Create expensive dependencies NOW
		$this->_ensure_settings();

		if ($this->settings === null) {
			$this->logger->error('user_settings_registry.settings_creation_failed');
			return;
		}

		// Define and render each collection
		foreach ($this->collections as $slug => $meta) {
			$callback = $this->render_callbacks[$slug] ?? null;

			// Create collection builder context
			// Second param is template (null = default), third is args including heading
			$builder = $this->settings->collection(
				$slug,
				null,
				$meta
			);

			// Run the on_render callback to define sections/fields
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

					// Display error inline so developers see it
					ErrorNoticeRenderer::renderWithContext($e, 'UserSettings Error', 'collection', $slug);
					continue;
				}
			}

			// End the collection
			$builder->end_collection();
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
		// Create expensive dependencies if not already done
		$this->_ensure_settings();

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
	 * Used during save when schema needs to be defined before validation.
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
}
