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

namespace Ran\PluginLib\Util;
/**
 * Trait WPWrappersTrait
 *
 * Wrappers for common WordPress functions to allow for easier testing and potential future modifications.
 *
 * @package Ran\PluginLib\Util
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
	 * @return bool True if successful, false otherwise
	 * @codeCoverageIgnore
	 */
	public function _do_add_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): bool {
		add_action($hook, $callback, $priority, $accepted_args);
		// Ensure we return a boolean as required by the method signature
		return true;
	}

	/**
	 * Wrapper for WordPress did_action() function.
	 *
	 * This wrapper makes the code more testable by allowing the did_action
	 * function to be mocked in tests.
	 *
	 * @param string $hook_name The hook name to check.
	 * @return int Number of times the hook has been executed.
	 */
	public function _do_did_action(string $hook_name): int {
		// Ensure we always return an integer, even if did_action returns null in tests
		$result = did_action($hook_name);
		return is_null($result) ? 0 : (int) $result;
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
	 * @return bool True if successful, false otherwise
	 * @codeCoverageIgnore
	 */
	public function _do_add_filter(string $hook, $callback, int $priority = 10, int $accepted_args = 1): void {
		add_filter($hook, $callback, $priority, $accepted_args);
	}


	/**
	 * public wrapper for WordPress remove_action function
	 *
	 * @param string $hook_name The name of the action
	 * @param callable $callback The callback function
	 * @param int $priority The priority (default: 10)
	 * @return bool True if successful, false otherwise
	 */
	public function _do_remove_action(string $hook_name, callable $callback, int $priority = 10): bool {
		return remove_action($hook_name, $callback, $priority);
	}

	/**
	 * public wrapper for WordPress remove_filter function
	 *
	 * @param string $hook_name The name of the filter
	 * @param callable $callback The callback function
	 * @param int $priority The priority (default: 10)
	 * @return bool True if successful, false otherwise
	 */
	public function _do_remove_filter(string $hook_name, callable $callback, int $priority = 10): bool {
		return remove_filter($hook_name, $callback, $priority);
	}

	/**
	 * public wrapper for WordPress do_action function
	 *
	 * @param string $hook_name The name of the action
	 * @param mixed ...$args Arguments to pass to the callbacks
	 */
	public function _do_execute_action(string $hook_name, ...$args): void {
		\do_action($hook_name, ...$args);
	}

	/**
	 * public wrapper for WordPress apply_filters function
	 *
	 * @param string $hook_name The name of the filter
	 * @param mixed $value The value to filter
	 * @param mixed ...$args Additional arguments to pass to the callbacks
	 * @return mixed The filtered value
	 */
	public function _do_apply_filter(string $hook_name, $value, ...$args) {
		return apply_filters($hook_name, $value, ...$args);
	}
}
