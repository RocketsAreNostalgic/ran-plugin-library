<?php
/**
 * Hooks Management Trait - Easy integration of HooksManager
 *
 * Provides a simple trait that any class can use to get sophisticated
 * hook management capabilities without boilerplate.
 *
 * @package Ran\PluginLib\HooksAccessory
 * @since 0.0.10
 */

declare(strict_types=1);

namespace Ran\PluginLib\HooksAccessory;

use Ran\PluginLib\Util\Logger;

/**
 * Trait for integrating the enhanced `HooksManager` API into any class.
 *
 * Overview:
 * - Provides memoized access to a per-owner `HooksManager` instance
 * - Thin convenience wrappers for registering actions/filters by callable or method name
 * - Conditional and bulk registration helpers with consistent shapes
 * - Common WordPress patterns (admin-only, frontend-only, universal) for ergonomics
 * - Debug/introspection helpers to assist testing and diagnostics
 *
 * Usage:
 * ```php
 * class MyClass {
 *     use HooksManagementTrait;
 *
 *     public function init(): void {
 *         // Declarative hooks (via interfaces) + optional programmatic hooks
 *         $this->_init_hooks();
 *         $this->_register_action('wp_init', [$this, 'on_wp_init']);
 *         $this->_register_conditional_action('admin_init', [$this, 'admin_only'], 'is_admin');
 *     }
 * }
 * ```
 *
 * Notes:
 * - Prefer these helpers over direct `add_action`/`add_filter` calls when a `HooksManager`
 *   is already in use for the class. For one-off hooks in isolated classes, wrappers from
 *   `WPWrappersTrait` remain acceptable as documented.
 */
trait HooksManagementTrait {
	/**
	 * Enhanced hooks manager instance
	 * @var HooksManager|null
	 */
	private ?HooksManager $hooks_manager = null;

	/**
	 * Get or lazily create the per-owner `HooksManager`.
	 *
	 * Returns a memoized instance constructed with `$this` as the owner and the
	 * class logger when available. Subsequent calls return the same instance.
	 *
	 * @return HooksManager Hooks manager bound to the current object
	 */
	protected function _get_hooks_manager(): HooksManager {
		if ($this->hooks_manager === null) {
			$logger              = method_exists($this, 'get_logger') ? $this->get_logger() : new Logger();
			$this->hooks_manager = new HooksManager($this, $logger);
		}
		return $this->hooks_manager;
	}

	/**
	 * Initialize all hooks (declarative + optional programmatic).
	 *
	 * - Invokes `init_declarative_hooks()` to register hooks declared via
	 *   the action/filter provider interfaces.
	 * - If the owner defines a `register_hooks()` method, it is invoked to
	 *   allow programmatic registrations using the helpers below.
	 *
	 * @return void
	 */
	protected function _init_hooks(): void {
		$hooks_manager = $this->_get_hooks_manager();

		// Initialize declarative hooks from interfaces
		$hooks_manager->init_declarative_hooks();

		// Call hook initialization method if it exists
		if (method_exists($this, 'register_hooks')) {
			$this->register_hooks();
		}
	}

	// === CONVENIENT ACTION REGISTRATION METHODS ===

	/**
	 * Register an action hook.
	 *
	 * @param string   $hook_name      WordPress action name
	 * @param callable $callback       Callable to execute
	 * @param int      $priority       Priority (lower runs earlier)
	 * @param int      $accepted_args  Number of accepted arguments
	 * @param array    $context        Optional context metadata stored with the hook
	 * @return bool True on successful registration
	 */
	protected function _register_action(
        string $hook_name,
        callable $callback,
        int $priority = 10,
        int $accepted_args = 1,
        array $context = array()
    ): bool {
		return $this->_get_hooks_manager()->register_action($hook_name, $callback, $priority, $accepted_args, $context);
	}

