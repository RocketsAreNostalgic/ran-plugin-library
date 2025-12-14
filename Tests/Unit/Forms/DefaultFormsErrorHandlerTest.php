<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms;

use Ran\PluginLib\Forms\Services\DefaultFormsErrorHandler;
use Psr\Log\AbstractLogger;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Ran\PluginLib\Forms\Services\DefaultFormsErrorHandler
 */
final class DefaultFormsErrorHandlerTest extends TestCase {
	public function test_handle_builder_error_logs_and_returns_early_when_not_admin(): void {
		$handler = new DefaultFormsErrorHandler();
		$logger  = new class extends AbstractLogger {
			/** @var array<int, array{level:string,message:string,context:array}> */
			public array $records = array();

			public function log($level, $message, array $context = array()): void {
				$this->records[] = array(
					'level'   => (string) $level,
					'message' => (string) $message,
					'context' => $context,
				);
			}
		};

		$e = new \RuntimeException('boom');

		$called = array(
			'add_action' => 0,
			'fallback'   => 0,
		);

		$handler->handle_builder_error(
			$e,
			'hook_name',
			$logger,
			'TestClass',
			function (): bool {
				return true;
			},
			function (): bool {
				return false;
			},
			function (string $capability): bool {
				return true;
			},
			function (string $hook, callable $callback, int $priority = 10, int $accepted_args = 1) use (&$called): void {
				$called['add_action']++;
			},
			function (\Throwable $e, string $hook, bool $is_dev) use (&$called): void {
				$called['fallback']++;
			}
		);

		self::assertCount(1, $logger->records);
		self::assertSame('error', $logger->records[0]['level']);
		self::assertSame(0, $called['add_action']);
		self::assertSame(0, $called['fallback']);
	}

	public function test_handle_builder_error_schedules_notice_in_dev_admin_context(): void {
		$handler = new DefaultFormsErrorHandler();
		$logger  = new class extends AbstractLogger {
			/** @var array<int, array{level:string,message:string,context:array}> */
			public array $records = array();

			public function log($level, $message, array $context = array()): void {
				$this->records[] = array(
					'level'   => (string) $level,
					'message' => (string) $message,
					'context' => $context,
				);
			}
		};

		$e = new \RuntimeException('boom');

		$called = array(
			'add_action' => array(),
			'fallback'   => 0,
		);

		$handler->handle_builder_error(
			$e,
			'hook_name',
			$logger,
			'TestClass',
			function (): bool {
				return true;
			},
			function (): bool {
				return true;
			},
			function (string $capability): bool {
				return $capability === 'manage_options';
			},
			function (string $hook, callable $callback, int $priority = 10, int $accepted_args = 1) use (&$called): void {
				$called['add_action'][] = array(
					'hook'          => $hook,
					'priority'      => $priority,
					'accepted_args' => $accepted_args,
				);
			},
			function (\Throwable $e, string $hook, bool $is_dev) use (&$called): void {
				$called['fallback']++;
			}
		);

		self::assertCount(1, $logger->records);
		self::assertSame('error', $logger->records[0]['level']);
		self::assertCount(1, $called['add_action']);
		self::assertSame('admin_notices', $called['add_action'][0]['hook']);
		self::assertSame(0, $called['fallback']);
	}

	public function test_handle_builder_error_does_not_schedule_notice_when_not_dev(): void {
		$handler = new DefaultFormsErrorHandler();
		$logger  = new class extends AbstractLogger {
			/** @var array<int, array{level:string,message:string,context:array}> */
			public array $records = array();

			public function log($level, $message, array $context = array()): void {
				$this->records[] = array(
					'level'   => (string) $level,
					'message' => (string) $message,
					'context' => $context,
				);
			}
		};

		$e = new \RuntimeException('boom');

		$called = array(
			'add_action' => 0,
			'fallback'   => 0,
		);

		$handler->handle_builder_error(
			$e,
			'hook_name',
			$logger,
			'TestClass',
			function (): bool {
				return false;
			},
			function (): bool {
				return true;
			},
			function (string $capability): bool {
				return $capability === 'manage_options';
			},
			function (string $hook, callable $callback, int $priority = 10, int $accepted_args = 1) use (&$called): void {
				$called['add_action']++;
			},
			function (\Throwable $e, string $hook, bool $is_dev) use (&$called): void {
				$called['fallback']++;
			}
		);

		self::assertCount(1, $logger->records);
		self::assertSame('error', $logger->records[0]['level']);
		self::assertSame(0, $called['add_action']);
		self::assertSame(0, $called['fallback']);
	}
}
