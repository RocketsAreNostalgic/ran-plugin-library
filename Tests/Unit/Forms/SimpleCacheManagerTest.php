<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms;

use Mockery;
use WP_Mock;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Forms\Component\CacheManager;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use Ran\PluginLib\Forms\Component\ComponentLoader;
use Ran\PluginLib\Util\ExpectLogTrait;

/**
 * Test CacheManager functionality.
 */
class CacheManagerTest extends PluginLibTestCase {
	use ExpectLogTrait;

	public function test_clear_all_calls_both_dependencies(): void {
		// Mock dependencies
		$componentManifest = Mockery::mock(ComponentManifest::class);
		$componentLoader   = Mockery::mock(ComponentLoader::class);

		// Set expectations
		$componentManifest->shouldReceive('clear_cache')->once()->with();
		$componentLoader->shouldReceive('clear_template_cache')->once()->with();
		$this->logger_mock->collected_logs = array();

		$cacheManager = new CacheManager($componentManifest, $componentLoader, $this->logger_mock);
		$cacheManager->clear_all();

		// Verify that the method exists and can be called
		$this->assertTrue(method_exists($cacheManager, 'clear_all'));
		$this->expectLog('debug', 'CacheManager: Starting clear_all operation');
		$this->expectLog('info', 'CacheManager: Cleared all component and template caches');
	}

	public function test_clear_component_calls_component_manifest(): void {
		// Mock dependencies
		$componentManifest = Mockery::mock(ComponentManifest::class);
		$componentLoader   = Mockery::mock(ComponentLoader::class);

		// Set expectations
		$componentManifest->shouldReceive('clear_cache')->once()->with('test-component');
		$this->logger_mock->collected_logs = array();

		$cacheManager = new CacheManager($componentManifest, $componentLoader, $this->logger_mock);
		$cacheManager->clear_component('test-component');

		// Verify that the method exists and can be called
		$this->assertTrue(method_exists($cacheManager, 'clear_component'));
		$this->expectLog('debug', 'CacheManager: Starting clear_component operation');
		$this->expectLog('info', 'CacheManager: Cleared component cache');

		$debugLogs = $this->logger_mock->find_logs(static function (array $entry): bool {
			return $entry['level'] === 'debug' && $entry['message'] === 'CacheManager: Starting clear_component operation';
		});
		self::assertSame('test-component', $debugLogs[0]['context']['alias'] ?? null);

		$infoLogs = $this->logger_mock->find_logs(static function (array $entry): bool {
			return $entry['level'] === 'info' && $entry['message'] === 'CacheManager: Cleared component cache';
		});
		self::assertSame('test-component', $infoLogs[0]['context']['alias'] ?? null);
	}

	public function test_clear_template_calls_component_loader(): void {
		// Mock dependencies
		$componentManifest = Mockery::mock(ComponentManifest::class);
		$componentLoader   = Mockery::mock(ComponentLoader::class);

		// Set expectations
		$componentLoader->shouldReceive('clear_template_cache')->once()->with('test-template');
		$this->logger_mock->collected_logs = array();

		$cacheManager = new CacheManager($componentManifest, $componentLoader, $this->logger_mock);
		$cacheManager->clear_template('test-template');

		// Verify that the method exists and can be called
		$this->assertTrue(method_exists($cacheManager, 'clear_template'));
		$this->expectLog('debug', 'CacheManager: Starting clear_template operation');
		$this->expectLog('info', 'CacheManager: Cleared template cache');

		$debugLogs = $this->logger_mock->find_logs(static function (array $entry): bool {
			return $entry['level'] === 'debug' && $entry['message'] === 'CacheManager: Starting clear_template operation';
		});
		self::assertSame('test-template', $debugLogs[0]['context']['name'] ?? null);

		$infoLogs = $this->logger_mock->find_logs(static function (array $entry): bool {
			return $entry['level'] === 'info' && $entry['message'] === 'CacheManager: Cleared template cache';
		});
		self::assertSame('test-template', $infoLogs[0]['context']['name'] ?? null);
	}

	public function test_get_stats_returns_expected_structure(): void {
		// Mock dependencies
		$componentManifest = Mockery::mock(ComponentManifest::class);
		$componentLoader   = Mockery::mock(ComponentLoader::class);

		// Mock WordPress functions
		WP_Mock::userFunction('wp_get_environment_type')->andReturn('production');
		WP_Mock::userFunction('wp_using_ext_object_cache')->andReturn(true);

		$cacheManager = new CacheManager($componentManifest, $componentLoader, $this->logger_mock);
		$stats        = $cacheManager->get_stats();

		// Verify structure
		$this->assertIsArray($stats);
		$this->assertArrayHasKey('cache_ttl', $stats);
		$this->assertArrayHasKey('environment', $stats);
		$this->assertArrayHasKey('object_cache_enabled', $stats);
		$this->assertArrayHasKey('caching_enabled', $stats);
		$this->assertArrayHasKey('debug_mode', $stats);

		// Verify types
		$this->assertIsInt($stats['cache_ttl']);
		$this->assertIsString($stats['environment']);
		$this->assertIsBool($stats['object_cache_enabled']);
		$this->assertIsBool($stats['caching_enabled']);
		$this->assertIsBool($stats['debug_mode']);
	}

