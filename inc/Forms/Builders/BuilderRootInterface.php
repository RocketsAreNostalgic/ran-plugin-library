<?php
/**
 * BuilderRootInterface: Shared interface for root template builders.
 *
 * Provides consistent API for building structures with sections,
 * template overrides, and priority/ordering across different contexts.
 *
 * @package Ran\PluginLib\Forms\Builders
 * @author  Ran Plugin Lib <bnjmnrsh@gmail.com>
 * @license GPL-2.0+ <http://www.gnu.org/licenses/gpl-2.0.txt>
 * @link    https://github.com/RocketsAreNostalgic
 * @since   0.2.0
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders;

use Ran\PluginLib\Forms\Builders\Capabilities\HasBeforeAfterInterface;
use Ran\PluginLib\Forms\Builders\Capabilities\HasDescriptionInterface;
use Ran\PluginLib\Forms\Builders\Capabilities\HasOrderInterface;
use Ran\PluginLib\Forms\FormsInterface;

/**
 * Shared interface for builders (eg pages, collections, or top level forms).
 *
 * Provides consistent API for building structures with sections,
 * template overrides, and priority/ordering across different WordPress contexts.
 */
interface BuilderRootInterface extends HasDescriptionInterface, HasOrderInterface, HasBeforeAfterInterface {
	/**
	 * Set the heading for the current container.
	 *
	 * @param string $heading The heading to use for the container.
	 *
	 * @return static
	 */
	public function heading(string $heading): static;

	/**
	 * Set the description for the current container.
	 *
	 * @param string|callable $description A string or callback returning the description.
	 *
	 * @return static
	 */
	public function description(string|callable $description): static;

	/**
	 * Set the template for this current container.
	 *
	 * @param string|callable|null $template_key The template key to use for the wrapper.
	 *
	 * @return static
	 * @throws \InvalidArgumentException If template key is empty.
	 */
	public function template(string|callable|null $template_key): static;

	/**
	 * Set the order for this container.
	 *
	 * @param int $order The order value.
	 *
	 * @return static
	 */
	public function order(?int $order): static;

	/**
	 * Register a callback to run before rendering the container.
	 *
	 * @param callable $before The before callback.
	 *
	 * @return static
	 */
	public function before(?callable $before): static;

	/**
	 * Register a callback to run after rendering the container.
	 *
	 * @param callable $after The after callback.
	 *
	 * @return static
	 */
	public function after(?callable $after): static;

	/**
	 * Define a new section within this settings container.
	 *
	 * @param string $section_id The section ID.
	 * @param string $title The section title.
	 * @param string|callable|null $description_cb Optional section description (string or callback).
	 * @param array<string,mixed>|null $args Optional configuration (order, before/after callbacks, classes, etc.).
	 *
	 * @return SectionBuilderInterface The section builder instance.
	 */
	public function section(string $section_id, string $title, string|callable|null $description_cb = null, ?array $args = null): SectionBuilderInterface;

	/**
	 * Complete the builder and return to the main form instance.
	 * Allows chaining to other builders or boot().
	 *
	 * @return FormsInterface The main form instance.
	 */
	public function end(): mixed;

	/**
	 * Expose the root FormsInterface to child builders.
	 *
	 * @return FormsInterface
	 */
	public function __get_forms(): FormsInterface;

	/**
	 * Alias for backward compatibility with existing builder APIs.
	 *
	 * @return FormsInterface
	 */
	public function get_settings(): FormsInterface;
}
