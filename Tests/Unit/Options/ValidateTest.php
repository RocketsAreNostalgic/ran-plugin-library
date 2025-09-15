<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options;

use Ran\PluginLib\Options\Validate;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;

/**
 * @covers \Ran\PluginLib\Options\Validate::inferSimpleTypeFromDefault
 */
final class ValidateTest extends PluginLibTestCase {
    public function test_infer_boolean(): void {
        $this->assertSame('bool', Validate::inferSimpleTypeFromDefault(true));
        $this->assertSame('bool', Validate::inferSimpleTypeFromDefault(false));
    }

    public function test_infer_integer(): void {
        $this->assertSame('int', Validate::inferSimpleTypeFromDefault(123));
        $this->assertSame('int', Validate::inferSimpleTypeFromDefault(0));
    }

    public function test_infer_float_from_double(): void {
        $this->assertSame('float', Validate::inferSimpleTypeFromDefault(1.5));
        $this->assertSame('float', Validate::inferSimpleTypeFromDefault(0.0));
    }

    public function test_infer_string(): void {
        $this->assertSame('string', Validate::inferSimpleTypeFromDefault('abc'));
        $this->assertSame('string', Validate::inferSimpleTypeFromDefault(''));
    }

    public function test_infer_array(): void {
        $this->assertSame('array', Validate::inferSimpleTypeFromDefault([]));
        $this->assertSame('array', Validate::inferSimpleTypeFromDefault(['a' => 1]));
    }

    public function test_infer_object(): void {
        $this->assertSame('object', Validate::inferSimpleTypeFromDefault(new \stdClass()));
        $this->assertSame('object', Validate::inferSimpleTypeFromDefault(new class {}));
    }

    public function test_infer_null(): void {
        $this->assertSame('null', Validate::inferSimpleTypeFromDefault(null));
    }

    public function test_infer_callable_is_null(): void {
        $this->assertNull(Validate::inferSimpleTypeFromDefault(function () {}));
        $this->assertNull(Validate::inferSimpleTypeFromDefault('strlen'));
    }

    public function test_infer_unrecognized_type_defaults_to_null(): void {
        $h = fopen('php://memory', 'r');
        try {
            // gettype(...) == 'resource' → default case => null
            $this->assertNull(Validate::inferSimpleTypeFromDefault($h));
        } finally {
            fclose($h);
        }
        // After closing, PHP 8+ reports 'resource (closed)' — still unrecognized, expect null
        $this->assertNull(Validate::inferSimpleTypeFromDefault($h));
    }

    /**
     * @covers \Ran\PluginLib\Options\Validate::validateByType
     */
    public function test_validate_by_type_known_types(): void {
        // True cases for known types
        $this->assertTrue(Validate::validateByType(123, 'int'));
        $this->assertTrue(Validate::validateByType(true, 'bool'));
        $this->assertTrue(Validate::validateByType(1.5, 'float'));
        $this->assertTrue(Validate::validateByType('abc', 'string'));
        $this->assertTrue(Validate::validateByType([], 'array'));
        $this->assertTrue(Validate::validateByType(new \stdClass(), 'object'));
        $this->assertTrue(Validate::validateByType(null, 'null'));

        // False cases for known types
        $this->assertFalse(Validate::validateByType('123', 'int'));
        $this->assertFalse(Validate::validateByType('true', 'bool'));
        $this->assertFalse(Validate::validateByType('1.5', 'float'));
        $this->assertFalse(Validate::validateByType(123, 'string'));
        $this->assertFalse(Validate::validateByType(new \stdClass(), 'array'));
        $this->assertFalse(Validate::validateByType([], 'object'));
        $this->assertFalse(Validate::validateByType('not-null', 'null'));
    }

    /**
     * @covers \Ran\PluginLib\Options\Validate::validateByType
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
     * @covers \Ran\PluginLib\Options\Validate::validateByType
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
     * @covers \Ran\PluginLib\Options\Validate::isBool
     * @covers \Ran\PluginLib\Options\Validate::isInt
     * @covers \Ran\PluginLib\Options\Validate::isFloat
     * @covers \Ran\PluginLib\Options\Validate::isString
     * @covers \Ran\PluginLib\Options\Validate::isArray
     * @covers \Ran\PluginLib\Options\Validate::isObject
     * @covers \Ran\PluginLib\Options\Validate::isNull
     * @covers \Ran\PluginLib\Options\Validate::alwaysTrue
     */
    public function test_basic_validators_direct(): void {
        // isBool
        $this->assertTrue(Validate::isBool(true));
        $this->assertFalse(Validate::isBool(1));

        // isInt
        $this->assertTrue(Validate::isInt(10));
        $this->assertFalse(Validate::isInt('10'));

        // isFloat
        $this->assertTrue(Validate::isFloat(1.23));
        $this->assertFalse(Validate::isFloat('1.23'));

        // isString
        $this->assertTrue(Validate::isString('abc'));
        $this->assertFalse(Validate::isString(123));

        // isArray
        $this->assertTrue(Validate::isArray(array('k' => 'v')));
        $this->assertFalse(Validate::isArray(new \stdClass()));

        // isObject
        $this->assertTrue(Validate::isObject(new \stdClass()));
        $this->assertFalse(Validate::isObject(array()));

        // isNull
        $this->assertTrue(Validate::isNull(null));
        $this->assertFalse(Validate::isNull('not-null'));

        // alwaysTrue
        $this->assertTrue(Validate::alwaysTrue('anything'));
        $this->assertTrue(Validate::alwaysTrue(null));
    }
}
