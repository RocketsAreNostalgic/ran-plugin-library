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

use Ran\PluginLib\Forms\Builders\BuilderChildInterface;
use Ran\PluginLib\Forms\FormsInterface;

/**
 * Shared interface for builders (eg pages, collections, or top level forms).
 *
 * Provides consistent API for building structures with sections,
 * template overrides, and priority/ordering across different WordPress contexts.
 */
interface BuilderRootInterface extends BuilderChildInterface {
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
	public function get_forms(): FormsInterface;

	/**
	 * Alias for backward compatibility with existing builder APIs.
	 *
	 * @return FormsInterface
	 */
	public function get_settings(): FormsInterface;
}
