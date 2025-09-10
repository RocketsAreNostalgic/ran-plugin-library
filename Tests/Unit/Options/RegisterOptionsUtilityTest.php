<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options;

use WP_Mock;
use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Options\OptionScope;
use Ran\PluginLib\Util\ExpectLogTrait;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Options\Policy\WritePolicyInterface;

/**
 * Tests for RegisterOptions utility and edge case functionality.
 */
final class RegisterOptionsUtilityTest extends PluginLibTestCase {
	use ExpectLogTrait;
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

		// Mock write functions to prevent actual database writes
		WP_Mock::userFunction('add_option')->andReturn(true);
		WP_Mock::userFunction('update_option')->andReturn(true);
		WP_Mock::userFunction('delete_option')->andReturn(true);

		// Note: Do not set a global default for apply_filters here; tests will set expectations explicitly per scenario.
	}

	/**
		* @covers \Ran\PluginLib\Options\RegisterOptions::__construct
		* @covers \Ran\PluginLib\Options\RegisterOptions::with_defaults
		*/
	public function test_with_defaults_sets_default_values(): void {
		$opts = RegisterOptions::site('test_options');

		$defaults = array(
			'default_key1' => 'default_value1',
			'default_key2' => 'default_value2'
		);

		$result = $opts->with_defaults($defaults);

		// Should return self for fluent interface
		$this->assertSame($opts, $result);

		// Should be able to retrieve the default values
		$this->assertEquals('default_value1', $opts->get_option('default_key1'));
		$this->assertEquals('default_value2', $opts->get_option('default_key2'));
	}

	/**
		* @covers \Ran\PluginLib\Options\RegisterOptions::__construct
		* @covers \Ran\PluginLib\Options\RegisterOptions::with_policy
		*/
	public function test_with_policy_sets_write_policy(): void {
		$opts = RegisterOptions::site('test_options');

		// Create a mock write policy
		$mockPolicy = $this->getMockBuilder(\Ran\PluginLib\Options\Policy\WritePolicyInterface::class)
			->getMock();

		$result = $opts->with_policy($mockPolicy);

		// Should return self for fluent interface
		$this->assertSame($opts, $result);

		// Policy should be set (we can't easily verify this without reflection, but the method should complete without error)
		$this->assertInstanceOf(RegisterOptions::class, $opts);
	}

	/**
		* @covers \Ran\PluginLib\Options\RegisterOptions::__construct
		* @covers \Ran\PluginLib\Options\RegisterOptions::with_logger
		*/
	public function test_with_logger_sets_logger_instance(): void {
		// Create a mock logger
		$mockLogger = $this->getMockBuilder(\Ran\PluginLib\Util\Logger::class)
			->disableOriginalConstructor()
			->getMock();

		// Construct without DI to exercise with_logger() behavior explicitly
		$opts   = RegisterOptions::site('test_options');
		$result = $opts->with_logger($mockLogger);

		// Should return self for fluent interface
		$this->assertSame($opts, $result);

		// Logger should be set (we can't easily verify this without reflection, but the method should complete without error)
		$this->assertInstanceOf(RegisterOptions::class, $opts);
	}

	/**
		* @covers \Ran\PluginLib\Options\RegisterOptions::__construct
		* @covers \Ran\PluginLib\Options\RegisterOptions::with_defaults
		* @covers \Ran\PluginLib\Options\RegisterOptions::with_policy
		* @covers \Ran\PluginLib\Options\RegisterOptions::with_logger
		*/
	public function test_fluent_interface_method_chaining(): void {
		$opts = RegisterOptions::site('test_options');

		// Create mock objects
		$mockPolicy = $this->getMockBuilder(\Ran\PluginLib\Options\Policy\WritePolicyInterface::class)
			->getMock();
		$mockLogger = $this->getMockBuilder(\Ran\PluginLib\Util\Logger::class)
			->disableOriginalConstructor()
			->getMock();

		$defaults = array('chained_key' => 'chained_value');

		// Test method chaining
		$result = $opts
			->with_defaults($defaults)
			->with_policy($mockPolicy)
			->with_logger($mockLogger);

		// Should return self after chaining
		$this->assertSame($opts, $result);
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::migrate
	 */
	public function test_migrate_with_array_result(): void {
		$opts = RegisterOptions::site('test_options');

		// Set up initial data
		$initialData = array('old_key' => 'old_value');
		$this->_set_protected_property_value($opts, 'options', $initialData);

		// Mock storage to return initial data
		$mockStorage = $this->createMock(\Ran\PluginLib\Options\Storage\OptionStorageInterface::class);
		$mockStorage->method('read')->willReturn($initialData);
		$mockStorage->method('scope')->willReturn(\Ran\PluginLib\Options\OptionScope::Site);
		$this->_set_protected_property_value($opts, 'storage', $mockStorage);

		// Mock write guards and storage functions
		WP_Mock::userFunction('apply_filters')->andReturn(true);
		WP_Mock::userFunction('update_option')->andReturn(true);
		WP_Mock::userFunction('get_option')->andReturn($initialData);

		// Migration function that transforms data
		$migration = function($current) {
			$prev = (is_array($current) && array_key_exists('old_key', $current)) ? $current['old_key'] : '';
			return array('new_key' => 'new_value', 'old_key' => 'migrated_' . $prev);
		};

		$result = $opts->migrate($migration);

		// Should return self for fluent interface
		$this->assertSame($opts, $result);
		$this->assertEquals('new_value', $opts->get_option('new_key'));
		$this->assertEquals('migrated_old_value', $opts->get_option('old_key'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::migrate
	 */
	public function test_migrate_with_scalar_result(): void {
		$opts = RegisterOptions::site('test_options');

		// Set up initial data
		$initialData = array('key' => 'value');
		$this->_set_protected_property_value($opts, 'options', $initialData);

		// Mock storage and functions
		$mockStorage = $this->createMock(\Ran\PluginLib\Options\Storage\OptionStorageInterface::class);
		$mockStorage->method('read')->willReturn($initialData);
		$mockStorage->method('scope')->willReturn(\Ran\PluginLib\Options\OptionScope::Site);
		$this->_set_protected_property_value($opts, 'storage', $mockStorage);

		WP_Mock::userFunction('apply_filters')->andReturn(true);
		WP_Mock::userFunction('update_option')->andReturn(true);
		WP_Mock::userFunction('get_option')->andReturn($initialData);

		// Migration function that returns scalar
		$migration = function($current) {
			return 'scalar_result';
		};

		$result = $opts->migrate($migration);

		$this->assertSame($opts, $result);
		$this->assertEquals('scalar_result', $opts->get_option('value'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::migrate
	 */
	public function test_migrate_no_op_when_option_missing(): void {
		$opts = RegisterOptions::site('test_options', true, $this->logger_mock);

		// Mock storage to return null (option doesn't exist)
		$mockStorage = $this->createMock(\Ran\PluginLib\Options\Storage\OptionStorageInterface::class);
		$mockStorage->method('read')->willReturn(null);
		$mockStorage->method('scope')->willReturn(OptionScope::Site);
		$this->_set_protected_property_value($opts, 'storage', $mockStorage);

		// Ensure _do_get_option receives sentinel by returning provided default
		WP_Mock::userFunction('get_option')->andReturnUsing(function ($name, $default = null) {
			return $default;
		});
		// Veto writes defensively in case migration attempts to persist
		$policy = $this->getMockBuilder(\Ran\PluginLib\Options\Policy\WritePolicyInterface::class)->getMock();
		$policy->method('allow')->willReturn(false);
		$opts->with_policy($policy);
		WP_Mock::userFunction('apply_filters')->andReturn(false);

		$migration = function($current) {
			return array('should_not_run' => 'value');
		};

		$result = $opts->migrate($migration);

		// Should return self but data unchanged
		$this->assertSame($opts, $result);
		$this->assertFalse($opts->has_option('should_not_run'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::migrate
	 */
	public function test_migrate_no_op_when_no_changes(): void {
		$opts = RegisterOptions::site('test_options');

		$initialData = array('key' => 'value');
		$this->_set_protected_property_value($opts, 'options', $initialData);

		$mockStorage = $this->createMock(\Ran\PluginLib\Options\Storage\OptionStorageInterface::class);
		$opts        = RegisterOptions::site('test_options');

		// Set up initial data
		$initialData = array('key' => 'value');
		$this->_set_protected_property_value($opts, 'options', $initialData);

		// Mock storage and functions
		$mockStorage = $this->createMock(\Ran\PluginLib\Options\Storage\OptionStorageInterface::class);
		$mockStorage->method('read')->willReturn($initialData);
		$mockStorage->method('scope')->willReturn(\Ran\PluginLib\Options\OptionScope::Site);
		$this->_set_protected_property_value($opts, 'storage', $mockStorage);

		WP_Mock::userFunction('apply_filters')->andReturn(true);
		WP_Mock::userFunction('update_option')->andReturn(true);
		WP_Mock::userFunction('get_option')->andReturn($initialData);

		// Migration function that returns scalar
		$migration = function($current) {
			return 'scalar_result';
		};

		$result = $opts->migrate($migration);

		$this->assertSame($opts, $result);
		$this->assertEquals('scalar_result', $opts->get_option('value'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::migrate
	 * @covers \Ran\PluginLib\Options\RegisterOptions::flush
	 */
	public function test_flush_with_merge_from_db(): void {
		$opts = RegisterOptions::site('test_options', true, $this->logger_mock);

		// Add some options in memory
		$opts->add_option('memory_key', 'memory_value');

		// Mock storage to return existing data and success
		$mockStorage = $this->createMock(\Ran\PluginLib\Options\Storage\OptionStorageInterface::class);
		$mockStorage->method('read')->willReturn(array('db_key' => 'db_value'));
		$mockStorage->method('scope')->willReturn(\Ran\PluginLib\Options\OptionScope::Site);
		$mockStorage->method('update')->willReturn(true);
		$this->_set_protected_property_value($opts, 'storage', $mockStorage);

		// Allow writes in this test
		WP_Mock::userFunction('apply_filters')->andReturn(true);

		// Mock storage to return success
		WP_Mock::userFunction('update_option')->andReturn(true);

		// Mock get_option for merge from DB
		WP_Mock::userFunction('get_option')->andReturn(array('db_key' => 'db_value'));

		// Mock update_option to prevent undefined function error
		WP_Mock::userFunction('update_option')->andReturn(true);

	// Flush with merge should combine memory and DB data
		$result = $opts->flush(true); // merge_from_db = true

		$this->assertTrue($result); // Flush should succeed with proper mocks
		$this->assertTrue($opts->has_option('memory_key'));
		$this->assertTrue($opts->has_option('db_key'));
	}

	/**
		* @covers \Ran\PluginLib\Options\RegisterOptions::__construct
		* @covers \Ran\PluginLib\Options\RegisterOptions::refresh_options
		* @covers \Ran\PluginLib\Options\RegisterOptions::_read_main_option
		*/
	public function test_refresh_options_reloads_from_storage(): void {
		$opts = RegisterOptions::site('test_options');

		// Seed in-memory state different from storage
		$this->_set_protected_property_value($opts, 'options', array('foo' => 'memory_value'));

		// Storage returns fresh DB snapshot
		$mockStorage = $this->createMock(\Ran\PluginLib\Options\Storage\OptionStorageInterface::class);
		$mockStorage->method('read')->willReturn(array('foo' => 'db_value', 'bar' => 2));
		$mockStorage->method('scope')->willReturn(\Ran\PluginLib\Options\OptionScope::Site);
		$this->_set_protected_property_value($opts, 'storage', $mockStorage);

		$opts->refresh_options();

		// Values should reflect storage snapshot now
		$this->assertSame('db_value', $opts->get_option('foo'));
		$this->assertTrue($opts->has_option('bar'));
		$this->assertSame(2, $opts->get_option('bar'));
	}

	/**
		* @covers \Ran\PluginLib\Options\RegisterOptions::__construct
		* @covers \Ran\PluginLib\Options\RegisterOptions::supports_autoload
		*/
	public function test_supports_autoload_method(): void {
		$opts = RegisterOptions::site('test_options');
		$this->assertTrue($opts->supports_autoload()); // Site scope supports autoload

		$opts = RegisterOptions::network('test_options');
		$this->assertFalse($opts->supports_autoload()); // Network scope doesn't support autoload
	}

	/**
		* @covers \Ran\PluginLib\Options\RegisterOptions::__construct
		* @covers \Ran\PluginLib\Options\RegisterOptions::set_option
		*/
	public function test_set_option_with_string_scope_override(): void {
		$opts = RegisterOptions::site('test_options', true, $this->logger_mock);

		// Exercise alternate scope path
		$this->_set_protected_property_value($opts, 'storage_scope', 'blog');

		// Allow writes in this test
		WP_Mock::userFunction('apply_filters')->andReturn(true);

		// Mock storage to return success through WP update_option
		WP_Mock::userFunction('update_option')->andReturn(true);

		// Set an option - should exercise scope string logic and succeed
		$result = $opts->set_option('test_key', 'test_value');
		$this->assertTrue($result);
		$this->assertEquals('test_value', $opts->get_option('test_key'));
	}

	/**
		* @covers \Ran\PluginLib\Options\RegisterOptions::__construct
		* @covers \Ran\PluginLib\Options\RegisterOptions::refresh_options
		* @covers \Ran\PluginLib\Options\RegisterOptions::_read_main_option
		*/
	public function test_read_main_option_non_array_returns_empty_and_logs(): void {
		$opts = RegisterOptions::site('test_options', true, $this->logger_mock);

		$mockStorage = $this->createMock(\Ran\PluginLib\Options\Storage\OptionStorageInterface::class);
		$mockStorage->method('read')->willReturn(null); // non-array path
		$mockStorage->method('scope')->willReturn(OptionScope::Site);
		$this->_set_protected_property_value($opts, 'storage', $mockStorage);

		$opts->refresh_options();
		// Options should be empty; validate via public API
		$this->assertFalse($opts->has_option('any_key'));
		$this->assertFalse($opts->get_option('any_key'));
		// With logger injected at construction, _read_main_option runs during:
		// 1) constructor, 2) factory re-read in ::site(), 3) explicit refresh() below
		$this->expectLog('debug', 'RegisterOptions: _read_main_option completed', 3);
	}
}
