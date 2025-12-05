<?php
/**
 * AdminSettingsComponentProxy: Type-safe component builder proxy for AdminSettings fluent chains.
 *
 * Uses composition with FieldProxyTrait instead of inheritance from ComponentBuilderProxy.
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
 * Type-safe proxy for AdminSettings field builder chains.
 *
 * Uses composition (trait) instead of inheritance for IDE-friendly concrete return types.
 * Handles fields added directly to sections (not inside groups or fieldsets).
 */
class AdminSettingsComponentProxy implements FieldProxyInterface, ComponentBuilderInterface {
	use FieldProxyTrait;

	/**
	 * The parent builder with Admin-specific typing.
	 *
	 * @var AdminSettingsSectionBuilder|AdminSettingsGroupBuilder|AdminSettingsFieldsetBuilder
	 */
	private AdminSettingsSectionBuilder|AdminSettingsGroupBuilder|AdminSettingsFieldsetBuilder $parent;

	/**
	 * Constructor.
	 *
	 * @param ComponentBuilderBase $builder The underlying component builder.
	 * @param AdminSettingsSectionBuilder|AdminSettingsGroupBuilder|AdminSettingsFieldsetBuilder $parent The parent builder.
	 * @param callable $updateFn The update callback for immediate data flow.
	 * @param string $container_id The container (page) ID.
	 * @param string $section_id The section ID.
	 * @param string $component_alias The component alias.
	 * @param string|null $group_id The group ID if within a group.
	 * @param string|null $field_template The field template override.
	 * @param array<string,mixed> $pending_context Additional context for the component.
	 */
	public function __construct(
		ComponentBuilderBase $builder,
		AdminSettingsSectionBuilder|AdminSettingsGroupBuilder|AdminSettingsFieldsetBuilder $parent,
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
	 * End field configuration and return to the parent builder.
	 *
	 * @return AdminSettingsSectionBuilder|AdminSettingsGroupBuilder|AdminSettingsFieldsetBuilder
	 */
	public function end_field(): AdminSettingsSectionBuilder|AdminSettingsGroupBuilder|AdminSettingsFieldsetBuilder {
		return $this->parent;
	}

	/**
	 * End field and group, returning to the section builder.
	 *
	 * @return AdminSettingsSectionBuilder
	 */
	public function end_group(): AdminSettingsSectionBuilder {
		$parent = $this->end_field();
		if ($parent instanceof AdminSettingsGroupBuilder) {
			return $parent->end_group();
		}
		if ($parent instanceof AdminSettingsSectionBuilder) {
			return $parent;
		}
		throw new \RuntimeException('Cannot call end_group() from fieldset context.');
	}

	/**
	 * End field and fieldset, returning to the section builder.
	 *
	 * @return AdminSettingsSectionBuilder
	 */
	public function end_fieldset(): AdminSettingsSectionBuilder {
		$parent = $this->end_field();
		if ($parent instanceof AdminSettingsFieldsetBuilder) {
			return $parent->end_fieldset();
		}
		if ($parent instanceof AdminSettingsSectionBuilder) {
			return $parent;
		}
		throw new \RuntimeException('Cannot call end_fieldset() from group context.');
	}

	/**
	 * End field and section, returning to the page builder.
	 *
	 * @return AdminSettingsPageBuilder
	 */
	public function end_section(): AdminSettingsPageBuilder {
		$parent = $this->end_field();
		if ($parent instanceof AdminSettingsGroupBuilder || $parent instanceof AdminSettingsFieldsetBuilder) {
			return $parent->end_section();
		}
		if ($parent instanceof AdminSettingsSectionBuilder) {
			return $parent->end_section();
		}
		throw new \RuntimeException('Unexpected parent type in end_section().');
	}

	/**
	 * End field, section, and page, returning to the menu group builder.
	 *
	 * @return AdminSettingsMenuGroupBuilder
	 */
	public function end_page(): AdminSettingsMenuGroupBuilder {
		return $this->end_section()->end_page();
	}

	/**
	 * Fluent shortcut: end all the way back to AdminSettings.
	 *
	 * @return AdminSettings
	 */
	public function end(): AdminSettings {
		return $this->end_page()->end_menu_group();
	}

	/**
	 * Start a sibling group from the current field's section context.
	 *
	 * @param string $group_id The group identifier.
	 * @param string $heading The group heading (optional).
	 * @param string|callable|null $description_cb Optional description (string or callback).
	 * @param array<string,mixed>|null $args Optional configuration.
	 *
	 * @return AdminSettingsGroupBuilder
	 */
	public function group(string $group_id, string $heading = '', string|callable|null $description_cb = null, ?array $args = null): AdminSettingsGroupBuilder {
		$parent = $this->end_field();
		if ($parent instanceof AdminSettingsSectionBuilder) {
			return $parent->group($group_id, $heading, $description_cb, $args);
		}
		if ($parent instanceof AdminSettingsGroupBuilder) {
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
	 * @return AdminSettingsFieldsetBuilder
	 */
	public function fieldset(string $fieldset_id, string $heading = '', string|callable|null $description_cb = null, ?array $args = null): AdminSettingsFieldsetBuilder {
		$parent = $this->end_field();
		if ($parent instanceof AdminSettingsSectionBuilder) {
			return $parent->fieldset($fieldset_id, $heading, $description_cb, $args);
		}
		if ($parent instanceof AdminSettingsFieldsetBuilder) {
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
	 * @return AdminSettingsComponentProxy
	 */
	public function field(string $field_id, string $label, string $component, array $args = array()): AdminSettingsComponentProxy {
		$parent = $this->end_field();
		return $parent->field($field_id, $label, $component, $args);
	}
}
