<?php
/**
 * UserSettingsGroupFieldProxy: Field proxy that returns UserSettingsGroupBuilder from end_field().
 *
 * Extends GroupFieldProxy to provide UserSettings-specific return types.
 *
 * @package Ran\PluginLib\Settings
 */

declare(strict_types=1);

namespace Ran\PluginLib\Settings;

use Ran\PluginLib\Forms\Component\Build\ComponentBuilderBase;
use Ran\PluginLib\Forms\Builders\GroupFieldProxy;

class UserSettingsGroupFieldProxy extends GroupFieldProxy {
	/**
	 * @var UserSettingsGroupBuilder
	 */
	private UserSettingsGroupBuilder $userGroupParent;

	/**
	 * @param ComponentBuilderBase $builder The component builder.
	 * @param UserSettingsGroupBuilder $parent The parent UserSettingsGroupBuilder.
	 * @param callable $updateFn The update callback.
	 * @param string $container_id The container ID.
	 * @param string $section_id The section ID.
	 * @param string $component_alias The component alias.
	 * @param string|null $group_id The group ID.
	 * @param string|null $field_template The field template override.
	 * @param array<string,mixed> $pending_context Additional context.
	 */
	public function __construct(
		ComponentBuilderBase $builder,
		UserSettingsGroupBuilder $parent,
		callable $updateFn,
		string $container_id,
		string $section_id,
		string $component_alias,
		?string $group_id = null,
		?string $field_template = null,
		array $pending_context = array()
	) {
		$this->userGroupParent = $parent;
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
	 * End field configuration and return to the UserSettingsGroupBuilder.
	 *
	 * @return UserSettingsGroupBuilder The parent group builder for continued chaining.
	 */
	public function end_field(): UserSettingsGroupBuilder {
		return $this->userGroupParent;
	}

	/**
	 * End field and group, returning to the section builder.
	 *
	 * @return UserSettingsSectionBuilder
	 */
	public function end_group(): UserSettingsSectionBuilder {
		return $this->userGroupParent->end_group();
	}

	/**
	 * End field, group, and section, returning to the collection builder.
	 *
	 * @return UserSettingsCollectionBuilder
	 */
	public function end_section(): UserSettingsCollectionBuilder {
		return $this->userGroupParent->end_section();
	}
}
