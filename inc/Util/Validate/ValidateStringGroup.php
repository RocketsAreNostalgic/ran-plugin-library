<?php
declare(strict_types=1);

namespace Ran\PluginLib\Util\Validate;

/**
 * String constraint validators.
 *
 * @example $isValid = (Validate::string()->minLength(1))($value);
 * @example $isValid = Validate::string()->minLength(1, $value);
 *
 * Dual-mode methods:
 * - When called without the value argument, methods return callable(mixed): bool
 * - When called with the value argument, methods apply immediately and return bool
 *
 * @method callable(mixed):bool minLength(int $n)
 * @method callable(mixed):bool maxLength(int $n)
 * @method callable(mixed):bool lengthBetween(int $min, int $max)
 * @method callable(mixed):bool pattern(string $regex)
 */
final class ValidateStringGroup {
	/**
	 * Validate that a string has a length at least the given bound.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example Validate::string()->minLength()($value)
	 * @example Validate::string()->minLength($value)
	 *
	 * @param int $n
	 * @param mixed $value
	 * @return callable|bool
	 */
	public function minLength(int $n, mixed $value = null): callable|bool {
		$fn = static function (mixed $v) use ($n): bool {
			return \is_string($v) && \strlen($v) >= $n;
		};
		return \func_num_args() === 1 ? $fn : $fn($value);
	}

	/**
	 * Validate that a string has a length at most the given bound.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example Validate::string()->maxLength()($value)
	 * @example Validate::string()->maxLength($value)
	 *
	 * @param int $n
	 * @param mixed $value
	 * @return callable|bool
	 */
	public function maxLength(int $n, mixed $value = null): callable|bool {
		$fn = static function (mixed $v) use ($n): bool {
			return \is_string($v) && \strlen($v) <= $n;
		};
		return \func_num_args() === 1 ? $fn : $fn($value);
	}

	/**
	 * Validate that a string has a length between the given bounds.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example Validate::string()->lengthBetween()($value)
	 * @example Validate::string()->lengthBetween($value)
	 *
	 * @param int $min
	 * @param int $max
	 * @param mixed $value
	 * @return callable|bool
	 */
	public function lengthBetween(int $min, int $max, mixed $value = null): callable|bool {
		$fn = static function (mixed $v) use ($min, $max): bool {
			return \is_string($v) && ($l = \strlen($v)) >= $min && $l <= $max;
		};
		return \func_num_args() === 2 ? $fn : $fn($value);
	}

	/**
	 * Validate that a string matches a given regex pattern.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example Validate::string()->pattern()($value)
	 * @example Validate::string()->pattern($value)
	 *
	 * @param string $regex
	 * @param mixed $value
	 * @return callable|bool
	 */
	public function pattern(string $regex, mixed $value = null): callable|bool {
		$fn = static function (mixed $v) use ($regex): bool {
			return \is_string($v) && (bool) \preg_match($regex, $v);
		};
		return \func_num_args() === 1 ? $fn : $fn($value);
	}
}
