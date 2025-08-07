<?php
/**
 * Enhanced Hooks Manager - Generic WordPress Hook Management System
 *
 * Provides sophisticated hook registration patterns that can be used throughout
 * the plugin library for any WordPress hook scenario, from simple declarative
 * hooks to complex dynamic registration patterns.
 *
 * @package Ran\PluginLib\HooksAccessory
 * @since 0.0.10
 */

declare(strict_types=1);

namespace Ran\PluginLib\HooksAccessory;

use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Util\WPWrappersTrait;
use Ran\PluginLib\HooksAccessory\ActionHooksInterface;
use Ran\PluginLib\HooksAccessory\ActionHooksRegistrar;
use Ran\PluginLib\HooksAccessory\FilterHooksInterface;
use Ran\PluginLib\HooksAccessory\FilterHooksRegistrar;

/**
 * Enhanced hook manager supporting both declarative and dynamic hook patterns
 *
 * This class extends the existing HooksAccessory pattern to support:
 * - Static declarative hooks (existing ActionHooksInterface/FilterHooksInterface)
 * - Dynamic runtime hook registration
 * - Instance-based hook registration
 * - Conditional hook registration
 * - Hook deduplication and tracking
 * - Comprehensive logging and debugging
 */
class EnhancedHooksManager {
	use WPWrappersTrait;
	/**
	 * Tracks registered hooks to prevent duplicates
	 * Format: ['hook_name_priority_callback_hash' => true]
	 * @var array<string, bool>
	 */
	private array $registered_hooks = array();

	/**
	 * Logger instance for debugging
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * The object that owns these hooks
	 * @var object
	 */
	private object $owner;

	/**
	 * Registered action hooks manager (for declarative hooks)
	 * @var ActionHooksRegistrar|null
	 */
	private ?ActionHooksRegistrar $action_manager = null;

	/**
	 * Registered filter hooks manager (for declarative hooks)
	 * @var FilterHooksRegistrar|null
	 */
	private ?FilterHooksRegistrar $filter_manager = null;

	/**
	 * Hook registration statistics for debugging
	 * @var array<string, int>
	 */
	private array $stats = array(
	    'actions_registered'       => 0,
	    'filters_registered'       => 0,
	    'dynamic_hooks_registered' => 0,
	    'duplicates_prevented'     => 0,
	);

	public function __construct(object $owner, ?Logger $logger = null) {
		$this->owner  = $owner;
		$this->logger = $logger ?? new Logger();
	}

	// === DECLARATIVE HOOKS INTEGRATION ===

	/**
	 * Initialize declarative hooks from interfaces (existing HooksAccessory pattern)
	 */
	public function init_declarative_hooks(): void {
		// Register action hooks if the owner implements ActionHooksInterface
		if ($this->owner instanceof ActionHooksInterface) {
			$this->action_manager = new ActionHooksRegistrar($this->logger);
			$this->action_manager->init($this->owner);
			$this->stats['actions_registered'] += count($this->owner::declare_action_hooks());

			if ($this->logger->is_active()) {
				$this->logger->debug('EnhancedHooksManager - Registered ' . count($this->owner::declare_action_hooks()) . ' declarative actions for ' . get_class($this->owner));
			}
		}

		// Register filter hooks if the owner implements FilterHooksInterface
		if ($this->owner instanceof FilterHooksInterface) {
			$this->filter_manager = new FilterHooksRegistrar($this->owner, $this->logger);
			$this->filter_manager->init($this->owner);
			$this->stats['filters_registered'] += count($this->owner::declare_filter_hooks());

			if ($this->logger->is_active()) {
				$this->logger->debug('EnhancedHooksManager - Registered ' . count($this->owner::declare_filter_hooks()) . ' declarative filters for ' . get_class($this->owner));
			}
		}
	}

	// === DYNAMIC HOOK REGISTRATION ===

