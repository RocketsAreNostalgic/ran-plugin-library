<?php
/**
 * GenericElementBuilder: Fluent builder proxy for non-input element components.
 *
 * Similar to GenericFieldBuilder but without input-specific methods like
 * name(), required(), disabled(), value(). Used for buttons, links, and
 * other non-form-field elements.
 *
 * @package Ran\PluginLib\Forms\Builders
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders;

use Ran\PluginLib\Forms\Component\Build\ComponentBuilderBase;
use BadMethodCallException;

/**
 * Generic element builder that preserves parent type for IDE autocomplete.
 *
 * Unlike GenericFieldBuilder, this does NOT implement ComponentBuilderInterface
 * since elements don't need input-specific methods like required(), disabled(), etc.
 *
 * @template TParent of object
 */
class GenericElementBuilder {
	/** @var ComponentBuilderBase */
	private ComponentBuilderBase $builder;

	/**
	 * The parent builder to return to after element configuration.
	 *
	 * @var TParent
	 */
	private object $parent;

	/** @var callable */
	private $updateFn;

	private string $container_id;
	private string $section_id;
	private string $component_alias;
	private ?string $group_id;
	/** @var string|callable|null */
	private $element_template;

	/** @var array<string,mixed> */
	private array $pending_context;

	private string $element_id;

	/** @var callable|null */
	private $before_callback = null;

	/** @var callable|null */
	private $after_callback = null;

	private mixed $style = '';

	/**
	 * Constructor.
	 *
	 * @param ComponentBuilderBase $builder The underlying component builder.
	 * @param TParent $parent The parent builder to return to.
	 * @param callable $updateFn The update callback for immediate data flow.
	 * @param string $container_id The container (collection/page) ID.
	 * @param string $section_id The section ID.
	 * @param string $component_alias The component alias.
	 * @param string|null $group_id The group ID (null for section-level elements).
	 * @param string|null $element_template The element template override.
	 * @param array<string,mixed> $pending_context Additional context for the component.
	 */
	public function __construct(
		ComponentBuilderBase $builder,
		object $parent,
		callable $updateFn,
		string $container_id,
		string $section_id,
		string $component_alias,
		?string $group_id = null,
		?string $element_template = null,
		array $pending_context = array()
	) {
		$this->builder          = $builder;
		$this->parent           = $parent;
		$this->updateFn         = $updateFn;
		$this->container_id     = $container_id;
		$this->section_id       = $section_id;
		$this->component_alias  = $component_alias;
		$this->group_id         = $group_id;
		$this->element_template = $element_template;
		$this->pending_context  = $pending_context;

		$this->element_id = $this->builder->to_array()['id'] ?? '';
		$this->_emit();
	}

	/**
	 * Set the template override for this element.
	 *
	 * @param string|callable $template_key The template key.
	 * @return static
	 */
	public function template(string|callable $template_key): static {
		if (is_callable($template_key)) {
			$this->element_template = $template_key;
			return $this;
		}

		$template = trim($template_key);
		if ($template !== '') {
			$this->element_template = $template;
		}
		return $this;
	}

	/**
	 * Set the visual style (CSS classes) for the element.
	 *
	 * @param string $style CSS class name(s).
	 * @return static
	 */
	public function style(string|callable $style): static {
		if ($style === '') {
			$this->style = '';
			$this->_emit();
			return $this;
		}

		if (is_callable($style)) {
			$this->style = $style;
			$this->_emit();
			return $this;
		}

		$this->style = trim($style);
		$this->_emit();
		return $this;
	}

	/**
	 * Set the description/help text for the element.
	 *
	 * Delegates to ComponentBuilderBase::description().
	 *
	 * @param string|null $description The description text.
	 * @return static
	 */
	public function description(?string $description): static {
		$this->builder->description($description);
		$this->_emit();
		return $this;
	}

	/**
	 * Set multiple HTML attributes for the element.
	 *
	 * Delegates to ComponentBuilderBase::attributes().
	 *
	 * @param array<string,string> $attributes Attribute key-value pairs.
	 * @return static
	 */
	public function attributes(array $attributes): static {
		$this->builder->attributes($attributes);
		$this->_emit();
		return $this;
	}

	/**
	 * Set a single HTML attribute for the element.
	 *
	 * Delegates to ComponentBuilderBase::attribute().
	 *
	 * @param string $key Attribute name.
	 * @param string $value Attribute value.
	 * @return static
	 */
	public function attribute(string $key, string $value): static {
		$this->builder->attribute($key, $value);
		$this->_emit();
		return $this;
	}

	/**
	 * Register a callback to run before rendering the element.
	 *
	 * @param callable|null $before The before callback.
	 * @return static
	 */
	public function before(?callable $before): static {
		$this->before_callback = $before;
		$this->_emit();
		return $this;
	}

	/**
	 * Register a callback to run after rendering the element.
	 *
	 * @param callable|null $after The after callback.
	 * @return static
	 */
	public function after(?callable $after): static {
		$this->after_callback = $after;
		$this->_emit();
		return $this;
	}

	/**
	 * Apply context values to the builder.
	 *
	 * @param array<string,mixed> $context Context values to apply.
	 * @return static
	 */
	public function apply_context(array $context): static {
		foreach ($context as $key => $value) {
			if ($value === null) {
				continue;
			}

			if ($key === 'attributes' && is_array($value) && method_exists($this->builder, 'attributes')) {
				$this->builder->attributes($value);
				continue;
			}

			if ($key === 'element_template') {
				$this->element_template = $value;
				continue;
			}

			// Try to call method on builder if it exists
			if (method_exists($this->builder, $key)) {
				call_user_func(array($this->builder, $key), $value);
			} else {
				$this->pending_context[$key] = $value;
			}
		}

		$this->_emit();
		return $this;
	}

	/**
	 * End element configuration and return to the parent builder.
	 *
	 * @return TParent The parent builder for continued chaining.
	 */
	public function end_element(): object {
		return $this->parent;
	}

	/**
	 * Magic method to delegate unknown methods to the underlying builder.
	 *
	 * @param string $name Method name.
	 * @param array<int,mixed> $arguments Method arguments.
	 * @return static
	 * @throws BadMethodCallException If method doesn't exist on builder.
	 */
	public function __call(string $name, array $arguments): static {
		if (!method_exists($this->builder, $name)) {
			throw new BadMethodCallException(sprintf(
				'Method "%s" does not exist on %s or its underlying builder.',
				$name,
				static::class
			));
		}

		call_user_func_array(array($this->builder, $name), $arguments);
		$this->_emit();
		return $this;
	}

	/**
	 * Emit the element update event.
	 */
	protected function _emit(): void {
		$element                      = $this->builder->to_array();
		$context                      = is_array($element['component_context'] ?? null) ? $element['component_context'] : array();
		$element['component_context'] = array_merge($context, $this->pending_context);
		$element['component']         = $this->component_alias;
		$element['id']                = $element['id']    ?? $this->element_id;
		$element['label']             = $element['label'] ?? '';
		$element['is_element']        = true;
		$element['before']            = $this->before_callback;
		$element['after']             = $this->after_callback;
		if ($this->style !== '') {
			$element['style'] = $this->style;
		}

		$payload = array(
			'container_id' => $this->container_id,
			'section_id'   => $this->section_id,
			'field_data'   => $element,
		);

		if ($this->group_id !== null) {
			$payload['group_id'] = $this->group_id;
			($this->updateFn)('group_field', $payload);
		} else {
			($this->updateFn)('field', $payload);
		}
	}
}
