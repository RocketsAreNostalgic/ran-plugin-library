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
	 * field() method can return the builder or a fluent proxy for component builders.
	 *
	 * @param string $field_id The field identifier
	 * @param string $label The field label
	 * @param string $component The component to use
	 * @param array $args Optional arguments for the component
	 *
	 * @return self|ComponentBuilderProxy The builder or a fluent proxy for component builders
	 */
	public function field(string $field_id, string $label, string $component, array $args = array()): self|ComponentBuilderProxy;
}
