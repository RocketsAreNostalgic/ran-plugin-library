<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options;

use Ran\PluginLib\Util\Sanitize;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;

final class SanitizeTest extends PluginLibTestCase {
	/**
	 * @covers \Ran\PluginLib\Util\Sanitize::string
	 * @covers \Ran\PluginLib\Util\Sanitize\SanitizeStringGroup::trim
	 * @covers \Ran\PluginLib\Util\Sanitize\SanitizeStringGroup::toLower
	 * @covers \Ran\PluginLib\Util\Sanitize\SanitizeStringGroup::toUpper
	 * @covers \Ran\PluginLib\Util\Sanitize\SanitizeStringGroup::stripTags
	 */
	public function test_string_sanitizers(): void {
		$trim = Sanitize::string()->trim();
		$this->assertSame('abc', $trim('  abc  '));
		$this->assertSame(123, $trim(123));

		$lower = Sanitize::string()->toLower();
		$this->assertSame('abc', $lower('AbC'));
		$this->assertSame(123, $lower(123));

		$upper = Sanitize::string()->toUpper();
		$this->assertSame('ABC', $upper('AbC'));
		$this->assertSame(123, $upper(123));

		$strip = Sanitize::string()->stripTags();
		$this->assertSame('hello', $strip('<b>hello</b>'));
		$this->assertSame(array('x'), $strip(array('x')));
	}

	/**
	 * @covers \Ran\PluginLib\Util\Sanitize::number
	 * @covers \Ran\PluginLib\Util\Sanitize\SanitizeNumberGroup::to_int
	 * @covers \Ran\PluginLib\Util\Sanitize\SanitizeNumberGroup::to_float
	 */
	public function test_number_sanitizers(): void {
		$to_int = Sanitize::number()->to_int();
		$this->assertSame(42, $to_int('42'));
		$this->assertSame(42, $to_int(42.8));
		$this->assertSame('x', $to_int('x'));

		$to_float = Sanitize::number()->to_float();
		$this->assertSame(42.0, $to_float('42'));
		$this->assertSame(42.5, $to_float(42.5));
		$this->assertSame('x', $to_float('x'));
	}

	/**
	 * @covers \Ran\PluginLib\Util\Sanitize::bool
	 * @covers \Ran\PluginLib\Util\Sanitize\SanitizeBooleanGroup::to_bool
	 * @covers \Ran\PluginLib\Util\Sanitize\SanitizeBooleanGroup::to_bool_strict
	 */
	public function test_bool_sanitizers(): void {
		// to_bool: form-friendly, handles more values including 'yes', 'no', 'on', 'off'
		$toBool = Sanitize::bool()->to_bool();
		$this->assertTrue($toBool(true));
		$this->assertFalse($toBool(false));
		$this->assertTrue($toBool(1));
		$this->assertFalse($toBool(0));
		$this->assertTrue($toBool('true'));
		$this->assertFalse($toBool('false'));
		$this->assertTrue($toBool('yes')); // form-friendly: converts 'yes' to true
		$this->assertFalse($toBool('no')); // form-friendly: converts 'no' to false
		$this->assertTrue($toBool('on')); // form-friendly: converts 'on' to true
		$this->assertFalse($toBool('off')); // form-friendly: converts 'off' to false
		$this->assertFalse($toBool('')); // form-friendly: converts empty string to false

		// to_bool_strict: only handles strict boolean values
		$toBoolStrict = Sanitize::bool()->to_bool_strict();
		$this->assertTrue($toBoolStrict(true));
		$this->assertFalse($toBoolStrict(false));
		$this->assertTrue($toBoolStrict(1));
		$this->assertFalse($toBoolStrict(0));
		$this->assertTrue($toBoolStrict('true'));
		$this->assertFalse($toBoolStrict('false'));
		$this->assertSame('yes', $toBoolStrict('yes')); // pass through non-strict values
	}

	/**
	 * @covers \Ran\PluginLib\Util\Sanitize::array
	 * @covers \Ran\PluginLib\Util\Sanitize\SanitizeArrayGroup::ensure_list
	 * @covers \Ran\PluginLib\Util\Sanitize\SanitizeArrayGroup::unique_list
	 * @covers \Ran\PluginLib\Util\Sanitize\SanitizeArrayGroup::ksort_assoc
	 */
	public function test_array_sanitizers(): void {
		$ensure_list = Sanitize::array()->ensure_list();
		$this->assertSame(array('a', 'b'), $ensure_list(array('a', 'b')));
		$this->assertSame(array('a', 'b'), $ensure_list(array('x' => 'a', 'y' => 'b')));
		$this->assertSame('x', $ensure_list('x'));

		$unique_list = Sanitize::array()->unique_list();
		$this->assertSame(array('a', 'b', 'c'), $unique_list(array('a', 'b', 'a', 'c', 'b')));
		$this->assertSame('x', $unique_list('x'));

		$ksort_assoc = Sanitize::array()->ksort_assoc();
		$this->assertSame(array('a' => 1, 'b' => 2), $ksort_assoc(array('b' => 2, 'a' => 1)));
		$this->assertSame(array('a', 'b'), $ksort_assoc(array('a', 'b')));
	}

