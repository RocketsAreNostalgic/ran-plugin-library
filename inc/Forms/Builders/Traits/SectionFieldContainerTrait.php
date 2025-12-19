<?php
/**
 * SectionFieldContainerTrait: Shared logic for section-scoped field container builders.
 *
 * This trait provides the core implementation for containers (fieldsets, groups) that
 * live within a section and can contain fields. It is designed to be used with
 * composition rather than inheritance, allowing context-specific builders to declare
 * concrete return types for IDE support.
 *
 * @package Ran\PluginLib\Forms\Builders\Traits
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders\Traits;

use Ran\PluginLib\Forms\FormsInterface;
use Ran\PluginLib\Forms\Component\Build\ComponentBuilderDefinitionInterface;
use Ran\PluginLib\Forms\Builders\ComponentBuilderProxy;
use Ran\PluginLib\Forms\Builders\SectionBuilderInterface;

/**
 * Shared implementation for containers that live directly under a SectionBuilder.
 *
 * Classes using this trait must:
 * 1. Implement the abstract methods for context-specific behavior
 * 2. Call `_init_container()` in their constructor
 * 3. Use `BuilderImmediateUpdateTrait` for meta updates
 */
trait SectionFieldContainerTrait {
	protected string $container_id;
	protected string $section_id;
	protected string $group_id;
	protected string $heading;

	/** @var string|callable|null */
	protected $description_cb;

	/** @var callable */
	protected $updateFn;

	/** @var callable|null */
	protected $before;

	/** @var callable|null */
	protected $after;

	protected ?int $order;
	protected mixed $style = '';

	/**
	 * Default field template for fields added to this container.
	 * If set, fields without an explicit field_template will use this.
	 */
	protected ?string $default_field_template = null;

	/**
	 * Initialize the container with common properties.
	 *
	 * Call this from the constructor of classes using this trait.
	 *
	 * @param string $container_id The container identifier.
	 * @param string $section_id The section identifier.
	 * @param string $group_id The group/fieldset identifier.
	 * @param string $heading The container heading.
	 * @param string|callable|null $description_cb Description or callback.
	 * @param callable $updateFn The update callback.
	 * @param array<string,mixed> $args Optional arguments (before, after, order, style).
	 */
	protected function _init_container(
		string $container_id,
		string $section_id,
		string $group_id,
		string $heading,
		string|callable|null $description_cb,
		callable $updateFn,
		array $args = array()
	): void {
		$this->container_id   = $container_id;
		$this->section_id     = $section_id;
		$this->group_id       = $group_id;
		$this->heading        = $heading;
		$this->description_cb = $description_cb ?? null;
		$this->updateFn       = $updateFn;
		$this->before         = $args['before'] ?? null;
		$this->after          = $args['after']  ?? null;
		$order                = $args['order']  ?? null;
		$this->order          = $order === null ? null : (int) $order;

		$this->style = $args['style'] ?? '';

		$this->_emit_container_metadata();
	}

	/**
	 * Set the container heading.
	 *
	 * @param string $heading The heading text.
	 *
	 * @return static
	 */
	public function heading(string $heading): static {
		$this->_update_meta('heading', $heading);
		return $this;
	}

	/**
	 * Set the optional container description.
	 *
	 * @param string|callable $description Description text or callback.
	 *
	 * @return static
	 */
	public function description(string|callable $description): static {
		$this->_update_meta('description', $description);
		return $this;
	}

	/**
	 * Set the template for this container.
	 *
	 * @param string|callable $template_key The template key.
	 *
	 * @return static
	 *
	 * @throws \InvalidArgumentException If template key is empty.
	 */
	public function template(string|callable $template_key): static {
		if (is_callable($template_key)) {
			($this->updateFn)('template_override', array(
				'element_type' => 'group',
				'element_id'   => $this->group_id,
				'overrides'    => array('group-wrapper' => $template_key)
			));
			return $this;
		}

		$template_key = trim($template_key);
		if ($template_key === '') {
			throw new \InvalidArgumentException('Template key cannot be empty');
		}

		($this->updateFn)('template_override', array(
			'element_type' => 'group',
			'element_id'   => $this->group_id,
			'overrides'    => array('group-wrapper' => $template_key)
		));

		return $this;
	}

	/**
	 * Set the display order for this container.
	 *
	 * @param int|null $order The order value.
	 *
	 * @return static
	 */
	public function order(?int $order): static {
		$this->_update_meta('order', $order);
		return $this;
	}

	/**
	 * Set the visual style for this container.
	 *
	 * @param string|callable $style The style identifier or a resolver returning a string.
	 *
	 * @return static
	 */
	public function style(string|callable $style): static {
		if ($style === '') {
			$this->_update_meta('style', '');
			return $this;
		}
		$this->_update_meta('style', $style);
		return $this;
	}

