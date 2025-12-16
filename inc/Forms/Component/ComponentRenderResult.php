<?php
/**
 * ComponentRenderResult: Immutable value object representing the outcome of a form component render.
 *
 * @package Ran\PluginLib\Forms
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Component;

use Ran\PluginLib\Util\Validate;

/**
 * Captures a form component's rendered markup.
 */
final readonly class ComponentRenderResult {
	/**
	 * @param string $markup Rendered HTML markup for the component.
	 * @param array $context_schema Schema definition for component context validation.
	 */
	public function __construct(
		public string $markup,
		public array $context_schema = array()
	) {
		if (!Validate::string()->min_length(0, $this->markup)) {
			throw new \InvalidArgumentException('Component markup must be a string.');
		}
	}
}
