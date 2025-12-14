<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms;

use Ran\PluginLib\Util\CollectingLogger;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Forms\Services\FormsMessageService;
use Ran\PluginLib\Forms\Renderer\FormMessageHandler;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Ran\PluginLib\Forms\Services\FormsMessageService
 */
final class FormsMessageServiceTest extends TestCase {
	public function test_prepare_validation_messages_sets_pending_values_and_clears_messages(): void {
		$logger  = new CollectingLogger();
		$handler = new FormMessageHandler($logger);
		$pending = null;
		$store   = array();

		$svc = new FormsMessageService(
			$handler,
			$logger,
			'opt',
			$pending,
			static fn (string $k): string => $k,
			static fn (): int => 123,
			static function (string $key, mixed $value, int $ttl) use (&$store): mixed {
				$store[$key] = $value;
				return true;
			},
			static function (string $key) use (&$store): mixed {
				return $store[$key] ?? false;
			},
			static function (string $key) use (&$store): mixed {
				unset($store[$key]);
				return true;
			},
			static fn (): string => 'admin'
		);

		$handler->set_messages(array(
			'field' => array('warnings' => array('warn'), 'notices' => array()),
		));

		$svc->prepare_validation_messages(array('a' => 'b'));

		$svcRef     = new \ReflectionObject($svc);
		$pendingRef = $svcRef->getProperty('pending_values');
		$pendingRef->setAccessible(true);
		self::assertSame(array('a' => 'b'), $pendingRef->getValue($svc));
		self::assertSame(array(), $handler->get_all_messages());
		self::assertSame(array('a' => 'b'), $handler->get_effective_values(array()));
	}

	public function test_process_validation_messages_merges_sanitized_values_only_for_submitted_keys(): void {
		$logger  = new CollectingLogger();
		$handler = new FormMessageHandler($logger);
		$pending = array('a' => 'raw', 'b' => 'raw2');
		$store   = array();

		$svc = new FormsMessageService(
			$handler,
			$logger,
			'opt',
			$pending,
			static fn (string $k): string => $k,
			static fn (): int => 123,
			static function (string $key, mixed $value, int $ttl) use (&$store): mixed {
				$store[$key] = $value;
				return true;
			},
			static function (string $key) use (&$store): mixed {
				return $store[$key] ?? false;
			},
			static function (string $key) use (&$store): mixed {
				unset($store[$key]);
				return true;
			},
			static fn (): string => 'admin'
		);

		$options = $this->createMock(RegisterOptions::class);
		$options->method('take_messages')->willReturn(array(
			'a' => array('warnings' => array('w1'), 'notices' => array()),
		));
		$options->method('get_options')->willReturn(array(
			'a' => 'sanitized-a',
			'c' => 'other',
		));

		$result = $svc->process_validation_messages($options);

		self::assertArrayHasKey('a', $result);

		$svcRef     = new \ReflectionObject($svc);
		$pendingRef = $svcRef->getProperty('pending_values');
		$pendingRef->setAccessible(true);
		$pendingAfter = (array) $pendingRef->getValue($svc);
		self::assertSame('sanitized-a', $pendingAfter['a']);
		self::assertSame('raw2', $pendingAfter['b']);
	}

	public function test_get_form_messages_transient_key_uses_form_type_and_current_user_id(): void {
		$logger  = new CollectingLogger();
		$handler = new FormMessageHandler($logger);
		$pending = null;
		$store   = array();

		$svc = new FormsMessageService(
			$handler,
			$logger,
			'main_option',
			$pending,
			static fn (string $k): string => $k,
			static fn (): int => 999,
			static function (string $key, mixed $value, int $ttl) use (&$store): mixed {
				$store[$key] = $value;
				return true;
			},
			static function (string $key) use (&$store): mixed {
				return $store[$key] ?? false;
			},
			static function (string $key) use (&$store): mixed {
				unset($store[$key]);
				return true;
			},
			static fn (): string => 'user'
		);

		self::assertSame('ran_form_messages_user_main_option_999', $svc->get_form_messages_transient_key());
		self::assertSame('ran_form_messages_user_main_option_123', $svc->get_form_messages_transient_key(123));
	}

	public function test_persist_and_restore_form_messages_round_trip_new_format(): void {
		$logger  = new CollectingLogger();
		$handler = new FormMessageHandler($logger);
		$pending = array('a' => 'pending-a');
		$store   = array();

		$svc = new FormsMessageService(
			$handler,
			$logger,
			'opt',
			$pending,
			static fn (string $k): string => $k,
			static fn (): int => 1,
			static function (string $key, mixed $value, int $ttl) use (&$store): mixed {
				$store[$key] = $value;
				return true;
			},
			static function (string $key) use (&$store): mixed {
				return $store[$key] ?? false;
			},
			static function (string $key) use (&$store): mixed {
				unset($store[$key]);
				return true;
			},
			static fn (): string => 'admin'
		);

		$messages = array(
			'a' => array('warnings' => array('w'), 'notices' => array('n')),
		);
		$svc->persist_form_messages($messages, 1);

		// Reset handler/service pending state before restore
		$handler->clear();
		$svc->clear_pending_validation();

		self::assertTrue($svc->restore_form_messages(1));
		self::assertSame($messages, $handler->get_all_messages());
		self::assertSame(array('a' => 'pending-a'), $handler->get_effective_values(array()));

		$svcRef     = new \ReflectionObject($svc);
		$pendingRef = $svcRef->getProperty('pending_values');
		$pendingRef->setAccessible(true);
		self::assertSame(array('a' => 'pending-a'), $pendingRef->getValue($svc));

		// Transient should have been deleted
		$key = $svc->get_form_messages_transient_key(1);
		self::assertArrayNotHasKey($key, $store);
	}

	public function test_restore_form_messages_accepts_old_format(): void {
		$logger  = new CollectingLogger();
		$handler = new FormMessageHandler($logger);
		$pending = null;
		$store   = array();

		$svc = new FormsMessageService(
			$handler,
			$logger,
			'opt',
			$pending,
			static fn (string $k): string => $k,
			static fn (): int => 1,
			static function (string $key, mixed $value, int $ttl) use (&$store): mixed {
				$store[$key] = $value;
				return true;
			},
			static function (string $key) use (&$store): mixed {
				return $store[$key] ?? false;
			},
			static function (string $key) use (&$store): mixed {
				unset($store[$key]);
				return true;
			},
			static fn (): string => 'admin'
		);

		$messages = array(
			'a' => array('warnings' => array('w'), 'notices' => array()),
		);
		$key         = $svc->get_form_messages_transient_key(1);
		$store[$key] = $messages;

		self::assertTrue($svc->restore_form_messages(1));
		self::assertSame($messages, $handler->get_all_messages());

		$svcRef     = new \ReflectionObject($svc);
		$pendingRef = $svcRef->getProperty('pending_values');
		$pendingRef->setAccessible(true);
		self::assertNull($pendingRef->getValue($svc));
	}
}
