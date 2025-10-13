<?php
declare(strict_types=1);

namespace Ran\PluginLib\Util;

use Ran\PluginLib\Util\Validate\ValidateEnumGroup;
use Ran\PluginLib\Util\Validate\ValidateBasicGroup;
use Ran\PluginLib\Util\Validate\ValidateFormatGroup;
use Ran\PluginLib\Util\Validate\ValidateNumberGroup;
use Ran\PluginLib\Util\Validate\ValidateStringGroup;
use Ran\PluginLib\Util\Validate\ValidateComposeGroup;
use Ran\PluginLib\Util\Validate\ValidateTemporalGroup;
use Ran\PluginLib\Util\Validate\ValidateCollectionGroup;

/**
 * Validation utilities for schema-driven option values.
 *
 * What this provides (built-ins):
 * - Type predicates: is_bool, is_int, isFloat, is_String, is_array, is_object, is_null, is_scalar, is_numeric,
 *   is_countable, is_nullable, is_callable (and corresponding negative helpers for a few cases)
 * - Type maps: validatorForType('string'|'int'|...) and validateByType($value, 'int')
 * - Simple default inspection: inferSimpleTypeFromValue($default)
 *
 * Usage in schema (examples):
 *
 *  $schema = [
 *    'enabled' => [
 *      'default'  => false,
 *      'validate' => [Validate::class, 'is_bool'],
 *    ],
 *    'name' => [
 *      'default'  => 'demo',
 *      'validate' => [Validate::class, 'isString'],
 *    ],
 *    'timeout' => [
 *      'default'  => 30,
 *      'validate' => [Validate::class, 'is_int'],
 *    ],
 *  ];
 *
 * Notes:
 * - These helpers are intentionally small and generic. Domain‑specific validation logic should still live in your
 *   own callable validators declared in the schema.
 * - Strict schema mode requires an explicit 'validate' callable for every key; you can use these helpers for quick
 *   type checks and combine them with additional custom checks as needed.
 *
 *
 * @example
 *  use Ran\PluginLib\Util\Validate\Validate;
 *
 * ///Chain validators and invoke the resulting callable on a value
 * $isValid = (Validate::compose()->all(
 *    Validate::basic()->is_string(),
 *    Validate::string()->length_between(1, 64)
 *  ))($value);
 *
* @example
 *  /// Numeric range
 *  $isPort = (Validate::compose()->all(
 *    Validate::basic()->is_int(),
 *    Validate::number()->between(1, 65535)
 *  ))($port);
 *
 * @method static validatorForType(string $type): ?callable
 * @method static validateByType(mixed $value, string $type): bool
 * @method static inferSimpleTypeFromValue(mixed $value): ?string
 * @method static ValidateBasicGroup basic()
 * @method static ValidateNumberGroup number()
 * @method static ValidateStringGroup string()
 * @method static ValidateCollectionGroup collection()
 * @method static ValidateEnumGroup enums()
 * @method static ValidateComposeGroup compose()
 * @method static ValidateFormatGroup format()
 * @method static ValidateTemporalGroup temporal()
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
	 * @return \Ran\PluginLib\Util\Validate\ValidateBasicGroup
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
	public static function basic(): ValidateBasicGroup {
		return new ValidateBasicGroup();
	}

	/**
	 * Access numeric constraint validators.
	 *
	 * @example $isValid = (Validate::number()->min(1))($value);
	 *
	 * @return \Ran\PluginLib\Util\Validate\ValidateNumberGroup
	 * @method callable(mixed):bool min(int|float $n)
	 * @method callable(mixed):bool max(int|float $n)
	 * @method callable(mixed):bool between(int|float $min, int|float $max)
	 */
	public static function number(): ValidateNumberGroup {
		return new ValidateNumberGroup();
	}

	/**
	 * Access string constraint validators.
	 *
	 * @example $isValid = (Validate::string()->min_length(1))($value);
	 *
	 * @return \Ran\PluginLib\Util\Validate\ValidateStringGroup
	 * @method callable(mixed):bool minLength(int $n)
	 * @method callable(mixed):bool maxLength(int $n)
	 * @method callable(mixed):bool length_between(int $min, int $max)
	 * @method callable(mixed):bool pattern(string $regex)
	 */
	public static function string(): ValidateStringGroup {
		return new ValidateStringGroup();
	}

	/**
	 * Access collection and shape validators.
	 *
	 * @example $isValid = (Validate::collection()->shape([
	 *     'name' => Validate::string()->min_length(1),
	 *     'age' => Validate::number()->min(0),
	 * ]))($value);
	 *
	 * @return \Ran\PluginLib\Util\Validate\ValidateCollectionGroup
	 * @method callable(mixed):bool listOf(callable $itemValidator)
	 * @method callable(mixed):bool shape(array $schema)
	 * @method callable(mixed):bool strictShape(array $schema)
	 * @method callable(mixed):bool hasKeys(array $keys)
	 * @method callable(mixed):bool exactKeys(array $keys)
	 * @method callable(mixed):bool minItems(int $n)
	 * @method callable(mixed):bool maxItems(int $n)
	 */
	public static function collection(): ValidateCollectionGroup {
		return new ValidateCollectionGroup();
	}

	/**
	 * Access enum validators for fixed sets and PHP 8.1+ enums.
	 *
	 * @example $isValid = (Validate::enums()->enum([1, 2, 3]))($value);
	 *
	 * @return \Ran\PluginLib\Util\Validate\ValidateEnumGroup
	 * @method callable(mixed):bool enum(array $values)
	 * @method callable(mixed):bool backed_enum(string $enumClass)
	 * @method callable(mixed):bool unit(string $enumClass)
	 */
	public static function enums(): ValidateEnumGroup {
		return new ValidateEnumGroup();
	}

	/**
	 * Access composition helpers (logical combinators) for validators.
	 *
	 * @example $isValid = (Validate::compose()->all(
	 *    Validate::basic()->is_string(),
	 *    Validate::string()->length_between(1, 64)
	 *  ))($value);
	 *
	 * @return \Ran\PluginLib\Util\Validate\ValidateComposeGroup
	 * @method callable(mixed):bool nullable(callable $validator)
	 * @method callable(mixed):bool optional(callable $validator)
	 * @method callable(mixed):bool union(callable ...$validators)
	 * @method callable(mixed):bool all(callable ...$validators)
	 * @method callable(mixed):bool none(callable ...$validators)
	 */
	public static function compose(): ValidateComposeGroup {
		return new ValidateComposeGroup();
	}

	/**
	 * Access common format validators (e.g., email, phone).
	 *
	 * @example $isValid = (Validate::format()->email())($value);
	 *
	 * @return \Ran\PluginLib\Util\Validate\ValidateFormatGroup
	 * @method callable(mixed):bool email()
	 * @method callable(mixed):bool json_string()
	 * @method callable(mixed):bool phone()
	 * @method callable(mixed):bool url()
	 * @method callable(mixed):bool domain()
	 * @method callable(mixed):bool hostname()
	 * @method callable(mixed):bool origin()
	 */
	public static function format(): ValidateFormatGroup {
		return new ValidateFormatGroup();
	}

	/**
	 * Access temporal validators (date, time, datetime).
	 *
	 * Provides helpers like `date()`, `time()`, `datetime()`, and `custom_datetime()`.
	 *
	 * @return \Ran\PluginLib\Util\Validate\ValidateTemporalGroup
	 */
	public static function temporal(): ValidateTemporalGroup {
		return new ValidateTemporalGroup();
	}
}
