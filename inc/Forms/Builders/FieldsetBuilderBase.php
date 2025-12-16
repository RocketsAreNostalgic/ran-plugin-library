<?php
/**
 * FieldsetBuilderBase: Abstract base class for fieldset builders with context injection.
 *
 * Provides shared implementation for fieldset builders, eliminating duplication
 * across context-specific variants (AdminSettingsFieldsetBuilder, UserSettingsFieldsetBuilder).
 *
 * @package Ran\PluginLib\Forms\Builders
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders;

use Ran\PluginLib\Forms\FormsInterface;
use Ran\PluginLib\Forms\Component\Build\ComponentBuilderDefinitionInterface;
use Ran\PluginLib\Forms\Component\Build\ComponentBuilderInputBase;
use Ran\PluginLib\Forms\Builders\Traits\SectionFieldContainerTrait;
use Ran\PluginLib\Forms\Builders\Traits\FieldsetBuilderTrait;

/**
 * Base class for fieldset builders with context injection.
 *
 * Subclasses only need to implement navigation methods that return
 * context-specific types for IDE autocomplete.
 *
 * @template TParent of SectionBuilderInterface
 */
abstract class FieldsetBuilderBase implements FieldsetBuilderInterface {
	use SectionFieldContainerTrait;
	use FieldsetBuilderTrait;
	use BuilderImmediateUpdateTrait;

	/**
	 * The parent section builder.
	 *
	 * @var TParent
	 */
	protected object $sectionBuilder;

	/**
	 * The builder context providing access to shared dependencies.
	 */
	protected BuilderContextInterface $context;

	/**
	 * @param TParent $sectionBuilder The parent section builder.
	 * @param BuilderContextInterface $context The builder context.
	 * @param string $section_id The section ID.
	 * @param string $fieldset_id The fieldset ID.
	 * @param string $heading The fieldset heading.
	 * @param string|callable|null $description_cb The description callback.
	 * @param array<string,mixed> $args Optional arguments.
	 */
	public function __construct(
		object $sectionBuilder,
		BuilderContextInterface $context,
		string $section_id,
		string $fieldset_id,
		string $heading,
		string|callable|null $description_cb = null,
		array $args = array()
	) {
		$this->sectionBuilder = $sectionBuilder;
		$this->context        = $context;

		// Initialize container (from SectionFieldContainerTrait)
		$this->_init_container(
			$context->get_container_id(),
			$section_id,
			$fieldset_id,
			$heading,
			$description_cb,
			$context->get_update_callback(),
			$args
		);

		// Initialize fieldset-specific properties (from FieldsetBuilderTrait)
		$this->_init_fieldset($args);
	}

	/**
	 * Get the FormsInterface instance.
	 *
	 * @return FormsInterface
	 */
	public function get_settings(): FormsInterface {
		return $this->context->get_forms();
	}

	// =========================================================================
	// Shared implementations (no longer abstract in subclasses)
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
	public function __get_component_builder_factory(string $component): ?callable {
		return $this->context->get_component_builder_factory($component);
	}

	/**
	 * Create a field proxy for the given component builder.
	 *
	 * @param ComponentBuilderDefinitionInterface $builder The component builder.
	 * @param string $component_alias The component alias.
	 * @param string|null $field_template The field template override.
	 * @param array<string,mixed> $component_context The component context.
	 *
	 * @return GenericFieldBuilder<static>
	 */
	protected function _create_field_proxy(
		ComponentBuilderDefinitionInterface $builder,
		string $component_alias,
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
		return $this->context->get_update_callback();
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
		return $this->_build_metadata_payload();
	}

	/**
	 * Build the metadata payload for emission.
	 *
	 * Extends the base container payload with fieldset-specific data.
	 *
	 * @return array<string,mixed>
	 */
	protected function _build_metadata_payload(): array {
		$payload = $this->_build_container_payload();
		return $this->_extend_fieldset_payload($payload);
	}

	// =========================================================================
	// Abstract methods for context-specific behavior
	// =========================================================================

