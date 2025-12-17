<?php
/**
 * SectionBuilderBase: Abstract base class for section builders with context injection.
 *
 * Provides shared implementation for section builders, eliminating duplication
 * across context-specific variants (AdminSettingsSectionBuilder, UserSettingsSectionBuilder).
 *
 * @package Ran\PluginLib\Forms\Builders
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders;

use Ran\PluginLib\Forms\FormsInterface;
use Ran\PluginLib\Forms\Component\Build\ComponentBuilderDefinitionInterface;
use Ran\PluginLib\Forms\Builders\Traits\SectionBuilderTrait;

/**
 * Base class for section builders with context injection.
 *
 * Subclasses only need to implement navigation methods that return
 * context-specific types for IDE autocomplete.
 *
 * @template TParent of BuilderRootInterface
 */
abstract class SectionBuilderBase implements SectionBuilderInterface {
	use SectionBuilderTrait;
	use BuilderImmediateUpdateTrait;

	/**
	 * The parent root builder.
	 *
	 * @var TParent
	 */
	protected object $rootBuilder;

	/**
	 * The builder context providing access to shared dependencies.
	 */
	protected BuilderContextInterface $context;

	/**
	 * @param TParent $rootBuilder The parent root builder.
	 * @param BuilderContextInterface $context The builder context.
	 * @param string $section_id The section ID.
	 * @param string $heading The section heading.
	 * @param callable|null $before Optional callback invoked before rendering.
	 * @param callable|null $after Optional callback invoked after rendering.
	 * @param int|null $order Optional section order.
	 */
	public function __construct(
		object $rootBuilder,
		BuilderContextInterface $context,
		string $section_id,
		string $heading,
		?callable $before = null,
		?callable $after = null,
		?int $order = null
	) {
		$this->rootBuilder = $rootBuilder;
		$this->context     = $context;

		// Initialize section (from SectionBuilderTrait)
		$this->_init_section(
			$context->get_container_id(),
			$section_id,
			$heading,
			$context->get_update_callback(),
			$before,
			$after,
			$order
		);
	}

	/**
	 * Get the FormsInterface instance.
	 *
	 * @return FormsInterface
	 */
	public function get_settings(): FormsInterface {
		return $this->context->get_forms();
	}

	/**
	 * Get the FormsInterface instance.
	 *
	 * @internal
	 *
	 * @return FormsInterface
	 */
	public function __get_forms(): FormsInterface {
		return $this->context->get_forms();
	}

	/**
	 * Get the builder context for child builders.
	 *
	 * @return BuilderContextInterface
	 */
	public function __get_context(): BuilderContextInterface {
		return $this->context;
	}

	// =========================================================================
	// Shared implementations (no longer abstract in subclasses)
	// =========================================================================

