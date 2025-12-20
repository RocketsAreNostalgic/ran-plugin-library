<?php
/**
 * FieldProxyTrait: Shared logic for field proxy builders.
 *
 * This trait provides the core implementation for field proxies that bridge
 * between settings builders and component builders. It is designed to be used
 * with composition rather than inheritance, allowing context-specific proxies
 * to declare concrete return types for IDE support.
 *
 * @package Ran\PluginLib\Forms\Builders\Traits
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders\Traits;

use Ran\PluginLib\Forms\Component\Build\ComponentBuilderDefinitionInterface;
use Ran\PluginLib\Forms\Component\Build\ComponentBuilderBase;
use BadMethodCallException;

/**
 * Shared implementation for field proxies.
 *
 * Classes using this trait must:
 * 1. Implement the abstract methods for context-specific behavior
 * 2. Call `_init_proxy()` in their constructor
 * 3. Store their own parent reference with a concrete type
 */
trait FieldProxyTrait {
	/** @var ComponentBuilderBase */
	private ComponentBuilderBase $builder;

	/** @var callable */
	private $updateFn;

	private string $container_id;
	private string $section_id;
	private string $component_alias;
	private ?string $group_id;
	/** @var string|callable|null */
	private $field_template;

	/** @var array<string,mixed> */
	private array $pending_context;

	private ?int $pending_order        = null;
	private bool $pending_order_is_set = false;
	private string $field_id;

	/** @var callable|null */
	private $before_callback = null;

	/** @var callable|null */
	private $after_callback = null;

	private mixed $style = '';

	/**
	 * Initialize the proxy with common properties.
	 *
	 * Call this from the constructor of classes using this trait.
	 *
	 * @param ComponentBuilderBase $builder The component builder.
	 * @param callable $updateFn The update callback.
	 * @param string $container_id The container identifier.
	 * @param string $section_id The section identifier.
	 * @param string $component_alias The component alias.
	 * @param string|null $group_id The group identifier (null for section-level fields).
	 * @param string|null $field_template The field template override.
	 * @param array<string,mixed> $pending_context Initial pending context.
	 */
	protected function _init_proxy(
		ComponentBuilderBase $builder,
		callable $updateFn,
		string $container_id,
		string $section_id,
		string $component_alias,
		?string $group_id = null,
		?string $field_template = null,
		array $pending_context = array()
	): void {
		$this->builder         = $builder;
		$this->updateFn        = $updateFn;
		$this->container_id    = $container_id;
		$this->section_id      = $section_id;
		$this->component_alias = $component_alias;
		$this->group_id        = $group_id;
		$this->field_template  = $field_template;
		$this->pending_context = $pending_context;

		$this->field_id = $this->builder->to_array()['id'] ?? '';
		$this->_emit_field_update();
	}

	/**
	 * Set the template override for this field.
	 *
	 * @param string|callable $template_key The template key.
	 *
	 * @return static
	 */
	public function template(string|callable $template_key): static {
		if (is_callable($template_key)) {
			$this->field_template = $template_key;
			$this->_emit_template_override();
			return $this;
		}

		$template = trim($template_key);
		if ($template !== '') {
			$this->field_template = $template;
			$this->_emit_template_override();
		}
		return $this;
	}

	/**
	 * Set the ID for this field.
	 *
	 * @param string $id The ID.
	 *
	 * @return static
	 */
	public function id(string $id): static {
		$this->field_id = $id;
		if (method_exists($this->builder, 'id')) {
			call_user_func(array($this->builder, 'id'), $id);
		}
		$this->_emit_field_update();
		return $this;
	}

	/**
	 * Set the disabled state for this field.
	 *
	 * @param bool|callable $disabled Boolean or callable that returns bool.
	 *
	 * @return static
	 */
	public function disabled(bool|callable $disabled = true): static {
		if (!method_exists($this->builder, 'disabled')) {
			throw new BadMethodCallException('Field builder does not support disabled().');
		}
		call_user_func(array($this->builder, 'disabled'), $disabled);
		$this->_emit_field_update();
		return $this;
	}

