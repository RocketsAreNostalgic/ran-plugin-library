<?php
declare(strict_types=1);

namespace Ran\PluginLib\Util\Validate;

/**
 * Logical composition helpers for validators.
 *
 * @example $isValid = (Validate::compose()->all(
 *    Validate::basic()->is_string(),
 *    Validate::string()->length_between(1, 64)
 *  ))($value);
 * @example $isValid = Validate::compose()->nullable(Validate::basic()->is_string(), $value);
 *
 * Dual-mode methods:
 * - nullable(), optional(): with only the validator, return callable; with $value provided, apply immediately
 * - union(), all(), none(): callable factories only (unchanged)
 *
 * @method callable(mixed):bool nullable(callable $validator)
 * @method callable(mixed):bool optional(callable $validator)
 * @method callable(mixed):bool union(callable ...$validators)
 * @method callable(mixed):bool all(callable ...$validators)
 * @method callable(mixed):bool none(callable ...$validators)
 */
final class ValidateComposeGroup {
	/**
	 * Allow nulls while delegating validation for non-null values.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * Behavior:
	 * - Returns true if the input value is null
	 * - Otherwise, returns the result of `$validator($value)`
	 *
	 * @example Validate::compose()->nullable(Validate::basic()->is_string())
	 *
	 * @param callable(mixed):bool $validator Validator applied when value is not null
	 * @param mixed $value Value to validate (optional)
	 * @return callable(mixed):bool|bool Closure that validates nullable values or result of validation
	 */
	public function nullable(callable $validator, mixed $value = null): callable|bool {
		$fn = static function (mixed $v) use ($validator): bool {
			return $v === null || $validator($v);
		};
		return \func_num_args() === 1 ? $fn : $fn($value);
	}

	/**
	 * Alias of nullable(). Provided for semantic readability.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * Behavior:
	 * - Identical to `nullable($validator)`
	 *
	 * @example Validate::compose()->optional(Validate::basic()->is_int())
	 *
	 * @param callable(mixed):bool $validator Validator applied when value is not `null`
	 * @param mixed $value Value to validate (optional)
	 * @return callable(mixed):bool|bool Closure that validates optional values or result of validation
	 */
	public function optional(callable $validator, mixed $value = null): callable|bool {
		if (\func_num_args() === 1) {
			return $this->nullable($validator);
		}
		/** @var callable $callable */
		$callable = $this->nullable($validator);
		return $callable($value);
	}

	/**
	 * Logical OR across validators. Passes if any validator passes.
	 *
	 * Behavior:
	 * - Iterates validators in order; returns true on first `$validator($value) === true`
	 * - Returns false only if all validators return false
	 *
	 * @example Validate::compose()->union(Validate::basic()->is_int(), Validate::basic()->is_float())
	 *
	 * @param callable(mixed):bool ...$validators One or more validators to combine
	 * @return callable(mixed):bool Closure that returns true if any validator accepts the value
	 */
	public function union(callable ...$validators): callable {
		return static function (mixed $v) use ($validators): bool {
			foreach ($validators as $validator) {
				if ($validator($v)) {
					return true;
				}
			}
			return false;
		};
	}

	/**
	 * Logical AND across validators. Passes only if all validators pass.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * Behavior:
	 * - Iterates validators; returns false on first `$validator($value) === false`
	 * - Returns true only if every validator returns true
	 *
	 * @example Validate::compose()->all(
	 *   Validate::basic()->is_string(),
	 *   Validate::string()->length_between(1, 64)
	 * )
	 *
	 * @param callable(mixed):bool ...$validators One or more validators to combine
	 * @return callable(mixed):bool Closure that returns true if all validators accept the value
	 */
	public function all(callable ...$validators): callable {
		return static function (mixed $v) use ($validators): bool {
			foreach ($validators as $validator) {
				if (!$validator($v)) {
					return false;
				}
			}
			return true;
		};
	}

	/**
	 * Logical NOR across validators. Passes only if all validators fail.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * Behavior:
	 * - Iterates validators; returns false on first `$validator($value) === true`
	 * - Returns true only if every validator returns false
	 *
	 * @example Validate::compose()->none(Validate::enums()->enum(['deprecated','removed']))
	 *
	 * @param callable(mixed):bool ...$validators One or more validators to negate collectively
	 * @return callable(mixed):bool Closure that returns true if none of the validators accept the value
	 */
	public function none(callable ...$validators): callable {
		return static function (mixed $v) use ($validators): bool {
			foreach ($validators as $validator) {
				if ($validator($v)) {
					return false;
				}
			}
			return true;
		};
	}
}
