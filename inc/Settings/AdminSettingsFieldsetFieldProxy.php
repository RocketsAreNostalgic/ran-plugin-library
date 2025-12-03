<?php
/**
 * AdminSettingsFieldsetFieldProxy: Field proxy that returns AdminSettingsFieldsetBuilder from end_field().
 *
 * Extends FieldsetFieldProxy to provide AdminSettings-specific return types.
 *
 * @package Ran\PluginLib\Settings
 */

declare(strict_types=1);

namespace Ran\PluginLib\Settings;

use Ran\PluginLib\Forms\Builders\FieldsetFieldProxy;
use Ran\PluginLib\Forms\Component\Build\ComponentBuilderBase;

class AdminSettingsFieldsetFieldProxy extends FieldsetFieldProxy {
	/**
	 * @var AdminSettingsFieldsetBuilder
	 */
	private AdminSettingsFieldsetBuilder $adminFieldsetParent;

	/**
	 * @param ComponentBuilderBase $builder The component builder.
	 * @param AdminSettingsFieldsetBuilder $parent The parent AdminSettingsFieldsetBuilder.
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
		AdminSettingsFieldsetBuilder $parent,
		callable $updateFn,
		string $container_id,
		string $section_id,
		string $component_alias,
		?string $group_id = null,
		?string $field_template = null,
		array $pending_context = array()
	) {
		$this->adminFieldsetParent = $parent;
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
	 * End field configuration and return to the AdminSettingsFieldsetBuilder.
	 *
	 * @return AdminSettingsFieldsetBuilder The parent fieldset builder for continued chaining.
	 */
	public function end_field(): AdminSettingsFieldsetBuilder {
		return $this->adminFieldsetParent;
	}

	/**
	 * End field and fieldset, returning to the section builder.
	 *
	 * @return AdminSettingsSectionBuilder
	 */
	public function end_fieldset(): AdminSettingsSectionBuilder {
		return $this->adminFieldsetParent->end_fieldset();
	}

	/**
	 * End field, fieldset, and section, returning to the page builder.
	 *
	 * @return AdminSettingsPageBuilder
	 */
	public function end_section(): AdminSettingsPageBuilder {
		return $this->adminFieldsetParent->end_section();
	}
}
