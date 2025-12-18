<?php
/**
 * Fluent number input field builder.
 *
 * Provides a specialized builder for numeric inputs with min, max, and step support.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\Number;

use Ran\PluginLib\Forms\Component\Build\ReadonlyComponentBuilderInterface;
use Ran\PluginLib\Forms\Component\Build\ComponentBuilderInputBase;

final class Builder extends ComponentBuilderInputBase implements ReadonlyComponentBuilderInterface {
	/** @var bool|callable */
	protected mixed $readonly = false;

	/**
	 * Marks the input as readonly.
	 *
	 * @param bool|callable $readonly Boolean or callable that returns bool.
	 * @return static
	 */
	public function readonly(bool|callable $readonly = true): static {
		$this->readonly = $readonly;
		return $this;
	}

	protected int|float|null $min  = null;
	protected int|float|null $max  = null;
	protected int|float|null $step = null;

	protected function _build_input_context(): array {
		$context = parent::_build_input_context();
		if (is_callable($this->readonly)) {
			$context['readonly'] = $this->readonly;
		} else {
			$this->_add_if_true($context, 'readonly', (bool) $this->readonly);
		}
		return $context;
	}

	/**
	 * Sets the minimum allowed value.
	 *
	 * @param int|float|null $min
	 * @return static
	 */
	public function min(int|float|null $min): static {
		$this->min = $min;
		return $this;
	}

	/**
	 * Sets the maximum allowed value.
	 *
	 * @param int|float|null $max
	 * @return static
	 */
	public function max(int|float|null $max): static {
		$this->max = $max;
		return $this;
	}

	/**
	 * Sets the step increment for the input.
	 *
	 * @param int|float|null $step Use 'any' for no step restriction (pass null and set via attribute if needed).
	 * @return static
	 */
	public function step(int|float|null $step): static {
		$this->step = $step;
		return $this;
	}

	/**
	 * Convenience method to set min and max together.
	 *
	 * @param int|float $min
	 * @param int|float $max
	 * @return static
	 */
	public function range(int|float $min, int|float $max): static {
		$this->min = $min;
		$this->max = $max;
		return $this;
	}

	protected function _build_component_context(): array {
		$context = $this->_build_input_context();

		// Force input type to number
		$context['input_type'] = 'number';

		// Add numeric constraints
		if ($this->min !== null) {
			$context['min'] = $this->min;
		}
		if ($this->max !== null) {
			$context['max'] = $this->max;
		}
		if ($this->step !== null) {
			$context['step'] = $this->step;
		}

		return $context;
	}

	protected function _get_component(): string {
		return 'number';
	}

	/**
	 * Get the minimum value.
	 *
	 * @return int|float|null
	 */
	public function get_min(): int|float|null {
		return $this->min;
	}

	/**
	 * Get the maximum value.
	 *
	 * @return int|float|null
	 */
	public function get_max(): int|float|null {
		return $this->max;
	}

	/**
	 * Get the step value.
	 *
	 * @return int|float|null
	 */
	public function get_step(): int|float|null {
		return $this->step;
	}
}
