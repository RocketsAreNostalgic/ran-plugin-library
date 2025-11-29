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
		$opts = RegisterOptions::site('test_options', true, $this->logger_mock);

		// Allow all writes for this test
		$policy = $this->getMockBuilder(\Ran\PluginLib\Options\Policy\WritePolicyInterface::class)->getMock();
		$policy->method('allow')->willReturn(true);
		$opts->with_policy($policy);

		$schema = array(
		    'test_key' => array(
		        'default'  => 'default_value',
		        'sanitize' => static fn ($value) => $value,
		        'validate' => static fn ($value) => true,
		    ),
		);

		$result = $opts->register_schema($schema);

		// Should return a boolean (true if changes made, false if no changes)
		$this->assertIsBool($result);

		$registeredSchema = $opts->_get_schema_internal();
		$this->assertArrayHasKey('test_key', $registeredSchema);
		$this->assertSame(array(), $registeredSchema['test_key']['sanitize']['component']);
		$this->assertCount(1, $registeredSchema['test_key']['sanitize']['schema']);
		$this->assertSame(array(), $registeredSchema['test_key']['validate']['component']);
		$this->assertCount(1, $registeredSchema['test_key']['validate']['schema']);

		// Verify schema was registered by checking if we can set an option
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist')
			->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
			->reply(true);
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist/scope/site')
			->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
			->reply(true);
		WP_Mock::userFunction('update_option')->andReturn(true);

		$result = $opts->stage_option('test_key', 'test_value')->commit_merge();
		$this->assertTrue($result);
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::register_schema
	 */
	public function test_register_schema_empty_returns_false(): void {
		$opts = RegisterOptions::site('test_options', true, $this->logger_mock);

		$result = $opts->register_schema(array());

		$this->assertFalse($result);
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::register_schema
	 */
	public function test_register_schema_merges_existing_keys(): void {
		$opts = RegisterOptions::site('test_options', true, $this->logger_mock);

		// Ensure policy allows schema mutations for this test
		$policy = $this->getMockBuilder(\Ran\PluginLib\Options\Policy\WritePolicyInterface::class)->getMock();
		$policy->method('allow')->willReturn(true);
		$opts->with_policy($policy);

		// First register a schema with some keys
		$initialSchema = array(
		    'existing_key' => array(
		        'default'  => 'initial_default',
		        'sanitize' => static fn ($value) => $value,
		        'validate' => static fn ($value): bool => is_string($value),
		    ),
		);

		$opts->register_schema($initialSchema);
		$initialMap = $opts->_get_schema_internal();
		$this->assertSame(array(), $initialMap['existing_key']['sanitize']['component']);
		$this->assertCount(1, $initialMap['existing_key']['sanitize']['schema']);
		$this->assertSame(array(), $initialMap['existing_key']['validate']['component']);
		$this->assertCount(1, $initialMap['existing_key']['validate']['schema']);

		// Now register a second schema that updates the existing key (lines 529-534)
		$updateSchema = array(
		    'existing_key' => array(
		        'default'  => 'updated_default',
		        'sanitize' => null,
		        'validate' => static fn ($v): bool => is_string($v),
		    ),
		);

		$result = $opts->register_schema($updateSchema);

		// Should return a boolean (true if changes made, false if no changes)
		$this->assertIsBool($result);

		// Verify the schema merging worked (default remains from first registration) and buckets intact
		$this->assertEquals('initial_default', $opts->get_option('existing_key'));
		$mergedMap = $opts->_get_schema_internal();
		$this->assertSame(array(), $mergedMap['existing_key']['sanitize']['component']);
		$this->assertCount(1, $mergedMap['existing_key']['sanitize']['schema']);
		$this->assertSame(array(), $mergedMap['existing_key']['validate']['component']);
		$this->assertCount(2, $mergedMap['existing_key']['validate']['schema']);
		$this->assertSame(
			$initialMap['existing_key']['validate']['schema'][0],
			$mergedMap['existing_key']['validate']['schema'][0]
		);
		$this->assertInstanceOf(
			\Closure::class,
			$mergedMap['existing_key']['validate']['schema'][1]
		);
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::with_schema
	 */
	public function test_with_schema_fluent_interface(): void {
		$opts = RegisterOptions::site('test_options', true, $this->logger_mock);

		$schema = array(
			    'fluent_key' => array(
				'default'  => 'fluent_value',
				'validate' => function ($v) {
					return is_string($v);
				}
			    )
		);

		$result = $opts->with_schema($schema);

		$this->assertSame($opts, $result); // Should return self for fluent interface
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::register_schema
	 */
	public function test_register_schema_with_flush_and_changed(): void {
		$opts = RegisterOptions::site('test_options', true, $this->logger_mock);

		// Allow all writes for this test
		$policy = $this->getMockBuilder(\Ran\PluginLib\Options\Policy\WritePolicyInterface::class)->getMock();
		$policy->method('allow')->willReturn(true);
		$opts->with_policy($policy);

		$schema = array(
		    'flush_key' => array(
		        'default'  => 'flush_value',
		        'sanitize' => static fn ($value) => $value,
		        'validate' => static fn ($value) => true,
		    ),
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

		// Option A: register_schema seeds/normalizes in-memory, no implicit flush
		$result = $opts->register_schema($schema);
		$this->assertTrue($result); // changed in memory
		// Persist explicitly
		$this->assertTrue($opts->commit_replace());
		// Verify the option was persisted
		$this->assertEquals('flush_value', $opts->get_option('flush_key'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::register_schema
	 */
	public function test_register_schema_seed_defaults_exception_handling(): void {
		$opts = RegisterOptions::site('test_options', true, $this->logger_mock);

		// Create a validation function that throws an exception
		$exceptionValidator = function($value) {
			throw new \RuntimeException('Test validation exception during seeding');
		};

		$schema = array(
		    'exception_key' => array(
		        'default'  => 'test_value',
		        'sanitize' => static fn ($value) => $value,
		        'validate' => $exceptionValidator,
		    )
		);

		// Under strict fail-fast: register_schema should throw; ensure no mutation occurs
		$this->expectException(\RuntimeException::class);
		try {
			$opts->register_schema($schema);
		} finally {
			// Verify that the option was not set due to the exception
			$this->assertFalse($opts->has_option('exception_key'));
		}
	}

	/**
	 * Ensure register_schema normalizes existing in-memory values and flags changes.
	 *
	 * Targets the branch where sanitized value differs from current in-memory value,
	 * updating the in-memory option and setting $changed = true (lines 419–420).
	 *
	 * @covers \Ran\PluginLib\Options\RegisterOptions::register_schema
	 */
	public function test_register_schema_normalizes_existing_values_and_sets_changed_true(): void {
		$opts = RegisterOptions::site('test_options', true, $this->logger_mock);

		// Pre-populate in-memory options with values that will be normalized by schema.
		$this->_set_protected_property_value($opts, 'options', array(
			'normalize_me' => '  Hello  ', // will become 'Hello'
			'num'          => '5',         // will become 5 (int)
		));

		$schema = array(
		    'normalize_me' => array(
		        'default'  => null,
		        'sanitize' => static fn ($v) => trim((string) $v),
		        'validate' => static fn ($v): bool => is_string($v),
		    ),
		    'num' => array(
		        'default'  => null,
		        'sanitize' => static fn ($v) => (int) $v,
		        'validate' => static fn ($v): bool => is_int($v),
		    ),
		);

		// Ensure policy allows schema mutations for this test
		$policy = $this->getMockBuilder(\Ran\PluginLib\Options\Policy\WritePolicyInterface::class)->getMock();
		$policy->method('allow')->willReturn(true);
		$opts->with_policy($policy);

		$changed = $opts->register_schema($schema);

		// Expect changes due to normalization.
		$this->assertTrue($changed);
		$this->assertSame('Hello', $opts->get_option('normalize_me'));
		$this->assertSame(5, $opts->get_option('num'));

		$normalizedMap = $opts->_get_schema_internal();
		$this->assertSame(array(), $normalizedMap['normalize_me']['sanitize']['component']);
		$this->assertCount(1, $normalizedMap['normalize_me']['sanitize']['schema']);
		$this->assertSame(array(), $normalizedMap['normalize_me']['validate']['component']);
		$this->assertCount(1, $normalizedMap['normalize_me']['validate']['schema']);
	}
}
