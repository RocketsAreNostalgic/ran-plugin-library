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
 * Trait for easy integration of enhanced hook management
 *
 * Usage:
 * ```php
 * class MyClass {
 *     use HooksManagementTrait;
 *
 *     public function init() {
 *         // Initialize declarative hooks
 *         $this->init_hooks();
 *
 *         // Register dynamic hooks
 *         $this->register_action('wp_init', [$this, 'on_wp_init']);
 *         $this->register_conditional_action('admin_init', [$this, 'admin_only'], 'is_admin');
 *     }
 * }
 * ```
 */
trait HooksManagementTrait {
	/**
	 * Enhanced hooks manager instance
	 * @var HooksManager|null
	 */
	private ?HooksManager $hooks_manager = null;

	/**
	 * Get or create the hooks manager
	 */
	protected function get_hooks_manager(): HooksManager {
		if ($this->hooks_manager === null) {
			$logger              = method_exists($this, 'get_logger') ? $this->get_logger() : new Logger();
			$this->hooks_manager = new HooksManager($this, $logger);
		}
		return $this->hooks_manager;
	}

	/**
	 * Initialize all hooks (both declarative and dynamic)
	 */
	protected function init_hooks(): void {
		$hooks_manager = $this->get_hooks_manager();

		// Initialize declarative hooks from interfaces
		$hooks_manager->init_declarative_hooks();

		// Call hook initialization method if it exists
		if (method_exists($this, 'register_hooks')) {
			$this->register_hooks();
		}
	}

	// === CONVENIENT ACTION REGISTRATION METHODS ===

	/**
	 * Register an action hook
	 */
	protected function register_action(
        string $hook_name,
        callable $callback,
        int $priority = 10,
        int $accepted_args = 1,
        array $context = array()
    ): bool {
		return $this->get_hooks_manager()->register_action($hook_name, $callback, $priority, $accepted_args, $context);
	}

	/**
	 * Register a filter hook
	 */
	protected function register_filter(
        string $hook_name,
        callable $callback,
        int $priority = 10,
        int $accepted_args = 1,
        array $context = array()
    ): bool {
		return $this->get_hooks_manager()->register_filter($hook_name, $callback, $priority, $accepted_args, $context);
	}

	/**
	 * Register an action hook using a method name
	 */
	protected function register_action_method(
        string $hook_name,
        string $method_name,
        int $priority = 10,
        int $accepted_args = 1,
        array $context = array()
    ): bool {
		return $this->get_hooks_manager()->register_method_hook(
			'action',
			$hook_name,
			$method_name,
			$priority,
			$accepted_args,
			$context
		);
	}

	/**
	 * Register a filter hook using a method name
	 */
	protected function register_filter_method(
        string $hook_name,
        string $method_name,
        int $priority = 10,
        int $accepted_args = 1,
        array $context = array()
    ): bool {
		return $this->get_hooks_manager()->register_method_hook(
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
	 * Register an action hook conditionally
	 */
	protected function register_conditional_action(
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

		$results = $this->get_hooks_manager()->register_conditional_hooks(array($hook_definition));
		return $results[0]['success'] ?? false;
	}

	/**
	 * Register a filter hook conditionally
	 */
	protected function register_conditional_filter(
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

		$results = $this->get_hooks_manager()->register_conditional_hooks(array($hook_definition));
		return $results[0]['success'] ?? false;
	}

	// === BULK REGISTRATION METHODS ===

	/**
	 * Register multiple hooks at once
	 */
	protected function register_hooks_bulk(array $hook_definitions): array {
		return $this->get_hooks_manager()->register_conditional_hooks($hook_definitions);
	}

	/**
	 * Register a group of related hooks
	 */
	protected function register_hook_group(string $group_name, array $hook_definitions): bool {
		return $this->get_hooks_manager()->register_hook_group($group_name, $hook_definitions);
	}

	// === COMMON WORDPRESS PATTERNS ===

	/**
	 * Register hooks for both frontend and admin
	 */
	protected function register_universal_action(
        string $hook_name,
        callable $callback,
        int $priority = 10,
        int $accepted_args = 1
    ): bool {
		$success1 = $this->register_action($hook_name, $callback, $priority, $accepted_args, array('context' => 'universal'));

		// For scripts/styles, also register admin version
		if (in_array($hook_name, array('wp_enqueue_scripts', 'wp_head', 'wp_footer'))) {
			$admin_hook = str_replace('wp_', 'admin_', $hook_name);
			$success2   = $this->register_action($admin_hook, $callback, $priority, $accepted_args, array('context' => 'universal_admin'));
			return $success1 && $success2;
		}

		return $success1;
	}

	/**
	 * Register admin-only hooks
	 */
	protected function register_admin_action(
        string $hook_name,
        callable $callback,
        int $priority = 10,
        int $accepted_args = 1
    ): bool {
		return $this->register_conditional_action(
			$hook_name,
			$callback,
			'is_admin',
			$priority,
			$accepted_args,
			array('context' => 'admin_only')
		);
	}

	/**
	 * Register frontend-only hooks
	 */
	protected function register_frontend_action(
        string $hook_name,
        callable $callback,
        int $priority = 10,
        int $accepted_args = 1
    ): bool {
		return $this->register_conditional_action(
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
	 * Register asset enqueue hooks with common patterns
	 */
	protected function register_asset_hooks(string $asset_type, array $hooks_config = array()): bool {
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
			$success = $this->register_action_method(
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
	 * Register deferred processing hooks
	 */
	protected function register_deferred_hooks(array $deferred_config): bool {
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

		return $this->register_hook_group('deferred_processing', $hook_definitions);
	}

	// === DEBUGGING AND INTROSPECTION ===

	/**
	 * Check if a hook is registered
	 */
	protected function is_hook_registered(string $type, string $hook_name, int $priority = 10): bool {
		return $this->get_hooks_manager()->is_hook_registered($type, $hook_name, $priority);
	}

	/**
	 * Get all registered hooks
	 */
	protected function get_registered_hooks(): array {
		return $this->get_hooks_manager()->get_registered_hooks();
	}

	/**
	 * Get hook registration statistics
	 */
	protected function get_hook_stats(): array {
		return $this->get_hooks_manager()->get_stats();
	}

	/**
	 * Get hooks by group
	 */
	protected function get_hooks_by_group(string $group_name): array {
		return $this->get_hooks_manager()->get_hooks_by_group($group_name);
	}

	/**
	 * Generate debug report for hooks
	 */
	protected function get_hooks_debug_report(): array {
		return $this->get_hooks_manager()->generate_debug_report();
	}

	/**
	 * Clear all hooks (for testing)
	 */
	protected function clear_hooks(): void {
		$this->get_hooks_manager()->clear_hooks();
	}
}
