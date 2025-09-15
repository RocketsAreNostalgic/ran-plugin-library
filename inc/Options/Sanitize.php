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
	 * Access string sanitizers (lightweight, pure, idempotent).
	 *
	 * @return SanitizeStringGroup
	 */
	public static function string(): SanitizeStringGroup {
		return new SanitizeStringGroup();
	}

	/**
	 * Access number/boolean sanitizers.
	 *
	 * @return SanitizeNumberGroup
	 */
	public static function number(): SanitizeNumberGroup {
		return new SanitizeNumberGroup();
	}

	/**
	 * Access array/list/map sanitizers.
	 *
	 * @return SanitizeArrayGroup
	 */
	public static function array(): SanitizeArrayGroup {
		return new SanitizeArrayGroup();
	}

	/**
	 * Access JSON sanitizers.
	 *
	 * @return SanitizeJsonGroup
	 */
	public static function json(): SanitizeJsonGroup {
		return new SanitizeJsonGroup();
	}

	/**
	 * Access sanitizer combinators (composition helpers).
	 *
	 * @return SanitizeCombineGroup
	 */
	public static function combine(): SanitizeCombineGroup {
		return new SanitizeCombineGroup();
	}

	/**
	 * Access canonicalizers (order-insensitive helpers) as callables.
	 * Keeps existing static methods for backward compatibility.
	 *
	 * @return SanitizeCanonicalGroup
	 */
	public static function canonical(): SanitizeCanonicalGroup {
		return new SanitizeCanonicalGroup();
	}
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

// --- Grouped sanitizer classes (return callables) ---

final class SanitizeStringGroup {
	/**
	 * Trim leading and trailing whitespace for strings; pass-through otherwise.
	 *
	 * @return callable(mixed):mixed
	 */
	public function trim(): callable {
		return static function (mixed $v): mixed {
			return \is_string($v) ? \trim($v) : $v;
		};
	}

	/**
	 * Lowercase strings; pass-through otherwise.
	 *
	 * @return callable(mixed):mixed
	 */
	public function toLower(): callable {
		return static function (mixed $v): mixed {
			return \is_string($v) ? \mb_strtolower($v) : $v;
		};
	}

	/**
	 * strip_tags on strings; pass-through otherwise.
	 *
	 * @return callable(mixed):mixed
	 */
	public function stripTags(): callable {
		return static function (mixed $v): mixed {
			return \is_string($v) ? \strip_tags($v) : $v;
		};
	}
}

final class SanitizeNumberGroup {
	/**
	 * Cast numeric-like values to int; pass-through otherwise.
	 *
	 * @return callable(mixed):mixed
	 */
	public function toInt(): callable {
		return static function (mixed $v): mixed {
			if (\is_int($v)) {
				return $v;
			}
			if (\is_float($v)) {
				return (int) $v;
			}
			if (\is_string($v) && is_numeric($v)) {
				return (int) $v;
			}
			return $v;
		};
	}

	/**
	 * Cast numeric-like values to float; pass-through otherwise.
	 *
	 * @return callable(mixed):mixed
	 */
	public function toFloat(): callable {
		return static function (mixed $v): mixed {
			if (\is_float($v)) {
				return $v;
			}
			if (\is_int($v)) {
				return (float) $v;
			}
			if (\is_string($v) && is_numeric($v)) {
				return (float) $v;
			}
			return $v;
		};
	}

	/**
	 * Coerce only strict boolean-like string literals to bool; pass-through otherwise.
	 * Accepted: true,false (lowercase), 1,0 (int), true/false (bool).
	 *
	 * @return callable(mixed):mixed
	 */
	public function toBoolStrict(): callable {
		return static function (mixed $v): mixed {
			if (\is_bool($v)) {
				return $v;
			}
			if ($v === 1 || $v === 0) {
				return (bool) $v;
			}
			if ($v === 'true') {
				return true;
			}
			if ($v === 'false') {
				return false;
			}
			return $v;
		};
	}
}

