<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options;

use Ran\PluginLib\Util\Validate;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;

/**
 * @covers \Ran\PluginLib\Util\Validate::inferSimpleTypeFromValue
 */
final class ValidateTest extends PluginLibTestCase {
	/**
	 * @covers \Ran\PluginLib\Util\Validate::inferSimpleTypeFromValue
	 */
	public function test_infer_boolean(): void {
		$this->assertSame('bool', Validate::inferSimpleTypeFromValue(true));
		$this->assertSame('bool', Validate::inferSimpleTypeFromValue(false));
	}

	/**
	 * @covers \Ran\PluginLib\Util\Validate::format
	 * @covers \Ran\PluginLib\Util\Validate\ValidateFormatGroup::jsonString
	 */
	public function test_format_json_string(): void {
		$json = Validate::format()->jsonString();
		// Valid JSON (various types)
		$this->assertTrue($json('{"a":1}'));
		$this->assertTrue($json('[1,2,3]'));
		$this->assertTrue($json('true'));
		$this->assertTrue($json('null'));
		$this->assertTrue($json('123'));
		// Invalid JSON
		$this->assertFalse($json(''));
		$this->assertFalse($json('{bad}'));
		$this->assertFalse($json('not-json'));
		$this->assertFalse($json(array('array')));
	}

	/**
	 * @covers \Ran\PluginLib\Util\Validate::format
	 * @covers \Ran\PluginLib\Util\Validate\ValidateFormatGroup::email
	 */
	public function test_format_email(): void {
		$email = Validate::format()->email();
		// Valid
		$this->assertTrue($email('user@example.com'));
		$this->assertTrue($email('first.last+tag@sub.domain.co'));
		// Invalid
		$this->assertFalse($email('not-an-email'));
		$this->assertFalse($email('user@'));
		$this->assertFalse($email(array('array')));
	}

	/**
	 * @covers \Ran\PluginLib\Util\Validate::format
	 * @covers \Ran\PluginLib\Util\Validate\ValidateFormatGroup::phone
	 */
	public function test_format_phone(): void {
		$phone = Validate::format()->phone();
		// Valid E.164-like
		$this->assertTrue($phone('+15551234567'));
		$this->assertTrue($phone('+4479645465153'));
        // Invalid cases
		$this->assertFalse($phone('079645465153')); // no + and leading zero
		$this->assertFalse($phone('+012345678')); // invalid country code leading 0
		$this->assertFalse($phone('+1 555 123 4567')); // spaces not allowed
		$this->assertFalse($phone('15551234567')); // missing +
		$this->assertFalse($phone(array('array')));
	}

	/**
	 * @covers \Ran\PluginLib\Util\Validate::format
	 * @covers \Ran\PluginLib\Util\Validate\ValidateFormatGroup::url
	 */
	public function test_format_url(): void {
		$url = Validate::format()->url();
		// Valid
		$this->assertTrue($url('https://example.com'));
		$this->assertTrue($url('http://sub.domain.co/path?x=1#frag'));
		// Invalid
		$this->assertFalse($url('not-a-url'));
		$this->assertFalse($url('http:///bad'));
		$this->assertFalse($url(array('array')));
	}

	/**
	 * @covers \Ran\PluginLib\Util\Validate::format
	 * @covers \Ran\PluginLib\Util\Validate\ValidateFormatGroup::domain
	 */
	public function test_format_domain(): void {
		$domain = Validate::format()->domain();
		// Valid domains
		$this->assertTrue($domain('example.com'));
		$this->assertTrue($domain('sub.domain.co'));
		// Invalid
		$this->assertFalse($domain('localhost'));
		$this->assertFalse($domain('example'));
		$this->assertFalse($domain('exa_mple.com'));
		$this->assertFalse($domain('http://example.com'));
	}

	/**
	 * @covers \Ran\PluginLib\Util\Validate::format
	 * @covers \Ran\PluginLib\Util\Validate\ValidateFormatGroup::hostname
	 */
	public function test_format_hostname(): void {
		$hostname = Validate::format()->hostname();
		// Valid hostnames
		$this->assertTrue($hostname('localhost'));
		$this->assertTrue($hostname('host'));
		$this->assertTrue($hostname('sub.domain.co'));
		// Invalid
		$this->assertFalse($hostname('-bad'));
		$this->assertFalse($hostname('bad-'));
		$this->assertFalse($hostname('http://example.com'));
	}

