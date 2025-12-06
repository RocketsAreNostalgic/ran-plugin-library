<?php
/**
 * UserSettingsComponentProxy: Type-safe component builder proxy for UserSettings fluent chains.
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
 * Type-safe proxy for UserSettings section-level field builder chains.
 *
 * Uses composition (trait) instead of inheritance for IDE-friendly concrete return types.
 * Handles fields added directly to sections (not inside groups or fieldsets).
 *
 * For fields inside groups, use UserSettingsGroupFieldProxy.
 * For fields inside fieldsets, use UserSettingsFieldsetFieldProxy.
 *
 * @method UserSettingsComponentProxy before(?callable $before) Set before callback.
 * @method UserSettingsComponentProxy after(?callable $after) Set after callback.
 * @method UserSettingsComponentProxy order(?int $order) Set field order.
 * @method UserSettingsComponentProxy template(string $template) Set field template.
 * @method UserSettingsComponentProxy style(string|callable $style) Set field style.
 * @method UserSettingsComponentProxy id(string $id) Set field ID.
 * @method UserSettingsComponentProxy disabled(bool $disabled = true) Set disabled state.
 * @method UserSettingsComponentProxy required(bool $required = true) Set required state.
 * @method UserSettingsComponentProxy readonly(bool $readonly = true) Set readonly state.
 * @method UserSettingsComponentProxy attribute(string $key, string $value) Set an attribute.
 * @method UserSettingsComponentProxy description(string|callable|null $description_cb) Set description.
 */
class UserSettingsComponentProxy implements FieldProxyInterface, ComponentBuilderInterface {
	use FieldProxyTrait;

	/**
	 * The parent section builder - concrete type for IDE support.
	 */
	private UserSettingsSectionBuilder $parent;

	/**
	 * Cached navigation wrapper for cleaner IDE autocomplete.
	 */
	private ?UserSettingsSectionNavigation $navigation = null;

	/**
	 * Constructor.
	 *
	 * @param ComponentBuilderBase $builder The underlying component builder.
	 * @param UserSettingsSectionBuilder $parent The parent section builder.
	 * @param callable $updateFn The update callback for immediate data flow.
	 * @param string $container_id The container (collection) ID.
	 * @param string $section_id The section ID.
	 * @param string $component_alias The component alias.
	 * @param string|null $group_id The group ID (always null for section-level fields).
	 * @param string|null $field_template The field template override.
	 * @param array<string,mixed> $pending_context Additional context for the component.
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
	 * End field configuration and return to the section navigation.
	 *
	 * Returns a navigation wrapper that only exposes methods useful after
	 * ending a field (field, group, fieldset, end_section, end, section).
	 * Use end_field()->configure() to access full section configuration.
	 *
	 * @return UserSettingsSectionNavigation
	 */
	public function end_field(): UserSettingsSectionNavigation {
		if ($this->navigation === null) {
			$this->navigation = new UserSettingsSectionNavigation($this->parent);
		}
		return $this->navigation;
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
	 * End field and section, returning to the collection builder.
	 *
	 * @return UserSettingsCollectionBuilder
	 */
	public function end_section(): UserSettingsCollectionBuilder {
		return $this->end_field()->end_section();
	}

	/**
	 * Alias for end_section() - ends the collection and returns to UserSettings.
	 *
	 * @return UserSettingsCollectionBuilder
	 */
	public function end_collection(): UserSettingsCollectionBuilder {
		return $this->end_section();
	}

	/**
	 * End the entire builder chain, returning to the UserSettings instance.
	 *
	 * @return UserSettings
	 */
	public function end(): UserSettings {
		return $this->end_section()->end();
	}

	/**
	 * Start a sibling group from the current field's section context.
	 *
	 * @param string $group_id The group identifier.
	 * @param string $heading The group heading (optional).
	 * @param string|callable|null $description_cb Optional description (string or callback).
	 * @param array<string,mixed>|null $args Optional configuration.
	 *
	 * @return UserSettingsGroupBuilder
	 */
	public function group(string $group_id, string $heading = '', string|callable|null $description_cb = null, ?array $args = null): UserSettingsGroupBuilder {
		return $this->end_field()->group($group_id, $heading, $description_cb, $args);
	}

	/**
	 * Start a sibling fieldset from the current field's section context.
	 *
	 * @param string $fieldset_id The fieldset identifier.
	 * @param string $heading The fieldset heading (optional).
	 * @param string|callable|null $description_cb Optional description (string or callback).
	 * @param array<string,mixed>|null $args Optional configuration.
	 *
	 * @return UserSettingsFieldsetBuilder
	 */
	public function fieldset(string $fieldset_id, string $heading = '', string|callable|null $description_cb = null, ?array $args = null): UserSettingsFieldsetBuilder {
		return $this->end_field()->fieldset($fieldset_id, $heading, $description_cb, $args);
	}

	/**
	 * Start a sibling field from the current field's section context.
	 *
	 * @param string $field_id The field identifier.
	 * @param string $label The field label.
	 * @param string $component The component alias.
	 * @param array<string,mixed> $args Optional configuration.
	 *
	 * @return UserSettingsComponentProxy
	 */
	public function field(string $field_id, string $label, string $component, array $args = array()): UserSettingsComponentProxy {
		return $this->end_field()->field($field_id, $label, $component, $args);
	}

	/**
	 * Start a sibling section from the current field's collection context.
	 *
	 * @param string $section_id The section identifier.
	 * @param string $heading The section heading.
	 *
	 * @return UserSettingsSectionBuilder
	 */
	public function section(string $section_id, string $heading = ''): UserSettingsSectionBuilder {
		return $this->end_field()->section($section_id, $heading);
	}
}
