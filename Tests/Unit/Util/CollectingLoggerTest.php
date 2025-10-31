<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Util;

use Psr\Log\LogLevel;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Util\CollectingLogger;

/**
 * @covers \Ran\PluginLib\Util\CollectingLogger
 */
class CollectingLoggerTest extends PluginLibTestCase {
	private CollectingLogger $collecting_logger;

	public function setUp(): void {
		parent::setUp();
		$this->collecting_logger = new CollectingLogger($this->config_mock->get_plugin_data());
	}

	public function test_is_active_always_returns_true(): void {
		$this->assertTrue($this->collecting_logger->is_active(), 'CollectingLogger should always report active.');
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function severityProvider(): array {
		return array(
			'emergency' => array('emergency', LogLevel::EMERGENCY),
			'alert'     => array('alert', LogLevel::ALERT),
			'critical'  => array('critical', LogLevel::CRITICAL),
			'error'     => array('error', LogLevel::ERROR),
			'warning'   => array('warning', LogLevel::WARNING),
			'notice'    => array('notice', LogLevel::NOTICE),
			'info'      => array('info', LogLevel::INFO),
			'debug'     => array('debug', LogLevel::DEBUG),
		);
	}

	/**
	 * @dataProvider severityProvider
	 */
	public function test_level_specific_methods_collect_logs(string $method, string $expected_level): void {
		$context = array('key' => 'value');
		$message = 'Message via ' . $method;

		$this->collecting_logger->{$method}($message, $context);

		$logs = $this->collecting_logger->get_logs();

		$this->assertCount(1, $logs);
		$this->assertSame($expected_level, $logs[0]['level']);
		$this->assertSame($message, $logs[0]['message']);
		$this->assertSame($context, $logs[0]['context']);
	}

	public function test_log_collects_arbitrary_level_entries(): void {
		$context = array('foo' => 'bar');
		$this->collecting_logger->log('custom-level', 'Custom message', $context);

		$logs = $this->collecting_logger->get_logs();

		$this->assertCount(1, $logs);
		$this->assertSame('custom-level', $logs[0]['level']);
		$this->assertSame('Custom message', $logs[0]['message']);
		$this->assertSame($context, $logs[0]['context']);
	}

	public function test_logs_are_preserved_in_call_order(): void {
		$this->collecting_logger->info('First message');
		$this->collecting_logger->error('Second message');

		$logs = $this->collecting_logger->get_logs();

		$this->assertCount(2, $logs);
		$this->assertSame('info', $logs[0]['level']);
		$this->assertSame('First message', $logs[0]['message']);
		$this->assertSame(array(), $logs[0]['context']);

		$this->assertSame('error', $logs[1]['level']);
		$this->assertSame('Second message', $logs[1]['message']);
		$this->assertSame(array(), $logs[1]['context']);
	}

	public function test_stringable_messages_are_cast_to_string(): void {
		$stringable = new class() {
			public function __toString(): string {
				return 'Rendered from __toString';
			}
		};

		$this->collecting_logger->debug($stringable);

		$logs = $this->collecting_logger->get_logs();

		$this->assertCount(1, $logs);
		$this->assertSame('Rendered from __toString', $logs[0]['message']);
	}
}
