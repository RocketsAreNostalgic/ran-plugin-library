<?php
declare(strict_types=1);

namespace Ran\PluginLib\Options;

/**
 * Validation utilities for schema-driven option values.
 *
 * Provides simple type validators and helpers to map a declared/inferred
 * type to a validator. Complex, domain-specific validation should still be
 * provided via the schema's 'validate' callable.
 */
final class Validate {
	/** @var array<string, callable(mixed):bool> */
	private const TYPE_VALIDATORS = array(
	    'bool'    => array(self::class, 'isBool'),
	    'boolean' => array(self::class, 'isBool'),
	    'int'     => array(self::class, 'isInt'),
	    'integer' => array(self::class, 'isInt'),
	    'float'   => array(self::class, 'isFloat'),
	    'double'  => array(self::class, 'isFloat'),
	    'string'  => array(self::class, 'isString'),
	    'array'   => array(self::class, 'isArray'),
	    'object'  => array(self::class, 'isObject'),
	    'null'    => array(self::class, 'isNull'),
	    'mixed'   => array(self::class, 'alwaysTrue'),
	);

	public static function validatorForType(string $type): ?callable {
		$key = strtolower($type);
		return self::TYPE_VALIDATORS[$key] ?? null;
	}

	public static function validateByType(mixed $value, string $type): bool {
		$v = self::validatorForType($type);
		if ($v === null) {
			return true; // Unknown type → do not block
		}
		return (bool) $v($value);
	}

	public static function inferSimpleTypeFromDefault(mixed $default): ?string {
		// Do not attempt to resolve callables here.
		if (\is_callable($default)) {
			return null;
		}
		$t = gettype($default);
		switch ($t) {
			case 'boolean': return 'bool';
			case 'integer': return 'int';
			case 'double':  return 'float';
			case 'string':  return 'string';
			case 'array':   return 'array';
			case 'object':  return 'object';
			case 'NULL':    return 'null';
			default:        return null;
		}
	}

	// --- Basic validators ---
	public static function isBool(mixed $v): bool {
		return \is_bool($v);
	}
	public static function isInt(mixed $v): bool {
		return \is_int($v);
	}
	public static function isFloat(mixed $v): bool {
		return \is_float($v);
	}
	public static function isString(mixed $v): bool {
		return \is_string($v);
	}
	public static function isArray(mixed $v): bool {
		return \is_array($v);
	}
	public static function isObject(mixed $v): bool {
		return \is_object($v);
	}
	public static function isNull(mixed $v): bool {
		return $v === null;
	}
	public static function alwaysTrue(mixed $v): bool {
		return true;
	}
}
