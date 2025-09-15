<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options;

use WP_Mock;
use Ran\PluginLib\Util\CollectingLogger;
use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Options\Storage\StorageContext;

final class RegisterOptionsStringifyTest extends PluginLibTestCase {
	public function setUp(): void {
		parent::setUp();
		// Common WP stubs used by constructor/read path
		WP_Mock::userFunction('wp_load_alloptions')->andReturn(array())->byDefault();
		WP_Mock::userFunction('get_option')->andReturn(array())->byDefault();
		WP_Mock::userFunction('sanitize_key')->andReturnUsing(function ($key) {
			$key = strtolower((string) $key);
			$key = preg_replace('/[^a-z0-9_\-]+/i', '_', $key) ?? '';
			return trim($key, '_');
		});
	}

	/**
	 * Ensure object branch of _stringify_value_for_error returns 'Object(<Class>)'.
	 * This covers the line: $s = 'Object(' . get_class($value) . ')';
	 */
	public function test_stringify_value_for_error_object_branch(): void {
		$logger = new CollectingLogger();
		$opts   = RegisterOptions::site('stringify_test_option', true, $logger);

		$val    = new \ArrayObject();
		$result = $this->_invoke_protected_method($opts, '_stringify_value_for_error', array($val));
		self::assertSame('Object(ArrayObject)', $result);
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
		$opts   = RegisterOptions::from_config($config, StorageContext::forSite(), true);
		$schema = array(
			'arr' => array(
				'validate' => function ($v) {
					return false;
				},
			),
		);
		$opts->register_schema($schema);
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
	public function test_stringify_truncates_long_scalar(): void {
		$config = $this->getMockBuilder(ConfigInterface::class)->getMock();
		$config->method('get_options_key')->willReturn('test_options');
		$config->method('get_logger')->willReturn($this->logger_mock);
		$opts   = RegisterOptions::from_config($config, StorageContext::forSite(), true);
		$schema = array(
			'long' => array(
				'validate' => function ($v) {
					return false;
				},
			),
		);
		$opts->register_schema($schema);
		$long = str_repeat('A', 500);
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessageMatches('/\.\.\./');
		$opts->set_option('long', $long);
		$this->expectLog('debug', '_stringify_value_for_error completed');
	}
}