	/**
	 * Register a filter hook.
	 *
	 * @param string   $hook_name      WordPress filter name
	 * @param callable $callback       Callable to execute
	 * @param int      $priority       Priority (lower runs earlier)
	 * @param int      $accepted_args  Number of accepted arguments
	 * @param array    $context        Optional context metadata stored with the hook
	 * @return bool True on successful registration
	 */
	protected function _register_filter(
        string $hook_name,
        callable $callback,
        int $priority = 10,
        int $accepted_args = 1,
        array $context = array()
    ): bool {
		return $this->_get_hooks_manager()->register_filter($hook_name, $callback, $priority, $accepted_args, $context);
	}

	/**
	 * Register an action hook by method name on the owner object.
	 *
	 * @param string $hook_name     WordPress action name
	 * @param string $method_name   Method on `$this` to invoke
	 * @param int    $priority      Priority (lower runs earlier)
	 * @param int    $accepted_args Number of accepted arguments
	 * @param array  $context       Optional context metadata stored with the hook
	 * @return bool True on successful registration
	 */
	protected function _register_action_method(
        string $hook_name,
        string $method_name,
        int $priority = 10,
        int $accepted_args = 1,
        array $context = array()
    ): bool {
		return $this->_get_hooks_manager()->register_method_hook(
			'action',
			$hook_name,
			$method_name,
			$priority,
			$accepted_args,
			$context
		);
	}

	/**
	 * Register a filter hook by method name on the owner object.
	 *
	 * @param string $hook_name     WordPress filter name
	 * @param string $method_name   Method on `$this` to invoke
	 * @param int    $priority      Priority (lower runs earlier)
	 * @param int    $accepted_args Number of accepted arguments
	 * @param array  $context       Optional context metadata stored with the hook
	 * @return bool True on successful registration
	 */
	protected function _register_filter_method(
        string $hook_name,
        string $method_name,
        int $priority = 10,
        int $accepted_args = 1,
        array $context = array()
    ): bool {
		return $this->_get_hooks_manager()->register_method_hook(
			'filter',
			$hook_name,
			$method_name,
			$priority,
			$accepted_args,
			$context
		);
	}

	// === CONDITIONAL REGISTRATION METHODS ===

	/**
	 * Register an action hook conditionally.
	 *
	 * @param string          $hook_name     Action name
	 * @param callable        $callback      Callback to execute
	 * @param callable|string $condition     Predicate (callable) or function name
	 * @param int             $priority      Priority
	 * @param int             $accepted_args Accepted args
	 * @param array           $context       Context metadata
	 * @return bool True when the registration was queued successfully
	 */
	protected function _register_conditional_action(
        string $hook_name,
        callable $callback,
        $condition,
        int $priority = 10,
        int $accepted_args = 1,
        array $context = array()
    ): bool {
		$hook_definition = array(
		    'type'          => 'action',
		    'hook'          => $hook_name,
		    'callback'      => $callback,
		    'priority'      => $priority,
		    'accepted_args' => $accepted_args,
		    'context'       => $context,
		    'condition'     => $condition,
		);

		$results = $this->_get_hooks_manager()->register_conditional_hooks(array($hook_definition));
		return $results[0]['success'] ?? false;
	}

	/**
	 * Register a filter hook conditionally.
	 *
	 * @param string          $hook_name     Filter name
	 * @param callable        $callback      Callback to execute
	 * @param callable|string $condition     Predicate (callable) or function name
	 * @param int             $priority      Priority
	 * @param int             $accepted_args Accepted args
	 * @param array           $context       Context metadata
	 * @return bool True when the registration was queued successfully
	 */
	protected function _register_conditional_filter(
        string $hook_name,
        callable $callback,
        $condition,
        int $priority = 10,
        int $accepted_args = 1,
        array $context = array()
    ): bool {
		$hook_definition = array(
		    'type'          => 'filter',
		    'hook'          => $hook_name,
		    'callback'      => $callback,
		    'priority'      => $priority,
		    'accepted_args' => $accepted_args,
		    'context'       => $context,
		    'condition'     => $condition,
		);

		$results = $this->_get_hooks_manager()->register_conditional_hooks(array($hook_definition));
		return $results[0]['success'] ?? false;
	}

