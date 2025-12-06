<?php
/**
 * UserSettingsFieldsetFieldProxy: Field proxy that returns UserSettingsFieldsetBuilder from end_field().
 *
 * Uses composition with FieldProxyTrait instead of inheritance from FieldsetFieldProxy.
 * This provides concrete return types for full IDE support.
 *
 * @package Ran\PluginLib\Settings
 */

declare(strict_types=1);

namespace Ran\PluginLib\Settings;

use Ran\PluginLib\Forms\Builders\FieldProxyInterface;
use Ran\PluginLib\Forms\Builders\Traits\FieldProxyTrait;
use Ran\PluginLib\Forms\Component\Build\ComponentBuilderInterface;
use Ran\PluginLib\Forms\Component\Build\ComponentBuilderBase;

/**
 * Field proxy for UserSettings fieldsets.
 *
 * Uses composition (trait) instead of inheritance for IDE-friendly concrete return types.
 *
 * @method UserSettingsFieldsetFieldProxy before(?callable $before) Set before callback.
 * @method UserSettingsFieldsetFieldProxy after(?callable $after) Set after callback.
 * @method UserSettingsFieldsetFieldProxy order(?int $order) Set field order.
 * @method UserSettingsFieldsetFieldProxy template(string $template) Set field template.
 * @method UserSettingsFieldsetFieldProxy style(string|callable $style) Set field style.
 * @method UserSettingsFieldsetFieldProxy id(string $id) Set field ID.
 * @method UserSettingsFieldsetFieldProxy disabled(bool $disabled = true) Set disabled state.
 * @method UserSettingsFieldsetFieldProxy required(bool $required = true) Set required state.
 * @method UserSettingsFieldsetFieldProxy readonly(bool $readonly = true) Set readonly state.
 * @method UserSettingsFieldsetFieldProxy attribute(string $key, string $value) Set an attribute.
 * @method UserSettingsFieldsetFieldProxy description(string|callable|null $description_cb) Set description.
 */
class UserSettingsFieldsetFieldProxy implements FieldProxyInterface, ComponentBuilderInterface {
	use FieldProxyTrait;

	/**
	 * The parent fieldset builder - concrete type for IDE support.
	 */
	private UserSettingsFieldsetBuilder $parent;

	/**
	 * Cached navigation wrapper for cleaner IDE autocomplete.
	 */
	private ?UserSettingsFieldsetNavigation $navigation = null;

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
	 * End field configuration and return to the fieldset navigation.
	 *
	 * Returns a navigation wrapper that only exposes methods useful after
	 * ending a field (field, end_fieldset, end_section, end).
	 *
	 * @return UserSettingsFieldsetNavigation
	 */
	public function end_field(): UserSettingsFieldsetNavigation {
		if ($this->navigation === null) {
			$this->navigation = new UserSettingsFieldsetNavigation($this->parent);
		}
		return $this->navigation;
	}

	/**
	 * End field and fieldset, returning to the section builder.
	 *
	 * @return UserSettingsSectionBuilder
	 */
	public function end_fieldset(): UserSettingsSectionBuilder {
		return $this->end_field()->end_fieldset();
	}

	/**
	 * End field, fieldset, and section, returning to the collection builder.
	 *
	 * @return UserSettingsCollectionBuilder
	 */
	public function end_section(): UserSettingsCollectionBuilder {
		return $this->end_field()->end_section();
	}

	/**
	 * End field, fieldset, section, and collection, returning to UserSettings.
	 *
	 * @return UserSettings
	 */
	public function end_collection(): UserSettings {
		return $this->end_field()->end_collection();
	}

	/**
	 * Fluent shortcut: end all the way back to UserSettings.
	 *
	 * @return UserSettings
	 */
	public function end(): UserSettings {
		return $this->end_field()->end();
	}

	/**
	 * Start a sibling field in the same fieldset.
	 *
	 * @param string $field_id The field identifier.
	 * @param string $label The field label.
	 * @param string $component The component alias.
	 * @param array<string,mixed> $args Optional configuration.
	 *
	 * @return UserSettingsFieldsetFieldProxy
	 */
	public function field(string $field_id, string $label, string $component, array $args = array()): UserSettingsFieldsetFieldProxy {
		return $this->end_field()->field($field_id, $label, $component, $args);
	}
}