	/**
	 * @covers \Ran\PluginLib\Util\Sanitize::json
	 * @covers \Ran\PluginLib\Util\Sanitize\SanitizeJsonGroup::decode_to_value
	 * @covers \Ran\PluginLib\Util\Sanitize\SanitizeJsonGroup::decode_object
	 * @covers \Ran\PluginLib\Util\Sanitize\SanitizeJsonGroup::decode_array
	 */
	public function test_json_sanitizers(): void {
		$toVal = Sanitize::json()->decode_to_value();
		$this->assertSame(array('a' => 1), $toVal('{"a":1}'));
		$this->assertSame('x', $toVal('x'));

		$toObj = Sanitize::json()->decode_object();
		$this->assertSame(array('a' => 1), $toObj('{"a":1}'));
		$this->assertSame('[1,2]', $toObj('[1,2]'));

		$toArr = Sanitize::json()->decode_array();
		$this->assertSame(array(1, 2), $toArr('[1,2]'));
		$this->assertSame('{"a":1}', $toArr('{"a":1}'));
	}

	/**
	 * @covers \Ran\PluginLib\Util\Sanitize::combine
	 * @covers \Ran\PluginLib\Util\Sanitize\SanitizeComposeGroup::pipe
	 */
	public function test_combine_pipe(): void {
		$pipe = Sanitize::combine()->pipe(
			Sanitize::string()->trim(),
			Sanitize::string()->toLower()
		);
		$this->assertSame('abc', $pipe('  ABC  '));
		$this->assertSame(5, $pipe(5));
	}

	/**
	 * @covers \Ran\PluginLib\Util\Sanitize::combine
	 * @covers \Ran\PluginLib\Util\Sanitize\SanitizeComposeGroup::nullable
	 * @covers \Ran\PluginLib\Util\Sanitize\SanitizeComposeGroup::optional
	 */
	public function test_compose_nullable_and_optional(): void {
		$nullableTrim = Sanitize::combine()->nullable(Sanitize::string()->trim());
		$this->assertNull($nullableTrim(null));
		$this->assertSame('x', $nullableTrim(' x '));

		$optionalInt = Sanitize::combine()->optional(Sanitize::number()->to_int());
		$this->assertNull($optionalInt(null));
		$this->assertSame(42, $optionalInt('42'));
		$this->assertSame('nope', $optionalInt('nope'));
	}

	/**
	 * @covers \Ran\PluginLib\Util\Sanitize::combine
	 * @covers \Ran\PluginLib\Util\Sanitize\SanitizeComposeGroup::when
	 * @covers \Ran\PluginLib\Util\Sanitize\SanitizeComposeGroup::unless
	 */
	public function test_compose_when_and_unless(): void {
		$whenLower = Sanitize::combine()->when(static fn($v) => is_string($v), Sanitize::string()->toLower());
		$this->assertSame('abc', $whenLower('ABC'));
		$this->assertSame(10, $whenLower(10));

		$unlessIntCast = Sanitize::combine()->unless(static fn($v) => is_int($v), Sanitize::number()->to_int());
		$this->assertSame(5, $unlessIntCast(5));
		$this->assertSame(5, $unlessIntCast('5'));
		$this->assertSame('x', $unlessIntCast('x'));
	}

	/**
	 * @covers \Ran\PluginLib\Util\Sanitize::canonical
	 * @covers \Ran\PluginLib\Util\Sanitize\SanitizeCanonicalGroup::order_insensitive_deep
	 * @covers \Ran\PluginLib\Util\Sanitize\SanitizeCanonicalGroup::order_insensitive_shallow
	 */
	public function test_canonical_wrappers(): void {
		$deep = Sanitize::canonical()->order_insensitive_deep();
		$this->assertSame(array('a' => 1, 'b' => 2), $deep(array('b' => 2, 'a' => 1)));

		$shallow = Sanitize::canonical()->order_insensitive_shallow();
		$this->assertSame(array('a' => 1, 'b' => 2), $shallow(array('b' => 2, 'a' => 1)));
	}

