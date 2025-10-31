<?php
declare(strict_types=1);

namespace Ran\PluginLib\Util;

use Ran\PluginLib\Util\Validate\ValidateTemporalGroup;
use Ran\PluginLib\Util\Validate\ValidateStringGroup;
use Ran\PluginLib\Util\Validate\ValidateNumberGroup;
use Ran\PluginLib\Util\Validate\ValidateFormatGroup;
use Ran\PluginLib\Util\Validate\ValidateEnumGroup;
use Ran\PluginLib\Util\Validate\ValidateComposeGroup;
use Ran\PluginLib\Util\Validate\ValidateCollectionGroup;
use Ran\PluginLib\Util\Validate\ValidateBasicGroup;

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
