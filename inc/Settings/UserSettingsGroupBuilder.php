<?php
/**
 * UserSettingsGroupBuilder: Context-aware group builder for user settings sections.
 *
 * @package Ran\PluginLib\Settings
 */

declare(strict_types=1);

namespace Ran\PluginLib\Settings;

use Ran\PluginLib\Forms\Component\Build\ComponentBuilderDefinitionInterface;
use Ran\PluginLib\Forms\Builders\GroupBuilder;

class UserSettingsGroupBuilder extends GroupBuilder {
	public function __construct(
		UserSettingsSectionBuilder $sectionBuilder,
		string $container_id,
		string $section_id,
		string $group_id,
		string $heading,
		?callable $description_cb,
		callable $updateFn,
		array $args = array()
	) {
		parent::__construct(
			$sectionBuilder,
			$container_id,
			$section_id,
			$group_id,
			$heading,
			$description_cb,
			$updateFn,
			$args
		);
	}

	/**
	 * Add a field with a component builder to this user settings group.
	 *
	 * Overrides parent to return UserSettingsGroupFieldProxy directly for IDE type hints.
	 *
	 * @param string $field_id The field identifier.
	 * @param string $label The field label.
	 * @param string $component The component alias.
	 * @param array<string,mixed> $args Optional arguments.
	 *
	 * @return UserSettingsGroupFieldProxy
	 */
	public function field(string $field_id, string $label, string $component, array $args = array()): UserSettingsGroupFieldProxy {
		return parent::field($field_id, $label, $component, $args);
	}

	/**
	 * End the group and return to the parent UserSettingsSectionBuilder.
	 *
	 * @return UserSettingsSectionBuilder
	 */
	public function end_group(): UserSettingsSectionBuilder {
		$section = parent::end_group();
		if (!$section instanceof UserSettingsSectionBuilder) {
			throw new \RuntimeException('UserSettingsGroupBuilder requires UserSettingsSectionBuilder context.');
		}

		return $section;
	}

	/**
	 * Not valid in group context - throws exception.
	 *
	 * This method exists for API consistency with union return types.
	 *
	 * @return never
	 * @throws \RuntimeException Always throws - cannot end fieldset from group context.
	 */
	public function end_fieldset(): never {
		throw new \RuntimeException('Cannot call end_fieldset() from group context. Use end_group() instead.');
	}

	/**
	 * End the group and return to the parent UserSettingsCollectionBuilder.
	 *
	 * @return UserSettingsCollectionBuilder
	 */
	public function end_section(): UserSettingsCollectionBuilder {
		$builder = parent::end_section();
		if (!$builder instanceof UserSettingsCollectionBuilder) {
			throw new \RuntimeException('UserSettingsGroupBuilder must be attached to a UserSettingsCollectionBuilder instance.');
		}

		return $builder;
	}

	/**
	 * Factory method to create UserSettingsGroupFieldProxy.
	 *
	 * @return UserSettingsGroupFieldProxy
	 */
	protected function _create_component_proxy(
		ComponentBuilderDefinitionInterface $builder,
		string $component_alias,
		?string $field_template,
		array $component_context
	): UserSettingsGroupFieldProxy {
		return new UserSettingsGroupFieldProxy(
			$builder,
			$this,
			$this->updateFn,
			$this->container_id,
			$this->section_id,
			$component_alias,
			$this->group_id,
			$field_template,
			$component_context
		);
	}
}