final class SanitizeArrayGroup {
	/**
	 * Ensure an array is a list: if associative, return values reindexed; if list, return as-is; pass-through otherwise.
	 *
	 * @return callable(mixed):mixed
	 */
	public function ensureList(): callable {
		return static function (mixed $v): mixed {
			if (!\is_array($v)) {
				return $v;
			}
			$is_list = array_keys($v) === range(0, count($v) - 1);
			return $is_list ? $v : array_values($v);
		};
	}

	/**
	 * Remove duplicate elements from a list while preserving original order; pass-through for non-arrays.
	 *
	 * @return callable(mixed):mixed
	 */
	public function uniqueList(): callable {
		return static function (mixed $v): mixed {
			if (!\is_array($v)) {
				return $v;
			}
			$seen = array();
			$out  = array();
			foreach ($v as $item) {
				$key = json_encode($item, JSON_UNESCAPED_UNICODE);
				if (!array_key_exists((string) $key, $seen)) {
					$seen[(string) $key] = true;
					$out[]               = $item;
				}
			}
			return $out;
		};
	}

	/**
	 * Sort associative arrays by key; pass-through for non-arrays and lists.
	 *
	 * @return callable(mixed):mixed
	 */
	public function ksortAssoc(): callable {
		return static function (mixed $v): mixed {
			if (!\is_array($v)) {
				return $v;
			}
			$is_list = array_keys($v) === range(0, count($v) - 1);
			if ($is_list) {
				return $v;
			}
			$copy = $v;
			ksort($copy);
			return $copy;
		};
	}
}

final class SanitizeJsonGroup {
	/**
	 * Decode valid JSON strings to PHP values; pass-through otherwise.
	 *
	 * @return callable(mixed):mixed
	 */
	public function decodeToValue(): callable {
		return static function (mixed $v): mixed {
			if (!\is_string($v)) {
				return $v;
			}
			$decoded = json_decode($v, true);
			return (json_last_error() === JSON_ERROR_NONE) ? $decoded : $v;
		};
	}

	/**
	 * Decode to associative array (JSON object); pass-through if not a JSON object string.
	 *
	 * @return callable(mixed):mixed
	 */
	public function decodeObject(): callable {
		return static function (mixed $v): mixed {
			if (!\is_string($v)) {
				return $v;
			}
			$decoded = json_decode($v, true);
			return (json_last_error() === JSON_ERROR_NONE && \is_array($decoded) && array_keys($decoded) !== range(0, count($decoded) - 1)) ? $decoded : $v;
		};
	}

	/**
	 * Decode to list array (JSON array); pass-through if not a JSON array string.
	 *
	 * @return callable(mixed):mixed
	 */
	public function decodeArray(): callable {
		return static function (mixed $v): mixed {
			if (!\is_string($v)) {
				return $v;
			}
			$decoded = json_decode($v, true);
			return (json_last_error() === JSON_ERROR_NONE && \is_array($decoded) && array_keys($decoded) === range(0, count($decoded) - 1)) ? $decoded : $v;
		};
	}
}

final class SanitizeCombineGroup {
	/**
	 * Combine multiple sanitizers into one by piping the output of each into the next.
	 *
	 * @param callable(mixed):mixed ...$sanitizers
	 * @return callable(mixed):mixed
	 */
	public function pipe(callable ...$sanitizers): callable {
		return static function (mixed $v) use ($sanitizers): mixed {
			$out = $v;
			foreach ($sanitizers as $s) {
				$out = $s($out);
			}
			return $out;
		};
	}
}

final class SanitizeCanonicalGroup {
	/**
	 * Wrap orderInsensitiveDeep as a callable sanitizer.
	 *
	 * @return callable(mixed):mixed
	 */
	public function orderInsensitiveDeep(): callable {
		return static function (mixed $v): mixed {
			return Sanitize::orderInsensitiveDeep($v);
		};
	}

	/**
	 * Wrap orderInsensitiveShallow as a callable sanitizer.
	 *
	 * @return callable(mixed):mixed
	 */
	public function orderInsensitiveShallow(): callable {
		return static function (mixed $v): mixed {
			return Sanitize::orderInsensitiveShallow($v);
		};
	}
}
