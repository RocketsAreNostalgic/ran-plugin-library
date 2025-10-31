<?php
/**
 * BuilderInterface: Shared interface for child builders.
 *
 * @package Ran\PluginLib\Forms\Builders
 * @author  Ran Plugin Lib <bnjmnrsh@gmail.com>
 * @license GPL-2.0+ <http://www.gnu.org/licenses/gpl-2.0.txt>
 * @link    https://github.com/RocketsAreNostalgic
 * @since   0.2.0
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders;

/**
 * Shared interface for child builders (eg sections, groups, fields).
 *
 * Provides consistent API for building structures with sections,
 * template overrides, and priority/ordering across different WordPress contexts.
 */
interface BuilderChildInterface {
	/**
	 * Set the heading for the current container.
	 *
	 * @param string $heading The heading to use for the container.
	 *
	 * @return self The builder instance for chaining.
	 */
	public function heading(string $heading): self;

	/**
	 * Set the description for the current container.
	 *
	 * @param string $description The description to use for the container.
	 *
	 * @return self The builder instance for chaining.
	 */
	public function description(string $description): self;

	/**
	 * Set the template for this current container.
	 *
	 * @param string $template_key The template key to use for the wrapper.
	 *
	 * @return self The builder instance for chaining.
	 * @throws \InvalidArgumentException If template key is empty.
	 */
	public function template(string $template_key): self;

	/**
	 * Set the order for this container.
	 * Higher numbers typically mean higher priority or later rendering order.
	 *
	 * @param int $order The order value (must be >= 0).
	 *
	 * @return self The builder instance for chaining.
	 */
	public function order(int $order): self;

	/**
	 * Set the before section for this container.
	 * Controls the before section template (page layout or collection wrapper).
	 *
	 * @param callable $before The before callback.
	 *
	 * @return self The builder instance for chaining.
	 */
	public function before(callable $before): self;

	/**
	 * Set the after section for this container.
	 * Controls the after section template (page layout or collection wrapper).
	 *
	 * @param callable $after The after callback.
	 *
	 * @return self The builder instance for chaining.
	 */
	public function after(callable $after): self;
}
