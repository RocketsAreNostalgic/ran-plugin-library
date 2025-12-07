<?php
/**
 * UserSettingsFieldsetBuilder: Context-aware fieldset builder for user settings sections.
 *
 * Thin wrapper around FieldsetBuilderBase providing concrete return types for IDE support.
 *
 * @package Ran\PluginLib\Settings
 */

declare(strict_types=1);

namespace Ran\PluginLib\Settings;

use Ran\PluginLib\Forms\Builders\FieldsetBuilderBase;
use Ran\PluginLib\Forms\Builders\BuilderContextInterface;
use Ran\PluginLib\Forms\Builders\GenericFieldBuilder;

/**
 * Fieldset builder for UserSettings.
 *
 * Extends FieldsetBuilderBase to provide concrete return types for IDE autocomplete.
 *
 * @extends FieldsetBuilderBase<UserSettingsSectionBuilder>
 */
final class UserSettingsFieldsetBuilder extends FieldsetBuilderBase {
	/**
	 * @param UserSettingsSectionBuilder $sectionBuilder The parent section builder.
	 * @param BuilderContextInterface $context The builder context.
	 * @param string $section_id The section ID.
	 * @param string $fieldset_id The fieldset ID.
	 * @param string $heading The fieldset heading.
	 * @param string|callable|null $description_cb The description callback.
	 * @param array<string,mixed> $args Optional arguments.
	 */
	public function __construct(
		UserSettingsSectionBuilder $sectionBuilder,
		BuilderContextInterface $context,
		string $section_id,
		string $fieldset_id,
		string $heading,
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
	 * Add a field with a component builder to this user settings fieldset.
	 *
	 * @param string $field_id The field identifier.
	 * @param string $label The field label.
	 * @param string $component The component alias.
	 * @param array<string,mixed> $args Optional arguments.
	 *
	 * @return GenericFieldBuilder<UserSettingsFieldsetBuilder>
	 */
	public function field(string $field_id, string $label, string $component, array $args = array()): GenericFieldBuilder {
		return parent::field($field_id, $label, $component, $args);
	}

	/**
	 * End the fieldset and return to the parent UserSettingsSectionBuilder.
	 *
	 * @return UserSettingsSectionBuilder
	 */
	public function end_fieldset(): UserSettingsSectionBuilder {
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
	 * @return UserSettingsFieldsetBuilder
	 */
	public function fieldset(string $fieldset_id, string $heading = '', string|callable|null $description_cb = null, ?array $args = null): UserSettingsFieldsetBuilder {
		return $this->sectionBuilder->fieldset($fieldset_id, $heading, $description_cb, $args ?? array());
	}
}
