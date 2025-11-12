<?php
/**
 * ComponentManifest defaults catalogue tests.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms;

use Mockery;
use Mockery\MockInterface;
use Ran\PluginLib\Forms\Component\ComponentLoader;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use Ran\PluginLib\Forms\FormsAssets;
use Ran\PluginLib\Forms\FormsServiceSession;
use Ran\PluginLib\Tests\Unit\Forms\Stubs\StubBuilderWithDefaults;
use Ran\PluginLib\Tests\Unit\Forms\Stubs\StubNormalizerWithDefaults;
use Ran\PluginLib\Tests\Unit\Forms\Stubs\StubValidatorWithDefaults;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Util\ExpectLogTrait;
use WP_Mock;

final class ComponentManifestDefaultsTest extends PluginLibTestCase {
	use ExpectLogTrait;

	/** @var ComponentLoader|MockInterface */
	private ComponentLoader|MockInterface $loader;
	/** @var array<string,mixed> */
	private array $cachedMetadata = array();

	public function setUp(): void {
		parent::setUp();

		$this->loader = Mockery::mock(ComponentLoader::class);
		$this->loader->shouldReceive('register')->zeroOrMoreTimes();

		$this->cachedMetadata = array();

		WP_Mock::userFunction('get_option')->andReturn(array());
		WP_Mock::userFunction('update_option')->andReturn(true);
		WP_Mock::userFunction('delete_option')->andReturn(true);
		WP_Mock::userFunction('get_transient')->andReturnUsing(function (string $key): array|false {
			return $this->cachedMetadata[$key] ?? false;
		});
		WP_Mock::userFunction('set_transient')->andReturnUsing(function (string $key, mixed $value, int $ttl): bool {
			$this->cachedMetadata[$key] = $value;
			return true;
		});
		WP_Mock::userFunction('delete_transient')->andReturnUsing(function (string $key): bool {
			unset($this->cachedMetadata[$key]);
			return true;
		});
	}

	private function resetLogs(): void {
		$this->logger_mock->collected_logs = array();
	}

	/**
	 * @return array{submits_data:bool,component_type:string,repeatable:bool}
	 */
	private function expectedContext(): array {
		return array(
			'submits_data'   => false,
			'component_type' => 'input',
			'repeatable'     => false,
		);
	}

	public function test_manifest_collects_defaults_from_normalizer(): void {
		WP_Mock::userFunction('wp_get_environment_type')->andReturn('development');

		$this->loader->shouldReceive('aliases')->andReturn(array('sample.component' => 'components/sample.php'));
		$this->loader->shouldReceive('resolve_normalizer_class')->andReturn(StubNormalizerWithDefaults::class);
		$this->loader->shouldReceive('resolve_builder_class')->andReturn(null);
		$this->loader->shouldReceive('resolve_validator_class')->andReturn(null);

		$this->resetLogs();
		$manifest = new ComponentManifest($this->loader, $this->logger_mock);

		$expected = array(
			'sanitize' => array(StubNormalizerWithDefaults::class),
			'context'  => $this->expectedContext(),
		);
		$this->assertSame($expected, $manifest->get_defaults_for('sample.component'));
		$this->assertSame(array('sample.component' => $expected), $manifest->default_catalogue());
		$this->expectLog('debug', 'ComponentManifest: defaults discovered for component');
	}

	public function test_manifest_logs_when_defaults_missing(): void {
		WP_Mock::userFunction('wp_get_environment_type')->andReturn('development');

		$this->loader->shouldReceive('aliases')->andReturn(array('empty.component' => 'components/empty.php'));
		$this->loader->shouldReceive('resolve_normalizer_class')->andReturn(null);
		$this->loader->shouldReceive('resolve_builder_class')->andReturn(null);
		$this->loader->shouldReceive('resolve_validator_class')->andReturn(null);

		$this->resetLogs();
		$manifest = new ComponentManifest($this->loader, $this->logger_mock);

		$this->assertSame(array('context' => $this->expectedContext()), $manifest->get_defaults_for('empty.component'));
		$this->expectLog('debug', 'ComponentManifest: defaults missing for component');
	}

	public function test_manifest_with_only_builder_sets_context_defaults(): void {
		WP_Mock::userFunction('wp_get_environment_type')->andReturn('development');

		$this->loader->shouldReceive('aliases')->andReturn(array('builder.component' => 'components/builder.php'));
		$this->loader->shouldReceive('resolve_normalizer_class')->andReturn(null);
		$this->loader->shouldReceive('resolve_builder_class')->andReturn(StubBuilderWithDefaults::class);
		$this->loader->shouldReceive('resolve_validator_class')->andReturn(null);

		$this->resetLogs();
		$manifest = new ComponentManifest($this->loader, $this->logger_mock);

		$this->assertSame(array('context' => $this->expectedContext()), $manifest->get_defaults_for('builder.component'));
		$this->expectLog('debug', 'ComponentManifest: defaults missing for component');
	}

	public function test_manifest_falls_back_to_validator_defaults(): void {
		WP_Mock::userFunction('wp_get_environment_type')->andReturn('development');

		$this->loader->shouldReceive('aliases')->andReturn(array('validator.component' => 'components/validator.php'));
		$this->loader->shouldReceive('resolve_normalizer_class')->andReturn(null);
		$this->loader->shouldReceive('resolve_builder_class')->andReturn(null);
		$this->loader->shouldReceive('resolve_validator_class')->andReturn(StubValidatorWithDefaults::class);

		$this->resetLogs();
		$manifest = new ComponentManifest($this->loader, $this->logger_mock);

		$expected = array(
			'validate' => array(StubValidatorWithDefaults::class),
			'context'  => $this->expectedContext(),
		);
		$this->assertSame($expected, $manifest->get_defaults_for('validator.component'));
		$this->expectLog('debug', 'ComponentManifest: defaults discovered for component');
	}

	public function test_defaults_are_cached_and_restored(): void {
		WP_Mock::userFunction('wp_get_environment_type')->andReturn('production');

		$this->loader->shouldReceive('aliases')->andReturn(array('cached.component' => 'components/cached.php'));
		$this->loader->shouldReceive('resolve_normalizer_class')->andReturn(StubNormalizerWithDefaults::class);
		$this->loader->shouldReceive('resolve_builder_class')->andReturn(null);
		$this->loader->shouldReceive('resolve_validator_class')->andReturn(null);

		$this->resetLogs();
		$firstManifest = new ComponentManifest($this->loader, $this->logger_mock);

		$cacheKey = 'kepler_comp_meta_cached.component';
		$this->assertArrayHasKey($cacheKey, $this->cachedMetadata);
		$this->assertSame(
			array(
				'sanitize' => array(StubNormalizerWithDefaults::class),
				'context'  => $this->expectedContext(),
			),
			$this->cachedMetadata[$cacheKey]['defaults'] ?? null
		);
		$this->expectLog('debug', 'ComponentManifest: Cache MISS for component');
		$this->expectLog('debug', 'ComponentManifest: Cached component metadata');

		$this->resetLogs();
		$secondManifest = new ComponentManifest($this->loader, $this->logger_mock);
		$this->assertSame(
			array(
				'sanitize' => array(StubNormalizerWithDefaults::class),
				'context'  => $this->expectedContext(),
			),
			$secondManifest->get_defaults_for('cached.component')
		);
		$this->expectLog('debug', 'ComponentManifest: Cache HIT for component');
	}

	public function test_forms_service_session_exposes_manifest_defaults(): void {
		WP_Mock::userFunction('wp_get_environment_type')->andReturn('development');

		$this->loader->shouldReceive('aliases')->andReturn(array('session.component' => 'components/session.php'));
		$this->loader->shouldReceive('resolve_normalizer_class')->andReturn(StubNormalizerWithDefaults::class);
		$this->loader->shouldReceive('resolve_builder_class')->andReturn(null);
		$this->loader->shouldReceive('resolve_validator_class')->andReturn(null);

		$manifest = new ComponentManifest($this->loader, $this->logger_mock);

		$session = new FormsServiceSession($manifest, new FormsAssets(), $this->logger_mock);
		$this->assertSame(
			array('session.component' => array(
				'sanitize' => array(StubNormalizerWithDefaults::class),
				'context'  => $this->expectedContext(),
			)),
			$session->manifest_defaults()
		);
	}
}

namespace Ran\PluginLib\Tests\Unit\Forms\Stubs;

use Ran\PluginLib\Forms\Component\Build\ComponentBuilderBase;
use Ran\PluginLib\Forms\Component\Normalize\NormalizerBase;
use Ran\PluginLib\Forms\Component\Validate\ValidatorBase;
use Ran\PluginLib\Util\TranslationService;


final class StubNormalizerWithDefaults extends NormalizerBase {
	protected function _normalize_component_specific(array $context): array {
		return $context;
	}
}

final class StubBuilderWithDefaults extends ComponentBuilderBase {
	public function __construct(string $id = 'builder', string $label = 'Builder', ?TranslationService $translator = null) {
		parent::__construct($id, $label, $translator);
	}

	protected function _build_component_context(): array {
		return $this->_build_base_context();
	}

	protected function _get_component(): string {
		return 'stub.builder';
	}
}

final class StubValidatorWithDefaults extends ValidatorBase {
	protected function _validate_component(mixed $value, array $context, callable $emitWarning): bool {
		return true;
	}
}
