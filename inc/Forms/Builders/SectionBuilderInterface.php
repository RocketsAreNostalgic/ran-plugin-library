<?php
/**
 * SectionBuilderInterface: Interface for section builders.
 *
 * @template TRoot of BuilderRootInterface
 *
 * @package Ran\PluginLib\Settings
 * @author  Ran Plugin Lib <bnjmnrsh@gmail.com>
 * @license GPL-2.0+ <http://www.gnu.org/licenses/gpl-2.0.txt>
 * @link    https://github.com/RocketsAreNostalgic
 * @since   0.2.0
 */

namespace Ran\PluginLib\Forms\Builders;

use Ran\PluginLib\Forms\Builders\SectionBuilder;
use Ran\PluginLib\Forms\Builders\GroupBuilder;
use Ran\PluginLib\Forms\Builders\ComponentBuilderProxy;
use Ran\PluginLib\Forms\Builders\BuilderRootInterface;
use Ran\PluginLib\Forms\Builders\BuilderFieldContainerInterface;

interface SectionBuilderInterface extends BuilderChildInterface, BuilderFieldContainerInterface {
	/**
	 * Define a new field group within this section.
	 *
	 * @param string $group_id The group ID.
	 * @param string $title The group title.
	 * @param callable|null $description_cb Optional group description callback, follows add_settings_section() pattern
	 * @param array<string,mixed>|null $args Optional configuration (order, before/after callbacks, classes, etc.).
	 *
	 * @return GroupBuilder<TRoot, SectionBuilder<TRoot>> The GroupBuilder instance.
	 */
	public function group(string $group_id, string $title, ?callable $description_cb = null, ?array $args = null): GroupBuilder;

	/**
	 * Define a new fieldset group within this section.
	 *
	 * @param string $fieldset_id The fieldset ID.
	 * @param string $title The fieldset legend/title.
	 * @param callable|null $description_cb Optional description callback.
	 * @param array<string,mixed>|null $args Optional configuration (order, before/after callbacks, style metadata, etc.).
	 *
	 * @return FieldsetBuilderInterface<TRoot, SectionBuilderInterface<TRoot>> The fieldset builder instance.
	 */
	public function fieldset(string $fieldset_id, string $title, ?callable $description_cb = null, ?array $args = null): FieldsetBuilderInterface;

	/**
	 * Define a new field within this section.
	 *
	 * @param string $field_id The field ID.
	 * @param string $label The field label.
	 * @param string $component The component alias.
	 * @param array<string,mixed> $args Optional configuration (context, order, field_template).
	 *
	 * @return ComponentBuilderProxy|static
	 */
	public function field(string $field_id, string $label, string $component, array $args = array()): ComponentBuilderProxy|SimpleFieldProxy;

	/**
	 * end_group() method returns this SectionBuilder instance.
	 *
	 * @return SectionBuilder<TRoot> The SectionBuilder instance.
	 */
	public function end_group(): SectionBuilder;

	/**
	 * end_section() method returns the original Settings instance.
	 *
	 * @return TRoot The root builder instance.
	 */
	public function end_section(): BuilderRootInterface;
}
