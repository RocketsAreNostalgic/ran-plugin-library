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
	 * field() method returns a fluent proxy for configuring field-level options.
	 *
	 * For components with builder factories, returns ComponentBuilderProxy.
	 * For simple components without builders, returns SimpleFieldProxy.
	 * Some specialized builders (like SubmitControlsBuilder) may return static.
	 *
	 * @param string $field_id The field identifier
	 * @param string $label The field label
	 * @param string $component The component to use
	 * @param array $args Optional arguments for the component
	 *
	 * @return ComponentBuilderProxy|SimpleFieldProxy|static The fluent proxy for field configuration
	 */
	public function field(string $field_id, string $label, string $component, array $args = array()): ComponentBuilderProxy|SimpleFieldProxy|static;
}
