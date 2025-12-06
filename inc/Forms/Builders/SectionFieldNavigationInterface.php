<?php
/**
 * SectionFieldNavigationInterface: Narrow interface for navigation after ending a field.
 *
 * This interface exposes only the methods that are typically useful after
 * calling end_field() from a section-level field proxy. It hides section
 * configuration methods (heading, description, style, etc.) to provide
 * cleaner IDE autocomplete.
 *
 * @package Ran\PluginLib\Forms\Builders
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders;

/**
 * Navigation interface for section builders after ending a field.
 *
 * Provides a focused API for common post-field operations:
 * - Adding more fields
 * - Starting groups or fieldsets
 * - Ending the section
 * - Starting sibling sections
 */
interface SectionFieldNavigationInterface {
	/**
	 * Add a field with a component builder to this section.
	 *
	 * @param string $field_id The field identifier.
	 * @param string $label The field label.
	 * @param string $component The component alias.
	 * @param array<string,mixed> $args Optional arguments.
	 *
	 * @return mixed The field proxy for configuration.
	 */
	public function field(string $field_id, string $label, string $component, array $args = array()): mixed;

	/**
	 * Begin configuring a grouped set of fields within this section.
	 *
	 * @param string $group_id The group ID.
	 * @param string $heading The group heading.
	 * @param string|callable|null $description_cb The description callback.
	 * @param array<string,mixed>|null $args Optional configuration.
	 *
	 * @return mixed The group builder.
	 */
	public function group(string $group_id, string $heading = '', string|callable|null $description_cb = null, ?array $args = null): mixed;

	/**
	 * Begin configuring a semantic fieldset grouping within this section.
	 *
	 * @param string $fieldset_id The fieldset ID.
	 * @param string $heading The fieldset heading.
	 * @param string|callable|null $description_cb The description callback.
	 * @param array<string,mixed>|null $args Optional configuration.
	 *
	 * @return mixed The fieldset builder.
	 */
	public function fieldset(string $fieldset_id, string $heading = '', string|callable|null $description_cb = null, ?array $args = null): mixed;

	/**
	 * End the current section and return to the parent builder.
	 *
	 * @return mixed The parent builder.
	 */
	public function end_section(): mixed;

	/**
	 * Fluent shortcut: end all the way back to the root settings instance.
	 *
	 * @return mixed The root settings instance.
	 */
	public function end(): mixed;
}