	/**
	 * Add a field with a component builder to this fieldset.
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
		$proxy = $this->_add_field($field_id, $label, $component, $args);
		if (!$proxy instanceof GenericFieldBuilder) {
			throw new \RuntimeException('Unexpected proxy type from _add_field()');
		}
		return $proxy;
	}

	/**
	 * Add raw HTML content to the fieldset.
	 *
	 * This is an escape hatch for injecting arbitrary markup into the form.
	 * The content is rendered inline in declaration order, without any wrapper.
	 *
	 * @param string|callable $content HTML string or callable that returns HTML.
	 *                                 Callable receives array with 'container_id', 'section_id', 'fieldset_id', 'values'.
	 * @return static
	 */
	public function html(string|callable $content): static {
		// Generate a unique ID for this HTML block
		$html_id = '_html_' . uniqid();

		// Emit as a special field entry with _raw_html component marker
		($this->updateFn)('group_field', array(
			'container_id' => $this->container_id,
			'section_id'   => $this->section_id,
			'group_id'     => $this->group_id,
			'field_data'   => array(
				'id'                => $html_id,
				'label'             => '',
				'component'         => '_raw_html',
				'is_element'        => true,
				'component_context' => array(
					'content' => $content,
				),
				'order' => null,
			),
		));

		return $this;
	}

	/**
	 * Add a horizontal rule to the fieldset.
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
			$this->group_id
		);
	}

	/**
	 * Add a non-input element (button, link, etc.) to the fieldset.
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
		$component_context = $args['context']          ?? $args['component_context'] ?? array();
		$element_template  = $args['element_template'] ?? null;
		$before            = $args['before']           ?? null;
		$after             = $args['after']            ?? null;

		$component = trim($component);
		if ($component === '') {
			throw new \InvalidArgumentException(sprintf('Element "%s" requires a component alias.', $element_id));
		}

		$factory = $this->context->get_component_builder_factory($component);
		if (!($factory instanceof \Closure || is_callable($factory))) {
			throw new \InvalidArgumentException(sprintf(
				'Element "%s" uses component "%s" which has no registered builder factory.',
				$element_id,
				$component
			));
		}

		$builder = $factory($element_id, $label);
		$session = $this->context->get_forms()->get_form_session();
		if ($session !== null) {
			$validatorFactories = $session->manifest()->validator_factories();
			if (isset($validatorFactories[$component])) {
				throw new \InvalidArgumentException(sprintf(
					'Element "%s" uses component "%s" which has a validator and must be registered via field() not element().',
					$element_id,
					$component
				));
			}
		}
		if ($builder instanceof ComponentBuilderInputBase) {
			throw new \InvalidArgumentException(sprintf(
				'Element "%s" uses component "%s" which is an input builder and must be registered via field() not element().',
				$element_id,
				$component
			));
		}

		$proxy = new GenericElementBuilder(
			$builder,
			$this,
			$this->updateFn,
			$this->container_id,
			$this->section_id,
			$component,
			$this->group_id,
			$element_template,
			$component_context
		);

		if (!empty($component_context)) {
			$proxy->apply_context($component_context);
		}

		if (is_callable($before)) {
			$proxy->before($before);
		}
		if (is_callable($after)) {
			$proxy->after($after);
		}

		return $proxy;
	}

	/**
	 * End the fieldset and return to the parent section builder.
	 *
	 * Subclasses must override to return the concrete parent type.
	 *
	 * @return TParent
	 */
	abstract public function end_fieldset(): mixed;

	/**
	 * Define a sibling fieldset within this section.
	 *
	 * Subclasses must override to return the concrete fieldset type.
	 *
	 * @param string $fieldset_id The fieldset ID.
	 * @param string $heading The fieldset heading.
	 * @param string|callable|null $description_cb The fieldset description.
	 * @param array<string,mixed>|null $args Optional configuration.
	 *
	 * @return FieldsetBuilderInterface
	 */
	abstract public function fieldset(string $fieldset_id, string $heading = '', string|callable|null $description_cb = null, ?array $args = null): FieldsetBuilderInterface;
}
