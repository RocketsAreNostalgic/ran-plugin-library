<?php
declare(strict_types=1);

namespace Ran\PluginLib\Util\Sanitize;

/**
 * Number/boolean coercion helpers.
 *
 * @example $clean = (Sanitize::number()->toInt())($value);
 * @example $clean = Sanitize::number()->toInt($value);
 *
 * Dual-mode methods:
 * - When called with no arguments, methods return a callable(mixed): mixed
 * - When called with a value, methods apply immediately and return mixed
 *
 * @method callable(mixed):mixed toInt()
 * @method callable(mixed):mixed toFloat()
 * @method callable(mixed):mixed toBoolStrict()
 */
final class SanitizeNumberGroup {
	/**
	 * Cast numeric-like values to int; pass-through otherwise.
	 *
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @param mixed $value
	 * @return callable(mixed):mixed Closure that casts numeric-like values to int
	 */
	public function toInt(mixed $value = null): mixed {
		$fn = static function (mixed $v): mixed {
			if (\is_int($v)) {
				return $v;
			}
			if (\is_float($v)) {
				return (int) $v;
			}
			if (\is_string($v) && is_numeric($v)) {
				return (int) $v;
			}
			return $v;
		};
		return \func_num_args() === 0 ? $fn : $fn($value);
	}

	/**
	 * Cast numeric-like values to float; pass-through otherwise.
	 *
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @param mixed $value
	 * @return callable(mixed):mixed Closure that casts numeric-like values to float
	 */
	public function toFloat(mixed $value = null): mixed {
		$fn = static function (mixed $v): mixed {
			if (\is_float($v)) {
				return $v;
			}
			if (\is_int($v)) {
				return (float) $v;
			}
			if (\is_string($v) && is_numeric($v)) {
				return (float) $v;
			}
			return $v;
		};
		return \func_num_args() === 0 ? $fn : $fn($value);
	}

	/**
	 * Coerce only strict boolean-like string literals to bool; pass-through otherwise.
	 * Accepted: true,false (lowercase), 1,0 (int), true/false (bool).
	 *
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @param mixed $value
	 * @return callable(mixed):mixed Closure that coerces only strict boolean-like string literals to bool
	 */
	public function toBoolStrict(mixed $value = null): mixed {
		$fn = static function (mixed $v): mixed {
			if (\is_bool($v)) {
				return $v;
			}
			if ($v === 1 || $v === 0) {
				return (bool) $v;
			}
			if ($v === 'true') {
				return true;
			}
			if ($v === 'false') {
				return false;
			}
			return $v;
		};
		return \func_num_args() === 0 ? $fn : $fn($value);
	}
}
