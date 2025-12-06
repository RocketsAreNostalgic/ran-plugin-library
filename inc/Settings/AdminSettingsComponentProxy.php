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
 * Type-safe proxy for AdminSettings section-level field builder chains.
 *
 * Uses composition (trait) instead of inheritance for IDE-friendly concrete return types.
 * Handles fields added directly to sections (not inside groups or fieldsets).
 *
 * For fields inside groups, use AdminSettingsGroupFieldProxy.
 * For fields inside fieldsets, use AdminSettingsFieldsetFieldProxy.
 */
class AdminSettingsComponentProxy implements FieldProxyInterface, ComponentBuilderInterface {
	use FieldProxyTrait;

	/**
	 * The parent section builder - concrete type for IDE support.
	 */
	private AdminSettingsSectionBuilder $parent;

	/**
	 * Constructor.
	 *
	 * @param ComponentBuilderBase $builder The underlying component builder.
	 * @param AdminSettingsSectionBuilder $parent The parent section builder.
	 * @param callable $updateFn The update callback for immediate data flow.
	 * @param string $container_id The container (page) ID.
	 * @param string $section_id The section ID.
	 * @param string $component_alias The component alias.
	 * @param string|null $group_id The group ID (always null for section-level fields).
	 * @param string|null $field_template The field template override.
	 * @param array<string,mixed> $pending_context Additional context for the component.
	 */
	public function __construct(
		ComponentBuilderBase $builder,
		AdminSettingsSectionBuilder $parent,
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
	 * End field configuration and return to the parent section builder.
	 *
	 * @return AdminSettingsSectionBuilder
	 */
	public function end_field(): AdminSettingsSectionBuilder {
		return $this->parent;
	}

	/**
	 * Not valid in section context - throws exception.
	 *
	 * @return never
	 * @throws \RuntimeException Always throws.
	 */
	public function end_group(): never {
		throw new \RuntimeException('Cannot call end_group() from section context. You are not inside a group.');
	}

	/**
	 * Not valid in section context - throws exception.
	 *
	 * @return never
	 * @throws \RuntimeException Always throws.
	 */
	public function end_fieldset(): never {
		throw new \RuntimeException('Cannot call end_fieldset() from section context. You are not inside a fieldset.');
	}

	/**
	 * End field and section, returning to the page builder.
	 *
	 * @return AdminSettingsPageBuilder
	 */
	public function end_section(): AdminSettingsPageBuilder {
		return $this->parent->end_section();
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
		return $this->parent->group($group_id, $heading, $description_cb, $args);
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
		return $this->parent->fieldset($fieldset_id, $heading, $description_cb, $args);
	}

	/**
	 * Start a sibling field from the current field's section context.
	 *
	 * @param string $field_id The field identifier.
	 * @param string $label The field label.
	 * @param string $component The component alias.
	 * @param array<string,mixed> $args Optional configuration.
	 *
	 * @return AdminSettingsComponentProxy
	 */
	public function field(string $field_id, string $label, string $component, array $args = array()): AdminSettingsComponentProxy {
		return $this->parent->field($field_id, $label, $component, $args);
	}

	/**
	 * Start a sibling section from the current field's page context.
	 *
	 * @param string $section_id The section identifier.
	 * @param string $heading The section heading.
	 *
	 * @return AdminSettingsSectionBuilder
	 */
	public function section(string $section_id, string $heading = ''): AdminSettingsSectionBuilder {
		return $this->parent->section($section_id, $heading);
	}
}
