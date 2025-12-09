<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms\Component\Sanitize;

use Ran\PluginLib\Util\CollectingLogger;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Forms\Component\Sanitize\SanitizerBase;
use Ran\PluginLib\Forms\Component\Sanitize\SanitizerInterface;

/**
 * Concrete test implementation of SanitizerBase.
 */
final class TestSanitizer extends SanitizerBase {
	protected function _sanitize_component(mixed $value, array $context, callable $emitNotice): mixed {
		// Simple pass-through for testing base class behavior
		return $value;
	}
}

/**
 * Test implementation that uses helper methods.
 */
final class ArrayFilterSanitizer extends SanitizerBase {
	protected function _sanitize_component(mixed $value, array $context, callable $emitNotice): mixed {
		$values  = $this->_coerce_to_array($value);
		$allowed = $this->_collect_allowed_values($context['options'] ?? array());
		return $this->_filter_array_to_allowed($values, $allowed, $emitNotice);
	}
}

/**
 * Test implementation for scalar filtering.
 */
final class ScalarFilterSanitizer extends SanitizerBase {
	protected function _sanitize_component(mixed $value, array $context, callable $emitNotice): mixed {
		$allowed = $this->_collect_allowed_values($context['options'] ?? array());
		return $this->_filter_scalar_to_allowed($value, $allowed, $emitNotice);
	}
}

/**
 * Test implementation for boolean coercion.
 */
final class BoolSanitizer extends SanitizerBase {
	protected function _sanitize_component(mixed $value, array $context, callable $emitNotice): mixed {
		return $this->_coerce_to_bool($value);
	}
}

final class SanitizerBaseTest extends PluginLibTestCase {
	public function test_implements_sanitizer_interface(): void {
		$sanitizer = new TestSanitizer();
		self::assertInstanceOf(SanitizerInterface::class, $sanitizer);
	}

	public function test_sanitize_passes_null_through_when_allowed(): void {
		$sanitizer  = new TestSanitizer();
		$notices    = array();
		$emitNotice = function (string $msg) use (&$notices): void {
			$notices[] = $msg;
		};

		$result = $sanitizer->sanitize(null, array(), $emitNotice);

		self::assertNull($result);
		self::assertEmpty($notices);
	}

	public function test_sanitize_delegates_to_component_method(): void {
		$sanitizer  = new TestSanitizer();
		$notices    = array();
		$emitNotice = function (string $msg) use (&$notices): void {
			$notices[] = $msg;
		};

		$result = $sanitizer->sanitize('test value', array(), $emitNotice);

		self::assertSame('test value', $result);
	}

	public function test_collect_allowed_values_extracts_from_options(): void {
		$sanitizer  = new ArrayFilterSanitizer(new CollectingLogger());
		$notices    = array();
		$emitNotice = function (string $msg) use (&$notices): void {
			$notices[] = $msg;
		};

		$context = array(
			'options' => array(
				array('value' => 'alpha', 'label' => 'Alpha'),
				array('value' => 'beta', 'label' => 'Beta'),
				array('value' => 'gamma', 'label' => 'Gamma'),
			),
		);

		// Pass allowed values - should return as-is
		$result = $sanitizer->sanitize(array('alpha', 'beta'), $context, $emitNotice);

		self::assertSame(array('alpha', 'beta'), $result);
		self::assertEmpty($notices);
	}

	public function test_filter_array_to_allowed_removes_invalid_values(): void {
		$sanitizer  = new ArrayFilterSanitizer(new CollectingLogger());
		$notices    = array();
		$emitNotice = function (string $msg) use (&$notices): void {
			$notices[] = $msg;
		};

		$context = array(
			'options' => array(
				array('value' => 'alpha', 'label' => 'Alpha'),
				array('value' => 'beta', 'label' => 'Beta'),
			),
		);

		// Include invalid value 'invalid'
		$result = $sanitizer->sanitize(array('alpha', 'invalid', 'beta'), $context, $emitNotice);

		self::assertSame(array('alpha', 'beta'), $result);
		self::assertCount(1, $notices);
		self::assertStringContainsString('invalid selections', $notices[0]);
	}

