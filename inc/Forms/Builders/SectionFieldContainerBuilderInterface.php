<?php
/**
 * SectionFieldContainerBuilderInterface: Shared contract for section-scoped field containers.
 *
 * @template TRoot of BuilderRootInterface
 * @template TSection of SectionBuilderInterface<TRoot>
 *
 * @package Ran\PluginLib\Forms\Builders
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders;

use Ran\PluginLib\Forms\Builders\ComponentBuilderProxy;
use Ran\PluginLib\Forms\Builders\BuilderRootInterface;

/**
 * Describes fluent builders that live within a section and emit field payloads.
 */
interface SectionFieldContainerBuilderInterface extends BuilderFieldContainerInterface {
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
	public function order(?int $order): static;

	/**
	 * Register a callback to run before rendering the container.
	 *
	 * @return static
	 */
	public function before(?callable $before): static;

	/**
	 * Register a callback to run after rendering the container.
	 *
	 * @return static
	 */
	public function after(?callable $after): static;
}