	/**
	 * @covers \Ran\PluginLib\Util\Validate::format
	 * @covers \Ran\PluginLib\Util\Validate\ValidateFormatGroup::origin
	 */
	public function test_format_origin(): void {
		$origin = Validate::format()->origin();
		// Valid origins
		$this->assertTrue($origin('https://example.com'));
		$this->assertTrue($origin('http://example.com:8080'));
		$this->assertTrue($origin('https://localhost'));
		// Invalid: path/query/fragment present
		$this->assertFalse($origin('https://example.com/path'));
		$this->assertFalse($origin('https://example.com?x=1'));
		$this->assertFalse($origin('https://example.com#frag'));
		// Invalid: missing scheme/host
		$this->assertFalse($origin('example.com'));
		$this->assertFalse($origin('://example.com'));
	}

	/**
	 * @covers \Ran\PluginLib\Util\Validate::inferSimpleTypeFromValue
	 */
	public function test_infer_integer(): void {
		$this->assertSame('int', Validate::inferSimpleTypeFromValue(123));
		$this->assertSame('int', Validate::inferSimpleTypeFromValue(0));
	}

	/**
	 * @covers \Ran\PluginLib\Util\Validate::inferSimpleTypeFromValue
	 */
	public function test_infer_float_from_double(): void {
		$this->assertSame('float', Validate::inferSimpleTypeFromValue(1.5));
		$this->assertSame('float', Validate::inferSimpleTypeFromValue(0.0));
	}

	/**
	 * @covers \Ran\PluginLib\Util\Validate::inferSimpleTypeFromValue
	 */
	public function test_infer_string(): void {
		$this->assertSame('string', Validate::inferSimpleTypeFromValue('abc'));
		$this->assertSame('string', Validate::inferSimpleTypeFromValue(''));
	}

	/**
	 * @covers \Ran\PluginLib\Util\Validate::inferSimpleTypeFromValue
	 */
	public function test_infer_array(): void {
		$this->assertSame('array', Validate::inferSimpleTypeFromValue(array()));
		$this->assertSame('array', Validate::inferSimpleTypeFromValue(array('a' => 1)));
	}

	/**
	 * @covers \Ran\PluginLib\Util\Validate::inferSimpleTypeFromValue
	 */
	public function test_infer_object(): void {
		$this->assertSame('object', Validate::inferSimpleTypeFromValue(new \stdClass()));
		$this->assertSame('object', Validate::inferSimpleTypeFromValue(new class {
		}));
	}

	/**
	 * @covers \Ran\PluginLib\Util\Validate::inferSimpleTypeFromValue
	 */
	public function test_infer_null(): void {
		$this->assertSame('null', Validate::inferSimpleTypeFromValue(null));
	}

	/**
	 * @covers \Ran\PluginLib\Util\Validate::inferSimpleTypeFromValue
	 */
	public function test_infer_callable_returns_callable(): void {
		$this->assertSame('callable', Validate::inferSimpleTypeFromValue(function () {
		}));
		$this->assertSame('callable', Validate::inferSimpleTypeFromValue('strlen'));
	}

	/**
	 * @covers \Ran\PluginLib\Util\Validate::inferSimpleTypeFromValue
	 */
	public function test_infer_unrecognized_type_defaults_to_null(): void {
		$h = fopen('php://memory', 'r');
		try {
			// gettype(...) == 'resource' → default case => null
			$this->assertNull(Validate::inferSimpleTypeFromValue($h));
		} finally {
			fclose($h);
		}
		// After closing, PHP 8+ reports 'resource (closed)' — still unrecognized, expect null
		$this->assertNull(Validate::inferSimpleTypeFromValue($h));
	}

	/**
	 * @covers \Ran\PluginLib\Util\Validate::validateByType
	 */
	/**
	 * @covers \Ran\PluginLib\Util\Validate::validateByType
	 * @covers \Ran\PluginLib\Util\Validate::validatorForType
	 */
	public function test_validate_by_type_known_types(): void {
		// True cases for known types
		$this->assertTrue(Validate::validateByType(123, 'int'));
		$this->assertTrue(Validate::validateByType(true, 'bool'));
		$this->assertTrue(Validate::validateByType(1.5, 'float'));
		$this->assertTrue(Validate::validateByType('abc', 'string'));
		$this->assertTrue(Validate::validateByType(array(), 'array'));
		$this->assertTrue(Validate::validateByType(new \stdClass(), 'object'));
		$this->assertTrue(Validate::validateByType(null, 'null'));

		// False cases for known types
		$this->assertFalse(Validate::validateByType('123', 'int'));
		$this->assertFalse(Validate::validateByType('true', 'bool'));
		$this->assertFalse(Validate::validateByType('1.5', 'float'));
		$this->assertFalse(Validate::validateByType(123, 'string'));
		$this->assertFalse(Validate::validateByType(new \stdClass(), 'array'));
		$this->assertFalse(Validate::validateByType(array(), 'object'));
		$this->assertFalse(Validate::validateByType('not-null', 'null'));
	}

