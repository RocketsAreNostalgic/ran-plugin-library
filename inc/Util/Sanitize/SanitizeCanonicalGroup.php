<?php
declare(strict_types=1);

namespace Ran\PluginLib\Util\Sanitize;

/**
 * Canonicalizers (order-insensitive helpers).
 *
 * Methods accept a value and return a canonicalized value directly.
 */
final class SanitizeCanonicalGroup {
	/**
	 * Canonicalize values deeply:
	 * - Objects are converted to arrays (prefers JsonSerializable where available)
	 * - Associative arrays have their keys sorted (ksort), values normalized recursively
	 * - List arrays are normalized element-wise, then sorted by JSON representation (stable)
	 *
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example $clean = (Sanitize::canonical()->order_insensitive_deep())($value);
	 * @example $clean = Sanitize::canonical()->order_insensitive_deep($value);
	 *
	 * @param mixed $value
	 * @return callable(mixed):mixed|mixed
	 */
	public function order_insensitive_deep(mixed $value = null): mixed {
		// Dual API: no args → return callable; with arg → return normalized value
		if (func_num_args() === 0) {
			$that = $this;
			return static function (mixed $v) use ($that): mixed {
				return $that->order_insensitive_deep($v);
			};
		}
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
			$normalized = array_map(fn($v) => $this->order_insensitive_deep($v), $value);
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
			$value[$k] = $this->order_insensitive_deep($v);
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
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example $clean = (Sanitize::canonical()->order_insensitive_shallow())($value);
	 * @example $clean = Sanitize::canonical()->order_insensitive_shallow($value);
	 *
	 * @param mixed $value
	 * @return callable(mixed):mixed
	 */
	public function order_insensitive_shallow(mixed $value = null): mixed {
		// Dual API: no args → return callable; with arg → return normalized value
		if (func_num_args() === 0) {
			$that = $this;
			return static function (mixed $v) use ($that): mixed {
				return $that->order_insensitive_shallow($v);
			};
		}
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
