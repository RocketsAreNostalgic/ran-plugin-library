<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms;

use Ran\PluginLib\Util\CollectingLogger;
use Ran\PluginLib\Options\Storage\StorageContext;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Forms\Services\FormsValidatorServiceInterface;
use Ran\PluginLib\Forms\Services\FormsSchemaService;
use Ran\PluginLib\Forms\FormsServiceSession;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Ran\PluginLib\Forms\Services\FormsSchemaService
 */
final class FormsSchemaServiceTest extends TestCase {
	public function test_resolve_schema_bundle_extracts_defaults_and_caches_by_context(): void {
		$schema_bundle_cache = array();
		$catalogue_cache     = null;
		$logger              = new CollectingLogger();

		$session_calls = array(
			'get'   => 0,
			'start' => 0,
		);

		$get_form_session = function () use (&$session_calls): ?FormsServiceSession {
			$session_calls['get']++;
			return null;
		};

		$start_form_session = function () use (&$session_calls): void {
			$session_calls['start']++;
		};

		$validator_service = $this->createMock(FormsValidatorServiceInterface::class);
		$components        = $this->createMock(ComponentManifest::class);

		$svc = new FormsSchemaService(
			$this->createMock(RegisterOptions::class),
			$components,
			$logger,
			$validator_service,
			'test-host',
			$schema_bundle_cache,
			$catalogue_cache,
			$get_form_session,
			$start_form_session,
			static fn (): array => array()
		);

		$site_options = $this->createMock(RegisterOptions::class);
		$site_options->expects(self::exactly(2))->method('get_main_option_name')->willReturn('opt');
		$site_options->expects(self::exactly(2))->method('get_storage_context')->willReturn(StorageContext::forSite());
		$site_options->expects(self::once())->method('__get_schema_internal')->willReturn(array(
			'foo' => array(
				'default'  => 123,
				'sanitize' => array('component' => array(), 'schema' => array()),
				'validate' => array('component' => array(), 'schema' => array()),
			),
			'bar' => array(
				'sanitize' => array('component' => array(), 'schema' => array()),
				'validate' => array('component' => array(), 'schema' => array()),
			),
		));

		$bundle1 = $svc->resolve_schema_bundle($site_options);
		self::assertArrayHasKey('defaults', $bundle1);
		self::assertSame(array('foo' => array('default' => 123)), $bundle1['defaults']);

		// Second call should be cache hit - no additional calls to underlying option object or session start.
		$bundle2 = $svc->resolve_schema_bundle($site_options);
		self::assertSame($bundle1, $bundle2);
		self::assertSame(1, $session_calls['start']);

		$user_options = $this->createMock(RegisterOptions::class);
		$user_options->expects(self::once())->method('get_main_option_name')->willReturn('opt');
		$user_options->expects(self::once())->method('get_storage_context')->willReturn(StorageContext::forUserId(77));
		$user_options->expects(self::once())->method('__get_schema_internal')->willReturn(array());

		$bundle3 = $svc->resolve_schema_bundle($user_options);
		self::assertNotSame($bundle1, $bundle3);
		self::assertCount(2, $schema_bundle_cache);
	}

	public function test_merge_schema_bundle_sources_seeds_and_overlays_defaults(): void {
		$schema_bundle_cache = array();
		$catalogue_cache     = null;
		$logger              = new CollectingLogger();

		$validator_service = $this->createMock(FormsValidatorServiceInterface::class);
		$components        = $this->createMock(ComponentManifest::class);

		$svc = new FormsSchemaService(
			$this->createMock(RegisterOptions::class),
			$components,
			$logger,
			$validator_service,
			'test-host',
			$schema_bundle_cache,
			$catalogue_cache,
			static fn (): ?FormsServiceSession => null,
			static function (): void {
			},
			static fn (): array => array()
		);

		$bundle = array(
			'bucketed_schema' => array(
				'k1' => array(
					'sanitize' => array('component' => array('a'), 'schema' => array()),
					'validate' => array('component' => array(), 'schema' => array('b')),
				),
			),
			'schema' => array(
				'k2' => array('default' => 'from_schema'),
			),
			'defaults' => array(
				'k3' => array('default' => 'from_defaults'),
			),
			'metadata' => array('k1' => array('m' => 1)),
			'queued_validators' => array('k1' => array('v')),
			'queued_sanitizers' => array('k1' => array('s')),
		);

		$merged = $svc->merge_schema_bundle_sources($bundle);
		self::assertArrayHasKey('merged_schema', $merged);
		self::assertArrayHasKey('k1', $merged['merged_schema']);
		self::assertArrayHasKey('k2', $merged['merged_schema']);
		self::assertSame('from_schema', $merged['merged_schema']['k2']['default']);
		self::assertArrayHasKey('k3', $merged['merged_schema']);
		self::assertSame('from_defaults', $merged['merged_schema']['k3']['default']);
		self::assertSame(array('k1' => array('m' => 1)), $merged['metadata']);
		self::assertSame(array('k1' => array('v')), $merged['queued_validators']);
		self::assertSame(array('k1' => array('s')), $merged['queued_sanitizers']);
		self::assertSame(array('k3' => array('default' => 'from_defaults')), $merged['defaults_for_seeding']);
	}

