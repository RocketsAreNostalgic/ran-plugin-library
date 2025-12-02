<?php
/**
 * SubmitControlsBuilderInterface: Interface for submit controls builder.
 *
 * @package Ran\PluginLib\Forms\Builders
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders;

use Ran\PluginLib\Forms\Builders\BuilderRootInterface;

interface SubmitControlsBuilderInterface {
	/**
	 * Register any component as a submit control.
	 *
	 * Unlike BuilderFieldContainerInterface::field(), this does not use component builders.
	 * Controls are registered directly without fluent proxy configuration.
	 *
	 * @param string $control_id Unique control identifier.
	 * @param string $label Display label.
	 * @param string $component Registered component alias.
	 * @param array<string,mixed> $args Optional configuration.
	 *
	 * @return static
	 */
	public function field(string $control_id, string $label, string $component, array $args = array()): static;
	/**
	 * Override submit wrapper template.
	 */
	public function template(string $template_key): self;

	/**
	 * Provide markup rendered before controls.
	 */
	public function before(?callable $before): self;

	/**
	 * Provide markup rendered after controls.
	 */
	public function after(?callable $after): self;

	/**
	 * Add a button control and return a fluent proxy for customization.
	 */
	public function button(string $control_id, string $label): self|SubmitControlButtonProxy;

	/**
	 * Return to parent builder.
	 */
	public function end_submit_controls(): BuilderRootInterface;

	/**
	 * Alias for end_submit_controls().
	 */
	public function end(): BuilderRootInterface;
}
