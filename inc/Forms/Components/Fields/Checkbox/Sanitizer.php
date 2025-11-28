<?php
/**
 * Checkbox component sanitizer.
 *
 * Sanitizes checkbox input by coercing values to the configured
 * checked_value or unchecked_value strings.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\Checkbox;

use Ran\PluginLib\Forms\Component\Sanitize\SanitizerBase;
use Ran\PluginLib\Forms\Validation\Helpers;

final class Sanitizer extends SanitizerBase {
	/**
	 * Sanitize checkbox value.
	 *
	 * Coerces the submitted value to either the checked_value or unchecked_value
	 * based on the context configuration. Handles various truthy/falsy representations.
	 *
	 * @param mixed               $value      The submitted value.
	 * @param array<string,mixed> $context    The field context containing checked_value/unchecked_value.
	 * @param callable            $emitNotice Callback to emit sanitization notices.
	 *
	 * @return string The sanitized value (checked_value or unchecked_value).
	 */
	protected function _sanitize_component(mixed $value, array $context, callable $emitNotice): mixed {
		$checkedValue   = Helpers::sanitizeString($context['checked_value'] ?? 'on', 'checked_value', $this->logger);
		$uncheckedValue = array_key_exists('unchecked_value', $context)
			? Helpers::sanitizeString($context['unchecked_value'] ?? '', 'unchecked_value', $this->logger)
			: '';

		// Already the checked value
		if ($value === $checkedValue) {
			return $checkedValue;
		}

		// Already the unchecked value
		if ($uncheckedValue !== '' && $value === $uncheckedValue) {
			return $uncheckedValue;
		}

		// Coerce truthy values to checked
		if ($this->_is_truthy($value)) {
			return $checkedValue;
		}

		// Everything else is unchecked
		return $uncheckedValue;
	}

	/**
	 * Check if a value represents a truthy checkbox state.
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
