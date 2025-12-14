<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms;

use PHPUnit\Framework\TestCase;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use Ran\PluginLib\Forms\Services\FormsValidatorService;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Util\Logger;

/**
 * @coversDefaultClass \Ran\PluginLib\Forms\Services\FormsValidatorService
 */
final class FormsValidatorServiceTest extends TestCase {
	public function test_inject_component_validators_queues_callable_under_normalized_key(): void {
		$queued_validators = array();
		$queued_sanitizers = array();

		$base_options = $this->createMock(RegisterOptions::class);
		$base_options->method('normalize_schema_key')->willReturn('normalized_key');
		$base_options->method('has_schema_key')->willReturn(false);

		$calls              = array();
		$validator_instance = new class($calls) {
			public array $calls;
			public function __construct(array &$calls) {
				$this->calls = & $calls;
			}
			public function validate($value, array $context, callable $emitWarning): bool {
				$this->calls[] = array('value' => $value, 'context' => $context);
				return true;
			}
		};

		$components = $this->createMock(ComponentManifest::class);
		$components->method('validator_factories')->willReturn(array(
			'fields.input' => function () use ($validator_instance) {
				return $validator_instance;
			},
		));

		$logger = $this->createMock(Logger::class);

		$svc = new FormsValidatorService($base_options, $components, $logger, $queued_validators, $queued_sanitizers);
		$svc->inject_component_validators('field_id', 'fields.input', array('a' => 1));

		self::assertArrayHasKey('normalized_key', $queued_validators);
		self::assertCount(1, $queued_validators['normalized_key']);
		self::assertIsCallable($queued_validators['normalized_key'][0]);

		$emit = function (string $msg): void {
		};
		$queued_validators['normalized_key'][0]('v', $emit);
		self::assertSame(array(array('value' => 'v', 'context' => array('a' => 1))), $calls);
	}

	public function test_inject_component_sanitizers_queues_callable_under_normalized_key(): void {
		$queued_validators = array();
		$queued_sanitizers = array();

		$base_options = $this->createMock(RegisterOptions::class);
		$base_options->method('normalize_schema_key')->willReturn('normalized_key');
		$base_options->method('has_schema_key')->willReturn(false);

		$calls              = array();
		$sanitizer_instance = new class($calls) {
			public array $calls;
			public function __construct(array &$calls) {
				$this->calls = & $calls;
			}
			public function sanitize($value, array $context, callable $emitNotice): mixed {
				$this->calls[] = array('value' => $value, 'context' => $context);
				return 'sanitized';
			}
		};

		$components = $this->createMock(ComponentManifest::class);
		$components->method('sanitizer_factories')->willReturn(array(
			'fields.input' => function () use ($sanitizer_instance) {
				return $sanitizer_instance;
			},
		));

		$logger = $this->createMock(Logger::class);

		$svc = new FormsValidatorService($base_options, $components, $logger, $queued_validators, $queued_sanitizers);
		$svc->inject_component_sanitizers('field_id', 'fields.input', array('b' => 2));

		self::assertArrayHasKey('normalized_key', $queued_sanitizers);
		self::assertCount(1, $queued_sanitizers['normalized_key']);
		self::assertIsCallable($queued_sanitizers['normalized_key'][0]);

		$emit = function (string $msg): void {
		};
		$result = $queued_sanitizers['normalized_key'][0]('v', $emit);
		self::assertSame('sanitized', $result);
		self::assertSame(array(array('value' => 'v', 'context' => array('b' => 2))), $calls);
	}

	public function test_consume_component_validator_queue_matches_schema_keys_and_requeues_unmatched(): void {
		$queued_validators = array(
			'k1' => array(function (): void {
			}),
			'k2' => array(function (): void {
			}),
		);
		$queued_sanitizers = array();

		$base_options = $this->createMock(RegisterOptions::class);
		$components   = $this->createMock(ComponentManifest::class);
		$logger       = $this->createMock(Logger::class);

		$svc = new FormsValidatorService($base_options, $components, $logger, $queued_validators, $queued_sanitizers);

		$bucketed = array(
			'k1' => array('x' => 1),
		);

		list($schema, $queued_for_schema) = $svc->consume_component_validator_queue($bucketed);

		self::assertSame($bucketed, $schema);
		self::assertArrayHasKey('k1', $queued_for_schema);
		self::assertCount(1, $queued_for_schema['k1']);

		// k2 should be re-queued since it did not match a schema key
		self::assertArrayHasKey('k2', $queued_validators);
		self::assertCount(1, $queued_validators['k2']);
	}

	public function test_consume_component_sanitizer_queue_matches_schema_keys_and_requeues_unmatched(): void {
		$queued_validators = array();
		$queued_sanitizers = array(
			'k1' => array(function (): void {
			}),
			'k2' => array(function (): void {
			}),
		);

		$base_options = $this->createMock(RegisterOptions::class);
		$components   = $this->createMock(ComponentManifest::class);
		$logger       = $this->createMock(Logger::class);

		$svc = new FormsValidatorService($base_options, $components, $logger, $queued_validators, $queued_sanitizers);

		$bucketed = array(
			'k1' => array('x' => 1),
		);

		list($schema, $queued_for_schema) = $svc->consume_component_sanitizer_queue($bucketed);

		self::assertSame($bucketed, $schema);
		self::assertArrayHasKey('k1', $queued_for_schema);
		self::assertCount(1, $queued_for_schema['k1']);

		// k2 should be re-queued since it did not match a schema key
		self::assertArrayHasKey('k2', $queued_sanitizers);
		self::assertCount(1, $queued_sanitizers['k2']);
	}
}
