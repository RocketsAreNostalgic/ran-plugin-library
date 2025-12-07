<?php
/**
 * FieldsetBuilderInterface: Interface for fieldset builders.
 *
 * @template TRoot of BuilderRootInterface
 * @template TSection of SectionBuilderInterface<TRoot>
 *
 * @package Ran\PluginLib\Forms\Builders
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders;

/**
 * @template TSection of SectionBuilderInterface
 */
interface FieldsetBuilderInterface extends FieldContainerBuilderInterface {
	/**
	 * Define the visual style for the fieldset wrapper.
	 */
	public function style(string|callable $style): static;

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
	 * @return FieldsetBuilderInterface<TRoot, TSection>
	 */
	public function fieldset(string $fieldset_id, string $heading = '', string|callable|null $description_cb = null, ?array $args = null): FieldsetBuilderInterface;

	/**
	 * Commit buffered data and return to the section builder.
	 *
	 * @return TSection
	 */
	public function end_fieldset(): mixed;
}
