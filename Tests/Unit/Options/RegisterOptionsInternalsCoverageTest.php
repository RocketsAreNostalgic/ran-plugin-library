<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options;

use WP_Mock;
use Ran\PluginLib\Options\OptionScope;
use Ran\PluginLib\Util\ExpectLogTrait;
use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Options\Storage\StorageContext;

/**
 * @covers \Ran\PluginLib\Options\RegisterOptions::__construct
 * @covers \Ran\PluginLib\Options\RegisterOptions::refresh_options
 * @covers \Ran\PluginLib\Options\RegisterOptions::commit_replace
 * @covers \Ran\PluginLib\Options\RegisterOptions::set_option
 * @covers \Ran\PluginLib\Options\RegisterOptions::register_schema
 * @uses \Ran\PluginLib\Config\ConfigInterface
 * @uses \Ran\PluginLib\Options\RegisterOptions::__construct
 * to increase coverage for schema, write gate, storage lifecycle, and error formatting.
 */
final class RegisterOptionsInternalsCoverageTest extends PluginLibTestCase {
	use ExpectLogTrait;
	public function setUp(): void {
		parent::setUp();

		// Common WP functions used by wrappers
		WP_Mock::userFunction('get_option')->andReturn(array())->byDefault();
		WP_Mock::userFunction('get_site_option')->andReturn(array())->byDefault();
		WP_Mock::userFunction('get_blog_option')->andReturn(array())->byDefault();
		WP_Mock::userFunction('get_user_option')->andReturn(array())->byDefault();
		WP_Mock::userFunction('get_user_meta')->andReturn(array())->byDefault();
		WP_Mock::userFunction('wp_load_alloptions')->andReturn(array())->byDefault();

		// Key normalization consistent with other tests
		WP_Mock::userFunction('sanitize_key')->andReturnUsing(function ($key) {
			$key = strtolower((string) $key);
			$key = preg_replace('/[^a-z0-9_\-]+/i', '_', $key) ?? '';
			return trim($key, '_');
		});

		// Default write functions
		WP_Mock::userFunction('add_option')->andReturn(true);
		WP_Mock::userFunction('update_option')->andReturn(true);
		WP_Mock::userFunction('delete_option')->andReturn(true);
	}