	/**
	 * @covers \Ran\PluginLib\Util\Validate::validateByType
	 */
	/**
	 * @covers \Ran\PluginLib\Util\Validate::validateByType
	 * @covers \Ran\PluginLib\Util\Validate::validatorForType
	 */
	public function test_validate_by_type_aliases(): void {
		// Aliases map to same validators
		$this->assertTrue(Validate::validateByType(5, 'integer'));
		$this->assertTrue(Validate::validateByType(false, 'boolean'));
		$this->assertTrue(Validate::validateByType(2.0, 'double'));

		$this->assertFalse(Validate::validateByType('5', 'integer'));
		$this->assertFalse(Validate::validateByType('false', 'boolean'));
		$this->assertFalse(Validate::validateByType('2.0', 'double'));
	}

	/**
	 * @covers \Ran\PluginLib\Util\Validate::validateByType
	 */
	/**
	 * @covers \Ran\PluginLib\Util\Validate::validateByType
	 * @covers \Ran\PluginLib\Util\Validate::validatorForType
	 */
	public function test_validate_by_type_mixed_and_unknown(): void {
		// mixed → always true regardless of value
		$this->assertTrue(Validate::validateByType('anything', 'mixed'));
		$this->assertTrue(Validate::validateByType(new \stdClass(), 'mixed'));

		// Unknown type → should not block, returns true per implementation
		$this->assertTrue(Validate::validateByType('anything', 'unknown-type'));
		$this->assertTrue(Validate::validateByType(123, 'weird'));
	}

	/**
	 * @covers \Ran\PluginLib\Util\Validate::basic
	 * @covers \Ran\PluginLib\Util\Validate\ValidateBasicGroup::isBool
	 * @covers \Ran\PluginLib\Util\Validate\ValidateBasicGroup::isInt
	 * @covers \Ran\PluginLib\Util\Validate\ValidateBasicGroup::isFloat
	 * @covers \Ran\PluginLib\Util\Validate\ValidateBasicGroup::isString
	 * @covers \Ran\PluginLib\Util\Validate\ValidateBasicGroup::isArray
	 * @covers \Ran\PluginLib\Util\Validate\ValidateBasicGroup::isObject
	 * @covers \Ran\PluginLib\Util\Validate\ValidateBasicGroup::isNull
	 */
	public function test_basic_validators_via_groups(): void {
		$basic = Validate::basic();

		$isBool = $basic->isBool();
		$this->assertTrue($isBool(true));
		$this->assertFalse($isBool(1));

		$isInt = $basic->isInt();
		$this->assertTrue($isInt(10));
		$this->assertFalse($isInt('10'));

		$isFloat = $basic->isFloat();
		$this->assertTrue($isFloat(1.23));
		$this->assertFalse($isFloat('1.23'));

		$isString = $basic->isString();
		$this->assertTrue($isString('abc'));
		$this->assertFalse($isString(123));

		$isArray = $basic->isArray();
		$this->assertTrue($isArray(array('k' => 'v')));
		$this->assertFalse($isArray(new \stdClass()));

		$isObject = $basic->isObject();
		$this->assertTrue($isObject(new \stdClass()));
		$this->assertFalse($isObject(array()));

		$isNull = $basic->isNull();
		$this->assertTrue($isNull(null));
		$this->assertFalse($isNull('not-null'));
	}

