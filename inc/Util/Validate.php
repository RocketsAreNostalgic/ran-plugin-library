<?php
declare(strict_types=1);

namespace Ran\PluginLib\Util;

/**
 * Validation utilities for schema-driven option values.
 *
 * What this provides (built-ins):
 * - Type predicates: isBool, isInt, isFloat, isString, isArray, isObject, isNull, isScalar, isNumeric,
 *   isCountable, isNullable, isCallable (and corresponding negative helpers for a few cases)
 * - Type maps: validatorForType('string'|'int'|...) and validateByType($value, 'int')
 * - Simple default inspection: inferSimpleTypeFromValue($default)
 *
 * Usage in schema (examples):
 *
 *  $schema = [
 *    'enabled' => [
 *      'default'  => false,
 *      'validate' => [Validate::class, 'isBool'],
 *    ],
 *    'name' => [
 *      'default'  => 'demo',
 *      'validate' => [Validate::class, 'isString'],
 *    ],
 *    'timeout' => [
 *      'default'  => 30,
 *      'validate' => [Validate::class, 'isInt'],
 *    ],
 *  ];
 *
 * Notes:
 * - These helpers are intentionally small and generic. Domain‑specific validation logic should still live in your
 *   own callable validators declared in the schema.
 * - Strict schema mode requires an explicit 'validate' callable for every key; you can use these helpers for quick
 *   type checks and combine them with additional custom checks as needed.
 */
final class Validate {
	/**
	 * Return a builtin validator callable for a given simple type name, or null if unknown.
	 *
	 * Supported aliases include: 'bool'|'boolean', 'int'|'integer', 'float'|'double',
	 * 'string', 'array', 'object', 'null', 'callable'.
	 */
	public static function validatorForType(string $type): ?callable {
		return match (strtolower($type)) {
			'bool', 'boolean' => static fn(mixed $v): bool => \is_bool($v),
			'int', 'integer' => static fn(mixed $v): bool => \is_int($v),
			'float', 'double' => static fn(mixed $v): bool => \is_float($v),
			'string'   => static fn(mixed $v): bool => \is_string($v),
			'array'    => static fn(mixed $v): bool => \is_array($v),
			'object'   => static fn(mixed $v): bool => \is_object($v),
			'null'     => static fn(mixed $v): bool => $v === null,
			'callable' => static fn(mixed $v): bool => \is_callable($v),
			default    => null,
		};
	}

	/**
	 * Validate a value against a simple type name using builtin validators.
	 * Unknown type names return true (non‑blocking).
	 */
	public static function validateByType(mixed $value, string $type): bool {
		$v = self::validatorForType($type);
		if ($v === null) {
			return true; // Unknown type → do not block
		}
		return (bool) $v($value);
	}

	/**
	 * Infer a simple scalar/aggregate type from a literal value.
	 * Returns null for callables or unrecognized types. This is informational only;
	 * strict schema still requires an explicit validate callable.
	 *
	 * @uses is_callable
	 * @uses gettype
	 * @param mixed $name
	 */
	public static function inferSimpleTypeFromValue(mixed $value): ?string {
		if (\is_callable($value)) {
			return 'callable';
		}
		$t = gettype($value);
		switch ($t) {
			case 'boolean': return 'bool';
			case 'integer': return 'int';
			case 'double':  return 'float';
			case 'string':  return 'string';
			case 'array':   return 'array';
			case 'object':  return 'object';
			case 'NULL':    return 'null';
			default:
				return null; // Unknown type → no inference; caller must provide explicit validator
		}
	}

	/**
     * Access basic predicate validators (type checks) as callables.
     *
     * Provides: isBool(), isInt(), isFloat(), isString(), isArray(), isObject(),
     * isNull(), isScalar(), isNumeric(), isCountable(), isNullable(), isCallable().
     *
     * @return ValidateBasicGroup Group exposing basic predicate callables
     *
     * @example Validate::basic()->isString()
     */
	public static function basic(): ValidateBasicGroup {
		/** @inheritDoc */
		return new ValidateBasicGroup();
	}

