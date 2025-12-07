<?php
declare(strict_types=1);

namespace Ran\PluginLib\Util\Sanitize;

/**
 * String sanitizers (pure, idempotent where possible).
 *
 * @example $clean = (Sanitize::string()->trim())($value);
 * @example $clean = Sanitize::string()->trim($value);
 *
 * Dual-mode methods:
 * - When called with no arguments, methods return a callable(mixed): mixed
 * - When called with a value, methods apply immediately and return mixed
 */
final class SanitizeStringGroup {
	/**
	 * Trim leading and trailing whitespace for strings; pass-through otherwise.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example $clean = (Sanitize::string()->trim())($value);
	 * @example $clean = Sanitize::string()->trim($value);
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	public function trim(mixed $value = null): mixed {
		$fn = static function (mixed $v): mixed {
			return \is_string($v) ? \trim($v) : $v;
		};
		return \func_num_args() === 0 ? $fn : $fn($value);
	}

	/**
	 * Lowercase strings; pass-through otherwise.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example $clean = (Sanitize::string()->to_lower())($value);
	 * @example $clean = Sanitize::string()->to_lower($value);
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	public function to_lower(mixed $value = null): mixed {
		$fn = static function (mixed $v): mixed {
			return \is_string($v) ? \mb_strtolower($v) : $v;
		};
		return \func_num_args() === 0 ? $fn : $fn($value);
	}

	/**
	 * Uppercase strings; pass-through otherwise.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example $clean = (Sanitize::string()->to_upper())($value);
	 * @example $clean = Sanitize::string()->to_upper($value);
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	public function to_upper(mixed $value = null): mixed {
		$fn = static function (mixed $v): mixed {
			return \is_string($v) ? \mb_strtoupper($v) : $v;
		};
		return \func_num_args() === 0 ? $fn : $fn($value);
	}

	/**
	 * strip_tags on strings; pass-through otherwise.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example  $clean = (Sanitize::string()->strip_tags(['<a>', '<b>']))($value);
	 * @example  $clean = Sanitize::string()->strip_tags($value, ['<a>', '<b>']);
	 *
	 * @param array $allowed_tags Array of allowed tags
	 * @param mixed $value
	 * @return mixed
	 */
	public function strip_tags(array $allowed_tags = array(), mixed $value = null): mixed {
		$fn = static function (mixed $v) use ($allowed_tags): mixed {
			return \is_string($v) ? \strip_tags($v, $allowed_tags) : $v;
		};
		return \func_num_args() < 2 ? $fn : $fn($value);
	}
}
