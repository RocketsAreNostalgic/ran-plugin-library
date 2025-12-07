<?php
/**
 * FieldContainerBuilderInterface: Shared contract for section-scoped field containers.
 *
 * @template TRoot of BuilderRootInterface
 * @template TSection of SectionBuilderInterface<TRoot>
 * @template TFieldProxy of ComponentBuilderProxy
 *
 * @package Ran\PluginLib\Forms\Builders
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders;

/**
 * Describes fluent builders that live within a section and emit field payloads.
 */
interface FieldContainerBuilderInterface {
	/**
	 * Set the container heading.
	 *
	 * @return static
	 */
	public function heading(string $heading): static;

	/**
	 * Set the optional container description.
	 *
	 * @param string|callable $description A string or callback returning the description.
	 *
	 * @return static
	 */
	public function description(string|callable $description): static;

	/**
	 * Configure a template override for the container wrapper.
	 *
	 * @return static
	 */
	public function template(string $template_key): static;

	/**
	 * Configure a style override for the container wrapper.
	 *
	 * @return static
	 */
	public function style(string|callable $style): self;

	/**
	 * Adjust the container order relative to siblings.
	 *
	 * @return static
	 */
	public function order(int $order): static;

	/**
	 * Register a callback to run before rendering the container.
	 *
	 * @return static
	 */
	public function before(callable $before): static;

	/**
	 * Register a callback to run after rendering the container.
	 *
	 * @return static
	 */
	public function after(callable $after): static;

	/**
	 * Add a field with a component builder.
	 *
	 * Use this for components that have registered builder factories (e.g., fields.input,
	 * fields.select). Returns a ComponentBuilderProxy with full fluent configuration.
	 *
	 * @param string $field_id The field identifier.
	 * @param string $label The field label.
	 * @param string $component The component alias (must have a registered builder factory).
	 * @param array<string,mixed> $args Optional arguments for the component.
	 *
	 * @return TFieldProxy The fluent proxy for field configuration.
	 *
	 * @throws \InvalidArgumentException If the component has no registered builder factory.
	 */
	public function field(string $field_id, string $label, string $component, array $args = array()): mixed;
}