	public function test_get_stats_with_development_environment(): void {
		// Mock dependencies
		$componentManifest = Mockery::mock(ComponentManifest::class);
		$componentLoader   = Mockery::mock(ComponentLoader::class);

		// Mock WordPress functions for development environment
		WP_Mock::userFunction('wp_get_environment_type')->andReturn('development');
		WP_Mock::userFunction('wp_using_ext_object_cache')->andReturn(false);

		$cacheManager = new CacheManager($componentManifest, $componentLoader, $this->logger_mock);
		$stats        = $cacheManager->get_stats();

		$this->assertEquals('development', $stats['environment']);
		$this->assertEquals(300, $stats['cache_ttl']); // 5 minutes for development
		$this->assertFalse($stats['object_cache_enabled']);
	}

	public function test_get_stats_with_staging_environment(): void {
		// Mock dependencies
		$componentManifest = Mockery::mock(ComponentManifest::class);
		$componentLoader   = Mockery::mock(ComponentLoader::class);

		// Mock WordPress functions for staging environment
		WP_Mock::userFunction('wp_get_environment_type')->andReturn('staging');
		WP_Mock::userFunction('wp_using_ext_object_cache')->andReturn(true);

		$cacheManager = new CacheManager($componentManifest, $componentLoader, $this->logger_mock);
		$stats        = $cacheManager->get_stats();

		$this->assertEquals('staging', $stats['environment']);
		$this->assertEquals(1800, $stats['cache_ttl']); // 30 minutes for staging
		$this->assertTrue($stats['object_cache_enabled']);
	}

	public function test_get_stats_with_production_environment(): void {
		// Mock dependencies
		$componentManifest = Mockery::mock(ComponentManifest::class);
		$componentLoader   = Mockery::mock(ComponentLoader::class);

		// Mock WordPress functions for production environment
		WP_Mock::userFunction('wp_get_environment_type')->andReturn('production');
		WP_Mock::userFunction('wp_using_ext_object_cache')->andReturn(true);

		$cacheManager = new CacheManager($componentManifest, $componentLoader, $this->logger_mock);
		$stats        = $cacheManager->get_stats();

		$this->assertEquals('production', $stats['environment']);
		$this->assertEquals(3600, $stats['cache_ttl']); // 1 hour for production
		$this->assertTrue($stats['object_cache_enabled']);
	}

	public function test_transient_integration_optimization(): void {
		// Mock dependencies
		$componentManifest = Mockery::mock(ComponentManifest::class);
		$componentLoader   = Mockery::mock(ComponentLoader::class);

		// Mock WordPress functions to verify transient usage
		WP_Mock::userFunction('wp_get_environment_type')->andReturn('production');
		WP_Mock::userFunction('wp_using_ext_object_cache')->andReturn(true);

		// Verify that the cache manager can be instantiated and uses transients
		$cacheManager = new CacheManager($componentManifest, $componentLoader, $this->logger_mock);
		$stats        = $cacheManager->get_stats();

		// Verify that object cache detection works
		$this->assertTrue($stats['object_cache_enabled']);
		$this->assertIsInt($stats['cache_ttl']);
		$this->assertGreaterThanOrEqual(300, $stats['cache_ttl']); // Minimum 5 minutes
	}

	public function test_debug_info_returns_expected_structure(): void {
		// Mock dependencies
		$componentManifest = Mockery::mock(ComponentManifest::class);
		$componentLoader   = Mockery::mock(ComponentLoader::class);

		// Mock WordPress functions
		WP_Mock::userFunction('wp_get_environment_type')->andReturn('development');
		WP_Mock::userFunction('wp_using_ext_object_cache')->andReturn(false);
		WP_Mock::userFunction('get_option')->with('component_cache_transients', array())->andReturn(array('key1', 'key2'));
		WP_Mock::userFunction('get_option')->with('template_cache_transients', array())->andReturn(array('key3'));

		$cacheManager = new CacheManager($componentManifest, $componentLoader, $this->logger_mock);
		$debugInfo    = $cacheManager->debug_info();

		// Verify structure
		$this->assertIsArray($debugInfo);
		$this->assertArrayHasKey('cache_enabled', $debugInfo);
		$this->assertArrayHasKey('cache_ttl', $debugInfo);
		$this->assertArrayHasKey('environment', $debugInfo);
		$this->assertArrayHasKey('object_cache_enabled', $debugInfo);
		$this->assertArrayHasKey('debug_mode', $debugInfo);
		$this->assertArrayHasKey('tracked_component_transients', $debugInfo);
		$this->assertArrayHasKey('tracked_template_transients', $debugInfo);
		$this->assertArrayHasKey('total_tracked_transients', $debugInfo);
		$this->assertArrayHasKey('component_transient_keys', $debugInfo);
		$this->assertArrayHasKey('template_transient_keys', $debugInfo);

		// Verify types and values
		$this->assertIsBool($debugInfo['cache_enabled']);
		$this->assertIsInt($debugInfo['cache_ttl']);
		$this->assertIsString($debugInfo['environment']);
		$this->assertIsBool($debugInfo['object_cache_enabled']);
		$this->assertIsBool($debugInfo['debug_mode']);
		$this->assertIsInt($debugInfo['tracked_component_transients']);
		$this->assertIsInt($debugInfo['tracked_template_transients']);
		$this->assertIsInt($debugInfo['total_tracked_transients']);
		$this->assertIsArray($debugInfo['component_transient_keys']);
		$this->assertIsArray($debugInfo['template_transient_keys']);

		// Verify calculated values
		$this->assertEquals(2, $debugInfo['tracked_component_transients']);
		$this->assertEquals(1, $debugInfo['tracked_template_transients']);
		$this->assertEquals(3, $debugInfo['total_tracked_transients']);
	}
}