	// === BULK REGISTRATION METHODS ===

	/**
	 * Register multiple hooks at once.
	 *
	 * Expects an array of hook definitions matching the `register_conditional_hooks`
	 * input shape used by `HooksManager`.
	 *
	 * @param array<int, array<string,mixed>> $hook_definitions Array of hook definitions
	 * @return array<int, array{success:bool, error?:string}> Registration results
	 */
	protected function _register_hooks_bulk(array $hook_definitions): array {
		return $this->_get_hooks_manager()->register_conditional_hooks($hook_definitions);
	}

	/**
	 * Register a named group of related hooks (for tracking/debugging).
	 *
	 * @param string                              $group_name       Identifier for the group
	 * @param array<int, array<string,mixed>>     $hook_definitions Hook definitions
	 * @return bool True if group registration succeeded
	 */
	protected function _register_hook_group(string $group_name, array $hook_definitions): bool {
		return $this->_get_hooks_manager()->register_hook_group($group_name, $hook_definitions);
	}

	// === COMMON WORDPRESS PATTERNS ===

	/**
	 * Register hooks for both frontend and admin.
	 *
	 * For enqueue-oriented hooks, also registers the `admin_` companion for
	 * parity.
	 *
	 * @param string   $hook_name     Action name
	 * @param callable $callback      Callback to execute
	 * @param int      $priority      Priority
	 * @param int      $accepted_args Accepted args
	 * @return bool True if all required registrations succeeded
	 */
	protected function _register_universal_action(
        string $hook_name,
        callable $callback,
        int $priority = 10,
        int $accepted_args = 1
    ): bool {
		$success1 = $this->_register_action($hook_name, $callback, $priority, $accepted_args, array('context' => 'universal'));

		// For scripts/styles, also register admin version
		if (in_array($hook_name, array('wp_enqueue_scripts', 'wp_head', 'wp_footer'))) {
			$admin_hook = str_replace('wp_', 'admin_', $hook_name);
			$success2   = $this->_register_action($admin_hook, $callback, $priority, $accepted_args, array('context' => 'universal_admin'));
			return $success1 && $success2;
		}

		return $success1;
	}

	/**
	 * Register admin-only hooks.
	 *
	 * @param string   $hook_name     Action name
	 * @param callable $callback      Callback to execute
	 * @param int      $priority      Priority
	 * @param int      $accepted_args Accepted args
	 * @return bool True when queued successfully
	 */
	protected function _register_admin_action(
        string $hook_name,
        callable $callback,
        int $priority = 10,
        int $accepted_args = 1
    ): bool {
		return $this->_register_conditional_action(
			$hook_name,
			$callback,
			'is_admin',
			$priority,
			$accepted_args,
			array('context' => 'admin_only')
		);
	}

	/**
	 * Register frontend-only hooks.
	 *
	 * @param string   $hook_name     Action name
	 * @param callable $callback      Callback to execute
	 * @param int      $priority      Priority
	 * @param int      $accepted_args Accepted args
	 * @return bool True when queued successfully
	 */
	protected function _register_frontend_action(
        string $hook_name,
        callable $callback,
        int $priority = 10,
        int $accepted_args = 1
    ): bool {
		return $this->_register_conditional_action(
			$hook_name,
			$callback,
			function() {
				return !\is_admin();
			},
			$priority,
			$accepted_args,
			array('context' => 'frontend_only')
		);
	}

	// === ASSET-SPECIFIC CONVENIENCE METHODS ===

