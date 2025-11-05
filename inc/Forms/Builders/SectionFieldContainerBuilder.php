<?php
/**
 * SectionFieldContainerBuilder: Shared base for section-scoped field container builders.
 *
 * @template TRoot of BuilderRootInterface
 * @template TSection of SectionBuilder<TRoot>
 *
 * @package Ran\PluginLib\Forms\Builders
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders;

use Ran\PluginLib\Forms\Builders\ComponentBuilderProxy;
use Ran\PluginLib\Forms\Builders\BuilderImmediateUpdateTrait;
use Ran\PluginLib\Forms\Builders\BuilderRootInterface;
use Ran\PluginLib\Forms\FormsInterface;
use Ran\PluginLib\Forms\Component\Build\ComponentBuilderDefinitionInterface;

/**
 * @internal Shared implementation for containers that live directly under a SectionBuilder.
 *
 * @template-extends SectionFieldContainerBuilderInterface<TRoot, TSection>
 */
abstract class SectionFieldContainerBuilder implements SectionFieldContainerBuilderInterface {
	use BuilderImmediateUpdateTrait;

	/**
	 * @var SectionBuilder
	 * @phpstan-var TSection
	 */
	protected SectionBuilder $sectionBuilder;
	protected string $container_id;
	protected string $section_id;
	protected string $group_id;
	protected string $heading;
	/** @var callable|null */
	protected $description_cb;
	/** @var callable */
	protected $updateFn;
	/** @var callable|null */
	protected $before;
	/** @var callable|null */
	protected $after;
	protected ?int $order;

	/**
	 * @param array<string,mixed> $args
	 */
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

	public function heading(string $heading): static {
		$this->_update_meta('heading', $heading);
		return $this;
	}

	public function description(callable $description_cb): static {
		$this->_update_meta('description', $description_cb);
		return $this;
	}

	public function template(string $template_key): static {
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

	public function order(?int $order): static {
		$this->_update_meta('order', $order);
		return $this;
	}

	public function field(string $field_id, string $label, string $component, array $args = array()): ComponentBuilderProxy|static {
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

	public function before(?callable $before): static {
		$this->_update_meta('before', $before);
		return $this;
	}

	public function after(?callable $after): static {
		$this->_update_meta('after', $after);
		return $this;
	}

	public function end_section(): BuilderRootInterface {
		return $this->sectionBuilder->end_section();
	}

	/**
	 * Accessor for the owning section builder.
	 */
	protected function section(): SectionBuilder {
		return $this->sectionBuilder;
	}

	public function get_settings(): FormsInterface {
		return $this->get_group_settings();
	}

	public function get_group_settings(): FormsInterface {
		if ($this->sectionBuilder instanceof SectionBuilder) {
			return $this->sectionBuilder->get_forms();
		}

		throw new \RuntimeException('SectionBuilder can only access settings when used with FormsInterface');
	}

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

	protected function _get_update_event_name(): string {
		return 'group_metadata';
	}

	/**
	 * @return array{container_id:string,section_id:string,group_id:string,group_data:array<string,mixed>}
	 */
	protected function _build_update_payload(string $key, mixed $value): array {
		// render the callback
		if (is_callable($this->description_cb)) {
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
	 * Emit container metadata using the underlying update callback.
	 */
	protected function emit_group_metadata(): void {
		($this->updateFn)($this->_get_update_event_name(), $this->_build_update_payload('', null));
	}
}
