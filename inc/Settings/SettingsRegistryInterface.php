<?php
/**
 * Settings Registry Interface
 *
 * Common interface for AdminMenuRegistry and UserSettingsRegistry.
 * Provides a unified entry point for lazy settings registration.
 *
 * @package Ran\PluginLib\Settings
 * @since   0.2.0
 */

declare(strict_types=1);

namespace Ran\PluginLib\Settings;

/**
 * Interface for settings registries that provide lazy loading.
 *
 * Both AdminMenuRegistry (for site/network scopes) and UserSettingsRegistry
 * (for user scope) implement this interface to provide a consistent API.
 */
interface SettingsRegistryInterface {
	/**
	 * Register settings with a builder callback.
	 *
	 * The callback receives the registry instance and should define
	 * containers (pages, menu groups, or collections) with on_render callbacks.
	 *
	 * Expensive dependencies (RegisterOptions, ComponentManifest) are NOT
	 * created during registration - only when a page/collection is rendered.
	 *
	 * @param callable $callback Receives the registry instance.
	 * @return void
	 */
	public function register(callable $callback): void;

	/**
	 * Get the option key used for storage.
	 *
	 * @return string
	 */
	public function get_option_key(): string;
}
