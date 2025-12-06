<?php
/**
 * UserSettingsSectionNavigation: Narrow navigation wrapper for UserSettingsSectionBuilder.
 *
 * This class wraps UserSettingsSectionBuilder and only exposes the methods
 * that are typically useful after calling end_field() from a field proxy.
 * It hides section configuration methods (heading, description, style, etc.)
 * to provide cleaner IDE autocomplete.
 *
 * @package Ran\PluginLib\Settings
 */

declare(strict_types=1);

namespace Ran\PluginLib\Settings;

use Ran\PluginLib\Forms\Builders\SectionFieldNavigationInterface;

/**
 * Navigation wrapper for UserSettingsSectionBuilder.
 *
 * Provides a focused API for common post-field operations without
 * exposing section configuration methods in IDE autocomplete.
 */
final class UserSettingsSectionNavigation implements SectionFieldNavigationInterface {
	/**
	 * The wrapped section builder.
	 */
	private UserSettingsSectionBuilder $builder;

	/**
	 * Constructor.
	 *
	 * @param UserSettingsSectionBuilder $builder The section builder to wrap.
	 */
	public function __construct(UserSettingsSectionBuilder $builder) {
		$this->builder = $builder;
	}

	/**
	 * Add a field with a component builder to this section.
	 *
	 * @param string $field_id The field identifier.
	 * @param string $label The field label.
	 * @param string $component The component alias.
	 * @param array<string,mixed> $args Optional arguments.
	 *
	 * @return UserSettingsComponentProxy
	 */
	public function field(string $field_id, string $label, string $component, array $args = array()): UserSettingsComponentProxy {
		return $this->builder->field($field_id, $label, $component, $args);
	}

	/**
	 * Begin configuring a grouped set of fields within this section.
	 *
	 * @param string $group_id The group ID.
	 * @param string $heading The group heading.
	 * @param string|callable|null $description_cb The description callback.
	 * @param array<string,mixed>|null $args Optional configuration.
	 *
	 * @return UserSettingsGroupBuilder
	 */
	public function group(string $group_id, string $heading = '', string|callable|null $description_cb = null, ?array $args = null): UserSettingsGroupBuilder {
		return $this->builder->group($group_id, $heading, $description_cb, $args);
	}

	/**
	 * Begin configuring a semantic fieldset grouping within this section.
	 *
	 * @param string $fieldset_id The fieldset ID.
	 * @param string $heading The fieldset heading.
	 * @param string|callable|null $description_cb The description callback.
	 * @param array<string,mixed>|null $args Optional configuration.
	 *
	 * @return UserSettingsFieldsetBuilder
	 */
	public function fieldset(string $fieldset_id, string $heading = '', string|callable|null $description_cb = null, ?array $args = null): UserSettingsFieldsetBuilder {
		return $this->builder->fieldset($fieldset_id, $heading, $description_cb, $args);
	}

	/**
	 * End the current section and return to the parent collection builder.
	 *
	 * @return UserSettingsCollectionBuilder
	 */
	public function end_section(): UserSettingsCollectionBuilder {
		return $this->builder->end_section();
	}

	/**
	 * End the section and collection, returning to UserSettings.
	 *
	 * @return UserSettings
	 */
	public function end_collection(): UserSettings {
		return $this->builder->end_collection();
	}

	/**
	 * Fluent shortcut: end all the way back to UserSettings.
	 *
	 * @return UserSettings
	 */
	public function end(): UserSettings {
		return $this->builder->end();
	}

	/**
	 * Start a sibling section on the same collection.
	 *
	 * @param string $section_id The section ID.
	 * @param string $heading The section heading.
	 *
	 * @return UserSettingsSectionBuilder
	 */
	public function section(string $section_id, string $heading = ''): UserSettingsSectionBuilder {
		return $this->builder->section($section_id, $heading);
	}
}
