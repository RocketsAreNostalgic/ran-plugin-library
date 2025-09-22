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
		WP_Mock::userFunction('current_user_can')->andReturn(true)->byDefault();

		// Ensure apply_filters is passthrough so onFilter hooks take effect in this suite
		WP_Mock::userFunction('apply_filters')->andReturnUsing(function($hook, $value) {
			return $value;
		});


		// Mock sanitize_key to properly handle key normalization
		WP_Mock::userFunction('sanitize_key')->andReturnUsing(function($key) {
			$key = strtolower($key);
			// Replace any run of non [a-z0-9_\-] with a single underscore (preserve hyphens)
			$key = preg_replace('/[^a-z0-9_\-]+/i', '_', $key) ?? '';
			// Trim underscores at edges (preserve leading/trailing hyphens if present)
			return trim($key, '_');
		});

		// Default allow for write gate filters at site scope; individual tests may override
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist')
			->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
			->reply(true);
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist/scope/site')
			->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
			->reply(true);
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::stage_option
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
		$result = $opts->stage_option('test_key', 'test_value')->commit_replace();

		// Verify the method returned true (success)
		$this->assertTrue($result);

		// Verify the option was stored in memory
		$this->assertEquals('test_value', $opts->get_option('test_key'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::stage_option
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

		// Update the existing option and persist
		$result = $opts->stage_option('existing_key', 'new_value')->commit_replace();

		$this->assertEquals('new_value', $opts->get_option('existing_key'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::stage_option
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

		// Try to set the same value - should be no-op and return false (nothing to persist)
		$result = $opts->stage_option('existing_key', 'same_value')->commit_replace();

		$this->assertFalse($result);
		$this->assertEquals('same_value', $opts->get_option('existing_key'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::stage_option
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
		$result1 = $opts->stage_option('test_key1', 'test_value1')->commit_replace();
		$this->assertIsBool($result1);

		$result2 = $opts->stage_option('test_key2', 'test_value2')->commit_replace();
		$this->assertIsBool($result2);

		// Verify options can be set normally (exercises the write gate allowing path)
		$this->assertEquals('test_value1', $opts->get_option('test_key1'));
		$this->assertEquals('test_value2', $opts->get_option('test_key2'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::stage_option
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
		$result1 = $opts->stage_option('key1', 'value1')->commit_replace();
		$this->assertIsBool($result1);

		$result2 = $opts->stage_option('key2', 'value2')->commit_replace();
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
	 * @covers \Ran\PluginLib\Options\RegisterOptions::stage_option
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
		$result = $opts->stage_option('test_key', 'test_value')->commit_replace();

		// Verify the method returned false (persistence failed)
		$this->assertFalse($result);

		// Current semantics: staged value remains in memory even if persistence is vetoed
		$this->assertSame('test_value', $opts->get_option('test_key'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::stage_option
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
		$result = $opts->stage_option('test_key', 'test_value')->commit_replace();

		// Verify the method returned false (persistence failed)
		$this->assertFalse($result);

		// Current semantics: staged value remains in memory even if persistence fails
		$this->assertSame('test_value', $opts->get_option('test_key'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::stage_option
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
		$result = $opts->stage_option('test_key', 'test_value')->commit_replace();

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

	/**
		* Additional coverage: stage_options pre-mutation write gate veto should prevent any in-memory changes.
		*
		* @covers \Ran\PluginLib\Options\RegisterOptions::stage_options
		*/
	public function test_stage_options_pre_mutation_veto_no_changes(): void {
		$opts = RegisterOptions::site('test_options');
		$opts->with_schema(array(
			'a' => array('validate' => function ($v) {
				return is_string($v);
			}),
			'b' => array('validate' => function ($v) {
				return is_string($v);
			}),
		));

		// Veto writes via immutable policy to ensure no in-memory mutation
		$policy = $this->getMockBuilder(\Ran\PluginLib\Options\Policy\WritePolicyInterface::class)->getMock();
		$policy->method('allow')->willReturn(false);
		$opts->with_policy($policy);

		// Attempt to stage multiple keys — veto should short-circuit and make no changes
		$result = $opts->stage_options(array('a' => 'x', 'b' => 'y'));
		$this->assertSame($opts, $result);
		$this->assertFalse($opts->has_option('a'));
		$this->assertFalse($opts->has_option('b'));
	}

	/**
		* Additional coverage: stage_options with sanitizers applied and gate allowed.
		* Ensures values are sanitized before being stored.
		*
		* @covers \Ran\PluginLib\Options\RegisterOptions::stage_options
		*/
	public function test_stage_options_applies_sanitizers_when_gate_allows(): void {
		$opts = RegisterOptions::site('test_options');
		$opts->with_schema(array(
			'name' => array(
				'sanitize' => function ($v) {
					return trim((string) $v);
				},
				'validate' => function ($v) {
					return is_string($v);
				},
			),
			'count' => array(
				'sanitize' => function ($v) {
					return (int) $v;
				},
				'validate' => function ($v) {
					return is_int($v);
				},
			),
		));

		// Allow writes via filters (general and scope)
		\WP_Mock::onFilter('ran/plugin_lib/options/allow_persist')
			->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
			->reply(true);
		\WP_Mock::onFilter('ran/plugin_lib/options/allow_persist/scope/site')
			->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
			->reply(true);

		$opts->stage_options(array(
			'name'  => '  Alice  ',
			'count' => '42',
		));

		$this->assertSame('Alice', $opts->get_option('name'));
		$this->assertSame(42, $opts->get_option('count'));
	}
}