	/**
	 * Access numeric constraint validators.
	 *
	 * Provides: min($n), max($n), between($min, $max).
	 *
	 * @return ValidateNumberGroup Group exposing numeric constraint callables
	 *
	 * @example Validate::number()->between(1, 300)
	 */
	public static function number(): ValidateNumberGroup {
		/** @inheritDoc */
		return new ValidateNumberGroup();
	}

	/**
	 * Access string constraint validators.
	 *
	 * Provides: minLength($n), maxLength($n), lengthBetween($min, $max), pattern($regex).
	 *
	 * @return ValidateStringGroup Group exposing string constraint callables
	 *
	 * @example Validate::string()->pattern('/^[a-z0-9_]+$/')
	 */
	public static function string(): ValidateStringGroup {
		/** @inheritDoc */
		return new ValidateStringGroup();
	}

	/**
	 * Access collection and shape validators.
	 *
	 * Provides: listOf($itemValidator), shape([$key => $validator, ...]).
	 *
	 * @return ValidateCollectionGroup Group exposing collection/shape callables
	 *
	 * @example Validate::collection()->listOf(Validate::basic()->isString())
	 */
	public static function collection(): ValidateCollectionGroup {
		/** @inheritDoc */
		return new ValidateCollectionGroup();
	}

	/**
	 * Access enum validators for fixed sets and PHP 8.1+ enums.
	 *
	 * Provides: enum($values), backed($enumClass), unit($enumClass).
	 *
	 * @return ValidateEnumGroup Group exposing enum-related callables
	 *
	 * @example Validate::enums()->backed(Mode::class)
	 */
	public static function enums(): ValidateEnumGroup {
		/** @inheritDoc */
		return new ValidateEnumGroup();
	}

	/**
	 * Access composition helpers (logical combinators) for validators.
	 *
	 * Provides: nullable($validator), optional($validator), union(...$validators),
	 * all(...$validators), none(...$validators).
	 *
	 * @return ValidateComposeGroup Group exposing compositional callables
	 *
	 * @example Validate::compose()->nullable(Validate::basic()->isString())
	 */
	public static function compose(): ValidateComposeGroup {
		/** @inheritDoc */
		return new ValidateComposeGroup();
	}

	/**
	 * Access common format validators (e.g., email, phone).
	 *
	 * Provides: email(), phone()
	 *
	 * @return ValidateFormatGroup Group exposing format validators
	 *
	 * @example Validate::format()->email()
	 */
	public static function format(): ValidateFormatGroup {
		/** @inheritDoc */
		return new ValidateFormatGroup();
	}
}

// --- Category proxy classes (return callables) ---

final class ValidateBasicGroup {
	/**
	 * Predicate: value is a boolean (strict).
	 *
	 * @return callable(mixed):bool Closure that returns true iff is_bool($value)
	 * @example Validate::basic()->isBool()
	 */
	public function isBool(): callable {
		return static fn(mixed $v): bool => \is_bool($v);
	}

	/**
	 * Predicate: value is an integer (strict).
	 *
	 * @return callable(mixed):bool Closure that returns true iff is_int($value)
	 * @example Validate::basic()->isInt()
	 */
	public function isInt(): callable {
		return static fn(mixed $v): bool => \is_int($v);
	}

	/**
	 * Predicate: value is a float/double (strict).
	 *
	 * @return callable(mixed):bool Closure that returns true iff is_float($value)
	 * @example Validate::basic()->isFloat()
	 */
	public function isFloat(): callable {
		return static fn(mixed $v): bool => \is_float($v);
	}

	/**
	 * Predicate: value is a string (strict).
	 *
	 * @return callable(mixed):bool Closure that returns true iff is_string($value)
	 * @example Validate::basic()->isString()
	 */
	public function isString(): callable {
		return static fn(mixed $v): bool => \is_string($v);
	}

	/**
	 * Predicate: value is an array.
	 *
	 * @return callable(mixed):bool Closure that returns true iff is_array($value)
	 * @example Validate::basic()->isArray()
	 */
	public function isArray(): callable {
		return static fn(mixed $v): bool => \is_array($v);
	}

