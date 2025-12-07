<?php
/**
 * AdminSettingsFieldsetBuilder: Context-aware fieldset builder for admin settings sections.
 *
 * Uses composition with traits instead of inheritance from FieldsetBuilder.
 * This provides concrete return types for full IDE support.
 *
 * @package Ran\PluginLib\Settings
 */

declare(strict_types=1);

namespace Ran\PluginLib\Settings;

use Ran\PluginLib\Forms\FormsInterface;
use Ran\PluginLib\Forms\Component\Build\ComponentBuilderDefinitionInterface;
use Ran\PluginLib\Forms\Builders\Traits\SectionFieldContainerTrait;
use Ran\PluginLib\Forms\Builders\Traits\FieldsetBuilderTrait;
use Ran\PluginLib\Forms\Builders\FieldsetBuilderInterface;
use Ran\PluginLib\Forms\Builders\BuilderImmediateUpdateTrait;

/**
 * Fieldset builder for AdminSettings.
 *
 * Uses composition (traits) instead of inheritance for IDE-friendly concrete return types.
 */
final class AdminSettingsFieldsetBuilder implements FieldsetBuilderInterface {
	use SectionFieldContainerTrait;
	use FieldsetBuilderTrait;
	use BuilderImmediateUpdateTrait;

	/**
	 * The parent section builder - concrete type for IDE support.
	 */
	private AdminSettingsSectionBuilder $sectionBuilder;

	/**
	 * @param AdminSettingsSectionBuilder $sectionBuilder The parent section builder.
	 * @param string $container_id The container ID.
	 * @param string $section_id The section ID.
	 * @param string $group_id The fieldset ID.
	 * @param string $heading The fieldset heading.
	 * @param string|callable|null $description_cb The description callback.
	 * @param callable $updateFn The update callback.
	 * @param array<string,mixed> $args Optional arguments.
	 */
	public function __construct(
		AdminSettingsSectionBuilder $sectionBuilder,
		string $container_id,
		string $section_id,
		string $group_id,
		string $heading,
		string|callable|null $description_cb,
		callable $updateFn,
		array $args = array()
	) {
		$this->sectionBuilder = $sectionBuilder;

		// Initialize container (from SectionFieldContainerTrait)
		$this->_init_container(
			$container_id,
			$section_id,
			$group_id,
			$heading,
			$description_cb,
			$updateFn,
			$args
		);

		// Initialize fieldset-specific properties (from FieldsetBuilderTrait)
		$this->_init_fieldset($args);
	}

	/**
	 * Add a field with a component builder to this admin settings fieldset.
	 *
	 * @param string $field_id The field identifier.
	 * @param string $label The field label.
	 * @param string $component The component alias.
	 * @param array<string,mixed> $args Optional arguments.
	 *
	 * @return AdminSettingsFieldsetFieldProxy
	 */
	public function field(string $field_id, string $label, string $component, array $args = array()): AdminSettingsFieldsetFieldProxy {
		$proxy = $this->_add_field($field_id, $label, $component, $args);
		if (!$proxy instanceof AdminSettingsFieldsetFieldProxy) {
			throw new \RuntimeException('Unexpected proxy type from _add_field()');
		}
		return $proxy;
	}

	/**
	 * End the fieldset and return to the parent AdminSettingsSectionBuilder.
	 *
	 * @return AdminSettingsSectionBuilder
	 */
	public function end_fieldset(): AdminSettingsSectionBuilder {
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
	 * @return AdminSettingsFieldsetBuilder
	 */
	public function fieldset(string $fieldset_id, string $heading = '', string|callable|null $description_cb = null, ?array $args = null): AdminSettingsFieldsetBuilder {
		return $this->sectionBuilder->fieldset($fieldset_id, $heading, $description_cb, $args ?? array());
	}

	/**
	 * Get the FormsInterface instance.
	 *
	 * @return FormsInterface
	 */
	public function get_settings(): FormsInterface {
		return $this->sectionBuilder->_get_forms();
	}

	// =========================================================================
	// Abstract method implementations from traits
	// =========================================================================

	/**
	 * Get the container type.
	 *
	 * @return string
	 */
	protected function _get_container_type(): string {
		return 'fieldset';
	}

	/**
	 * Get the component builder factory for a given component alias.
	 *
	 * @param string $component The component alias.
	 *
	 * @return callable|null
	 */
	protected function _get_component_builder_factory(string $component): ?callable {
		return $this->sectionBuilder->_get_component_builder_factory($component);
	}

	/**
	 * Create a field proxy for the given component builder.
	 *
	 * @param ComponentBuilderDefinitionInterface $builder The component builder.
	 * @param string $component_alias The component alias.
	 * @param string|null $field_template The field template override.
	 * @param array<string,mixed> $component_context The component context.
	 *
	 * @return AdminSettingsFieldsetFieldProxy
	 */
	protected function _create_field_proxy(
		ComponentBuilderDefinitionInterface $builder,
		string $component_alias,
		?string $field_template,
		array $component_context
	): AdminSettingsFieldsetFieldProxy {
		return new AdminSettingsFieldsetFieldProxy(
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

	// =========================================================================
	// BuilderImmediateUpdateTrait abstract implementations
	// =========================================================================

	/**
	 * Apply a meta update to local state.
	 *
	 * @param string $key The meta key.
	 * @param mixed $value The new value.
	 */
	protected function _apply_meta_update(string $key, mixed $value): void {
		// Try fieldset-specific keys first
		if ($this->_apply_fieldset_meta_update($key, $value)) {
			$this->_emit_container_metadata();
			return;
		}

		// Fall back to container keys
		$this->_apply_container_meta_update($key, $value);
	}

	/**
	 * Get the update callback.
	 *
	 * @return callable
	 */
	protected function _get_update_callback(): callable {
		return $this->updateFn;
	}

	/**
	 * Get the update event name.
	 *
	 * @return string
	 */
	protected function _get_update_event_name(): string {
		return 'group_metadata';
	}

	/**
	 * Build the update payload.
	 *
	 * @param string $key The meta key.
	 * @param mixed $value The new value.
	 *
	 * @return array<string,mixed>
	 */
	protected function _build_update_payload(string $key, mixed $value): array {
		$payload = $this->_build_container_payload();
		return $this->_extend_fieldset_payload($payload);
	}
}
