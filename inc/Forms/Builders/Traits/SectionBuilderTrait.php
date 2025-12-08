<?php
/**
 * SectionBuilderTrait: Shared logic for section builders.
 *
 * This trait provides the core implementation for section builders that contain
 * groups, fieldsets, and fields. It is designed to be used with composition
 * rather than inheritance, allowing context-specific builders to declare
 * concrete return types for IDE support.
 *
 * @package Ran\PluginLib\Forms\Builders\Traits
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders\Traits;

use Ran\PluginLib\Forms\FormsInterface;
use Ran\PluginLib\Forms\Component\Build\ComponentBuilderDefinitionInterface;
use Ran\PluginLib\Forms\Component\Build\ComponentBuilderBase;
use Ran\PluginLib\Forms\Builders\ComponentBuilderProxy;
use Ran\PluginLib\Forms\Builders\GroupBuilder;
use Ran\PluginLib\Forms\Builders\FieldsetBuilder;
use Ran\PluginLib\Forms\Builders\FieldsetBuilderInterface;
use Ran\PluginLib\Forms\Builders\GroupBuilderInterface;
use Ran\PluginLib\Forms\Builders\GenericElementBuilder;

/**
 * Shared implementation for section builders.
 *
 * Classes using this trait must:
 * 1. Implement the abstract methods for context-specific behavior
 * 2. Call `_init_section()` in their constructor
 * 3. Use `BuilderImmediateUpdateTrait` for meta updates
 */
trait SectionBuilderTrait {
	protected string $container_id;
	protected string $section_id;
	protected string $heading;
	protected string $description = '';

	/** @var callable */
	protected $updateFn;

	/** @var callable|null */
	private $before;

	/** @var callable|null */
	private $after;

	private ?int $order;
	private string $style = '';

	/** @var array<string, callable>|null */
	private ?array $componentBuilderFactories = null;

	/**
	 * Initialize the section with common properties.
	 *
	 * Call this from the constructor of classes using this trait.
	 *
	 * @param string $container_id The container identifier.
	 * @param string $section_id The section identifier.
	 * @param string $heading The section heading.
	 * @param callable $updateFn The update callback.
	 * @param callable|null $before Before callback.
	 * @param callable|null $after After callback.
	 * @param int|null $order Display order.
	 */
	protected function _init_section(
		string $container_id,
		string $section_id,
		string $heading,
		callable $updateFn,
		?callable $before = null,
		?callable $after = null,
		?int $order = null
	): void {
		$this->container_id = $container_id;
		$this->section_id   = $section_id;
		$this->heading      = $heading;
		$this->updateFn     = $updateFn;
		$this->before       = $before;
		$this->after        = $after;
		$this->order        = $order;
		$this->style        = '';

		$this->_emit_section_metadata();
	}

	/**
	 * Set the section heading.
	 *
	 * @param string $heading The section heading.
	 *
	 * @return static
	 */
	public function heading(string $heading): static {
		$this->_update_meta('heading', $heading);
		return $this;
	}

	/**
	 * Set the section description.
	 *
	 * @param string|callable $description A string or callback returning the description.
	 *
	 * @return static
	 */
	public function description(string|callable $description): static {
		$this->_update_meta('description', $description);
		return $this;
	}