	private function allow_all_persist_filters_for_site(): void {
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist')
			->with(WP_Mock\Functions::type('bool'), WP_Mock\Functions::type('array'))
			->reply(true);
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist/scope/site')
			->with(WP_Mock\Functions::type('bool'), WP_Mock\Functions::type('array'))
			->reply(true);
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::register_schema
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_normalize_schema_keys
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_resolve_default_value
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_sanitize_and_validate_option
	 */
	public function test_register_schema_seeds_and_sanitizes_and_validates(): void {
		$config = $this->getMockBuilder(ConfigInterface::class)->getMock();
		$config->method('get_options_key')->willReturn('test_options');
		$config->method('get_logger')->willReturn($this->logger_mock);
		$opts = RegisterOptions::from_config($config, StorageContext::forSite(), true);

		$schema = array(
			'num' => array(
				// default via callable to exercise _resolve_default_value
				'default' => function () {
					return '5';
				},
				// sanitize coerces to integer
				'sanitize' => function ($v) {
					return (int) $v;
				},
				// validate must be > 0
				'validate' => function ($v) {
					return $v > 0;
				},
			),
			'label' => array(
				'default'  => '  Hello  ',
				'sanitize' => function ($v) {
					return trim((string)$v);
				},
				'validate' => function ($v) {
					return is_string($v);
				},
			),
		);

		// Register schema (Option A seeds defaults and normalizes in-memory by default)
		$changed = $opts->register_schema($schema);
		$this->assertTrue($changed);
		$this->assertSame(5, $opts->get_option('num'));
		$this->assertSame('Hello', $opts->get_option('label'));
		// Assert logs after SUT ran
		$this->expectLog('debug', "Getting option 'num'");
		$this->expectLog('debug', "Getting option 'label'");
		$this->expectLog('debug', '_normalize_schema_keys completed');
		$this->expectLog('debug', '_resolve_default_value resolved callable', 1);
		// Under Option A, seeding and normalization can trigger sanitization multiple times
		$this->expectLog('debug', '_sanitize_and_validate_option completed', 4);
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::set_option
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_sanitize_and_validate_option
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_stringify_value_for_error
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_describe_callable
	 */
	public function test_validation_failure_throws_and_formats_error(): void {
		$config = $this->getMockBuilder(ConfigInterface::class)->getMock();
		$config->method('get_options_key')->willReturn('test_options');
		$config->method('get_logger')->willReturn($this->logger_mock);
		$opts = RegisterOptions::from_config($config, StorageContext::forSite(), true);

		$schema = array(
			'age' => array(
				'sanitize' => function ($v) {
					return (int) $v;
				},
				'validate' => function ($v) {
					return $v >= 18;
				},
			),
		);
		$opts->register_schema($schema);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessageMatches('/Validation failed/');
		$opts->set_option('age', 10); // should fail validate, triggering _stringify_value_for_error + _describe_callable
		// End-of-method logs produced during failure path
		$this->expectLog('debug', '_stringify_value_for_error completed');
		$this->expectLog('debug', '_describe_callable completed');
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::commit_replace
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_save_all_options
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_apply_write_gate
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_get_storage
	 */
	public function test_commit_replace_triggers_storage_update_and_save_all_options(): void {
		$config = $this->getMockBuilder(ConfigInterface::class)->getMock();
		$config->method('get_options_key')->willReturn('test_options');
		$config->method('get_logger')->willReturn($this->logger_mock);
		$opts = RegisterOptions::from_config($config, StorageContext::forSite(), true);
		// Allow all writes for this test
		$policy = $this->getMockBuilder(\Ran\PluginLib\Options\Policy\WritePolicyInterface::class)->getMock();
		$policy->method('allow')->willReturn(true);
		$opts->with_policy($policy);
		$this->allow_all_persist_filters_for_site();

		// Phase 4: schema required for staged keys
		$opts->with_schema(array('k' => array('validate' => function ($v) {
			return is_string($v);
		})));
		// Stage new value
		$opts->stage_option('k', 'v');

		// Storage mock to exercise _get_storage and _save_all_options
		$mockStorage = $this->createMock(\Ran\PluginLib\Options\Storage\OptionStorageInterface::class);
		$mockStorage->method('read')->willReturn(array());
		$mockStorage->method('scope')->willReturn(OptionScope::Site);
		$mockStorage->method('update')->willReturn(true);
		$this->_set_protected_property_value($opts, 'storage', $mockStorage);

		// update_option used by Site storage adapter paths
		WP_Mock::userFunction('update_option')->andReturn(true);

		$this->assertTrue($opts->commit_replace());
		// Assert logs after SUT ran
		// Expect two write-gate sequences (add_option, save_all)
		$this->expectLog('debug', '_apply_write_gate policy decision', 2);
		$this->expectLog('debug', '_apply_write_gate applying general allow_persist filter', 2);
		$this->expectLog('debug', '_apply_write_gate general filter result', 2);
		$this->expectLog('debug', '_apply_write_gate applying scoped allow_persist filter', 2);
		$this->expectLog('debug', '_apply_write_gate scoped filter result', 2);
		$this->expectLog('debug', '_apply_write_gate final decision', 2);
		$this->expectLog('debug', '_save_all_options starting');
		$this->expectLog('debug', 'storage->update() completed.');
		$this->expectLog('debug', '_save_all_options completed');
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::refresh_options
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_read_main_option
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_get_storage
	 */
	public function test_refresh_options_reads_from_storage(): void {
		$config = $this->getMockBuilder(ConfigInterface::class)->getMock();
		$config->method('get_options_key')->willReturn('test_options');
		$config->method('get_logger')->willReturn($this->logger_mock);
		$opts = RegisterOptions::from_config($config, StorageContext::forSite(), true);

		// In-memory different from storage
		$this->_set_protected_property_value($opts, 'options', array('foo' => 'mem'));

		$mockStorage = $this->createMock(\Ran\PluginLib\Options\Storage\OptionStorageInterface::class);
		$mockStorage->method('read')->willReturn(array('foo' => 'db', 'bar' => 1));
		$mockStorage->method('scope')->willReturn(OptionScope::Site);
		$this->_set_protected_property_value($opts, 'storage', $mockStorage);

		$opts->refresh_options();
		$this->assertSame('db', $opts->get_option('foo'));
		$this->assertSame(1, $opts->get_option('bar'));
		// Assert logs after SUT ran
		$this->expectLog('debug', 'Refreshing options from database');
		$this->expectLog('debug', "Getting option 'foo'");
		$this->expectLog('debug', "Getting option 'bar'");
		$this->expectLog('debug', '_get_storage resolved', 3);
	}

	/**
	 * Micro-test to exercise logger resolution path via set_option callsite
	 * @covers \Ran\PluginLib\Options\RegisterOptions::__construct
	 * @covers \Ran\PluginLib\Options\RegisterOptions::set_option
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_get_logger
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_apply_write_gate
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_save_all_options
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_get_storage
	 */
	public function test_logger_resolution_exercised_via_set_option(): void {
		$config = $this->getMockBuilder(ConfigInterface::class)->getMock();
		$config->method('get_options_key')->willReturn('test_options');
		$config->method('get_logger')->willReturn($this->logger_mock);
		$opts = RegisterOptions::from_config($config, StorageContext::forSite(), true);
		// Allow all writes for this test
		$policy = $this->getMockBuilder(\Ran\PluginLib\Options\Policy\WritePolicyInterface::class)->getMock();
		$policy->method('allow')->willReturn(true);
		$opts->with_policy($policy);
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist')
			->with(WP_Mock\Functions::type('bool'), WP_Mock\Functions::type('array'))
			->reply(true);
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist/scope/site')
			->with(WP_Mock\Functions::type('bool'), WP_Mock\Functions::type('array'))
			->reply(true);
		WP_Mock::userFunction('update_option')->andReturn(true);

		// Phase 4: schema required for set_option keys
		$opts->with_schema(array('lg_key' => array('validate' => function ($v) {
			return is_string($v);
		})));

		$this->assertTrue($opts->set_option('lg_key', 'lg_value'));
		$this->assertSame('lg_value', $opts->get_option('lg_key'));
		// Assert logs after SUT ran
		// Expect three write-gate sequences (pre-mutation, pre-persist, inside _save_all_options)
		$this->expectLog('debug', '_apply_write_gate policy decision', 3);
		$this->expectLog('debug', '_apply_write_gate applying general allow_persist filter', 3);
		$this->expectLog('debug', '_apply_write_gate general filter result', 3);
		$this->expectLog('debug', '_apply_write_gate applying scoped allow_persist filter', 3);
		$this->expectLog('debug', '_apply_write_gate scoped filter result', 3);
		$this->expectLog('debug', '_apply_write_gate final decision', 3);
		$this->expectLog('debug', 'storage->update() completed.');
		$this->expectLog('debug', '_save_all_options completed');
		$this->expectLog('debug', "Getting option 'lg_key'");
		$this->expectLog('debug', "Getting option 'lg_key'");
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::set_option
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_sanitize_and_validate_option
	 */
	public function test_set_option_unknown_key_without_schema_throws(): void {
		$config = $this->getMockBuilder(ConfigInterface::class)->getMock();
		$config->method('get_options_key')->willReturn('test_options');
		$config->method('get_logger')->willReturn($this->logger_mock);
		$opts = RegisterOptions::from_config($config, StorageContext::forSite(), true);
		// Allow all writes for this test
		$policy = $this->getMockBuilder(\Ran\PluginLib\Options\Policy\WritePolicyInterface::class)->getMock();
		$policy->method('allow')->willReturn(true);
		$opts->with_policy($policy);
		// Allow persistence to exercise full path
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist')
			->with(WP_Mock\Functions::type('bool'), WP_Mock\Functions::type('array'))
			->reply(true);
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist/scope/site')
			->with(WP_Mock\Functions::type('bool'), WP_Mock\Functions::type('array'))
			->reply(true);
		WP_Mock::userFunction('update_option')->andReturn(true);

		$this->expectException(\InvalidArgumentException::class);
		try {
			$opts->set_option('unknown_key', 'val');
		} finally {
			$this->assertFalse($opts->get_option('unknown_key'));
		}
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_describe_callable
	 * Ensures string callables (e.g., 'is_int') are described verbatim and logged as (string).
	 */
	public function test_describe_callable_string_validator_formats_name(): void {
		$config = $this->getMockBuilder(ConfigInterface::class)->getMock();
		$config->method('get_options_key')->willReturn('opts_call_string');
		$config->method('get_logger')->willReturn($this->logger_mock);
		$opts = RegisterOptions::from_config($config, StorageContext::forSite(), true);

		$opts->register_schema(array(
			'age' => array(
				'validate' => 'is_int',
			),
		));

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessageMatches('/using validator is_int\./');
		try {
			$opts->set_option('age', '10');
		} finally {
			$this->expectLog('debug', '_describe_callable completed (string)');
		}
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_describe_callable
	 * Ensures array callables are rendered as Class::method and logged as (array).
	 */
	public function test_describe_callable_array_validator_formats_class_and_method(): void {
		$validator = new class {
			public static function validateFalse($v): bool {
				return false;
			}
		};

		$config = $this->getMockBuilder(ConfigInterface::class)->getMock();
		$config->method('get_options_key')->willReturn('opts_call_array');
		$config->method('get_logger')->willReturn($this->logger_mock);
		$opts = RegisterOptions::from_config($config, StorageContext::forSite(), true);

		$opts->register_schema(array(
			'age' => array(
				'validate' => array($validator, 'validateFalse'),
			),
		));

		$fqcn = get_class($validator);
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessageMatches('/using validator ' . preg_quote($fqcn, '/') . '::validateFalse\./');
		try {
			$opts->set_option('age', 10);
		} finally {
			$this->expectLog('debug', '_describe_callable completed (array)');
		}
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_describe_callable
	 * Ensures closures are reported as "Closure" and logged as (closure).
	 */
	public function test_describe_callable_closure_reports_closure(): void {
		$config = $this->getMockBuilder(ConfigInterface::class)->getMock();
		$config->method('get_options_key')->willReturn('opts_call_closure');
		$config->method('get_logger')->willReturn($this->logger_mock);
		$opts = RegisterOptions::from_config($config, StorageContext::forSite(), true);

		$opts->register_schema(array(
			'flag' => array(
				'validate' => function ($v) {
					return false;
				},
			),
		));

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessageMatches('/using validator Closure\./');
		try {
			$opts->set_option('flag', 1);
		} finally {
			$this->expectLog('debug', '_describe_callable completed (closure)');
		}
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_describe_callable
	 * Ensures invokable objects are reported generically as "callable" and logged as (other).
	 */
	public function test_describe_callable_invokable_object_reports_callable(): void {
		$config = $this->getMockBuilder(ConfigInterface::class)->getMock();
		$config->method('get_options_key')->willReturn('opts_call_other');
		$config->method('get_logger')->willReturn($this->logger_mock);
		$opts = RegisterOptions::from_config($config, StorageContext::forSite(), true);

		$validator = new class {
			public function __invoke($v): bool {
				return false;
			}
		};

		$opts->register_schema(array(
			'age' => array(
				'validate' => $validator,
			),
		));

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessageMatches('/using validator callable\./');
		try {
			$opts->set_option('age', 10);
		} finally {
			$this->expectLog('debug', '_describe_callable completed (other)');
		}
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_sanitize_and_validate_option
	 * Ensures the inferred validation path executes the stringify+throw block
	 * (RegisterOptions.php lines ~1239–1242) when a value mismatches the inferred type.
	 */
	public function test_inferred_validation_branch_hits_stringify_and_throws(): void {
		$config = $this->getMockBuilder(ConfigInterface::class)->getMock();
		$config->method('get_options_key')->willReturn('opts_inferred_str');
		$config->method('get_logger')->willReturn($this->logger_mock);
		$opts = RegisterOptions::from_config($config, StorageContext::forSite(), true);

		// With strict validator-required, provide an explicit validator that mimics the old inferred 'int' expectation.
		$opts->register_schema(array(
			'count' => array(
				'default'  => 1,
				'validate' => function ($v) {
					return is_int($v);
				},
			),
		));

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessageMatches('/Validation failed/');
		try {
			// Non-int value should fail validator and trigger stringify before throw
			$opts->set_option('count', 'not-an-int');
		} finally {
			$this->expectLog('debug', '_stringify_value_for_error completed');
		}
	}
}
