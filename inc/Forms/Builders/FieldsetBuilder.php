<?php
/**
 * FieldsetBuilder: Fluent builder for semantic fieldset groups within a section.
 *
 * Standalone/generic fieldset builder for use outside Settings contexts.
 * Extends FieldsetBuilderBase with GenericBuilderContext for DI consistency.
 *
 * @package Ran\PluginLib\Forms\Builders
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders;

/**
 * Standalone fieldset builder for generic/frontend forms.
 *
 * Extends FieldsetBuilderBase to provide concrete return types for IDE autocomplete.
 * Uses GenericBuilderContext for dependency injection.
 *
 * @extends FieldsetBuilderBase<SectionBuilder>
 */
class FieldsetBuilder extends FieldsetBuilderBase {
	/**
	 * @param SectionBuilder $sectionBuilder The parent section builder.
	 * @param BuilderContextInterface $context The builder context.
	 * @param string $section_id The section ID.
	 * @param string $fieldset_id The fieldset ID.
	 * @param string $heading The fieldset heading.
	 * @param string|callable|null $description_cb The description callback.
	 * @param array<string,mixed> $args Optional arguments.
	 */
	public function __construct(
		SectionBuilder $sectionBuilder,
		BuilderContextInterface $context,
		string $section_id,
		string $fieldset_id,
		string $heading = '',
		string|callable|null $description_cb = null,
		array $args = array()
	) {
		parent::__construct(
			$sectionBuilder,
			$context,
			$section_id,
			$fieldset_id,
			$heading,
			$description_cb,
			$args
		);
	}

	/**
	 * Add a field with a component builder to this fieldset.
	 *
	 * @param string $field_id The field identifier.
	 * @param string $label The field label.
	 * @param string $component The component alias.
	 * @param array<string,mixed> $args Optional arguments.
	 *
	 * @return GenericFieldBuilder<FieldsetBuilder>
	 */
	public function field(string $field_id, string $label, string $component, array $args = array()): GenericFieldBuilder {
		return parent::field($field_id, $label, $component, $args);
	}

	/**
	 * End the fieldset and return to the parent section builder.
	 *
	 * @return SectionBuilder
	 */
	public function end_fieldset(): SectionBuilder {
		return $this->sectionBuilder;
	}

	/**
	 * Define a sibling fieldset within this section.
	 *
	 * @param string $fieldset_id The fieldset ID.
	 * @param string $heading The fieldset heading.
	 * @param string|callable|null $description_cb The fieldset description.
	 * @param array<string,mixed>|null $args Optional configuration.
	 *
	 * @return FieldsetBuilder
	 */
	public function fieldset(string $fieldset_id, string $heading = '', string|callable|null $description_cb = null, ?array $args = null): FieldsetBuilder {
		return $this->sectionBuilder->fieldset($fieldset_id, $heading, $description_cb, $args ?? array());
	}
}
