<?php
/**
 * Input component sanitizer.
 *
 * Sanitizes input values based on the input type (text, email, url, number, etc.).
 * Each input type has specific sanitization rules to normalize and clean data.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\Input;

use Ran\PluginLib\Forms\Validation\Helpers;
use Ran\PluginLib\Forms\Component\Sanitize\SanitizerBase;

class Sanitizer extends SanitizerBase {
	/**
	 * Sanitize input value based on input type.
	 *
	 * Delegates to type-specific sanitization methods based on the input_type
	 * in context. Unknown types default to text sanitization.
	 *
	 * @param mixed               $value      The submitted value.
	 * @param array<string,mixed> $context    The field context containing input_type.
	 * @param callable            $emitNotice Callback to emit sanitization notices.
	 *
	 * @return mixed The sanitized value.
	 */
	protected function _sanitize_component(mixed $value, array $context, callable $emitNotice): mixed {
		$type = isset($context['input_type']) ? (string) $context['input_type'] : 'text';

		return match ($type) {
			'email' => $this->_sanitize_email($value),
			'url'   => $this->_sanitize_url($value),
			'tel'   => $this->_sanitize_phone($value),
			'number', 'range' => $this->_sanitize_number($value, $context, $emitNotice),
			'date'           => $this->_sanitize_date($value),
			'datetime-local' => $this->_sanitize_datetime($value),
			'time'           => $this->_sanitize_time($value),
			'color'          => $this->_sanitize_color($value),
			'checkbox', 'radio' => $this->_sanitize_choice($value, $context),
			'hidden'   => $this->_sanitize_text($value),
			'password' => $this->_sanitize_password($value),
			default    => $this->_sanitize_text($value),
		};
	}

	/**
	 * Sanitize text input (default for text, search, hidden types).
	 *
	 * @param mixed $value The value to sanitize.
	 *
	 * @return string The sanitized string.
	 */
	private function _sanitize_text(mixed $value): string {
		if (!is_scalar($value)) {
			return '';
		}

		return sanitize_text_field((string) $value);
	}

	/**
	 * Sanitize password input.
	 *
	 * Passwords are trimmed but not otherwise sanitized to preserve special characters.
	 *
	 * @param mixed $value The value to sanitize.
	 *
	 * @return string The sanitized string.
	 */
	private function _sanitize_password(mixed $value): string {
		if (!is_scalar($value)) {
			return '';
		}

		// Passwords should preserve special characters but trim whitespace
		return trim((string) $value);
	}

	/**
	 * Sanitize email input.
	 *
	 * @param mixed $value The value to sanitize.
	 *
	 * @return string The sanitized email or empty string if invalid.
	 */
	private function _sanitize_email(mixed $value): string {
		if (!is_scalar($value) || $value === '') {
			return '';
		}

		$sanitized = \sanitize_email((string) $value);

		return $sanitized !== '' ? $sanitized : '';
	}

	/**
	 * Sanitize URL input.
	 *
	 * @param mixed $value The value to sanitize.
	 *
	 * @return string The sanitized URL.
	 */
	private function _sanitize_url(mixed $value): string {
		if (!is_scalar($value) || $value === '') {
			return '';
		}

		return \esc_url_raw((string) $value);
	}

	/**
	 * Sanitize phone number input.
	 *
	 * Keeps only digits, plus sign, hyphens, parentheses, and spaces.
	 *
	 * @param mixed $value The value to sanitize.
	 *
	 * @return string The sanitized phone number.
	 */
	private function _sanitize_phone(mixed $value): string {
		if (!is_scalar($value) || $value === '') {
			return '';
		}

		// Keep only phone-valid characters
		$sanitized = preg_replace('/[^\d\+\-\(\)\s]/', '', (string) $value);

		if ($sanitized === null) {
			return '';
		}

		// Collapse repeated whitespace and trim ends
		$sanitized = preg_replace('/\s+/', ' ', $sanitized) ?? $sanitized;

		return trim($sanitized);
	}

	/**
	 * Sanitize number input with optional range clamping.
	 *
	 * @param mixed               $value      The value to sanitize.
	 * @param array<string,mixed> $context    The field context with optional min/max/step.
	 * @param callable            $emitNotice Callback to emit notices.
	 *
	 * @return float|int|string The sanitized number or empty string if invalid.
	 */
	private function _sanitize_number(mixed $value, array $context, callable $emitNotice): float|int|string {
		if ($value === '' || $value === null) {
			return '';
		}

		if (!is_numeric($value)) {
			$emitNotice($this->_translate('Non-numeric value was cleared.'));
			return '';
		}

		// Determine if we should use float or int based on step or value format
		$useFloat = isset($context['step']) && str_contains((string) $context['step'], '.');
		$useFloat = $useFloat || str_contains((string) $value, '.');
		$numValue = $useFloat ? (float) $value : (int) $value;

		// Clamp to min if specified
		if (isset($context['min'])) {
			$min = $useFloat ? (float) $context['min'] : (int) $context['min'];
			if ($numValue < $min) {
				$numValue = $min;
				$emitNotice($this->_translate('Value was adjusted to minimum.'));
			}
		}

		// Clamp to max if specified
		if (isset($context['max'])) {
			$max = $useFloat ? (float) $context['max'] : (int) $context['max'];
			if ($numValue > $max) {
				$numValue = $max;
				$emitNotice($this->_translate('Value was adjusted to maximum.'));
			}
		}

		return $numValue;
	}

	/**
	 * Sanitize date input to ISO format (YYYY-MM-DD).
	 *
	 * @param mixed $value The value to sanitize.
	 *
	 * @return string The sanitized date or empty string if invalid.
	 */
	private function _sanitize_date(mixed $value): string {
		if (!is_scalar($value) || $value === '') {
			return '';
		}

		$timestamp = strtotime((string) $value);

		if ($timestamp === false) {
			return '';
		}

		return gmdate('Y-m-d', $timestamp);
	}

	/**
	 * Sanitize datetime-local input to ISO format (YYYY-MM-DDTHH:MM).
	 *
	 * @param mixed $value The value to sanitize.
	 *
	 * @return string The sanitized datetime or empty string if invalid.
	 */
	private function _sanitize_datetime(mixed $value): string {
		if (!is_scalar($value) || $value === '') {
			return '';
		}

		$timestamp = strtotime((string) $value);

		if ($timestamp === false) {
			return '';
		}

		return gmdate('Y-m-d\TH:i', $timestamp);
	}

	/**
	 * Sanitize time input to HH:MM format.
	 *
	 * @param mixed $value The value to sanitize.
	 *
	 * @return string The sanitized time or empty string if invalid.
	 */
	private function _sanitize_time(mixed $value): string {
		if (!is_scalar($value) || $value === '') {
			return '';
		}

		// Match HH:MM or HH:MM:SS format
		if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', (string) $value, $matches)) {
			$hours   = (int) $matches[1];
			$minutes = (int) $matches[2];

			// Validate ranges
			if ($hours >= 0 && $hours <= 23 && $minutes >= 0 && $minutes <= 59) {
				return sprintf('%02d:%02d', $hours, $minutes);
			}
		}

		return '';
	}

	/**
	 * Sanitize color input to hex format (#RRGGBB).
	 *
	 * Handles 3-digit shorthand (#RGB) by expanding to 6-digit (#RRGGBB).
	 *
	 * @param mixed $value The value to sanitize.
	 *
	 * @return string The sanitized color or empty string if invalid.
	 */
	private function _sanitize_color(mixed $value): string {
		if (!is_scalar($value) || $value === '') {
			return '';
		}

		$color = strtoupper(trim((string) $value));

		// Ensure # prefix
		if (!str_starts_with($color, '#')) {
			$color = '#' . $color;
		}

		// Expand 3-digit hex to 6-digit (#RGB -> #RRGGBB)
		if (preg_match('/^#([0-9A-F])([0-9A-F])([0-9A-F])$/i', $color, $matches)) {
			$color = '#' . $matches[1] . $matches[1] . $matches[2] . $matches[2] . $matches[3] . $matches[3];
		}

		// Validate final 6-digit format
		if (preg_match('/^#[0-9A-F]{6}$/', $color)) {
			return $color;
		}

		return '';
	}

	/**
	 * Sanitize checkbox/radio choice input.
	 *
	 * Returns the expected value if the input matches truthy patterns,
	 * otherwise returns empty string.
	 *
	 * @param mixed               $value   The value to sanitize.
	 * @param array<string,mixed> $context The field context with optional 'value' key.
	 *
	 * @return string The expected value or empty string.
	 */
	private function _sanitize_choice(mixed $value, array $context): string {
		$expectedValue = isset($context['value']) ? (string) $context['value'] : 'on';

		// Already the expected value
		if ($value === $expectedValue) {
			return $expectedValue;
		}

		// Truthy values map to expected value
		if ($value === true || $value === '1' || $value === 1) {
			return $expectedValue;
		}

		if (is_string($value)) {
			$lower = strtolower($value);
			if (in_array($lower, array('on', 'yes', 'true', 'checked'), true)) {
				return $expectedValue;
			}
		}

		return '';
	}
}
