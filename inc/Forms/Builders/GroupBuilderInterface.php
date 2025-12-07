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

/**
 * @template TSection of SectionBuilderInterface
 */
interface GroupBuilderInterface extends FieldContainerBuilderInterface {
	/**
	 * end_group() method returns the SectionBuilder instance.
	 *
	 * @return TSection The SectionBuilder instance (concrete type in implementations).
	 */
	public function end_group(): mixed;
}