	/**
	 * Predicate: value is an object.
	 *
	 * @return callable(mixed):bool Closure that returns true iff is_object($value)
	 * @example Validate::basic()->isObject()
	 */
	public function isObject(): callable {
		return static fn(mixed $v): bool => \is_object($v);
	}

	/**
	 * Predicate: value is null.
	 *
	 * @return callable(mixed):bool Closure that returns true iff $value === null
	 * @example Validate::basic()->isNull()
	 */
	public function isNull(): callable {
		return static fn(mixed $v): bool => $v === null;
	}

	/**
	 * Predicate: value is a scalar (bool|int|float|string).
	 *
	 * @return callable(mixed):bool Closure that returns true iff is_scalar($value)
	 * @example Validate::basic()->isScalar()
	 */
	public function isScalar(): callable {
		return static fn(mixed $v): bool => \is_scalar($v);
	}

	/**
	 * Predicate: value is numeric (string or number).
	 *
	 * @return callable(mixed):bool Closure that returns true iff is_numeric($value)
	 * @example Validate::basic()->isNumeric()
	 */
	public function isNumeric(): callable {
		return static fn(mixed $v): bool => \is_numeric($v);
	}

	/**
	 * Predicate: value is null or a scalar (coarse helper).
	 *
	 * @return callable(mixed):bool Closure that returns true iff $value === null || is_scalar($value)
	 * @example Validate::basic()->isNullable()
	 */
	public function isNullable(): callable {
		return static fn(mixed $v): bool => $v === null || \is_scalar($v);
	}

	/**
	 * Predicate: value is callable.
	 *
	 * @return callable(mixed):bool Closure that returns true iff is_callable($value)
	 * @example Validate::basic()->isCallable()
	 */
	public function isCallable(): callable {
		return static fn(mixed $v): bool => \is_callable($v);
	}

	/**
	 * Predicate: value is empty.
	 *
	 * @return callable(mixed):bool Closure that returns true if $value is empty
	 */
	public function isEmpty(): callable {
		return static fn(mixed $v): bool => $v === '' || $v === null || $v === false;
	}

	/**
	 * Predicate: value is not empty.
	 *
	 * @return callable(mixed):bool Closure that returns true if $value is not empty
	 */
	public function isNotEmpty(): callable {
		$empty = $this->isEmpty();
		return static fn(mixed $v): bool => !$empty($v);
	}
}

final class ValidateNumberGroup {
	/**
	 * Numeric minimum (inclusive).
	 *
	 * Behavior:
	 * - Accepts only ints or floats
	 * - Returns true if value >= $n
	 *
	 * @param int|float $n Lower bound (inclusive)
	 * @return callable(mixed):bool Closure that validates numeric minimum
	 *
	 * @example Validate::number()->min(1)
	 */
	public function min(int|float $n): callable {
		return static function (mixed $v) use ($n): bool {
			return (\is_int($v) || \is_float($v)) && $v >= $n;
		};
	}

	/**
	 * Numeric maximum (inclusive).
	 *
	 * Behavior:
	 * - Accepts only ints or floats
	 * - Returns true if value <= $n
	 *
	 * @param int|float $n Upper bound (inclusive)
	 * @return callable(mixed):bool Closure that validates numeric maximum
	 *
	 * @example Validate::number()->max(300)
	 */
	public function max(int|float $n): callable {
		return static function (mixed $v) use ($n): bool {
			return (\is_int($v) || \is_float($v)) && $v <= $n;
		};
	}

	/**
	 * Numeric inclusive range.
	 *
	 * Behavior:
	 * - Accepts only ints or floats
	 * - Returns true if min <= value <= max
	 *
	 * @param int|float $min Lower bound (inclusive)
	 * @param int|float $max Upper bound (inclusive)
	 * @return callable(mixed):bool Closure that validates numeric ranges
	 *
	 * @example Validate::number()->between(1, 300)
	 */
	public function between(int|float $min, int|float $max): callable {
		return static function (mixed $v) use ($min, $max): bool {
			if (! (\is_int($v) || \is_float($v))) {
				return false;
			}
			return $v >= $min && $v <= $max;
		};
	}
}

