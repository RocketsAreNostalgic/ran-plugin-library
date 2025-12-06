<?php
/**
 * UserSettingsFieldsetNavigation: Narrow navigation wrapper for UserSettingsFieldsetBuilder.
 *
 * This class wraps UserSettingsFieldsetBuilder and only exposes the methods
 * that are typically useful after calling end_field() from a fieldset field proxy.
 *
 * @package Ran\PluginLib\Settings
 */

declare(strict_types=1);

namespace Ran\PluginLib\Settings;

/**
 * Navigation wrapper for UserSettingsFieldsetBuilder.
 *
 * Provides a focused API for common post-field operations without
 * exposing fieldset configuration methods in IDE autocomplete.
 */
final class UserSettingsFieldsetNavigation {
	/**
	 * The wrapped fieldset builder.
	 */
	private UserSettingsFieldsetBuilder $builder;

	/**
	 * Constructor.
	 *
	 * @param UserSettingsFieldsetBuilder $builder The fieldset builder to wrap.
	 */
	public function __construct(UserSettingsFieldsetBuilder $builder) {
		$this->builder = $builder;
	}

	/**
	 * Add a field with a component builder to this fieldset.
	 *
	 * @param string $field_id The field identifier.
	 * @param string $label The field label.
	 * @param string $component The component alias.
	 * @param array<string,mixed> $args Optional arguments.
	 *
	 * @return UserSettingsFieldsetFieldProxy
	 */
	public function field(string $field_id, string $label, string $component, array $args = array()): UserSettingsFieldsetFieldProxy {
		return $this->builder->field($field_id, $label, $component, $args);
	}

	/**
	 * End the current fieldset and return to the section builder.
	 *
	 * @return UserSettingsSectionBuilder
	 */
	public function end_fieldset(): UserSettingsSectionBuilder {
		return $this->builder->end_fieldset();
	}

	/**
	 * End fieldset and section, returning to the collection builder.
	 *
	 * @return UserSettingsCollectionBuilder
	 */
	public function end_section(): UserSettingsCollectionBuilder {
		return $this->builder->end_section();
	}

	/**
	 * End fieldset, section, and collection, returning to UserSettings.
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
