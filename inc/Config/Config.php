<?php
/**
 * A convenience Config class.
 *
 *  @package  RanPluginLib
 */

declare(strict_types = 1);

namespace Ran\PluginLib\Config;

use Ran\PluginLib\Config\ConfigAbstract;
use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Options\Storage\StorageContext;

/**
 * Final Config class which holds key information about the plugin.
 */
class Config extends ConfigAbstract implements ConfigInterface {
	/**
	 * Initialize configuration from a plugin root file.
	 *
	 * @param string $pluginFile Absolute path to the plugin root file (typically __FILE__).
	 * @return self
	 */
	public static function fromPluginFile(string $pluginFile): self {
		$instance = new self();
		$instance->_hydrateFromPlugin($pluginFile);
		return $instance;
	}

	/**
	 * Initialize configuration from a plugin root file with a custom logger.
	 * Ensures the logger is used during hydration.
	 */
	public static function fromPluginFileWithLogger(string $pluginFile, \Ran\PluginLib\Util\Logger $logger): self {
		$instance = new self();
		$instance->set_logger($logger);
		$instance->_hydrateFromPlugin($pluginFile);
		return $instance;
	}

	/**
	 * Initialize configuration for a theme directory.
	 *
	 * @param string|null $stylesheetDir Optional absolute path to the theme stylesheet directory.
	 * @return self
	 */
	public static function fromThemeDir(?string $stylesheetDir = null): self {
		$instance = new self();
		$instance->_hydrateFromTheme($stylesheetDir ?? '');
		return $instance;
	}

	/**
	 * Initialize configuration for a theme directory with a custom logger.
	 * Ensures the logger is used during hydration.
	 */
	public static function fromThemeDirWithLogger(?string $stylesheetDir, \Ran\PluginLib\Util\Logger $logger): self {
		$instance = new self();
		$instance->set_logger($logger);
		$instance->_hydrateFromTheme($stylesheetDir ?? '');
		return $instance;
	}

	/**
	 * Accessor: get a pre-wired RegisterOptions instance for this app's options key.
	 *
	 * @param \Ran\PluginLib\Options\Storage\StorageContext|null $context  Typed storage context; when null defaults to site scope.
	 * @param bool                                                   $autoload Whether to autoload on create (site/blog storages only).
	 * @return \Ran\PluginLib\Options\RegisterOptions
	 */
	public function options(?StorageContext $context = null, bool $autoload = true): \Ran\PluginLib\Options\RegisterOptions {
		// Delegate to typed-first from_config
		return RegisterOptions::from_config($this, $context, $autoload);
	}
}
