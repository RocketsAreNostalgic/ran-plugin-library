<?php
/**
 * UserSettingsSectionBuilder: Fluent builder for user settings sections with template override support.
 *
 * Uses composition with SectionBuilderTrait instead of inheritance from SectionBuilder.
 * This provides concrete return types for full IDE support.
 *
 * @package Ran\PluginLib\Settings
 */

declare(strict_types=1);

namespace Ran\PluginLib\Settings;

use Ran\PluginLib\Forms\FormsInterface;
use Ran\PluginLib\Forms\Component\Build\ComponentBuilderDefinitionInterface;
use Ran\PluginLib\Forms\Builders\SectionBuilderInterface;
use Ran\PluginLib\Forms\Builders\Traits\SectionBuilderTrait;
use Ran\PluginLib\Forms\Builders\BuilderImmediateUpdateTrait;

/**
 * Section builder for UserSettings.
 *
 * Uses composition (traits) instead of inheritance for IDE-friendly concrete return types.
 */
class UserSettingsSectionBuilder implements SectionBuilderInterface {
	use SectionBuilderTrait;
	use BuilderImmediateUpdateTrait;

	/**
	 * The parent collection builder - concrete type for IDE support.
	 */
	private UserSettingsCollectionBuilder $collectionBuilder;

	/**
	 * Constructor.
	 *
	 * @param UserSettingsCollectionBuilder $collectionBuilder The collection builder instance.
	 * @param string $container_id The container ID.
	 * @param string $section_id The section ID.
	 * @param string $heading The section heading.
	 * @param callable $updateFn The update function for immediate data flow.
	 * @param callable|null $before Optional callback invoked before rendering the section.
	 * @param callable|null $after Optional callback invoked after rendering the section.
	 * @param int|null $order Optional section order.
	 */
	public function __construct(
		UserSettingsCollectionBuilder $collectionBuilder,
		string $container_id,
		string $section_id,
		string $heading,
		callable $updateFn,
		?callable $before = null,
		?callable $after = null,
		?int $order = null
	) {
		$this->collectionBuilder = $collectionBuilder;

		// Initialize section (from SectionBuilderTrait)
		$this->_init_section(
			$container_id,
			$section_id,
			$heading,
			$updateFn,
			$before,
			$after,
			$order
		);
	}

	/**
	 * Get the UserSettings instance from the collection builder.
	 *
	 * @return FormsInterface
	 */
	public function get_settings(): FormsInterface {
		return $this->collectionBuilder->get_settings();
	}

	/**
	 * Add a field with a component builder to this section.
	 *
	 * @param string $field_id The field identifier.
	 * @param string $label The field label.
	 * @param string $component The component alias.
	 * @param array<string,mixed> $args Optional arguments.
	 *
	 * @return UserSettingsComponentProxy
	 */
	public function field(string $field_id, string $label, string $component, array $args = array()): UserSettingsComponentProxy {
		$proxy = $this->_add_section_field($field_id, $label, $component, $args);
		if (!$proxy instanceof UserSettingsComponentProxy) {
			throw new \RuntimeException('Unexpected proxy type from _add_section_field()');
		}
		return $proxy;
	}

	/**
	 * Set the field template for field wrapper customization.
	 *
	 * @param string $template_key The template key to use for field wrappers.
	 *
	 * @return static
	 * @throws \InvalidArgumentException If template key is empty.
	 */
	public function field_template(string $template_key): static {
		if (trim($template_key) === '') {
			throw new \InvalidArgumentException('Template key cannot be empty');
		}

		($this->updateFn)('template_override', array(
			'element_type' => 'section',
			'element_id'   => $this->section_id,
			'overrides'    => array('field-wrapper' => $template_key)
		));

		return $this;
	}

	/**
	 * No-op when called on the section builder directly.
	 *
	 * @return static
	 */
	public function end_field(): static {
		return $this;
	}

	/**
	 * Not valid in section context - throws exception.
	 *
	 * @return never
	 * @throws \RuntimeException Always throws.
	 */
	public function end_fieldset(): never {
		throw new \RuntimeException('Cannot call end_fieldset() from section context. You are not inside a fieldset.');
	}

