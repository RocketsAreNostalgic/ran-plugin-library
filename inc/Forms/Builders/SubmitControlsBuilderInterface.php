<?php
/**
 * SubmitControlsBuilderInterface: Interface for submit controls builder.
 *
 * @package Ran\PluginLib\Forms\Builders
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders;

use Ran\PluginLib\Forms\Builders\BuilderRootInterface;
use Ran\PluginLib\Forms\Builders\BuilderFieldContainerInterface;

interface SubmitControlsBuilderInterface extends BuilderFieldContainerInterface {
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
