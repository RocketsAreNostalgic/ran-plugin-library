<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Util;

use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use PHPUnit\Framework\AssertionFailedError;

/**
 * @covers \Ran\PluginLib\Util\ExpectLogTrait
 */
class ExpectLogTraitTest extends PluginLibTestCase {
	/**
	 * @var resource|null
	 */
	public $expect_log_output_stream = null;

	public function test_expect_log_matches_single_substring(): void {
		$this->logger_mock->info('The quick brown fox jumps over the lazy dog');

		$this->expectLog('info', 'quick brown');
	}

	public function test_expect_log_matches_multiple_substrings_required_times(): void {
		$this->logger_mock->warning('User 42 encountered warning code 1001');
		$this->logger_mock->warning('User 42 encountered warning code 1001');

		$this->expectLog('warning', array('User 42', '1001'), 2);
	}

	public function test_expect_log_failure_throws_assertion_failed_error_with_verbose_debug(): void {
		$this->logger_mock->error('Different message that will not match');

		$this->expectException(AssertionFailedError::class);
		$this->expectExceptionMessage("Expected to find 1 log message(s) of level 'info' containing ['missing segment']");

		$this->expectLog('info', 'missing segment', 1, true, true);
	}

	public function test_expect_log_failure_with_relevant_logs_reports_messages(): void {
		$this->logger_mock->info('Some info message without the keyword');
		$this->logger_mock->info('Another info message still missing it');

		$this->expectException(AssertionFailedError::class);
		$this->expectExceptionMessage("Expected to find 2 log message(s) of level 'info' containing ['needle']");

		$this->expectLog('info', 'needle', 2, true, false);
	}

	public function test_expect_log_verbose_output_uses_configured_stream(): void {
		$this->enable_console_logging   = false;
		$this->expect_log_output_stream = fopen('php://memory', 'w+');
		$this->logger_mock->info('Info message containing needle');

		try {
			$this->expectLog('info', 'needle', 1, true, false);
			rewind($this->expect_log_output_stream);
			$contents = stream_get_contents($this->expect_log_output_stream);
			$this->assertIsString($contents);
			$this->assertStringContainsString('[EXPECTLOG] START', $contents);
			$this->assertStringContainsString("Expecting 1 message(s) of level 'info'", $contents);
		} finally {
			$this->enable_console_logging = false;
			if (is_resource($this->expect_log_output_stream)) {
				fclose($this->expect_log_output_stream);
			}
			$this->expect_log_output_stream = null;
		}
	}

	public function test_expect_log_output_writes_formatted_message(): void {
		$this->enable_console_logging   = false;
		$this->expect_log_output_stream = fopen('php://memory', 'w+');

		try {
			$method = new \ReflectionMethod($this, 'expectLogOutput');
			$method->setAccessible(true);
			$method->invoke($this, 'Formatted value: %s', 'needle');

			rewind($this->expect_log_output_stream);
			$contents = stream_get_contents($this->expect_log_output_stream);
			$this->assertSame('Formatted value: needle', $contents);
		} finally {
			$this->enable_console_logging = false;
			if (is_resource($this->expect_log_output_stream)) {
				fclose($this->expect_log_output_stream);
			}
			$this->expect_log_output_stream = null;
		}
	}

	public function test_expect_log_requires_collecting_logger_property(): void {
		$original_logger   = $this->logger_mock;
		$this->logger_mock = null;

		$this->expectException(AssertionFailedError::class);
		$this->expectExceptionMessageMatches('/requires a CollectingLogger property named "logger_mock"/i');

		try {
			$this->expectLog('info', 'needle');
		} finally {
			$this->logger_mock = $original_logger;
		}
	}
}
