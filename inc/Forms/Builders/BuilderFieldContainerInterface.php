<?php
/**
 * BuilderFieldContainerInterface: Shared interface for builders.
 *
 * @package Ran\PluginLib\Forms\Builder
 * @author  Ran Plugin Lib <bnjmnrsh@gmail.com>
 * @license GPL-2.0+ <http://www.gnu.org/licenses/gpl-2.0.txt>
 * @link    https://github.com/RocketsAreNostalgic
 * @since   0.2.0
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders;

use Ran\PluginLib\Forms\Builders\BuilderRootInterface;

/**
 * Shared interface for builders (collections and pages).
 *
 * Provides consistent API for building structures with sections,
 * template overrides, and priority/ordering across different WordPress contexts.
 */
interface BuilderFieldContainerInterface {
	/**
	 * Add a field with a component builder.
	 *
	 * Use this for components that have registered builder factories (e.g., fields.input,
	 * fields.select). Returns a ComponentBuilderProxy with full fluent configuration.
	 *
	 * @param string $field_id The field identifier.
	 * @param string $label The field label.
	 * @param string $component The component alias (must have a registered builder factory).
	 * @param array<string,mixed> $args Optional arguments for the component.
	 *
	 * @return ComponentBuilderProxy The fluent proxy for field configuration.
	 *
	 * @throws \InvalidArgumentException If the component has no registered builder factory.
	 */
	public function field(string $field_id, string $label, string $component, array $args = array()): ComponentBuilderProxy;

	/**
	 * Add a simple field without a component builder.
	 *
	 * Use this for components that render directly without builder support (e.g., raw HTML,
	 * custom markup). Returns a SimpleFieldProxy with limited fluent configuration.
	 *
	 * @param string $field_id The field identifier.
	 * @param string $label The field label.
	 * @param string $component The component alias.
	 * @param array<string,mixed> $args Optional arguments for the component.
	 *
	 * @return SimpleFieldProxy The fluent proxy for simple field configuration.
	 */
	public function field_simple(string $field_id, string $label, string $component, array $args = array()): SimpleFieldProxy;
}