	/**
	 * Set the required state for this field.
	 *
	 * @param bool|callable $required Boolean or callable that returns bool.
	 *
	 * @return static
	 */
	public function required(bool|callable $required = true): static {
		if (!method_exists($this->builder, 'required')) {
			throw new BadMethodCallException('Field builder does not support required().');
		}
		call_user_func(array($this->builder, 'required'), $required);
		$this->_emit_field_update();
		return $this;
	}

	/**
	 * Set the readonly state for this field.
	 *
	 * @param bool|callable $readonly Boolean or callable that returns bool.
	 *
	 * @return static
	 */
	public function readonly(bool|callable $readonly = true): static {
		if (!method_exists($this->builder, 'readonly')) {
			throw new BadMethodCallException('Field builder does not support readonly().');
		}
		call_user_func(array($this->builder, 'readonly'), $readonly);
		$this->_emit_field_update();
		return $this;
	}

	/**
	 * Sets a default value for the input element.
	 *
	 * @param string|callable|null $value A string value or callable that returns a string.
	 * @return static
	 */
	public function default(string|callable|null $value): static {
		if (!method_exists($this->builder, 'default')) {
			throw new BadMethodCallException('Field builder does not support default().');
		}
		call_user_func(array($this->builder, 'default'), $value);
		$this->_emit_field_update();
		return $this;
	}

	/**
	 * Sets default values for multi-value fields.
	 *
	 * @param array|callable $values Array of values or callable that returns an array.
	 * @return static
	 */
	public function default_values(array|callable $values): static {
		if (!method_exists($this->builder, 'default_values')) {
			throw new BadMethodCallException('Field builder does not support default_values().');
		}
		call_user_func(array($this->builder, 'default_values'), $values);
		$this->_emit_field_update();
		return $this;
	}

	/**
	 * Sets the name attribute for the input element.
	 *
	 * @param string|null $name
	 * @return static
	 */
	public function name(?string $name): static {
		if (!method_exists($this->builder, 'name')) {
			throw new BadMethodCallException('Field builder does not support name().');
		}
		call_user_func(array($this->builder, 'name'), $name);
		$this->_emit_field_update();
		return $this;
	}

	/**
	 * Set an attribute for this field.
	 *
	 * @param string $attribute The attribute name.
	 * @param string $value The attribute value.
	 *
	 * @return static
	 */
	public function attribute(string $attribute, string $value): static {
		$this->builder->attribute($attribute, $value);
		$this->_emit_field_update();
		return $this;
	}

	/**
	 * Set multiple attributes for this field.
	 *
	 * @param array<string,string> $attributes Key-value pairs of attributes.
	 *
	 * @return static
	 */
	public function attributes(array $attributes): static {
		if (method_exists($this->builder, 'attributes')) {
			call_user_func(array($this->builder, 'attributes'), $attributes);
			$this->_emit_field_update();
		}
		return $this;
	}

	/**
	 * Set the aria label for this field.
	 *
	 * @param string $aria_label The aria label.
	 *
	 * @return static
	 */
	public function aria_label(string $aria_label): static {
		$this->attribute('aria-label', $aria_label);
		return $this;
	}

	/**
	 * Set the aria described by for this field.
	 *
	 * @param string $aria_described_by The aria described by.
	 *
	 * @return static
	 */
	public function aria_described_by(string $aria_described_by): static {
		$this->attribute('aria-describedby', $aria_described_by);
		return $this;
	}

	/**
	 * Set the input type for this field (e.g., 'email', 'tel', 'url').
	 *
	 * Delegates to the underlying component builder if it supports type().
	 *
	 * @param string $type The input type.
	 *
	 * @return static
	 */
	public function type(string $type): static {
		if (method_exists($this->builder, 'type')) {
			call_user_func(array($this->builder, 'type'), $type);
			$this->_emit_field_update();
		}
		return $this;
	}

