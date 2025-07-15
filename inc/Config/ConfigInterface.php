<?php
/**
 * An interface for the Config class.
 *
 * @package  RanPlugin
 */

declare(strict_types = 1);

namespace Ran\PluginLib\Config;

use Ran\PluginLib\Util\Logger;

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
	 * @return array<string>|string|false the value of the option key (string or array), or false
	 */
	public function get_plugin_options( string $key, string $option_string = '' ): mixed;

	/**
	 * Returns an array of plugin config properties.
	 *
	 * @return array<string> config array
	 */
	public function get_plugin_config(): array;

	/**
	 * Returns an instance of the Logger.
	 *
	 * @return Logger The logger instance.
	 */
	public function get_logger(): Logger;

	/**
	 * Returns the developer-defined callback for checking if the environment is 'dev'.
	 *
	 * @return callable|null The callback function, or null if not set.
	 */
	public function get_is_dev_callback(): ?callable;

	/**
	 * Checks if the current environment is considered a 'development' environment.
	 *
	 * This method should encapsulate the logic for determining the environment status,
	 * such as checking for a specific callback or a WordPress constant.
	 *
	 * @return bool True if it's a development environment, false otherwise.
	 */
	public function is_dev_environment(): bool;
}
