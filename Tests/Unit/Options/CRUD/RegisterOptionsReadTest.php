<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options;

use WP_Mock;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;

/**
 * Tests for RegisterOptions read operations.
 */
final class RegisterOptionsReadTest extends PluginLibTestCase {
	public function setUp(): void {
		parent::setUp();

		// Mock basic WordPress functions that WPWrappersTrait calls
		WP_Mock::userFunction('get_option')->andReturn(array())->byDefault();
		WP_Mock::userFunction('get_site_option')->andReturn(array())->byDefault();
		WP_Mock::userFunction('get_blog_option')->andReturn(array())->byDefault();
		WP_Mock::userFunction('get_user_option')->andReturn(array())->byDefault();
		WP_Mock::userFunction('get_user_meta')->andReturn(array())->byDefault();
		WP_Mock::userFunction('wp_load_alloptions')->andReturn(array())->byDefault();

		// Mock sanitize_key to properly handle key normalization
		WP_Mock::userFunction('sanitize_key')->andReturnUsing(function($key) {
			$key = strtolower($key);
			// Replace any run of non [a-z0-9_\-] with a single underscore (preserve hyphens)
			$key = preg_replace('/[^a-z0-9_\-]+/i', '_', $key) ?? '';
			// Trim underscores at edges (preserve leading/trailing hyphens if present)
			return trim($key, '_');
		});
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::get_option
	 */
	public function test_get_option_with_existing_key(): void {
		$opts = RegisterOptions::site('test_options');
		// Add some test data to the options
		$this->_set_protected_property_value($opts, 'options', array('test_key' => 'test_value', 'another_key' => 42));

		$this->assertEquals('test_value', $opts->get_option('test_key'));
		$this->assertEquals(42, $opts->get_option('another_key'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::get_option
	 */
	public function test_get_option_with_non_existing_key_and_default(): void {
		$opts = RegisterOptions::site('test_options');

		$this->assertEquals('default_value', $opts->get_option('non_existing_key', 'default_value'));
		$this->assertEquals(123, $opts->get_option('another_key', 123));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::get_option
	 */
	public function test_get_option_with_non_existing_key_no_default(): void {
		$opts = RegisterOptions::site('test_options');

		$this->assertFalse($opts->get_option('non_existing_key')); // Default is false
		$this->assertEquals('', $opts->get_option('another_key', '')); // Explicit empty string
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::get_options
	 */
	public function test_get_options_returns_all_options(): void {
		$opts     = RegisterOptions::site('test_options');
		$testData = array('key1' => 'value1', 'key2' => 'value2', 'key3' => 123);
		$this->_set_protected_property_value($opts, 'options', $testData);

		$result = $opts->get_options();
		$this->assertEquals($testData, $result);
		$this->assertIsArray($result);
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::get_option
	 */
	public function test_get_option_key_sanitization(): void {
		$opts = RegisterOptions::site('test_options');

		// Store value under a simple key
		$this->_set_protected_property_value($opts, 'options', array('test_key' => 'original_value'));

		// Test retrieval with the same key (should work)
		$this->assertEquals('original_value', $opts->get_option('test_key'));

		// Test retrieval with a different key (should return default)
		$this->assertFalse($opts->get_option('different_key'));

		// Test with explicit default
		$this->assertEquals('custom_default', $opts->get_option('different_key', 'custom_default'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::has_option
	 */
	public function test_has_option_with_existing_key(): void {
		$opts = RegisterOptions::site('test_options');

		// Phase 4: schema required for stage_option keys
		$opts->with_schema(array('existing_key' => array('validate' => function ($v) {
			return is_string($v);
		})));
		// Add an option using stage_option (which doesn't require write gate mocking)
		$opts->stage_option('existing_key', 'value');

		// Test with the exact same key
		$this->assertTrue($opts->has_option('existing_key'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::has_option
	 */
	public function test_has_option_with_non_existing_key(): void {
		$opts = RegisterOptions::site('test_options');

		$this->assertFalse($opts->has_option('non_existing_key'));
		$this->assertFalse($opts->has_option('another_key'));
	}
}