	public function test_merge_schema_entry_buckets_merges_default_validate_sanitize_and_context(): void {
		$schema_bundle_cache = array();
		$catalogue_cache     = null;
		$logger              = new CollectingLogger();

		$validator_service = $this->createMock(FormsValidatorServiceInterface::class);
		$components        = $this->createMock(ComponentManifest::class);

		$svc = new FormsSchemaService(
			$this->createMock(RegisterOptions::class),
			$components,
			$logger,
			$validator_service,
			'test-host',
			$schema_bundle_cache,
			$catalogue_cache,
			static fn (): ?FormsServiceSession => null,
			static function (): void {
			},
			static fn (): array => array()
		);

		$existing = array(
			'sanitize' => array('component' => array('a'), 'schema' => array()),
			'validate' => array('component' => array(), 'schema' => array('b')),
			'context'  => array('x' => 1),
		);

		$incoming = array(
			'default'  => 10,
			'sanitize' => array('component' => array('c'), 'schema' => array('d')),
			'validate' => array('component' => array('e'), 'schema' => array()),
			'context'  => array('y' => 2),
		);

		$merged = $svc->merge_schema_entry_buckets($existing, $incoming);
		self::assertSame(10, $merged['default']);
		self::assertSame(array('a', 'c'), $merged['sanitize']['component']);
		self::assertSame(array('d'), $merged['sanitize']['schema']);
		self::assertSame(array('e'), $merged['validate']['component']);
		self::assertSame(array('b'), $merged['validate']['schema']);
		self::assertSame(array('x' => 1, 'y' => 2), $merged['context']);
	}

	public function test_assemble_initial_bucketed_schema_merges_defaults_and_consumes_validator_queues(): void {
		$schema_bundle_cache = array();
		$catalogue_cache     = null;
		$logger              = new CollectingLogger();

		$base_options = $this->createMock(RegisterOptions::class);
		$base_options->method('__get_schema_internal')->willReturn(array());
		$base_options->method('normalize_schema_key')->willReturn('normalized');

		$session = $this->createMock(FormsServiceSession::class);
		$session->expects(self::once())
			->method('merge_schema_with_defaults')
			->with(
				'fields.input',
				array('default' => 'x'),
				array('fields.input' => array('default' => 'manifest'))
			)
			->willReturn(array(
				'sanitize' => array('component' => array(), 'schema' => array()),
				'validate' => array('component' => array(), 'schema' => array()),
				'default'  => 'x',
			));

		$components = $this->createMock(ComponentManifest::class);
		$components->expects(self::once())
			->method('default_catalogue')
			->willReturn(array('fields.input' => array('default' => 'manifest')));
		$components->method('validator_factories')->willReturn(array(
			'fields.input' => static function (): void {
			},
		));

		$validator_service = $this->createMock(FormsValidatorServiceInterface::class);
		$validator_service->expects(self::once())
			->method('consume_component_validator_queue')
			->with(self::callback(static function (array $bucketed): bool {
				return isset($bucketed['normalized']);
			}))
			->willReturn(array(
				array(
					'normalized' => array(
						'sanitize' => array('component' => array(), 'schema' => array()),
						'validate' => array('component' => array(), 'schema' => array()),
						'default'  => 'x',
					),
				),
				array('normalized' => array('v1')),
			));
		$validator_service->expects(self::once())
			->method('consume_component_sanitizer_queue')
			->with(self::callback(static function (array $bucketed): bool {
				return isset($bucketed['normalized']);
			}))
			->willReturn(array(
				array(
					'normalized' => array(
						'sanitize' => array('component' => array(), 'schema' => array()),
						'validate' => array('component' => array(), 'schema' => array()),
						'default'  => 'x',
					),
				),
				array('normalized' => array('s1')),
			));

		$get_registered_field_metadata = static fn (): array => array(
			array(
				'field' => array(
					'id'        => 'field_id',
					'component' => 'fields.input',
					'schema'    => array('default' => 'x'),
				),
			),
		);

		$svc = new FormsSchemaService(
			$base_options,
			$components,
			$logger,
			$validator_service,
			'test-host',
			$schema_bundle_cache,
			$catalogue_cache,
			static fn (): ?FormsServiceSession => $session,
			static function (): void {
			},
			$get_registered_field_metadata
		);

		$result = $svc->assemble_initial_bucketed_schema($session);
		self::assertArrayHasKey('schema', $result);
		self::assertArrayHasKey('normalized', $result['schema']);
		self::assertArrayHasKey('queued_validators', $result);
		self::assertSame(array('normalized' => array('v1')), $result['queued_validators']);
		self::assertSame(array('normalized' => array('s1')), $result['queued_sanitizers']);
		self::assertArrayHasKey('metadata', $result);
		self::assertTrue((bool) ($result['metadata']['normalized']['requires_validator'] ?? false));

		self::assertIsArray($catalogue_cache);
		self::assertSame(array('fields.input' => array('default' => 'manifest')), $catalogue_cache);
	}
}
