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
	 * @covers \Ran\PluginLib\Util\Sanitize\SanitizeNumberGroup::toInt
	 * @covers \Ran\PluginLib\Util\Sanitize\SanitizeNumberGroup::toFloat
	 * @covers \Ran\PluginLib\Util\Sanitize\SanitizeNumberGroup::toBoolStrict
	 */
	public function test_number_sanitizers(): void {
		$toInt = Sanitize::number()->toInt();
		$this->assertSame(42, $toInt('42'));
		$this->assertSame(42, $toInt(42.8));
		$this->assertSame('x', $toInt('x'));

		$toFloat = Sanitize::number()->toFloat();
		$this->assertSame(42.0, $toFloat('42'));
		$this->assertSame(42.5, $toFloat(42.5));
		$this->assertSame('x', $toFloat('x'));

		$toBool = Sanitize::number()->toBoolStrict();
		$this->assertTrue($toBool(true));
		$this->assertFalse($toBool(false));
		$this->assertTrue($toBool(1));
		$this->assertFalse($toBool(0));
		$this->assertTrue($toBool('true'));
		$this->assertFalse($toBool('false'));
		$this->assertSame('yes', $toBool('yes'));
	}

	/**
	 * @covers \Ran\PluginLib\Util\Sanitize::array
	 * @covers \Ran\PluginLib\Util\Sanitize\SanitizeArrayGroup::ensureList
	 * @covers \Ran\PluginLib\Util\Sanitize\SanitizeArrayGroup::uniqueList
	 * @covers \Ran\PluginLib\Util\Sanitize\SanitizeArrayGroup::ksortAssoc
	 */
	public function test_array_sanitizers(): void {
		$ensureList = Sanitize::array()->ensureList();
		$this->assertSame(array('a', 'b'), $ensureList(array('a', 'b')));
		$this->assertSame(array('a', 'b'), $ensureList(array('x' => 'a', 'y' => 'b')));
		$this->assertSame('x', $ensureList('x'));

		$uniqueList = Sanitize::array()->uniqueList();
		$this->assertSame(array('a', 'b', 'c'), $uniqueList(array('a', 'b', 'a', 'c', 'b')));
		$this->assertSame('x', $uniqueList('x'));

		$ksortAssoc = Sanitize::array()->ksortAssoc();
		$this->assertSame(array('a' => 1, 'b' => 2), $ksortAssoc(array('b' => 2, 'a' => 1)));
		$this->assertSame(array('a', 'b'), $ksortAssoc(array('a', 'b')));
	}

	/**
	 * @covers \Ran\PluginLib\Util\Sanitize::json
	 * @covers \Ran\PluginLib\Util\Sanitize\SanitizeJsonGroup::decodeToValue
	 * @covers \Ran\PluginLib\Util\Sanitize\SanitizeJsonGroup::decodeObject
	 * @covers \Ran\PluginLib\Util\Sanitize\SanitizeJsonGroup::decodeArray
	 */
	public function test_json_sanitizers(): void {
		$toVal = Sanitize::json()->decodeToValue();
		$this->assertSame(array('a' => 1), $toVal('{"a":1}'));
		$this->assertSame('x', $toVal('x'));

		$toObj = Sanitize::json()->decodeObject();
		$this->assertSame(array('a' => 1), $toObj('{"a":1}'));
		$this->assertSame('[1,2]', $toObj('[1,2]'));

		$toArr = Sanitize::json()->decodeArray();
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

		$optionalInt = Sanitize::combine()->optional(Sanitize::number()->toInt());
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

		$unlessIntCast = Sanitize::combine()->unless(static fn($v) => is_int($v), Sanitize::number()->toInt());
		$this->assertSame(5, $unlessIntCast(5));
		$this->assertSame(5, $unlessIntCast('5'));
		$this->assertSame('x', $unlessIntCast('x'));
	}

	/**
	 * @covers \Ran\PluginLib\Util\Sanitize::canonical
	 * @covers \Ran\PluginLib\Util\Sanitize\SanitizeCanonicalGroup::orderInsensitiveDeep
	 * @covers \Ran\PluginLib\Util\Sanitize\SanitizeCanonicalGroup::orderInsensitiveShallow
	 */
	public function test_canonical_wrappers(): void {
		$deep = Sanitize::canonical()->orderInsensitiveDeep();
		$this->assertSame(array('a' => 1, 'b' => 2), $deep(array('b' => 2, 'a' => 1)));

		$shallow = Sanitize::canonical()->orderInsensitiveShallow();
		$this->assertSame(array('a' => 1, 'b' => 2), $shallow(array('b' => 2, 'a' => 1)));
	}

	/**
	 * @covers \Ran\PluginLib\Util\Sanitize\SanitizeCanonicalGroup::orderInsensitiveDeep
	 */
	public function test_order_insensitive_deep(): void {
		// Scalars pass-through
		$this->assertSame(123, Sanitize::canonical()->orderInsensitiveDeep(123));
		$this->assertSame('abc', Sanitize::canonical()->orderInsensitiveDeep('abc'));
		$this->assertSame(null, Sanitize::canonical()->orderInsensitiveDeep(null));

		// Object conversion preference: JsonSerializable over public props
		$jsonObj = new class implements \JsonSerializable {
			public function jsonSerialize(): mixed {
				return array('b' => 1, 'a' => 2);
			}
		};
		$this->assertSame(array('a' => 2, 'b' => 1), Sanitize::canonical()->orderInsensitiveDeep($jsonObj));

		$plainObj = new class {
			public int $z = 1;
			public int $a = 2;
		};
		$this->assertSame(array('a' => 2, 'z' => 1), Sanitize::canonical()->orderInsensitiveDeep($plainObj));

		// Assoc maps: recurse and sort by keys
		$a  = array('b' => array('y' => 2, 'x' => 1), 'a' => 9);
		$b  = array('a' => 9, 'b' => array('x' => 1, 'y' => 2));
		$na = Sanitize::canonical()->orderInsensitiveDeep($a);
		$nb = Sanitize::canonical()->orderInsensitiveDeep($b);
		$this->assertSame($na, $nb);
		$this->assertSame(array('a' => 9, 'b' => array('x' => 1, 'y' => 2)), $na);

		// Lists: normalize elements then stable sort by JSON
		$l1 = array(array('k' => 2), array('k' => 1));
		$l2 = array(array('k' => 1), array('k' => 2));
		$n1 = Sanitize::canonical()->orderInsensitiveDeep($l1);
		$n2 = Sanitize::canonical()->orderInsensitiveDeep($l2);
		$this->assertSame($n1, $n2);
		$this->assertSame(array(array('k' => 1), array('k' => 2)), $n1);

		// Nested mixed
		$in = array(
		    array('b' => array(2, 1)),
		    array('a' => array( array('y' => 2, 'x' => 1), array('x' => 1, 'y' => 2) )),
		);
		$out = Sanitize::canonical()->orderInsensitiveDeep($in);
		$this->assertSame(array(
		    array('a' => array( array('x' => 1, 'y' => 2), array('x' => 1, 'y' => 2) )),
		    array('b' => array(1, 2)),
		), $out);
	}

	/**
	 * @covers \Ran\PluginLib\Util\Sanitize\SanitizeCanonicalGroup::orderInsensitiveShallow
	 */
	public function test_order_insensitive_shallow(): void {
		// Scalars pass-through
		$this->assertSame(false, Sanitize::canonical()->orderInsensitiveShallow(false));
		$this->assertSame('x', Sanitize::canonical()->orderInsensitiveShallow('x'));

		// Object conversion preference: JsonSerializable over public props; sort top-level only
		$obj = new class implements \JsonSerializable {
			public function jsonSerialize(): mixed {
				return array('b' => 1, 'a' => 2);
			}
		};
		$this->assertSame(array('a' => 2, 'b' => 1), Sanitize::canonical()->orderInsensitiveShallow($obj));

		$plain = new class {
			public $k = 3;
			public $a = 1;
		};
		$this->assertSame(array('a' => 1, 'k' => 3), Sanitize::canonical()->orderInsensitiveShallow($plain));

		// Assoc: sort top-level keys only; do not recurse
		$in  = array('b' => array('z' => 2, 'a' => 1), 'a' => array('y' => 2, 'x' => 1));
		$out = Sanitize::canonical()->orderInsensitiveShallow($in);
		$this->assertSame(array('a' => array('y' => 2, 'x' => 1), 'b' => array('z' => 2, 'a' => 1)), $out);

		// List: stable sort by JSON at top level only
		$list = array(array('k' => 2), array('k' => 1));
		$this->assertSame(array(array('k' => 1), array('k' => 2)), Sanitize::canonical()->orderInsensitiveShallow($list));

		// Elements are not deep-normalized in shallow mode
		$in2  = array(array('b' => 2, 'a' => 1), array('a' => 1, 'b' => 2));
		$out2 = Sanitize::canonical()->orderInsensitiveShallow($in2);
		$this->assertSame(array(array('a' => 1, 'b' => 2), array('b' => 2, 'a' => 1)), $out2);
	}
}
