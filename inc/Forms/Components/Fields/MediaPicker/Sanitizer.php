<?php
/**
 * MediaPicker component sanitizer.
 *
 * Sanitizes media picker input by coercing values to positive integers
 * (WordPress attachment IDs).
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\MediaPicker;

use Ran\PluginLib\Forms\Component\Sanitize\SanitizerBase;
use Ran\PluginLib\Forms\Validation\Helpers;

final class Sanitizer extends SanitizerBase {
	/**
	 * Sanitize media picker value.
	 *
	 * Handles both single selection (integer) and multiple selection (array of integers).
	 * Coerces values to positive integers and filters out invalid IDs.
	 *
	 * @param mixed               $value      The submitted value (int, string, or array).
	 * @param array<string,mixed> $context    The field context.
	 * @param callable            $emitNotice Callback to emit sanitization notices.
	 *
	 * @return int|array<int,int>|string The sanitized media ID(s) or empty string/array.
	 */
	protected function _sanitize_component(mixed $value, array $context, callable $emitNotice): mixed {
		$multiple = Helpers::sanitizeBoolean($context['multiple'] ?? false, 'media_multiple', $this->logger);

		// Empty value
		if ($value === '' || $value === null) {
			return $multiple ? array() : '';
		}

		// Handle array of IDs
		if (is_array($value)) {
			$sanitized = array();
			foreach ($value as $id) {
				$sanitizedId = $this->_sanitize_single_id($id);
				if ($sanitizedId > 0) {
					$sanitized[] = $sanitizedId;
				}
			}

			if (count($sanitized) < count($value)) {
				$emitNotice($this->_translate('Some invalid media IDs were removed.'));
			}

			return $multiple ? $sanitized : ($sanitized[0] ?? '');
		}

		// Handle single ID
		$sanitizedId = $this->_sanitize_single_id($value);

		if ($sanitizedId <= 0) {
			$emitNotice($this->_translate('Invalid media ID was cleared.'));
			return $multiple ? array() : '';
		}

		return $multiple ? array($sanitizedId) : $sanitizedId;
	}

	/**
	 * Sanitize a single media ID.
	 *
	 * @param mixed $value The value to sanitize.
	 *
	 * @return int The sanitized ID (0 if invalid).
	 */
	private function _sanitize_single_id(mixed $value): int {
		// Handle empty values
		if ($value === '' || $value === null) {
			return 0;
		}

		// Must be scalar
		if (!is_scalar($value)) {
			return 0;
		}

		// Coerce to integer
		$intValue = (int) $value;

		// Must be positive
		return $intValue > 0 ? $intValue : 0;
	}
}
