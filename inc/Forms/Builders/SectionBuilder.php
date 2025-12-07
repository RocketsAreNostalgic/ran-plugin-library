<?php
/**
 * SectionBuilder: Fluent builder for html sections within a Settings collection.
 *
 * @template TRoot of BuilderRootInterface
 * @implements SectionBuilderInterface<TRoot>
 *
 * @package Ran\PluginLib\Settings
 * @author  Ran Plugin Lib <bnjmnrsh@gmail.com>
 * @license GPL-2.0+ <http://www.gnu.org/licenses/gpl-2.0.txt>
 * @link    https://github.com/RocketsAreNostalgic
 * @since   0.2.0
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders;

use Ran\PluginLib\Forms\FormsInterface;
use Ran\PluginLib\Forms\Component\Build\ComponentBuilderDefinitionInterface;
use Ran\PluginLib\Forms\Builders\SectionBuilderInterface;
use Ran\PluginLib\Forms\Builders\GroupBuilder;
use Ran\PluginLib\Forms\Builders\ComponentBuilderProxy;
use Ran\PluginLib\Forms\Builders\BuilderRootInterface;
use Ran\PluginLib\Forms\Builders\BuilderImmediateUpdateTrait;

class SectionBuilder implements SectionBuilderInterface {
	use BuilderImmediateUpdateTrait;
	/**
	 * @var BuilderRootInterface
	 */
	protected BuilderRootInterface $collectionBuilder;
	protected FormsInterface $forms;
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
	private string $style   = '';
	private bool $committed = false;
	/** @var array<string, callable>|null */
	private ?array $componentBuilderFactories = null;

	/**
	 * Constructor.
	 *
	 * @param BuilderRootInterface $collectionBuilder The collection/page/form builder instance.
	 * @param string $container_id The container ID.
	 * @param string $section_id The section ID.
	 * @param callable $updateFn The update function for immediate data flow.
	 */
	public function __construct(
		BuilderRootInterface $collectionBuilder,
		string $container_id,
		string $section_id,
		string $heading = '',
		?callable $updateFn = null,
		?callable $before = null,
		?callable $after = null,
		?int $order = null
	) {
		if ($updateFn === null) {
			throw new \InvalidArgumentException('updateFn is required');
		}
		$this->collectionBuilder = $collectionBuilder;
		$this->forms             = $collectionBuilder->_get_forms();
		$this->container_id      = $container_id;
		$this->section_id        = $section_id;
		$this->heading           = $heading;
		$this->updateFn          = $updateFn;
		$this->before            = $before;
		$this->after             = $after;
		$this->order             = $order;
		$this->style             = '';

		$this->_emit_section_metadata();
	}

	/**
	 * Set the section heading.
	 *
	 * @param string $heading The section heading.
	 *
	 * @return SectionBuilder The SectionBuilder instance.
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
	 * @return SectionBuilder The SectionBuilder instance.
	 */
	public function description(string|callable $description): static {
		$this->_update_meta('description', $description);
		return $this;
	}

	/**
	 * Set the section template for section container customization.
	 * Configures Tier 2 individual section template override via FormsServiceSession.
	 *
	 * @param string $template_key The template key to use for section container.
	 *
	 * @return SectionBuilder The SectionBuilder instance.
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
	 * Set the section template for section container customization.
	 * Alias for template() to match SectionBuilderInterface.
	 *
	 * @param string $template_key The template key to use for section container.
	 *
	 * @return static
	 * @throws \InvalidArgumentException If template key is empty.
	 */
	public function section_template(string $template_key): static {
		return $this->template($template_key);
	}

	/**
	 * Set the default field template for all fields in this section.
	 *
	 * @param string $template_key The template key to use for field wrappers.
	 *
	 * @return static
	 * @throws \InvalidArgumentException If template key is empty.
	 */
	public function field_template(string $template_key): static {
		$template_key = trim($template_key);
		if ($template_key === '') {
			throw new \InvalidArgumentException('Template key cannot be empty');
		}

		($this->updateFn)('template_override', array(
			'element_type' => 'section',
			'element_id'   => $this->section_id,
			'overrides'    => array('field-wrapper' => $template_key)
		));

		return $this;
	}

	/**
	 * Set the default fieldset template for all fieldsets in this section.
	 *
	 * @param string $template_key The template key to use for fieldset containers.
	 *
	 * @return static
	 * @throws \InvalidArgumentException If template key is empty.
	 */
	public function fieldset_template(string $template_key): static {
		$template_key = trim($template_key);
		if ($template_key === '') {
			throw new \InvalidArgumentException('Template key cannot be empty');
		}

		($this->updateFn)('template_override', array(
			'element_type' => 'section',
			'element_id'   => $this->section_id,
			'overrides'    => array('fieldset-wrapper' => $template_key)
		));

		return $this;
	}

	/**
	 * Set the default group template for all groups in this section.
	 *
	 * @param string $template_key The template key to use for group containers.
	 *
	 * @return static
	 * @throws \InvalidArgumentException If template key is empty.
	 */
	public function group_template(string $template_key): static {
		$template_key = trim($template_key);
		if ($template_key === '') {
			throw new \InvalidArgumentException('Template key cannot be empty');
		}

		($this->updateFn)('template_override', array(
			'element_type' => 'section',
			'element_id'   => $this->section_id,
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
	public function order(?int $order): static {
		$this->_update_meta('order', $order);
		return $this;
	}

	/**
	 * Set the visual style for this section.
	 *
	 * @param string|callable $style The style identifier or resolver returning a string.
	 *
	 * @return self
	 */
	public function style(string|callable $style): static {
		$normalized = $style === '' ? '' : $this->_resolve_style_arg($style);
		$this->_update_meta('style', $normalized);
		return $this;
	}

	/**
	 * Start a sibling section on the same collection and return its SectionBuilder.
	 * Convenient when you want to chain multiple sections without returning to the collection builder.
	 *
	 * @param string              $section_id     The section ID.
	 * @param string              $heading        The section heading (optional, can be set via heading()).
	 * @param string|callable|null $description_cb The section description (string or callback).
	 * @param array<string,mixed> $args           Optional configuration (order, before/after callbacks, classes, etc.).
	 *
	 * @return SectionBuilder<TRoot> The SectionBuilder instance for the new section.
	 */
	public function section(string $section_id, string $heading = '', string|callable|null $description_cb = null, array $args = array()): SectionBuilder {
		return $this->collectionBuilder->section($section_id, $heading, $description_cb, $args);
	}

	/**
	 * Begin configuring a grouped set of fields within this section.
	 *
	 * @param string              $group_id       The group identifier.
	 * @param string              $heading        The human-readable group heading (optional, can be set via heading()).
	 * @param string|callable|null $description_cb The group description (string or callback).
	 * @param array<string,mixed> $args           Optional configuration (order, before/after callbacks, layout metadata, etc.).
	 *
	 * @return GroupBuilder<TRoot, SectionBuilder<TRoot>> The fluent group builder instance.
	 */
	public function group(string $group_id, string $heading = '', string|callable|null $description_cb = null, ?array $args = null): mixed {
		$args = $args ?? array();
		return new GroupBuilder(
			$this,
			$this->container_id,
			$this->section_id,
			$group_id,
			$heading,
			$description_cb,
			$this->updateFn,
			$args
		);
	}

	/**
	 * Begin configuring a semantic fieldset grouping within this section.
	 *
	 * @param string              $fieldset_id    The fieldset identifier.
	 * @param string              $heading        The legend to display for the fieldset (optional, can be set via heading()).
	 * @param string|callable|null $description_cb The fieldset description (string or callback).
	 * @param array<string,mixed> $args           Optional configuration (order, before/after callbacks, style metadata, etc.).
	 *
	 * @return FieldsetBuilderInterface<TRoot, SectionBuilderInterface<TRoot>> The fluent fieldset builder instance.
	 */
	public function fieldset(string $fieldset_id, string $heading = '', string|callable|null $description_cb = null, ?array $args = null): mixed {
		$args = $args ?? array();
		return new FieldsetBuilder(
			$this,
			$this->container_id,
			$this->section_id,
			$fieldset_id,
			$heading,
			$description_cb,
			$this->updateFn,
			$args
		);
	}

	/**
	 * Add a field with a component builder.
	 *
	 * Use this for components that have registered builder factories (e.g., fields.input,
	 * fields.select). Throws if the component has no registered builder factory.
	 *
	 * @param string $field_id The field ID.
	 * @param string $label The field label.
	 * @param string $component The component alias (must have a registered builder factory).
	 * @param array<string,mixed> $args Optional configuration (context, order, field_template).
	 *
	 * @return GenericFieldBuilder<SectionBuilder> The fluent proxy for field configuration.
	 *
	 * @throws \InvalidArgumentException If the component has no registered builder factory.
	 */
	public function field(string $field_id, string $label, string $component, array $args = array()): GenericFieldBuilder {
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

		$factory = $this->_get_component_builder_factory($component);
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

		$proxy = $this->_create_component_proxy(
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
	 * Factory method to create a ComponentBuilderProxy.
	 * Override in subclasses to return context-specific proxy types.
	 *
	 * @param ComponentBuilderDefinitionInterface $builder The component builder.
	 * @param string $component_alias The component alias.
	 * @param string|null $group_id The group ID (null for section-level fields).
	 * @param string|null $field_template The field template override.
	 * @param array<string,mixed> $component_context The component context.
	 *
	 * @return GenericFieldBuilder<SectionBuilder> The proxy instance.
	 */
	protected function _create_component_proxy(
		ComponentBuilderDefinitionInterface $builder,
		string $component_alias,
		?string $group_id,
		?string $field_template,
		array $component_context
	): GenericFieldBuilder {
		return new GenericFieldBuilder(
			$builder,
			$this,
			$this->updateFn,
			$this->container_id,
			$this->section_id,
			$component_alias,
			$group_id,
			$field_template,
			$component_context
		);
	}

	/**
	 * Set the before section for this section.
	 * Controls the before section template (page layout or collection wrapper).
	 *
	 * @param string $section_id The section ID to place before this one.
	 *
	 * @return self The builder instance for chaining.
	 */
	public function before(callable $before): static {
		$this->_update_meta('before', $before);
		return $this;
	}

	/**
	 * Set the after section for this section.
	 * Controls the after section template (page layout or collection wrapper).
	 *
	 * @param callable|null $after The after callback (null to clear).
	 *
	 * @return self The builder instance for chaining.
	 */
	public function after(callable $after): static {
		$this->_update_meta('after', $after);
		return $this;
	}

	/**
	 * Commit buffered data and return to the section builder.
	 *
	 * @return $this The SectionBuilder instance.
	 */
	public function end_group(): mixed {
		return $this;
	}

	/**
	 * Not valid in section context - throws exception.
	 *
	 * This method exists for API consistency with union return types.
	 *
	 * @return never
	 * @throws \RuntimeException Always throws - cannot end fieldset from section context.
	 */
	public function end_fieldset(): mixed {
		throw new \RuntimeException('Cannot call end_fieldset() from section context. You are not inside a fieldset.');
	}

	/**
	 * Return to the CollectionBuilder.
	 *
	 * @return TRoot The root builder instance.
	 */
	public function end_section(): mixed {
		return $this->collectionBuilder;
	}

	/**
	 * Get the FormsInterface instance from the collection builder.
	 *
	 * @return FormsInterface
	 */
	public function _get_forms(): FormsInterface {
		return $this->forms;
	}

	/**
	 * Get the component builder factory for the given component.
	 *
	 * @internal
	 *
	 * @param string $component The component alias.
	 *
	 * @return callable|null The component builder factory.
	 */
	public function _get_component_builder_factory(string $component): ?callable {
		if ($component === '') {
			return null;
		}

		if ($this->componentBuilderFactories === null) {
			$forms   = $this->_get_forms();
			$session = $forms->get_form_session();
			if ($session === null) {
				$this->componentBuilderFactories = array();
			} else {
				$this->componentBuilderFactories = $session->manifest()->builder_factories();
			}
		}

		return $this->componentBuilderFactories[$component] ?? null;
	}

	/**
	 * Apply a meta update to the section.
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
			default:
				$this->$key = $value;
		}

		$this->_emit_section_metadata();
	}

	/**
	 * Get the update callback for the section.
	 *
	 * @return callable The update callback.
	 */
	protected function _get_update_callback(): callable {
		return $this->updateFn;
	}

	/**
	 * Get the update event name for the section.
	 *
	 * @return string The update event name.
	 */
	protected function _get_update_event_name(): string {
		return 'section_metadata';
	}

	/**
	 * Build the update payload for the section.
	 *
	 * @param string $key The meta key.
	 * @param mixed $value The meta value.
	 *
	 * @return array The update payload.
	 */
	protected function _build_update_payload(string $key, mixed $value): array {
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
	 * Emit the section metadata.
	 */
	private function _emit_section_metadata(): void {
		($this->updateFn)($this->_get_update_event_name(), $this->_build_update_payload('', null));
	}

	/**
	 * Normalize a style argument to a trimmed string.
	 *
	 * @param string|callable $style Style value or resolver callback returning a string.
	 *
	 * @return string
	 * @throws \InvalidArgumentException When the resolved value is not a string.
	 */
	protected function _resolve_style_arg(string|callable $style): string {
		$resolved = is_callable($style) ? $style() : $style;
		if (!is_string($resolved)) {
			throw new \InvalidArgumentException('Section style callback must return a string.');
		}
		return trim($resolved);
	}
}