final class ValidateStringGroup {
	/**
	 * String minimum length (in bytes).
	 *
	 * Behavior:
	 * - Accepts only strings
	 * - Uses strlen (byte length); for multibyte support, provide your own validator
	 * - Returns true if length >= $n
	 *
	 * @param int $n Minimum length (inclusive)
	 * @return callable(mixed):bool Closure that validates string minimum length
	 *
	 * @example Validate::string()->minLength(1) // non-empty string
	 */
	public function minLength(int $n): callable {
		return static function (mixed $v) use ($n): bool {
			return \is_string($v) && \strlen($v) >= $n;
		};
	}

	/**
	 * String maximum length (in bytes).
	 *
	 * Behavior:
	 * - Accepts only strings
	 * - Uses strlen (byte length)
	 * - Returns true if length <= $n
	 *
	 * @param int $n Maximum length (inclusive)
	 * @return callable(mixed):bool Closure that validates string maximum length
	 *
	 * @example Validate::string()->maxLength(64)
	 */
	public function maxLength(int $n): callable {
		return static function (mixed $v) use ($n): bool {
			return \is_string($v) && \strlen($v) <= $n;
		};
	}

	/**
	 * String inclusive length range (in bytes).
	 *
	 * Behavior:
	 * - Accepts only strings
	 * - Uses strlen (byte length)
	 * - Returns true if min <= length <= max
	 *
	 * @param int $min Minimum length (inclusive)
	 * @param int $max Maximum length (inclusive)
	 * @return callable(mixed):bool Closure that validates string length range
	 *
	 * @example Validate::string()->lengthBetween(1, 64)
	 */
	public function lengthBetween(int $min, int $max): callable {
		return static function (mixed $v) use ($min, $max): bool {
			return \is_string($v) && ($l = \strlen($v)) >= $min && $l <= $max;
		};
	}

	/**
	 * String pattern validation using a PCRE regex.
	 *
	 * Behavior:
	 * - Accepts only strings
	 * - Returns true if preg_match($regex, $value) === 1
	 *
	 * @param string $regex PCRE pattern (including delimiters)
	 * @return callable(mixed):bool Closure that validates strings by regex match
	 *
	 * @example Validate::string()->pattern('/^[a-z0-9_]+$/')
	 */
	public function pattern(string $regex): callable {
		return static function (mixed $v) use ($regex): bool {
			return \is_string($v) && (bool) \preg_match($regex, $v);
		};
	}
}

