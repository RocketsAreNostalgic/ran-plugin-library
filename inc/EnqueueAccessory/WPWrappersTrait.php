<?php
/**
 * WPWrappersTrait.php
 *
 * @package Ran\PluginLib\EnqueueAccessory
 * @author  Ran Plugin Lib <support@ran.org>
 * @license GPL-2.0+ <http://www.gnu.org/licenses/gpl-2.0.txt>
 * @link    https://github.com/RocketsAreNostalgic
 * @since   0.1.0
 */

namespace Ran\PluginLib\EnqueueAccessory;
/**
 * Trait WPWrappersTrait
 *
 * Wrappers for common WordPress functions to allow for easier testing and potential future modifications.
 *
 * @package Ran\PluginLib\EnqueueAccessory
 */
trait WPWrappersTrait {
	/**
	 * Wrapper method for WordPress add_action function.
	 *
	 * This method provides a consistent interface for adding actions across the codebase
	 * and allows for easier testing and potential future modifications to action registration.
	 *
	 * @param string $hook The WordPress hook to add the action to.
	 * @param mixed $callback The callback function or method to be executed.
	 * @param int $priority Optional. The priority of the action. Default 10.
	 * @param int $accepted_args Optional. The number of arguments the callback accepts. Default 1.
	 * @return void
	 * @codeCoverageIgnore
	 */
	protected function _do_add_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): void {
		add_action($hook, $callback, $priority, $accepted_args);
	}

	/**
	 * Wrapper method for WordPress add_filter function.
	 *
	 * This method provides a consistent interface for adding filters across the codebase
	 * and allows for easier testing and potential future modifications to filter registration.
	 *
	 * @param string $hook The WordPress hook to add the filter to.
	 * @param mixed $callback The callback function or method to be executed.
	 * @param int $priority Optional. The priority of the filter. Default 10.
	 * @param int $accepted_args Optional. The number of arguments the callback accepts. Default 1.
	 * @return void
	 * @codeCoverageIgnore
	 */
	protected function _do_add_filter(string $hook, $callback, int $priority = 10, int $accepted_args = 1): void {
		add_filter($hook, $callback, $priority, $accepted_args);
	}
}
