<?php
/**
 * GroupBuilder: Fluent builder for grouped fields within a section.
 *
 * Standalone/generic group builder for use outside Settings contexts.
 * Extends GroupBuilderBase with GenericBuilderContext for DI consistency.
 *
 * @package Ran\PluginLib\Forms\Builders
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders;

/**
 * Standalone group builder for generic/frontend forms.
 *
 * Extends GroupBuilderBase to provide concrete return types for IDE autocomplete.
 * Uses GenericBuilderContext for dependency injection.
 *
 * @extends GroupBuilderBase<SectionBuilder>
 */
class GroupBuilder extends GroupBuilderBase {
	/**
	 * @param SectionBuilder $sectionBuilder The parent section builder.
	 * @param BuilderContextInterface $context The builder context.
	 * @param string $section_id The section ID.
	 * @param string $group_id The group ID.
	 * @param string $heading The group heading.
	 * @param string|callable|null $description_cb The description callback.
	 * @param array<string,mixed> $args Optional arguments.
	 */
	public function __construct(
		SectionBuilder $sectionBuilder,
		BuilderContextInterface $context,
		string $section_id,
		string $group_id,
		string $heading,
		string|callable|null $description_cb = null,
		array $args = array()
	) {
		parent::__construct(
			$sectionBuilder,
			$context,
			$section_id,
			$group_id,
			$heading,
			$description_cb,
			$args
		);
	}

	/**
	 * Add a field with a component builder to this group.
	 *
	 * @param string $field_id The field identifier.
	 * @param string $label The field label.
	 * @param string $component The component alias.
	 * @param array<string,mixed> $args Optional arguments.
	 *
	 * @return GenericFieldBuilder<GroupBuilder>
	 */
	public function field(string $field_id, string $label, string $component, array $args = array()): GenericFieldBuilder {
		return parent::field($field_id, $label, $component, $args);
	}

	/**
	 * End the group and return to the parent section builder.
	 *
	 * @return SectionBuilder
	 */
	public function end_group(): SectionBuilder {
		return $this->sectionBuilder;
	}
}
