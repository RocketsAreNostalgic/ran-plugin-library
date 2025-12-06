<?php
/**
 * UserSettingsGroupFieldProxy: Field proxy that returns UserSettingsGroupBuilder from end_field().
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
 * Field proxy for UserSettings groups.
 *
 * Uses composition (trait) instead of inheritance for IDE-friendly concrete return types.
 *
 * @method UserSettingsGroupFieldProxy before(?callable $before)
 * @method UserSettingsGroupFieldProxy after(?callable $after)
 * @method UserSettingsGroupFieldProxy order(?int $order)
 * @method UserSettingsGroupFieldProxy template(string $template)
 * @method UserSettingsGroupFieldProxy style(string|callable $style)
 * @method UserSettingsGroupFieldProxy id(string $id)
 * @method UserSettingsGroupFieldProxy disabled(bool $disabled = true)
 * @method UserSettingsGroupFieldProxy required(bool $required = true)
 * @method UserSettingsGroupFieldProxy readonly(bool $readonly = true)
 * @method UserSettingsGroupFieldProxy attribute(string $key, string $value)
 * @method UserSettingsGroupFieldProxy description(string|callable|null $description_cb)
 * @method UserSettingsGroupFieldProxy ariaLabel(string $ariaLabel)
 * @method UserSettingsGroupFieldProxy ariaDescribedBy(string $ariaDescribedBy)
 */
class UserSettingsGroupFieldProxy implements FieldProxyInterface, ComponentBuilderInterface {
	use FieldProxyTrait;

	/**
	 * The parent group builder - concrete type for IDE support.
	 */
	private UserSettingsGroupBuilder $parent;

	/**
	 * Cached navigation wrapper for cleaner IDE autocomplete.
	 */
	private ?UserSettingsGroupNavigation $navigation = null;

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
	 * End field configuration and return to the group navigation.
	 *
	 * Returns a navigation wrapper that only exposes methods useful after
	 * ending a field (field, end_group, end_section, end).
	 *
	 * @return \Ran\PluginLib\Settings\UserSettingsGroupNavigation
	 */
	public function end_field(): UserSettingsGroupNavigation {
		if ($this->navigation === null) {
			$this->navigation = new UserSettingsGroupNavigation($this->parent);
		}
		return $this->navigation;
	}

	/**
	 * End field and group, returning to the section builder.
	 *
	 * @return UserSettingsSectionBuilder
	 */
	public function end_group(): UserSettingsSectionBuilder {
		return $this->end_field()->end_group();
	}

	/**
	 * End field, group, and section, returning to the collection builder.
	 *
	 * @return UserSettingsCollectionBuilder
	 */
	public function end_section(): UserSettingsCollectionBuilder {
		return $this->end_field()->end_section();
	}

	/**
	 * End field, group, section, and collection, returning to UserSettings.
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
	 * Start a sibling field in the same group.
	 *
	 * @param string $field_id The field identifier.
	 * @param string $label The field label.
	 * @param string $component The component alias.
	 * @param array<string,mixed> $args Optional configuration.
	 *
	 * @return UserSettingsGroupFieldProxy
	 */
	public function field(string $field_id, string $label, string $component, array $args = array()): UserSettingsGroupFieldProxy {
		return $this->end_field()->field($field_id, $label, $component, $args);
	}
}
