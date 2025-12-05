<?php
/**
 * AdminSettingsGroupFieldProxy: Field proxy that returns AdminSettingsGroupBuilder from end_field().
 *
 * Uses composition with FieldProxyTrait instead of inheritance from GroupFieldProxy.
 * This provides concrete return types for full IDE support.
 *
 * @package Ran\PluginLib\Settings
 */

declare(strict_types=1);

namespace Ran\PluginLib\Settings;

use Ran\PluginLib\Forms\Component\Build\ComponentBuilderInterface;
use Ran\PluginLib\Forms\Component\Build\ComponentBuilderBase;
use Ran\PluginLib\Forms\Builders\Traits\FieldProxyTrait;
use Ran\PluginLib\Forms\Builders\FieldProxyInterface;

/**
 * Field proxy for AdminSettings groups.
 *
 * Uses composition (trait) instead of inheritance for IDE-friendly concrete return types.
 */
class AdminSettingsGroupFieldProxy implements FieldProxyInterface, ComponentBuilderInterface {
	use FieldProxyTrait;

	/**
	 * The parent group builder - concrete type for IDE support.
	 */
	private AdminSettingsGroupBuilder $parent;

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
		$this->parent = $parent;
		$this->_init_proxy(
			$builder,
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
		return $this->parent;
	}

	/**
	 * End field and group, returning to the section builder.
	 *
	 * @return AdminSettingsSectionBuilder
	 */
	public function end_group(): AdminSettingsSectionBuilder {
		return $this->parent->end_group();
	}

	/**
	 * End field, group, and section, returning to the page builder.
	 *
	 * @return AdminSettingsPageBuilder
	 */
	public function end_section(): AdminSettingsPageBuilder {
		return $this->parent->end_section();
	}

	/**
	 * End field, group, section, and page, returning to the menu group builder.
	 *
	 * @return AdminSettingsMenuGroupBuilder
	 */
	public function end_page(): AdminSettingsMenuGroupBuilder {
		return $this->parent->end_page();
	}

	/**
	 * Fluent shortcut: end all the way back to AdminSettings.
	 *
	 * @return AdminSettings
	 */
	public function end(): AdminSettings {
		return $this->end_page()->end_menu_group();
	}
}
