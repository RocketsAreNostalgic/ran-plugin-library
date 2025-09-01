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
	 * Direct-forward: Yes (requires WP loaded)
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
	 * Direct-forward: No (normalizes null->0 for WP_Mock in tests)
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
	 * Direct-forward: Yes (requires WP loaded)
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
	 * Direct-forward: Yes (requires WP loaded)
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
	 * Direct-forward: Yes (requires WP loaded)
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
	* Direct-forward: Yes (requires WP loaded)
	 *
	 * @param string $hook_name The name of the action
	 * @param mixed ...$args Arguments to pass to the callbacks
	 * @return void
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
	 * Direct-forward: Yes (requires WP loaded)
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
	 * Direct-forward: Yes (requires WP loaded)
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
	 * Direct-forward: Yes (requires WP loaded)
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
	 *
	 * Direct-forward: Yes (requires WP loaded)
	 * @param string $option
	 * @param mixed $value
	 * @param string $deprecated
	 * @param mixed $autoload
	 * @return bool
	 * @codeCoverageIgnore
	 */
	public function _do_add_option(string $option, mixed $value = '', string $deprecated = '', mixed $autoload = 'yes'): bool {
		// Some test shims may return null; always normalize to strict bool.
		return (bool) \add_option($option, $value, $deprecated, $autoload);
	}

	/**
	 * Public wrapper for WordPress delete_option function
	 *
	 * Direct-forward: Yes (requires WP loaded)
	 * @param string $option
	 * @return bool
	 * @codeCoverageIgnore
	 */
	public function _do_delete_option(string $option): bool {
		// Some test shims may return null; always normalize to strict bool.
		return (bool) \delete_option($option);
	}

	/**
	 * Public wrapper for WordPress get_site_option function (Network scope)
	 *
	 * Direct-forward: Yes (requires WP loaded)
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
	 * Direct-forward: Yes (requires WP loaded)
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
	 * Direct-forward: Yes (requires WP loaded)
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
	 * Direct-forward: Yes (requires WP loaded)
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
	 * Direct-forward: Yes (requires WP loaded)
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
	 * Direct-forward: Yes (requires WP loaded)
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
	 * Direct-forward: Yes (requires WP loaded)
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
	 * Direct-forward: Yes (requires WP loaded)
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
	 * Direct-forward: Yes (requires WP loaded; parameter order flipped for convenience)
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
	 * Direct-forward: Yes (requires WP loaded)
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
	 * Direct-forward: Yes (requires WP loaded)
	 * @param  int    $user_id
	 * @param  string $option_name
	 * @param  bool   $is_global
	 * @return bool
	 */
	public function _do_delete_user_option(int $user_id, string $option_name, bool $is_global = false): bool {
		return (bool) \delete_user_option($user_id, $option_name, $is_global);
	}

	/**
	 * Public wrapper for WordPress get_user_meta function (User meta)
	 *
	 * Direct-forward: Yes (requires WP loaded)
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
	 * Direct-forward: Yes (requires WP loaded)
	 * @param int    $user_id User ID
	 * @param string $key     Meta key
	 * @param mixed  $value   Meta value
	 * @param string $prev_value Previous meta value
	 * @return int|bool           True on success
	 */
	public function _do_update_user_meta(int $user_id, string $key, mixed $value, string $prev_value = ''): int|bool {
		return \update_user_meta($user_id, $key, $value, $prev_value);
	}

	/**
	 * Public wrapper for WordPress add_user_meta function (User meta)
	 * Direct-forward: Yes (requires WP loaded)
	 *
	 * @param int    $user_id User ID
	 * @param string $key     Meta key
	 * @param mixed  $value   Meta value
	 * @param bool   $unique  Whether the meta key should be unique. Default false.
	 * @return int|false           Meta ID on success, false on failure.
	 */
	public function _do_add_user_meta(int $user_id, string $key, mixed $value, bool $unique = false): int|false {
		return \add_user_meta($user_id, $key, $value, $unique);
	}

	/**
	 * Public wrapper for WordPress delete_user_meta function (User meta)
	 *
	 * Some test shims may return null; always normalize to strict bool.
	 * Direct-forward: Yes (requires WP loaded)
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
	 *
	 * Availability-guarded: Yes
	 * Rationale: In CLI/tests without full WP bootstrap, wp_load_alloptions() may be undefined.
	 * Returning null lets callers feature-detect autoload cache without fatals.
	 *
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
	 *
	 * Availability-guarded: Yes
	 * Rationale: In non-multisite/early contexts get_current_blog_id() may be unavailable.
	 * Returning 0 provides a neutral default for tests/CLI.
	 *
	 * @return int
	 */
	public function _do_get_current_blog_id(): int {
		if (\function_exists('get_current_blog_id')) {
			return (int) \get_current_blog_id();
		}
		return 0;
	}

	/**
	 * Public wrapper for WordPress current_user_can
	 *
	 * Availability-guarded: Yes
	 * Rationale: In tests/CLI or early boot, capability APIs may be unavailable; returning true
	 * preserves existing library behavior in non-WP contexts while allowing strict checks in WP.
	 *
	 * @param string $capability Capability name
	 * @param mixed  ...$args    Optional capability args (e.g., user ID for edit_user)
	 * @return bool
	 * @codeCoverageIgnore
	 */
	public function _do_current_user_can(string $capability, ...$args): bool {
		// In WP_Mock-powered unit tests, treat caps as allowed-by-default to preserve
		// historical behavior where caps APIs were considered unavailable.
		if (\defined('WP_MOCK')) {
			return true;
		}

		if (\function_exists('current_user_can')) {
			return (bool) \current_user_can($capability, ...$args);
		}
		return true;
	}

	/**
	 * Public wrapper for WordPress sanitize_key with fallback when WP not loaded
	 *
	 * Availability-guarded: Yes
	 * Rationale: Provide stable key normalization when sanitize_key() is missing
	 * (e.g., early CLI/tests). Also guards against unexpected empty returns by
	 * falling back to internal normalization mirroring WP behavior.
	 *
	 * @param ?string $key
	 * @return string
	 * @codeCoverageIgnore
	 */
	public function _do_sanitize_key(?string $key): string {
		$key = (string) $key;
		if (\function_exists('sanitize_key')) {
			$res = (string) \sanitize_key($key);
			if ($res !== '' || $key === '') {
				return $res;
			}
			// fall through to internal logic when sanitize_key returns empty unexpectedly
		}
		$key = \strtolower($key);
		// Replace any run of non [a-z0-9_\-] with a single underscore (preserve hyphens)
		$key = \preg_replace('/[^a-z0-9_\-]+/i', '_', $key) ?? '';
		// Trim underscores at edges (preserve leading/trailing hyphens if present)
		return \trim($key, '_');
	}

	/**
	 * Public wrapper for WordPress get_stylesheet_directory
	 *
	 * Availability-guarded: Yes
	 * Rationale: get_stylesheet_directory() may be unavailable in early contexts;
	 * empty string allows callers to detect and short-circuit filesystem ops.
	 *
	 * @return string
	 */
	public function _do_get_stylesheet_directory(): string {
		return \function_exists('get_stylesheet_directory') ? (string) \get_stylesheet_directory() : '';
	}

	/**
	 * Public wrapper for WordPress get_plugin_data
	 *
	 * Availability-guarded: Yes
	 * Rationale: get_plugin_data() lives in wp-admin includes and may be unavailable
	 * unless explicitly loaded. Returning an empty array avoids fatals in tests/CLI.
	 *
	 * @param string $plugin_file
	 * @param bool $markup
	 * @param bool $translate
	 * @return array
	 */
	public function _do_get_plugin_data(string $plugin_file, bool $markup = false, bool $translate = false): array {
		if (!\function_exists('get_plugin_data')) {
			return array();
		}
		/** @var array $data */
		$data = \get_plugin_data($plugin_file, $markup, $translate);
		return (array) $data;
	}

	/**
	 * Public wrapper for WordPress wp_get_theme
	 *
	 * Availability-guarded: Yes
	 * Rationale: Theme API may be unavailable without full WP bootstrap; return null
	 * to allow feature-detection in tests/CLI.
	 *
	 * @param ?string $stylesheet_dir Optional slug. Defaults to active theme.
	 * @param ?string $theme_root     Optional absolute theme root.
	 * @return object|null            Theme-like object or null if WP unavailable.
	 */
	public function _do_wp_get_theme(?string $stylesheet_dir = null, ?string $theme_root = ''): ?object {
		if (!\function_exists('wp_get_theme')) {
			return null;
		}
		return \wp_get_theme($stylesheet_dir, $theme_root);
	}

	/**
	 * Public wrapper for WordPress plugin_dir_url
	 *
	 * Availability-guarded: Yes
	 * Rationale: plugin_dir_url() may be undefined early; empty string is a safe sentinel
	 * that callers can check to avoid building invalid URLs.
	 *
	 * @param string $plugin_file
	 * @return string
	 */
	public function _do_plugin_dir_url(string $plugin_file): string {
		return \function_exists('plugin_dir_url') ? (string) \plugin_dir_url($plugin_file) : '';
	}

	/**
	 * Public wrapper for WordPress plugin_dir_path
	 *
	 * Availability-guarded: Yes
	 * Rationale: plugin_dir_path() may be undefined early; empty string lets callers
	 * short-circuit path-dependent operations safely
	 *
	 * @param string $plugin_file
	 * @return string
	 */
	public function _do_plugin_dir_path(string $plugin_file): string {
		return \function_exists('plugin_dir_path') ? (string) \plugin_dir_path($plugin_file) : '';
	}

	/**
	 * Public wrapper for WordPress plugin_basename with basename fallback
	 *
	 * Availability-guarded: Yes
	 * Rationale: plugin_basename() may be unavailable; basename() provides a reasonable
	 * approximation for identifiers/labels in tests/CLI.
	 *
	 * @param string $plugin_file
	 * @return string
	 */
	public function _do_plugin_basename(string $plugin_file): string {
		if (\function_exists('plugin_basename')) {
			return (string) \plugin_basename($plugin_file);
		}
		return \basename($plugin_file);
	}

	/**
	 * Public wrapper for WordPress get_stylesheet_directory_uri
	 *
	 * Availability-guarded: Yes
	 * Rationale: get_stylesheet_directory_uri() may be unavailable; empty string is a safe
	 * sentinel for front-end URL composition.
	 *
	 * @return string
	 */
	public function _do_get_stylesheet_directory_uri(): string {
		return \function_exists('get_stylesheet_directory_uri') ? (string) \get_stylesheet_directory_uri() : '';
	}
}
