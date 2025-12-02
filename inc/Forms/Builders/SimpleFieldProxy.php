<?php
/**
 * SimpleFieldProxy: Lightweight proxy for fields without component builders.
 *
 * This proxy captures field-level before/after callbacks for simple components
 * that don't have a full ComponentBuilderDefinitionInterface. It ensures that
 * chained ->before() and ->after() calls affect the field, not the parent container.
 * Call end_field() to return to the parent builder.
 *
 * @package Ran\PluginLib\Forms\Builders
 *
 * @template TParent of SectionBuilder|SectionFieldContainerBuilder
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders;

use BadMethodCallException;

/**
 * Proxy for simple field configurations without component builders.
 *
 * @template-implements FieldProxyInterface
 */
class SimpleFieldProxy implements FieldProxyInterface {
	/** @var SectionBuilder|SectionFieldContainerBuilder */
	private SectionBuilder|SectionFieldContainerBuilder $parent;

	/** @var callable */
	private $updateFn;

	private string $container_id;
	private string $section_id;
	private ?string $group_id;
	private string $field_id;
	private string $label;
	private string $component;

	/** @var array<string,mixed> */
	private array $component_context;

	private ?int $order;
	private ?string $template;
	private string $style = '';

	/** @var callable|null */
	private $before_callback = null;

	/** @var callable|null */
	private $after_callback = null;

	private bool $emitted = false;

	public function __construct(
		SectionBuilder|SectionFieldContainerBuilder $parent,
		callable $updateFn,
		string $container_id,
		string $section_id,
		?string $group_id,
		string $field_id,
		string $label,
		string $component,
		array $component_context,
		?int $order,
		?string $template,
		?callable $before,
		?callable $after
	) {
		$this->parent            = $parent;
		$this->updateFn          = $updateFn;
		$this->container_id      = $container_id;
		$this->section_id        = $section_id;
		$this->group_id          = $group_id;
		$this->field_id          = $field_id;
		$this->label             = $label;
		$this->component         = $component;
		$this->component_context = $component_context;
		$this->order             = $order;
		$this->template          = $template;
		$this->before_callback   = $before;
		$this->after_callback    = $after;
	}

	/**
	 * Set the field ID.
	 *
	 * @param string $id The field ID.
	 *
	 * @return static
	 */
	public function id(string $id): static {
		$this->field_id = $id;
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
		return $this;
	}

	/**
	 * Set the field order.
	 *
	 * @param int $order The field order.
	 *
	 * @return static
	 */
	public function order(int $order): static {
		$this->order = $order;
		return $this;
	}

	/**
	 * Set the field template override.
	 *
	 * @param string $template The template key.
	 *
	 * @return static
	 */
	public function template(string $template): static {
		$this->template = $template;
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
		$this->style = $style === '' ? '' : $this->_resolve_style_arg($style);
		return $this;
	}

	/**
	 * Throws - call end_field() before configuring section heading.
	 *
	 * @param string $heading The heading.
	 *
	 * @return never
	 * @throws BadMethodCallException Always.
	 */
	public function heading(string $heading): static {
		throw new BadMethodCallException('Call end_field() before configuring section heading.');
	}

	/**
	 * End field configuration and return to the parent builder.
	 *
	 * @return TParent
	 */
	public function end_field(): SectionBuilder|SectionFieldContainerBuilder {
		$this->_emit_field_update();
		return $this->parent;
	}

	/**
	 * End the fieldset and return to the section builder.
	 *
	 * This is a convenience method that calls end_field() then end_fieldset() on the parent.
	 *
	 * @return SectionBuilderInterface
	 */
	public function end_fieldset(): SectionBuilderInterface {
		$parent = $this->end_field();
		if ($parent instanceof FieldsetBuilder) {
			return $parent->end_fieldset();
		}
		throw new \RuntimeException('Cannot call end_fieldset() - parent is not a FieldsetBuilder.');
	}

	/**
	 * End the group and return to the section builder.
	 *
	 * This is a convenience method that calls end_field() then end_group() on the parent.
	 *
	 * @return SectionBuilderInterface
	 */
	public function end_group(): SectionBuilderInterface {
		$parent = $this->end_field();
		if ($parent instanceof GroupBuilder) {
			return $parent->end_group();
		}
		throw new \RuntimeException('Cannot call end_group() - parent is not a GroupBuilder.');
	}

	/**
	 * End the section and return to the collection/root builder.
	 *
	 * This is a convenience method that calls end_field() then end_section() on the parent.
	 *
	 * @return BuilderRootInterface
	 */
	public function end_section(): BuilderRootInterface {
		$parent = $this->end_field();
		if (method_exists($parent, 'end_section')) {
			return $parent->end_section();
		}
		throw new \RuntimeException('Cannot call end_section() - parent does not have end_section() method.');
	}

	/**
	 * Emit the field update event.
	 */
	private function _emit_field_update(): void {
		if ($this->emitted) {
			return;
		}
		$this->emitted = true;

		$payload = array(
			'container_id' => $this->container_id,
			'section_id'   => $this->section_id,
			'field_data'   => array(
				'id'                => $this->field_id,
				'label'             => $this->label,
				'component'         => $this->component,
				'component_context' => $this->component_context,
				'order'             => $this->order,
				'before'            => $this->before_callback,
				'after'             => $this->after_callback,
				'style'             => $this->style !== '' ? $this->style : null,
			),
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
		if ($this->template === null || $this->field_id === '') {
			return;
		}

		($this->updateFn)('template_override', array(
			'element_type' => 'field',
			'element_id'   => $this->field_id,
			'overrides'    => array('field-wrapper' => $this->template),
		));
	}

	/**
	 * Magic method to forward calls to parent builder after emitting field.
	 *
	 * @internal
	 * @param string $name Method name.
	 * @param array<int,mixed> $arguments Method arguments.
	 *
	 * @return mixed
	 */
	public function __call(string $name, array $arguments): mixed {
		$this->_emit_field_update();
		return $this->parent->$name(...$arguments);
	}

	/**
	 * Ensure field is emitted when proxy is destroyed.
	 *
	 * @internal
	 */
	public function __destruct() {
		$this->_emit_field_update();
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
