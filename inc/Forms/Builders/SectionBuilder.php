<?php
/**
 * SectionBuilder: Fluent builder for html sections within a Settings collection.
 *
 * Standalone/generic section builder for use outside Settings contexts.
 * Extends SectionBuilderBase with GenericBuilderContext for DI consistency.
 *
 * @package Ran\PluginLib\Forms\Builders
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders;

/**
 * Standalone section builder for generic/frontend forms.
 *
 * Extends SectionBuilderBase to provide concrete return types for IDE autocomplete.
 * Uses GenericBuilderContext for dependency injection.
 *
 * @extends SectionBuilderBase<BuilderRootInterface>
 */
class SectionBuilder extends SectionBuilderBase {
	/**
	 * @param BuilderRootInterface $rootBuilder The parent root builder.
	 * @param BuilderContextInterface $context The builder context.
	 * @param string $section_id The section ID.
	 * @param string $heading The section heading.
	 * @param callable|null $before Optional callback invoked before rendering.
	 * @param callable|null $after Optional callback invoked after rendering.
	 * @param int|null $order Optional section order.
	 */
	public function __construct(
		BuilderRootInterface $rootBuilder,
		BuilderContextInterface $context,
		string $section_id,
		string $heading = '',
		?callable $before = null,
		?callable $after = null,
		?int $order = null
	) {
		parent::__construct(
			$rootBuilder,
			$context,
			$section_id,
			$heading,
			$before,
			$after,
			$order
		);
	}

	/**
	 * Add a field with a component builder to this section.
	 *
	 * @param string $field_id The field identifier.
	 * @param string $label The field label.
	 * @param string $component The component alias.
	 * @param array<string,mixed> $args Optional arguments.
	 *
	 * @return GenericFieldBuilder<SectionBuilder>
	 */
	public function field(string $field_id, string $label, string $component, array $args = array()): GenericFieldBuilder {
		return parent::field($field_id, $label, $component, $args);
	}

	/**
	 * End the section and return to the parent root builder.
	 *
	 * @return BuilderRootInterface
	 */
	public function end_section(): BuilderRootInterface {
		return $this->rootBuilder;
	}

	/**
	 * Begin configuring a grouped set of fields within this section.
	 *
	 * @param string $group_id The group ID.
	 * @param string $heading The group heading.
	 * @param string|callable|null $description_cb The description callback.
	 * @param array<string,mixed>|null $args Optional configuration.
	 *
	 * @return GroupBuilder
	 */
	public function group(string $group_id, string $heading = '', string|callable|null $description_cb = null, ?array $args = null): GroupBuilder {
		return new GroupBuilder(
			$this,
			$this->context,
			$this->section_id,
			$group_id,
			$heading,
			$description_cb,
			$args ?? array()
		);
	}

	/**
	 * Begin configuring a semantic fieldset grouping within this section.
	 *
	 * @param string $fieldset_id The fieldset ID.
	 * @param string $heading The fieldset heading.
	 * @param string|callable|null $description_cb The description callback.
	 * @param array<string,mixed>|null $args Optional configuration.
	 *
	 * @return FieldsetBuilder
	 */
	public function fieldset(string $fieldset_id, string $heading = '', string|callable|null $description_cb = null, ?array $args = null): FieldsetBuilder {
		return new FieldsetBuilder(
			$this,
			$this->context,
			$this->section_id,
			$fieldset_id,
			$heading,
			$description_cb,
			$args ?? array()
		);
	}

	/**
	 * Start a sibling section on the same collection.
	 *
	 * @param string $section_id The section ID.
	 * @param string $heading The section heading.
	 * @param string|callable|null $description_cb The section description.
	 * @param array<string,mixed> $args Optional configuration.
	 *
	 * @return SectionBuilder
	 */
	public function section(string $section_id, string $heading = '', string|callable|null $description_cb = null, array $args = array()): SectionBuilder {
		return $this->rootBuilder->section($section_id, $heading, $description_cb, $args);
	}
}
