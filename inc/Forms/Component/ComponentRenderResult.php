<?php
/**
 * ComponentRenderResult: Immutable value object representing the outcome of a form component render.
 *
 * @package Ran\PluginLib\Forms
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Component;

use Ran\PluginLib\Util\Validate;
use Ran\PluginLib\EnqueueAccessory\StyleDefinition;
use Ran\PluginLib\EnqueueAccessory\ScriptDefinition;

/**
 * Captures a form component's rendered markup and optional asset dependencies.
 */
final readonly class ComponentRenderResult {
	/**
	 * @param string $markup Rendered HTML markup for the component.
	 * @param ScriptDefinition|null $script Optional script definition required by the component.
	 * @param StyleDefinition|null $style Optional style definition required by the component.
	 * @param bool $requires_media Whether the WordPress media picker assets must be enqueued.
	 */
	public function __construct(
		public string $markup,
		public ?ScriptDefinition $script = null,
		public ?StyleDefinition $style = null,
		public bool $requires_media = false,
		public bool $repeatable = false,
		public array $context_schema = array()
	) {
		if (!Validate::string()->min_length(0, $this->markup)) {
			throw new \InvalidArgumentException('Component markup must be a string.');
		}
	}

	/**
	 * Whether any script assets were declared by the component.
	 */
	public function has_script(): bool {
		return $this->script instanceof ScriptDefinition;
	}

	/**
	 * Whether the component is repeatable.
	 */
	public function repeatable(): bool {
		return $this->repeatable;
	}

	/**
	 * Whether any style assets were declared by the component.
	 */
	public function has_style(): bool {
		return $this->style instanceof StyleDefinition;
	}

	/**
	 * Convenience helper indicating whether the component declared any assets at all.
	 */
	public function has_assets(): bool {
		return $this->has_script() || $this->has_style() || $this->requires_media;
	}
}
