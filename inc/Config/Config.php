<?php
/**
 * A convenience Config class.
 *
 *  @package  RanPluginLib
 */

declare(strict_types = 1);

namespace Ran\PluginLib\Config;

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
	 * Semantics:
	 * - Returns a RegisterOptions instance bound to `get_options_key()` and this Config's logger.
	 * - If a schema is provided, it will be registered on the instance only.
	 * - This method does not perform any DB writes, seeding, or flushing.
	 * - Persistent data changes can be made through the RegisterOptions instance eg `$opts->register_schema($schema, true, true);`.
	 *
	 * @param array{autoload?: bool, schema?: array<string, mixed>} $args
	 * @return \Ran\PluginLib\Options\RegisterOptions
	 */
	public function options(array $args = array()): \Ran\PluginLib\Options\RegisterOptions {
		// Normalize args with defaults
		$defaults = array('autoload' => true, 'schema' => array());
		$args     = is_array($args) ? array_merge($defaults, $args) : $defaults;

		$autoload = (bool) ($args['autoload'] ?? true);
		$schema   = is_array($args['schema'] ?? null) ? $args['schema'] : array();

		// Build instance via factory; pass empty initial and empty schema to avoid constructor-side writes
		$opts = \Ran\PluginLib\Options\RegisterOptions::from_config(
			$this,
			array(),           // initial (none)
			$autoload,
			$this->get_logger(),
			array()            // schema (none at construction)
		);

		// Optional schema registration without seeding or flushing (no writes)
		if (!empty($schema)) {
			$opts->register_schema($schema, false, false);
		}

		return $opts;
	}
}
