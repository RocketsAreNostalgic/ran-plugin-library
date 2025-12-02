<?php
/**
 * ComponentBuilderProxy: Fluent bridge between settings builders and component builders.
 *
 * Provides field-level fluent methods for components with full builder support.
 * Call end_field() to return to the parent builder.
 *
 * @package Ran\PluginLib\Forms\Builders
 *
 * @template TParent of SectionBuilder|SectionFieldContainerBuilder
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders;

use Ran\PluginLib\Forms\Component\Build\ComponentBuilderInterface;
use Ran\PluginLib\Forms\Component\Build\ComponentBuilderDefinitionInterface;
use Ran\PluginLib\Forms\Component\Build\ComponentBuilderBase;
use Ran\PluginLib\Forms\Builders\SectionFieldContainerBuilder;
use Ran\PluginLib\Forms\Builders\SectionBuilder;
use BadMethodCallException;

/**
 * @template-implements ComponentBuilderInterface
 * @template-implements FieldProxyInterface
 */
class ComponentBuilderProxy implements ComponentBuilderInterface, FieldProxyInterface {
	/** @var ComponentBuilderDefinitionInterface&ComponentBuilderInterface */
	private ComponentBuilderDefinitionInterface $builder;
	/**
	 * @var SectionBuilder|SectionFieldContainerBuilder
	 */
	private SectionBuilder|SectionFieldContainerBuilder $parent;
	/**
	 * @var callable(
	 *     string,
	 *     array{container_id:string,section_id:string,field_data:array<string,mixed>,group_id?:string}|
	 *     array{element_type:string,element_id:string,overrides:array<string,string>}
	 * )
	 */
	private $updateFn;
	private string $container_id;
	private string $section_id;
	private string $component_alias;
	private ?string $group_id;
	private ?string $field_template;
	/** @var array<string,mixed> */
	private array $pending_context;
	private ?int $pending_order = null;
	private string $field_id;
	/** @var callable|null */
	private $before_callback = null;
	/** @var callable|null */
	private $after_callback = null;
	private string $style   = '';

	/**
	 * @param ComponentBuilderBase $builder
	 * @param SectionBuilder|SectionFieldContainerBuilder $parent
	 * @param callable $updateFn
	 * @param string $container_id
	 * @param string $section_id
	 * @param string $component_alias
	 * @param string|null $group_id
	 * @param string|null $field_template
	 * @param array<string,mixed> $pending_context
	 */
	/**
	 * @param SectionBuilder|SectionFieldContainerBuilder $parent
	 */
	public function __construct(
		ComponentBuilderBase $builder,
		SectionBuilder|SectionFieldContainerBuilder $parent,
		callable $updateFn,
		string $container_id,
		string $section_id,
		string $component_alias,
		?string $group_id = null,
		?string $field_template = null,
		array $pending_context = array()
	) {
		$this->builder         = $builder;
		$this->parent          = $parent;
		$this->updateFn        = $updateFn;
		$this->container_id    = $container_id;
		$this->section_id      = $section_id;
		$this->component_alias = $component_alias;
		$this->group_id        = $group_id;
		$this->field_template  = $field_template;
		$this->pending_context = $pending_context;

		$this->field_id = $this->builder->to_array()['id'] ?? $this->field_id ?? '';
		$this->_emit_field_update();
	}

	/**
	 * Proxy fluent builder methods to the underlying component builder.
	 *
	 * Only forwards to the component builder, not the parent. Use end_field()
	 * to return to the parent builder before calling parent methods.
	 *
	 * @param string $name
	 * @param array<int,mixed> $arguments
	 * @return mixed
	 */
	public function __call(string $name, array $arguments) {
		if (method_exists($this->builder, $name)) {
			$result = $this->builder->$name(...$arguments);
			if ($result instanceof ComponentBuilderDefinitionInterface || $result === $this->builder) {
				return $this;
			}
			return $result;
		}

		throw new BadMethodCallException(sprintf(
			'Method "%s" is not available on field proxy. Call end_field() first to return to the parent builder.',
			$name
		));
	}

