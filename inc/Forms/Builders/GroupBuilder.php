<?php
/**
 * GroupBuilder: Fluent builder for grouped fields within a section.
 *
 * @package Ran\PluginLib\Forms\Builders
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders;

use Ran\PluginLib\Forms\Component\Build\ComponentBuilderDefinitionInterface;
use Ran\PluginLib\Forms\Builders\SectionFieldContainerBuilder;
use Ran\PluginLib\Forms\Builders\SectionBuilder;
use Ran\PluginLib\Forms\Builders\GroupBuilderInterface;

class GroupBuilder extends SectionFieldContainerBuilder implements GroupBuilderInterface {
	public function __construct(
		SectionBuilder $sectionBuilder,
		string $container_id,
		string $section_id,
		string $group_id,
		string $heading,
		string|callable|null $description_cb,
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
	 * Commit buffered data and return to the section builder.
	 *
	 * Emits a final group_metadata event to ensure any fields added
	 * after before()/after() calls are reflected in the metadata.
	 *
	 * @return SectionBuilderInterface
	 */
	public function end_group(): SectionBuilderInterface {
		// Emit final group metadata to capture any fields added after before()/after()
		$this->emit_group_metadata();
		return $this->section();
	}

	/**
	 * Add a field with a component builder to this group.
	 *
	 * @param string $field_id The field identifier.
	 * @param string $label The field label.
	 * @param string $component The component alias.
	 * @param array<string,mixed> $args Optional arguments.
	 *
	 * @return GroupFieldProxy The proxy instance with correct return type for end_field().
	 */
	public function field(string $field_id, string $label, string $component, array $args = array()): GroupFieldProxy {
		$proxy = parent::field($field_id, $label, $component, $args);
		if (!$proxy instanceof GroupFieldProxy) {
			throw new \RuntimeException('Unexpected proxy type from parent::field()');
		}
		return $proxy;
	}

	/**
	 * Factory method to create a GroupFieldProxy.
	 *
	 * @param ComponentBuilderDefinitionInterface $builder The component builder.
	 * @param string $component_alias The component alias.
	 * @param string|null $field_template The field template override.
	 * @param array<string,mixed> $component_context The component context.
	 *
	 * @return GroupFieldProxy The proxy instance.
	 */
	protected function _create_component_proxy(
		ComponentBuilderDefinitionInterface $builder,
		string $component_alias,
		?string $field_template,
		array $component_context
	): GroupFieldProxy {
		return new GroupFieldProxy(
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