	/**
	 * @covers \Ran\PluginLib\Util\Validate::basic
	 * @covers \Ran\PluginLib\Util\Validate\ValidateBasicGroup::isScalar
	 * @covers \Ran\PluginLib\Util\Validate\ValidateBasicGroup::isNumeric
	 * @covers \Ran\PluginLib\Util\Validate\ValidateBasicGroup::isNullable
	 * @covers \Ran\PluginLib\Util\Validate\ValidateBasicGroup::isCallable
	 */
	public function test_basic_additional_predicates(): void {
		$basic = Validate::basic();

		$isScalar = $basic->isScalar();
		$this->assertTrue($isScalar(1));
		$this->assertTrue($isScalar(1.5));
		$this->assertTrue($isScalar('x'));
		$this->assertTrue($isScalar(true));
		$this->assertFalse($isScalar(array()));
		$this->assertFalse($isScalar(new \stdClass()));

		$isNumeric = $basic->isNumeric();
		$this->assertTrue($isNumeric(123));
		$this->assertTrue($isNumeric('123')); // numeric string
		$this->assertFalse($isNumeric('abc'));
		$this->assertFalse($isNumeric(array()));

		$isNullable = $basic->isNullable();
		$this->assertTrue($isNullable(null));
		$this->assertTrue($isNullable('string')); // scalar passes
		$this->assertFalse($isNullable(array())); // non-scalar non-null fails

		$isCallable = $basic->isCallable();
		$this->assertTrue($isCallable('strlen'));
		$this->assertTrue($isCallable(function () {
		}));
		$this->assertFalse($isCallable('not_a_function'));
		$this->assertFalse($isCallable(123));
	}

	/**
	 * @covers \Ran\PluginLib\Util\Validate::enums
	 * @covers \Ran\PluginLib\Util\Validate\ValidateEnumGroup::enum
	 * @covers \Ran\PluginLib\Util\Validate\ValidateEnumGroup::backed
	 * @covers \Ran\PluginLib\Util\Validate\ValidateEnumGroup::unit
	 */
	public function test_enums_helpers_in_main_suite(): void {
		// oneOf
		$oneOf = Validate::enums()->enum(array('a', 'b'));
		$this->assertTrue($oneOf('a'));
		$this->assertFalse($oneOf('c'));

		// backed enum by value
		$backed = Validate::enums()->backed(VTMode::class);
		$this->assertTrue($backed('basic'));
		$this->assertFalse($backed('On')); // unit enum case name should fail here

		// unit enum by case name
		$unit = Validate::enums()->unit(VTFlag::class);
		$this->assertTrue($unit('On'));
		$this->assertFalse($unit('basic'));
	}

	/**
	 * @covers \Ran\PluginLib\Util\Validate::collection
	 * @covers \Ran\PluginLib\Util\Validate\ValidateCollectionGroup::hasKeys
	 * @covers \Ran\PluginLib\Util\Validate\ValidateCollectionGroup::exactKeys
	 */
	public function test_collection_has_keys_and_exact_keys(): void {
		$hasXY = Validate::collection()->hasKeys(array('x', 'y'));
		$this->assertTrue($hasXY(array('x' => 1, 'y' => 2)));
		$this->assertTrue($hasXY(array('x' => 1, 'y' => 2, 'z' => 3)));
		$this->assertFalse($hasXY(array('x' => 1)));
		$this->assertFalse($hasXY('not-array'));

		$exactXY = Validate::collection()->exactKeys(array('x', 'y'));
		$this->assertTrue($exactXY(array('x' => 1, 'y' => 2)));
		$this->assertFalse($exactXY(array('x' => 1, 'y' => 2, 'z' => 3)));
		$this->assertFalse($exactXY(array('x' => 1)));
		$this->assertFalse($exactXY('not-array'));
	}

	/**
	 * @covers \Ran\PluginLib\Util\Validate::collection
	 * @covers \Ran\PluginLib\Util\Validate\ValidateCollectionGroup::strictShape
	 */
	public function test_collection_strict_shape(): void {
		$shape = Validate::collection()->strictShape(array(
		    'x' => Validate::basic()->isInt(),
		    'y' => Validate::basic()->isInt(),
		));

		$this->assertTrue($shape(array('x' => 1, 'y' => 2)));
		$this->assertFalse($shape(array('x' => 1, 'y' => 2, 'z' => 3))); // extra key
		$this->assertFalse($shape(array('x' => 1))); // missing key
		$this->assertFalse($shape(array('x' => 1, 'y' => '2'))); // wrong type
		$this->assertFalse($shape('not-array'));
	}

	/**
	 * @covers \Ran\PluginLib\Util\Validate::collection
	 * @covers \Ran\PluginLib\Util\Validate\ValidateCollectionGroup::minItems
	 * @covers \Ran\PluginLib\Util\Validate\ValidateCollectionGroup::maxItems
	 */
	public function test_collection_min_max_items(): void {
		$nonEmpty = Validate::collection()->minItems(1);
		$this->assertFalse($nonEmpty(array()));
		$this->assertTrue($nonEmpty(array(1)));
		$this->assertFalse($nonEmpty('not-array'));

		$maxTwo = Validate::collection()->maxItems(2);
		$this->assertTrue($maxTwo(array(1)));
		$this->assertTrue($maxTwo(array(1, 2)));
		$this->assertFalse($maxTwo(array(1, 2, 3)));
		$this->assertFalse($maxTwo('not-array'));
	}