final class ValidateCollectionGroup {
	/**
	 * Validate arrays whose every element satisfies the provided item validator.
	 *
	 * Behavior:
	 * - Returns false if the value is not an array
	 * - Iterates each element and applies $itemValidator($element)
	 * - Returns true only if all elements pass
	 *
	 * @param callable(mixed):bool $itemValidator Validator applied to each array element
	 * @return callable(mixed):bool Closure that validates homogeneous arrays
	 *
	 * @example Validate::collection()->listOf(Validate::basic()->isString())
	 */
	public function listOf(callable $itemValidator): callable {
		return static function (mixed $v) use ($itemValidator): bool {
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
	}

	/**
	 * Validate associative arrays by per-key validators; ignores extra keys.
	 *
	 * Behavior:
	 * - Returns false if the value is not an array
	 * - For each [$key => $validator] in $schema:
	 *   - The key must exist (array_key_exists)
	 *   - $validator($value[$key]) must return true
	 * - Extra keys not present in $schema are allowed (non-strict shape)
	 *
	 * @param array<string, callable(mixed):bool> $schema Map of required keys to their validators
	 * @return callable(mixed):bool Closure that validates object-like arrays
	 *
	 * @example Validate::collection()->shape([
	 *   'x' => Validate::basic()->isInt(),
	 *   'y' => Validate::basic()->isInt(),
	 * ])
	 */
	public function shape(array $schema): callable {
		return static function (mixed $v) use ($schema): bool {
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
	}

	/**
	 * Validate associative arrays strictly: required keys must exist, and no extra keys are allowed.
	 *
	 * Behavior:
	 * - Returns false if the value is not an array
	 * - For each [$key => $validator] in $schema: key must exist and validator must pass
	 * - Rejects if any additional key not present in $schema exists in the array
	 *
	 * @param array<string, callable(mixed):bool> $schema Map of required keys to their validators
	 * @return callable(mixed):bool Closure that validates exact-key object-like arrays
	 *
	 * @example Validate::collection()->strictShape([
	 *   'x' => Validate::basic()->isInt(),
	 *   'y' => Validate::basic()->isInt(),
	 * ])
	 */
	public function strictShape(array $schema): callable {
		return static function (mixed $v) use ($schema): bool {
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
	}

	/**
	 * Require presence of specific keys in an array without constraining values.
	 *
	 * Behavior:
	 * - Returns false if the value is not an array
	 * - Returns true only if all keys in $keys exist in the array (array_key_exists)
	 *
	 * @param array<int, string|int> $keys Keys that must be present
	 * @return callable(mixed):bool Closure that checks for key presence
	 *
	 * @example Validate::collection()->hasKeys(['x','y'])
	 */
	public function hasKeys(array $keys): callable {
		return static function (mixed $v) use ($keys): bool {
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
	}

	/**
	 * Enforce exact key set: requires that the array has exactly and only the given keys.
	 *
	 * Behavior:
	 * - Returns false if the value is not an array
	 * - Returns true only if there are no missing and no extra keys compared to $keys
	 *
	 * @param array<int, string|int> $keys Exact key set required
	 * @return callable(mixed):bool Closure that checks exact key set equality
	 *
	 * @example Validate::collection()->exactKeys(['x','y'])
	 */
	public function exactKeys(array $keys): callable {
		return static function (mixed $v) use ($keys): bool {
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
	}

	/**
	 * Minimum number of items in an array.
	 *
	 * Behavior:
	 * - Returns false if the value is not an array
	 * - Returns true if count($value) >= $n
	 *
	 * @param int $n Minimum number of items (>= 0)
	 * @return callable(mixed):bool Closure that validates array cardinality lower bound
	 *
	 * @example Validate::collection()->minItems(1) // non-empty array
	 */
	public function minItems(int $n): callable {
		return static function (mixed $v) use ($n): bool {
			return \is_array($v) && \count($v) >= $n;
		};
	}

	/**
	 * Maximum number of items in an array.
	 *
	 * Behavior:
	 * - Returns false if the value is not an array
	 * - Returns true if count($value) <= $n
	 *
	 * @param int $n Maximum number of items (>= 0)
	 * @return callable(mixed):bool Closure that validates array cardinality upper bound
	 *
	 * @example Validate::collection()->maxItems(10)
	 */
	public function maxItems(int $n): callable {
		return static function (mixed $v) use ($n): bool {
			return \is_array($v) && \count($v) <= $n;
		};
	}
}

final class ValidateEnumGroup {
	/**
	 * Strict membership against a fixed set of allowed values.
	 *
	 * Behavior:
	 * - Returns true if the input strictly equals (===) one of the provided values
	 * - Works for scalars or other comparable values; uses in_array(..., true) strict mode
	 *
	 * @param array<int,mixed> $values The finite set of allowed values
	 * @return callable(mixed):bool Closure that accepts only values in the set
	 *
	 * @example Validate::enums()->enum(['basic','pro','enterprise'])
	 */
	public function enum(array $values): callable {
		return static function (mixed $v) use ($values): bool {
			return \in_array($v, $values, true);
		};
	}

	/**
	 * PHP 8.1+ backed enum validator (by enum case value).
	 *
	 * Behavior:
	 * - Validates that the input strictly equals one of the backed enum case values
	 * - Rejects if the class does not exist, is not an enum, or is a unit enum
	 *
	 * @param class-string $enumClass Fully-qualified enum class name (backed enum)
	 * @return callable(mixed):bool Closure that accepts values present in the enum's ->value set
	 *
	 * @example Validate::enums()->backed(Mode::class) // where enum Mode: string { case basic='basic'; ... }
	 */
	public function backed(string $enumClass): callable {
		return static function (mixed $v) use ($enumClass): bool {
			if (!function_exists('enum_exists') || !enum_exists($enumClass)) {
				return false;
			}
			$cases = $enumClass::cases();
			foreach ($cases as $case) {
				if (!($case instanceof \BackedEnum)) {
					return false;
				}
				if ($v === $case->value) {
					return true;
				}
			}
			return false;
		};
	}

	/**
	 * PHP 8.1+ unit enum validator (by enum case name).
	 *
	 * Behavior:
	 * - Validates that the input is a string that equals one of the enum case names
	 * - Rejects if the class does not exist, is not an enum, or is a backed enum
	 *
	 * @param class-string $enumClass Fully-qualified enum class name (unit enum)
	 * @return callable(mixed):bool Closure that accepts strings matching enum case names
	 *
	 * @example Validate::enums()->unit(Flag::class) // where enum Flag { case On; case Off; }
	 */
	public function unit(string $enumClass): callable {
		return static function (mixed $v) use ($enumClass): bool {
			if (!function_exists('enum_exists') || !enum_exists($enumClass)) {
				return false;
			}
			$cases = $enumClass::cases();
			foreach ($cases as $case) {
				if ($case instanceof \BackedEnum) {
					return false;
				}
			}
			return is_string($v) && in_array($v, array_map(fn($c) => $c->name, $cases), true);
		};
	}
}

final class ValidateComposeGroup {
	/**
	 * Allow nulls while delegating validation for non-null values.
	 *
	 * Behavior:
	 * - Returns true if the input value is null
	 * - Otherwise, returns the result of `$validator($value)`
	 *
	 * @param callable(mixed):bool $validator Validator applied when value is not null
	 * @return callable(mixed):bool Closure that validates nullable values
	 *
	 * @example Validate::compose()->nullable(Validate::basic()->isString())
	 */
	public function nullable(callable $validator): callable {
		return static function (mixed $v) use ($validator): bool {
			return $v === null || $validator($v);
		};
	}

	/**
	 * Alias of nullable(). Provided for semantic readability.
	 *
	 * Behavior:
	 * - Identical to `nullable($validator)`
	 *
	 * @param callable(mixed):bool $validator Validator applied when value is not null
	 * @return callable(mixed):bool Closure that validates optional values
	 *
	 * @example Validate::compose()->optional(Validate::basic()->isInt())
	 */
	public function optional(callable $validator): callable {
		return self::nullable($validator);
	}

	/**
	 * Logical OR across validators. Passes if any validator passes.
	 *
	 * Behavior:
	 * - Iterates validators in order; returns true on first `$validator($value) === true`
	 * - Returns false only if all validators return false
	 *
	 * @param callable(mixed):bool ...$validators One or more validators to combine
	 * @return callable(mixed):bool Closure that returns true if any validator accepts the value
	 *
	 * @example Validate::compose()->union(Validate::basic()->isInt(), Validate::basic()->isFloat())
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
	 *
	 * Behavior:
	 * - Iterates validators; returns false on first `$validator($value) === false`
	 * - Returns true only if every validator returns true
	 *
	 * @param callable(mixed):bool ...$validators One or more validators to combine
	 * @return callable(mixed):bool Closure that returns true if all validators accept the value
	 *
	 * @example Validate::compose()->all(
	 *   Validate::basic()->isString(),
	 *   Validate::string()->lengthBetween(1, 64)
	 * )
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
	 *
	 * Behavior:
	 * - Iterates validators; returns false on first `$validator($value) === true`
	 * - Returns true only if every validator returns false
	 *
	 * @param callable(mixed):bool ...$validators One or more validators to negate collectively
	 * @return callable(mixed):bool Closure that returns true if none of the validators accept the value
	 *
	 * @example Validate::compose()->none(Validate::enums()->enum(['deprecated','removed']))
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

final class ValidateFormatGroup {
	/**
	 * Pragmatic RFC 5322-lite email validator.
	 *
	 * Behavior:
	 * - Accepts only strings
	 * - Uses PHP's FILTER_VALIDATE_EMAIL for practical coverage
	 *
	 * @return callable(mixed):bool Closure that returns true for valid-looking emails
	 *
	 * @example Validate::format()->email()
	 */
	public function email(): callable {
		return static function (mixed $v): bool {
			return \is_string($v) && (false !== filter_var($v, FILTER_VALIDATE_EMAIL));
		};
	}

	/**
	 * Validate that a string is valid JSON.
	 *
	 * Behavior:
	 * - Accepts only strings
	 * - Returns true if json_decode succeeds without error (any JSON type allowed)
	 *
	 * @return callable(mixed):bool Closure that returns true for valid JSON strings
	 *
	 * @example Validate::format()->jsonString()
	 */
	public function jsonString(): callable {
		return static function (mixed $v): bool {
			if (!\is_string($v)) {
				return false;
			}
			json_decode($v);
			return json_last_error() === JSON_ERROR_NONE;
		};
	}

	/**
	 * Pragmatic E.164 phone number validator.
	 *
	 * Behavior:
	 * - Accepts only strings
	 * - Matches "+" (required) followed by country code 1-9 then up to 14 digits (max 15 characters total)
	 * - No spaces or separators allowed (normalized numeric format)
	 *
	 * Pattern: ^\+[1-9]\d{1,14}$
	 *
	 * @return callable(mixed):bool Closure that returns true for E.164-like phone strings
	 *
	 * @example Validate::format()->phone()
	 */
	public function phone(): callable {
		return static function (mixed $v): bool {
			return \is_string($v) && (bool) \preg_match('/^\\+[1-9]\\d{1,14}$/', $v);
		};
	}

	/**
	 * Validate a general URL using FILTER_VALIDATE_URL (pragmatic).
	 *
	 * @return callable(mixed):bool
	 *
	 * @example Validate::format()->url()
	 */
	public function url(): callable {
		return static function (mixed $v): bool {
			return \is_string($v) && (false !== filter_var($v, FILTER_VALIDATE_URL));
		};
	}

	/**
	 * Validate a domain name (requires at least one dot, TLD 2-63, RFC-like labels).
	 * Does not accept protocol or path.
	 *
	 * @return callable(mixed):bool
	 *
	 * @example Validate::format()->domain()
	 */
	public function domain(): callable {
		return static function (mixed $v): bool {
			if (!\is_string($v)) {
				return false;
			}
			// Labels: [a-z0-9]([-a-z0-9]{0,61}[a-z0-9])? ; at least two labels separated by dots; TLD >=2
			return (bool) \preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i', $v);
		};
	}

	/**
	 * Validate a hostname (allows single-label like "localhost" or full domains).
	 * Does not accept protocol or path.
	 *
	 * @return callable(mixed):bool
	 *
	 * @example Validate::format()->hostname()
	 */
	public function hostname(): callable {
		return static function (mixed $v): bool {
			if (!\is_string($v)) {
				return false;
			}
			if ($v === 'localhost') {
				return true;
			}
			// Allow single label 1-63 chars or domain()
			if ((bool) \preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])$/i', $v)) {
				return true;
			}
			return (bool) \preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i', $v);
		};
	}

	/**
	 * Validate an origin string: scheme://host[:port] with no path/query/fragment.
	 * Pragmatic: accepts http(s) and other schemes recognized by parse_url.
	 *
	 * @return callable(mixed):bool
	 *
	 * @example Validate::format()->origin()
	 */
	public function origin(): callable {
		return static function (mixed $v): bool {
			if (!\is_string($v)) {
				return false;
			}
			$parts = parse_url($v);
			if ($parts === false) {
				return false;
			}
			if (!isset($parts['scheme'], $parts['host'])) {
				return false;
			}
			// No path, query, or fragment allowed
			if (isset($parts['path']) || isset($parts['query']) || isset($parts['fragment'])) {
				return false;
			}
			// Hostname/domain check
			$host   = $parts['host'];
			$hostOk = $host === 'localhost' || (bool) \preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i', $host) || (bool) \preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])$/i', $host);
			if (!$hostOk) {
				return false;
			}
			// Port, if present, must be numeric 1-65535
			if (isset($parts['port']) && (!\is_int($parts['port']) || $parts['port'] < 1 || $parts['port'] > 65535)) {
				return false;
			}
			return true;
		};
	}
}
