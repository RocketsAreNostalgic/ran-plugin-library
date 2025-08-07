<?php
/**
 * WordPress Filter Hooks Registrar
 *
 * Handles registration of WordPress filter hooks for objects that implement
 * the FilterHooksInterface. This class is responsible for validating and
 * registering filter hooks defined in a declarative manner.
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

/**
 * Registrar for WordPress filter hooks
 *
 * Processes objects that implement FilterHooksInterface and registers their
 * declared filter hooks with WordPress.
 */
class FilterHooksRegistrar implements AccessoryBaseInterface {
	/**
	 * The object that owns these hooks
	 * @var object
	 */
	private object $owner;

	/**
	 * Logger instance for debugging
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * FilterHooksRegistrar constructor.
	 *
	 * @param object $owner The object that owns these hooks
	 * @param Logger|null $logger Optional logger instance
	 */
	public function __construct(object $owner, ?Logger $logger = null) {
		$this->owner  = $owner;
		$this->logger = $logger ?? new Logger();
		if ($this->logger->is_active()) {
			$this->logger->debug('FilterHooksRegistrar initialized');
		}
	}

	/**
	 * Initialize filter hooks for an object implementing FilterHooksInterface
	 *
	 * @param object $provider The object implementing FilterHooksInterface
	 * @return bool True if hooks were registered, false otherwise
	 * @throws \InvalidArgumentException If provider doesn't implement FilterHooksInterface
	 */
	public function init(object $provider): bool {
		if (!($provider instanceof FilterHooksInterface)) {
			throw new \InvalidArgumentException(
				'Provider must implement FilterHooksInterface'
			);
		}

		$provider_class = get_class($provider);
		$hooks          = $provider::declare_filter_hooks();

		if (empty($hooks)) {
			if ($this->logger->is_active()) {
				$this->logger->warning("FilterHooksRegistrar - No filter hooks defined for {$provider_class}");
			}
			return false;
		}

		// Validate hooks if validation method exists
		if (method_exists($provider, 'validate_filter_hooks')) {
			$validation_errors = $provider::validate_filter_hooks($provider);
			if (!empty($validation_errors)) {
				$error_message = "FilterHooksRegistrar - Validation errors for {$provider_class}: " .
				    implode(', ', $validation_errors);

				if ($this->logger->is_active()) {
					$this->logger->error($error_message);
				}
				throw new \InvalidArgumentException($error_message);
			}
		}

		// Register each hook
		foreach ($hooks as $hook_name => $hook_definition) {
			$this->register_single_filter_hook($provider, $hook_name, $hook_definition);
		}

		return true;
	}

	/**
	 * Register a single filter hook based on its definition
	 *
	 * @param object $provider The object implementing FilterHooksInterface
	 * @param string $hook_name The WordPress hook name
	 * @param string|array $hook_definition The hook definition (method name or array with priority/args)
	 * @return bool True if hook was registered, false otherwise
	 */
	private function register_single_filter_hook(object $provider, string $hook_name, $hook_definition): bool {
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
					"FilterHooksRegistrar - Invalid hook definition for {$hook_name} in {$provider_class}"
				);
			}
			return false;
		}

		// Verify method exists
		if (!method_exists($provider, $method)) {
			if ($this->logger->is_active()) {
				$this->logger->warning(
					"FilterHooksRegistrar - Method {$method} does not exist in {$provider_class}"
				);
			}
			return false;
		}

		// Register the hook
		add_filter($hook_name, array($provider, $method), $priority, $accepted_args);

		if ($this->logger->is_active()) {
			$this->logger->debug(
				"FilterHooksRegistrar - Registered: {$hook_name} -> {$method} (priority: {$priority}, args: {$accepted_args}) for {$provider_class}"
			);
		}

		return true;
	}
}
