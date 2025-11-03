<?php
/**
 * GroupBuilder: Fluent builder for grouped fields within a section.
 *
 * @package Ran\PluginLib\Forms\Builders
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders;

use Ran\PluginLib\Forms\FormsInterface;
use Ran\PluginLib\Forms\Component\Build\ComponentBuilderDefinitionInterface;
use Ran\PluginLib\Forms\Builders\SectionBuilder;
use Ran\PluginLib\Forms\Builders\GroupBuilderInterface;
use Ran\PluginLib\Forms\Builders\ComponentBuilderProxy;
use Ran\PluginLib\Forms\Builders\BuilderRootInterface;
use Ran\PluginLib\Forms\Builders\BuilderImmediateUpdateTrait;

class GroupBuilder implements GroupBuilderInterface {
	use BuilderImmediateUpdateTrait;
	/**
	 * @var SectionBuilder
	 * @phpstan-var TSection
	 */
	private SectionBuilder $sectionBuilder;
	private string $container_id;
	private string $section_id;
	private string $group_id;
	private string $heading;
	/** @var callable|null */
	private $description_cb;
	/** @var callable */
	private $updateFn;
	/** @var callable|null */
	private $before;
	/** @var callable|null */
	private $after;
	private ?int $order;

	public function __construct(
		SectionBuilder $sectionBuilder,
		string $container_id,
		string $section_id,
		string $group_id,
		string $heading,
		?callable $description_cb,
		callable $updateFn,
		array $args = array()
	) {
		$this->sectionBuilder = $sectionBuilder;
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

		$this->emit_group_metadata();
	}

	/**
	 * Set the group heading.
	 *
	 * @param string $heading The group heading.
	 *
	 * @return GroupBuilder The GroupBuilder instance.
	 */
	public function heading(string $heading): self {
		$this->_update_meta('heading', $heading);
		return $this;
	}

	/**
	 * Set the group description.
	 *
	 * @param callable $description_cb The description callback.
	 *
	 * @return GroupBuilder The GroupBuilder instance.
	 */
	public function description(callable $description_cb): self {
		$this->_update_meta('description', $description_cb);
		return $this;
	}

	/**
	 * Set the group template for group container styling.
	 * Configures Tier 2 individual group template override via FormsServiceSession.
	 *
	 * @param string $template_key The template key to use for group containers.
	 *
	 * @return GroupBuilder The GroupBuilder instance.
	 */
	public function template(string $template_key): self {
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
	 * Set the order.
	 *
	 * @param int|null $order The order.
	 *
	 * @return GroupBuilder The GroupBuilder instance.
	 */
	public function order(?int $order): self {
		$this->_update_meta('order', $order);
		return $this;
	}

	/**
	 * Define a field within this group.
	 *
	 * @param string $field_id The field ID.
	 * @param string $label The field label.
	 * @param string $component The component alias.
	 * @param array<string,mixed> $args Optional configuration (context, order, field_template).
	 *
	 * @return self|ComponentBuilderProxy The fluent proxy or builder instance.
	 */
	public function field(string $field_id, string $label, string $component, array $args = array()): self|ComponentBuilderProxy {
		$component_context = $args['context']        ?? $args['component_context'] ?? array();
		$order             = $args['order']          ?? null;
		$field_template    = $args['field_template'] ?? null;
		$before            = $args['before']         ?? null;
		$after             = $args['after']          ?? null;

		$component = trim($component);
		if ($component === '') {
			throw new \InvalidArgumentException(sprintf('Grouped field "%s" requires a component alias.', $field_id));
		}
		if (!is_array($component_context)) {
			throw new \InvalidArgumentException(sprintf('Grouped field "%s" must provide an array component_context.', $field_id));
		}

		$factory = $this->sectionBuilder->get_component_builder_factory($component);
		if ($factory instanceof \Closure || is_callable($factory)) {
			$builder = $factory($field_id, $label);
			if (!$builder instanceof ComponentBuilderDefinitionInterface) {
				throw new \UnexpectedValueException(sprintf('Builder factory for "%s" must return ComponentBuilderDefinitionInterface.', $component));
			}

			$proxy = new ComponentBuilderProxy(
				$builder,
				$this,
				$this->updateFn,
				$this->container_id,
				$this->section_id,
				$component,
				$this->group_id,
				$field_template,
				$component_context
			);

			if (!empty($component_context)) {
				$proxy->apply_context($component_context);
			}

			if ($order !== null) {
				$proxy->order((int) $order);
			}

			if ($field_template !== null) {
				$proxy->field_template($field_template);
			}

			if (is_callable($before)) {
				$proxy->before($before);
			}
			if (is_callable($after)) {
				$proxy->after($after);
			}

			return $proxy;
		}

		($this->updateFn)('group_field', array(
			'container_id' => $this->container_id,
			'section_id'   => $this->section_id,
			'group_id'     => $this->group_id,
			'field_data'   => array(
				'id'                => $field_id,
				'label'             => $label,
				'component'         => $component,
				'component_context' => $component_context,
				'order'             => $order,
				'before'            => is_callable($before) ? $before : null,
				'after'             => is_callable($after) ? $after : null,
			)
		));

		if ($field_template !== null) {
			($this->updateFn)('template_override', array(
				'element_type' => 'field',
				'element_id'   => $field_id,
				'overrides'    => array('field-wrapper' => $field_template)
			));
		}

		return $this;
	}

	/**
	 * Set the before callback.
	 *
	 * @param callable|null $before The before callback.
	 *
	 * @return GroupBuilder The GroupBuilder instance.
	 */
	public function before(?callable $before): self {
		$this->_update_meta('before', $before);
		return $this;
	}

	/**
	 * Set the after callback.
	 *
	 * @param callable|null $after The after callback.
	 *
	 * @return GroupBuilder The GroupBuilder instance.
	 */
	public function after(?callable $after): self {
		$this->_update_meta('after', $after);
		return $this;
	}

	/**
	 * Commit buffered data and return to the section builder.
	 *
	 * @return SectionBuilder The SectionBuilder instance.
	 * @phpstan-return TSection
	 */
	public function end_group(): SectionBuilder {
		return $this->sectionBuilder;
	}

	/**
	 * Commit buffered data and return to the root builder.
	 *
	 * @return BuilderRootInterface The root builder instance.
	 * @phpstan-return TRoot
	 */
	public function end_section(): BuilderRootInterface {
		return $this->sectionBuilder->end_section();
	}

	/**
	 * Start a sibling group on the same section and return its GroupBuilder.
	 * Convenient when you want to chain multiple groups without returning to the section builder.
	 *
	 * @param string $group_id The group ID.
	 * @param string $heading The group heading.
	 * @param callable|null $before The before callback.
	 * @param callable|null $after The after callback.
	 * @param int|null $order The group order.
	 *
	 * @return GroupBuilder<TRoot, TSection> The GroupBuilder instance for the new group.
	 */
	public function group(string $group_id, string $heading, ?callable $description_cb = null, ?array $args = null): GroupBuilderInterface {
		$args = $args ?? array();
		return $this->sectionBuilder->group($group_id, $heading, $description_cb, $args);
	}

	/**
	 * Get the FormsInterface instance from the section builder.
	 *
	 * @return FormsInterface
	 */
	public function get_settings(): FormsInterface {
		return $this->get_group_settings();
	}

	/**
	 * Get the FormsInterface instance from the section builder.
	 *
	 * @return FormsInterface
	 */
	public function get_group_settings(): FormsInterface {
		if ($this->sectionBuilder instanceof SectionBuilder) {
			return $this->sectionBuilder->get_forms();
		}

		throw new \RuntimeException('SectionBuilder can only access settings when used with FormsInterface');
	}

	/**
	 * Apply a meta update to the group.
	 *
	 * @param string $key The meta key.
	 * @param mixed $value The meta value.
	 */
	protected function _apply_meta_update(string $key, mixed $value): void {
		switch ($key) {
			case 'heading':
				$this->heading = (string) $value;
				break;
			case 'description':
				$this->description_cb = $value === null || !is_callable($value) ? null : $value;
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
		}

		$this->emit_group_metadata();
	}

	protected function _get_update_callback(): callable {
		return $this->updateFn;
	}

	/**
	 * Get the update event name.
	 *
	 * @return string The update event name.
	 */
	protected function _get_update_event_name(): string {
		return 'group_metadata';
	}

	/**
	 * Build the update payload for the group metadata.
	 *
	 * @param string $key The meta key.
	 * @param mixed $value The meta value.
	 *
	 * @return array The update payload.
	 */
	protected function _build_update_payload(string $key, mixed $value): array {
		// render the callback
		if (\is_callable($this->description_cb)) {
			$description_cb = $this->description_cb;
			$description    = $description_cb();
		} else {
			$description = '';
		}

		return array(
			'container_id' => $this->container_id,
			'section_id'   => $this->section_id,
			'group_id'     => $this->group_id,
			'group_data'   => array(
				'id'          => $this->group_id,
				'heading'     => $this->heading,
				'description' => $description,
				'before'      => $this->before,
				'after'       => $this->after,
				'order'       => $this->order,
			),
		);
	}

	/**
	 * Emit the group metadata to the update callback.
	 */
	private function emit_group_metadata(): void {
		($this->updateFn)($this->_get_update_event_name(), $this->_build_update_payload('', null));
	}
}
