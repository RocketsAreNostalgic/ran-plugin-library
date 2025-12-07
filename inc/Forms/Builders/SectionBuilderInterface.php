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
	 * Add a field with a component builder to this section.
	 *
	 * @param  string $field_id
	 * @param  string $label
	 * @param  string $component
	 * @param  array  $args
	 *
	 * @return mixed
	 */
	public function field(string $field_id, string $label, string $component, array $args = array()): mixed;

	/**
	 * Set the field template for field wrapper customization.
	 *
	 * @param string $template_key The template key to use for field wrappers.
	 *
	 * @return static
	 */
	public function field_template(string $template_key): static;

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

	/**
	 * Set the default fieldset template for all fieldsets in this section.
	 *
	 * @param string $template_key The template key to use for fieldset containers.
	 *
	 * @return static
	 */
	public function fieldset_template(string $template_key): static;

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
	 * Set the default group template for all groups in this section.
	 *
	 * @param string $template_key The template key to use for group containers.
	 *
	 * @return static
	 */
	public function group_template(string $template_key): static;

	/**
	 * Set the section template for section container customization.
	 *
	 * @param string $template_key The template key to use for section container.
	 *
	 * @return static
	 */
	public function section_template(string $template_key): static;

	/**
	 * end_section() method returns the original Settings instance.
	 *
	 * @return mixed The root builder instance.
	 */
	public function end_section(): mixed;
}
