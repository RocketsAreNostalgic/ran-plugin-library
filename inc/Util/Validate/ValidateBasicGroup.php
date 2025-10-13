<?php
declare(strict_types=1);

namespace Ran\PluginLib\Util\Validate;

/**
 * Basic type predicate validators.
 *
 * @example $isValid = (Validate::basic()->is_string())($value);
 * @example $isValid = Validate::basic()->is_string($value);
 *
 * Dual-mode methods:
 * - When called with no arguments, each method returns a callable(mixed): bool
 * - When called with a value, each method returns a bool immediately
 *
 * @method callable(mixed):bool is_bool()
 * @method callable(mixed):bool is_int()
 * @method callable(mixed):bool is_float()
 * @method callable(mixed):bool is_string()
 * @method callable(mixed):bool is_array()
 * @method callable(mixed):bool is_object()
 * @method callable(mixed):bool is_null()
 * @method callable(mixed):bool is_scalar()
 * @method callable(mixed):bool is_numeric()
 * @method callable(mixed):bool is_nullable()
 * @method callable(mixed):bool is_callable()
 * @method callable(mixed):bool is_empty()
 * @method callable(mixed):bool is_not_empty()
 */
final class ValidateBasicGroup {
	/**
	 * Validate that a value is a boolean.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example Validate::basic()->is_bool($value)
	 *
	 * @param mixed $value
	 * @return callable|bool
	 */
	public function is_bool(mixed $value = null): callable|bool {
		$fn = static fn(mixed $v): bool => \is_bool($v);
		return \func_num_args() === 0 ? $fn : $fn($value);
	}

	/**
	 * Validate that a value is an integer.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example Validate::basic()->is_int($value)
	 *
	 * @param mixed $value
	 * @return callable|bool
	 */
	public function is_int(mixed $value = null): callable|bool {
		$fn = static fn(mixed $v): bool => \is_int($v);
		return \func_num_args() === 0 ? $fn : $fn($value);
	}

	/**
	 * Validate that a value is a float.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example Validate::basic()->is_float($value)
	 *
	 * @param mixed $value
	 * @return callable|bool
	 */
	public function is_float(mixed $value = null): callable|bool {
		$fn = static fn(mixed $v): bool => \is_float($v);
		return \func_num_args() === 0 ? $fn : $fn($value);
	}

	/**
	 * Validate that a value is a string.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example Validate::basic()->is_string($value)
	 *
	 * @param mixed $value
	 * @return callable|bool
	 */
	public function is_string(mixed $value = null): callable|bool {
		$fn = static fn(mixed $v): bool => \is_string($v);
		return \func_num_args() === 0 ? $fn : $fn($value);
	}

	/**
	 * Validate that a value is an array.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example Validate::basic()->is_array($value)
	 *
	 * @param mixed $value
	 * @return callable|bool
	 */
	public function is_array(mixed $value = null): callable|bool {
		$fn = static fn(mixed $v): bool => \is_array($v);
		return \func_num_args() === 0 ? $fn : $fn($value);
	}

	/**
	 * Validate that a value is an object.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example Validate::basic()->is_object($value)
	 *
	 * @param mixed $value
	 * @return callable|bool
	 */
	public function is_object(mixed $value = null): callable|bool {
		$fn = static fn(mixed $v): bool => \is_object($v);
		return \func_num_args() === 0 ? $fn : $fn($value);
	}

	/**
	 * Validate that a value is null.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example Validate::basic()->is_null($value)
	 *
	 * @param mixed $value
	 * @return callable|bool
	 */
	public function is_null(mixed $value = null): callable|bool {
		$fn = static fn(mixed $v): bool => $v === null;
		return \func_num_args() === 0 ? $fn : $fn($value);
	}

	/**
	 * Validate that a value is a scalar.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example Validate::basic()->is_scalar($value)
	 *
	 * @param mixed $value
	 * @return callable|bool
	 */
	public function is_scalar(mixed $value = null): callable|bool {
		$fn = static fn(mixed $v): bool => \is_scalar($v);
		return \func_num_args() === 0 ? $fn : $fn($value);
	}

	/**
	 * Validate that a value is numeric.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example Validate::basic()->is_numeric($value)
	 *
	 * @param mixed $value
	 * @return callable|bool
	 */
	public function is_numeric(mixed $value = null): callable|bool {
		$fn = static fn(mixed $v): bool => \is_numeric($v);
		return \func_num_args() === 0 ? $fn : $fn($value);
	}

	/**
	 * Validate that a value is nullable.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example Validate::basic()->is_nullable($value)
	 *
	 * @param mixed $value
	 * @return callable|bool
	 */
	public function is_nullable(mixed $value = null): callable|bool {
		$fn = static fn(mixed $v): bool => $v === null || \is_scalar($v);
		return \func_num_args() === 0 ? $fn : $fn($value);
	}

	/**
	 * Validate that a value is callable.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example Validate::basic()->is_callable($value)
	 *
	 * @param mixed $value
	 * @return callable|bool
	 */
	public function is_callable(mixed $value = null): callable|bool {
		$fn = static fn(mixed $v): bool => \is_callable($v);
		return \func_num_args() === 0 ? $fn : $fn($value);
	}

	/**
	 * Validate that a value is empty.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example Validate::basic()->is_empty($value)
	 *
	 * @param mixed $value
	 * @return callable|bool
	 */
	public function is_empty(mixed $value = null): callable|bool {
		$fn = static fn(mixed $v): bool => $v === '' || $v === null || $v === false;
		return \func_num_args() === 0 ? $fn : $fn($value);
	}

	/**
	 * Validate that a value is not empty.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example Validate::basic()->is_not_empty($value)
	 *
	 * @param mixed $value
	 * @return callable|bool
	 */
	public function is_not_empty(mixed $value = null): callable|bool {
		$empty = $this->is_empty();
		$fn    = static fn(mixed $v): bool => !$empty($v);
		return \func_num_args() === 0 ? $fn : $fn($value);
	}
}
