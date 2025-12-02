<?php
/**
 * AdminSettingsGroupFieldProxy: Field proxy that returns AdminSettingsGroupBuilder from end_field().
 *
 * Extends GroupFieldProxy to provide AdminSettings-specific return types.
 *
 * @package Ran\PluginLib\Settings
 */

declare(strict_types=1);

namespace Ran\PluginLib\Settings;

use Ran\PluginLib\Forms\Builders\GroupFieldProxy;
use Ran\PluginLib\Forms\Component\Build\ComponentBuilderBase;

class AdminSettingsGroupFieldProxy extends GroupFieldProxy {
	/**
	 * @var AdminSettingsGroupBuilder
	 */
	private AdminSettingsGroupBuilder $adminGroupParent;

	/**
	 * @param ComponentBuilderBase $builder The component builder.
	 * @param AdminSettingsGroupBuilder $parent The parent AdminSettingsGroupBuilder.
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
		AdminSettingsGroupBuilder $parent,
		callable $updateFn,
		string $container_id,
		string $section_id,
		string $component_alias,
		?string $group_id = null,
		?string $field_template = null,
		array $pending_context = array()
	) {
		$this->adminGroupParent = $parent;
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
	 * End field configuration and return to the AdminSettingsGroupBuilder.
	 *
	 * @return AdminSettingsGroupBuilder The parent group builder for continued chaining.
	 */
	public function end_field(): AdminSettingsGroupBuilder {
		return $this->adminGroupParent;
	}

	/**
	 * End field and group, returning to the section builder.
	 *
	 * @return AdminSettingsSectionBuilder
	 */
	public function end_group(): AdminSettingsSectionBuilder {
		return $this->adminGroupParent->end_group();
	}

	/**
	 * End field, group, and section, returning to the page builder.
	 *
	 * @return AdminSettingsPageBuilder
	 */
	public function end_section(): AdminSettingsPageBuilder {
		return $this->adminGroupParent->end_section();
	}
}
