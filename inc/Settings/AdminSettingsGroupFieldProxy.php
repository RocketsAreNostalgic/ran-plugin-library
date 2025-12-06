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
 *
 * @method AdminSettingsGroupFieldProxy before(?callable $before) Set before callback.
 * @method AdminSettingsGroupFieldProxy after(?callable $after) Set after callback.
 * @method AdminSettingsGroupFieldProxy order(?int $order) Set field order.
 * @method AdminSettingsGroupFieldProxy template(string $template) Set field template.
 * @method AdminSettingsGroupFieldProxy style(string|callable $style) Set field style.
 * @method AdminSettingsGroupFieldProxy id(string $id) Set field ID.
 * @method AdminSettingsGroupFieldProxy disabled(bool $disabled = true) Set disabled state.
 * @method AdminSettingsGroupFieldProxy required(bool $required = true) Set required state.
 * @method AdminSettingsGroupFieldProxy readonly(bool $readonly = true) Set readonly state.
 * @method AdminSettingsGroupFieldProxy attribute(string $key, string $value) Set an attribute.
 * @method AdminSettingsGroupFieldProxy description(string|callable|null $description_cb) Set description.
 */
class AdminSettingsGroupFieldProxy implements FieldProxyInterface, ComponentBuilderInterface {
	use FieldProxyTrait;

	private AdminSettingsGroupBuilder $parent;
	private ?AdminSettingsGroupNavigation $navigation = null;

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

	public function end_field(): AdminSettingsGroupNavigation {
		if ($this->navigation === null) {
			$this->navigation = new AdminSettingsGroupNavigation($this->parent);
		}
		return $this->navigation;
	}

	public function end_group(): AdminSettingsSectionBuilder {
		return $this->end_field()->end_group();
	}

	public function end_section(): AdminSettingsPageBuilder {
		return $this->end_field()->end_section();
	}

	public function end_page(): AdminSettingsMenuGroupBuilder {
		return $this->end_field()->end_page();
	}

	public function end(): AdminSettings {
		return $this->end_field()->end();
	}

	public function field(string $field_id, string $label, string $component, array $args = array()): AdminSettingsGroupFieldProxy {
		return $this->end_field()->field($field_id, $label, $component, $args);
	}
}
