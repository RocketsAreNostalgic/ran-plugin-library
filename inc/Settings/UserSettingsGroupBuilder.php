<?php
/**
 * UserSettingsGroupBuilder: Context-aware group builder for user settings sections.
 *
 * @package Ran\PluginLib\Settings
 *
 * @method static before(?callable $before) Set a callback to render content before the group.
 * @method static after(?callable $after) Set a callback to render content after the group.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Settings;

use Ran\PluginLib\Forms\Component\Build\ComponentBuilderDefinitionInterface;
use Ran\PluginLib\Forms\Builders\GroupBuilder;

final class UserSettingsGroupBuilder extends GroupBuilder {
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
	 * Add a field to this user settings group.
	 *
	 * @return UserSettingsComponentProxy|static
	 */
	public function field(string $field_id, string $label, string $component, array $args = array()): UserSettingsComponentProxy|static {
		$result = parent::field($field_id, $label, $component, $args);
		return $result instanceof UserSettingsComponentProxy ? $result : $this;
	}

	/**
	 * No-op when called on the group builder directly.
	 * Enables consistent chaining whether field() returned a proxy or $this.
	 *
	 * @return static
	 */
	public function end_field(): static {
		return $this;
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
	 * Factory method to create UserSettingsComponentProxy.
	 *
	 * @return UserSettingsComponentProxy
	 */
	protected function _create_component_proxy(
		ComponentBuilderDefinitionInterface $builder,
		string $component_alias,
		?string $field_template,
		array $component_context
	): UserSettingsComponentProxy {
		return new UserSettingsComponentProxy(
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
