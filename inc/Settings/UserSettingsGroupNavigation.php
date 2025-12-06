<?php
/**
 * UserSettingsGroupNavigation: Narrow navigation wrapper for UserSettingsGroupBuilder.
 *
 * This class wraps UserSettingsGroupBuilder and only exposes the methods
 * that are typically useful after calling end_field() from a group field proxy.
 *
 * @package Ran\PluginLib\Settings
 */

declare(strict_types=1);

namespace Ran\PluginLib\Settings;

/**
 * Navigation wrapper for UserSettingsGroupBuilder.
 *
 * Provides a focused API for common post-field operations without
 * exposing group configuration methods in IDE autocomplete.
 */
final class UserSettingsGroupNavigation {
	/**
	 * The wrapped group builder.
	 */
	private UserSettingsGroupBuilder $builder;

	/**
	 * Constructor.
	 *
	 * @param UserSettingsGroupBuilder $builder The group builder to wrap.
	 */
	public function __construct(UserSettingsGroupBuilder $builder) {
		$this->builder = $builder;
	}

	/**
	 * Add a field with a component builder to this group.
	 *
	 * @param string $field_id The field identifier.
	 * @param string $label The field label.
	 * @param string $component The component alias.
	 * @param array<string,mixed> $args Optional arguments.
	 *
	 * @return UserSettingsGroupFieldProxy
	 */
	public function field(string $field_id, string $label, string $component, array $args = array()): UserSettingsGroupFieldProxy {
		return $this->builder->field($field_id, $label, $component, $args);
	}

	/**
	 * End the current group and return to the section builder.
	 *
	 * @return UserSettingsSectionBuilder
	 */
	public function end_group(): UserSettingsSectionBuilder {
		return $this->builder->end_group();
	}

	/**
	 * End group and section, returning to the collection builder.
	 *
	 * @return UserSettingsCollectionBuilder
	 */
	public function end_section(): UserSettingsCollectionBuilder {
		return $this->builder->end_section();
	}

	/**
	 * End group, section, and collection, returning to UserSettings.
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
}
