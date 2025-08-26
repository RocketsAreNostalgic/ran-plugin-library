<?php
/**
 * WPWrappersTrait.php
 *
 * @package Ran\PluginLib\Util
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
	 * Note: For anything beyond simple, one-off registrations or when a hooks manager is already available,
	 * prefer using the HooksManager API (via HooksManagementTrait::register_action) which provides
	 * deduplication, grouping, conditional registration, and richer logging.
	 *
	 * @param string $hook The WordPress hook to add the action to.
	 * @param mixed $callback The callback function or method to be executed.
	 * @param int $priority Optional. The priority of the action. Default 10.
	 * @param int $accepted_args Optional. The number of arguments the callback accepts. Default 1.
	 * @return void
	 * @see \Ran\PluginLib\HooksAccessory\HooksManagementTrait::register_action
	 * @see \Ran\PluginLib\HooksAccessory\HooksManager
	 * @codeCoverageIgnore
	 */
	public function _do_add_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): void {
		\add_action($hook, $callback, $priority, $accepted_args);
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
		// Due to a shorcoming in WP_Mock's behavior, did_action returns null in tests.
		// So we always return an integer, even if did_action returns null in tests,
		$result = \did_action($hook_name);
		return is_null($result) ? 0 : (int) $result;
	}

	/**
	 * Wrapper method for WordPress add_filter function.
	 *
	 * This method provides a consistent interface for adding filters across the codebase
	 * and allows for easier testing and potential future modifications to filter registration.
	 *
	 * Note: For anything beyond simple, one-off registrations or when a hooks manager is already available,
	 * prefer using the HooksManager API (via HooksManagementTrait::register_filter) which provides
	 * deduplication, grouping, conditional registration, and richer logging.
	 *
	 * @param string $hook The WordPress hook to add the filter to.
	 * @param mixed $callback The callback function or method to be executed.
	 * @param int $priority Optional. The priority of the filter. Default 10.
	 * @param int $accepted_args Optional. The number of arguments the callback accepts. Default 1.
	 * @return void
	 * @see \Ran\PluginLib\HooksAccessory\HooksManagementTrait::register_filter
	 * @see \Ran\PluginLib\HooksAccessory\HooksManager
	 * @codeCoverageIgnore
	 */
	public function _do_add_filter(string $hook, $callback, int $priority = 10, int $accepted_args = 1): void {
		\add_filter($hook, $callback, $priority, $accepted_args);
	}


	/**
	 * public wrapper for WordPress remove_action function
	 *
	 * Note: If an action was registered via HooksManager, prefer removing it via
	 * HooksManager too to keep internal tracking in sync:
	 * `$this->_get_hooks_manager()->remove_hook('action', $hook_name, $callback, $priority)`.
	 * This wrapper remains valid for one-off direct removals.
	 *
	 * @param string $hook_name The name of the action
	 * @param callable $callback The callback function
	 * @param int $priority The priority (default: 10)
	 * @return bool True if successful, false otherwise
	 */
	public function _do_remove_action(string $hook_name, callable $callback, int $priority = 10): bool {
		return \remove_action($hook_name, $callback, $priority);
	}

	/**
	 * public wrapper for WordPress remove_filter function
	 *
	 * Note: If a filter was registered via HooksManager, prefer removing it via
	 * HooksManager as well to keep tracking in sync:
	 * `$this->_get_hooks_manager()->remove_hook('filter', $hook_name, $callback, $priority)`.
	 * This wrapper remains valid for one-off direct removals.
	 *
	 * @param string $hook_name The name of the filter
	 * @param callable $callback The callback function
	 * @param int $priority The priority (default: 10)
	 * @return bool True if successful, false otherwise
	 */
	public function _do_remove_filter(string $hook_name, callable $callback, int $priority = 10): bool {
		return \remove_filter($hook_name, $callback, $priority);
	}

	/**
	 * public wrapper for WordPress do_action function
	*
	* Invocation guidance: Registration should go through HooksManager, but
	* dispatching an action is unaffected; calling WordPress `do_action` directly
	* via this wrapper is appropriate.
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
	 * Invocation guidance: Registration should go through HooksManager, but
	 * applying filters to a value can use this wrapper (or
	 * `$this->_get_hooks_manager()->apply_filters(...)`, which forwards here).
	 *
	 * @param string $hook_name The name of the filter
	 * @param mixed $value The value to filter
	 * @param mixed ...$args Additional arguments to pass to the callbacks
	 * @return mixed The filtered value
	 */
	public function _do_apply_filter(string $hook_name, $value, ...$args) {
		return \apply_filters($hook_name, $value, ...$args);
	}

	/**
	 * Public wrapper for WordPress get_option function
	 *
	 * @param  string $option
	 * @param  mixed  $default
	 *
	 * @return mixed
	 */
	public function _do_get_option(string $option, mixed $default = false): mixed {
		return \get_option($option, $default);
	}

	/**
	 * Public wrapper for WordPress update_option function
	 *
	 * @param  string $option
	 * @param  mixed  $value
	 * @param  mixed  $autoload
	 *
	 * @return bool
	 */
	public function _do_update_option(string $option, mixed $value, mixed $autoload = 'yes'): bool {
		return \update_option($option, $value, $autoload);
	}

	/**
	 * Public wrapper for WordPress add_option function
	 * @codeCoverageIgnore
	 */
	public function _do_add_option(string $option, mixed $value = '', string $deprecated = '', mixed $autoload = 'yes'): bool {
		// Some test shims may return null; always normalize to strict bool.
		return (bool) \add_option($option, $value, $deprecated, $autoload);
	}

	/**
	 * Public wrapper for WordPress delete_option function
	 * @codeCoverageIgnore
	 */
	public function _do_delete_option(string $option): bool {
		// Some test shims may return null; always normalize to strict bool.
		return (bool) \delete_option($option);
	}

	/**
	 * Public wrapper for WordPress get_site_option function (Network scope)
	 *
	 * @param  string $option
	 * @param  mixed  $default
	 * @return mixed
	 */
	public function _do_get_site_option(string $option, mixed $default = false): mixed {
		return \get_site_option($option, $default);
	}

	/**
	 * Public wrapper for WordPress update_site_option function (Network scope)
	 *
	 * @param  string $option
	 * @param  mixed  $value
	 * @return bool
	 */
	public function _do_update_site_option(string $option, mixed $value): bool {
		return \update_site_option($option, $value);
	}

	/**
	 * Public wrapper for WordPress add_site_option function (Network scope)
	 *
	 * Some test shims may return null; always normalize to strict bool.
	 *
	 * @param  string $option
	 * @param  mixed  $value
	 * @return bool
	 */
	public function _do_add_site_option(string $option, mixed $value = ''): bool {
		return (bool) \add_site_option($option, $value);
	}

	/**
	 * Public wrapper for WordPress delete_site_option function (Network scope)
	 *
	 * Some test shims may return null; always normalize to strict bool.
	 *
	 * @param  string $option
	 * @return bool
	 */
	public function _do_delete_site_option(string $option): bool {
		return (bool) \delete_site_option($option);
	}

	/**
	 * Public wrapper for WordPress get_blog_option function (Blog scope)
	 *
	 * @param  int    $blog_id
	 * @param  string $option
	 * @param  mixed  $default
	 * @return mixed
	 */
	public function _do_get_blog_option(int $blog_id, string $option, mixed $default = false): mixed {
		return \get_blog_option($blog_id, $option, $default);
	}

	/**
	 * Public wrapper for WordPress update_blog_option function (Blog scope)
	 *
	 * @param  int    $blog_id
	 * @param  string $option
	 * @param  mixed  $value
	 * @return bool
	 */
	public function _do_update_blog_option(int $blog_id, string $option, mixed $value): bool {
		return \update_blog_option($blog_id, $option, $value);
	}

	/**
	 * Public wrapper for WordPress add_blog_option function (Blog scope)
	 *
	 * Some test shims may return null; always normalize to strict bool.
	 *
	 * @param  int    $blog_id
	 * @param  string $option
	 * @param  mixed  $value
	 * @return bool
	 */
	public function _do_add_blog_option(int $blog_id, string $option, mixed $value = ''): bool {
		return (bool) \add_blog_option($blog_id, $option, $value);
	}

	/**
	 * Public wrapper for WordPress delete_blog_option function (Blog scope)
	 *
	 * Some test shims may return null; always normalize to strict bool.
	 *
	 * @param  int    $blog_id
	 * @param  string $option
	 * @return bool
	 */
	public function _do_delete_blog_option(int $blog_id, string $option): bool {
		return (bool) \delete_blog_option($blog_id, $option);
	}

	/**
	 * Public wrapper for WordPress get_user_option function (User scope)
	 *
	 * Note: WP signature is get_user_option($option, $user, $deprecated=''). We flip
	 * the first two arguments for consistency with other wrappers.
	 *
	 * @param  int    $user_id
	 * @param  string $option
	 * @param  mixed  $deprecated
	 * @return mixed
	 */
	public function _do_get_user_option(int $user_id, string $option, mixed $deprecated = ''): mixed {
		return \get_user_option($option, $user_id, $deprecated);
	}

	/**
	 * Public wrapper for WordPress update_user_option function (User scope)
	 *
	 * @param  int    $user_id
	 * @param  string $option
	 * @param  mixed  $value
	 * @param  bool   $global
	 * @return bool
	 */
	public function _do_update_user_option(int $user_id, string $option, mixed $value, bool $global = false): bool {
		return (bool) \update_user_option($user_id, $option, $value, $global);
	}

	/**
	 * Public wrapper for WordPress delete_user_option function (User scope)
	 *
	 * @param  int    $user_id
	 * @param  string $option
	 * @param  bool   $global
	 * @return bool
	 */
	public function _do_delete_user_option(int $user_id, string $option, bool $global = false): bool {
		return (bool) \delete_user_option($user_id, $option, $global);
	}

	/**
	 * Public wrapper for WordPress get_user_meta function (User meta)
	 *
	 * @param int    $user_id User ID
	 * @param string $key     Meta key
	 * @param bool   $single  Whether to return a single value. Default true.
	 * @return mixed          Meta value(s)
	 */
	public function _do_get_user_meta(int $user_id, string $key, bool $single = true): mixed {
		return \get_user_meta($user_id, $key, $single);
	}

	/**
	 * Public wrapper for WordPress update_user_meta function (User meta)
	 *
	 * Some test shims may return null; always normalize to strict bool.
	 *
	 * @param int    $user_id User ID
	 * @param string $key     Meta key
	 * @param mixed  $value   Meta value
	 * @return bool           True on success
	 */
	public function _do_update_user_meta(int $user_id, string $key, mixed $value): bool {
		return (bool) \update_user_meta($user_id, $key, $value);
	}

	/**
	 * Public wrapper for WordPress add_user_meta function (User meta)
	 *
	 * Some test shims may return null; always normalize to strict bool.
	 *
	 * @param int    $user_id User ID
	 * @param string $key     Meta key
	 * @param mixed  $value   Meta value
	 * @param bool   $unique  Whether the meta key should be unique. Default false.
	 * @return bool           True on success
	 */
	public function _do_add_user_meta(int $user_id, string $key, mixed $value, bool $unique = false): bool {
		return (bool) \add_user_meta($user_id, $key, $value, $unique);
	}

	/**
	 * Public wrapper for WordPress delete_user_meta function (User meta)
	 *
	 * Some test shims may return null; always normalize to strict bool.
	 *
	 * @param int    $user_id User ID
	 * @param string $key     Meta key
	 * @return bool           True on success
	 */
	public function _do_delete_user_meta(int $user_id, string $key): bool {
		return (bool) \delete_user_meta($user_id, $key);
	}

	/**
	 * Public wrapper for WordPress wp_load_alloptions() with availability guard
	 * Returns autoloaded options map when available; null when WP function is unavailable.
	 * @param bool $force_cache Optional. Whether to force an update of the local cache from the persistent cache. Default false.
	 * @return array|null
	 * @codeCoverageIgnore
	 */
	public function _do_wp_load_alloptions($force_cache = false): ?array {
		if (\function_exists('wp_load_alloptions')) {
			$all = \wp_load_alloptions($force_cache);
			// If WP returns a non-array here, treat as undeterminable
			return is_array($all) ? $all : null;
		}
		return null;
	}

	/**
	 * Public wrapper for WordPress get_current_blog_id()
	 * Returns current blog ID; when function missing, defaults to 0.
	 * @return int
	 */
	public function _do_get_current_blog_id(): int {
		if (\function_exists('get_current_blog_id')) {
			return (int) \get_current_blog_id();
		}
		return 0;
	}

	/**
	 * Public wrapper for WordPress sanitize_key with fallback when WP not loaded
	 * @codeCoverageIgnore
	 */
	public function _do_sanitize_key(string $key): string {
		if (\function_exists('sanitize_key')) {
			return \sanitize_key($key);
		}
		$key = strtolower($key);
		$key = preg_replace( '/[^a-z0-9_\-]/', '', $key );
		return trim($key, '_');
	}
}