	/**
	 * @covers \Ran\PluginLib\Util\Sanitize\SanitizeCanonicalGroup::order_insensitive_deep
	 */
	public function test_order_insensitive_deep(): void {
		// Scalars pass-through
		$this->assertSame(123, Sanitize::canonical()->order_insensitive_deep(123));
		$this->assertSame('abc', Sanitize::canonical()->order_insensitive_deep('abc'));
		$this->assertSame(null, Sanitize::canonical()->order_insensitive_deep(null));

		// Object conversion preference: JsonSerializable over public props
		$jsonObj = new class implements \JsonSerializable {
			public function jsonSerialize(): mixed {
				return array('b' => 1, 'a' => 2);
			}
		};
		$this->assertSame(array('a' => 2, 'b' => 1), Sanitize::canonical()->order_insensitive_deep($jsonObj));

		$plainObj = new class {
			public int $z = 1;
			public int $a = 2;
		};
		$this->assertSame(array('a' => 2, 'z' => 1), Sanitize::canonical()->order_insensitive_deep($plainObj));

		// Assoc maps: recurse and sort by keys
		$a  = array('b' => array('y' => 2, 'x' => 1), 'a' => 9);
		$b  = array('a' => 9, 'b' => array('x' => 1, 'y' => 2));
		$na = Sanitize::canonical()->order_insensitive_deep($a);
		$nb = Sanitize::canonical()->order_insensitive_deep($b);
		$this->assertSame($na, $nb);
		$this->assertSame(array('a' => 9, 'b' => array('x' => 1, 'y' => 2)), $na);

		// Lists: normalize elements then stable sort by JSON
		$l1 = array(array('k' => 2), array('k' => 1));
		$l2 = array(array('k' => 1), array('k' => 2));
		$n1 = Sanitize::canonical()->order_insensitive_deep($l1);
		$n2 = Sanitize::canonical()->order_insensitive_deep($l2);
		$this->assertSame($n1, $n2);
		$this->assertSame(array(array('k' => 1), array('k' => 2)), $n1);

		// Nested mixed
		$in = array(
		    array('b' => array(2, 1)),
		    array('a' => array( array('y' => 2, 'x' => 1), array('x' => 1, 'y' => 2) )),
		);
		$out = Sanitize::canonical()->order_insensitive_deep($in);
		$this->assertSame(array(
		    array('a' => array( array('x' => 1, 'y' => 2), array('x' => 1, 'y' => 2) )),
		    array('b' => array(1, 2)),
		), $out);
	}

	/**
	 * @covers \Ran\PluginLib\Util\Sanitize\SanitizeCanonicalGroup::order_insensitive_shallow
	 */
	public function test_order_insensitive_shallow(): void {
		// Scalars pass-through
		$this->assertSame(false, Sanitize::canonical()->order_insensitive_shallow(false));
		$this->assertSame('x', Sanitize::canonical()->order_insensitive_shallow('x'));

		// Object conversion preference: JsonSerializable over public props; sort top-level only
		$obj = new class implements \JsonSerializable {
			public function jsonSerialize(): mixed {
				return array('b' => 1, 'a' => 2);
			}
		};
		$this->assertSame(array('a' => 2, 'b' => 1), Sanitize::canonical()->order_insensitive_shallow($obj));

		$plain = new class {
			public $k = 3;
			public $a = 1;
		};
		$this->assertSame(array('a' => 1, 'k' => 3), Sanitize::canonical()->order_insensitive_shallow($plain));

		// Assoc: sort top-level keys only; do not recurse
		$in  = array('b' => array('z' => 2, 'a' => 1), 'a' => array('y' => 2, 'x' => 1));
		$out = Sanitize::canonical()->order_insensitive_shallow($in);
		$this->assertSame(array('a' => array('y' => 2, 'x' => 1), 'b' => array('z' => 2, 'a' => 1)), $out);

		// List: stable sort by JSON at top level only
		$list = array(array('k' => 2), array('k' => 1));
		$this->assertSame(array(array('k' => 1), array('k' => 2)), Sanitize::canonical()->order_insensitive_shallow($list));

		// Elements are not deep-normalized in shallow mode
		$in2  = array(array('b' => 2, 'a' => 1), array('a' => 1, 'b' => 2));
		$out2 = Sanitize::canonical()->order_insensitive_shallow($in2);
		$this->assertSame(array(array('a' => 1, 'b' => 2), array('b' => 2, 'a' => 1)), $out2);
	}
}