	/**
	 * Get the component builder factory for a given component alias.
	 *
	 * @internal
	 *
	 * @param string $component The component alias.
	 *
	 * @return callable|null
	 */
	public function __get_component_builder_factory(string $component): ?callable {
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
		return $this->context->get_component_builder_factory($component);
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
	 * @return GenericFieldBuilder<static>
	 */
	protected function _create_section_field_proxy(
		ComponentBuilderDefinitionInterface $builder,
		string $component_alias,
		?string $group_id,
		?string $field_template,
		array $component_context
	): GenericFieldBuilder {
		return new GenericFieldBuilder(
			$builder,
			$this,
			$this->context->get_update_callback(),
			$this->context->get_container_id(),
			$this->section_id,
			$component_alias,
			$group_id,
			$field_template,
			$component_context
		);
	}

	// =========================================================================
	// Template override methods (shared)
	// =========================================================================

	/**
	 * Set the default template for all fields in this section.
	 *
	 * @param string|callable $template_key The template key to use for field wrappers.
	 *
	 * @return static
	 * @throws \InvalidArgumentException If template key is empty.
	 */
	public function field_templates(string|callable $template_key): static {
		if (is_callable($template_key)) {
			($this->updateFn)('template_override', array(
				'element_type' => 'section',
				'element_id'   => $this->section_id,
				'overrides'    => array('field-wrapper' => $template_key)
			));
			return $this;
		}

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
	 * Set the default template for all fieldsets in this section.
	 *
	 * @param string|callable $template_key The template key to use for fieldset containers.
	 *
	 * @return static
	 * @throws \InvalidArgumentException If template key is empty.
	 */
	public function fieldset_templates(string|callable $template_key): static {
		if (is_callable($template_key)) {
			($this->updateFn)('template_override', array(
				'element_type' => 'section',
				'element_id'   => $this->section_id,
				'overrides'    => array('fieldset-wrapper' => $template_key)
			));
			return $this;
		}

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
	 * Set the default template for all groups in this section.
	 *
	 * @param string|callable $template_key The template key to use for group containers.
	 *
	 * @return static
	 * @throws \InvalidArgumentException If template key is empty.
	 */
	public function group_templates(string|callable $template_key): static {
		if (is_callable($template_key)) {
			($this->updateFn)('template_override', array(
				'element_type' => 'section',
				'element_id'   => $this->section_id,
				'overrides'    => array('group-wrapper' => $template_key)
			));
			return $this;
		}

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
		return $this->context->get_update_callback();
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

	// =========================================================================
	// Abstract methods for context-specific behavior
	// =========================================================================

	/**
	 * Add a field with a component builder to this section.
	 *
	 * Subclasses should override to provide specific return type annotation.
	 *
	 * @param string $field_id The field identifier.
	 * @param string $label The field label.
	 * @param string $component The component alias.
	 * @param array<string,mixed> $args Optional arguments.
	 *
	 * @return GenericFieldBuilder<static>
	 */
	public function field(string $field_id, string $label, string $component, array $args = array()): GenericFieldBuilder {
		$proxy = $this->_add_section_field($field_id, $label, $component, $args);
		if (!$proxy instanceof GenericFieldBuilder) {
			throw new \RuntimeException('Unexpected proxy type from _add_section_field()');
		}
		return $proxy;
	}

	/**
	 * Add raw HTML content to the section.
	 *
	 * This is an escape hatch for injecting arbitrary markup into the form.
	 * The content is rendered inline in declaration order, without any wrapper.
	 *
	 * @param string|callable $content HTML string or callable that returns HTML.
	 *                                 Callable receives array with 'container_id', 'section_id', 'values'.
	 * @return static
	 */
	public function html(string|callable $content): static {
		return $this->_add_section_html($content);
	}

	/**
	 * Add a horizontal rule to the section.
	 *
	 * Returns a fluent builder for configuring the hr element.
	 *
	 * @return HrBuilder<static> The hr builder for configuration.
	 */
	public function hr(): HrBuilder {
		return new HrBuilder(
			$this,
			$this->updateFn,
			$this->container_id,
			$this->section_id,
			null // No group_id for section-level
		);
	}

	/**
	 * Add a non-input element (button, link, etc.) to the section.
	 *
	 * Unlike field(), element() is for components that don't submit form data.
	 * The returned builder provides styling methods but not input-specific ones.
	 *
	 * @param string $element_id The element identifier.
	 * @param string $label The element label/text.
	 * @param string $component The component alias (e.g., 'elements.button', 'elements.button-link').
	 * @param array<string,mixed> $args Optional arguments including 'context' for component-specific config.
	 *
	 * @return GenericElementBuilder<static> The element builder for configuration.
	 */
	public function element(string $element_id, string $label, string $component, array $args = array()): GenericElementBuilder {
		return $this->_add_section_element($element_id, $label, $component, $args);
	}

	/**
	 * End the section and return to the parent root builder.
	 *
	 * Subclasses must override to return the concrete parent type.
	 *
	 * @return TParent
	 */
	abstract public function end_section(): mixed;

	/**
	 * Begin configuring a grouped set of fields within this section.
	 *
	 * Subclasses must override to return the concrete group builder type.
	 *
	 * @param string $group_id The group ID.
	 * @param string $heading The group heading.
	 * @param string|callable|null $description_cb The description callback.
	 * @param array<string,mixed>|null $args Optional configuration.
	 *
	 * @return GroupBuilderInterface
	 */
	abstract public function group(string $group_id, string $heading = '', string|callable|null $description_cb = null, ?array $args = null): GroupBuilderInterface;

	/**
	 * Begin configuring a semantic fieldset grouping within this section.
	 *
	 * Subclasses must override to return the concrete fieldset builder type.
	 *
	 * @param string $fieldset_id The fieldset ID.
	 * @param string $heading The fieldset heading.
	 * @param string|callable|null $description_cb The description callback.
	 * @param array<string,mixed>|null $args Optional configuration.
	 *
	 * @return FieldsetBuilderInterface
	 */
	abstract public function fieldset(string $fieldset_id, string $heading = '', string|callable|null $description_cb = null, ?array $args = null): FieldsetBuilderInterface;
}