	/**
	 * @covers \Ran\PluginLib\Util\Validate::number
	 * @covers \Ran\PluginLib\Util\Validate\ValidateNumberGroup::min
	 * @covers \Ran\PluginLib\Util\Validate\ValidateNumberGroup::max
	 * @covers \Ran\PluginLib\Util\Validate\ValidateNumberGroup::between
	 */
	public function test_number_group_helpers(): void {
		$min = Validate::number()->min(10);
		$this->assertTrue($min(10));
		$this->assertTrue($min(11));
		$this->assertFalse($min(9));
		$this->assertFalse($min('10'));

		$max = Validate::number()->max(5);
		$this->assertTrue($max(5));
		$this->assertTrue($max(4));
		$this->assertFalse($max(6));
		$this->assertFalse($max('5'));

		$between = Validate::number()->between(1, 3);
		$this->assertTrue($between(1));
		$this->assertTrue($between(2));
		$this->assertTrue($between(3));
		$this->assertFalse($between(0));
		$this->assertFalse($between(4));
		$this->assertFalse($between('2'));
	}

	/**
	 * @covers \Ran\PluginLib\Util\Validate::string
	 * @covers \Ran\PluginLib\Util\Validate\ValidateStringGroup::minLength
	 * @covers \Ran\PluginLib\Util\Validate\ValidateStringGroup::maxLength
	 * @covers \Ran\PluginLib\Util\Validate\ValidateStringGroup::lengthBetween
	 * @covers \Ran\PluginLib\Util\Validate\ValidateStringGroup::pattern
	 */
	public function test_string_group_helpers(): void {
		$minLen = Validate::string()->minLength(2);
		$this->assertTrue($minLen('ab'));
		$this->assertFalse($minLen('a'));
		$this->assertFalse($minLen(123));

		$maxLen = Validate::string()->maxLength(2);
		$this->assertTrue($maxLen('ab'));
		$this->assertTrue($maxLen('a'));
		$this->assertFalse($maxLen('abc'));

		$lenBetween = Validate::string()->lengthBetween(1, 3);
		$this->assertTrue($lenBetween('a'));
		$this->assertTrue($lenBetween('abc'));
		$this->assertFalse($lenBetween(''));
		$this->assertFalse($lenBetween('abcd'));

		$pattern = Validate::string()->pattern('/^a+$/');
		$this->assertTrue($pattern('a'));
		$this->assertTrue($pattern('aaa'));
		$this->assertFalse($pattern('ab'));
		$this->assertFalse($pattern(123));
	}

	/**
	 * @covers \Ran\PluginLib\Util\Validate::collection
	 * @covers \Ran\PluginLib\Util\Validate\ValidateCollectionGroup::listOf
	 * @covers \Ran\PluginLib\Util\Validate\ValidateCollectionGroup::shape
	 */
	public function test_collection_listOf_and_shape(): void {
		$listOfInts = Validate::collection()->listOf(Validate::basic()->isInt());
		$this->assertTrue($listOfInts(array(1, 2, 3)));
		$this->assertFalse($listOfInts(array(1, '2', 3)));
		$this->assertFalse($listOfInts('not-array'));

		$shape = Validate::collection()->shape(array(
		    'x' => Validate::basic()->isInt(),
		    'y' => Validate::basic()->isInt(),
		));
		$this->assertTrue($shape(array('x' => 1, 'y' => 2)));
		$this->assertTrue($shape(array('x' => 1, 'y' => 2, 'z' => 3))); // extras allowed
		$this->assertFalse($shape(array('x' => 1)));
		$this->assertFalse($shape(array('x' => 1, 'y' => '2')));
	}

