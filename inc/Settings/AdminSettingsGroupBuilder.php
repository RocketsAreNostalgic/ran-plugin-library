<?php
/**
 * AdminSettingsGroupBuilder: Context-aware group builder for admin settings sections.
 *
 * @package Ran\PluginLib\Settings
 */

declare(strict_types=1);

namespace Ran\PluginLib\Settings;

use Ran\PluginLib\Forms\Component\Build\ComponentBuilderDefinitionInterface;
use Ran\PluginLib\Forms\Builders\GroupBuilder;
use Ran\PluginLib\Forms\Builders\ComponentBuilderProxy;
use Ran\PluginLib\Forms\Builders\SimpleFieldProxy;

final class AdminSettingsGroupBuilder extends GroupBuilder {
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
	 * Add a field with a component builder to this admin group.
	 *
	 * @return AdminSettingsComponentProxy
	 */
	public function field(string $field_id, string $label, string $component, array $args = array()): AdminSettingsComponentProxy {
		$result = parent::field($field_id, $label, $component, $args);
		if ($result instanceof AdminSettingsComponentProxy) {
			return $result;
		}
		throw new \RuntimeException('Unexpected return type from parent::field()');
	}

	/**
	 * Commit buffered data and return to the section builder.
	 *
	 * @return AdminSettingsSectionBuilder
	 */
	public function end_group(): AdminSettingsSectionBuilder {
		$section = parent::end_group();
		if (!$section instanceof AdminSettingsSectionBuilder) {
			throw new \RuntimeException('AdminSettingsGroupBuilder requires AdminSettingsSectionBuilder context.');
		}

		return $section;
	}

	/**
	 * Commit buffered data and return to the page builder.
	 *
	 * @return AdminSettingsPageBuilder
	 */
	public function end_section(): AdminSettingsPageBuilder {
		$builder = parent::end_section();
		if (!$builder instanceof AdminSettingsPageBuilder) {
			throw new \RuntimeException('AdminSettingsGroupBuilder must be attached to an AdminSettingsPageBuilder instance.');
		}

		return $builder;
	}

	/**
	 * Start a sibling admin group on the same section.
	 *
	 * @return AdminSettingsGroupBuilder
	 */
	public function group(string $group_id, string $heading = '', ?callable $description_cb = null, ?array $args = null): AdminSettingsGroupBuilder {
		$builder = parent::group($group_id, $heading, $description_cb, $args);
		if (!$builder instanceof AdminSettingsGroupBuilder) {
			throw new \RuntimeException('AdminSettingsGroupBuilder chaining expects AdminSettingsGroupBuilder instance.');
		}
		return $builder;
	}

	/**
	 * Factory method to create AdminSettingsComponentProxy.
	 *
	 * @return AdminSettingsComponentProxy
	 */
	protected function _create_component_proxy(
		ComponentBuilderDefinitionInterface $builder,
		string $component_alias,
		?string $field_template,
		array $component_context
	): AdminSettingsComponentProxy {
		return new AdminSettingsComponentProxy(
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