	/**
	 * Register a dynamic action hook with deduplication
	 *
	 * @param string $hook_name The name of the action hook
	 * @param callable|string $callback The callback function/method to run when the hook is called
	 * @param int $priority Optional. Used to specify the order in which the functions
	 *                      associated with a particular action are executed. Default 10.
	 *                      Lower numbers correspond with earlier execution,
	 *                      and functions with the same priority are executed
	 *                      in the order in which they were added to the action.
	 * @param int $accepted_args Optional. The number of arguments the function accepts. Default 1.
	 * @param array $context Optional. Additional context for the hook registration.
	 * @return bool True if the hook was registered, false if it failed or was a duplicate.
	 */
	public function register_action(
        string $hook_name,
        $callback,
        int $priority = 10,
        int $accepted_args = 1,
        array $context = array()
    ): bool {
		// Handle string callbacks (method names)
		if (is_string($callback) && method_exists($this->owner, $callback)) {
			$callback = array($this->owner, $callback);
		}

		if (!is_callable($callback)) {
			if ($this->logger->is_active()) {
				$this->logger->warning("EnhancedHooksManager - Invalid callback provided for action: {$hook_name}");
			}
			return false;
		}

		return $this->_register_hook('action', $hook_name, $callback, $priority, $accepted_args, $context);
	}

	/**
	 * Apply filters on a value.
	 *
	 * @param string $hook_name The name of the filter hook.
	 * @param mixed $value The value to filter.
	 * @param mixed ...$args Additional parameters to pass to the filter functions.
	 * @return mixed The filtered value.
	 */
	public function apply_filters(string $hook_name, $value, ...$args) {
		return $this->_do_apply_filter($hook_name, $value, ...$args);
	}

	/**
	 * Removes a callback function from an action or filter hook.
	 *
	 * @param string $hook_name The action or filter hook to which the function to be removed is hooked.
	 */
	public function remove_hook(string $type, string $hook_name, callable $callback, int $priority = 10): bool {
		if (!in_array($type, array('action', 'filter'), true)) {
			return false;
		}

		// Generate the callback hash for our internal tracking
		$callback_hash = $this->_generate_callback_hash($callback);
		$hook_key      = "{$type}_{$hook_name}_{$priority}_{$callback_hash}";

		// Check if we're tracking this hook
		$is_tracked = isset($this->registered_hooks[$hook_key]);

		// Remove from WordPress
		$result = false;
		if ($type === 'action') {
			$result = $this->_do_remove_action($hook_name, $callback, $priority);
		} elseif ($type === 'filter') {
			$result = $this->_do_remove_filter($hook_name, $callback, $priority);
		}

		// If successful and we were tracking it, remove from our tracking
		if ($result && $is_tracked) {
			unset($this->registered_hooks[$hook_key]);
		}

		// Return true if either the WordPress function succeeded or we removed our tracking
		return $result || $is_tracked;
	}

	/**
	 * Register a dynamic filter hook with deduplication
	 *
	 * @param string $hook_name The name of the filter hook
	 * @param callable|string $callback The callback function/method to run when the hook is called
	 * @param int $priority Optional. Used to specify the order in which the functions
	 *                      associated with a particular filter are executed. Default 10.
	 *                      Lower numbers correspond with earlier execution,
	 *                      and functions with the same priority are executed
	 *                      in the order in which they were added to the filter.
	 * @param int $accepted_args Optional. The number of arguments the function accepts. Default 1.
	 * @param array $context Optional. Additional context for the hook registration.
	 * @return bool True if the hook was registered, false if it failed or was a duplicate.
	 */
	public function register_filter(
        string $hook_name,
        $callback,
        int $priority = 10,
        int $accepted_args = 1,
        array $context = array()
    ): bool {
		// Handle string callbacks (method names)
		if (is_string($callback) && method_exists($this->owner, $callback)) {
			$callback = array($this->owner, $callback);
		}

		if (!is_callable($callback)) {
			if ($this->logger->is_active()) {
				$this->logger->warning("EnhancedHooksManager - Invalid callback provided for filter: {$hook_name}");
			}
			return false;
		}

		return $this->_register_hook('filter', $hook_name, $callback, $priority, $accepted_args, $context);
	}