	/**
	 * @covers \Ran\PluginLib\Util\Validate::compose
	 * @covers \Ran\PluginLib\Util\Validate\ValidateComposeGroup::nullable
	 * @covers \Ran\PluginLib\Util\Validate\ValidateComposeGroup::optional
	 * @covers \Ran\PluginLib\Util\Validate\ValidateComposeGroup::union
	 * @covers \Ran\PluginLib\Util\Validate\ValidateComposeGroup::all
	 * @covers \Ran\PluginLib\Util\Validate\ValidateComposeGroup::none
	 */
	public function test_compose_helpers(): void {
		$nullableString = Validate::compose()->nullable(Validate::basic()->isString());
		$this->assertTrue($nullableString(null));
		$this->assertTrue($nullableString('abc'));
		$this->assertFalse($nullableString(123));

		$optionalInt = Validate::compose()->optional(Validate::basic()->isInt());
		$this->assertTrue($optionalInt(null));
		$this->assertTrue($optionalInt(5));
		$this->assertFalse($optionalInt('5'));

		$unionIntFloat = Validate::compose()->union(
			Validate::basic()->isInt(),
			Validate::basic()->isFloat()
		);
		$this->assertTrue($unionIntFloat(1));
		$this->assertTrue($unionIntFloat(1.5));
		$this->assertFalse($unionIntFloat('1.5'));

		$allStringLen = Validate::compose()->all(
			Validate::basic()->isString(),
			Validate::string()->minLength(1)
		);
		$this->assertTrue($allStringLen('a'));
		$this->assertFalse($allStringLen(''));
		$this->assertFalse($allStringLen(1));

		$noneEnum = Validate::compose()->none(
			Validate::enums()->enum(array('deprecated', 'removed'))
		);
		$this->assertTrue($noneEnum('active'));
		$this->assertFalse($noneEnum('deprecated'));
	}

	/**
		* @covers \Ran\PluginLib\Util\Validate::basic
		* @covers \Ran\PluginLib\Util\Validate\ValidateBasicGroup::isEmpty
		*/
	public function test_basic_is_empty(): void {
		$empty = Validate::basic()->isEmpty();
		// True cases
		$this->assertTrue($empty(''));
		$this->assertTrue($empty(null));
		$this->assertTrue($empty(false));
		// False cases (not considered empty by our strict predicate)
		$this->assertFalse($empty(0));
		$this->assertFalse($empty('0'));
		$this->assertFalse($empty(array()));
		$this->assertFalse($empty(new \stdClass()));
	}

	/**
		* @covers \Ran\PluginLib\Util\Validate::basic
		* @covers \Ran\PluginLib\Util\Validate\ValidateBasicGroup::isNotEmpty
		*/
	public function test_basic_is_not_empty(): void {
		$notEmpty = Validate::basic()->isNotEmpty();
		// False cases
		$this->assertFalse($notEmpty(''));
		$this->assertFalse($notEmpty(null));
		$this->assertFalse($notEmpty(false));
		// True cases
		$this->assertTrue($notEmpty(0));
		$this->assertTrue($notEmpty('0'));
		$this->assertTrue($notEmpty(array()));
		$this->assertTrue($notEmpty(new \stdClass()));
		$this->assertTrue($notEmpty('x'));
		$this->assertTrue($notEmpty(1));
	}
}

// Enums for enum validator tests (PHP 8.1+)
enum VTMode: string {
	case basic = 'basic';
	case pro   = 'pro';
}
enum VTFlag {
	case On;
	case Off;
}

final class ValidateEnumHelpersTest extends PluginLibTestCase {
	/**
	 * @covers \Ran\PluginLib\Util\Validate::enums
	 * @covers \Ran\PluginLib\Util\Validate\ValidateEnumGroup::enum
	 */
	public function test_enums_enum(): void {
		$oneOf = Validate::enums()->enum(array('a', 'b', 'c'));
		$this->assertTrue($oneOf('a'));
		$this->assertFalse($oneOf('d'));
	}

	/**
	 * @covers \Ran\PluginLib\Util\Validate::enums
	 * @covers \Ran\PluginLib\Util\Validate\ValidateEnumGroup::backed
	 */
	public function test_enums_backed(): void {
		$backed = Validate::enums()->backed(\VTMode::class);
		$this->assertTrue($backed('basic'));
		$this->assertFalse($backed('On'));
		$this->assertFalse($backed('unknown'));
	}

	/**
	 * @covers \Ran\PluginLib\Util\Validate::enums
	 * @covers \Ran\PluginLib\Util\Validate\ValidateEnumGroup::unit
	 */
	public function test_enums_unit(): void {
		$unit = Validate::enums()->unit(\VTFlag::class);
		$this->assertTrue($unit('On'));
		$this->assertFalse($unit('basic'));
		$this->assertFalse($unit('Unknown'));
	}
}
