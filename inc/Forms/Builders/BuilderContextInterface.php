<?php
/**
 * BuilderContextInterface: Encapsulates context-specific dependencies for fluent builders.
 *
 * Implementations provide access to the FormsInterface, component factories,
 * and update callbacks for a specific context (AdminSettings, UserSettings, etc.).
 *
 * @package Ran\PluginLib\Forms\Builders
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders;

use Ran\PluginLib\Forms\FormsInterface;

/**
 * Contract for builder context objects that encapsulate context-specific dependencies.
 *
 * This interface enables dependency injection in fluent builders, allowing shared
 * base classes to access context-specific services without tight coupling.
 */
interface BuilderContextInterface {
	/**
	 * Get the FormsInterface instance for this context.
	 *
	 * @return FormsInterface
	 */
	public function get_forms(): FormsInterface;

	/**
	 * Get the component builder factory for a given component alias.
	 *
	 * @param string $component The component alias (e.g., 'fields.input').
	 *
	 * @return callable|null The factory or null if not found.
	 */
	public function get_component_builder_factory(string $component): ?callable;

	/**
	 * Get the update callback for immediate data flow.
	 *
	 * @return callable
	 */
	public function get_update_callback(): callable;

	/**
	 * Get the container ID for this context.
	 *
	 * @return string
	 */
	public function get_container_id(): string;
}
