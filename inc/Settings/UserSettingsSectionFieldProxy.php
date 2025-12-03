<?php
/**
 * UserSettingsSectionFieldProxy: Field proxy that returns UserSettingsSectionBuilder from end_field().
 *
 * Extends SectionFieldProxy to provide UserSettings-specific return types.
 *
 * @package Ran\PluginLib\Settings
 */

declare(strict_types=1);

namespace Ran\PluginLib\Settings;

use Ran\PluginLib\Forms\Builders\SectionFieldProxy;
use Ran\PluginLib\Forms\Component\Build\ComponentBuilderBase;

class UserSettingsSectionFieldProxy extends SectionFieldProxy {
	/**
	 * @var UserSettingsSectionBuilder
	 */
	private UserSettingsSectionBuilder $userSectionParent;

	/**
	 * @param ComponentBuilderBase $builder The component builder.
	 * @param UserSettingsSectionBuilder $parent The parent UserSettingsSectionBuilder.
	 * @param callable $updateFn The update callback.
	 * @param string $container_id The container ID.
	 * @param string $section_id The section ID.
	 * @param string $component_alias The component alias.
	 * @param string|null $group_id The group ID (null for section-level fields).
	 * @param string|null $field_template The field template override.
	 * @param array<string,mixed> $pending_context Additional context.
	 */
	public function __construct(
		ComponentBuilderBase $builder,
		UserSettingsSectionBuilder $parent,
		callable $updateFn,
		string $container_id,
		string $section_id,
		string $component_alias,
		?string $group_id = null,
		?string $field_template = null,
		array $pending_context = array()
	) {
		$this->userSectionParent = $parent;
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
	}

	/**
	 * Set the before callback for this field.
	 *
	 * @param callable|null $before The before callback.
	 *
	 * @return $this
	 */
	public function before(?callable $before): static {
		parent::before($before);
		return $this;
	}

	/**
	 * Set the after callback for this field.
	 *
	 * @param callable|null $after The after callback.
	 *
	 * @return $this
	 */
	public function after(?callable $after): static {
		parent::after($after);
		return $this;
	}

	/**
	 * End field configuration and return to the UserSettingsSectionBuilder.
	 *
	 * @return UserSettingsSectionBuilder The parent section builder for continued chaining.
	 */
	public function end_field(): UserSettingsSectionBuilder {
		return $this->userSectionParent;
	}

	/**
	 * End field and section, returning to the collection builder.
	 *
	 * @return UserSettingsCollectionBuilder
	 */
	public function end_section(): UserSettingsCollectionBuilder {
		return $this->userSectionParent->end_section();
	}
}
