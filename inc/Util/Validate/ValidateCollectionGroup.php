<?php
declare(strict_types=1);

namespace Ran\PluginLib\Util\Validate;

/**
 * Array/collection shape validators.
 *
 * @method callable(mixed):bool list_of(callable $itemValidator)
 * @method callable(mixed):bool shape(array $schema)
 * @method callable(mixed):bool strict_shape(array $schema)
 * @method callable(mixed):bool hasKeys(array $keys)
 * @method callable(mixed):bool exactKeys(array $keys)
 * @method callable(mixed):bool minItems(int $n)
 * @method callable(mixed):bool maxItems(int $n)
 *
 *
 * @example $isValid = (Validate::collection()->shape([
 *     'name' => Validate::string()->min_length(1),
 *     'age' => Validate::number()->min(0),
 * ]))($value);
 * @example $isValid = Validate::collection()->shape([
 *     'name' => Validate::string()->min_length(1),
 *     'age' => Validate::number()->min(0),
 * ], $value);
 *
 * Dual-mode methods:
 * - When called without the value argument, methods return callable(mixed): bool
 * - When called with the value argument, methods apply immediately and return bool
 */
final class ValidateCollectionGroup {
	/**
	 * Validate arrays whose every element satisfies the provided item validator.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example Validate::collection()->list_of(Validate::basic()->is_int())($value)
	 *
	 * @param callable(mixed):bool $itemValidator Validator applied to each array element
	 * @param mixed $value
	 * @return callable(mixed):bool|bool Closure that validates homogeneous arrays or immediate result
	 */
	public function list_of(callable $itemValidator, mixed $value = null): callable|bool {
		$fn = static function (mixed $v) use ($itemValidator): bool {
			if (!\is_array($v)) {
				return false;
			}
			foreach ($v as $item) {
				if (!$itemValidator($item)) {
					return false;
				}
			}
			return true;
		};
		return \func_num_args() === 1 ? $fn : $fn($value);
	}

	/**
	 * Validate associative arrays by per-key validators; ignores extra keys.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example Validate::collection()->shape(['a' => Validate::basic()->is_int()])($value)
	 *
	 * @param array<string, callable(mixed):bool> $schema Map of required keys to their validators
	 * @param mixed $value
	 * @return callable(mixed):bool|bool Closure that validates object-like arrays or immediate result
	 */
	public function shape(array $schema, mixed $value = null): callable|bool {
		$fn = static function (mixed $v) use ($schema): bool {
			if (!\is_array($v)) {
				return false;
			}
			foreach ($schema as $key => $validator) {
				if (!\array_key_exists($key, $v)) {
					return false;
				}
				if (!$validator($v[$key])) {
					return false;
				}
			}
			return true;
		};
		return \func_num_args() === 1 ? $fn : $fn($value);
	}

	/**
	 * Validate associative arrays strictly: required keys must exist, and no extra keys are allowed.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example Validate::collection()->strict_shape(['a' => Validate::basic()->is_int()])($value)
	 *
	 * @param array<string, callable(mixed):bool> $schema Map of required keys to their validators
	 * @param mixed $value
	 * @return callable(mixed):bool|bool Closure that validates exact-key object-like arrays or immediate result
	 */
	public function strict_shape(array $schema, mixed $value = null): callable|bool {
		$fn = static function (mixed $v) use ($schema): bool {
			if (!\is_array($v)) {
				return false;
			}
			// Validate required keys and values
			foreach ($schema as $key => $validator) {
				if (!\array_key_exists($key, $v)) {
					return false;
				}
				if (!$validator($v[$key])) {
					return false;
				}
			}
			// Ensure there are no extra keys
			$allowed = array_fill_keys(array_keys($schema), true);
			foreach ($v as $key => $_) {
				if (!\array_key_exists($key, $allowed)) {
					return false;
				}
			}
			return true;
		};
		return \func_num_args() === 1 ? $fn : $fn($value);
	}

	/**
	 * Require presence of specific keys in an array without constraining values.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example Validate::collection()->has_keys(['a', 'b'])($value)
	 *
	 * @param array<int, string|int> $keys Keys that must be present
	 * @param mixed $value
	 * @return callable(mixed):bool|bool Closure that checks for key presence or immediate result
	 */
	public function has_keys(array $keys, mixed $value = null): callable|bool {
		$fn = static function (mixed $v) use ($keys): bool {
			if (!\is_array($v)) {
				return false;
			}
			foreach ($keys as $k) {
				if (!\array_key_exists($k, $v)) {
					return false;
				}
			}
			return true;
		};
		return \func_num_args() === 1 ? $fn : $fn($value);
	}

	/**
	 * Enforce exact key set: requires that the array has exactly and only the given keys.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example Validate::collection()->exact_keys(['a', 'b'])($value)
	 *
	 * @param array<int, string|int> $keys Exact key set required
	 * @param mixed $value
	 * @return callable(mixed):bool|bool Closure that checks exact key set equality or immediate result
	 */
	public function exact_keys(array $keys, mixed $value = null): callable|bool {
		$fn = static function (mixed $v) use ($keys): bool {
			if (!\is_array($v)) {
				return false;
			}
			$required = array_fill_keys($keys, true);
			// Check no extras
			foreach ($v as $k => $_) {
				if (!\array_key_exists($k, $required)) {
					return false;
				}
			}
			// Check no missing
			foreach ($required as $k => $_) {
				if (!\array_key_exists($k, $v)) {
					return false;
				}
			}
			return true;
		};
		return \func_num_args() === 1 ? $fn : $fn($value);
	}

	/**
	 * Minimum number of items in an array.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example Validate::collection()->min_items(1)($value)
	 *
	 * @param int $n Minimum number of items required
	 * @param mixed $value
	 * @return callable(mixed):bool|bool Closure that checks minimum item count or immediate result
	 */
	public function min_items(int $n, mixed $value = null): callable|bool {
		$fn = static function (mixed $v) use ($n): bool {
			return \is_array($v) && \count($v) >= $n;
		};
		return \func_num_args() === 1 ? $fn : $fn($value);
	}

	/**
	 * Maximum number of items in an array.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example Validate::collection()->max_items(10)($value)
	 *
	 * @param int $n Maximum number of items allowed
	 * @param mixed $value
	 * @return callable(mixed):bool|bool Closure that checks maximum item count or immediate result
	 */
	public function max_items(int $n, mixed $value = null): callable|bool {
		$fn = static function (mixed $v) use ($n): bool {
			return \is_array($v) && \count($v) <= $n;
		};
		return \func_num_args() === 1 ? $fn : $fn($value);
	}
}