	/**
	 * Not valid in section context - throws exception.
	 *
	 * @return never
	 * @throws \RuntimeException Always throws.
	 */
	public function end_group(): never {
		throw new \RuntimeException('Cannot call end_group() from section context. You are not inside a group.');
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
			$this->container_id,
			$this->section_id,
			$fieldset_id,
			$heading,
			$description_cb,
			$this->updateFn,
			$args ?? array()
		);
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
			$this->container_id,
			$this->section_id,
			$group_id,
			$heading,
			$description_cb,
			$this->updateFn,
			$args ?? array()
		);
	}

	/**
	 * Set the default group template for all groups in this section.
	 *
	 * @param string $template_key The template key to use for group containers.
	 *
	 * @return static
	 * @throws \InvalidArgumentException If template key is empty.
	 */
	public function group_template(string $template_key): static {
		if (trim($template_key) === '') {
			throw new \InvalidArgumentException('Template key cannot be empty');
		}

		($this->updateFn)('template_override', array(
			'element_type' => 'section',
			'element_id'   => $this->section_id,
			'overrides'    => array('group-wrapper' => $template_key)
		));

		return $this;
	}

	/**
	 * Set the default fieldset template for all fieldsets in this section.
	 *
	 * @param string $template_key The template key to use for fieldset containers.
	 *
	 * @return static
	 * @throws \InvalidArgumentException If template key is empty.
	 */
	public function fieldset_template(string $template_key): static {
		if (trim($template_key) === '') {
			throw new \InvalidArgumentException('Template key cannot be empty');
		}

		($this->updateFn)('template_override', array(
			'element_type' => 'section',
			'element_id'   => $this->section_id,
			'overrides'    => array('fieldset-wrapper' => $template_key)
		));

		return $this;
	}

	/**
	 * Set the section template for section container customization.
	 *
	 * @param string $template_key The template key to use for section container.
	 *
	 * @return static
	 * @throws \InvalidArgumentException If template key is empty.
	 */
	public function section_template(string $template_key): static {
		if (trim($template_key) === '') {
			throw new \InvalidArgumentException('Template key cannot be empty');
		}

		($this->updateFn)('template_override', array(
			'element_type' => 'section',
			'element_id'   => $this->section_id,
			'overrides'    => array('section-wrapper' => $template_key)
		));

		return $this;
	}

	/**
	 * End the current section and return to the parent collection builder.
	 *
	 * @return UserSettingsCollectionBuilder
	 */
	public function end_section(): UserSettingsCollectionBuilder {
		return $this->collectionBuilder;
	}

	/**
	 * End the section and collection, returning to UserSettings.
	 *
	 * @return UserSettings
	 */
	public function end_collection(): UserSettings {
		return $this->collectionBuilder->end_collection();
	}

	/**
	 * Fluent shortcut: end all the way back to UserSettings.
	 *
	 * @return UserSettings
	 */
	public function end(): UserSettings {
		return $this->end_collection();
	}

	/**
	 * Start a sibling section on the same collection.
	 *
	 * @param string $section_id The section ID.
	 * @param string $heading The section heading.
	 *
	 * @return UserSettingsSectionBuilder
	 */
	public function section(string $section_id, string $heading = ''): UserSettingsSectionBuilder {
		return $this->collectionBuilder->section($section_id, $heading);
	}

	// =========================================================================
	// Abstract method implementations from traits
	// =========================================================================

	/**
	 * Get the FormsInterface instance.
	 *
	 * @return FormsInterface
	 */
	public function get_forms(): FormsInterface {
		return $this->collectionBuilder->get_settings();
	}

	/**
	 * Get the component builder factory for a given component alias.
	 *
	 * @param string $component The component alias.
	 *
	 * @return callable|null
	 */
	public function get_component_builder_factory(string $component): ?callable {
		return $this->_get_section_component_builder_factory($component);
	}

	/**
	 * Get the component builder factory for a given component alias.
	 *
	 * @param string $component The component alias.
	 *
	 * @return callable|null
	 */
	protected function _get_section_component_builder_factory(string $component): ?callable {
		$component = trim($component);
		if ($component === '') {
			return null;
		}

		if ($this->componentBuilderFactories === null) {
			$forms   = $this->collectionBuilder->get_settings();
			$session = $forms->get_form_session();
			if ($session === null) {
				$this->componentBuilderFactories = array();
			} else {
				$this->componentBuilderFactories = $session->manifest()->builder_factories();
			}
		}

		return $this->componentBuilderFactories[$component] ?? null;
	}

	/**
	 * Create a field proxy for the given component builder.
	 *
	 * @param ComponentBuilderDefinitionInterface $builder The component builder.
	 * @param string $component_alias The component alias.
	 * @param string|null $group_id The group ID (null for section-level fields).
	 * @param string|null $field_template The field template override.
	 * @param array<string,mixed> $component_context The component context.
	 *
	 * @return UserSettingsComponentProxy
	 */
	protected function _create_section_field_proxy(
		ComponentBuilderDefinitionInterface $builder,
		string $component_alias,
		?string $group_id,
		?string $field_template,
		array $component_context
	): UserSettingsComponentProxy {
		return new UserSettingsComponentProxy(
			$builder,
			$this,
			$this->updateFn,
			$this->container_id,
			$this->section_id,
			$component_alias,
			$group_id,
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
		$this->_apply_section_meta_update($key, $value);
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
		return 'section_metadata';
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
		return $this->_build_section_payload();
	}
}
