<?php
declare(strict_types=1);

namespace Ran\PluginLib\Util\Sanitize;

/**
 * Number/boolean coercion helpers.
 *
 * @example $clean = (Sanitize::bool()->to_to_bool())($value);
 * @example $clean = Sanitize::bool()->to_bool_strict($value);
 *
 * Dual-mode methods:
 * - When called with no arguments, methods return a callable(mixed): mixed
 * - When called with a value, methods apply immediately and return mixed
 *
 * @method callable(mixed):mixed to_bool()
 * @method callable(mixed):mixed to_bool_strict()
 */
final class SanitizeBooleanGroup {
	/**
	 * Coerce form-friendly boolean-like values to bool; pass-through otherwise.
	 * Accepts strict values plus form-friendly strings like 'yes', 'no', 'on', 'off'.
	 * Accepted: true/false (bool), 1/0 (int), 'true'/'false', 'yes'/'no', 'on'/'off', numeric strings.
	 *
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @param mixed $value
	 * @return callable(mixed):mixed Closure that coerces form-friendly boolean-like values to bool
	 */
	public function to_bool(mixed $value = null): mixed {
		$fn = static function (mixed $v): mixed {
			// Try strict coercion first
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

			// Handle form-friendly string values
			if (\is_string($v)) {
				$lower = \strtolower(\trim($v));
				if (\in_array($lower, array('yes', 'on'), true)) {
					return true;
				}
				if (\in_array($lower, array('no', 'off', ''), true)) {
					return false;
				}
			}

			// Handle numeric values
			if (\is_numeric($v)) {
				return (bool) $v;
			}

			// Pass through if not convertible
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
	public function to_bool_strict(mixed $value = null): mixed {
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