	/**
	 * Register asset enqueue hooks with common patterns.
	 *
	 * @param string               $asset_type    Asset type identifier (e.g. 'script','style','media')
	 * @param array<string,int>    $hooks_config  Map of hook_name => priority (overrides defaults)
	 * @return bool True if all registrations succeeded; false if missing handler or partial failures
	 */
	protected function _register_asset_hooks(string $asset_type, array $hooks_config = array()): bool {
		$default_hooks = array(
		    'wp_enqueue_scripts'    => 10,
		    'admin_enqueue_scripts' => 10,
		);

		$hooks_to_register = array_merge($default_hooks, $hooks_config);
		$method_name       = "enqueue_{$asset_type}s";

		if (!method_exists($this, $method_name)) {
			return false;
		}

		$all_successful = true;
		foreach ($hooks_to_register as $hook => $priority) {
			$success = $this->_register_action_method(
				$hook,
				$method_name,
				$priority,
				1,
				array('asset_type' => $asset_type)
			);
			if (!$success) {
				$all_successful = false;
			}
		}

		return $all_successful;
	}

	/**
	 * Register deferred processing hooks.
	 *
	 * @param array<string, array{priority?:int, callback?:callable, context?:array<string,mixed>}> $deferred_config
	 *        Map of hook_name => options; entries with null callbacks are skipped.
	 * @return bool True if group registration succeeded
	 */
	protected function _register_deferred_hooks(array $deferred_config): bool {
		$hook_definitions = array();

		foreach ($deferred_config as $hook_name => $config) {
			$priority = $config['priority'] ?? 10;
			$callback = $config['callback'] ?? null;
			$context  = $config['context']  ?? array();

			if ($callback === null) {
				continue;
			}

			$hook_definitions[] = array(
			    'type'          => 'action',
			    'hook'          => $hook_name,
			    'callback'      => $callback,
			    'priority'      => $priority,
			    'accepted_args' => 1,
			    'context'       => array_merge($context, array('deferred' => true)),
			);
		}

		return $this->_register_hook_group('deferred_processing', $hook_definitions);
	}

	// === DEBUGGING AND INTROSPECTION ===

	/**
	 * Check if a hook is registered.
	 *
	 * @param string $type      'action'|'filter'
	 * @param string $hook_name Hook name
	 * @param int    $priority  Priority to test
	 * @return bool True if a matching registration exists
	 */
	protected function _is_hook_registered(string $type, string $hook_name, int $priority = 10): bool {
		return $this->_get_hooks_manager()->is_hook_registered($type, $hook_name, $priority);
	}

	/**
	 * Get all registered hook keys.
	 *
	  * @return array<int, string> List of unique hook keys
	 */
	protected function _get_registered_hooks(): array {
		return $this->_get_hooks_manager()->get_registered_hooks();
	}

	/**
	 * Get hook registration statistics.
	 *
	 * @return array<string, int|float> Stats (e.g., totals, duplicates prevented)
	 */
	protected function _get_hook_stats(): array {
		return $this->_get_hooks_manager()->get_stats();
	}

	/**
	 * Public: Get hook registration statistics for userland access.
	 *
	 * Exposes `HooksManager::get_stats()` without requiring consumers to call a
	 * protected underscore method.
	 *
	 * @return array<string, int|float>
	 */
	public function get_hook_stats(): array {
		return $this->_get_hooks_manager()->get_stats();
	}

	/**
	 * Get hooks by group name.
	 *
	 * @param string $group_name Group identifier
	 * @return array<int, array<string,mixed>> Hook definitions in the group
	 */
	protected function _get_hooks_by_group(string $group_name): array {
		return $this->_get_hooks_manager()->get_hooks_by_group($group_name);
	}

	/**
	 * Public: Get all registered hook keys for userland access.
	 *
	 * @return array<int, string>
	 */
	public function get_registered_hooks(): array {
		return $this->_get_hooks_manager()->get_registered_hooks();
	}

	/**
	 * Generate a structured debug report for hooks.
	 *
	 * @return array<string, mixed>
	 */
	protected function _get_hooks_debug_report(): array {
		return $this->_get_hooks_manager()->generate_debug_report();
	}

	/**
	 * Clear all hooks (primarily for testing teardown).
	 *
	 * @return void
	 */
	protected function _clear_hooks(): void {
		$this->_get_hooks_manager()->clear_hooks();
	}
}
