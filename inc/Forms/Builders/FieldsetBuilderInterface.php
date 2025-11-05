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

interface FieldsetBuilderInterface extends SectionFieldContainerBuilderInterface {
	/**
	 * Define the visual style for the fieldset wrapper.
	 */
	public function style(string $style): self;

	/**
	 * Flag the fieldset as required when any contained field requires a value.
	 */
	public function required(bool $required = true): self;

	/**
	 * Commit buffered data and return to the section builder.
	 *
	 * @return TSection
	 */
	public function end_fieldset(): SectionBuilderInterface;

	/**
	 * Open a sibling fieldset on the same section.

	 * @return FieldsetBuilderInterface<TRoot, TSection>
	 */
	public function fieldset(string $fieldset_id, string $heading, ?callable $description_cb = null, ?array $args = null): FieldsetBuilderInterface;
}
