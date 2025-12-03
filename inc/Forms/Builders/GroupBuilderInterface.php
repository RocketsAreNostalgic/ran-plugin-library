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

interface GroupBuilderInterface extends BuilderFieldContainerInterface {
	/**
	 * Define a new field group within this section.
	 *
	 * @param string $group_id The group ID.
	 * @param string $title The group title.
	 * @param callable|null $description_cb Optional group description callback, follows add_settings_section() pattern
	 * @param ?array<string,mixed> $args Optional configuration (order, before/after callbacks).
	 *
	 * @return GroupBuilderInterface The GroupBuilder instance.
	 */
	public function group(string $group_id, string $title, ?callable $description_cb = null, ?array $args = array()): GroupBuilderInterface;

	/**
	 * end_group() method returns the SectionBuilder instance.
	 *
	 * @return SectionBuilderInterface The SectionBuilder instance.
	 */
	public function end_group(): SectionBuilderInterface;
}