	public function test_filter_scalar_to_allowed_returns_valid_value(): void {
		$sanitizer  = new ScalarFilterSanitizer(new CollectingLogger());
		$notices    = array();
		$emitNotice = function (string $msg) use (&$notices): void {
			$notices[] = $msg;
		};

		$context = array(
			'options' => array(
				array('value' => 'alpha', 'label' => 'Alpha'),
				array('value' => 'beta', 'label' => 'Beta'),
			),
		);

		$result = $sanitizer->sanitize('alpha', $context, $emitNotice);

		self::assertSame('alpha', $result);
		self::assertEmpty($notices);
	}

	public function test_filter_scalar_to_allowed_returns_null_for_invalid(): void {
		$sanitizer  = new ScalarFilterSanitizer(new CollectingLogger());
		$notices    = array();
		$emitNotice = function (string $msg) use (&$notices): void {
			$notices[] = $msg;
		};

		$context = array(
			'options' => array(
				array('value' => 'alpha', 'label' => 'Alpha'),
				array('value' => 'beta', 'label' => 'Beta'),
			),
		);

		$result = $sanitizer->sanitize('invalid', $context, $emitNotice);

		self::assertNull($result);
		self::assertCount(1, $notices);
		self::assertStringContainsString('Invalid selection', $notices[0]);
	}

	public function test_coerce_to_array_handles_various_inputs(): void {
		$sanitizer  = new ArrayFilterSanitizer(new CollectingLogger());
		$notices    = array();
		$emitNotice = function (string $msg) use (&$notices): void {
			$notices[] = $msg;
		};

		// Empty options means no filtering, just coercion
		$context = array('options' => array());

		// Array input
		$result = $sanitizer->sanitize(array('a', 'b'), $context, $emitNotice);
		self::assertSame(array('a', 'b'), $result);

		// Single scalar becomes array
		$result = $sanitizer->sanitize('single', $context, $emitNotice);
		self::assertSame(array('single'), $result);

		// Empty string becomes empty array
		$result = $sanitizer->sanitize('', $context, $emitNotice);
		self::assertSame(array(), $result);

		// False becomes empty array
		$result = $sanitizer->sanitize(false, $context, $emitNotice);
		self::assertSame(array(), $result);
	}

	public function test_coerce_to_bool_handles_various_inputs(): void {
		$sanitizer  = new BoolSanitizer(new CollectingLogger());
		$notices    = array();
		$emitNotice = function (string $msg) use (&$notices): void {
			$notices[] = $msg;
		};

		// Truthy values
		self::assertTrue($sanitizer->sanitize(true, array(), $emitNotice));
		self::assertTrue($sanitizer->sanitize('1', array(), $emitNotice));
		self::assertTrue($sanitizer->sanitize(1, array(), $emitNotice));
		self::assertTrue($sanitizer->sanitize('on', array(), $emitNotice));
		self::assertTrue($sanitizer->sanitize('yes', array(), $emitNotice));
		self::assertTrue($sanitizer->sanitize('true', array(), $emitNotice));

		// Falsy values
		self::assertFalse($sanitizer->sanitize(false, array(), $emitNotice));
		self::assertFalse($sanitizer->sanitize('0', array(), $emitNotice));
		self::assertFalse($sanitizer->sanitize(0, array(), $emitNotice));
		self::assertFalse($sanitizer->sanitize('', array(), $emitNotice));
	}

	public function test_manifest_defaults_returns_empty_array(): void {
		self::assertSame(array(), TestSanitizer::manifest_defaults());
	}

	public function test_constructor_accepts_logger(): void {
		$logger    = new CollectingLogger();
		$sanitizer = new TestSanitizer($logger);

		// Just verify it doesn't throw
		$result = $sanitizer->sanitize('test', array(), function (): void {
		});
		self::assertSame('test', $result);
	}
}
