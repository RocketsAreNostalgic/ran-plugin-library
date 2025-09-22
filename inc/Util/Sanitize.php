<?php
declare(strict_types=1);

namespace Ran\PluginLib\Util;

use Ran\PluginLib\Util\Sanitize\SanitizeJsonGroup;
use Ran\PluginLib\Util\Sanitize\SanitizeArrayGroup;
use Ran\PluginLib\Util\Sanitize\SanitizeNumberGroup;
use Ran\PluginLib\Util\Sanitize\SanitizeStringGroup;
use Ran\PluginLib\Util\Sanitize\SanitizeComposeGroup;
use Ran\PluginLib\Util\Sanitize\SanitizeCanonicalGroup;

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
 *    Sanitize::string()->toLower(),
 *    Sanitize::string()->stripTags()
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
	 * @method callable(mixed):mixed toLower()
	 * @method callable(mixed):mixed toUpper()
	 * @method callable(mixed):mixed stripTags()
	 */
	public static function string(): SanitizeStringGroup {
		return new SanitizeStringGroup();
	}

	/**
	 * Access number/boolean sanitizers.
	 *
	 * @example  $clean = (Sanitize::number()->toInt())($value);
	 *
	 * @return \Ran\PluginLib\Util\Sanitize\SanitizeNumberGroup
	 * @method callable(mixed):mixed toInt()
	 * @method callable(mixed):mixed toFloat()
	 * @method callable(mixed):mixed toBoolStrict()
	 */
	public static function number(): SanitizeNumberGroup {
		return new SanitizeNumberGroup();
	}

	/**
	 * Access array/list/map sanitizers.
	 *
	 * @example  $clean = (Sanitize::array()->ensureList())($value);
	 *
	 * @return \Ran\PluginLib\Util\Sanitize\SanitizeArrayGroup
	 * @method callable(mixed):mixed ensureList()
	 * @method callable(mixed):mixed uniqueList()
	 * @method callable(mixed):mixed ksortAssoc()
	 */
	public static function array(): SanitizeArrayGroup {
		return new SanitizeArrayGroup();
	}

	/**
	 * Access JSON sanitizers.
	 *
	 * @example  $clean = (Sanitize::json()->decodeToValue())($value);
	 *
	 * @return \Ran\PluginLib\Util\Sanitize\SanitizeJsonGroup
	 * @method callable(mixed):mixed decodeToValue()
	 * @method callable(mixed):mixed decodeObject()
	 * @method callable(mixed):mixed decodeArray()
	 */
	public static function json(): SanitizeJsonGroup {
		return new SanitizeJsonGroup();
	}

	/**
	 * Access sanitizer combinators (composition helpers).
	 *
	 * @example  $clean = (Sanitize::combine()->pipe(
	 *   Sanitize::string()->trim(),
	 *   Sanitize::string()->toLower(),
	 *   Sanitize::string()->stripTags()
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
	 * @example  $clean = (Sanitize::canonical()->orderInsensitiveDeep())($value);
	 *
	 * @return \Ran\PluginLib\Util\Sanitize\SanitizeCanonicalGroup
	 * @method callable(mixed):mixed orderInsensitiveDeep()
	 * @method callable(mixed):mixed orderInsensitiveShallow()
	 */
	public static function canonical(): SanitizeCanonicalGroup {
		return new SanitizeCanonicalGroup();
	}
}
