<?php
/**
 * FieldsetFieldProxy: Field proxy that returns FieldsetBuilder from end_field().
 *
 * Provides correct IDE type hints when fields are added within a FieldsetBuilder context.
 *
 * @package Ran\PluginLib\Forms\Builders
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders;

use Ran\PluginLib\Forms\Component\Build\ComponentBuilderBase;

class FieldsetFieldProxy extends ComponentBuilderProxy {
	/**
	 * @var FieldsetBuilder
	 */
	protected FieldsetBuilder $fieldsetParent;

	/**
	 * @param ComponentBuilderBase $builder The component builder.
	 * @param FieldsetBuilder $parent The parent FieldsetBuilder.
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
		FieldsetBuilder $parent,
		callable $updateFn,
		string $container_id,
		string $section_id,
		string $component_alias,
		?string $group_id = null,
		?string $field_template = null,
		array $pending_context = array()
	) {
		$this->fieldsetParent = $parent;
		parent::__construct(
			$builder,
			$parent,
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
	 * End field configuration and return to the FieldsetBuilder.
	 *
	 * @return FieldsetBuilder The parent FieldsetBuilder for continued chaining.
	 */
	public function end_field(): FieldsetBuilder {
		return $this->fieldsetParent;
	}
}