	/**
	 * Set the visual style for this field.
	 *
	 * @param string|callable $style The style identifier or resolver returning a string.
	 *
	 * @return static
	 */
	public function style(string|callable $style): static {
		if ($style === '') {
			$this->style = '';
			$this->_emit_field_update();
			return $this;
		}

		if (is_callable($style)) {
			$this->style = $style;
			$this->_emit_field_update();
			return $this;
		}

		$this->style = trim($style);
		$this->_emit_field_update();
		return $this;
	}

	/**
	 * Set the heading - not valid on field proxy.
	 *
	 * @param string $heading The heading.
	 *
	 * @return static
	 *
	 * @throws BadMethodCallException Always throws.
	 */
	public function heading(string $heading): static {
		throw new BadMethodCallException('Call end_field() before configuring section heading.');
	}

	/**
	 * Set the description for this field.
	 *
	 * @param string $description The description.
	 *
	 * @return static
	 *
	 * @throws BadMethodCallException If builder doesn't support description.
	 */
	public function description(string $description): static {
		if (method_exists($this->builder, 'description')) {
			call_user_func(array($this->builder, 'description'), $description);
			$this->_emit_field_update();
			return $this;
		}

		throw new BadMethodCallException('Field builder does not support description().');
	}

	/**
	 * Set options for select/multi-select/radio/checkbox-group fields.
	 *
	 * Delegates to the underlying component builder if it supports options().
	 *
	 * @param array|callable $options Array of options or callable that returns array.
	 *
	 * @return static
	 */
	public function options(array|callable $options): static {
		if (method_exists($this->builder, 'options')) {
			call_user_func(array($this->builder, 'options'), $options);
			$this->_emit_field_update();
		}
		return $this;
	}

	/**
	 * Set the order for this field.
	 *
	 * @param int|null $order The order.
	 *
	 * @return static
	 */
	public function order(?int $order): static {
		$this->pending_order_is_set = true;
		$this->pending_order        = $order;
		if ($order !== null && method_exists($this->builder, 'order')) {
			call_user_func(array($this->builder, 'order'), $order);
		}
		$this->_emit_field_update();
		return $this;
	}

	/**
	 * Set the before callback for this field.
	 *
	 * @param callable|null $before The before callback.
	 *
	 * @return static
	 */
	public function before(?callable $before): static {
		$this->before_callback = $before;
		$this->_emit_field_update();
		return $this;
	}

	/**
	 * Set the after callback for this field.
	 *
	 * @param callable|null $after The after callback.
	 *
	 * @return static
	 */
	public function after(?callable $after): static {
		$this->after_callback = $after;
		$this->_emit_field_update();
		return $this;
	}

	/**
	 * Apply context to the builder.
	 *
	 * @param array<string,mixed> $context The context.
	 */
	public function apply_context(array $context): void {
		$this->pending_context = $this->_hydrate_builder($context);
		$this->_emit_field_update();
	}

	/**
	 * Emit the field update event.
	 */
	protected function _emit_field_update(): void {
		$field                      = $this->builder->to_array();
		$context                    = is_array($field['component_context'] ?? null) ? $field['component_context'] : array();
		$field['component_context'] = array_merge($context, $this->pending_context);
		$field['component']         = $this->component_alias;
		$field['order']             = $this->_resolve_field_order($field);
		$field['id']                = $this->field_id !== '' ? $this->field_id : ($field['id'] ?? '');
		$field['label']             = $field['label'] ?? null;
		$field['before']            = $this->before_callback;
		$field['after']             = $this->after_callback;
		if ($this->style !== '') {
			$field['style'] = $this->style;
		}

		$payload = array(
			'container_id' => $this->container_id,
			'section_id'   => $this->section_id,
			'field_data'   => $field,
		);

		if ($this->group_id !== null) {
			$payload['group_id'] = $this->group_id;
			($this->updateFn)('group_field', $payload);
		} else {
			($this->updateFn)('field', $payload);
		}

		$this->_emit_template_override();
	}

