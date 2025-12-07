<?php
/**
 * FieldsetBuilderInterface: Interface for fieldset builders.
 *
 * @template TSection of SectionBuilderInterface
 *
 * @package Ran\PluginLib\Forms\Builders
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders;

/**
 * @template TSection of SectionBuilderInterface
 */
interface FieldsetBuilderInterface {
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
	 * Define the visual style for the fieldset wrapper.
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
	 * Set the form attribute for this fieldset.
	 * Associates the fieldset with a form element by its ID.
	 */
	public function form(string $form_id): static;

	/**
	 * Set the name attribute for this fieldset.
	 */
	public function name(string $name): static;

	/**
	 * Set the disabled state for this fieldset.
	 * When disabled, all form controls within the fieldset are disabled.
	 */
	public function disabled(bool $disabled = true): static;

	/**
	 * Open a sibling fieldset on the same section.
	 *
	 * @param string $fieldset_id    The fieldset identifier.
	 * @param string $heading        The legend (optional, can be set via heading()).
	 * @param string|callable|null $description_cb The fieldset description (string or callback).
	 * @param array<string,mixed>|null $args Optional configuration.
	 *
	 * @return FieldsetBuilderInterface<TSection>
	 */
	public function fieldset(string $fieldset_id, string $heading = '', string|callable|null $description_cb = null, ?array $args = null): FieldsetBuilderInterface;

	/**
	 * Commit buffered data and return to the section builder.
	 *
	 * @return TSection
	 */
	public function end_fieldset(): mixed;
}
