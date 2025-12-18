<?php
/**
 * HrBuilder: Fluent builder for horizontal rule elements.
 *
 * Provides a minimal fluent interface for configuring <hr> elements
 * within form sections, groups, and fieldsets.
 *
 * @package Ran\PluginLib\Forms\Builders
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders;

/**
 * Fluent builder for horizontal rule elements.
 *
 * @template TParent of object
 */
class HrBuilder {
	/** @var TParent */
	private object $parent;

	/** @var callable */
	private $updateFn;

	private string $container_id;
	private string $section_id;
	private ?string $group_id;
	private string $hr_id;

	private mixed $style = '';

	/** @var callable|null */
	private $before_callback = null;

	/** @var callable|null */
	private $after_callback = null;

	/**
	 * Constructor.
	 *
	 * @param TParent $parent The parent builder to return to.
	 * @param callable $updateFn The update callback for immediate data flow.
	 * @param string $container_id The container (collection/page) ID.
	 * @param string $section_id The section ID.
	 * @param string|null $group_id The group ID (null for section-level).
	 */
	public function __construct(
		object $parent,
		callable $updateFn,
		string $container_id,
		string $section_id,
		?string $group_id = null
	) {
		$this->parent       = $parent;
		$this->updateFn     = $updateFn;
		$this->container_id = $container_id;
		$this->section_id   = $section_id;
		$this->group_id     = $group_id;
		$this->hr_id        = '_hr_' . uniqid();

		// Emit initial state
		$this->_emit();
	}

	/**
	 * Set CSS classes for the hr element.
	 *
	 * Following the builder convention, style() sets CSS classes.
	 * For inline styles, use the style attribute in the template.
	 *
	 * @param string $style CSS class name(s) to apply.
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
	 * Register a callback to run before rendering the hr.
	 *
	 * @param callable $before The before callback.
	 * @return static
	 */
	public function before(callable $before): static {
		$this->before_callback = $before;
		$this->_emit();
		return $this;
	}

	/**
	 * Register a callback to run after rendering the hr.
	 *
	 * @param callable $after The after callback.
	 * @return static
	 */
	public function after(callable $after): static {
		$this->after_callback = $after;
		$this->_emit();
		return $this;
	}

	/**
	 * End hr configuration and return to the parent builder.
	 *
	 * @return TParent The parent builder for continued chaining.
	 */
	public function end_hr(): object {
		return $this->parent;
	}

	/**
	 * Emit the hr update event.
	 */
	protected function _emit(): void {
		$field_data = array(
			'id'                => $this->hr_id,
			'label'             => '',
			'component'         => '_hr',
			'is_element'        => true,
			'component_context' => array(
				'style' => $this->style,
			),
			'order'  => null,
			'before' => $this->before_callback,
			'after'  => $this->after_callback,
		);

		if ($this->group_id !== null) {
			($this->updateFn)('group_field', array(
				'container_id' => $this->container_id,
				'section_id'   => $this->section_id,
				'group_id'     => $this->group_id,
				'field_data'   => $field_data,
			));
		} else {
			($this->updateFn)('field', array(
				'container_id' => $this->container_id,
				'section_id'   => $this->section_id,
				'field_data'   => $field_data,
			));
		}
	}
}
