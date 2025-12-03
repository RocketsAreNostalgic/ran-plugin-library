<?php
/**
 * UserSettingsFieldsetFieldProxy: Field proxy that returns UserSettingsFieldsetBuilder from end_field().
 *
 * Extends FieldsetFieldProxy to provide UserSettings-specific return types.
 *
 * @package Ran\PluginLib\Settings
 */

declare(strict_types=1);

namespace Ran\PluginLib\Settings;

use Ran\PluginLib\Forms\Builders\FieldsetFieldProxy;
use Ran\PluginLib\Forms\Component\Build\ComponentBuilderBase;

class UserSettingsFieldsetFieldProxy extends FieldsetFieldProxy {
	/**
	 * @var UserSettingsFieldsetBuilder
	 */
	private UserSettingsFieldsetBuilder $userFieldsetParent;

	/**
	 * @param ComponentBuilderBase $builder The component builder.
	 * @param UserSettingsFieldsetBuilder $parent The parent UserSettingsFieldsetBuilder.
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
		UserSettingsFieldsetBuilder $parent,
		callable $updateFn,
		string $container_id,
		string $section_id,
		string $component_alias,
		?string $group_id = null,
		?string $field_template = null,
		array $pending_context = array()
	) {
		$this->userFieldsetParent = $parent;
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
	 * End field configuration and return to the UserSettingsFieldsetBuilder.
	 *
	 * @return UserSettingsFieldsetBuilder The parent fieldset builder for continued chaining.
	 */
	public function end_field(): UserSettingsFieldsetBuilder {
		return $this->userFieldsetParent;
	}

	/**
	 * End field and fieldset, returning to the section builder.
	 *
	 * @return UserSettingsSectionBuilder
	 */
	public function end_fieldset(): UserSettingsSectionBuilder {
		return $this->userFieldsetParent->end_fieldset();
	}

	/**
	 * End field, fieldset, and section, returning to the collection builder.
	 *
	 * @return UserSettingsCollectionBuilder
	 */
	public function end_section(): UserSettingsCollectionBuilder {
		return $this->userFieldsetParent->end_section();
	}
}
