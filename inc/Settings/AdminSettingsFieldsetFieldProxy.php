<?php
/**
 * AdminSettingsFieldsetFieldProxy: Field proxy that returns AdminSettingsFieldsetBuilder from end_field().
 *
 * Uses composition with FieldProxyTrait instead of inheritance from FieldsetFieldProxy.
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
 * Field proxy for AdminSettings fieldsets.
 *
 * Uses composition (trait) instead of inheritance for IDE-friendly concrete return types.
 *
 * @method AdminSettingsFieldsetFieldProxy before(?callable $before)
 * @method AdminSettingsFieldsetFieldProxy after(?callable $after)
 * @method AdminSettingsFieldsetFieldProxy order(?int $order)
 * @method AdminSettingsFieldsetFieldProxy template(string $template)
 * @method AdminSettingsFieldsetFieldProxy style(string|callable $style)
 * @method AdminSettingsFieldsetFieldProxy id(string $id)
 * @method AdminSettingsFieldsetFieldProxy disabled(bool $disabled = true)
 * @method AdminSettingsFieldsetFieldProxy required(bool $required = true)
 * @method AdminSettingsFieldsetFieldProxy readonly(bool $readonly = true)
 * @method AdminSettingsFieldsetFieldProxy attribute(string $key, string $value)
 * @method AdminSettingsFieldsetFieldProxy description(string|callable|null $description_cb)
 * @method AdminSettingsFieldsetFieldProxy ariaLabel(string $ariaLabel)
 * @method AdminSettingsFieldsetFieldProxy ariaDescribedBy(string $ariaDescribedBy)
 */
class AdminSettingsFieldsetFieldProxy implements FieldProxyInterface, ComponentBuilderInterface {
	use FieldProxyTrait;

	private AdminSettingsFieldsetBuilder $parent;
	private ?AdminSettingsFieldsetNavigation $navigation = null;

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

	public function end_field(): AdminSettingsFieldsetNavigation {
		if ($this->navigation === null) {
			$this->navigation = new AdminSettingsFieldsetNavigation($this->parent);
		}
		return $this->navigation;
	}

	public function end_fieldset(): AdminSettingsSectionBuilder {
		return $this->end_field()->end_fieldset();
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

	public function field(string $field_id, string $label, string $component, array $args = array()): AdminSettingsFieldsetFieldProxy {
		return $this->end_field()->field($field_id, $label, $component, $args);
	}
}
