<?php
/**
 * A convenience Config class.
 *
 *  @package  RanPluginLib
 */

declare(strict_types = 1);

namespace Ran\PluginLib\Config;

use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Settings\UserSettingsRegistry;
use Ran\PluginLib\Settings\SettingsRegistryInterface;
use Ran\PluginLib\Settings\AdminMenuRegistry;
use Ran\PluginLib\Options\Storage\StorageContext;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Options\OptionScope;
use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\Config\ConfigAbstract;

/**
 * Config class which holds key information about a plugin or theme.
 */
class Config extends ConfigAbstract implements ConfigInterface {
	/**
	 * Cache of settings registries by context key.
	 *
	 * @var array<string, SettingsRegistryInterface>
	 */
	private array $settings_cache = array();

	/**
	 * Static cache of Config instances by plugin file path.
	 *
	 * @var array<string, self>
	 */
	private static array $instances = array();

	/**
	 * Initialize configuration from a plugin root file.
	 *
	 * Returns a cached instance if one already exists for this plugin file,
	 * ensuring consistent registry caching across multiple calls.
	 *
	 * @param string $pluginFile Absolute path to the plugin root file (typically __FILE__).
	 * @return self
	 */
	public static function fromPluginFile(string $pluginFile): self {
		$key = realpath($pluginFile) ?: $pluginFile;

		if (isset(self::$instances[$key])) {
			return self::$instances[$key];
		}

		$instance = new self();
		$instance->_hydrateFromPlugin($pluginFile);
		self::$instances[$key] = $instance;

		return $instance;
	}

	/**
	 * Initialize configuration from a plugin root file with a custom logger.
	 * Ensures the logger is used during hydration.
	 *
	 * Returns a cached instance if one already exists for this plugin file.
	 * Note: The logger is only set on first creation.
	 *
	 * @param string $pluginFile Absolute path to the plugin root file (typically __FILE__).
	 * @param Logger $logger     Custom logger instance.
	 * @return self
	 */
	public static function fromPluginFileWithLogger(string $pluginFile, Logger $logger): self {
		$key = realpath($pluginFile) ?: $pluginFile;

		if (isset(self::$instances[$key])) {
			return self::$instances[$key];
		}

		$instance = new self();
		$instance->set_logger($logger);
		$instance->_hydrateFromPlugin($pluginFile);
		self::$instances[$key] = $instance;

		return $instance;
	}

	/**
	 * Initialize configuration for a theme directory.
	 *
	 * Returns a cached instance if one already exists for this theme directory.
	 *
	 * @param string|null $stylesheetDir Optional absolute path to the theme stylesheet directory.
	 * @return self
	 */
	public static function fromThemeDir(?string $stylesheetDir = null): self {
		$dir = $stylesheetDir ?? '';
		$key = 'theme:' . (realpath($dir) ?: $dir);

		if (isset(self::$instances[$key])) {
			return self::$instances[$key];
		}

		$instance = new self();
		$instance->_hydrateFromTheme($dir);
		self::$instances[$key] = $instance;

		return $instance;
	}

	/**
	 * Initialize configuration for a theme directory with a custom logger.
	 * Ensures the logger is used during hydration.
	 *
	 * Returns a cached instance if one already exists for this theme directory.
	 * Note: The logger is only set on first creation.
	 *
	 * @param string|null $stylesheetDir Optional absolute path to the theme stylesheet directory.
	 * @param Logger      $logger        Custom logger instance.
	 * @return self
	 */
	public static function fromThemeDirWithLogger(?string $stylesheetDir, Logger $logger): self {
		$dir = $stylesheetDir ?? '';
		$key = 'theme:' . (realpath($dir) ?: $dir);

		if (isset(self::$instances[$key])) {
			return self::$instances[$key];
		}

		$instance = new self();
		$instance->set_logger($logger);
		$instance->_hydrateFromTheme($dir);
		self::$instances[$key] = $instance;
		return $instance;
	}

	/**
	 * Reset the static instance cache.
	 *
	 * Primarily for testing purposes to ensure test isolation.
	 *
	 * @internal
	 * @return void
	 */
	public static function __reset_instance_cache(): void {
		self::$instances = array();
	}

	/**
	 * Accessor: get a pre-wired RegisterOptions instance for this app's options key.
	 *
	 * @param StorageContext|null $context  Typed storage context; when null defaults to site scope.
	 * @param bool 				  $autoload Whether to autoload on create (site/blog storages only).
	 * @return RegisterOptions
	 */
	public function options(?StorageContext $context = null, bool $autoload = true): RegisterOptions {
		return new RegisterOptions($this->get_options_key(), $context, $autoload, $this->get_logger());
	}

	/**
	 * Accessor: get a SettingsRegistry for lazy settings registration.
	 *
	 * This is the unified entry point for all settings (admin and user).
	 * Returns the appropriate registry based on the storage context scope:
	 * - User scope → UserSettingsRegistry (profile settings)
	 * - Site/Network/Blog scope → AdminMenuRegistry (admin settings)
	 *
	 * Hooks are registered immediately (lightweight), but expensive dependencies
	 * (RegisterOptions, ComponentManifest) are only created when actually needed.
	 *
	 * Usage:
	 * ```php
	 * // Admin settings (site scope)
	 * $config->settings($site_context)->register(function ($s) {
	 *     $s->settings_page('my-settings')
	 *         ->heading('My Settings')
	 *         ->on_render(fn($page) => $page->section(...)->field(...));
	 * });
	 *
	 * // User settings (user scope)
	 * $config->settings($user_context)->register(function ($s) {
	 *     $s->collection('my-prefs')
	 *         ->heading('My Preferences')
	 *         ->on_render(fn($c) => $c->section(...)->field(...));
	 * });
	 * ```
	 *
	 * @param StorageContext|null $context  Typed storage context; when null defaults to site scope.
	 * @param bool                $autoload Whether to autoload on create (site/blog storages only).
	 * @return SettingsRegistryInterface
	 */
	public function settings(?StorageContext $context = null, bool $autoload = true): SettingsRegistryInterface {
		$context  = $context ?? StorageContext::forSite('option');
		$cacheKey = $context->get_cache_key();

		// Return cached registry if available
		if (isset($this->settings_cache[$cacheKey])) {
			return $this->settings_cache[$cacheKey];
		}

		$scope = $context->scope;

		// User scope → UserSettingsRegistry
		if ($scope === OptionScope::User) {
			$registry = new UserSettingsRegistry(
				$this->get_options_key(),
				$context,
				$autoload,
				$this->get_logger(),
				$this
			);
		} else {
			// Site, Network, Blog → AdminMenuRegistry
			$registry = new AdminMenuRegistry(
				$this->get_options_key(),
				$context,
				$autoload,
				$this->get_logger(),
				$this
			);
		}

		$this->settings_cache[$cacheKey] = $registry;
		return $registry;
	}
}
