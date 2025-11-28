<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms;

use Mockery;
use Mockery\MockInterface;
use WP_Mock;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use Ran\PluginLib\Forms\Component\ComponentLoader;
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

		self::assertArrayHasKey('kepler_comp_meta_example-component', $this->cachedMetadata);
		$this->expectLog('debug', 'ComponentManifest: Cache MISS for component');
		$this->expectLog('debug', 'ComponentManifest: Cached component metadata');
		$this->expectLog('debug', 'ComponentManifest: Cache HIT for component', 0);
	}

	public function test_register_alias_reads_from_cache_and_logs_hit(): void {
		$this->aliases        = array('cached-component' => 'components/cached.php');
		$this->cachedMetadata = array(
			'kepler_comp_meta_cached-component' => array('normalizer' => null, 'builder' => null, 'validator' => null),
		);

		$this->resetLogs();
		$this->createManifest();

		$this->expectLog('debug', 'ComponentManifest: Cache HIT for component');
		$this->expectLog('debug', 'ComponentManifest: Cache MISS for component', 0);
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
		$this->expectLog('debug', 'ComponentManifest: CLEARED cache for component');

		$this->resetLogs();
		$manifest->clear_cache();
		$this->expectLog('debug', 'ComponentManifest: CLEARED all component caches');
	}

	private function createManifest(): ComponentManifest {
		return new ComponentManifest($this->loader, $this->logger_mock);
	}

	private function resetLogs(): void {
		$this->logger_mock->collected_logs = array();
	}
}
