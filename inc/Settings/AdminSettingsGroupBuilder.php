<?php
/**
 * AdminSettingsGroupBuilder: Context-aware group builder for admin settings sections.
 *
 * Thin wrapper around GroupBuilderBase providing concrete return types for IDE support.
 *
 * @package Ran\PluginLib\Settings
 */

declare(strict_types=1);

namespace Ran\PluginLib\Settings;

use Ran\PluginLib\Forms\Builders\GroupBuilderBase;
use Ran\PluginLib\Forms\Builders\BuilderContextInterface;
use Ran\PluginLib\Forms\Builders\GenericFieldBuilder;

/**
 * Group builder for AdminSettings.
 *
 * Extends GroupBuilderBase to provide concrete return types for IDE autocomplete.
 *
 * @extends GroupBuilderBase<AdminSettingsSectionBuilder>
 */
final class AdminSettingsGroupBuilder extends GroupBuilderBase {
	/**
	 * @param AdminSettingsSectionBuilder $sectionBuilder The parent section builder.
	 * @param BuilderContextInterface $context The builder context.
	 * @param string $section_id The section ID.
	 * @param string $group_id The group ID.
	 * @param string $heading The group heading.
	 * @param string|callable|null $description_cb The description callback.
	 * @param array<string,mixed> $args Optional arguments.
	 */
	public function __construct(
		AdminSettingsSectionBuilder $sectionBuilder,
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
	 * Add a field with a component builder to this admin settings group.
	 *
	 * @param string $field_id The field identifier.
	 * @param string $label The field label.
	 * @param string $component The component alias.
	 * @param array<string,mixed> $args Optional arguments.
	 *
	 * @return GenericFieldBuilder<AdminSettingsGroupBuilder>
	 */
	public function field(string $field_id, string $label, string $component, array $args = array()): GenericFieldBuilder {
		return parent::field($field_id, $label, $component, $args);
	}

	/**
	 * End the group and return to the parent AdminSettingsSectionBuilder.
	 *
	 * @return AdminSettingsSectionBuilder
	 */
	public function end_group(): AdminSettingsSectionBuilder {
		$this->_finalize_group();
		return $this->sectionBuilder;
	}
}
