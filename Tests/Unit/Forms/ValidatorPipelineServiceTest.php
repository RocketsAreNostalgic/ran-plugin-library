<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms;

use Ran\PluginLib\Util\CollectingLogger;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Forms\Validation\ValidatorPipelineService;

final class ValidatorPipelineServiceTest extends PluginLibTestCase {
	public function test_normalize_schema_entry_wraps_plain_callables(): void {
		$service = new ValidatorPipelineService();
		$logger  = new CollectingLogger();

		$sanitize = static function (mixed $value): mixed {
			return (string) $value;
		};
		$validate = static function (mixed $value): bool {
			return $value !== '';
		};

		$result = $service->normalize_schema_entry(
			array(
				'sanitize' => $sanitize,
				'validate' => array($validate),
				'default'  => 'alpha',
			),
			'field_a',
			'TestHost',
			$logger
		);

		self::assertSame(array(), $result['sanitize'][ValidatorPipelineService::BUCKET_COMPONENT]);
		self::assertSame(array($sanitize), $result['sanitize'][ValidatorPipelineService::BUCKET_SCHEMA]);
		self::assertSame(array(), $result['validate'][ValidatorPipelineService::BUCKET_COMPONENT]);
		self::assertSame(array($validate), $result['validate'][ValidatorPipelineService::BUCKET_SCHEMA]);
		self::assertSame('alpha', $result['default']);

		$normalizeLog = $logger->find_logs(static function (array $record): bool {
			return $record['message'] === 'TestHost: _coerce_schema_entry completed';
		});
		self::assertCount(1, $normalizeLog, 'Expected service to log normalized schema entry.');
	}

	public function test_merge_bucketed_callables_preserves_order_and_schema_override(): void {
		$service = new ValidatorPipelineService();

		$existing = array(
			ValidatorPipelineService::BUCKET_COMPONENT => array(
				static fn (): bool => true,
			),
			ValidatorPipelineService::BUCKET_SCHEMA => array(
				static fn (): bool => true,
			),
		);

		$incoming = array(
			ValidatorPipelineService::BUCKET_COMPONENT => array(
				static fn (): bool => false,
			),
			ValidatorPipelineService::BUCKET_SCHEMA => array(),
		);

		$merged = $service->merge_bucketed_callables($existing, $incoming);

		self::assertCount(2, $merged[ValidatorPipelineService::BUCKET_COMPONENT]);
		self::assertSame(array_values($existing[ValidatorPipelineService::BUCKET_SCHEMA]), $merged[ValidatorPipelineService::BUCKET_SCHEMA]);
	}

	public function test_sanitize_and_validate_executes_in_bucket_order_and_records_warnings(): void {
		$service = new ValidatorPipelineService();
		$logger  = new CollectingLogger();

		$order    = array();
		$notices  = array();
		$warnings = array();

		$rules = array(
			'sanitize' => array(
				ValidatorPipelineService::BUCKET_COMPONENT => array(
					static function (mixed $value, callable $emitNotice) use (&$order) {
						static $recorded = false;
						if (!$recorded) {
							$order[]  = 'component_sanitize';
							$recorded = true;
						}
						return trim((string) $value);
					},
				),
				ValidatorPipelineService::BUCKET_SCHEMA => array(
					static function (mixed $value, callable $emitNotice) use (&$order) {
						static $recorded = false;
						if (!$recorded) {
							$order[]  = 'schema_sanitize';
							$recorded = true;
						}
						return $value;
					},
				),
			),
			'validate' => array(
				ValidatorPipelineService::BUCKET_COMPONENT => array(
					static function (mixed $value, callable $emitWarning) use (&$order): bool {
						$order[] = 'component_validate';
						return true;
					},
				),
				ValidatorPipelineService::BUCKET_SCHEMA => array(
					static function (mixed $value, callable $emitWarning) use (&$order, &$warnings): bool {
						$order[] = 'schema_validate';
						$emitWarning('schema failed');
						return false;
					},
				),
			),
		);

		$result = $service->sanitize_and_validate(
			'field_a',
			'  example  ',
			$rules,
			'TestHost',
			$logger,
			static function (callable $callable): string {
				return is_array($callable) ? 'callable@array' : 'callable@closure';
			},
			static function (mixed $subject): string {
				return is_scalar($subject) ? (string) $subject : gettype($subject);
			},
			function (string $key, string $message) use (&$notices): void {
				$notices[$key][] = $message;
			},
			function (string $key, string $message) use (&$warnings): void {
				$warnings[$key][] = $message;
			}
		);

		self::assertSame('example', $result);
		self::assertSame(array('component_sanitize', 'schema_sanitize', 'component_validate', 'schema_validate'), $order);
		self::assertArrayNotHasKey('field_a', $notices);
		self::assertSame(array('schema failed'), $warnings['field_a'] ?? array());

		$debugLogs = $logger->find_logs(static function (array $entry): bool {
			return $entry['message'] === 'TestHost: running sanitizer' || $entry['message'] === 'TestHost: running validator';
		});
		self::assertNotEmpty($debugLogs, 'Expected debug logs capturing bucket execution.');
	}
}
