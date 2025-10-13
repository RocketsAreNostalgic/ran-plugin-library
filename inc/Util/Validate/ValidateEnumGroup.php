<?php
declare(strict_types=1);

namespace Ran\PluginLib\Util\Validate;

/**
 * Enum validators (fixed sets and PHP 8.1 enums).
 * Dual-mode: no argument returns a callable; with value, applies immediately.
 *
 * @example $isValid = (Validate::enums()->enum([1, 2, 3]))($value);
 * @example $isValid = Validate::enums()->enum([1, 2, 3], $value);
 *
 * @method callable(mixed):bool enum(array $values)
 * @method callable(mixed):bool backed_enum(string $enumClass)
 * @method callable(mixed):bool unit(string $enumClass)
 */
final class ValidateEnumGroup {
	/**
	 * Strict membership against a fixed set of allowed values.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example Validate::enums()->enum()($value)
	 * @example Validate::enums()->enum($value)
	 *
	 * @param array<int,mixed> $values The finite set of allowed values
	 * @param mixed $value Optional value to validate immediately
	 * @return callable(mixed):bool|bool Closure that accepts only values in the set or validation result
	 */
	public function enum(array $values, mixed $value = null): callable|bool {
		$fn = static function (mixed $v) use ($values): bool {
			return \in_array($v, $values, true);
		};
		return \func_num_args() === 1 ? $fn : $fn($value);
	}

	/**
	 * PHP 8.1+ backed enum validator (by enum case value).
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example Validate::enums()->backed_enum()($value)
	 * @example Validate::enums()->backed_enum($value)
	 *
	 * @param class-string $enumClass Fully-qualified enum class name (backed enum)
	 * @param mixed $value Optional value to validate immediately
	 * @return callable(mixed):bool|bool Closure that accepts values present in the enum's ->value set or validation result
	 */
	public function backed_enum(string $enumClass, mixed $value = null): callable|bool {
		$fn = static function (mixed $v) use ($enumClass): bool {
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
		return \func_num_args() === 1 ? $fn : $fn($value);
	}

	/**
	 * PHP 8.1+ unit enum validator (by enum case name).
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example Validate::enums()->unit()($value)
	 * @example Validate::enums()->unit($value)
	 *
	 * @param class-string $enumClass Fully-qualified enum class name (unit enum)
	 * @param mixed $value Optional value to validate immediately
	 * @return callable(mixed):bool|bool Closure that accepts strings matching enum case names or validation result
	 */
	public function unit(string $enumClass, mixed $value = null): callable|bool {
		$fn = static function (mixed $v) use ($enumClass): bool {
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
		return $value === null ? $fn : $fn($value);
	}
}