	/**
	 * Set the section template for section container customization.
	 *
	 * @param string $template_key The template key to use for section container.
	 *
	 * @return static
	 *
	 * @throws \InvalidArgumentException If template key is empty.
	 */
	public function template(string $template_key): static {
		$template_key = trim($template_key);
		if ($template_key === '') {
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
	 * Set the display order.
	 *
	 * @param int|null $order The order.
	 *
	 * @return static
	 */
	public function order(?int $order): static {
		$this->_update_meta('order', $order);
		return $this;
	}

	/**
	 * Set the visual style for this section.
	 *
	 * @param string|callable $style The style identifier or resolver returning a string.
	 *
	 * @return static
	 */
	public function style(string|callable $style): static {
		$normalized = $style === '' ? '' : $this->_resolve_section_style_arg($style);
		$this->_update_meta('style', $normalized);
		return $this;
	}

	/**
	 * Set a callback to render content before this section.
	 *
	 * @param callable|null $before The before callback.
	 *
	 * @return static
	 */
	public function before(?callable $before): static {
		$this->_update_meta('before', $before);
		return $this;
	}

	/**
	 * Set a callback to render content after this section.
	 *
	 * @param callable $after The after callback.
	 *
	 * @return static
	 */
	public function after(callable $after): static {
		$this->_update_meta('after', $after);
		return $this;
	}

	/**
	 * Add a field with a component builder.
	 *
	 * Note: Implementing classes should override this method to return their specific
	 * proxy type for IDE support.
	 *
	 * @param string $field_id The field ID.
	 * @param string $label The field label.
	 * @param string $component The component alias.
	 * @param array<string,mixed> $args Optional configuration.
	 *
	 * @return ComponentBuilderProxy The fluent proxy for field configuration (concrete type in implementations).
	 *
	 * @throws \InvalidArgumentException If the component has no registered builder factory.
	 */
	protected function _add_section_field(string $field_id, string $label, string $component, array $args = array()): mixed {
		$component_context = $args['context']        ?? $args['component_context'] ?? array();
		$order             = $args['order']          ?? null;
		$field_template    = $args['field_template'] ?? null;
		$before            = $args['before']         ?? null;
		$after             = $args['after']          ?? null;

		$component = trim($component);
		if ($component === '') {
			throw new \InvalidArgumentException(sprintf('Field "%s" requires a component alias.', $field_id));
		}
		if (!is_array($component_context)) {
			throw new \InvalidArgumentException(sprintf('Field "%s" must provide an array component_context.', $field_id));
		}

		$factory = $this->_get_section_component_builder_factory($component);
		if (!($factory instanceof \Closure || is_callable($factory))) {
			throw new \InvalidArgumentException(sprintf(
				'Field "%s" uses component "%s" which has no registered builder factory.',
				$field_id,
				$component
			));
		}

		$builder = $factory($field_id, $label);
		if (!$builder instanceof ComponentBuilderDefinitionInterface) {
			throw new \UnexpectedValueException(sprintf('Builder factory for "%s" must return ComponentBuilderDefinitionInterface.', $component));
		}

		$proxy = $this->_create_section_field_proxy(
			$builder,
			$component,
			null,
			$field_template,
			$component_context
		);

		if ($order !== null) {
			$proxy->order((int) $order);
		}

		if ($field_template !== null) {
			$proxy->template($field_template);
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
	 * Add raw HTML content to the section.
	 *
	 * This is an escape hatch for injecting arbitrary markup into the form.
	 * The content is rendered inline in declaration order, without any wrapper.
	 *
	 * @param string|callable $content HTML string or callable that returns HTML.
	 *                                 Callable receives array with 'container_id', 'section_id', 'values'.
	 * @return static
	 */
	protected function _add_section_html(string|callable $content): static {
		// Generate a unique ID for this HTML block
		$html_id = '_html_' . uniqid();

		// Emit as a special field entry with _raw_html component marker
		($this->updateFn)('field', array(
			'container_id' => $this->container_id,
			'section_id'   => $this->section_id,
			'field_data'   => array(
				'id'                => $html_id,
				'label'             => '',
				'component'         => '_raw_html',
				'component_context' => array(
					'content' => $content,
				),
				'order' => null,
			),
		));

		return $this;
	}

	/**
	 * Add a non-input element to the section.
	 *
	 * @param string $element_id The element identifier.
	 * @param string $label The element label/text.
	 * @param string $component The component alias.
	 * @param array<string,mixed> $args Optional arguments.
	 *
	 * @return GenericElementBuilder The element builder for configuration.
	 *
	 * @throws \InvalidArgumentException If the component has no registered builder factory.
	 */
	protected function _add_section_element(string $element_id, string $label, string $component, array $args = array()): GenericElementBuilder {
		$component_context = $args['context']          ?? $args['component_context'] ?? array();
		$element_template  = $args['element_template'] ?? null;
		$before            = $args['before']           ?? null;
		$after             = $args['after']            ?? null;

		$component = trim($component);
		if ($component === '') {
			throw new \InvalidArgumentException(sprintf('Element "%s" requires a component alias.', $element_id));
		}
		if (!is_array($component_context)) {
			throw new \InvalidArgumentException(sprintf('Element "%s" must provide an array component_context.', $element_id));
		}

		$factory = $this->_get_section_component_builder_factory($component);
		if (!($factory instanceof \Closure || is_callable($factory))) {
			throw new \InvalidArgumentException(sprintf(
				'Element "%s" uses component "%s" which has no registered builder factory.',
				$element_id,
				$component
			));
		}

		$builder = $factory($element_id, $label);
		if (!$builder instanceof ComponentBuilderBase) {
			throw new \UnexpectedValueException(sprintf('Builder factory for "%s" must return ComponentBuilderBase.', $component));
		}

		$proxy = new GenericElementBuilder(
			$builder,
			$this,
			$this->updateFn,
			$this->container_id,
			$this->section_id,
			$component,
			null, // No group_id for section-level
			$element_template,
			$component_context
		);

		if (!empty($component_context)) {
			$proxy->apply_context($component_context);
		}

		if ($element_template !== null) {
			$proxy->template($element_template);
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
	 * Apply a meta update to local state.
	 *
	 * @param string $key The meta key.
	 * @param mixed $value The new value.
	 */
	protected function _apply_section_meta_update(string $key, mixed $value): void {
		switch ($key) {
			case 'heading':
				$this->heading = (string) $value;
				break;
			case 'description':
				$this->description = (string) $value;
				break;
			case 'before':
				$this->before = $value;
				break;
			case 'after':
				$this->after = $value;
				break;
			case 'order':
				$this->order = $value === null ? null : (int) $value;
				break;
			case 'style':
				$this->style = (string) $value;
				break;
		}

		$this->_emit_section_metadata();
	}

	/**
	 * Build the metadata payload for this section.
	 *
	 * @return array<string,mixed>
	 */
	protected function _build_section_payload(): array {
		return array(
			'container_id' => $this->container_id,
			'section_id'   => $this->section_id,
			'group_data'   => array(
				'heading'     => $this->heading,
				'description' => $this->description,
				'before'      => $this->before,
				'after'       => $this->after,
				'order'       => $this->order,
				'style'       => $this->style,
			),
		);
	}

	/**
	 * Normalize a style argument to a trimmed string.
	 *
	 * @param string|callable $style Style value or resolver callback returning a string.
	 *
	 * @return string
	 *
	 * @throws \InvalidArgumentException When the resolved value is not a string.
	 */
	protected function _resolve_section_style_arg(string|callable $style): string {
		$resolved = is_callable($style) ? $style() : $style;
		if (!is_string($resolved)) {
			throw new \InvalidArgumentException('Section style callback must return a string.');
		}
		return trim($resolved);
	}

	/**
	 * Emit section metadata using the update callback.
	 */
	protected function _emit_section_metadata(): void {
		($this->updateFn)('section_metadata', $this->_build_section_payload());
	}

	// =========================================================================
	// Abstract methods - must be implemented by classes using this trait
	// =========================================================================

	/**
	 * Get the FormsInterface instance.
	 *
	 * @return FormsInterface
	 */
	abstract public function __get_forms(): FormsInterface;

	/**
	 * Get the component builder factory for a given component alias.
	 *
	 * @param string $component The component alias.
	 *
	 * @return callable|null The factory or null if not found.
	 */
	abstract protected function _get_section_component_builder_factory(string $component): ?callable;

	/**
	 * Create a field proxy for the given component builder.
	 *
	 * Implementing classes should return their specific proxy type.
	 *
	 * @param ComponentBuilderDefinitionInterface $builder The component builder.
	 * @param string $component_alias The component alias.
	 * @param string|null $group_id The group ID (null for section-level fields).
	 * @param string|null $field_template The field template override.
	 * @param array<string,mixed> $component_context The component context.
	 *
	 * @return ComponentBuilderProxy The proxy instance (concrete type in implementations).
	 */
	abstract protected function _create_section_field_proxy(
		ComponentBuilderDefinitionInterface $builder,
		string $component_alias,
		?string $group_id,
		?string $field_template,
		array $component_context
	): mixed;

	/**
	 * Update a meta value (from BuilderImmediateUpdateTrait pattern).
	 *
	 * @param string $key The meta key.
	 * @param mixed $value The new value.
	 */
	abstract protected function _update_meta(string $key, mixed $value): void;
}
