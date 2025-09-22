<?php
declare(strict_types=1);

namespace Ran\PluginLib\Util\Validate;

/**
 * Numeric constraint validators.
 * Dual-mode: no argument returns a callable; with value, applies immediately.
 *
 * @example $isValid = (Validate::number()->min(1))($value);
 * @example $isValid = Validate::number()->min(1, $value);
 *
 * @method callable(mixed):bool min(int|float $n)
 * @method callable(mixed):bool max(int|float $n)
 * @method callable(mixed):bool between(int|float $min, int|float $max)
 */
final class ValidateNumberGroup {
	/**
	 * Validate that a value is greater than or equal to a minimum value.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example Validate::number()->min()($value)
	 * @example Validate::number()->min($value)
	 *
	 * @param int|float $n Minimum value
	 * @param mixed $value Value to validate (optional)
	 * @return callable(mixed):bool|bool Closure that returns true if the value is greater than or equal to the minimum value or result of validation
	 *
	 */
	public function min(int|float $n, mixed $value = null): callable|bool {
		$fn = static function (mixed $v) use ($n): bool {
			return (\is_int($v) || \is_float($v)) && $v >= $n;
		};
		return \func_num_args() === 1 ? $fn : $fn($value);
	}

	/**
	 * Validate that a value is less than or equal to a maximum value.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example Validate::number()->max()($value)
	 * @example Validate::number()->max($value)
	 *
	 * @param int|float $n Maximum value
	 * @param mixed $value Value to validate (optional)
	 * @return callable(mixed):bool|bool Closure that returns true if the value is less than or equal to the maximum value or result of validation
	 *
	 * @example Validate::number()->max(1, $value)
	 */
	public function max(int|float $n, mixed $value = null): callable|bool {
		$fn = static function (mixed $v) use ($n): bool {
			return (\is_int($v) || \is_float($v)) && $v <= $n;
		};
		return \func_num_args() === 1 ? $fn : $fn($value);
	}

	/**
	 * Validate that a value is within a range of values.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example Validate::number()->between()($value)
	 * @example Validate::number()->between($value)
	 *
	 * @param int|float $min Minimum value
	 * @param int|float $max Maximum value
	 * @param mixed $value Value to validate (optional)
	 * @return callable(mixed):bool|bool Closure that returns true if the value is within the range or result of validation
	 *
	 * @example Validate::number()->between(1, 10, $value)
	 */
	public function between(int|float $min, int|float $max, mixed $value = null): callable|bool {
		$fn = static function (mixed $v) use ($min, $max): bool {
			if (!(\is_int($v) || \is_float($v))) {
				return false;
			}
			return $v >= $min && $v <= $max;
		};
		return \func_num_args() === 2 ? $fn : $fn($value);
	}
}
