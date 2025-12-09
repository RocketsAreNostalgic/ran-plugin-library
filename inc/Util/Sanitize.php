<?php
declare(strict_types=1);

namespace Ran\PluginLib\Util;

use Ran\PluginLib\Util\Sanitize\SanitizeStringGroup;
use Ran\PluginLib\Util\Sanitize\SanitizeNumberGroup;
use Ran\PluginLib\Util\Sanitize\SanitizeJsonGroup;
use Ran\PluginLib\Util\Sanitize\SanitizeComposeGroup;
use Ran\PluginLib\Util\Sanitize\SanitizeCanonicalGroup;
use  Ran\PluginLib\Util\Sanitize\SanitizeBooleanGroup;
use Ran\PluginLib\Util\Sanitize\SanitizeArrayGroup;

/**
 * Sanitization utilities (nested namespace facade).
 *
 * This facade lives under the new nested namespace per Option B. Group classes
 * are being migrated gradually; combine() already returns the new namespaced
 * group. Others temporarily return the legacy groups until migration completes.
 *
 * @example
 *  use Ran\PluginLib\Util\Sanitize\Sanitize;
 *  $clean = (Sanitize::combine()->pipe(
 *    Sanitize::string()->trim(),
 *    Sanitize::string()->to_lower(),
 *    Sanitize::string()->strip_tags()
 *  ))($value);
 *
 * @method static SanitizeStringGroup string()
 * @method static SanitizeNumberGroup number()
 * @method static SanitizeArrayGroup array()
 * @method static SanitizeJsonGroup json()
 * @method static SanitizeComposeGroup combine()
 * @method static SanitizeCanonicalGroup canonical()
 */
final class Sanitize {
	/**
	 * Access string sanitizers (lightweight, pure, idempotent).
	 *
	 * @example  $clean = (Sanitize::string()->trim())($value);
	 *
	 * @return \Ran\PluginLib\Util\Sanitize\SanitizeStringGroup
	 * @method callable(mixed):mixed trim()
	 * @method callable(mixed):mixed to_lower()
	 * @method callable(mixed):mixed to_upper()
	 * @method callable(mixed):mixed strip_tags()
	 */
	public static function string(): SanitizeStringGroup {
		return new SanitizeStringGroup();
	}

	/**
	 * Access number/boolean sanitizers.
	 *
	 * @example  $clean = (Sanitize::number()->to_int())($value);
	 *
	 * @return \Ran\PluginLib\Util\Sanitize\SanitizeNumberGroup
	 * @method callable(mixed):mixed to_int()
	 * @method callable(mixed):mixed to_float()
	 */
	public static function number(): SanitizeNumberGroup {
		return new SanitizeNumberGroup();
	}

	/**
	 * Access boolean sanitizers.
	 *
	 * @example  $clean = (Sanitize::bool()->to_bool())($value);
	 *
	 * @return \Ran\PluginLib\Util\Sanitize\SanitizeBooleanGroup
	 * @method callable(mixed):mixed to_bool()
	 * @method callable(mixed):mixed to_bool_strict()
	*/
	public static function bool(): SanitizeBooleanGroup {
		return new SanitizeBooleanGroup();
	}

	/**
	 * Access array/list/map sanitizers.
	 *
	 * @example  $clean = (Sanitize::array()->ensure_list())($value);
	 *
	 * @return \Ran\PluginLib\Util\Sanitize\SanitizeArrayGroup
	 * @method callable(mixed):mixed ensure_list()
	 * @method callable(mixed):mixed unique_list()
	 * @method callable(mixed):mixed ksort_assoc()
	 */
	public static function array(): SanitizeArrayGroup {
		return new SanitizeArrayGroup();
	}

	/**
	 * Access JSON sanitizers.
	 *
	 * @example  $clean = (Sanitize::json()->decode_to_value())($value);
	 *
	 * @return \Ran\PluginLib\Util\Sanitize\SanitizeJsonGroup
	 * @method callable(mixed):mixed decode_to_value()
	 * @method callable(mixed):mixed decode_object()
	 * @method callable(mixed):mixed decode_array()
	 */
	public static function json(): SanitizeJsonGroup {
		return new SanitizeJsonGroup();
	}

	/**
	 * Access sanitizer combinators (composition helpers).
	 *
	 * @example  $clean = (Sanitize::combine()->pipe(
	 *   Sanitize::string()->trim(),
	 *   Sanitize::string()->to_lower(),
	 *   Sanitize::string()->strip_tags()
	 * ))($value);
	 *
	 * @return \Ran\PluginLib\Util\Sanitize\SanitizeComposeGroup
	 * @method callable(mixed):mixed pipe(callable ...$sanitizers)
	 * @method callable(mixed):mixed nullable(callable $sanitizer)
	 * @method callable(mixed):mixed optional(callable $sanitizer)
	 * @method callable(mixed):mixed when(callable $predicate, callable $sanitizer)
	 * @method callable(mixed):mixed unless(callable $predicate, callable $sanitizer)
	 */
	public static function combine(): SanitizeComposeGroup {
		return new SanitizeComposeGroup();
	}

	/**
	 * Access canonicalizers (order-insensitive helpers) as callables.
  	 *
	 * @example  $clean = (Sanitize::canonical()->order_insensitive_deep())($value);
	 *
	 * @return \Ran\PluginLib\Util\Sanitize\SanitizeCanonicalGroup
	 * @method callable(mixed):mixed order_insensitive_deep()
	 * @method callable(mixed):mixed order_insensitive_shallow()
	 */
	public static function canonical(): SanitizeCanonicalGroup {
		return new SanitizeCanonicalGroup();
	}
}
