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

/**
 * @template TGroup of GroupBuilderInterface
 * @template TFieldset of FieldsetBuilderInterface
 * @template TSection of SectionBuilderInterface
 */
interface SectionBuilderInterface extends BuilderChildInterface, BuilderFieldContainerInterface {
	/**
	 * Define a new field group within this section.
	 *
	 * @param string $group_id The group ID.
	 * @param string $title The group title.
	 * @param string|callable|null $description_cb Optional group description (string or callback).
	 * @param array<string,mixed>|null $args Optional configuration (order, before/after callbacks, classes, etc.).
	 *
	 * @return TGroup The GroupBuilder instance (concrete type in implementations).
	 */
	public function group(string $group_id, string $title, string|callable|null $description_cb = null, ?array $args = null): mixed;

	/**
	 * Define a new fieldset group within this section.
	 *
	 * @param string $fieldset_id The fieldset ID.
	 * @param string $title The fieldset legend/title.
	 * @param string|callable|null $description_cb Optional description (string or callback).
	 * @param array<string,mixed>|null $args Optional configuration (order, before/after callbacks, style metadata, etc.).
	 *
	 * @return TFieldset The fieldset builder instance (concrete type in implementations).
	 */
	public function fieldset(string $fieldset_id, string $title, string|callable|null $description_cb = null, ?array $args = null): mixed;

	// Note: field() is inherited from BuilderFieldContainerInterface with mixed return type.

	/**
	 * end_group() method returns this SectionBuilder instance.
	 *
	 * @return TSection The SectionBuilder instance (concrete type in implementations).
	 */
	public function end_group(): mixed;

	/**
	 * end_section() method returns the original Settings instance.
	 *
	 * @return mixed The root builder instance.
	 */
	public function end_section(): mixed;
}
