<?php
/**
 * UserSettingsSectionBuilder: Fluent builder for user settings sections with template override support.
 *
 * Thin wrapper around SectionBuilderBase providing concrete return types for IDE support.
 *
 * @package Ran\PluginLib\Settings
 */

declare(strict_types=1);

namespace Ran\PluginLib\Settings;

use Ran\PluginLib\Forms\Builders\SectionBuilderBase;
use Ran\PluginLib\Forms\Builders\BuilderContextInterface;
use Ran\PluginLib\Forms\Builders\GenericFieldBuilder;

/**
 * Section builder for UserSettings.
 *
 * Extends SectionBuilderBase to provide concrete return types for IDE autocomplete.
 *
 * @extends SectionBuilderBase<UserSettingsCollectionBuilder>
 */
class UserSettingsSectionBuilder extends SectionBuilderBase {
	/**
	 * @param UserSettingsCollectionBuilder $collectionBuilder The parent collection builder.
	 * @param BuilderContextInterface $context The builder context.
	 * @param string $section_id The section ID.
	 * @param string $heading The section heading.
	 * @param callable|null $before Optional callback invoked before rendering.
	 * @param callable|null $after Optional callback invoked after rendering.
	 * @param int|null $order Optional section order.
	 */
	public function __construct(
		UserSettingsCollectionBuilder $collectionBuilder,
		BuilderContextInterface $context,
		string $section_id,
		string $heading,
		?callable $before = null,
		?callable $after = null,
		?int $order = null
	) {
		parent::__construct(
			$collectionBuilder,
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
	 * @return GenericFieldBuilder<UserSettingsSectionBuilder>
	 */
	public function field(string $field_id, string $label, string $component, array $args = array()): GenericFieldBuilder {
		return parent::field($field_id, $label, $component, $args);
	}

	/**
	 * End the current section and return to the parent collection builder.
	 *
	 * @return UserSettingsCollectionBuilder
	 */
	public function end_section(): UserSettingsCollectionBuilder {
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
	 * @return UserSettingsGroupBuilder
	 */
	public function group(string $group_id, string $heading = '', string|callable|null $description_cb = null, ?array $args = null): UserSettingsGroupBuilder {
		return new UserSettingsGroupBuilder(
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
	 * @return UserSettingsFieldsetBuilder
	 */
	public function fieldset(string $fieldset_id, string $heading = '', string|callable|null $description_cb = null, ?array $args = null): UserSettingsFieldsetBuilder {
		return new UserSettingsFieldsetBuilder(
			$this,
			$this->context,
			$this->section_id,
			$fieldset_id,
			$heading,
			$description_cb,
			$args ?? array()
		);
	}
}
