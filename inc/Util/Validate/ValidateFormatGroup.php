<?php
declare(strict_types=1);

namespace Ran\PluginLib\Util\Validate;

use Ran\PluginLib\Util\WPWrappersTrait;

/**
 * Common format validators (email, phone, URL, etc.).
 *
 * @example $isValid = (Validate::format()->email())($value);
 * @example $isValid = Validate::format()->email($value);
 *
 * Dual-mode methods:
 * - With no value, methods return callable(mixed): bool
 * - With a value, methods apply immediately and return bool
 */
final class ValidateFormatGroup {
	use  WPWrappersTrait;

	/**
	 * Pragmatic RFC 5322-lite email validator.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example Validate::format()->email()($value)
	 * @example Validate::format()->email($value)
	 *
	 * @param mixed $value
	 * @return callable(mixed):bool|bool
	 */
	public function email(mixed $value = null): callable|bool {
		$fn = static function (mixed $v): bool {
			return \is_string($v) && (false !== filter_var($v, FILTER_VALIDATE_EMAIL));
		};
		return \func_num_args() === 0 ? $fn : $fn($value);
	}

	/**
	 * Validate that a string is valid JSON.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @param mixed $value
	 * @return callable(mixed):bool|bool
	 */
	public function json_string(mixed $value = null): callable|bool {
		$fn = static function (mixed $v): bool {
			if (!\is_string($v)) {
				return false;
			}
			json_decode($v);
			return json_last_error() === JSON_ERROR_NONE;
		};
		return \func_num_args() === 0 ? $fn : $fn($value);
	}

	/**
	 * Pragmatic E.164 phone number validator.
	 * Pattern: ^\+[1-9]\d{1,14}$
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example Validate::format()->phone()($value)
	 * @example Validate::format()->phone($value)
	 *
	 * @param mixed $value
	 * @return callable(mixed):bool|bool
	 */
	public function phone(mixed $value = null): callable|bool {
		$fn = static function (mixed $v): bool {
			return \is_string($v) && (bool) \preg_match('/^\\+[1-9]\\d{1,14}$/', $v);
		};
		return \func_num_args() === 0 ? $fn : $fn($value);
	}

	/**
	 * Validate a general URL using FILTER_VALIDATE_URL (pragmatic).
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example Validate::format()->url()($value)
	 * @example Validate::format()->url($value)
	 *
	 * @param mixed $value
	 * @return callable(mixed):bool|bool
	 */
	public function url(mixed $value = null): callable|bool {
		$fn = static function (mixed $v): bool {
			return \is_string($v) && (false !== filter_var($v, FILTER_VALIDATE_URL));
		};
		return \func_num_args() === 0 ? $fn : $fn($value);
	}

	/**
	 * Validate a domain name (requires at least one dot, TLD 2-63, RFC-like labels).
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example Validate::format()->domain()($value)
	 * @example Validate::format()->domain($value)
	 *
	 * @param mixed $value
	 * @return callable(mixed):bool|bool
	 */
	public function domain(mixed $value = null): callable|bool {
		$fn = static function (mixed $v): bool {
			if (!\is_string($v)) {
				return false;
			}
			// Labels: [a-z0-9]([-a-z0-9]{0,61}[a-z0-9])? ; at least two labels separated by dots; TLD >=2
			return (bool) \preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i', $v);
		};
		return \func_num_args() === 0 ? $fn : $fn($value);
	}

