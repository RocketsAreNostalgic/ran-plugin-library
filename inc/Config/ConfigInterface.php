<?php
/**
 * An interface for the Config class.
 *
 * @package  RanPlugin
 */

declare(strict_types=1);

namespace Ran\PluginLib\Config;

/**
 * Interface for the Config class which holds key information about the plugin.
 *
 * @package  RanPluginLib
 */
interface ConfigInterface {
	/**
	 * Returns the value of an active option, or false.
	 *
	 * @param  string $key The key of the option.
	 *
	 * @param  string $option_string The option name.
	 *
	 * @return array|string|false the value of the option key (string or array), or false
	 */
	public function get_plugin_options( string $key, string $option_string = '' ): mixed;

	/**
	 * Returns an array of plugin config properties.
	 *
	 * @return array config array
	 */
	public function get_plugin_config(): array;
}