	/**
	 * Register hooks from a class that implements ActionHooksInterface or FilterHooksInterface
	 *
	 * This method inspects the provided object and registers any hooks defined in its
	 * get_action_hooks() or get_filter_hooks() methods.
	 *
	 * @param object $instance An instance of a class that implements ActionHooksInterface or FilterHooksInterface
	 * @return bool True if any hooks were registered, false otherwise
	 */
	public function register_hooks_for(object $instance): bool {
		$registered = false;

		// Register action hooks if the instance implements ActionHooksInterface
		if ($instance instanceof ActionHooksInterface) {
			$hooks = $instance::declare_action_hooks();

			foreach ($hooks as $hook_name => $hook_definition) {
				$method        = '';
				$priority      = 10;
				$accepted_args = 1;

				// Handle different definition formats
				if (is_string($hook_definition)) {
					$method = $hook_definition;
				} elseif (is_array($hook_definition)) {
					$method        = $hook_definition[0] ?? '';
					$priority      = $hook_definition[1] ?? $priority;
					$accepted_args = $hook_definition[2] ?? $accepted_args;
				}

				// Only proceed if we have a valid method name
				if (!empty($method) && method_exists($instance, $method)) {
					$callback = array($instance, $method);
					$this->register_action($hook_name, $callback, $priority, $accepted_args);
					$registered = true;
				}
			}
		}

		// Register filter hooks if the instance implements FilterHooksInterface
		if ($instance instanceof FilterHooksInterface) {
			$hooks = $instance::declare_filter_hooks();

			foreach ($hooks as $hook_name => $hook_definition) {
				$method        = '';
				$priority      = 10;
				$accepted_args = 1;

				// Handle different definition formats
				if (is_string($hook_definition)) {
					$method = $hook_definition;
				} elseif (is_array($hook_definition)) {
					$method        = $hook_definition[0] ?? '';
					$priority      = $hook_definition[1] ?? $priority;
					$accepted_args = $hook_definition[2] ?? $accepted_args;
				}

				// Only proceed if we have a valid method name
				if (!empty($method) && method_exists($instance, $method)) {
					$callback = array($instance, $method);
					$this->register_filter($hook_name, $callback, $priority, $accepted_args);
					$registered = true;
				}
			}
		}

		return $registered;
	}

	/**
	 * Core hook registration method with comprehensive tracking
	 *
	 * @param string $type The type of hook (action or filter)
	 * @param string $hook_name The name of the hook
	 * @param callable $callback The callback function/method to run when the hook is called
	 * @param int $priority Optional. Used to specify the order in which the functions
	 *                      associated with a particular action are executed. Default 10.
	 *                      Lower numbers correspond with earlier execution,
	 *                      and functions with the same priority are executed
	 *                      in the order in which they were added to the action.
	 * @param int $accepted_args Optional. The number of arguments the function accepts. Default 1.
	 * @param array $context Optional. Additional context for the hook registration.
	 * @return bool True if the hook was registered, false if it failed or was a duplicate.
	 */
	protected function _register_hook(
        string $type,
        string $hook_name,
        callable $callback,
        int $priority,
        int $accepted_args,
        array $context
    ): bool {
		// Generate unique key for deduplication
		$callback_hash = $this->_generate_callback_hash($callback, $context);
		$hook_key      = "{$type}_{$hook_name}_{$priority}_{$callback_hash}";

		// Check for duplicates
		if (isset($this->registered_hooks[$hook_key])) {
			$this->stats['duplicates_prevented']++;
			if ($this->logger->is_active()) {
				$this->logger->debug("EnhancedHooksManager - Prevented duplicate {$type} registration: {$hook_name} (priority: {$priority})");
			}
			return false;
		}

		// Register the hook
		$success = match ($type) {
			'action' => $this->_do_add_action($hook_name, $callback, $priority, $accepted_args),
			'filter' => $this->_do_add_filter($hook_name, $callback, $priority, $accepted_args),
			default  => false
		};

		if ($success !== false) {
			$this->registered_hooks[$hook_key] = true;
			$this->stats['dynamic_hooks_registered']++;

			if ($this->logger->is_active()) {
				$context_str = !empty($context) ? ' [' . json_encode($context) . ']' : '';
				$this->logger->debug("EnhancedHooksManager - Registered {$type}: {$hook_name} (priority: {$priority}){$context_str}");
			}
		}

		return $success !== false;
	}

	// === SPECIALIZED REGISTRATION PATTERNS ===

