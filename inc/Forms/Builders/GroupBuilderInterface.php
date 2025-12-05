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

namespace Ran\PluginLib\Forms\Builders;

use Ran\PluginLib\Forms\Builders\BuilderFieldContainerInterface;

/**
 * @template TGroup of GroupBuilderInterface
 * @template TSection of SectionBuilderInterface
 */
interface GroupBuilderInterface extends BuilderFieldContainerInterface {
	/**
	 * Define a new field group within this section.
	 *
	 * @param string $group_id The group ID.
	 * @param string $title The group title.
	 * @param string|callable|null $description_cb Optional group description (string or callback).
	 * @param ?array<string,mixed> $args Optional configuration (order, before/after callbacks).
	 *
	 * @return TGroup The GroupBuilder instance (concrete type in implementations).
	 */
	public function group(string $group_id, string $title, string|callable|null $description_cb = null, ?array $args = array()): mixed;

	/**
	 * end_group() method returns the SectionBuilder instance.
	 *
	 * @return TSection The SectionBuilder instance (concrete type in implementations).
	 */
	public function end_group(): mixed;
}