	/**
	 * Set the template override for this field.
	 *
	 * @param string $template_key The template key.
	 *
	 * @return self
	 */
	public function template(string $template_key): static {
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
	 * @return self
	 */
	public function id(string $id): static {
		$this->builder->id($id);
		$this->field_id = $id;
		$this->_emit_field_update();
		return $this;
	}

	/**
	 * Set the disabled state for this field.
	 *
	 * @param bool $disabled The disabled state.
	 *
	 * @return self
	 */
	public function disabled(bool $disabled = true): self {
		$this->builder->disabled($disabled);
		$this->_emit_field_update();
		return $this;
	}

	/**
	 * Set the required state for this field.
	 *
	 * @param bool $required The required state.
	 *
	 * @return self
	 */
	public function required(bool $required = true): self {
		$this->builder->required($required);
		$this->_emit_field_update();
		return $this;
	}

	/**
	 * Set the readonly state for this field.
	 *
	 * @param bool $readonly The readonly state.
	 *
	 * @return self
	 */
	public function readonly(bool $readonly = true): self {
		$this->builder->readonly($readonly);
		$this->_emit_field_update();
		return $this;
	}

	/**
	 * Set an attribute for this field.
	 *
	 * @param string $attribute The attribute name.
	 * @param string $value The attribute value.
	 *
	 * @return self
	 */
	public function attribute(string $attribute, string $value): self {
		$this->builder->attribute($attribute, $value);
		$this->_emit_field_update();
		return $this;
	}

	/**
	 * Set the aria label for this field.
	 *
	 * @param string $ariaLabel The aria label.
	 *
	 * @return self
	 */
	public function ariaLabel(string $ariaLabel): self {
		$this->builder->ariaLabel($ariaLabel);
		$this->_emit_field_update();
		return $this;
	}

	/**
	 * Set the aria described by for this field.
	 *
	 * @param string $ariaDescribedBy The aria described by.
	 *
	 * @return self
	 */
	public function ariaDescribedBy(string $ariaDescribedBy): self {
		$this->builder->ariaDescribedBy($ariaDescribedBy);
		$this->_emit_field_update();
		return $this;
	}

	/**
	 * Set the visual style for this field.
	 *
	 * @param string|callable $style The style identifier or resolver returning a string.
	 *
	 * @return self
	 */
	public function style(string|callable $style): static {
		$this->style = $style === '' ? '' : $this->_resolve_style_arg($style);
		$this->_emit_field_update();
		return $this;
	}

	/**
	 * Set the heading for this field.
	 *
	 * @param string $heading The heading.
	 *
	 * @return self
	 */
	public function heading(string $heading): self {
		throw new BadMethodCallException('Call end_field() before configuring section heading.');
	}

	/**
	 * Set the description for this field.
	 *
	 * @param string $description The description.
	 *
	 * @return self
	 */
	public function description(string $description): self {
		if (method_exists($this->builder, 'description')) {
			call_user_func(array($this->builder, 'description'), $description);
			$this->_emit_field_update();
			return $this;
		}

		throw new BadMethodCallException('Field builder does not support description().');
	}

	/**
	 * Set the order for this field.
	 *
	 * @param int $order The order.
	 *
	 * @return self
	 */
	public function order(int $order): static {
		$this->pending_order = $order;
		if (method_exists($this->builder, 'order')) {
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
	 * @return self
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
	 * @return self
	 */
	public function after(?callable $after): static {
		$this->after_callback = $after;
		$this->_emit_field_update();
		return $this;
	}

	/**
	 * @return TParent
	 */
	public function end_field(): SectionBuilder|SectionFieldContainerBuilder {
		return $this->parent;
	}

	/**
	 * Apply context to the builder.
	 *
	 * @param array<string,mixed> $context The context.
	 *
	 * @return void
	 */
	public function apply_context(array $context): void {
		$this->pending_context = $this->_hydrate_builder($context);
		$this->_emit_field_update();
	}

	/**
	 * Emit the field update event.
	 */
	private function _emit_field_update(): void {
		$field                      = $this->builder->to_array();
		$context                    = is_array($field['component_context'] ?? null) ? $field['component_context'] : array();
		$field['component_context'] = array_merge($context, $this->pending_context);
		$field['component']         = $this->component_alias;
		$field['order']             = $this->_resolve_order($field);
		$field['id']                = $field['id']    ?? $this->field_id;
		$field['label']             = $field['label'] ?? null;
		if ($this->before_callback !== null) {
			$field['before'] = $this->before_callback;
		}
		if ($this->after_callback !== null) {
			$field['after'] = $this->after_callback;
		}
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
	private function _emit_template_override(): void {
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
	private function _resolve_order(array $field): int {
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
	 * @return array<string,mixed>
	 */
	private function _hydrate_builder(array $context): array {
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
				$this->field_template((string) $value);
				continue;
			}

			$method = $this->_normalize_to_method($key);
			if (method_exists($this->builder, $method)) {
				$result = call_user_func(array($this->builder, $method), $value);
				if ($result instanceof ComponentBuilderDefinitionInterface || $result === $this->builder) {
					continue;
				}
				// Non-fluent return - keep context value
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
	private function _normalize_to_method(string $key): string {
		$key        = str_replace(array('-', '_'), ' ', $key);
		$key        = ucwords(strtolower($key));
		$normalized = lcfirst(str_replace(' ', '', $key));
		return $normalized;
	}

	/**
	 * Normalize a style argument to a trimmed string.
	 *
	 * @param string|callable $style Style value or resolver callback returning a string.
	 *
	 * @return string
	 * @throws \InvalidArgumentException When the resolved value is not a string.
	 */
	private function _resolve_style_arg(string|callable $style): string {
		$resolved = is_callable($style) ? $style() : $style;
		if (!is_string($resolved)) {
			throw new \InvalidArgumentException('Field style callback must return a string.');
		}
		return trim($resolved);
	}
}