	/**
	 * Register a method-based hook (for dynamic method names)
	 *
	 * @internal This is a protected method that should not be called directly.
	 * @param string $type The type of hook (action or filter)
	 * @param string $hook_name The name of the hook
	 * @param string $method_name The name of the method to run when the hook is called
	 * @param int $priority Optional. Used to specify the order in which the functions
	 *                      associated with a particular action are executed. Default 10.
	 *                      Lower numbers correspond with earlier execution,
	 *                      and functions with the same priority are executed
	 *                      in the order in which they were added to the action.
	 * @param int $accepted_args Optional. The number of arguments the function accepts. Default 1.
	 * @param array $context Optional. Additional context for the hook registration.
	 * @return bool True if the hook was registered, false if it failed or was a duplicate.
	 */
	public function register_method_hook(
        string $type,
        string $hook_name,
        string $method_name,
        int $priority = 10,
        int $accepted_args = 1,
        array $context = array()
    ): bool {
		if (!method_exists($this->owner, $method_name)) {
			if ($this->logger->is_active()) {
				$this->logger->warning("EnhancedHooksManager - Method '{$method_name}' does not exist on " . get_class($this->owner));
			}
			return false;
		}

		// Check if the method is actually callable (public methods only)
		$callback = array($this->owner, $method_name);
		if (!is_callable($callback)) {
			if ($this->logger->is_active()) {
				$this->logger->warning("EnhancedHooksManager - Method '{$method_name}' is is not callable, and is likely access protected or private, and cannot be used as a callback on " . get_class($this->owner));
			}
			return false;
		}

		return $this->_register_hook(
			$type,
			$hook_name,
			$callback,
			$priority,
			$accepted_args,
			array_merge($context, array('method' => $method_name))
		);
	}

	/**
	 * Register a closure-based hook with context preservation
	 *
	 * @internal This is a protected method that should not be called directly.
	 * @param string $type The type of hook (action or filter)
	 * @param string $hook_name The name of the hook
	 * @param \Closure $closure The closure to run when the hook is called
	 * @param int $priority Optional. Used to specify the order in which the functions
	 *                      associated with a particular action are executed. Default 10.
	 *                      Lower numbers correspond with earlier execution,
	 *                      and functions with the same priority are executed
	 *                      in the order in which they were added to the action.
	 * @param int $accepted_args Optional. The number of arguments the function accepts. Default 1.
	 * @param array $context Optional. Additional context for the hook registration.
	 * @return bool True if the hook was registered, false if it failed or was a duplicate.
	 */
	public function register_closure_hook(
        string $type,
        string $hook_name,
        \Closure $closure,
        int $priority = 10,
        int $accepted_args = 1,
        array $context = array()
    ): bool {
		return $this->_register_hook($type, $hook_name, $closure, $priority, $accepted_args, $context);
	}

	/**
	 * Register conditional hooks based on configuration
	 *
	 * @param array $hook_definitions An array of hook definitions
	 * @return array An array of results for each hook registration
	 */
	public function register_conditional_hooks(array $hook_definitions): array {
		$results = array();

		foreach ($hook_definitions as $definition) {
			// Validate definition
			if (!$this->validate_hook_definition($definition)) {
				$results[] = array('success' => false, 'error' => 'Invalid hook definition');
				continue;
			}

			// Check conditions
			if (isset($definition['condition']) && !$this->evaluate_condition($definition['condition'])) {
				$results[] = array('success' => false, 'error' => 'Condition not met');
				continue;
			}

			// Register the hook
			$success = $this->_register_hook(
				$definition['type'],
				$definition['hook'],
				$definition['callback'],
				$definition['priority']      ?? 10,
				$definition['accepted_args'] ?? 1,
				$definition['context']       ?? array()
			);

			$results[] = array('success' => $success, 'hook' => $definition['hook']);
		}

		return $results;
	}

	/**
	 * Register hooks in bulk with grouping
	 *
	 * @param string $group_name The name of the group
	 * @param array $hook_definitions An array of hook definitions
	 * @return bool True if all hooks were registered successfully, false otherwise
	 */
	public function register_hook_group(string $group_name, array $hook_definitions): bool {
		$group_context  = array('group' => $group_name);
		$all_successful = true;

		foreach ($hook_definitions as $definition) {
			$definition['context'] = array_merge($definition['context'] ?? array(), $group_context);

			$success = $this->_register_hook(
				$definition['type'],
				$definition['hook'],
				$definition['callback'],
				$definition['priority']      ?? 10,
				$definition['accepted_args'] ?? 1,
				$definition['context']
			);

			if (!$success) {
				$all_successful = false;
			}
		}

		if ($this->logger->is_active()) {
			$count  = count($hook_definitions);
			$status = $all_successful ? 'successfully' : 'with some failures';
			$this->logger->debug("EnhancedHooksManager - Registered hook group '{$group_name}' ({$count} hooks) {$status}");
		}

		return $all_successful;
	}

