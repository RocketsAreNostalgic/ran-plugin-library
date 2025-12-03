<?php
/**
 * AdminSettingsFieldsetBuilder: Context-aware fieldset builder for admin settings sections.
 *
 * @package Ran\PluginLib\Settings
 */

declare(strict_types=1);

namespace Ran\PluginLib\Settings;

use Ran\PluginLib\Forms\Component\Build\ComponentBuilderDefinitionInterface;
use Ran\PluginLib\Forms\Builders\FieldsetFieldProxy;
use Ran\PluginLib\Forms\Builders\FieldsetBuilder;

final class AdminSettingsFieldsetBuilder extends FieldsetBuilder {
	public function __construct(
		AdminSettingsSectionBuilder $sectionBuilder,
		string $container_id,
		string $section_id,
		string $group_id,
		string $heading,
		?callable $description_cb,
		callable $updateFn,
		array $args = array()
	) {
		parent::__construct(
			$sectionBuilder,
			$container_id,
			$section_id,
			$group_id,
			$heading,
			$description_cb,
			$updateFn,
			$args
		);
	}

	/**
	 * Add a field with a component builder to this admin fieldset.
	 *
	 * @return AdminSettingsFieldsetFieldProxy
	 */
	public function field(string $field_id, string $label, string $component, array $args = array()): AdminSettingsFieldsetFieldProxy {
		$result = parent::field($field_id, $label, $component, $args);
		if ($result instanceof AdminSettingsFieldsetFieldProxy) {
			return $result;
		}
		throw new \RuntimeException('Unexpected return type from parent::field()');
	}

	/**
	 * End the fieldset and return the parent section builder.
	 *
	 * @return AdminSettingsSectionBuilder
	 */
	public function end_fieldset(): AdminSettingsSectionBuilder {
		$section = parent::end_fieldset();
		if (!$section instanceof AdminSettingsSectionBuilder) {
			throw new \RuntimeException('AdminSettingsFieldsetBuilder requires AdminSettingsSectionBuilder context.');
		}

		return $section;
	}

	/**
	 * End the current section and return the parent page builder.
	 *
	 * @return AdminSettingsPageBuilder
	 */
	public function end_section(): AdminSettingsPageBuilder {
		$builder = parent::end_section();
		if (!$builder instanceof AdminSettingsPageBuilder) {
			throw new \RuntimeException('AdminSettingsFieldsetBuilder must be attached to an AdminSettingsPageBuilder instance.');
		}

		return $builder;
	}

	/**
	 * Factory method to create AdminSettingsFieldsetFieldProxy.
	 *
	 * @return AdminSettingsFieldsetFieldProxy
	 */
	protected function _create_component_proxy(
		ComponentBuilderDefinitionInterface $builder,
		string $component_alias,
		?string $field_template,
		array $component_context
	): AdminSettingsFieldsetFieldProxy {
		return new AdminSettingsFieldsetFieldProxy(
			$builder,
			$this,
			$this->updateFn,
			$this->container_id,
			$this->section_id,
			$component_alias,
			$this->group_id,
			$field_template,
			$component_context
		);
	}
}
