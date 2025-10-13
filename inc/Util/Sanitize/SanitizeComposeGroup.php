<?php
declare(strict_types=1);

namespace Ran\PluginLib\Util\Sanitize;

/**
 * Sanitizer composition helpers (nested namespace).
 *
 * @method callable(mixed):mixed pipe(callable ...$sanitizers)
 * @method callable(mixed):mixed nullable(callable $sanitizer)
 * @method callable(mixed):mixed optional(callable $sanitizer)
 * @method callable(mixed):mixed when(callable $predicate, callable $sanitizer)
 * @method callable(mixed):mixed unless(callable $predicate, callable $sanitizer)
 */
final class SanitizeComposeGroup {
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

	/**
	 * Apply a sanitizer only when value is not null; pass-through null.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example $clean = (Sanitize::compose()->nullable(Sanitize::basic()->is_string()))($value);
	 * @example $clean = Sanitize::compose()->nullable(Sanitize::basic()->is_string(), $value);
	 *
	 * @param callable(mixed):mixed $sanitizer
	 * @param mixed $value
	 * Returns a callable when called without $value; returns the sanitized result when $value is provided.
     * @return mixed
     */
	public function nullable(callable $sanitizer, mixed $value = null): mixed {
		$fn = static function (mixed $v) use ($sanitizer): mixed {
			if ($v === null) {
				return null;
			}
			return $sanitizer($v);
		};
		return \func_num_args() === 1 ? $fn : $fn($value);
	}

	/**
	 * Alias of nullable(...) for ergonomics; null is treated as absent and passed through.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example $clean = (Sanitize::compose()->optional(Sanitize::basic()->is_string()))($value);
	 * @example $clean = Sanitize::compose()->optional(Sanitize::basic()->is_string(), $value);
	 *
	 * @param callable(mixed):mixed $sanitizer
	 * @param mixed $value
	     * Returns a callable when called without $value; returns the sanitized result when $value is provided.
     * @return mixed
     */
	public function optional(callable $sanitizer, mixed $value = null): mixed {
		if (\func_num_args() === 1) {
			return $this->nullable($sanitizer);
		}
		/** @var callable $callable */
		$callable = $this->nullable($sanitizer);
		return $callable($value);
	}

	/**
	 * Conditionally apply a sanitizer when predicate returns true; otherwise pass-through.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example $clean = (Sanitize::compose()->when(Sanitize::basic()->is_string(), Sanitize::basic()->is_string()))($value);
	 * @example $clean = Sanitize::compose()->when(Sanitize::basic()->is_string(), Sanitize::basic()->is_string(), $value);
	 *
	 * @param callable(mixed):bool   $predicate Pure predicate receiving the current value
	 * @param callable(mixed):mixed  $sanitizer Sanitizer to apply when predicate is true
	 * @param mixed $value
	 * Returns a callable when called without $value; returns the sanitized result when $value is provided.
     * @return mixed
     */
	public function when(callable $predicate, callable $sanitizer, mixed $value = null): mixed {
		$fn = static function (mixed $v) use ($predicate, $sanitizer): mixed {
			return $predicate($v) ? $sanitizer($v) : $v;
		};
		return \func_num_args() === 2 ? $fn : $fn($value);
	}

	/**
	 * Conditionally apply a sanitizer when predicate returns false; otherwise pass-through.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example $clean = (Sanitize::compose()->unless(Sanitize::basic()->is_string(), Sanitize::basic()->is_string()))($value);
	 * @example $clean = Sanitize::compose()->unless(Sanitize::basic()->is_string(), Sanitize::basic()->is_string(), $value);
	 *
	 * @param callable(mixed):bool   $predicate Pure predicate receiving the current value
	 * @param callable(mixed):mixed  $sanitizer Sanitizer to apply when predicate is false
	 * @param mixed $value
	 * Returns a callable when called without $value; returns the sanitized result when $value is provided.
     * @return mixed
     */
	public function unless(callable $predicate, callable $sanitizer, mixed $value = null): mixed {
		$fn = static function (mixed $v) use ($predicate, $sanitizer): mixed {
			return $predicate($v) ? $v : $sanitizer($v);
		};
		return \func_num_args() === 2 ? $fn : $fn($value);
	}
}
