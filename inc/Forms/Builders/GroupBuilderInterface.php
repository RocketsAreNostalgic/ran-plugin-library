<?php
/**
 * GroupBuilderInterface: Interface for group builders.
 *
 * @package Ran\PluginLib\Settings
 * @author  Ran Plugin Lib <bnjmnrsh@gmail.com>
 * @license GPL-2.0+ <http://www.gnu.org/licenses/gpl-2.0.txt>
 * @link    https://github.com/RocketsAreNostalgic
 * @since   0.2.0
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders;

/**
 * @template TSection of SectionBuilderInterface
 */
interface GroupBuilderInterface {
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
	public function style(string|callable $style): static;

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
	 * @param string $field_id The field identifier.
	 * @param string $label The field label.
	 * @param string $component The component alias.
	 * @param array<string,mixed> $args Optional arguments.
	 *
	 * @return mixed The fluent proxy for field configuration.
	 */
	public function field(string $field_id, string $label, string $component, array $args = array()): mixed;

	/**
	 * Add raw HTML content to the group.
	 *
	 * This is an escape hatch for injecting arbitrary markup into the form.
	 * The content is rendered inline in declaration order, without any wrapper.
	 *
	 * @param string|callable $content HTML string or callable that returns HTML.
	 *                                 Callable receives array with 'container_id', 'section_id', 'group_id', 'values'.
	 * @return static
	 */
	public function html(string|callable $content): static;

	/**
	 * Add a horizontal rule to the group.
	 *
	 * Returns a fluent builder for configuring the hr element.
	 *
	 * @return HrBuilder<static> The hr builder for configuration.
	 */
	public function hr(): HrBuilder;

	/**
	 * Add a non-input element (button, link, etc.) to the group.
	 *
	 * Unlike field(), element() is for components that don't submit form data.
	 * The returned builder provides styling methods but not input-specific ones.
	 *
	 * @param string $element_id The element identifier.
	 * @param string $label The element label/text.
	 * @param string $component The component alias (e.g., 'elements.button', 'elements.button-link').
	 * @param array<string,mixed> $args Optional arguments including 'context' for component-specific config.
	 *
	 * @return GenericElementBuilder<static> The element builder for configuration.
	 */
	public function element(string $element_id, string $label, string $component, array $args = array()): GenericElementBuilder;

	/**
	 * end_group() method returns the SectionBuilder instance.
	 *
	 * @return TSection The SectionBuilder instance (concrete type in implementations).
	 */
	public function end_group(): mixed;
}
