<?php
/**
 * AdminSettingsSectionNavigation: Narrow navigation wrapper for AdminSettingsSectionBuilder.
 *
 * This class wraps AdminSettingsSectionBuilder and only exposes the methods
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
 * Navigation wrapper for AdminSettingsSectionBuilder.
 *
 * Provides a focused API for common post-field operations without
 * exposing section configuration methods in IDE autocomplete.
 */
final class AdminSettingsSectionNavigation implements SectionFieldNavigationInterface {
	/**
	 * The wrapped section builder.
	 */
	private AdminSettingsSectionBuilder $builder;

	/**
	 * Constructor.
	 *
	 * @param AdminSettingsSectionBuilder $builder The section builder to wrap.
	 */
	public function __construct(AdminSettingsSectionBuilder $builder) {
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
	 * @return AdminSettingsComponentProxy
	 */
	public function field(string $field_id, string $label, string $component, array $args = array()): AdminSettingsComponentProxy {
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
	 * @return AdminSettingsGroupBuilder
	 */
	public function group(string $group_id, string $heading = '', string|callable|null $description_cb = null, ?array $args = null): AdminSettingsGroupBuilder {
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
	 * @return AdminSettingsFieldsetBuilder
	 */
	public function fieldset(string $fieldset_id, string $heading = '', string|callable|null $description_cb = null, ?array $args = null): AdminSettingsFieldsetBuilder {
		return $this->builder->fieldset($fieldset_id, $heading, $description_cb, $args);
	}

	/**
	 * End the current section and return to the parent page builder.
	 *
	 * @return AdminSettingsPageBuilder
	 */
	public function end_section(): AdminSettingsPageBuilder {
		return $this->builder->end_section();
	}

	/**
	 * End the section and page, returning to the menu group builder.
	 *
	 * @return AdminSettingsMenuGroupBuilder
	 */
	public function end_page(): AdminSettingsMenuGroupBuilder {
		return $this->builder->end_page();
	}

	/**
	 * Fluent shortcut: end all the way back to AdminSettings.
	 *
	 * @return AdminSettings
	 */
	public function end(): AdminSettings {
		return $this->builder->end();
	}

	/**
	 * Start a sibling section on the same page.
	 *
	 * @param string $section_id The section ID.
	 * @param string $heading The section heading.
	 *
	 * @return AdminSettingsSectionBuilder
	 */
	public function section(string $section_id, string $heading = ''): AdminSettingsSectionBuilder {
		return $this->builder->section($section_id, $heading);
	}
}