	/**
	 * Set a callback to render content before this container.
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
	 * Set a callback to render content after this container.
	 *
	 * @param callable|null $after The after callback.
	 *
	 * @return static
	 */
	public function after(?callable $after): static {
		$this->_update_meta('after', $after);
		return $this;
	}

	/**
	 * Add a field with a component builder.
	 *
	 * Use this for components that have registered builder factories (e.g., fields.input,
	 * fields.select). Throws if the component has no registered builder factory.
	 *
	 * Note: Implementing classes should override this method to return their specific
	 * proxy type for IDE support.
	 *
	 * @param string $field_id The field identifier.
	 * @param string $label The field label.
	 * @param string $component The component alias (must have a registered builder factory).
	 * @param array<string,mixed> $args Optional arguments for the component.
	 *
	 * @return ComponentBuilderProxy The fluent proxy for field configuration (concrete type in implementations).
	 *
	 * @throws \InvalidArgumentException If the component has no registered builder factory.
	 */
	protected function _add_field(string $field_id, string $label, string $component, array $args = array()): mixed {
		$component_context = $args['context']        ?? $args['component_context'] ?? array();
		$order             = $args['order']          ?? null;
		$field_template    = $args['field_template'] ?? $this->default_field_template;
		$before            = $args['before']         ?? null;
		$after             = $args['after']          ?? null;

		$component = trim($component);
		if ($component === '') {
			throw new \InvalidArgumentException(sprintf('Field "%s" requires a component alias.', $field_id));
		}
		if (!is_array($component_context)) {
			throw new \InvalidArgumentException(sprintf('Field "%s" must provide an array component_context.', $field_id));
		}

		$factory = $this->__get_component_builder_factory($component);
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

		$proxy = $this->_create_field_proxy(
			$builder,
			$component,
			null,
			$component_context
		);

		if (!empty($component_context)) {
			$proxy->apply_context($component_context);
		}

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
	 * Apply a meta update to local state.
	 *
	 * @param string $key The meta key.
	 * @param mixed $value The new value.
	 */
	protected function _apply_container_meta_update(string $key, mixed $value): void {
		switch ($key) {
			case 'heading':
				$this->heading = (string) $value;
				break;
			case 'description':
				if ($value === null) {
					$this->description_cb = null;
					break;
				}
				$this->description_cb = is_callable($value) ? $value : (string) $value;
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
				$this->style = $value;
				break;
		}

		$this->_emit_container_metadata();
	}

	/**
	 * Build the metadata payload for this container.
	 *
	 * @return array{container_id:string,section_id:string,group_id:string,group_data:array<string,mixed>}
	 */
	protected function _build_container_payload(): array {
		// Render the callback
		$description = $this->description_cb;
		if ($description === null) {
			$description = '';
		}

		$group_data = array(
			'id'          => $this->group_id,
			'type'        => $this->_get_container_type(),
			'heading'     => $this->heading,
			'description' => $description,
			'order'       => $this->order,
			'style'       => $this->style,
		);

		// Only include before/after if they're set (avoid overwriting with null)
		if ($this->before !== null) {
			$group_data['before'] = $this->before;
		}
		if ($this->after !== null) {
			$group_data['after'] = $this->after;
		}

		return array(
			'container_id' => $this->container_id,
			'section_id'   => $this->section_id,
			'group_id'     => $this->group_id,
			'group_data'   => $group_data,
		);
	}

	/**
	 * Emit container metadata using the update callback.
	 *
	 * Subclasses can override _build_metadata_payload() to extend the payload.
	 */
	protected function _emit_container_metadata(): void {
		($this->updateFn)('group_metadata', $this->_build_metadata_payload());
	}

	/**
	 * Build the metadata payload for emission.
	 *
	 * Override this in subclasses to extend the payload with additional data.
	 *
	 * @return array<string,mixed>
	 */
	protected function _build_metadata_payload(): array {
		return $this->_build_container_payload();
	}

	// =========================================================================
	// Abstract methods - must be implemented by classes using this trait
	// =========================================================================

	/**
	 * Get the container type identifier (e.g., 'group', 'fieldset').
	 *
	 * @return string
	 */
	abstract protected function _get_container_type(): string;

	/**
	 * Get the component builder factory for a given component alias.
	 *
	 * @param string $component The component alias.
	 *
	 * @return callable|null The factory or null if not found.
	 */
	abstract public function __get_component_builder_factory(string $component): ?callable;

	/**
	 * Create a field proxy for the given component builder.
	 *
	 * Implementing classes should return their specific proxy type.
	 *
	 * @param ComponentBuilderDefinitionInterface $builder The component builder.
	 * @param string $component_alias The component alias.
	 * @param string|null $field_template The field template override.
	 * @param array<string,mixed> $component_context The component context.
	 *
	 * @return ComponentBuilderProxy The proxy instance (concrete type in implementations).
	 */
	abstract protected function _create_field_proxy(
		ComponentBuilderDefinitionInterface $builder,
		string $component_alias,
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
