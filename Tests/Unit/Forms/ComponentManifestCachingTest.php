<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms;

use Mockery;
use Mockery\MockInterface;
use WP_Mock;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use Ran\PluginLib\Forms\Component\ComponentLoader;
use Ran\PluginLib\Forms\Component\Cache\ComponentCacheService;
use Ran\PluginLib\Util\ExpectLogTrait;

/**
 * Test ComponentManifest caching functionality.
 */
class ComponentManifestCachingTest extends PluginLibTestCase {
	use ExpectLogTrait;
	/** @var ComponentLoader|MockInterface */
	private ComponentLoader|MockInterface $loader;
	/** @var array<string,string> */
	private array $aliases = array();
	/** @var array<string,mixed> */
	private array $cachedMetadata = array();

	public function setUp(): void {
		parent::setUp();

		$this->aliases        = array();
		$this->cachedMetadata = array();

		$instance = $this;

		$this->loader = Mockery::mock(ComponentLoader::class);
		$this->loader->shouldReceive('register')->zeroOrMoreTimes();
		$this->loader->shouldReceive('aliases')->andReturnUsing(static function () use ($instance): array {
			return $instance->aliases;
		});
		$this->loader->shouldReceive('resolve_normalizer_class')->andReturn(null);
		$this->loader->shouldReceive('resolve_builder_class')->andReturn(null);
		$this->loader->shouldReceive('resolve_validator_class')->andReturn(null);
		$this->loader->shouldReceive('resolve_sanitizer_class')->andReturn(null);
		$this->loader->shouldReceive('get_cache_service')->andReturn(new ComponentCacheService($this->logger_mock));

		WP_Mock::userFunction('get_transient')->andReturnUsing(static function (string $key) use ($instance) {
			return $instance->cachedMetadata[$key] ?? false;
		});
		WP_Mock::userFunction('set_transient')->andReturnUsing(static function (string $key, mixed $value, int $ttl) use ($instance): bool {
			$instance->cachedMetadata[$key] = $value;
			return true;
		});
		WP_Mock::userFunction('delete_transient')->andReturnUsing(static function (string $key) use ($instance): bool {
			unset($instance->cachedMetadata[$key]);
			return true;
		});
		WP_Mock::userFunction('get_option')->andReturn(array());
		WP_Mock::userFunction('update_option')->andReturn(true);
		WP_Mock::userFunction('delete_option')->andReturn(true);
		WP_Mock::userFunction('wp_get_environment_type')->andReturn('production');

		$this->logger_mock->collected_logs = array();
	}

	public function test_register_alias_logs_cache_miss_and_caches_metadata(): void {
		$this->aliases        = array('example-component' => 'components/example.php');
		$this->cachedMetadata = array();

		$this->resetLogs();
		$manifest = $this->createManifest();

		// Verify metadata was cached
		self::assertArrayHasKey('kepler_comp_meta_example-component', $this->cachedMetadata);
		// Logging now happens in ComponentCacheService
		$this->expectLog('debug', 'ComponentCacheService: Cached value');
	}

	public function test_register_alias_reads_from_cache_and_logs_hit(): void {
		$this->aliases        = array('cached-component' => 'components/cached.php');
		$this->cachedMetadata = array(
			'kepler_comp_meta_cached-component' => array('normalizer' => null, 'builder' => null, 'validator' => null),
		);

		$this->resetLogs();
		$this->createManifest();

		// Logging now happens in ComponentCacheService
		$this->expectLog('debug', 'ComponentCacheService: Cache HIT');
	}

	public function test_clear_cache_logs_and_removes_entries(): void {
		$this->aliases        = array('demo-component' => 'components/demo.php');
		$this->cachedMetadata = array(
			'kepler_comp_meta_demo-component' => array('normalizer' => null, 'builder' => null, 'validator' => null),
		);
		$manifest = $this->createManifest();

		$this->resetLogs();
		$manifest->clear_cache('demo-component');

		self::assertArrayNotHasKey('kepler_comp_meta_demo-component', $this->cachedMetadata);
		// Logging now happens in ComponentCacheService
		$this->expectLog('debug', 'ComponentCacheService: Deleted cache entry');

		$this->resetLogs();
		$manifest->clear_cache();
		$this->expectLog('info', 'ComponentCacheService: Cleared all cache entries');
	}

	/**
	 * Test that validator_factories() returns memoized factory map.
	 *
	 * Multiple calls should return the same array without rebuilding.
	 */
	public function test_validator_factories_returns_memoized_map(): void {
		$this->aliases        = array('test.component' => 'components/test.php');
		$this->cachedMetadata = array(
			'kepler_comp_meta_test.component' => array(
				'normalizer' => null,
				'builder'    => null,
				'validator'  => StubValidator::class,
				'sanitizer'  => null,
			),
		);

		$manifest = $this->createManifest();

		// First call builds the factory map
		$factories1 = $manifest->validator_factories();
		// Second call should return the same memoized map
		$factories2 = $manifest->validator_factories();

		$this->assertSame($factories1, $factories2, 'Factory map should be memoized');
		$this->assertArrayHasKey('test.component', $factories1);
	}

