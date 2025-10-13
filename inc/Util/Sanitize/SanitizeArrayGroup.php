<?php
declare(strict_types=1);

namespace Ran\PluginLib\Util\Sanitize;

/**
 * Array/list/map sanitizers.
 */
final class SanitizeArrayGroup {
	/**
	 * Ensure an array is a list: if associative, return values reindexed; if list, return as-is; pass-through otherwise.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example $clean = (Sanitize::array()->ensure_list())($value);
	 * @example $clean = Sanitize::array()->ensure_list($value);
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	public function ensure_list(mixed $value = null): mixed {
		$fn = static function (mixed $v): mixed {
			if (!\is_array($v)) {
				return $v;
			}
			$is_list = array_keys($v) === range(0, count($v) - 1);
			return $is_list ? $v : array_values($v);
		};
		return \func_num_args() === 0 ? $fn : $fn($value);
	}

	/**
	 * Remove duplicate elements from a list while preserving original order; pass-through for non-arrays.
	 * Dual-mode: no argument returns callable; with value, applies immediately
	 *
	 * @example $clean = (Sanitize::array()->unique_list())($value);
	 * @example $clean = Sanitize::array()->unique_list($value);
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	public function unique_list(mixed $value = null): mixed {
		$fn = static function (mixed $v): mixed {
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
		return \func_num_args() === 0 ? $fn : $fn($value);
	}

	/**
	 * Sort associative arrays by key; pass-through for non-arrays and lists.
	 * Dual-mode: no argument returns callable; with value, applies immediately
	 *
	 * @example $clean = (Sanitize::array()->ksort_assoc())($value);
	 * @example $clean = Sanitize::array()->ksort_assoc($value);
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	public function ksort_assoc(mixed $value = null): mixed {
		$fn = static function (mixed $v): mixed {
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
		return \func_num_args() === 0 ? $fn : $fn($value);
	}
}
