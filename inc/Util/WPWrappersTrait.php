<?php
/**
 * WPWrappersTrait.php
 * A trait containing wrappers for common WordPress functions to allow for easier testing and potential future modifications.
 *
 * @package Ran\PluginLib\Util
 * @author  Ran Plugin Lib <bnjmnrsh@gmail.com>
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
 * These methods are prefixed with `_do_` to indicate they are internal implementation details
 * and should not be called directly by consuming code. They exist primarily for testability.
 *
 * @package Ran\PluginLib\Util
 *
 * @internal All _do_* methods in this trait are internal implementation details.
 * @codeCoverageIgnore
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
	 * Availability-guarded: No (requires WP loaded)
	 *
	 * @param string $hook The WordPress hook to add the action to.
	 * @param mixed $callback The callback function or method to be executed.
	 * @param int $priority Optional. The priority of the action. Default 10.
	 * @param int $accepted_args Optional. The number of arguments the callback accepts. Default 1.
	 * @internal
	 * @return void
	 * @see \Ran\PluginLib\HooksAccessory\HooksManagementTrait::register_action
	 * @see \Ran\PluginLib\HooksAccessory\HooksManager
	 */
	protected function _do_add_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): void {
		$handled = false;
		$via     = 'none';
		if (\defined('WP_MOCK') && \class_exists(\WP_Mock\Functions\Handler::class)) {
			try {
				\WP_Mock\Functions\Handler::handleFunction('add_action', array($hook, $callback, $priority, $accepted_args));
				$handled = true;
				$via     = 'wp_mock';
			} catch (\PHPUnit\Framework\ExpectationFailedException $e) {
				// In WP_Mock strict mode when no handler registered; continue to fallback.
				if (!\function_exists('add_action')) {
					throw $e;
				}
			}
		}

		if (!$handled && \function_exists('add_action')) {
			\add_action($hook, $callback, $priority, $accepted_args);
			$handled = true;
			$via     = 'native';
		}

		if (isset($this->logger) && $this->logger instanceof \Ran\PluginLib\Util\Logger) {
			$this->logger->debug('wp_wrappers.add_action', array(
			    'hook'          => $hook,
			    'priority'      => $priority,
			    'accepted_args' => $accepted_args,
			    'via'           => $via,
			));
		}
	}

	/**
	 * Wrapper for WordPress did_action() function.
	 *
	 * This wrapper makes the code more testable by allowing the did_action
	 * function to be mocked in tests.
	 * Availability-guarded: No (normalizes null->0 for WP_Mock in tests)
	 *
	 * @param string $hook_name The hook name to check.
	 * @internal
	 * @return int Number of times the hook has been executed.
	 */
	protected function _do_did_action(string $hook_name): int {
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
	 * Availability-guarded: No (requires WP loaded)
	 *
	 * @param string $hook The WordPress hook to add the filter to.
	 * @param mixed $callback The callback function or method to be executed.
	 * @param int $priority Optional. The priority of the filter. Default 10.
	 * @param int $accepted_args Optional. The number of arguments the callback accepts. Default 1.
	 * @internal
	 * @return void
	 * @see \Ran\PluginLib\HooksAccessory\HooksManagementTrait::register_filter
	 * @see \Ran\PluginLib\HooksAccessory\HooksManager
	 */
	protected function _do_add_filter(string $hook, $callback, int $priority = 10, int $accepted_args = 1): void {
		$handled = false;
		$via     = 'none';
		if (\defined('WP_MOCK') && \class_exists(\WP_Mock\Functions\Handler::class)) {
			try {
				\WP_Mock\Functions\Handler::handleFunction('add_filter', array($hook, $callback, $priority, $accepted_args));
				$handled = true;
				$via     = 'wp_mock';
			} catch (\PHPUnit\Framework\ExpectationFailedException $e) {
				if (!\function_exists('add_filter')) {
					throw $e;
				}
			}
		}

		if (!$handled && \function_exists('add_filter')) {
			\add_filter($hook, $callback, $priority, $accepted_args);
			$handled = true;
			$via     = 'native';
		}

		if (isset($this->logger) && $this->logger instanceof \Ran\PluginLib\Util\Logger) {
			$this->logger->debug('wp_wrappers.add_filter', array(
			    'hook'          => $hook,
			    'priority'      => $priority,
			    'accepted_args' => $accepted_args,
			    'via'           => $via,
			));
		}
	}

	/**
	 * public wrapper for WordPress remove_action function
	 *
	 * Note: If an action was registered via HooksManager, prefer removing it via
	 * HooksManager too to keep internal tracking in sync:
	 * `$this->_get_hooks_manager()->remove_hook('action', $hook_name, $callback, $priority)`.
	 * This wrapper remains valid for one-off direct removals.
	 *
	 * Availability-guarded: No
	 *
	 * @param string $hook_name The name of the action
	 * @param callable $callback The callback function
	 * @param int $priority The priority (default: 10)
	 * @internal
	 * @return bool True if successful, false otherwise
	 */
	protected function _do_remove_action(string $hook_name, callable $callback, int $priority = 10): bool {
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
	 * Availability-guarded: No
	 *
	 * @param string $hook_name The name of the filter
	 * @param callable $callback The callback function
	 * @param int $priority The priority (default: 10)
	 * @internal
	 * @return bool True if successful, false otherwise
	 */
	protected function _do_remove_filter(string $hook_name, callable $callback, int $priority = 10): bool {
		return \remove_filter($hook_name, $callback, $priority);
	}

	/**
	 * public wrapper for WordPress do_action function
	 *
	 * Invocation guidance: Registration should go through HooksManager, but
	 * dispatching an action is unaffected; calling WordPress `do_action` directly
	 * via this wrapper is appropriate.
	 *
	 * Availability-guarded: No
	 *
	 * @param string $hook_name The name of the action
	 * @param mixed ...$args Arguments to pass to the callbacks
	 * @internal
	 * @return void
	 */
	protected function _do_execute_action(string $hook_name, ...$args): void {
		\do_action($hook_name, ...$args);
	}

	/**
	 * public wrapper for WordPress apply_filters function
	 *
	 * Invocation guidance: Registration should go through HooksManager, but
	 * applying filters to a value can use this wrapper (or
	 * `$this->_get_hooks_manager()->apply_filters(...)`, which forwards here).
	 *
	 * Availability-guarded: No
	 *
	 * @param string $hook_name The name of the filter
	 * @param mixed $value The value to filter
	 * @param mixed ...$args Additional arguments to pass to the callbacks
	 * @internal
	 * @return mixed The filtered value
	 */
	protected function _do_apply_filter(string $hook_name, $value, ...$args) {
		// Mirror WP behavior: when no filter is attached, the input value is returned unchanged.
		// Under WP_Mock, apply_filters may return null if not explicitly mocked; normalize to $value.
		if (\function_exists('apply_filters')) {
			$res = \apply_filters($hook_name, $value, ...$args);
			if (isset($this->logger) && $this->logger instanceof \Ran\PluginLib\Util\Logger) {
				$this->logger->debug('wp_wrappers.apply_filters', array(
				    'hook'   => $hook_name,
				    'value'  => $value,
				    'args'   => $args,
				    'result' => $res,
				));
			}
			return $res !== null ? $res : $value;
		}
		return $value;
	}

	/**
	 * Public wrapper for WordPress get_option function
	 * Availability-guarded: No
	 *
	 * @param  string $option
	 * @param  mixed  $default
	 *
	 * @internal
	 * @return mixed
	 */
	protected function _do_get_option(string $option, mixed $default = false): mixed {
		return \get_option($option, $default);
	}

	/**
	 * Public wrapper for WordPress set_option function
	 * Availability-guarded: Yes, with fallback
	 *
	 * @param  string $option
	 * @param  mixed  $value
	 * @param  mixed  $autoload
	 *
	 * @internal
	 * @return bool
	 */
	protected function _do_update_option(string $option, mixed $value, mixed $autoload = null): bool {
		// Availability guard: in tests/CLI WP may not be loaded. Mirror behavior of other
		// wrappers by avoiding fatals and returning a strict bool.
		if (!\function_exists('update_option')) {
			return true;
		}
		// Preserve legacy two-argument behavior when $autoload is null so callers/tests
		// expecting the 2-arg signature still match. When explicitly provided, forward
		// the third parameter.
		if ($autoload === null) {
			return (bool) \update_option($option, $value);
		}
		return (bool) \update_option($option, $value, $autoload);
	}

	/**
	 * Public wrapper for WordPress add_option function
	 * Availability-guarded: Yes, with default fallback response of: true
	 *
	 * @param string $option
	 * @param mixed $value
	 * @param string $deprecated
	 * @param bool|null $autoload Whether to autoload; null defers to WordPress heuristics (WP 6.6+).
	 * @internal
	 * @return bool
	 */
	protected function _do_add_option(string $option, mixed $value = '', string $deprecated = '', mixed $autoload = null): bool {
		// Pass through to WP when available. In 6.6+, null triggers wp_determine_option_autoload_value() heuristics.
		// Some test shims may return null; always normalize to strict bool.
		if (\function_exists('add_option')) {
			return (bool) \add_option($option, $value, $deprecated, $autoload);
		}
		// In non-WP/unit contexts, avoid fatals and assume success to preserve historical behavior.
		return true;
	}

	/**
	 * Public wrapper for WordPress delete_option function
	 * Availability-guarded: No
	 *
	 * @param string $option
	 * @internal
	 * @return bool
	 */
	protected function _do_delete_option(string $option): bool {
		// Some test shims may return null; always normalize to strict bool.
		return (bool) \delete_option($option);
	}

	/**
	 * Public wrapper for WordPress get_site_option function (Network scope)
	 * Availability-guarded: No
	 *
	 * @param  string $option
	 * @param  mixed  $default
	 * @internal
	 * @return mixed
	 */
	protected function _do_get_site_option(string $option, mixed $default = false): mixed {
		return \get_site_option($option, $default);
	}

	/**
	 * Public wrapper for WordPress update_site_option function (Network scope)
	 * Availability-guarded: No
	 *
	 *
	 * @param  string $option
	 * @param  mixed  $value
	 * @internal
	 * @return bool
	 */
	protected function _do_update_site_option(string $option, mixed $value): bool {
		return \update_site_option($option, $value);
	}

	/**
	 * Public wrapper for WordPress add_site_option function (Network scope)
	 * Some test shims may return null; always normalize to strict bool.
	 * Availability-guarded: Yes
	 *
	 * @param  string $option
	 * @param  mixed  $value
	 * @internal
	 * @return bool
	 */
	protected function _do_add_site_option(string $option, mixed $value = ''): bool {
		if (\function_exists('add_site_option')) {
			return (bool) \add_site_option($option, $value);
		}
		// In non-WP/unit contexts, avoid fatals and assume success to preserve historical behavior.
		return true;
	}

	/**
	 * Public wrapper for WordPress delete_site_option function (Network scope)
	 * Some test shims may return null; always normalize to strict bool.
	 * Availability-guarded: Yes
	 *
	 * @param  string $option
	 * @internal
	 * @return bool
	 */
	protected function _do_delete_site_option(string $option): bool {
		return (bool) \delete_site_option($option);
	}

	/**
	 * Public wrapper for WordPress get_blog_option function (Blog scope)
	 * Availability-guarded: No
	 *
	 * @param  int    $blog_id
	 * @param  string $option
	 * @param  mixed  $default
	 * @internal
	 * @return mixed
	 */
	protected function _do_get_blog_option(int $blog_id, string $option, mixed $default = false): mixed {
		return \get_blog_option($blog_id, $option, $default);
	}

	/**
	 * Public wrapper for WordPress update_blog_option function (Blog scope)
	 * Availability-guarded: No
	 *
	 * @param  int    $blog_id
	 * @param  string $option
	 * @param  mixed  $value
	 * @internal
	 * @return bool
	 */
	protected function _do_update_blog_option(int $blog_id, string $option, mixed $value): bool {
		return \update_blog_option($blog_id, $option, $value);
	}

	/**
	 * Public wrapper for WordPress add_blog_option function (Blog scope)
	 *
	 * Some test shims may return null; always normalize to strict bool.
	 * Availability-guarded: No
	 *
	 * @param  int    $blog_id
	 * @param  string $option
	 * @param  mixed  $value
	 * @internal
	 * @return bool
	 */
	protected function _do_add_blog_option(int $blog_id, string $option, mixed $value = ''): bool {
		return (bool) \add_blog_option($blog_id, $option, $value);
	}

	/**
	 * Public wrapper for WordPress delete_blog_option function (Blog scope)
	 * Some test shims may return null; always normalize to strict bool.
	 * Availability-guarded: Yes
	 *
	 * @param  int    $blog_id
	 * @param  string $option
	 * @internal
	 * @return bool
	 */
	protected function _do_delete_blog_option(int $blog_id, string $option): bool {
		return (bool) \delete_blog_option($blog_id, $option);
	}

	/**
	 * Public wrapper for WordPress get_current_user_id()
	 * Returns current user ID; when function missing, defaults to 0.
	 * Availability-guarded: Yes
	 *
	 * Rationale: In early boot or tests, get_current_user_id() may be unavailable.
	 * Returning 0 provides a neutral default.
	 *
	 * @internal
	 * @return int
	 */
	protected function _do_get_current_user_id(): int {
		if (\function_exists('get_current_user_id')) {
			return (int) \get_current_user_id();
		}
		return 0;
	}

	/**
	 * Public wrapper for WordPress get_user_option function (User scope)
	 * Availability-guarded: No (requires WP loaded)
	 *
	 * Note: WP signature is get_user_option($option, $user, $deprecated=''). We flip
	 * the first two arguments for consistency with other wrappers.
	 * Direct-forward: Yes (requires WP loaded; parameter order flipped for convenience)
	 *
	 * @param  int    $user_id
	 * @param  string $option
	 * @param  mixed  $deprecated
	 * @internal
	 * @return mixed
	 */
	protected function _do_get_user_option(int $user_id, string $option, mixed $deprecated = ''): mixed {
		return \get_user_option($option, $user_id, $deprecated);
	}

	/**
	 * Public wrapper for WordPress update_user_option function (User scope)
	 * Availability-guarded: No (requires WP loaded)
	 *
	 * @param  int    $user_id
	 * @param  string $option
	 * @param  mixed  $value
	 * @param  bool   $global
	 * @internal
	 * @return bool
	 */
	protected function _do_update_user_option(int $user_id, string $option, mixed $value, bool $global = false): bool {
		return (bool) \update_user_option($user_id, $option, $value, $global);
	}

	/**
	 * Public wrapper for WordPress delete_user_option function (User scope)
	 * Availability-guarded: No (requires WP loaded)
	 *
	 * @param  int    $user_id
	 * @param  string $option_name
	 * @param  bool   $is_global
	 * @internal
	 * @return bool
	 */
	protected function _do_delete_user_option(int $user_id, string $option_name, bool $is_global = false): bool {
		return (bool) \delete_user_option($user_id, $option_name, $is_global);
	}

	/**
	 * Public wrapper for WordPress get_user_meta function (User meta)
	 * Availability-guarded: No (requires WP loaded)
	 *
	 * @param int    $user_id User ID
	 * @param string $key     Meta key
	 * @param bool   $single  Whether to return a single value. Default true.
	 * @internal
	 * @return mixed          Meta value(s)
	 */
	protected function _do_get_user_meta(int $user_id, string $key, bool $single = true): mixed {
		return \get_user_meta($user_id, $key, $single);
	}

	/**
	 * Public wrapper for WordPress update_user_meta function (User meta)
	 * Avalibility-garded: No (requires WP loaded)
	 *
	 * @param int    $user_id User ID
	 * @param string $key     Meta key
	 * @param mixed  $value   Meta value
	 * @param string $prev_value Previous meta value
	 * @internal
	 * @return int|bool           True on success
	 */
	protected function _do_update_user_meta(int $user_id, string $key, mixed $value, string $prev_value = ''): int|bool {
		return \update_user_meta($user_id, $key, $value, $prev_value);
	}

	/**
	 * Public wrapper for WordPress add_user_meta function (User meta)
	 * Availability-guarded: No (requires WP loaded)
	 *
	 * @param int    $user_id User ID
	 * @param string $key     Meta key
	 * @param mixed  $value   Meta value
	 * @param bool   $unique  Whether the meta key should be unique. Default false.
	 * @internal
	 * @return int|false           Meta ID on success, false on failure.
	 */
	protected function _do_add_user_meta(int $user_id, string $key, mixed $value, bool $unique = false): int|false {
		return \add_user_meta($user_id, $key, $value, $unique);
	}

	/**
	 * Public wrapper for WordPress delete_user_meta function (User meta)
	 *
	 * Some test shims may return null; always normalize to strict bool.
	 * Availability-guarded: No (requires WP loaded)
	 *
	 * @param int    $user_id User ID
	 * @param string $key     Meta key
	 * @internal
	 * @return bool           True on success
	 */
	protected function _do_delete_user_meta(int $user_id, string $key): bool {
		return (bool) \delete_user_meta($user_id, $key);
	}

	/**
	 * Public wrapper for WordPress wp_load_alloptions() with availability guard
	 * Returns autoloaded options map when available; null when WP function is unavailable.
	 * Availability-guarded: Yes
	 *
	 * Rationale: In CLI/tests without full WP bootstrap, wp_load_alloptions() may be undefined.
	 * Returning null lets callers feature-detect autoload cache without fatals.
	 *
	 * @param bool $force_cache Optional. Whether to force an update of the local cache from the persistent cache. Default false.
	 * @internal
	 * @return array|null
	 */
	protected function _do_wp_load_alloptions($force_cache = false): ?array {
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
	 * @internal
	 * @return int
	 */
	protected function _do_get_current_blog_id(): int {
		// In WP_Mock tests, always try to call the function (it may be mocked)
		if (\defined('WP_MOCK')) {
			return (int) \get_current_blog_id();
		}
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
	 * @internal
	 * @return bool
	 */
	protected function _do_current_user_can(string $capability, ...$args): bool {
		if (\function_exists('current_user_can')) {
			return (bool) \current_user_can($capability, ...$args);
		}

		// When capability API is unavailable, default to denial to avoid
		// unintentionally granting access in restricted contexts (including tests).
		return false;
	}

	/**
	 * Public wrapper for WordPress sanitize_key with fallback when WP not loaded
	 *
	 * Availability-guarded: Yes
	 * Rationale: Provide stable key normalization when sanitize_key() is missing
	 * (e.g., early CLI/tests). Also guards against unexpected empty returns by
	 * falling back to internal normalization mirroring WP behavior.
	 *
	 * @param string $key
	 * @internal
	 * @return string
	 */
	protected function _do_sanitize_key(string $key): string {
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
	 * Public wrapper for WordPress sanitize_text_field
	 *
	 * Availability-guarded: Yes
	 * Rationale: sanitize_text_field() may be unavailable in early contexts;
	 * empty string allows callers to detect and short-circuit filesystem ops.
	 *
	 * @internal
	 * @return string
	 */
	protected function _do_sanitize_text_field(): string {
		return \function_exists('sanitize_text_field') ? (string) \sanitize_text_field() : '';
	}

	/**
	 * Public wrapper for WordPress get_stylesheet_directory
	 *
	 * Availability-guarded: Yes
	 * Rationale: get_stylesheet_directory() may be unavailable in early contexts;
	 * empty string allows callers to detect and short-circuit filesystem ops.
	 *
	 * @internal
	 * @return string
	 */
	protected function _do_get_stylesheet_directory(): string {
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
	 * @internal
	 * @return array
	 */
	protected function _do_get_plugin_data(string $plugin_file, bool $markup = false, bool $translate = false): array {
		if (!\function_exists('get_plugin_data')) {
			return array();
		}
		/** @var array $data */
		$data = \get_plugin_data($plugin_file, $markup, $translate);
		return (array) $data;
	}

	/**
	 * Public wrapper for WordPress wp_register_script
	 *
	 * Availability-guarded: Yes
	 * Rationale: wp_register_script() may be unavailable in early contexts;
	 *
	 * @param string $handel
	 * @param string|false $src
	 * @param string|array $deps
	 * @param string|bool|null $ver
	 * @param array|bool $args
	 * @internal
	 * @return bool
	 */
	protected function _do_wp_register_script(string $handel, string|false $src, string|array $deps = array(), string|bool|null $ver = false, array|bool $args = array()): bool {
		if (!\function_exists('wp_register_script')) {
			return null;
		}
		return \wp_register_script($handel, $src, $deps, $ver, $args);
	}

	/**
	 * Public wrapper for WordPress wp_localize_script
	 *
	 * Availability-guarded: Yes
	 * Rationale: wp_localize_script() may be unavailable in early contexts;
	 *
	 * @param string $handle
	 * @param string $object_name
	 * @param array $l10n
	 * @internal
	 * @return bool
	 */
	protected function _do_wp_localize_script(string $handle, string $object_name, array $l10n ):bool {
		if (!\function_exists('wp_localize_script')) {
			return null;
		}
		return \wp_localize_script($handle, $object_name, $l10n);
	}

	/**
	 * Public wrapper for WordPress wp_enqueue_media
	 *
	 * Availability-guarded: Yes
	 * Rationale: wp_enqueue_media() may be unavailable in early contexts;
	 *
	 * @param array $args
	 * @internal
	 * @return void
	 */
	protected function _do_wp_enqueue_media(array $args = array()): void {
		if (\function_exists('wp_enqueue_media')) {
			\wp_enqueue_media($args);
		}
	}

	/**
	 * Public wrapper for WordPress wp_enqueue_script
	 *
	 * Availability-guarded: Yes
	 * Rationale: wp_enqueue_script() may be unavailable in early contexts;
	 *
	 * @param string $handle
	 * @param string $src
	 * @param string|array $deps
	 * @param string|bool|null $ver
	 * @param array|bool $args
	 * @internal
	 * @return void
	 */
	protected function _do_wp_enqueue_script(string $handle, string $src = '', string|array $deps = array(), string|bool|null $ver = false, array|bool $args = array()): void {
		if (\function_exists('wp_enqueue_media')) {
			\wp_enqueue_media($handle, $src, $deps, $ver, $args);
		}
	}

	/**
	 * Public wrapper for WordPress wp_enqueue_script
	 *
	 * Availability-guarded: Yes
	 * Rationale: wp_enqueue_script() may be unavailable in early contexts;
	 *
	 *
	 */
	protected function _do_wp_register_style(): void {
		if (\function_exists('wp_enqueue_media')) {
			\wp_enqueue_media();
		}
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
	 * @internal
	 * @return object|null            Theme-like object or null if WP unavailable.
	 */
	protected function _do_wp_get_theme(?string $stylesheet_dir = null, ?string $theme_root = ''): ?object {
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
	 * @internal
	 * @return string
	 */
	protected function _do_plugin_dir_url(string $plugin_file): string {
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
	 * @internal
	 * @return string
	 */
	protected function _do_plugin_dir_path(string $plugin_file): string {
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
	 * @internal
	 * @return string
	 */
	protected function _do_plugin_basename(string $plugin_file): string {
		if (\function_exists('plugin_basename')) {
			return (string) \plugin_basename($plugin_file);
		}
		return \basename($plugin_file);
	}

	/**
	 * Public wrapper for WordPress plugins_url()
	 *
	 * Availability-guarded: Yes
	 * Rationale: plugins_url() may be unavailable; empty string is a safe sentinel
	 * for front-end URL composition.
	 *
	 * @param string $path   Optional. Extra path appended to the end of the URL, including
	 *                       the relative directory if $plugin is supplied. Default empty.
	 * @param string $plugin Optional. A full path to a file inside a plugin or mu-plugin.
	 *                       The URL will be relative to its directory. Default empty.
	 *                       Typically this is done by passing `__FILE__` as the argument.
	 * @internal
	 * @return string
	 */
	protected function _do_plugins_url(string $path = '', string $plugin = ''): string {
		return \function_exists('plugins_url') ? (string) \plugins_url($path, $plugin) : '';
	}

	/**
	 * Public wrapper for WordPress get_stylesheet_directory_uri
	 *
	 * Availability-guarded: Yes
	 * Rationale: get_stylesheet_directory_uri() may be unavailable; empty string is a safe
	 * sentinel for front-end URL composition.
	 *
	 * @internal
	 * @return string
	 */
	protected function _do_get_stylesheet_directory_uri(): string {
		return \function_exists('get_stylesheet_directory_uri') ? (string) \get_stylesheet_directory_uri() : '';
	}

	/**
	 * Public wrapper for WordPress is_network_admin()
	 *
	 * Availability-guarded: Yes
	 * @internal
	 * @return bool
	 */
	protected function _do_is_network_admin(): bool {
		// In WP_Mock tests, always try to call the function (it may be mocked)
		if (\defined('WP_MOCK')) {
			return (bool) \is_network_admin();
		}
		return \function_exists('is_network_admin') ? (bool) \is_network_admin() : false;
	}

	/**
	 * Public wrapper for WordPress register_setting()
	 * Availability-guarded: Yes
	 *
	 * @param string $option_group
	 * @param string $option_name
	 * @param array  $args
	 * @internal
	 * @return void
	 */
	protected function _do_register_setting(string $option_group, string $option_name, array $args = array()): void {
		if (\function_exists('register_setting')) {
			\register_setting($option_group, $option_name, $args);
		}
	}

	/**
	 * Wrapper for WordPress add_menu_page().
	 * Availability-guarded: Yes
	 */
	protected function _do_add_menu_page(string $heading, string $menu_title, string $capability, string $menu_slug, callable $callback, ?string $icon_url = null, ?int $position = null): void {
		if (\function_exists('add_menu_page')) {
			\add_menu_page($heading, $menu_title, $capability, $menu_slug, $callback, $icon_url, $position ?? null);
		}
	}

	/**
	 * Wrapper for WordPress add_submenu_page().
	 * Availability-guarded: Yes
	 */
	protected function _do_add_submenu_page(string $parent_slug, string $heading, string $menu_title, string $capability, string $menu_slug, callable $callback): void {
		if (\function_exists('add_submenu_page')) {
			\add_submenu_page($parent_slug, $heading, $menu_title, $capability, $menu_slug, $callback);
		}
	}

	/**
	 * Public wrapper for WordPress add_options_page()
	 *
	 * Availability-guarded: Yes (requires WP loaded)
	 * @param string   $heading
	 * @param string   $menu_title
	 * @param string   $capability
	 * @param string   $menu_slug
	 * @param callable $callback
	 * @param ?int     $position
	 * @internal
	 * @return void
	 */
	protected function _do_add_options_page(string $heading, string $menu_title, string $capability, string $menu_slug, callable $callback, ?int $position = null): void {
		if (\function_exists('add_options_page')) {
			// add_options_page signature ignores position; using parent menu API normally controls ordering.
			\add_options_page($heading, $menu_title, $capability, $menu_slug, $callback);
		}
	}

	/**
	 * Public wrapper for WordPress add_settings_section()
	 * Availability-guarded: Yes
	 * @param string   $id
	 * @param string   $title
	 * @param callable $callback
	 * @param string   $page
	 * @internal
	 * @return void
	 */
	protected function _do_add_settings_section(string $id, string $title, callable $callback, string $page): void {
		if (\function_exists('add_settings_section')) {
			\add_settings_section($id, $title, $callback, $page);
		}
	}

	/**
	 * Public wrapper for WordPress add_settings_field()
	 * Availability-guarded: Yes
	 *
	 * @param string   $id
	 * @param string   $title
	 * @param callable $callback
	 * @param string   $page
	 * @param string   $section
	 * @internal
	 * @return void
	 */
	protected function _do_add_settings_field(string $id, string $title, callable $callback, string $page, string $section): void {
		if (\function_exists('add_settings_field')) {
			\add_settings_field($id, $title, $callback, $page, $section);
		}
	}

	/**
	 * Public wrapper for WordPress settings_fields()
	 * Availability-guarded: Yes
	 *
	 * @param string $option_group
	 * @internal
	 * @return void
	 */
	protected function _do_settings_fields(string $option_group): void {
		if (\function_exists('settings_fields')) {
			\settings_fields($option_group);
		}
	}

	/**
	 * Public wrapper for WordPress do_settings_sections()
	 * Availability-guarded: Yes
	 *
	 * @param string $page
	 * @internal
	 * @return void
	 */
	protected function _do_settings_sections(string $page): void {
		if (\function_exists('do_settings_sections')) {
			\do_settings_sections($page);
		}
	}

	/**
	 * Public wrapper for WordPress submit_button()
	 * Availability-guarded: Yes
	 *
	 * @param string $text
	 * @internal
	 * @return void
	 */
	protected function _do_submit_button(string $text = 'Save Changes'): void {
		if (\function_exists('submit_button')) {
			\submit_button($text);
		}
	}

	/**
	 * Public wrapper for WordPress get_allowed_mime_types()
	 * Availability-guarded: Yes
	 *
	 * @internal
	 * @return array
	 */
	protected function _do_get_allowed_mime_types(): array {
		if (\function_exists('wp_get_allowed_mime_types')) {
			/** @var array $mime_types */
			$mime_types = \get_allowed_mime_types();
			return (array) $mime_types;
		}
		return array();
	}

	/**
	 * Public wrapper for WordPress wp_nonce_field()
	 * Availability-guarded: Yes
	 *
	 * @param int|string $action
	 * @param string $name
	 * @param bool $referer
	 * @param bool $display
	 * @internal
	 * @return string
	 */
	protected function _do_wp_nonce_field(int|string $action, string $name = '_wpnonce', bool $referer = true, bool $display = true): string {
		if (\function_exists('wp_nonce_field')) {
			return (string) \wp_nonce_field($action, $name, $referer, $display);
		}
		return '';
	}

	/**
	 * Public wrapper for WordPress esc_html()
	 * Availability-guarded: Yes
	 *
	 * Rationale: esc_html() may be unavailable in early contexts; fallback to
	 * htmlspecialchars() provides basic HTML escaping functionality for testing.
	 *
	 * @param string $text Text to escape
	 * @internal
	 * @return string Escaped text
	 */
	protected function _do_esc_html(string $text): string {
		if (\function_exists('esc_html')) {
			return (string) \esc_html($text);
		}
		return \htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
	}

	/**
	 * Public wrapper for WordPress esc_attr()
	 * Availability-guarded: Yes
	 *
	 * Rationale: esc_attr() may be unavailable in early contexts; fallback to
	 * htmlspecialchars() provides basic attribute escaping functionality for testing.
	 *
	 * @param string $text Text to escape for attribute
	 * @internal
	 * @return string Escaped text
	 */
	protected function _do_esc_attr(string $text): string {
		if (\function_exists('esc_attr')) {
			return (string) \esc_attr($text);
		}
		return \htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
	}

	/**
	 * Public wrapper for WordPress esc_url()
	 * Availability-guarded: Yes
	 *
	 * Rationale: esc_url() may be unavailable in early contexts; fallback to
	 * filter_var() provides basic URL validation and escaping for testing.
	 *
	 * @param string $url URL to escape
	 * @param array $protocols Optional. Array of allowed protocols
	 * @param string $_context Optional. Context for escaping (unused in fallback)
	 * @internal
	 * @return string Escaped URL
	 */
	protected function _do_esc_url(string $url, array $protocols = array(), string $_context = 'display'): string {
		if (\function_exists('esc_url')) {
			return (string) \esc_url($url, $protocols, $_context);
		}
		// Basic fallback validation for testing
		$filtered = \filter_var($url, FILTER_VALIDATE_URL);
		return $filtered !== false ? $filtered : '';
	}

	/**
	 * Public wrapper for WordPress set_transient()
	 * Availability-guarded: Yes
	 * @see https://developer.wordpress.org/reference/functions/set_transient/
	 *
	 * @param string $transient Transient name - must be 172 characters or less
	 * @param mixed $value Value to store
	 * @param int $expiration Optional. Expiration time in seconds
	 * @internal
	 * @return bool True on success, false on failure
	 */
	protected function _do_set_transient(string $transient, mixed $value, int $expiration = 0): bool {
		if (\function_exists('set_transient')) {
			return (bool) \set_transient($transient, $value, $expiration);
		}
		return false;
	}

	/**
	 * Public wrapper for WordPress get_transient()
	 * Availability-guarded: Yes
	 * @see https://developer.wordpress.org/reference/functions/get_transient/
	 *
	 * @param string $transient Transient name
	 * @internal
	 * @return mixed Value of transient, or false if not set or <expired></expired>
	 */
	protected function _do_get_transient(string $transient): mixed {
		if (\function_exists('get_transient')) {
			return \get_transient($transient);
		}
		return false;
	}

	/**
	 * Public wrapper for WordPress delete_transient()
	 * Availability-guarded: Yes
	 * @see https://developer.wordpress.org/reference/functions/delete_transient/
	 *
	 * @param string $transient Transient name
	 * @internal
	 * @return bool True on success, false on failure
	 */
	protected function _do_delete_transient(string $transient): bool {
		if (\function_exists('delete_transient')) {
			return (bool) \delete_transient($transient);
		}
		return false;
	}

	/**
	 * Public wrapper for WordPress wp_get_environment_type()
	 * Availability-guarded: Yes
	 * @see https://developer.wordpress.org/reference/functions/wp_get_environment_type/
	 *
	 * @internal
	 * @return string Environment type (development, staging, production)
	 */
	protected function _do_wp_get_environment_type(): string {
		if (\function_exists('wp_get_environment_type')) {
			return (string) \wp_get_environment_type();
		}
		return 'production'; // Default fallback
	}

	/**
	 * Public wrapper for WordPress __() function
	 * Availability-guarded: Yes
	 *
	 * @param string $text Text to translate
	 * @param string $domain Text domain
	 * @internal
	 * @return string Translated text
	 */
	protected function _do___(string $text, string $domain = 'default'): string {
		if (\function_exists('__')) {
			return (string) \__($text, $domain);
		}
		return $text;
	}

	/**
	 * Public wrapper for WordPress _e() function
	 * Availability-guarded: Yes
	 *
	 * @param string $text Text to translate and echo
	 * @param string $domain Text domain
	 * @internal
	 * @return void
	 */
	protected function _do_e(string $text, string $domain = 'default'): void {
		if (\function_exists('_e')) {
			\_e($text, $domain);
		} else {
			echo $text;
		}
	}

	/**
	 * Public wrapper for WordPress _n() function
	 * Availability-guarded: Yes
	 *
	 * @param string $single Singular text
	 * @param string $plural Plural text
	 * @param int $number Number to determine singular/plural
	 * @param string $domain Text domain
	 * @internal
	 * @return string Translated text
	 */
	protected function _do_n(string $single, string $plural, int $number, string $domain = 'default'): string {
		$namespaced = __NAMESPACE__ . '\\_n';
		if (\function_exists($namespaced)) {
			return (string) $namespaced($single, $plural, $number, $domain);
		}
		if (\function_exists('_n')) {
			return (string) \_n($single, $plural, $number, $domain);
		}
		return $number === 1 ? $single : $plural;
	}

	/**
	 * Public wrapper for WordPress _nx() function
	 * Availability-guarded: Yes
	 *
	 * @param string $single Singular text
	 * @param string $plural Plural text
	 * @param int $number Number to determine singular/plural
	 * @param string $context Context for translation
	 * @param string $domain Text domain
	 * @internal
	 * @return string Translated text
	 */
	protected function _do_nx(string $single, string $plural, int $number, string $context, string $domain = 'default'): string {
		$namespaced = __NAMESPACE__ . '\\_nx';
		if (\function_exists($namespaced)) {
			return (string) $namespaced($single, $plural, $number, $context, $domain);
		}
		if (\function_exists('_nx')) {
			return (string) \_nx($single, $plural, $number, $context, $domain);
		}
		return $number === 1 ? $single : $plural;
	}

	/**
	 * Public wrapper for WordPress esc_attr_e() function
	 * Availability-guarded: Yes
	 *
	 * @param string $text Text to translate, escape for attributes, and echo
	 * @param string $domain Text domain
	 * @internal
	 * @return void
	 */
	protected function _do_esc_attr_e(string $text, string $domain = 'default'): void {
		if (\function_exists('esc_attr_e')) {
			\esc_attr_e($text, $domain);
		} else {
			echo \htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
		}
	}

	/**
	 * Public wrapper for WordPress esc_html__() function
	 * Availability-guarded: Yes
	 *
	 * @param string $text Text to translate and escape
	 * @param string $domain Text domain
	 * @internal
	 * @return string Escaped and translated text
	 */
	protected function _do_esc_html__(string $text, string $domain = 'default'): string {
		if (\function_exists('esc_html__')) {
			return (string) \esc_html__($text, $domain);
		}
		return \htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
	}


	/**
	 * Public wrapper for WordPress esc_js()
	 * Availability-guarded: Yes
	 *
	 * Rationale: esc_js() may be unavailable in early contexts; fallback provides
	 * basic JavaScript string escaping for testing purposes.
	 *
	 * @param string $text Text to escape for JavaScript
	 * @internal
	 * @return string Escaped text
	 */
	protected function _do_esc_js(string $text): string {
		if (\function_exists('esc_js')) {
			return (string) \esc_js($text);
		}
		// Basic fallback for testing - escape for JavaScript string context
		return \str_replace(array('\\', "'", '"', "\n", "\r", "\t"), array('\\\\', "\\'", '\\"', '\\n', '\\r', '\\t'), $text);
	}

	/**
	 * Public wrapper for WordPress esc_textarea()
	 * Availability-guarded: Yes, with fallback to htmlspecialchars
	 *
	 * @param string $text Text to escape for textarea
	 * @internal
	 * @return string Escaped text
	 */
	protected function _do_esc_textarea(string $text): string {
		if (\function_exists('esc_textarea')) {
			return (string) \esc_textarea($text);
		}
		return \htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
	}

	/**
	 * Public wrapper for WordPress __() translation function
	 * Availability-guarded: Yes
	 *
	 * @param string $text Text to translate
	 * @param string $domain Text domain for translation
	 * @internal
	 * @return string Translated text
	 */
	protected function _do__(string $text, string $domain = 'default'): string {
		if (\function_exists('__')) {
			return (string) \__($text, $domain);
		}
		return $text;
	}

	/**
	 * Public wrapper for WordPress _x() contextual translation function
	 * Availability-guarded: Yes
	 *
	 * @param string $text Text to translate
	 * @param string $context Context for translation
	 * @param string $domain Text domain for translation
	 * @internal
	 * @return string Translated text
	 */
	protected function _do_x(string $text, string $context, string $domain = 'default'): string {
		if (\function_exists('_x')) {
			return (string) \_x($text, $context, $domain);
		}
		return $text;
	}

	/**
	 * Public wrapper for WordPress esc_html__() - escape and translate
	 * Availability-guarded: Yes
	 *
	 * @param string $text Text to translate and escape
	 * @param string $domain Text domain for translation
	 * @internal
	 * @return string Translated and escaped text
	 */
	protected function _do_esc_html___(string $text, string $domain = 'default'): string {
		if (\function_exists('esc_html__')) {
			return (string) \esc_html__($text, $domain);
		}
		return $text;
	}

	/**
	 * Public wrapper for WordPress esc_attr__() - escape for attributes and translate
	 * Availability-guarded: Yes
	 *
	 * @param string $text Text to translate and escape for attributes
	 * @param string $domain Text domain for translation
	 * @internal
	 * @return string Translated and escaped text
	 */
	protected function _do_esc_attr__(string $text, string $domain = 'default'): string {
		if (\function_exists('esc_attr__')) {
			return (string) \esc_attr__($text, $domain);
		}
		return $text;
	}

	/**
	 * Public wrapper for WordPress esc_html_x() - escape and translate with context
	 * Availability-guarded: Yes
	 *
	 * @param string $text Text to translate and escape
	 * @param string $context Context for translation
	 * @param string $domain Text domain for translation
	 * @internal
	 * @return string Translated and escaped text
	 */
	protected function _do_esc_html_x(string $text, string $context, string $domain = 'default'): string {
		if (\function_exists('esc_html_x')) {
			return (string) \esc_html_x($text, $context, $domain);
		}
		return $text;
	}

	/**
	 * Public wrapper for WordPress esc_html_e() - Displays translated text that has been escaped for safe use in HTML output.
	 * Availability-guarded: Yes
	 *
	 * @param string $text Text to translate and escape
	 * @param string $context Context for translation
	 * @param string $domain Text domain for translation
	 * @internal
	 * @return string Translated and escaped text
	 */
	protected function _do_esc_html_e(string $text, string $domain = 'default'): string {
		if (\function_exists('esc_html_e')) {
			return (string) \esc_html_e($text, $domain);
		}
		return $text;
	}

	/**
	 * Public wrapper for WordPress esc_attr_x() - escape for attributes and translate with context
	 * Availability-guarded: Yes
	 *
	 * @param string $text Text to translate and escape for attributes
	 * @param string $context Context for translation
	 * @param string $domain Text domain for translation
	 * @internal
	 * @return string Translated and escaped text
	 */
	protected function _do_esc_attr_x(string $text, string $context, string $domain = 'default'): string {
		if (\function_exists('esc_attr_x')) {
			return (string) \esc_attr_x($text, $context, $domain);
		}
		return $text;
	}

	/**
	 * Public wrapper for WordPress wp_enqueue_style()
	 * Availability-guarded: Yes
	 *
	 * Rationale: wp_enqueue_style() may be unavailable in early contexts or tests;
	 * graceful degradation allows code to continue without fatals.
	 *
	 * @param string      $handle Name of the stylesheet. Should be unique.
	 * @param string      $src    Full URL of the stylesheet, or path of the stylesheet relative to the WordPress root directory.
	 * @param array       $deps   Optional. An array of registered stylesheet handles this stylesheet depends on. Default empty array.
	 * @param string|bool $ver    Optional. String specifying stylesheet version number, if it has one, which is added to the URL as a query string for cache busting purposes. If version is set to false, a version number is automatically added equal to current installed WordPress version. If set to null, no version is added.
	 * @param string      $media  Optional. The media for which this stylesheet has been defined. Accepts media types like 'all', 'print' and 'screen', or media queries like '(orientation: portrait)' and '(max-width: 640px)'. Default 'all'.
	 * @internal
	 * @return void
	 */
	protected function _do_wp_enqueue_style(string $handle, string $src = '', array $deps = array(), string|bool|null $ver = false, string $media = 'all'): void {
		if (\function_exists('wp_enqueue_style')) {
			\wp_enqueue_style($handle, $src, $deps, $ver, $media);
		}
	}

	/**
	 * Public wrapper for WordPress wp_using_ext_object_cache()
	 * Availability-guarded: Yes
	 *
	 * Rationale: wp_using_ext_object_cache() was introduced in WordPress 6.1 and may be
	 * unavailable in older versions or early contexts. Returns false as safe default.
	 *
	 * @internal
	 * @return bool True if using external object cache, false otherwise
	 */
	protected function _do_wp_using_ext_object_cache(): bool {
		if (\function_exists('wp_using_ext_object_cache')) {
			return (bool) \wp_using_ext_object_cache();
		}
		return false;
	}
}
