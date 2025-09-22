<?php
declare(strict_types=1);

namespace Ran\PluginLib\Util\Sanitize;

/**
 * JSON decoding helpers.
 *
 * @method callable(mixed):mixed decodeToValue()
 * @method callable(mixed):mixed decodeObject()
 * @method callable(mixed):mixed decodeArray()
 */
final class SanitizeJsonGroup {
	/**
	 * Decode valid JSON strings to PHP values; pass-through otherwise.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example  $clean = (Sanitize::json()->decodeToValue())($value);
	 * @example  $clean = Sanitize::json()->decodeToValue($value);
	 *
	 * @param mixed $value
	 * @return callable(mixed):mixed Closure that decodes valid JSON strings to PHP values
	 */
	public function decodeToValue(mixed $value = null): mixed {
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
	 * @example  $clean = (Sanitize::json()->decodeObject())($value);
	 * @example  $clean = Sanitize::json()->decodeObject($value);
	 *
	 * @param mixed $value
	 * @return callable(mixed):mixed Closure that decodes to associative array (JSON object)
	 */
	public function decodeObject(mixed $value = null): mixed {
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
	 * @example  $clean = (Sanitize::json()->decodeArray())($value);
	 * @example  $clean = Sanitize::json()->decodeArray($value);
	 *
	 * @param mixed $value
	 * @return callable(mixed):mixed Closure that decodes to list array (JSON array)
	 */
	public function decodeArray(mixed $value = null): mixed {
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
