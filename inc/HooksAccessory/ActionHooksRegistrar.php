<?php
/**
 * WordPress Action Hooks Registrar
 *
 * Handles registration of WordPress action hooks for objects that implement
 * the ActionHooksInterface. This class is responsible for validating and
 * registering action hooks defined in a declarative manner.
 *
 * This implementation is inspired by the polymorphic interface concepts from
 * https://carlalexander.ca/polymorphism-wordpress-interfaces/
 *
 * @package Ran\PluginLib\HooksAccessory
 * @since 0.0.10
 */

declare(strict_types=1);

namespace Ran\PluginLib\HooksAccessory;

use Ran\PluginLib\AccessoryAPI\AccessoryBaseInterface;
use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\EnqueueAccessory\WPWrappersTrait;

/**
 * Registrar for WordPress action hooks
 *
 * Processes objects that implement ActionHooksInterface and registers their
 * declared action hooks with WordPress.
 */
class ActionHooksRegistrar implements AccessoryBaseInterface {
	use WPWrappersTrait;

	/**
	 * Logger instance for debugging
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * ActionHooksRegistrar constructor.
	 *
	 * @param Logger|null $logger Optional logger instance
	 */
	public function __construct(?Logger $logger = null) {
		$this->logger = $logger ?? new Logger();
		if ($this->logger->is_active()) {
			$this->logger->debug('ActionHooksRegistrar initialized');
		}
	}

	/**
	 * Initialize action hooks for an object implementing ActionHooksInterface
	 *
	 * @param object $provider The object implementing ActionHooksInterface
	 * @return bool True if hooks were registered, false otherwise
	 * @throws \InvalidArgumentException If provider doesn't implement ActionHooksInterface
	 */
	public function init(object $provider): bool {
		if (!($provider instanceof ActionHooksInterface)) {
			throw new \InvalidArgumentException(
				'Provider must implement ActionHooksInterface'
			);
		}

		$provider_class = get_class($provider);
		$hooks          = $provider::declare_action_hooks();

		if (empty($hooks)) {
			if ($this->logger->is_active()) {
				$this->logger->warning("ActionHooksRegistrar - No action hooks defined for {$provider_class}");
			}
			return false;
		}

		// Validate hooks if validation method exists
		if (method_exists($provider, 'validate_action_hooks')) {
			$validation_errors = $provider::validate_action_hooks($provider);
			if (!empty($validation_errors)) {
				$error_message = "ActionHooksRegistrar - Validation errors for {$provider_class}: " .
				    implode(', ', $validation_errors);

				if ($this->logger->is_active()) {
					$this->logger->error($error_message);
				}
				throw new \InvalidArgumentException($error_message);
			}
		}

		// Register each hook
		foreach ($hooks as $hook_name => $hook_definition) {
			$this->register_single_action_hook($provider, $hook_name, $hook_definition);
		}

		return true;
	}

	/**
	 * Register a single action hook based on its definition
	 *
	 * @param object $provider The object implementing ActionHooksInterface
	 * @param string $hook_name The WordPress hook name
	 * @param string|array $hook_definition The hook definition (method name or array with priority/args)
	 * @return bool True if hook was registered, false otherwise
	 */
	private function register_single_action_hook(object $provider, string $hook_name, $hook_definition): bool {
		$provider_class = get_class($provider);
		$method         = '';
		$priority       = 10;
		$accepted_args  = 1;

		// Parse hook definition
		if (is_string($hook_definition)) {
			$method = $hook_definition;
		} elseif (is_array($hook_definition)) {
			$method        = $hook_definition[0] ?? '';
			$priority      = $hook_definition[1] ?? $priority;
			$accepted_args = $hook_definition[2] ?? $accepted_args;
		} else {
			if ($this->logger->is_active()) {
				$this->logger->warning(
					"ActionHooksRegistrar - Invalid hook definition for {$hook_name} in {$provider_class}"
				);
			}
			return false;
		}

		// Verify method exists
		if (!method_exists($provider, $method)) {
			if ($this->logger->is_active()) {
				$this->logger->warning(
					"ActionHooksRegistrar - Method {$method} does not exist in {$provider_class}"
				);
			}
			return false;
		}

		// Register the hook
		$this->_do_add_action($hook_name, array($provider, $method), $priority, $accepted_args);

		if ($this->logger->is_active()) {
			$this->logger->debug(
				"ActionHooksRegistrar - Registered: {$hook_name} -> {$method} (priority: {$priority}, args: {$accepted_args}) for {$provider_class}"
			);
		}

		return true;
	}
}
