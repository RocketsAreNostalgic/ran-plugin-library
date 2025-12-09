<?php
declare(strict_types=1);

namespace Ran\PluginLib\Util\Sanitize;

/**
 * JSON decoding helpers.
 */
final class SanitizeJsonGroup {
	/**
	 * Decode valid JSON strings to PHP values; pass-through otherwise.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example  $clean = (Sanitize::json()->decode_to_value())($value);
	 * @example  $clean = Sanitize::json()->decode_to_value($value);
	 *
	 * @param mixed $value
	 * @return callable(mixed):mixed Closure that decodes valid JSON strings to PHP values
	 */
	public function decode_to_value(mixed $value = null): mixed {
		$fn = static function (mixed $v): mixed {
			if (!\is_string($v)) {
				return $v;
			}
			$decoded = json_decode($v, true);
			return (json_last_error() === JSON_ERROR_NONE) ? $decoded : $v;
		};
		return \func_num_args() === 0 ? $fn : $fn($value);
	}

	/**
	 * Decode to associative array (JSON object); pass-through if not a JSON object string.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example  $clean = (Sanitize::json()->decode_object())($value);
	 * @example  $clean = Sanitize::json()->decode_object($value);
	 *
	 * @param mixed $value
	 * @return callable(mixed):mixed Closure that decodes to associative array (JSON object)
	 */
	public function decode_object(mixed $value = null): mixed {
		$fn = static function (mixed $v): mixed {
			if (!\is_string($v)) {
				return $v;
			}
			$decoded = json_decode($v, true);
			return (json_last_error() === JSON_ERROR_NONE && \is_array($decoded) && array_keys($decoded) !== range(0, count($decoded) - 1)) ? $decoded : $v;
		};
		return \func_num_args() === 0 ? $fn : $fn($value);
	}

	/**
	 * Decode to list array (JSON array); pass-through if not a JSON array string.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example  $clean = (Sanitize::json()->decode_array())($value);
	 * @example  $clean = Sanitize::json()->decode_array($value);
	 *
	 * @param mixed $value
	 * @return callable(mixed):mixed Closure that decodes to list array (JSON array)
	 */
	public function decode_array(mixed $value = null): mixed {
		$fn = static function (mixed $v): mixed {
			if (!\is_string($v)) {
				return $v;
			}
			$decoded = json_decode($v, true);
			return (json_last_error() === JSON_ERROR_NONE && \is_array($decoded) && array_keys($decoded) === range(0, count($decoded) - 1)) ? $decoded : $v;
		};
		return \func_num_args() === 0 ? $fn : $fn($value);
	}
}
