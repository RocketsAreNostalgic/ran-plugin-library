<?php
/**
 * SectionBuilderInterface: Interface for section builders.
 *
 * @package Ran\PluginLib\Settings
 * @author  Ran Plugin Lib <bnjmnrsh@gmail.com>
 * @license GPL-2.0+ <http://www.gnu.org/licenses/gpl-2.0.txt>
 * @link    https://github.com/RocketsAreNostalgic
 * @since   0.2.0
 */

namespace Ran\PluginLib\Settings;

use Ran\PluginLib\Settings\SectionGroupBuilder;
use Ran\PluginLib\Forms\Component\Build\BuilderDefinitionInterface;

interface SectionBuilderInterface {
	/**
	 * field_group() method returns a SectionGroupBuilder instance for fluent field chaining.
	 */
	public function group(string $group_id, string $title, ?callable $before = null, ?callable $after = null, ?int $order = null): SectionGroupBuilder;

	/**
	 * field() method returns this SectionBuilder instance.
	 */
	public function field(string $field_id, string $label, string $component, array $component_context = array(), ?int $order = null): SectionBuilder;

	/**
	 * Attach a reusable field definition to this section.
	 */
	public function definition(BuilderDefinitionInterface $definition): SectionBuilder;

	/**
	 * end_section() method returns the original Settings instance.
	 *
	 * @return CollectionBuilderInterface The Settings instance.
	 */
	public function end_section(): CollectionBuilderInterface;
}
