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
	 * end_group() method returns the SectionBuilder instance.
	 *
	 * @return TSection The SectionBuilder instance (concrete type in implementations).
	 */
	public function end_group(): mixed;
}
