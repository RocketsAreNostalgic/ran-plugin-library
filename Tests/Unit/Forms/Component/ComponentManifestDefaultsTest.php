<?php
/**
 * ComponentManifest defaults catalogue tests.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms;

use WP_Mock;
use Ran\PluginLib\Util\ExpectLogTrait;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Tests\Unit\Forms\Stubs\StubValidatorWithDefaults;
use Ran\PluginLib\Tests\Unit\Forms\Stubs\StubNormalizerWithDefaults;
use Ran\PluginLib\Tests\Unit\Forms\Stubs\StubBuilderWithDefaults;
use Ran\PluginLib\Forms\FormsTemplateOverrideResolver;
use Ran\PluginLib\Forms\FormsServiceSession;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use Ran\PluginLib\Forms\Component\ComponentLoader;
use Ran\PluginLib\Forms\Component\Cache\ComponentCacheService;
use Mockery\MockInterface;
use Mockery;

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
		$this->loader->shouldReceive('get_cache_service')->andReturn(new ComponentCacheService($this->logger_mock));

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
	 * Defaults are now empty - component_type comes from View's ComponentRenderResult at render time.
	 *
	 * @return array<string,mixed>
	 */
	private function expectedDefaults(): array {
		return array();
	}

	public function test_manifest_collects_defaults_from_normalizer(): void {
		WP_Mock::userFunction('wp_get_environment_type')->andReturn('development');

		$this->loader->shouldReceive('aliases')->andReturn(array('sample.component' => 'components/sample.php'));
		$this->loader->shouldReceive('resolve_normalizer_class')->andReturn(StubNormalizerWithDefaults::class);
		$this->loader->shouldReceive('resolve_builder_class')->andReturn(null);
		$this->loader->shouldReceive('resolve_validator_class')->andReturn(null);
		$this->loader->shouldReceive('resolve_sanitizer_class')->andReturn(null);

		$this->resetLogs();
		$manifest = new ComponentManifest($this->loader, $this->logger_mock);

		// Defaults are now empty - component_type comes from View's ComponentRenderResult at render time.
		// Sanitizers and validators are injected via factory -> queue -> merge path.
		$this->assertSame($this->expectedDefaults(), $manifest->get_defaults_for('sample.component'));
		$this->assertSame(array('sample.component' => $this->expectedDefaults()), $manifest->default_catalogue());
		// With normalizer present, defaults are considered "discovered" (sanitize sources exist)
		$this->expectLog('debug', 'ComponentManifest: defaults missing for component');
	}

	public function test_manifest_logs_when_defaults_missing(): void {
		WP_Mock::userFunction('wp_get_environment_type')->andReturn('development');

		$this->loader->shouldReceive('aliases')->andReturn(array('empty.component' => 'components/empty.php'));
		$this->loader->shouldReceive('resolve_normalizer_class')->andReturn(null);
		$this->loader->shouldReceive('resolve_builder_class')->andReturn(null);
		$this->loader->shouldReceive('resolve_validator_class')->andReturn(null);
		$this->loader->shouldReceive('resolve_sanitizer_class')->andReturn(null);

		$this->resetLogs();
		$manifest = new ComponentManifest($this->loader, $this->logger_mock);

		$this->assertSame($this->expectedDefaults(), $manifest->get_defaults_for('empty.component'));
		$this->expectLog('debug', 'ComponentManifest: defaults missing for component');
	}

	public function test_manifest_with_only_builder_sets_context_defaults(): void {
		WP_Mock::userFunction('wp_get_environment_type')->andReturn('development');

		$this->loader->shouldReceive('aliases')->andReturn(array('builder.component' => 'components/builder.php'));
		$this->loader->shouldReceive('resolve_normalizer_class')->andReturn(null);
		$this->loader->shouldReceive('resolve_builder_class')->andReturn(StubBuilderWithDefaults::class);
		$this->loader->shouldReceive('resolve_validator_class')->andReturn(null);
		$this->loader->shouldReceive('resolve_sanitizer_class')->andReturn(null);

		$this->resetLogs();
		$manifest = new ComponentManifest($this->loader, $this->logger_mock);

		$this->assertSame($this->expectedDefaults(), $manifest->get_defaults_for('builder.component'));
		$this->expectLog('debug', 'ComponentManifest: defaults missing for component');
	}

	public function test_manifest_falls_back_to_validator_defaults(): void {
		WP_Mock::userFunction('wp_get_environment_type')->andReturn('development');

		$this->loader->shouldReceive('aliases')->andReturn(array('validator.component' => 'components/validator.php'));
		$this->loader->shouldReceive('resolve_normalizer_class')->andReturn(null);
		$this->loader->shouldReceive('resolve_builder_class')->andReturn(null);
		$this->loader->shouldReceive('resolve_validator_class')->andReturn(StubValidatorWithDefaults::class);
		$this->loader->shouldReceive('resolve_sanitizer_class')->andReturn(null);

		$this->resetLogs();
		$manifest = new ComponentManifest($this->loader, $this->logger_mock);

		// Defaults are now empty - component_type comes from View's ComponentRenderResult at render time.
		// Validators are injected via factory -> queue -> merge path.
		$this->assertSame($this->expectedDefaults(), $manifest->get_defaults_for('validator.component'));
		// With validator present, validate sources exist
		$this->expectLog('debug', 'ComponentManifest: defaults discovered for component');
	}

	public function test_defaults_are_cached_and_restored(): void {
		WP_Mock::userFunction('wp_get_environment_type')->andReturn('production');

		$this->loader->shouldReceive('aliases')->andReturn(array('cached.component' => 'components/cached.php'));
		$this->loader->shouldReceive('resolve_normalizer_class')->andReturn(StubNormalizerWithDefaults::class);
		$this->loader->shouldReceive('resolve_builder_class')->andReturn(null);
		$this->loader->shouldReceive('resolve_validator_class')->andReturn(null);
		$this->loader->shouldReceive('resolve_sanitizer_class')->andReturn(null);

		$this->resetLogs();
		$firstManifest = new ComponentManifest($this->loader, $this->logger_mock);

		// Defaults are now empty - component_type comes from View's ComponentRenderResult at render time.
		$cacheKey = 'kepler_comp_meta_cached.component';
		$this->assertArrayHasKey($cacheKey, $this->cachedMetadata);
		$this->assertSame(
			$this->expectedDefaults(),
			$this->cachedMetadata[$cacheKey]['defaults'] ?? null
		);
		// Logging now happens in ComponentCacheService
		$this->expectLog('debug', 'ComponentCacheService: Cached value');

		$this->resetLogs();
		$secondManifest = new ComponentManifest($this->loader, $this->logger_mock);
		$this->assertSame(
			$this->expectedDefaults(),
			$secondManifest->get_defaults_for('cached.component')
		);
		// Logging now happens in ComponentCacheService
		$this->expectLog('debug', 'ComponentCacheService: Cache HIT');
	}

	/**
	 * Regression test: Ensures factory return arrays have exactly 2 unique keys.
	 *
	 * Previously, a copy-paste error caused duplicate 'result' and 'warnings' keys
	 * in the factory return array. PHP silently resolves these at parse time, but
	 * the code was confusing and could trigger static analysis warnings.
	 *
	 * @see 15-Component-Handling-Optimization-Plan.md Workstream A
	 */
	public function test_factory_return_has_exactly_two_keys(): void {
		WP_Mock::userFunction('wp_get_environment_type')->andReturn('development');

		$this->loader->shouldReceive('aliases')->andReturn(array('test.component' => 'components/test.php'));
		$this->loader->shouldReceive('resolve_normalizer_class')->andReturn(StubNormalizerWithDefaults::class);
		$this->loader->shouldReceive('resolve_builder_class')->andReturn(null);
		$this->loader->shouldReceive('resolve_validator_class')->andReturn(null);
		$this->loader->shouldReceive('resolve_sanitizer_class')->andReturn(null);
		$this->loader->shouldReceive('render')->andReturn('<div>test</div>');
		$this->loader->shouldReceive('render_payload')->andReturn(array(
			'markup' => '<div>test</div>',
		));

		$manifest = new ComponentManifest($this->loader, $this->logger_mock);

		// Get the registered factory via reflection
		$reflection         = new \ReflectionClass($manifest);
		$componentsProperty = $reflection->getProperty('components');
		$componentsProperty->setAccessible(true);
		$components = $componentsProperty->getValue($manifest);

		$this->assertArrayHasKey('test.component', $components);
		$factory = $components['test.component'];

		// Call the factory with minimal context
		$result = $factory(array('id' => 'test', 'name' => 'test', 'value' => ''));

		// Verify exactly 2 keys (no duplicates)
		$this->assertCount(2, $result, 'Factory should return exactly 2 keys');
		$this->assertArrayHasKey('result', $result);
		$this->assertArrayHasKey('warnings', $result);
	}

	public function test_forms_service_session_exposes_manifest_defaults(): void {
		WP_Mock::userFunction('wp_get_environment_type')->andReturn('development');

		$this->loader->shouldReceive('aliases')->andReturn(array('session.component' => 'components/session.php'));
		$this->loader->shouldReceive('resolve_normalizer_class')->andReturn(StubNormalizerWithDefaults::class);
		$this->loader->shouldReceive('resolve_builder_class')->andReturn(null);
		$this->loader->shouldReceive('resolve_validator_class')->andReturn(null);
		$this->loader->shouldReceive('resolve_sanitizer_class')->andReturn(null);

		$manifest = new ComponentManifest($this->loader, $this->logger_mock);

		$resolver = new FormsTemplateOverrideResolver($this->logger_mock);
		$session  = new FormsServiceSession($manifest, $resolver, $this->logger_mock);
		// Defaults are now empty - component_type comes from View's ComponentRenderResult at render time.
		$this->assertSame(
			array('session.component' => $this->expectedDefaults()),
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
