<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Config;

use Mockery;
use Ran\PluginLib\Config\Config;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use WP_Mock;

/**
 * @covers \Ran\PluginLib\Config\Config::options
 */
final class ConfigOptionsWiringTest extends PluginLibTestCase {
	public function setUp(): void {
		parent::setUp();
		// Common sanitize_key stub (mirrors other tests)
		WP_Mock::userFunction('sanitize_key')
			->andReturnUsing(function ($v) {
				$s = strtolower(preg_replace('/[^a-z0-9_\-]/i', '', (string) $v));
				return trim($s, '_');
			})
			->byDefault();
	}

	public function tearDown(): void {
		parent::tearDown();
	}

	/**
	 * Ensures Config::options() uses the configured AppOption key for reads and performs no writes.
	 */
	public function test_key_wiring_and_no_writes(): void {
		$main_option = 'ran_test_app_option_key';

		// Expect a single read on construction and forbid writes
		WP_Mock::userFunction('get_option')->with($main_option, array())->once()->andReturn(array());
		WP_Mock::userFunction('update_option')->never();
		WP_Mock::userFunction('add_option')->never();
		WP_Mock::userFunction('delete_option')->never();

		// Create a partial mock of Config to inject get_logger and get_config
		/** @var Config&\PHPUnit\Framework\MockObject\MockObject $config */
		$config = $this->getMockBuilder(Config::class)
			->onlyMethods(array('get_logger', 'get_config'))
			->disableOriginalConstructor()
			->getMock();

		// Use the CollectingLogger mock helper (active logger)
		$logger = $this->set_logger_mock();
		$config->method('get_logger')->willReturn($logger);

		$config->method('get_config')->willReturn(array(
			'RAN' => array('AppOption' => $main_option),
		));

		$opts = $config->options();
		$this->assertInstanceOf(\Ran\PluginLib\Options\RegisterOptions::class, $opts);
	}

	/**
	 * Unknown args should emit a warning via the provided logger; still no writes.
	 */
	public function test_unknown_args_emit_warning(): void {
		$main_option = 'ran_test_app_option_key';

		WP_Mock::userFunction('get_option')->with($main_option, array())->once()->andReturn(array());
		WP_Mock::userFunction('update_option')->never();
		WP_Mock::userFunction('add_option')->never();
		WP_Mock::userFunction('delete_option')->never();

		/** @var Config&\PHPUnit\Framework\MockObject\MockObject $config */
		$config = $this->getMockBuilder(Config::class)
			->onlyMethods(array('get_logger', 'get_config'))
			->disableOriginalConstructor()
			->getMock();

		$logger = $this->set_logger_mock();
		// Expect a warning call mentioning Ignored args
		$logger->shouldReceive('warning')->once()->with(Mockery::on(function ($message) {
			return is_string($message) && str_contains($message, 'Ignored args');
		}));

		$config->method('get_logger')->willReturn($logger);
		$config->method('get_config')->willReturn(array(
			'RAN' => array('AppOption' => $main_option),
		));

		$opts = $config->options(array('foo' => 'bar'));
		$this->assertInstanceOf(\Ran\PluginLib\Options\RegisterOptions::class, $opts);
	}
}
