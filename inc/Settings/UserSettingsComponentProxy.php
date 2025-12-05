<?php
/**
 * UserSettingsComponentProxy: Type-safe component builder proxy for UserSettings fluent chains.
 *
 * This proxy extends ComponentBuilderProxy to provide accurate return types for IDE autocompletion
 * when building UserSettings forms. It overrides navigation methods to return User-specific types.
 *
 * @package Ran\PluginLib\Settings
 */

declare(strict_types=1);

namespace Ran\PluginLib\Settings;

use Ran\PluginLib\Forms\Builders\ComponentBuilderProxy;
use Ran\PluginLib\Forms\Builders\SectionBuilder;
use Ran\PluginLib\Forms\Builders\SectionFieldContainerBuilder;
use Ran\PluginLib\Forms\Component\Build\ComponentBuilderBase;

/**
 * Type-safe proxy for UserSettings field builder chains.
 *
 * Provides covariant return types for navigation methods, enabling proper IDE
 * autocompletion throughout fluent UserSettings builder chains.
 */
class UserSettingsComponentProxy extends ComponentBuilderProxy {
	/**
	 * The parent builder with User-specific typing.
	 *
	 * @var UserSettingsSectionBuilder|UserSettingsGroupBuilder|UserSettingsFieldsetBuilder
	 */
	private UserSettingsSectionBuilder|UserSettingsGroupBuilder|UserSettingsFieldsetBuilder $userParent;

	/**
	 * Constructor.
	 *
	 * @param ComponentBuilderBase $builder The underlying component builder.
	 * @param UserSettingsSectionBuilder|UserSettingsGroupBuilder|UserSettingsFieldsetBuilder $parent The parent builder.
	 * @param callable $updateFn The update callback for immediate data flow.
	 * @param string $container_id The container (collection) ID.
	 * @param string $section_id The section ID.
	 * @param string $component_alias The component alias.
	 * @param string|null $group_id The group ID if within a group.
	 * @param string|null $field_template The field template override.
	 * @param array<string,mixed> $pending_context Additional context for the component.
	 */
	public function __construct(
		ComponentBuilderBase $builder,
		UserSettingsSectionBuilder|UserSettingsGroupBuilder|UserSettingsFieldsetBuilder $parent,
		callable $updateFn,
		string $container_id,
		string $section_id,
		string $component_alias,
		?string $group_id = null,
		?string $field_template = null,
		array $pending_context = array()
	) {
		parent::__construct(
			$builder,
			$parent,
			$updateFn,
			$container_id,
			$section_id,
			$component_alias,
			$group_id,
			$field_template,
			$pending_context
		);
		$this->userParent = $parent;
	}

	/**
	 * End field configuration and return to the parent builder.
	 *
	 * @return UserSettingsSectionBuilder|UserSettingsGroupBuilder|UserSettingsFieldsetBuilder
	 */
	public function end_field(): UserSettingsSectionBuilder|UserSettingsGroupBuilder|UserSettingsFieldsetBuilder {
		return $this->userParent;
	}

	/**
	 * End field and group, returning to the section builder.
	 *
	 * @return UserSettingsSectionBuilder
	 */
	public function end_group(): UserSettingsSectionBuilder {
		$parent = $this->end_field();
		if ($parent instanceof UserSettingsGroupBuilder) {
			return $parent->end_group();
		}
		if ($parent instanceof UserSettingsSectionBuilder) {
			return $parent;
		}
		throw new \RuntimeException('Cannot call end_group() from fieldset context.');
	}

	/**
	 * End field and fieldset, returning to the section builder.
	 *
	 * @return UserSettingsSectionBuilder
	 */
	public function end_fieldset(): UserSettingsSectionBuilder {
		$parent = $this->end_field();
		if ($parent instanceof UserSettingsFieldsetBuilder) {
			return $parent->end_fieldset();
		}
		if ($parent instanceof UserSettingsSectionBuilder) {
			return $parent;
		}
		throw new \RuntimeException('Cannot call end_fieldset() from group context.');
	}

	/**
	 * End field and section, returning to the collection builder.
	 *
	 * @return UserSettingsCollectionBuilder
	 */
	public function end_section(): UserSettingsCollectionBuilder {
		$parent = $this->end_field();
		if ($parent instanceof UserSettingsGroupBuilder || $parent instanceof UserSettingsFieldsetBuilder) {
			return $parent->end_section();
		}
		if ($parent instanceof UserSettingsSectionBuilder) {
			return $parent->end_section();
		}
		throw new \RuntimeException('Unexpected parent type in end_section().');
	}

	/**
	 * Alias for end_section() - ends the collection and returns to UserSettings.
	 *
	 * @return UserSettingsCollectionBuilder
	 */
	public function end_collection(): UserSettingsCollectionBuilder {
		return $this->end_section();
	}

	/**
	 * End the entire builder chain, returning to the UserSettings instance.
	 *
	 * @return UserSettings
	 */
	public function end(): UserSettings {
		return $this->end_section()->end();
	}

	/**
	 * Start a sibling group from the current field's section context.
	 *
	 * @param string $group_id The group identifier.
	 * @param string $heading The group heading (optional).
	 * @param string|callable|null $description_cb Optional description (string or callback).
	 * @param array<string,mixed>|null $args Optional configuration.
	 *
	 * @return UserSettingsGroupBuilder
	 */
	public function group(string $group_id, string $heading = '', string|callable|null $description_cb = null, ?array $args = null): UserSettingsGroupBuilder {
		$parent = $this->end_field();
		if ($parent instanceof UserSettingsSectionBuilder) {
			return $parent->group($group_id, $heading, $description_cb, $args);
		}
		if ($parent instanceof UserSettingsGroupBuilder) {
			return $parent->end_group()->group($group_id, $heading, $description_cb, $args);
		}
		throw new \RuntimeException('Cannot start group from fieldset context.');
	}

	/**
	 * Start a sibling fieldset from the current field's section context.
	 *
	 * @param string $fieldset_id The fieldset identifier.
	 * @param string $heading The fieldset heading (optional).
	 * @param string|callable|null $description_cb Optional description (string or callback).
	 * @param array<string,mixed>|null $args Optional configuration.
	 *
	 * @return UserSettingsFieldsetBuilder
	 */
	public function fieldset(string $fieldset_id, string $heading = '', string|callable|null $description_cb = null, ?array $args = null): UserSettingsFieldsetBuilder {
		$parent = $this->end_field();
		if ($parent instanceof UserSettingsSectionBuilder) {
			return $parent->fieldset($fieldset_id, $heading, $description_cb, $args);
		}
		if ($parent instanceof UserSettingsFieldsetBuilder) {
			return $parent->end_fieldset()->fieldset($fieldset_id, $heading, $description_cb, $args);
		}
		throw new \RuntimeException('Cannot start fieldset from group context.');
	}

	/**
	 * Start a sibling field from the current field's parent context.
	 *
	 * @param string $field_id The field identifier.
	 * @param string $label The field label.
	 * @param string $component The component alias.
	 * @param array<string,mixed> $args Optional configuration.
	 *
	 * @return UserSettingsComponentProxy
	 */
	public function field(string $field_id, string $label, string $component, array $args = array()): UserSettingsComponentProxy {
		$parent = $this->end_field();
		return $parent->field($field_id, $label, $component, $args);
	}
}
