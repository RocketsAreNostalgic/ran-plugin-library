<?php
/**
 * Number component normalizer.
 *
 * Normalizes numeric input fields with min, max, and step attribute support.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\Number;

use Ran\PluginLib\Forms\Component\Normalize\NormalizerInputBase;

final class Normalizer extends NormalizerInputBase {
	/**
	 * Normalize number-specific context.
	 *
	 * Forces input_type to 'number' and handles min/max/step attributes.
	 *
	 * @param array<string,mixed> $context Component context.
	 * @return array<string,mixed> Normalized context.
	 */
	protected function _normalize_component_specific(array $context): array {
		// Force input type to number
		$context['input_type']         = 'number';
		$context['attributes']['type'] = 'number';

		// Normalize numeric constraints
		$context = $this->_normalize_numeric_constraints($context);

		// Use the complete input normalization pipeline
		return $this->_complete_input_normalization($context, 'number');
	}

	/**
	 * Normalize min, max, and step attributes.
	 *
	 * @param array<string,mixed> $context Component context.
	 * @return array<string,mixed> Normalized context.
	 */
	private function _normalize_numeric_constraints(array $context): array {
		$attributes = &$context['attributes'];

		// Handle min attribute
		if (isset($context['min'])) {
			$min = $this->_normalize_numeric_value($context['min']);
			if ($min !== null) {
				$attributes['min'] = (string) $min;
				$context['min']    = $min;
			}
		}

		// Handle max attribute
		if (isset($context['max'])) {
			$max = $this->_normalize_numeric_value($context['max']);
			if ($max !== null) {
				$attributes['max'] = (string) $max;
				$context['max']    = $max;
			}
		}

		// Handle step attribute
		if (isset($context['step'])) {
			$step = $this->_normalize_numeric_value($context['step']);
			if ($step !== null && $step > 0) {
				$attributes['step'] = (string) $step;
				$context['step']    = $step;
			}
		}

		return $context;
	}

	/**
	 * Normalize a numeric value to int or float.
	 *
	 * @param mixed $value The value to normalize.
	 * @return int|float|null The normalized value or null if invalid.
	 */
	private function _normalize_numeric_value(mixed $value): int|float|null {
		if ($value === null || $value === '') {
			return null;
		}

		if (!is_numeric($value)) {
			return null;
		}

		// Use float if value contains decimal, otherwise int
		$stringValue = (string) $value;
		if (str_contains($stringValue, '.')) {
			return (float) $value;
		}

		return (int) $value;
	}
}