	/**
	 * Validate a hostname (allows single-label like "localhost" or full domains).
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example Validate::format()->hostname()($value)
	 * @example Validate::format()->hostname($value)
	 *
	 * @param mixed $value
	 * @return callable(mixed):bool|bool
	 */
	public function hostname(mixed $value = null): callable|bool {
		$fn = static function (mixed $v): bool {
			if (!\is_string($v)) {
				return false;
			}
			if ($v === 'localhost') {
				return true;
			}
			// Allow single label 1-63 chars or domain()
			if ((bool) \preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])$/i', $v)) {
				return true;
			}
			return (bool) \preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i', $v);
		};
		return \func_num_args() === 0 ? $fn : $fn($value);
	}

	/**
	 * Validate an origin string: scheme://host[:port] with no path/query/fragment.
	 * Dual-mode: no argument returns a callable; with value, applies immediately.
	 *
	 * @example Validate::format()->origin()($value)
	 * @example Validate::format()->origin($value)
	 *
	 * @param mixed $value
	 * @return callable(mixed):bool|bool
	 */
	public function origin(mixed $value = null): callable|bool {
		$fn = static function (mixed $v): bool {
			if (!\is_string($v)) {
				return false;
			}
			$parts = parse_url($v);
			if ($parts === false) {
				return false;
			}
			if (!isset($parts['scheme'], $parts['host'])) {
				return false;
			}
			// No path, query, or fragment allowed
			if (isset($parts['path']) || isset($parts['query']) || isset($parts['fragment'])) {
				return false;
			}
			// Hostname/domain check
			$host   = $parts['host'];
			$hostOk = $host === 'localhost' || (bool) \preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i', $host) || (bool) \preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])$/i', $host);
			if (!$hostOk) {
				return false;
			}
			// Port, if present, must be numeric 1-65535
			if (isset($parts['port']) && (!\is_int($parts['port']) || $parts['port'] < 1 || $parts['port'] > 65535)) {
				return false;
			}
			return true;
		};
		return \func_num_args() === 0 ? $fn : $fn($value);
	}

	/**
	 * Validate file extension against WordPress allowed types with optional restrictions.
	 * Dual-mode: no argument (null) returns a callable; with value, applies immediately.
	 *
	 * Accepts simple strings, URLs and file paths or null.
	 *
	 * If no restrictions provided, it uses WordPress default allowed file extensions.
	 * If restrictions provided, validates that all restrictions are in WordPress allowed list and retuns a subset of the two.
	 * Extensions in allowedExtensions that are not also in WordPress allowed list, will return false.
	 *
	 * @example Validate::format()->file_extension($value) // Uses WordPress defaults
	 * @example Validate::format()->file_extension($value, ['png', 'jpg']) // Restrict to images
	 * @example (Validate::format()->file_extension())($value) // Callable with WordPress defaults
	 * @example (Validate::format()->file_extension(null, ['png', 'jpg']))($value) // Callable with subset
	 *
	 * @uses wp_get_mime_types()
	 *
	 * @param mixed $value Optional value to validate immediately
	 * @param ?array $allowedExtensions Optional array of allowed extensions to restrict WordPress defaults
	 *
	 * @return callable(mixed):bool|bool
	 */
	public function file_extension(mixed $value = null, ?array $allowedExtensions = null): callable|bool {
		$fn = static function (mixed $v) use ($allowedExtensions): bool {
			if (!\is_string($v) || empty($v)) {
				return false;
			}

			// Extract file extension from path or URL
			$filePath = $v;
			if (str_contains($v, '://') || str_starts_with($v, '//')) {
				$parsedUrl = parse_url($v);
				$filePath  = $parsedUrl['path'] ?? '';
			}

			$extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
			if (empty($extension)) {
				return false;
			}

			// Get WordPress allowed extensions using the trait wrapper
			(array) $mimeTypes = WPWrappersTrait::_do_get_allowed_mime_types();
			$wordpressAllowed  = array();

			foreach ($mimeTypes as $exts => $mime) {
				$extList = explode('|', $exts);
				foreach ($extList as $ext) {
					$wordpressAllowed[] = strtolower(trim($ext));
				}
			}
			$wordpressAllowed = array_unique($wordpressAllowed);

			// If no restrictions provided, use WordPress defaults
			if ($allowedExtensions === null) {
				return in_array($extension, $wordpressAllowed, true);
			}

			// Normalize custom extensions to lowercase
			$customExtensions = array_map('strtolower', array_map('trim', $allowedExtensions));

			// Validate that all custom extensions are in WordPress allowed list
			$invalidExtensions = array_diff($customExtensions, $wordpressAllowed);
			if (!empty($invalidExtensions)) {
				return false; // Custom extensions not allowed by WordPress
			}

			// Check if file extension is in the restricted list
			return in_array($extension, $customExtensions, true);
		};

		return \func_num_args() === 0 ? $fn : $fn($value);
	}
}
