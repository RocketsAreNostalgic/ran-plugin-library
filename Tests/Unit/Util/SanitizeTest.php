<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options;

use Ran\PluginLib\Util\Sanitize;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;

final class SanitizeTest extends PluginLibTestCase {
	/**
	 * @covers \Ran\PluginLib\Util\Sanitize::string
	 * @covers \Ran\PluginLib\Util\SanitizeStringGroup::trim
	 * @covers \Ran\PluginLib\Util\SanitizeStringGroup::toLower
	 * @covers \Ran\PluginLib\Util\SanitizeStringGroup::stripTags
	 */
	public function test_string_sanitizers(): void {
		$trim = Sanitize::string()->trim();
		$this->assertSame('abc', $trim('  abc  '));
		$this->assertSame(123, $trim(123));

		$lower = Sanitize::string()->toLower();
		$this->assertSame('abc', $lower('AbC'));
		$this->assertSame(123, $lower(123));

		$strip = Sanitize::string()->stripTags();
		$this->assertSame('hello', $strip('<b>hello</b>'));
		$this->assertSame(array('x'), $strip(array('x')));
	}

	/**
	 * @covers \Ran\PluginLib\Util\Sanitize::number
	 * @covers \Ran\PluginLib\Util\SanitizeNumberGroup::toInt
	 * @covers \Ran\PluginLib\Util\SanitizeNumberGroup::toFloat
	 * @covers \Ran\PluginLib\Util\SanitizeNumberGroup::toBoolStrict
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
	 * @covers \Ran\PluginLib\Util\SanitizeArrayGroup::ensureList
	 * @covers \Ran\PluginLib\Util\SanitizeArrayGroup::uniqueList
	 * @covers \Ran\PluginLib\Util\SanitizeArrayGroup::ksortAssoc
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
	 * @covers \Ran\PluginLib\Util\SanitizeJsonGroup::decodeToValue
	 * @covers \Ran\PluginLib\Util\SanitizeJsonGroup::decodeObject
	 * @covers \Ran\PluginLib\Util\SanitizeJsonGroup::decodeArray
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
	 * @covers \Ran\PluginLib\Util\SanitizeComposeGroup::pipe
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
	 * @covers \Ran\PluginLib\Util\SanitizeComposeGroup::nullable
	 * @covers \Ran\PluginLib\Util\SanitizeComposeGroup::optional
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
	 * @covers \Ran\PluginLib\Util\SanitizeComposeGroup::when
	 * @covers \Ran\PluginLib\Util\SanitizeComposeGroup::unless
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
	 * @covers \Ran\PluginLib\Util\SanitizeCanonicalGroup::orderInsensitiveDeep
	 * @covers \Ran\PluginLib\Util\SanitizeCanonicalGroup::orderInsensitiveShallow
	 */
	public function test_canonical_wrappers(): void {
		$deep = Sanitize::canonical()->orderInsensitiveDeep();
		$this->assertSame(array('a' => 1, 'b' => 2), $deep(array('b' => 2, 'a' => 1)));

		$shallow = Sanitize::canonical()->orderInsensitiveShallow();
		$this->assertSame(array('a' => 1, 'b' => 2), $shallow(array('b' => 2, 'a' => 1)));
	}

	/**
	 * @covers \Ran\PluginLib\Util\Sanitize::orderInsensitiveDeep
	 */
	public function test_order_insensitive_deep(): void {
		// Scalars pass-through
		$this->assertSame(123, Sanitize::orderInsensitiveDeep(123));
		$this->assertSame('abc', Sanitize::orderInsensitiveDeep('abc'));
		$this->assertSame(null, Sanitize::orderInsensitiveDeep(null));

		// Object conversion preference: JsonSerializable over public props
		$jsonObj = new class implements \JsonSerializable {
			public function jsonSerialize(): mixed {
				return array('b' => 1, 'a' => 2);
			}
		};
		$this->assertSame(array('a' => 2, 'b' => 1), Sanitize::orderInsensitiveDeep($jsonObj));

		$plainObj = new class {
			public int $z = 1;
			public int $a = 2;
		};
		$this->assertSame(array('a' => 2, 'z' => 1), Sanitize::orderInsensitiveDeep($plainObj));

		// Assoc maps: recurse and sort by keys
		$a  = array('b' => array('y' => 2, 'x' => 1), 'a' => 9);
		$b  = array('a' => 9, 'b' => array('x' => 1, 'y' => 2));
		$na = Sanitize::orderInsensitiveDeep($a);
		$nb = Sanitize::orderInsensitiveDeep($b);
		$this->assertSame($na, $nb);
		$this->assertSame(array('a' => 9, 'b' => array('x' => 1, 'y' => 2)), $na);

		// Lists: normalize elements then stable sort by JSON
		$l1 = array(array('k' => 2), array('k' => 1));
		$l2 = array(array('k' => 1), array('k' => 2));
		$n1 = Sanitize::orderInsensitiveDeep($l1);
		$n2 = Sanitize::orderInsensitiveDeep($l2);
		$this->assertSame($n1, $n2);
		$this->assertSame(array(array('k' => 1), array('k' => 2)), $n1);

		// Nested mixed
		$in = array(
		    array('b' => array(2, 1)),
		    array('a' => array( array('y' => 2, 'x' => 1), array('x' => 1, 'y' => 2) )),
		);
		$out = Sanitize::orderInsensitiveDeep($in);
		$this->assertSame(array(
		    array('a' => array( array('x' => 1, 'y' => 2), array('x' => 1, 'y' => 2) )),
		    array('b' => array(1, 2)),
		), $out);
	}

	/**
	 * @covers \Ran\PluginLib\Util\Sanitize::orderInsensitiveShallow
	 */
	public function test_order_insensitive_shallow(): void {
		// Scalars pass-through
		$this->assertSame(false, Sanitize::orderInsensitiveShallow(false));
		$this->assertSame('x', Sanitize::orderInsensitiveShallow('x'));

		// Object conversion preference: JsonSerializable over public props; sort top-level only
		$obj = new class implements \JsonSerializable {
			public function jsonSerialize(): mixed {
				return array('b' => 1, 'a' => 2);
			}
		};
		$this->assertSame(array('a' => 2, 'b' => 1), Sanitize::orderInsensitiveShallow($obj));

		$plain = new class {
			public $k = 3;
			public $a = 1;
		};
		$this->assertSame(array('a' => 1, 'k' => 3), Sanitize::orderInsensitiveShallow($plain));

		// Assoc: sort top-level keys only; do not recurse
		$in  = array('b' => array('z' => 2, 'a' => 1), 'a' => array('y' => 2, 'x' => 1));
		$out = Sanitize::orderInsensitiveShallow($in);
		$this->assertSame(array('a' => array('y' => 2, 'x' => 1), 'b' => array('z' => 2, 'a' => 1)), $out);

		// List: stable sort by JSON at top level only
		$list = array(array('k' => 2), array('k' => 1));
		$this->assertSame(array(array('k' => 1), array('k' => 2)), Sanitize::orderInsensitiveShallow($list));

		// Elements are not deep-normalized in shallow mode
		$in2  = array(array('b' => 2, 'a' => 1), array('a' => 1, 'b' => 2));
		$out2 = Sanitize::orderInsensitiveShallow($in2);
		$this->assertSame(array(array('a' => 1, 'b' => 2), array('b' => 2, 'a' => 1)), $out2);
	}
}
