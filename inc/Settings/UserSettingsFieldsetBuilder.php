<?php
/**
 * UserSettingsFieldsetBuilder: Context-aware fieldset builder for user settings sections.
 *
 * @package Ran\PluginLib\Settings
 */

declare(strict_types=1);

namespace Ran\PluginLib\Settings;

use Ran\PluginLib\Forms\Component\Build\ComponentBuilderDefinitionInterface;
use Ran\PluginLib\Forms\Builders\FieldsetBuilder;

final class UserSettingsFieldsetBuilder extends FieldsetBuilder {
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
	 * Add a field with a component builder to this user settings fieldset.
	 *
	 * @return UserSettingsFieldsetFieldProxy
	 */
	public function field(string $field_id, string $label, string $component, array $args = array()): UserSettingsFieldsetFieldProxy {
		$result = parent::field($field_id, $label, $component, $args);
		if ($result instanceof UserSettingsFieldsetFieldProxy) {
			return $result;
		}
		throw new \RuntimeException('Unexpected return type from parent::field()');
	}

	/**
	 * End the fieldset and return to the parent UserSettingsSectionBuilder.
	 *
	 * @return UserSettingsSectionBuilder
	 */
	public function end_fieldset(): UserSettingsSectionBuilder {
		$section = parent::end_fieldset();
		if (!$section instanceof UserSettingsSectionBuilder) {
			throw new \RuntimeException('UserSettingsFieldsetBuilder requires UserSettingsSectionBuilder context.');
		}

		return $section;
	}

	/**
	 * Not valid in fieldset context - throws exception.
	 *
	 * This method exists for API consistency with union return types.
	 *
	 * @return never
	 * @throws \RuntimeException Always throws - cannot end group from fieldset context.
	 */
	public function end_group(): never {
		throw new \RuntimeException('Cannot call end_group() from fieldset context. Use end_fieldset() instead.');
	}

	/**
	 * End the fieldset and return to the parent UserSettingsCollectionBuilder.
	 *
	 * @return UserSettingsCollectionBuilder
	 */
	public function end_section(): UserSettingsCollectionBuilder {
		$builder = parent::end_section();
		if (!$builder instanceof UserSettingsCollectionBuilder) {
			throw new \RuntimeException('UserSettingsFieldsetBuilder must be attached to a UserSettingsCollectionBuilder instance.');
		}

		return $builder;
	}

	/**
	 * Factory method to create UserSettingsFieldsetFieldProxy.
	 *
	 * @return UserSettingsFieldsetFieldProxy
	 */
	protected function _create_component_proxy(
		ComponentBuilderDefinitionInterface $builder,
		string $component_alias,
		?string $field_template,
		array $component_context
	): UserSettingsFieldsetFieldProxy {
		return new UserSettingsFieldsetFieldProxy(
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
