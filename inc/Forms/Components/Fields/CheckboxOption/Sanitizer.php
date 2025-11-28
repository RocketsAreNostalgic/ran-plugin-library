<?php
/**
 * Checkbox option component sanitizer.
 *
 * Sanitizes checkbox option input by coercing truthy values to the
 * configured option value string.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\CheckboxOption;

use Ran\PluginLib\Forms\Component\Sanitize\SanitizerBase;
use Ran\PluginLib\Forms\Validation\Helpers;

final class Sanitizer extends SanitizerBase {
	/**
	 * Sanitize checkbox option value.
	 *
	 * Coerces the submitted value to either the option value string
	 * (if truthy) or null (if falsy). Unlike Checkbox which has both
	 * checked_value and unchecked_value, CheckboxOption only has a
	 * single value and returns null when unchecked.
	 *
	 * @param mixed               $value      The submitted value.
	 * @param array<string,mixed> $context    The field context containing 'value' key.
	 * @param callable            $emitNotice Callback to emit sanitization notices.
	 *
	 * @return string|null The option value if checked, null if unchecked.
	 */
	protected function _sanitize_component(mixed $value, array $context, callable $emitNotice): mixed {
		$optionValue = Helpers::sanitizeString($context['value'] ?? 'on', 'option_value', $this->logger);

		// Already the option value
		if ($value === $optionValue) {
			return $optionValue;
		}

		// Truthy values map to option value
		if ($this->_is_truthy($value)) {
			return $optionValue;
		}

		// Falsy values return null (unchecked state)
		return null;
	}

	/**
	 * Check if a value represents a truthy/checked state.
	 *
	 * @param mixed $value The value to check.
	 *
	 * @return bool True if the value represents a checked state.
	 */
	private function _is_truthy(mixed $value): bool {
		if ($value === true) {
			return true;
		}

		if ($value === 1 || $value === '1') {
			return true;
		}

		if (is_string($value)) {
			$lower = strtolower($value);
			return in_array($lower, array('on', 'yes', 'true', 'checked'), true);
		}

		return false;
	}
}