	// === UTILITY METHODS ===

	/**
	 * Generate a unique hash for a callback to prevent duplicates
	 */
	private function _generate_callback_hash(callable $callback, array $context = array()): string {
		if (is_array($callback)) {
			// Method callback: [object, method]
			$hash = spl_object_hash($callback[0]) . '::' . $callback[1];
		} elseif ($callback instanceof \Closure) {
			// Closure callback
			$hash = spl_object_hash($callback);
		} elseif (is_string($callback)) {
			// Function name callback
			$hash = $callback;
		} else {
			// Fallback
			$hash = serialize($callback);
		}

		// Include context in hash if provided
		if (!empty($context)) {
			$hash .= '_' . md5(serialize($context));
		}

		return $hash;
	}

	/**
	 * Validate a hook definition array
	 */
	private function validate_hook_definition(array $definition): bool {
		$required_fields = array('type', 'hook', 'callback');

		foreach ($required_fields as $field) {
			if (!isset($definition[$field])) {
				return false;
			}
		}

		if (!in_array($definition['type'], array('action', 'filter'))) {
			return false;
		}

		if (!is_callable($definition['callback'])) {
			return false;
		}

		return true;
	}

	/**
	 * Evaluate a condition for conditional hook registration
	 */
	private function evaluate_condition($condition): bool {
		if (is_bool($condition)) {
			return $condition;
		}

		if (is_string($condition)) {
			// Support for WordPress conditional functions
			if (function_exists($condition)) {
				return (bool) $condition();
			}
		}

		if (is_callable($condition)) {
			return (bool) $condition();
		}

		return false;
	}

	// === INTROSPECTION AND DEBUGGING ===

	/**
	 * Get all registered hooks for debugging
	 */
	public function get_registered_hooks(): array {
		return array_keys($this->registered_hooks);
	}

	/**
	 * Get registration statistics
	 */
	public function get_stats(): array {
		return $this->stats;
	}

	/**
	 * Check if a specific hook is registered
	 */
	public function is_hook_registered(string $type, string $hook_name, int $priority = 10, ?callable $callback = null): bool {
		if ($callback === null) {
			// Check if any hook with this name and priority exists
			$pattern = "{$type}_{$hook_name}_{$priority}_";
			foreach ($this->registered_hooks as $key => $value) {
				if (str_starts_with($key, $pattern)) {
					return true;
				}
			}
			return false;
		}

		// Check for specific callback
		$callback_hash = $this->_generate_callback_hash($callback);
		$hook_key      = "{$type}_{$hook_name}_{$priority}_{$callback_hash}";
		return isset($this->registered_hooks[$hook_key]);
	}

	/**
	 * Get hooks by group
	 */
	public function get_hooks_by_group(string $group_name): array {
		$group_hooks = array();
		$pattern     = '"group":"' . $group_name . '"';

		foreach ($this->registered_hooks as $hook_key => $value) {
			if (strpos($hook_key, $pattern) !== false) {
				$group_hooks[] = $hook_key;
			}
		}

		return $group_hooks;
	}

	/**
	 * Clear all registered hooks (for testing)
	 */
	public function clear_hooks(): void {
		$this->registered_hooks = array();
		$this->stats            = array(
		    'actions_registered'       => 0,
		    'filters_registered'       => 0,
		    'dynamic_hooks_registered' => 0,
		    'duplicates_prevented'     => 0,
		);
	}

	/**
	 * Generate a comprehensive debug report
	 */
	public function generate_debug_report(): array {
		return array(
		    'owner_class'               => get_class($this->owner),
		    'stats'                     => $this->stats,
		    'registered_hooks_count'    => count($this->registered_hooks),
		    'registered_hooks'          => $this->registered_hooks,
		    'has_declarative_actions'   => $this->owner instanceof ActionHooksInterface,
		    'has_declarative_filters'   => $this->owner instanceof FilterHooksInterface,
		    'declarative_actions_count' => $this->owner instanceof ActionHooksInterface ? count($this->owner::declare_action_hooks()) : 0,
		    'declarative_filters_count' => $this->owner instanceof FilterHooksInterface ? count($this->owner::declare_filter_hooks()) : 0,
		);
	}
}