	/**
	 * Emit the template override event.
	 */
	protected function _emit_template_override(): void {
		if ($this->field_template === null || $this->field_id === '') {
			return;
		}

		($this->updateFn)('template_override', array(
			'element_type' => 'field',
			'element_id'   => $this->field_id,
			'overrides'    => array('field-wrapper' => $this->field_template)
		));
	}

	/**
	 * Resolve the order for the field.
	 *
	 * @param array<string,mixed> $field The field data.
	 *
	 * @return int
	 */
	protected function _resolve_field_order(array $field): int {
		if ($this->pending_order_is_set) {
			return $this->pending_order === null ? 0 : (int) $this->pending_order;
		}
		if ($this->pending_order !== null) {
			return $this->pending_order;
		}
		if (array_key_exists('order', $field)) {
			return (int) $field['order'];
		}
		return 0;
	}

	/**
	 * Hydrate the builder with context.
	 *
	 * @param array<string,mixed> $context The context.
	 *
	 * @return array<string,mixed> Remaining context not applied to builder.
	 */
	protected function _hydrate_builder(array $context): array {
		$remaining = $this->pending_context;

		foreach ($context as $key => $value) {
			if ($value === null) {
				continue;
			}

			if ($key === 'order') {
				if (method_exists($this->builder, 'order')) {
					call_user_func(array($this->builder, 'order'), (int) $value);
					continue;
				}
				$this->pending_order = (int) $value;
				continue;
			}

			if ($key === 'attributes' && is_array($value) && method_exists($this->builder, 'attributes')) {
				call_user_func(array($this->builder, 'attributes'), $value);
				continue;
			}

			if ($key === 'field_template') {
				$this->template((string) $value);
				continue;
			}

			$method                = $this->_normalize_to_method($key);
			$has_exact_method      = method_exists($this->builder, (string) $key);
			$has_normalized_method = $method !== $key && method_exists($this->builder, $method);
			if ($has_exact_method && $has_normalized_method) {
				throw new BadMethodCallException(sprintf(
					'Ambiguous builder hydration for key "%s": both "%s" and "%s" exist on %s.',
					(string) $key,
					(string) $key,
					(string) $method,
					get_class($this->builder)
				));
			}
			if ($has_exact_method) {
				$result = call_user_func(array($this->builder, (string) $key), $value);
				if ($result instanceof ComponentBuilderDefinitionInterface || $result === $this->builder) {
					continue;
				}
			}
			if ($has_normalized_method) {
				$result = call_user_func(array($this->builder, $method), $value);
				if ($result instanceof ComponentBuilderDefinitionInterface || $result === $this->builder) {
					continue;
				}
			}

			// Fallback: attributes accept scalar string values
			if (is_scalar($value) && method_exists($this->builder, 'attribute')) {
				call_user_func(array($this->builder, 'attribute'), (string) $key, (string) $value);
				continue;
			}

			$remaining[$key] = $value;
		}

		return $remaining;
	}

	/**
	 * Normalize a key to a method name.
	 *
	 * @param string $key The key.
	 *
	 * @return string
	 */
	protected function _normalize_to_method(string $key): string {
		$key        = str_replace(array('-', '_'), ' ', $key);
		$key        = ucwords(strtolower($key));
		$normalized = lcfirst(str_replace(' ', '', $key));
		return $normalized;
	}

	/**
	 * Magic method to forward calls to the underlying component builder.
	 *
	 * This enables IDE autocomplete via @method annotations on proxy classes.
	 * Methods defined directly on the proxy or trait take precedence.
	 *
	 * @param string $name Method name.
	 * @param array<int,mixed> $arguments Method arguments.
	 *
	 * @return static
	 *
	 * @throws BadMethodCallException If the method doesn't exist on the builder.
	 */
	public function __call(string $name, array $arguments): static {
		if (method_exists($this->builder, $name)) {
			$this->builder->$name(...$arguments);
			$this->_emit_field_update();
			return $this;
		}
		throw new BadMethodCallException(
			sprintf('Method %s::%s does not exist.', static::class, $name)
		);
	}
}
