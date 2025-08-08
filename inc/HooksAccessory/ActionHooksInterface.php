<?php
/**
 * WordPress Action Hooks Provider Interface
 *
 * Defines a contract for objects that need to register WordPress action hooks
 * in a declarative, type-safe manner. This interface provides a modern,
 * comprehensive approach to action hook registration with enhanced validation
 * and error handling capabilities.
 *
 * This implementation is inspired by the polymorphic interface concepts from
 * https://carlalexander.ca/polymorphism-wordpress-interfaces/ but has been
 * significantly enhanced with modern PHP patterns, comprehensive type safety,
 * and integration with the enhanced hook management system.
 *
 * @package Ran\PluginLib\HooksAccessory
 * @since 0.0.10
 */

declare(strict_types=1);

namespace Ran\PluginLib\HooksAccessory;

use Ran\PluginLib\AccessoryAPI\AccessoryBaseInterface;

/**
 * Interface for objects that provide WordPress action hook registrations
 *
 * Classes implementing this interface can declare their action hook requirements
 * in a static, declarative manner. The hook registration system will automatically
 * process these declarations and register the appropriate WordPress action hooks.
 *
 * This interface supports multiple declaration formats for maximum flexibility
 * while maintaining type safety and comprehensive validation.
 */
interface ActionHooksInterface extends AccessoryBaseInterface {
	/**
	 * Declare the action hook registrations for this object
	 *
	 * Returns an associative array where keys are WordPress action hook names
	 * and values define the callback method and optional parameters.
	 *
	 * Supported value formats:
	 * - 'method_name' - Simple method name with default priority (10) and args (1)
	 * - ['method_name', priority] - Method name with custom priority
	 * - ['method_name', priority, accepted_args] - Full specification
	 *
	 * All callback methods must:
	 * - Be public methods on the implementing class
	 * - Accept the correct number of parameters as specified
	 * - Have appropriate return types for the hook context
	 *
	 * @return array<string, string|array{string, int}|array{string, int, int}> Action hook definitions
	 * @throws \InvalidArgumentException If hook definitions are malformed
	 * @throws \BadMethodCallException If referenced methods don't exist
	 */
	public static function declare_action_hooks(): array;

	/**
	 * Validate that all declared action hooks are properly configured
	 *
	 * This method is called during hook registration to ensure that:
	 * - All referenced methods exist and are callable
	 * - Hook definitions are properly formatted
	 * - No conflicts exist between hook registrations
	 *
	 * Implementations should override this method if they need custom
	 * validation logic beyond the standard checks.
	 *
	 * @param object $instance The instance that will receive the hook callbacks
	 * @return array<string> Array of validation error messages (empty if valid)
	 */
	public static function validate_action_hooks(object $instance): array;

	/**
	 * Get metadata about the action hook registrations
	 *
	 * Returns additional information about the hook registrations that can be
	 * used for debugging, documentation, or advanced hook management features.
	 *
	 * @return array<string, mixed> Metadata about the hook registrations
	 */
	public static function get_action_hooks_metadata(): array;
}
