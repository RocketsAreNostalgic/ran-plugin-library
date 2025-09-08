<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options;

use WP_Mock;
use Ran\PluginLib\Options\OptionScope;
use Ran\PluginLib\Util\ExpectLogTrait;
use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;

/**
 * @covers \Ran\PluginLib\Options\RegisterOptions::__construct
 * @covers \Ran\PluginLib\Options\RegisterOptions::refresh_options
 * @covers \Ran\PluginLib\Options\RegisterOptions::flush
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
		WP_Mock::userFunction('get_option')->andReturn(array());
		WP_Mock::userFunction('get_site_option')->andReturn(array());
		WP_Mock::userFunction('get_blog_option')->andReturn(array());
		WP_Mock::userFunction('get_user_option')->andReturn(array());
		WP_Mock::userFunction('get_user_meta')->andReturn(array());
		WP_Mock::userFunction('wp_load_alloptions')->andReturn(array());

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
		$opts = RegisterOptions::from_config($config, true, OptionScope::Site);

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

		// Seed defaults (in-memory). This exercises _normalize_schema_keys, _resolve_default_value, _sanitize_and_validate_option
		$changed = $opts->register_schema($schema, seed_defaults: true, flush: false);
		$this->assertTrue($changed);
		$this->assertSame(5, $opts->get_option('num'));
		$this->assertSame('Hello', $opts->get_option('label'));
		// Assert logs after SUT ran
		$this->expectLog('debug', "Getting option 'num'");
		$this->expectLog('debug', "Getting option 'label'");
		$this->expectLog('debug', '_normalize_schema_keys completed');
		$this->expectLog('debug', '_resolve_default_value resolved callable');
		$this->expectLog('debug', '_sanitize_and_validate_option completed', 2);
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
		$opts = RegisterOptions::from_config($config, true, OptionScope::Site);

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
		$opts->register_schema($schema, seed_defaults: false, flush: false);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessageMatches('/Validation failed/');
		$opts->set_option('age', 10); // should fail validate, triggering _stringify_value_for_error + _describe_callable
		// End-of-method logs produced during failure path
		$this->expectLog('debug', '_stringify_value_for_error completed');
		$this->expectLog('debug', '_describe_callable completed');
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::flush
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_save_all_options
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_apply_write_gate
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_get_storage
	 */
	public function test_flush_triggers_storage_update_and_save_all_options(): void {
		$config = $this->getMockBuilder(ConfigInterface::class)->getMock();
		$config->method('get_options_key')->willReturn('test_options');
		$config->method('get_logger')->willReturn($this->logger_mock);
		$opts = RegisterOptions::from_config($config, true, OptionScope::Site);
		$this->allow_all_persist_filters_for_site();

		// Stage new value
		$opts->add_option('k', 'v');

		// Storage mock to exercise _get_storage and _save_all_options
		$mockStorage = $this->createMock(\Ran\PluginLib\Options\Storage\OptionStorageInterface::class);
		$mockStorage->method('read')->willReturn(array());
		$mockStorage->method('scope')->willReturn(OptionScope::Site);
		$mockStorage->method('update')->willReturn(true);
		$this->_set_protected_property_value($opts, 'storage', $mockStorage);

		// update_option used by Site storage adapter paths
		WP_Mock::userFunction('update_option')->andReturn(true);

		$this->assertTrue($opts->flush(false));
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
		$opts = RegisterOptions::from_config($config, true, OptionScope::Site);

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
		$opts = RegisterOptions::from_config($config, true, OptionScope::Site);
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist')
			->with(WP_Mock\Functions::type('bool'), WP_Mock\Functions::type('array'))
			->reply(true);
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist/scope/site')
			->with(WP_Mock\Functions::type('bool'), WP_Mock\Functions::type('array'))
			->reply(true);
		WP_Mock::userFunction('update_option')->andReturn(true);

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
	 * @covers \Ran\PluginLib\Options\RegisterOptions::__construct
	 * @covers \Ran\PluginLib\Options\RegisterOptions::set_option
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_describe_callable
	 */
	public function test_describe_callable_string(): void {
		$config = $this->getMockBuilder(ConfigInterface::class)->getMock();
		$config->method('get_options_key')->willReturn('test_options');
		$config->method('get_logger')->willReturn($this->logger_mock);
		$opts   = RegisterOptions::from_config($config, true, OptionScope::Site);
		$schema = array(
			's' => array(
				'validate' => 'is_numeric',
			),
		);
		$opts->register_schema($schema, seed_defaults: false, flush: false);
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessageMatches('/using validator is_numeric/');
		$opts->set_option('s', 'not-a-number');
		$this->expectLog('debug', '_describe_callable completed (string)');
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::__construct
	 * @covers \Ran\PluginLib\Options\RegisterOptions::set_option
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_describe_callable
	 */
	public function test_describe_callable_array(): void {
		$config = $this->getMockBuilder(ConfigInterface::class)->getMock();
		$config->method('get_options_key')->willReturn('test_options');
		$config->method('get_logger')->willReturn($this->logger_mock);
		$opts   = RegisterOptions::from_config($config, true, OptionScope::Site);
		$schema = array(
			'a' => array(
				'validate' => array($this, 'helperReturnsFalse'),
			),
		);
		$opts->register_schema($schema, seed_defaults: false, flush: false);
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessageMatches('/::helperReturnsFalse/');
		$opts->set_option('a', 'anything');
		$this->expectLog('debug', '_describe_callable completed (array)');
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::__construct
	 * @covers \Ran\PluginLib\Options\RegisterOptions::set_option
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_describe_callable
	 */
	public function test_describe_callable_invokable_object(): void {
		$config = $this->getMockBuilder(ConfigInterface::class)->getMock();
		$config->method('get_options_key')->willReturn('test_options');
		$config->method('get_logger')->willReturn($this->logger_mock);
		$opts      = RegisterOptions::from_config($config, true, OptionScope::Site);
		$invokable = new class {
			public function __invoke($v) {
				return false;
			}
		};
		$schema = array(
			'i' => array(
				'validate' => $invokable,
			),
		);
		$opts->register_schema($schema, seed_defaults: false, flush: false);
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessageMatches('/using validator/');
		$opts->set_option('i', 'anything');
		$this->expectLog('debug', '_describe_callable completed (other)');
	}

	// helper for array callable test above
	public function helperReturnsFalse($v) {
		return false;
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::__construct
	 * @covers \Ran\PluginLib\Options\RegisterOptions::set_option
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_stringify_value_for_error
	 */
	public function test_stringify_handles_array_input(): void {
		$config = $this->getMockBuilder(ConfigInterface::class)->getMock();
		$config->method('get_options_key')->willReturn('test_options');
		$config->method('get_logger')->willReturn($this->logger_mock);
		$opts   = RegisterOptions::from_config($config, true, OptionScope::Site);
		$schema = array(
			'arr' => array(
				'validate' => function ($v) {
					return false;
				},
			),
		);
		$opts->register_schema($schema, seed_defaults: false, flush: false);
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessageMatches('/Array\(3\)/');
		$opts->set_option('arr', array(1, 2, 3));
		$this->expectLog('debug', '_stringify_value_for_error completed');
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::__construct
	 * @covers \Ran\PluginLib\Options\RegisterOptions::set_option
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_stringify_value_for_error
	 */
	public function test_stringify_handles_object_input(): void {
		$config = $this->getMockBuilder(ConfigInterface::class)->getMock();
		$config->method('get_options_key')->willReturn('test_options');
		$config->method('get_logger')->willReturn($this->logger_mock);
		$opts   = RegisterOptions::from_config($config, true, OptionScope::Site);
		$schema = array(
			'obj' => array(
				'validate' => function ($v) {
					return false;
				},
			),
		);
		$opts->register_schema($schema, seed_defaults: false, flush: false);
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessageMatches('/Object\(stdClass\)/');
		$opts->set_option('obj', new \stdClass());
		$this->expectLog('debug', '_stringify_value_for_error completed');
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::__construct
	 * @covers \Ran\PluginLib\Options\RegisterOptions::set_option
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_stringify_value_for_error
	 */
	public function test_stringify_truncates_long_scalar(): void {
		$config = $this->getMockBuilder(ConfigInterface::class)->getMock();
		$config->method('get_options_key')->willReturn('test_options');
		$config->method('get_logger')->willReturn($this->logger_mock);
		$opts   = RegisterOptions::from_config($config, true, OptionScope::Site);
		$schema = array(
			'long' => array(
				'validate' => function ($v) {
					return false;
				},
			),
		);
		$opts->register_schema($schema, seed_defaults: false, flush: false);
		$long = str_repeat('A', 500);
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessageMatches('/\.\.\./');
		$opts->set_option('long', $long);
		$this->expectLog('debug', '_stringify_value_for_error completed');
	}

	/**
		* @covers \Ran\PluginLib\Options\RegisterOptions::set_option
		* @covers \Ran\PluginLib\Options\RegisterOptions::_sanitize_and_validate_option
		*/
	public function test_set_option_unknown_key_triggers_no_schema_log(): void {
		$config = $this->getMockBuilder(ConfigInterface::class)->getMock();
		$config->method('get_options_key')->willReturn('test_options');
		$config->method('get_logger')->willReturn($this->logger_mock);
		$opts = RegisterOptions::from_config($config, true, OptionScope::Site);
		// Allow persistence to exercise full path
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist')
			->with(WP_Mock\Functions::type('bool'), WP_Mock\Functions::type('array'))
			->reply(true);
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist/scope/site')
			->with(WP_Mock\Functions::type('bool'), WP_Mock\Functions::type('array'))
			->reply(true);
		WP_Mock::userFunction('update_option')->andReturn(true);

		$this->assertTrue($opts->set_option('unknown_key', 'val'));
		$this->assertSame('val', $opts->get_option('unknown_key'));
		$this->expectLog('debug', '_sanitize_and_validate_option no-schema');
	}
}
