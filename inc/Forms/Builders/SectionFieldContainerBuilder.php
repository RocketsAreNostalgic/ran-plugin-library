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

use Ran\PluginLib\Forms\FormsInterface;
use Ran\PluginLib\Forms\Component\Build\ComponentBuilderDefinitionInterface;
use Ran\PluginLib\Forms\Builders\ComponentBuilderProxy;
use Ran\PluginLib\Forms\Builders\BuilderRootInterface;
use Ran\PluginLib\Forms\Builders\BuilderImmediateUpdateTrait;

/**
 * @internal Shared implementation for containers that live directly under a SectionBuilder.
 *
 * @template-extends SectionFieldContainerBuilderInterface<TRoot, TSection>
 */
abstract class SectionFieldContainerBuilder implements SectionFieldContainerBuilderInterface {
	use BuilderImmediateUpdateTrait;

	/**
	 * @var SectionBuilder
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
	 * Default field template for fields added to this container.
	 * If set, fields without an explicit field_template will use this.
	 */
	protected ?string $default_field_template = null;

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

	/**
	 * Add a field to this group/fieldset.
	 *
	 * @return ComponentBuilderProxy<SectionFieldContainerBuilderInterface<TRoot, TSection>>|SimpleFieldProxy
	 */
	public function field(string $field_id, string $label, string $component, array $args = array()): ComponentBuilderProxy|SimpleFieldProxy {
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

		$factory = $this->sectionBuilder->get_component_builder_factory($component);
		if ($factory instanceof \Closure || is_callable($factory)) {
			$builder = $factory($field_id, $label);
			if (!$builder instanceof ComponentBuilderDefinitionInterface) {
				throw new \UnexpectedValueException(sprintf('Builder factory for "%s" must return ComponentBuilderDefinitionInterface.', $component));
			}

			$proxy = $this->_create_component_proxy(
				$builder,
				$component,
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

		// For simple components without builder factories, return a SimpleFieldProxy
		// to allow chained before()/after() calls without affecting the parent group
		return $this->_create_simple_field_proxy(
			$field_id,
			$label,
			$component,
			$component_context,
			$order !== null ? (int) $order : null,
			$field_template,
			is_callable($before) ? $before : null,
			is_callable($after) ? $after : null
		);
	}

	/**
	 * Factory method to create a SimpleFieldProxy.
	 * Override in subclasses to return context-specific proxy types.
	 *
	 * @param string $field_id The field identifier.
	 * @param string $label The field label.
	 * @param string $component The component alias.
	 * @param array<string,mixed> $component_context The component context.
	 * @param int|null $order The field order.
	 * @param string|null $field_template The field template override.
	 * @param callable|null $before The before callback.
	 * @param callable|null $after The after callback.
	 *
	 * @return SimpleFieldProxy The proxy instance.
	 */
	protected function _create_simple_field_proxy(
		string $field_id,
		string $label,
		string $component,
		array $component_context,
		?int $order,
		?string $field_template,
		?callable $before,
		?callable $after
	): SimpleFieldProxy {
		return new SimpleFieldProxy(
			$this,
			$this->updateFn,
			$this->container_id,
			$this->section_id,
			$this->group_id,
			$field_id,
			$label,
			$component,
			$component_context,
			$order,
			$field_template,
			$before,
			$after
		);
	}

	/**
	 * Factory method to create a ComponentBuilderProxy.
	 * Override in subclasses to return context-specific proxy types.
	 *
	 * @param ComponentBuilderDefinitionInterface $builder The component builder.
	 * @param string $component_alias The component alias.
	 * @param string|null $field_template The field template override.
	 * @param array<string,mixed> $component_context The component context.
	 *
	 * @return ComponentBuilderProxy The proxy instance.
	 */
	protected function _create_component_proxy(
		ComponentBuilderDefinitionInterface $builder,
		string $component_alias,
		?string $field_template,
		array $component_context
	): ComponentBuilderProxy {
		return new ComponentBuilderProxy(
			$builder,
			$this,
			$this->updateFn,
			$this->container_id,
			$this->section_id,
			$component_alias,
			$this->group_id,
			$field_template,
			$component_context
		);
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
	 * End the group and return to the section builder.
	 *
	 * This method exists for API consistency with union return types.
	 * GroupBuilder overrides with proper implementation.
	 *
	 * @return SectionBuilderInterface
	 * @throws \RuntimeException When called from wrong context.
	 */
	public function end_group(): SectionBuilderInterface {
		throw new \RuntimeException('Cannot call end_group() from this context.');
	}

	/**
	 * End the fieldset and return to the section builder.
	 *
	 * This method exists for API consistency with union return types.
	 * FieldsetBuilder overrides with proper implementation.
	 *
	 * @return SectionBuilderInterface
	 * @throws \RuntimeException When called from wrong context.
	 */
	public function end_fieldset(): SectionBuilderInterface {
		throw new \RuntimeException('Cannot call end_fieldset() from this context.');
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
				'type'        => 'group', // Subclasses override (e.g., FieldsetBuilder sets 'fieldset')
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
