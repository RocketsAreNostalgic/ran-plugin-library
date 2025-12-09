<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options;

use WP_Mock;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;

/**
 * Tests for RegisterOptions delete operations.
 */
final class RegisterOptionsDeleteTest extends PluginLibTestCase {
	public function setUp(): void {
		parent::setUp();

		// Mock basic WordPress functions that WPWrappersTrait calls
		WP_Mock::userFunction('get_option')->andReturn(array())->byDefault();
		WP_Mock::userFunction('get_site_option')->andReturn(array())->byDefault();
		WP_Mock::userFunction('get_blog_option')->andReturn(array())->byDefault();
		WP_Mock::userFunction('get_user_option')->andReturn(array())->byDefault();
		WP_Mock::userFunction('get_user_meta')->andReturn(array())->byDefault();
		WP_Mock::userFunction('wp_load_alloptions')->andReturn(array())->byDefault();
		WP_Mock::userFunction('current_user_can')->andReturn(true)->byDefault();
		// Ensure apply_filters passthrough and default allow for write-gate in this suite
		WP_Mock::userFunction('apply_filters')->andReturnUsing(function($hook,$value) {
			return $value;
		});
		\WP_Mock::onFilter('ran/plugin_lib/options/allow_persist')
			->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
			->reply(true);
		\WP_Mock::onFilter('ran/plugin_lib/options/allow_persist/scope/site')
			->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
			->reply(true);

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
	 * @covers \Ran\PluginLib\Options\RegisterOptions::delete_option
	 */
	public function test_delete_option_existing_key(): void {
		$opts = RegisterOptions::site('test_options', true, $this->logger_mock);

		// Pre-populate with data using properly sanitized keys
		$this->_set_protected_property_value($opts, 'options', array('key_to_delete' => 'value', 'keep_key' => 'keep_value'));

		// Mock write guards to allow deletion
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist')
			->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
			->reply(true);
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist/scope/site')
			->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
			->reply(true);

		// Mock storage to return success
		WP_Mock::userFunction('delete_option')->andReturn(true);
		WP_Mock::userFunction('update_option')->andReturn(true);

		// Delete should return true and remove from memory
		$result = $opts->delete_option('key_to_delete');

		// Verify the method returned true (successful deletion)
		$this->assertTrue($result);
		$this->assertFalse($opts->has_option('key_to_delete'));
		$this->assertTrue($opts->has_option('keep_key')); // Other keys should remain
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::delete_option
	 */
	public function test_delete_option_non_existing_key(): void {
		$opts = RegisterOptions::site('test_options', true, $this->logger_mock);

		// Mock write guards to allow deletion
		WP_Mock::userFunction('apply_filters')->andReturn(true);

		// Delete should return false for non-existing key
		$result = $opts->delete_option('non_existing_key');

		$this->assertFalse($result);
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::clear
	 */
	public function test_clear_removes_all_options(): void {
		$opts = RegisterOptions::site('test_options', true, $this->logger_mock);

		// Pre-populate with data using properly sanitized keys
		$this->_set_protected_property_value($opts, 'options', array('key1' => 'value1', 'key2' => 'value2'));

		// Mock write guards to allow clearing
		WP_Mock::userFunction('apply_filters')->andReturn(true);

		// Mock storage to return success
		WP_Mock::userFunction('delete_option')->andReturn(true);
		WP_Mock::userFunction('update_option')->andReturn(true);

		// Clear should return true and empty options
		$result = $opts->clear();

		// Verify the method returned true (successful clearing)
		// Mock write guards to allow deletion
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist')
			->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
			->reply(true);
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist/scope/site')
			->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
			->reply(true);
		$this->assertFalse($opts->has_option('key2'));
		$this->assertEquals(array(), $opts->get_options());
	}
}
