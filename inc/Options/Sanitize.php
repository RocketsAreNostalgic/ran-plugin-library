<?php
declare(strict_types=1);

namespace Ran\PluginLib\Options;

/**
 * Utility helpers for schema sanitization and canonicalization.
 *
 * Primary goal: provide order-insensitive deep normalization so that
 * semantically equivalent inputs normalize to an identical canonical
 * structure, enabling strict (===) equality checks to behave as a
 * robust no-op guard and reduce needless DB writes.
 */
final class Sanitize {
	/**
	 * Canonicalize values deeply:
	 * - Objects are converted to arrays (prefers JsonSerializable where available)
	 * - Associative arrays have their keys sorted (ksort), values normalized recursively
	 * - List arrays are normalized element-wise, then sorted by JSON representation (stable)
	 *
	 * Note: Only use for keys where list order is not semantically meaningful.
	 * For order-sensitive data, do not apply this sanitizer.
	 */
	public static function orderInsensitiveDeep(mixed $value): mixed {
		// Convert objects to arrays for comparison
		if (\is_object($value)) {
			if ($value instanceof \JsonSerializable) {
				$value = $value->jsonSerialize();
			} else {
				// Best-effort conversion of public properties
				$value = get_object_vars($value);
			}
		}

		if (!\is_array($value)) {
			return $value;
		}

		// Distinguish list vs associative
		$is_list = array_keys($value) === range(0, count($value) - 1);

		if ($is_list) {
			// Normalize each element first
			$normalized = array_map(array(self::class, 'orderInsensitiveDeep'), $value);
			// Stable sort by JSON representation to obtain a canonical order
			usort($normalized, static function ($a, $b): int {
				$ja = json_encode($a, JSON_UNESCAPED_UNICODE);
				$jb = json_encode($b, JSON_UNESCAPED_UNICODE);
				return strcmp((string) $ja, (string) $jb);
			});
			return $normalized;
		}

		// Associative map: normalize values and sort by key
		foreach ($value as $k => $v) {
			$value[$k] = self::orderInsensitiveDeep($v);
		}
		ksort($value);
		return $value;
	}

	/**
	 * Canonicalize values at the top level only (shallow):
	 * - Objects are converted to arrays (prefers JsonSerializable where available)
	 * - If the top-level is an associative array: keys are sorted (ksort); nested arrays are left untouched
	 * - If the top-level is a list: elements are left as-is except for a stable top-level sort by JSON representation
	 *
	 * Use when only the top-level ordering should be ignored while preserving
	 * the original ordering semantics of nested structures.
	 */
	public static function orderInsensitiveShallow(mixed $value): mixed {
		// Convert objects to arrays for comparison
		if (\is_object($value)) {
			if ($value instanceof \JsonSerializable) {
				$value = $value->jsonSerialize();
			} else {
				$value = get_object_vars($value);
			}
		}

		if (!\is_array($value)) {
			return $value;
		}

		$is_list = array_keys($value) === range(0, count($value) - 1);

		if ($is_list) {
			// Top-level list: do not recurse; provide stable ordering only if order is irrelevant
			$copy = $value;
			usort($copy, static function ($a, $b): int {
				$ja = json_encode($a, JSON_UNESCAPED_UNICODE);
				$jb = json_encode($b, JSON_UNESCAPED_UNICODE);
				return strcmp((string) $ja, (string) $jb);
			});
			return $copy;
		}

		// Top-level assoc: sort keys only; do not recurse into nested arrays
		ksort($value);
		return $value;
	}
}
