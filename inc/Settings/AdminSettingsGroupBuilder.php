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
	 * @return AdminSettingsGroupFieldProxy
	 */
	public function field(string $field_id, string $label, string $component, array $args = array()): AdminSettingsGroupFieldProxy {
		$result = parent::field($field_id, $label, $component, $args);
		if ($result instanceof AdminSettingsGroupFieldProxy) {
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
	 * Factory method to create AdminSettingsGroupFieldProxy.
	 *
	 * @return AdminSettingsGroupFieldProxy
	 */
	protected function _create_component_proxy(
		ComponentBuilderDefinitionInterface $builder,
		string $component_alias,
		?string $field_template,
		array $component_context
	): AdminSettingsGroupFieldProxy {
		return new AdminSettingsGroupFieldProxy(
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
