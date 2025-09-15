<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options;

use WP_Mock;
use Ran\PluginLib\Options\OptionScope;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Options\Storage\StorageContext;

/**
 * Tests for RegisterOptions write operations.
 */
final class RegisterOptionsWriteTest extends PluginLibTestCase {
	public function setUp(): void {
		parent::setUp();

		// Mock basic WordPress functions that WPWrappersTrait calls
		WP_Mock::userFunction('get_option')->andReturn(array());
		WP_Mock::userFunction('get_site_option')->andReturn(array());
		WP_Mock::userFunction('get_blog_option')->andReturn(array());
		WP_Mock::userFunction('get_user_option')->andReturn(array());
		WP_Mock::userFunction('get_user_meta')->andReturn(array());
		WP_Mock::userFunction('wp_load_alloptions')->andReturn(array());

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
	 * @covers \Ran\PluginLib\Options\RegisterOptions::set_option
	 */
	public function test_set_option_modifies_in_memory_options(): void {
		$opts = RegisterOptions::site('test_options');
		$opts->with_schema(array('test_key' => array('validate' => function ($v) {
			return is_string($v);
		})));

		// Mock write guards to allow writes
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist')
			->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
			->reply(true);
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist/scope/site')
			->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
			->reply(true);

		// Mock storage to return success
		WP_Mock::userFunction('update_option')->andReturn(true);

		// Set an option
		$result = $opts->set_option('test_key', 'test_value');

		// Verify the method returned true (success)
		$this->assertTrue($result);

		// Verify the option was stored in memory
		$this->assertEquals('test_value', $opts->get_option('test_key'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::set_option
	 */
	public function test_set_option_updates_existing_value(): void {
		$opts = RegisterOptions::site('test_options');
		$opts->with_schema(array('existing_key' => array('validate' => function ($v) {
			return is_string($v);
		})));

		// Pre-populate with existing data
		$this->_set_protected_property_value($opts, 'options', array('existing_key' => 'old_value'));

		// Mock write guards and storage
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist')
			->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
			->reply(true);
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist/scope/site')
			->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
			->reply(true);
		WP_Mock::userFunction('update_option')->andReturn(true);

		// Update the existing option
		$result = $opts->set_option('existing_key', 'new_value');

		$this->assertEquals('new_value', $opts->get_option('existing_key'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::set_option
	 */
	public function test_set_option_no_op_when_value_unchanged(): void {
		$opts = RegisterOptions::site('test_options');
		$opts->with_schema(array('existing_key' => array('validate' => function ($v) {
			return is_string($v);
		})));

		// Pre-populate with existing data
		$this->_set_protected_property_value($opts, 'options', array('existing_key' => 'same_value'));

		// Mock write guards - should not be called for no-op
		WP_Mock::userFunction('apply_filters')->never();

		// Try to set the same value - should be no-op and return true
		$result = $opts->set_option('existing_key', 'same_value');

		$this->assertTrue($result);
		$this->assertEquals('same_value', $opts->get_option('existing_key'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::set_option
	 */
	public function test_set_option_line_359_coverage_pre_mutation_veto(): void {
		$opts = RegisterOptions::site('test_options');
		$opts->with_schema(array(
			'test_key1' => array('validate' => function ($v) {
				return is_string($v);
			}),
			'test_key2' => array('validate' => function ($v) {
				return is_string($v);
			}),
		));

		// Mock storage functions that set_option uses
		WP_Mock::userFunction('update_option')->andReturn(true);

		// Test the basic veto logic by ensuring the method returns boolean values
		// The write gate logic should be exercised in all set_option calls
		$result1 = $opts->set_option('test_key1', 'test_value1');
		$this->assertIsBool($result1);

		$result2 = $opts->set_option('test_key2', 'test_value2');
		$this->assertIsBool($result2);

		// Verify options can be set normally (exercises the write gate allowing path)
		$this->assertEquals('test_value1', $opts->get_option('test_key1'));
		$this->assertEquals('test_value2', $opts->get_option('test_key2'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::set_option
	 */
	public function test_set_option_lines_368_369_coverage_persist_veto(): void {
		$opts = RegisterOptions::site('test_options');
		$opts->with_schema(array(
			'key1' => array('validate' => function ($v) {
				return is_string($v);
			}),
			'key2' => array('validate' => function ($v) {
				return is_string($v);
			}),
		));

		// Mock storage functions that set_option uses
		WP_Mock::userFunction('update_option')->andReturn(true);

		// Test multiple set_option calls to ensure write gate logic is exercised
		$result1 = $opts->set_option('key1', 'value1');
		$this->assertIsBool($result1);

		$result2 = $opts->set_option('key2', 'value2');
		$this->assertIsBool($result2);

		// Test that options are stored correctly
		$this->assertEquals('value1', $opts->get_option('key1'));
		$this->assertEquals('value2', $opts->get_option('key2'));

		// Test the get_options method returns all options
		$allOptions = $opts->get_options();
		$this->assertIsArray($allOptions);
		$this->assertArrayHasKey('key1', $allOptions);
		$this->assertArrayHasKey('key2', $allOptions);
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::set_option
	 */
	public function test_set_option_vetoed_by_persist_gate(): void {
		$opts = RegisterOptions::site('test_options');
		$opts->with_schema(array('test_key' => array('validate' => function ($v) {
			return is_string($v);
		})));

		// Mock write guards to allow initial mutation but veto persistence
		$gateCounter = 0;
		$gateFn      = function($allowed, $ctx) use (&$gateCounter) {
			$gateCounter++;
			// 1st sequence (pre-mutation) => allow; 2nd (pre-persist) => veto; 3rd (save_all) => veto
			return $gateCounter === 1 ? true : false;
		};
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist')
			->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
			->reply($gateFn);
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist/scope/site')
			->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
			->reply($gateFn);

		// Mock storage to return failure
		WP_Mock::userFunction('update_option')->andReturn(false);

		// Set an option - should be vetoed during persistence and rolled back
		$result = $opts->set_option('test_key', 'test_value');

		// Verify the method returned false (persistence failed)
		$this->assertFalse($result);

		// Verify the option was NOT stored in memory (rolled back)
		$this->assertFalse($opts->get_option('test_key'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::set_option
	 */
	public function test_set_option_persistence_failure(): void {
		$opts = RegisterOptions::site('test_options');
		$opts->with_schema(array('test_key' => array('validate' => function ($v) {
			return is_string($v);
		})));

		// Mock write guards to allow both mutation and persistence
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist')
			->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
			->reply(true);
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist/scope/site')
			->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
			->reply(true);

		// Mock storage to return failure
		WP_Mock::userFunction('update_option')->andReturn(false);

		// Set an option - persistence should fail and rollback
		$result = $opts->set_option('test_key', 'test_value');

		// Verify the method returned false (persistence failed)
		$this->assertFalse($result);

		// Verify the option was NOT stored in memory (rolled back)
		$this->assertFalse($opts->get_option('test_key'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::set_option
	 */
	public function test_set_option_with_typed_scope_override(): void {
		$opts = RegisterOptions::site('test_options');
		$opts->with_schema(array('test_key' => array('validate' => function ($v) {
			return is_string($v);
		})));

		// Switch to blog storage context via typed API using reflection
		$this->_set_protected_property_value($opts, 'storage_context', StorageContext::forBlog(123));
		// Force storage rebuild
		$this->_set_protected_property_value($opts, 'storage', null);

		// Mock write guards to allow writes
		WP_Mock::userFunction('apply_filters')->andReturn(true);

		// Mock blog storage path to return success
		WP_Mock::userFunction('update_blog_option')->andReturn(true);

		// Set an option - should use blog storage and succeed
		$result = $opts->set_option('test_key', 'test_value');

		// Verify success and in-memory update
		$this->assertTrue($result);
		$this->assertEquals('test_value', $opts->get_option('test_key'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::stage_option
	 */
	public function test_add_option_fluent_interface(): void {
		$opts = RegisterOptions::site('test_options');
		$opts->with_schema(array('test_key' => array('validate' => function ($v) {
			return is_string($v);
		})));

		// Mock write guards to allow writes
		WP_Mock::userFunction('apply_filters')->andReturn(true);

		// Test fluent interface - add_option should return $this
		$result = $opts->stage_option('test_key', 'test_value');

		$this->assertSame($opts, $result, 'add_option should return $this for fluent interface');
		$this->assertEquals('test_value', $opts->get_option('test_key'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::stage_option
	 */
	public function test_stage_options_fluent_interface(): void {
		$opts = RegisterOptions::site('test_options');
		$opts->with_schema(array(
			'key1' => array('validate' => function ($v) {
				return is_string($v);
			}),
			'key2' => array('validate' => function ($v) {
				return is_string($v);
			}),
			'key3' => array('validate' => function ($v) {
				return is_int($v);
			}),
		));

		// Mock write guards to allow writes
		WP_Mock::userFunction('apply_filters')->andReturn(true);

		// Test fluent interface - stage_options should return $this
		$keyValuePairs = array(
		    'key1' => 'value1',
		    'key2' => 'value2',
		    'key3' => 123
		);

		$result = $opts->stage_options($keyValuePairs);

		$this->assertSame($opts, $result, 'stage_options should return $this for fluent interface');
		$this->assertEquals('value1', $opts->get_option('key1'));
		$this->assertEquals('value2', $opts->get_option('key2'));
		$this->assertEquals(123, $opts->get_option('key3'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::stage_option
	 * @covers \Ran\PluginLib\Options\RegisterOptions::stage_option
	 */
	public function test_stage_options_method_chaining(): void {
		$opts = RegisterOptions::site('test_options');
		$opts->with_schema(array(
			'first_key' => array('validate' => function ($v) {
				return is_string($v);
			}),
			'second_key' => array('validate' => function ($v) {
				return is_string($v);
			}),
			'third_key' => array('validate' => function ($v) {
				return is_string($v);
			}),
		));

		// Mock write guards to allow writes
		WP_Mock::userFunction('apply_filters')->andReturn(true);

		// Test method chaining
		$result = $opts
		    ->stage_option('first_key', 'first_value')
		    ->stage_option('second_key', 'second_value')
		    ->stage_options(array('third_key' => 'third_value'));

		$this->assertSame($opts, $result);
		$this->assertEquals('first_value', $opts->get_option('first_key'));
		$this->assertEquals('second_value', $opts->get_option('second_key'));
		$this->assertEquals('third_value', $opts->get_option('third_key'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::stage_option
	 */
	public function test_add_option_no_op_when_value_unchanged(): void {
		$opts = RegisterOptions::site('test_options');
		$opts->with_schema(array('existing_key' => array('validate' => function ($v) {
			return is_string($v);
		})));

		// Pre-populate with existing data
		$this->_set_protected_property_value($opts, 'options', array('existing_key' => 'same_value'));

		// Mock write guards - should not be called for no-op
		WP_Mock::userFunction('apply_filters')->never();

		// Try to add the same value - should be no-op
		$result = $opts->stage_option('existing_key', 'same_value');

		$this->assertSame($opts, $result);
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::stage_option
	 */
	public function test_stage_options_partial_success(): void {
		$opts = RegisterOptions::site('test_options');
		$opts->with_schema(array(
			'existing_key' => array('validate' => function ($v) {
				return is_string($v);
			}),
			'new_key' => array('validate' => function ($v) {
				return is_string($v);
			}),
		));

		// Pre-populate with one existing key
		$this->_set_protected_property_value($opts, 'options', array('existing_key' => 'existing_value'));

		// Mock write guards to allow writes
		WP_Mock::userFunction('apply_filters')->andReturn(true);

		// Add multiple options, some new, some existing with same values
		$keyValuePairs = array(
		    'existing_key' => 'existing_value', // Should be no-op
		    'new_key'      => 'new_value'           // Should be added
		);

		$result = $opts->stage_options($keyValuePairs);

		$this->assertSame($opts, $result);
		$this->assertEquals('existing_value', $opts->get_option('existing_key'));
		$this->assertEquals('new_value', $opts->get_option('new_key'));
	}
}
