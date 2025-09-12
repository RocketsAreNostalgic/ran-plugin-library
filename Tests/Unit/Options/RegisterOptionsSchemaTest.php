<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options;

use WP_Mock;
use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;

/**
 * Tests for RegisterOptions schema management.
 */
final class RegisterOptionsSchemaTest extends PluginLibTestCase {
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
	 * @covers \Ran\PluginLib\Options\RegisterOptions::register_schema
	 */
	public function test_register_schema_basic(): void {
		$opts = RegisterOptions::site('test_options');

		// Allow all writes for this test
		$policy = $this->getMockBuilder(\Ran\PluginLib\Options\Policy\WritePolicyInterface::class)->getMock();
		$policy->method('allow')->willReturn(true);
		$opts->with_policy($policy);

		$schema = array(
		    'test_key' => array(
		        'default'  => 'default_value',
		        'sanitize' => function($value) {
		        	return $value;
		        },
		        'validate' => function($value) {
		        	return true;
		        }
		    )
		);

		$result = $opts->register_schema($schema);

		// Should return a boolean (true if changes made, false if no changes)
		$this->assertIsBool($result);

		// Verify schema was registered by checking if we can set an option
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist')
			->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
			->reply(true);
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist/scope/site')
			->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
			->reply(true);
		WP_Mock::userFunction('update_option')->andReturn(true);

		$result = $opts->set_option('test_key', 'test_value');
		$this->assertTrue($result);
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::register_schema
	 */
	public function test_register_schema_empty_returns_false(): void {
		$opts = RegisterOptions::site('test_options');

		$result = $opts->register_schema(array());

		$this->assertFalse($result);
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::register_schema
	 */
	public function test_register_schema_merges_existing_keys(): void {
		$opts = RegisterOptions::site('test_options');

		// First register a schema with some keys
		$initialSchema = array(
		    'existing_key' => array(
		        'default'  => 'initial_default',
		        'sanitize' => function($value) {
		        	return $value;
		        },
		        'validate' => function($value) {
		        	return true;
		        }
		    )
		);

		$opts->register_schema($initialSchema);

		// Now register a second schema that updates the existing key (lines 529-534)
		$updateSchema = array(
		    'existing_key' => array(
		        'default'  => 'updated_default', // This should override
		        'sanitize' => null, // This should clear the sanitizer
		        // validate not provided, should preserve existing
		    )
		);

		$result = $opts->register_schema($updateSchema, true); // Enable seeding to trigger lines 529-534

		// Should return a boolean (true if changes made, false if no changes)
		$this->assertIsBool($result);

		// Verify the schema merging worked by checking that the updated default was seeded
		// Since we enabled seeding, the updated default should be in the options
		$this->assertEquals('updated_default', $opts->get_option('existing_key')); // Should use updated default
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::with_schema
	 */
	public function test_with_schema_fluent_interface(): void {
		$opts = RegisterOptions::site('test_options');

		$schema = array(
		    'fluent_key' => array(
		        'default' => 'fluent_value'
		    )
		);

		$result = $opts->with_schema($schema);

		$this->assertSame($opts, $result); // Should return self for fluent interface
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::register_schema
	 */
	public function test_register_schema_with_flush_and_changed(): void {
		$opts = RegisterOptions::site('test_options');

		// Allow all writes for this test
		$policy = $this->getMockBuilder(\Ran\PluginLib\Options\Policy\WritePolicyInterface::class)->getMock();
		$policy->method('allow')->willReturn(true);
		$opts->with_policy($policy);

		$schema = array(
		    'flush_key' => array(
		        'default'  => 'flush_value',
		        'sanitize' => function($value) {
		        	return $value;
		        },
		        'validate' => function($value) {
		        	return true;
		        }
		    )
		);

		// Mock write guards to allow writes
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist')
			->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
			->reply(true);
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist/scope/site')
			->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
			->reply(true);

		// Mock storage to return success
		WP_Mock::userFunction('update_option')->andReturn(true);

		// Call register_schema with both seed_defaults=true and flush=true
		// This should trigger the condition at line 577: if ($flush && $changed)
		$result = $opts->register_schema($schema, true, true); // seed_defaults=true, flush=true

		// Should return the result of _save_all_options() (true on success)
		$this->assertTrue($result);

		// Verify the option was seeded and flushed
		$this->assertEquals('flush_value', $opts->get_option('flush_key'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::register_schema
	 */
	public function test_register_schema_seed_defaults_exception_handling(): void {
		$opts = RegisterOptions::site('test_options');

		// Create a validation function that throws an exception
		$exceptionValidator = function($value) {
			throw new \RuntimeException('Test validation exception during seeding');
		};

		$schema = array(
		    'exception_key' => array(
		        'default'  => 'test_value',
		        'sanitize' => function($value) {
		        	return $value;
		        },
		        'validate' => $exceptionValidator // This will throw during seeding
		    )
		);

		// Register schema with seeding enabled - this should trigger the catch block at lines 554-564
		$result = $opts->register_schema($schema, true); // seed_defaults = true

		// Should return false due to exception during seeding
		$this->assertFalse($result);

		// Verify that the option was not set due to the exception
		$this->assertFalse($opts->has_option('exception_key'));
	}
}
