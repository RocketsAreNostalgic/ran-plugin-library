<?php
declare(strict_types=1);

namespace Ran\PluginLib\Util\Validate;

/**
 * Basic type predicate validators.
 *
 * @example $isValid = (Validate::basic()->isString())($value);
 * @example $isValid = Validate::basic()->isString($value);
 *
 * Dual-mode methods:
 * - When called with no arguments, each method returns a callable(mixed): bool
 * - When called with a value, each method returns a bool immediately
 *
 * @method callable(mixed):bool isBool()
 * @method callable(mixed):bool isInt()
 * @method callable(mixed):bool isFloat()
 * @method callable(mixed):bool isString()
 * @method callable(mixed):bool isArray()
 * @method callable(mixed):bool isObject()
 * @method callable(mixed):bool isNull()
 * @method callable(mixed):bool isScalar()
 * @method callable(mixed):bool isNumeric()
 * @method callable(mixed):bool isNullable()
 * @method callable(mixed):bool isCallable()
 * @method callable(mixed):bool isEmpty()
 * @method callable(mixed):bool isNotEmpty()
 */
final class ValidateBasicGroup {
	/**
	 * Validate that a value is a boolean.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example Validate::basic()->isBool($value)
	 *
	 * @param mixed $value
	 * @return callable|bool
	 */
	public function isBool(mixed $value = null): callable|bool {
		$fn = static fn(mixed $v): bool => \is_bool($v);
		return \func_num_args() === 0 ? $fn : $fn($value);
	}

	/**
	 * Validate that a value is an integer.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example Validate::basic()->isInt($value)
	 *
	 * @param mixed $value
	 * @return callable|bool
	 */
	public function isInt(mixed $value = null): callable|bool {
		$fn = static fn(mixed $v): bool => \is_int($v);
		return \func_num_args() === 0 ? $fn : $fn($value);
	}

	/**
	 * Validate that a value is a float.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example Validate::basic()->isFloat($value)
	 *
	 * @param mixed $value
	 * @return callable|bool
	 */
	public function isFloat(mixed $value = null): callable|bool {
		$fn = static fn(mixed $v): bool => \is_float($v);
		return \func_num_args() === 0 ? $fn : $fn($value);
	}

	/**
	 * Validate that a value is a string.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example Validate::basic()->isString($value)
	 *
	 * @param mixed $value
	 * @return callable|bool
	 */
	public function isString(mixed $value = null): callable|bool {
		$fn = static fn(mixed $v): bool => \is_string($v);
		return \func_num_args() === 0 ? $fn : $fn($value);
	}

	/**
	 * Validate that a value is an array.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example Validate::basic()->isArray($value)
	 *
	 * @param mixed $value
	 * @return callable|bool
	 */
	public function isArray(mixed $value = null): callable|bool {
		$fn = static fn(mixed $v): bool => \is_array($v);
		return \func_num_args() === 0 ? $fn : $fn($value);
	}

	/**
	 * Validate that a value is an object.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example Validate::basic()->isObject($value)
	 *
	 * @param mixed $value
	 * @return callable|bool
	 */
	public function isObject(mixed $value = null): callable|bool {
		$fn = static fn(mixed $v): bool => \is_object($v);
		return \func_num_args() === 0 ? $fn : $fn($value);
	}

	/**
	 * Validate that a value is null.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example Validate::basic()->isNull($value)
	 *
	 * @param mixed $value
	 * @return callable|bool
	 */
	public function isNull(mixed $value = null): callable|bool {
		$fn = static fn(mixed $v): bool => $v === null;
		return \func_num_args() === 0 ? $fn : $fn($value);
	}

	/**
	 * Validate that a value is a scalar.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example Validate::basic()->isScalar($value)
	 *
	 * @param mixed $value
	 * @return callable|bool
	 */
	public function isScalar(mixed $value = null): callable|bool {
		$fn = static fn(mixed $v): bool => \is_scalar($v);
		return \func_num_args() === 0 ? $fn : $fn($value);
	}

	/**
	 * Validate that a value is numeric.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example Validate::basic()->isNumeric($value)
	 *
	 * @param mixed $value
	 * @return callable|bool
	 */
	public function isNumeric(mixed $value = null): callable|bool {
		$fn = static fn(mixed $v): bool => \is_numeric($v);
		return \func_num_args() === 0 ? $fn : $fn($value);
	}

	/**
	 * Validate that a value is nullable.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example Validate::basic()->isNullable($value)
	 *
	 * @param mixed $value
	 * @return callable|bool
	 */
	public function isNullable(mixed $value = null): callable|bool {
		$fn = static fn(mixed $v): bool => $v === null || \is_scalar($v);
		return \func_num_args() === 0 ? $fn : $fn($value);
	}

	/**
	 * Validate that a value is callable.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example Validate::basic()->isCallable($value)
	 *
	 * @param mixed $value
	 * @return callable|bool
	 */
	public function isCallable(mixed $value = null): callable|bool {
		$fn = static fn(mixed $v): bool => \is_callable($v);
		return \func_num_args() === 0 ? $fn : $fn($value);
	}

	/**
	 * Validate that a value is empty.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example Validate::basic()->isEmpty($value)
	 *
	 * @param mixed $value
	 * @return callable|bool
	 */
	public function isEmpty(mixed $value = null): callable|bool {
		$fn = static fn(mixed $v): bool => $v === '' || $v === null || $v === false;
		return \func_num_args() === 0 ? $fn : $fn($value);
	}

	/**
	 * Validate that a value is not empty.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example Validate::basic()->isNotEmpty($value)
	 *
	 * @param mixed $value
	 * @return callable|bool
	 */
	public function isNotEmpty(mixed $value = null): callable|bool {
		$empty = $this->isEmpty();
		$fn    = static fn(mixed $v): bool => !$empty($v);
		return \func_num_args() === 0 ? $fn : $fn($value);
	}
}
