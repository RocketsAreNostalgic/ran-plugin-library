<?php
declare(strict_types=1);

namespace Ran\PluginLib\Util;

use Ran\PluginLib\Util\Validate\ValidateEnumGroup;
use Ran\PluginLib\Util\Validate\ValidateBasicGroup;
use Ran\PluginLib\Util\Validate\ValidateFormatGroup;
use Ran\PluginLib\Util\Validate\ValidateNumberGroup;
use Ran\PluginLib\Util\Validate\ValidateStringGroup;
use Ran\PluginLib\Util\Validate\ValidateComposeGroup;
use Ran\PluginLib\Util\Validate\ValidateCollectionGroup;

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
 *
 *
 * @example
 *  use Ran\PluginLib\Util\Validate\Validate;
 *
 * ///Chain validators and invoke the resulting callable on a value
 * $isValid = (Validate::compose()->all(
 *    Validate::basic()->isString(),
 *    Validate::string()->lengthBetween(1, 64)
 *  ))($value);
 *
* @example
 *  /// Numeric range
 *  $isPort = (Validate::compose()->all(
 *    Validate::basic()->isInt(),
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
	 * @example $isValid = (Validate::string()->minLength(1))($value);
	 *
	 * @return \Ran\PluginLib\Util\Validate\ValidateStringGroup
	 * @method callable(mixed):bool minLength(int $n)
	 * @method callable(mixed):bool maxLength(int $n)
	 * @method callable(mixed):bool lengthBetween(int $min, int $max)
	 * @method callable(mixed):bool pattern(string $regex)
	 */
	public static function string(): ValidateStringGroup {
		return new ValidateStringGroup();
	}

	/**
	 * Access collection and shape validators.
	 *
	 * @example $isValid = (Validate::collection()->shape([
	 *     'name' => Validate::string()->minLength(1),
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
	 * @method callable(mixed):bool backed(string $enumClass)
	 * @method callable(mixed):bool unit(string $enumClass)
	 */
	public static function enums(): ValidateEnumGroup {
		return new ValidateEnumGroup();
	}

	/**
	 * Access composition helpers (logical combinators) for validators.
	 *
	 * @example $isValid = (Validate::compose()->all(
	 *    Validate::basic()->isString(),
	 *    Validate::string()->lengthBetween(1, 64)
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
	 * @method callable(mixed):bool jsonString()
	 * @method callable(mixed):bool phone()
	 * @method callable(mixed):bool url()
	 * @method callable(mixed):bool domain()
	 * @method callable(mixed):bool hostname()
	 * @method callable(mixed):bool origin()
	 */
	public static function format(): ValidateFormatGroup {
		return new ValidateFormatGroup();
	}
}