	/**
	 * Test that validator factory returns cached instance.
	 *
	 * Multiple calls to the same factory should return the same instance.
	 */
	public function test_validator_factory_returns_cached_instance(): void {
		$this->aliases        = array('test.component' => 'components/test.php');
		$this->cachedMetadata = array(
			'kepler_comp_meta_test.component' => array(
				'normalizer' => null,
				'builder'    => null,
				'validator'  => StubValidator::class,
				'sanitizer'  => null,
			),
		);

		$manifest  = $this->createManifest();
		$factories = $manifest->validator_factories();
		$factory   = $factories['test.component'];

		// First call creates the instance
		$instance1 = $factory();
		// Second call should return the same cached instance
		$instance2 = $factory();

		$this->assertSame($instance1, $instance2, 'Factory should return cached instance');
		$this->assertInstanceOf(StubValidator::class, $instance1);
	}

	/**
	 * Test that sanitizer_factories() returns memoized factory map.
	 */
	public function test_sanitizer_factories_returns_memoized_map(): void {
		$this->aliases        = array('test.component' => 'components/test.php');
		$this->cachedMetadata = array(
			'kepler_comp_meta_test.component' => array(
				'normalizer' => null,
				'builder'    => null,
				'validator'  => null,
				'sanitizer'  => StubSanitizer::class,
			),
		);

		$manifest = $this->createManifest();

		$factories1 = $manifest->sanitizer_factories();
		$factories2 = $manifest->sanitizer_factories();

		$this->assertSame($factories1, $factories2, 'Factory map should be memoized');
		$this->assertArrayHasKey('test.component', $factories1);
	}

	/**
	 * Test that sanitizer factory returns cached instance.
	 */
	public function test_sanitizer_factory_returns_cached_instance(): void {
		$this->aliases        = array('test.component' => 'components/test.php');
		$this->cachedMetadata = array(
			'kepler_comp_meta_test.component' => array(
				'normalizer' => null,
				'builder'    => null,
				'validator'  => null,
				'sanitizer'  => StubSanitizer::class,
			),
		);

		$manifest  = $this->createManifest();
		$factories = $manifest->sanitizer_factories();
		$factory   = $factories['test.component'];

		$instance1 = $factory();
		$instance2 = $factory();

		$this->assertSame($instance1, $instance2, 'Factory should return cached instance');
		$this->assertInstanceOf(StubSanitizer::class, $instance1);
	}

	/**
	 * Test that _clear_instance_cache() clears all caches.
	 */
	public function test_clear_instance_cache_clears_all_caches(): void {
		$this->aliases        = array('test.component' => 'components/test.php');
		$this->cachedMetadata = array(
			'kepler_comp_meta_test.component' => array(
				'normalizer' => null,
				'builder'    => null,
				'validator'  => StubValidator::class,
				'sanitizer'  => StubSanitizer::class,
			),
		);

		$manifest = $this->createManifest();

		// Build caches
		$validatorFactories = $manifest->validator_factories();
		$sanitizerFactories = $manifest->sanitizer_factories();
		$validatorFactories['test.component']();
		$sanitizerFactories['test.component']();

		// Clear caches
		$manifest->_clear_instance_cache();

		// After clearing, factory maps should be rebuilt
		$newValidatorFactories = $manifest->validator_factories();
		$newSanitizerFactories = $manifest->sanitizer_factories();

		// Maps should be equal but not same (rebuilt)
		$this->assertEquals($validatorFactories, $newValidatorFactories);
		$this->assertEquals($sanitizerFactories, $newSanitizerFactories);

		// New instances should be created
		$newValidatorInstance = $newValidatorFactories['test.component']();
		$this->assertInstanceOf(StubValidator::class, $newValidatorInstance);
	}

	/**
	 * Test that different component aliases get different instances.
	 */
	public function test_different_aliases_get_different_instances(): void {
		$this->aliases = array(
			'component.a' => 'components/a.php',
			'component.b' => 'components/b.php',
		);
		$this->cachedMetadata = array(
			'kepler_comp_meta_component.a' => array(
				'normalizer' => null,
				'builder'    => null,
				'validator'  => StubValidator::class,
				'sanitizer'  => null,
			),
			'kepler_comp_meta_component.b' => array(
				'normalizer' => null,
				'builder'    => null,
				'validator'  => StubValidator::class,
				'sanitizer'  => null,
			),
		);

		$manifest  = $this->createManifest();
		$factories = $manifest->validator_factories();

		$instanceA = $factories['component.a']();
		$instanceB = $factories['component.b']();

		$this->assertNotSame($instanceA, $instanceB, 'Different aliases should get different instances');
		$this->assertInstanceOf(StubValidator::class, $instanceA);
		$this->assertInstanceOf(StubValidator::class, $instanceB);
	}

	private function createManifest(): ComponentManifest {
		return new ComponentManifest($this->loader, $this->logger_mock);
	}

	private function resetLogs(): void {
		$this->logger_mock->collected_logs = array();
	}
}

namespace Ran\PluginLib\Tests\Unit\Forms;

use Ran\PluginLib\Forms\Component\Validate\ValidatorInterface;
use Ran\PluginLib\Forms\Component\Sanitize\SanitizerInterface;
use Ran\PluginLib\Util\Logger;

/**
 * Stub validator for testing instance caching.
 */
class StubValidator implements ValidatorInterface {
	public function __construct(private Logger $logger) {
	}

	public function validate(mixed $value, array $context, callable $emitWarning): bool {
		return true;
	}
}

/**
 * Stub sanitizer for testing instance caching.
 */
class StubSanitizer implements SanitizerInterface {
	public function __construct(private Logger $logger) {
	}

	public function sanitize(mixed $value, array $context, callable $emitWarning): mixed {
		return $value;
	}
}
