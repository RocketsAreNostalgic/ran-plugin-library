<?php
/**
 * Tests for ComponentCacheService.
 *
 * @package Ran\PluginLib\Tests\Unit\Forms
 */

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms;

use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Forms\Component\Cache\ComponentCacheService;
use Ran\PluginLib\Util\CollectingLogger;
use WP_Mock;

class ComponentCacheServiceTest extends PluginLibTestCase {
	private CollectingLogger $logger;
	private array $transient_storage = array();
	private array $option_storage    = array();

	public function setUp(): void {
		parent::setUp();
		$this->logger            = new CollectingLogger();
		$this->transient_storage = array();
		$this->option_storage    = array();

		// Mock WordPress transient functions
		WP_Mock::userFunction('get_transient')->andReturnUsing(function (string $key) {
			return $this->transient_storage[$key] ?? false;
		});

		WP_Mock::userFunction('set_transient')->andReturnUsing(function (string $key, $value, int $expiration) {
			$this->transient_storage[$key] = $value;
			return true;
		});

		WP_Mock::userFunction('delete_transient')->andReturnUsing(function (string $key) {
			unset($this->transient_storage[$key]);
			return true;
		});

		// Mock WordPress option functions
		WP_Mock::userFunction('get_option')->andReturnUsing(function (string $key, $default = false) {
			return $this->option_storage[$key] ?? $default;
		});

		WP_Mock::userFunction('update_option')->andReturnUsing(function (string $key, $value) {
			$this->option_storage[$key] = $value;
			return true;
		});

		WP_Mock::userFunction('delete_option')->andReturnUsing(function (string $key) {
			unset($this->option_storage[$key]);
			return true;
		});

		// Default environment type
		WP_Mock::userFunction('wp_get_environment_type')->andReturn('production');
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Enable/Disable Tests
	// ─────────────────────────────────────────────────────────────────────────

	public function test_caching_enabled_when_wp_debug_not_defined(): void {
		// WP_DEBUG is not defined in test environment, so caching is enabled
		$service = new ComponentCacheService($this->logger);

		$this->assertTrue($service->is_enabled());
	}

	public function test_get_returns_cached_value_when_enabled(): void {
		$service = new ComponentCacheService($this->logger);

		// Pre-populate transient storage
		$this->transient_storage['test_key'] = 'cached_value';

		$result = $service->get('test_key');

		$this->assertEquals('cached_value', $result);
	}

	public function test_get_returns_false_when_key_not_found(): void {
		$service = new ComponentCacheService($this->logger);

		$result = $service->get('nonexistent_key');

		$this->assertFalse($result);
	}

	public function test_set_stores_value_when_enabled(): void {
		$service = new ComponentCacheService($this->logger);

		$result = $service->set('test_key', 'test_value');

		$this->assertTrue($result);
		$this->assertEquals('test_value', $this->transient_storage['test_key']);
	}

	// ─────────────────────────────────────────────────────────────────────────
	// TTL Tests
	// ─────────────────────────────────────────────────────────────────────────

	public function test_get_ttl_returns_production_default(): void {
		$service = new ComponentCacheService($this->logger);

		// Production default is 3600 seconds (1 hour)
		$this->assertEquals(3600, $service->get_ttl());
	}

	/**
	 * Note: Environment-specific TTL tests would require resetting WP_Mock
	 * between service instantiations. The TTL is determined at construction time.
	 * These tests verify the production default which is used in our test environment.
	 */

	// ─────────────────────────────────────────────────────────────────────────
	// Cache Key Generation Tests
	// ─────────────────────────────────────────────────────────────────────────

	public function test_generate_template_key_with_empty_context(): void {
		$service = new ComponentCacheService($this->logger);

		$key = $service->generate_template_key('fields.input', array());

		$this->assertStringStartsWith('kepler_tpl_', $key);
		$this->assertStringContainsString('fields.input', $key);
		$this->assertStringEndsWith('_empty', $key);
	}

	public function test_generate_template_key_with_context(): void {
		$service = new ComponentCacheService($this->logger);

		$key = $service->generate_template_key('fields.input', array('name' => 'test'));

		$this->assertStringStartsWith('kepler_tpl_', $key);
		$this->assertStringContainsString('fields.input', $key);
		$this->assertStringNotContainsString('_empty', $key);
	}

	public function test_generate_template_key_filters_closures(): void {
		$service = new ComponentCacheService($this->logger);

		$context1 = array('name' => 'test');
		$context2 = array('name' => 'test', 'callback' => fn() => 'ignored');

		$key1 = $service->generate_template_key('fields.input', $context1);
		$key2 = $service->generate_template_key('fields.input', $context2);

		// Keys should be identical since closure is filtered out
		$this->assertEquals($key1, $key2);
	}

	public function test_generate_component_key(): void {
		$service = new ComponentCacheService($this->logger);

		$key = $service->generate_component_key('fields.input');

		// Dots are preserved in component keys (valid characters)
		$this->assertEquals('kepler_comp_meta_fields.input', $key);
	}

	public function test_generate_component_key_sanitizes_special_characters(): void {
		$service = new ComponentCacheService($this->logger);

		$key = $service->generate_component_key('fields/input@test');

		$this->assertEquals('kepler_comp_meta_fields_input_test', $key);
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Clear Cache Tests
	// ─────────────────────────────────────────────────────────────────────────

	public function test_clear_all_removes_all_tracked_transients(): void {
		$service = new ComponentCacheService($this->logger);

		// Setup tracked transients
		$this->option_storage['template_cache_transients'] = array(
			'kepler_tpl_test1_abc',
			'kepler_tpl_test2_def',
		);
		$this->transient_storage['kepler_tpl_test1_abc'] = 'value1';
		$this->transient_storage['kepler_tpl_test2_def'] = 'value2';

		$cleared = $service->clear_all(ComponentCacheService::PREFIX_TEMPLATE);

		$this->assertEquals(2, $cleared);
		$this->assertArrayNotHasKey('kepler_tpl_test1_abc', $this->transient_storage);
		$this->assertArrayNotHasKey('kepler_tpl_test2_def', $this->transient_storage);
		$this->assertArrayNotHasKey('template_cache_transients', $this->option_storage);
	}

	public function test_clear_by_name_removes_matching_transients(): void {
		$service = new ComponentCacheService($this->logger);

		// Setup tracked transients
		$this->option_storage['template_cache_transients'] = array(
			'kepler_tpl_fields.input_abc',
			'kepler_tpl_fields.input_def',
			'kepler_tpl_fields.select_ghi',
		);
		$this->transient_storage['kepler_tpl_fields.input_abc']  = 'value1';
		$this->transient_storage['kepler_tpl_fields.input_def']  = 'value2';
		$this->transient_storage['kepler_tpl_fields.select_ghi'] = 'value3';

		$cleared = $service->clear_by_name('fields.input', ComponentCacheService::PREFIX_TEMPLATE);

		$this->assertEquals(2, $cleared);
		$this->assertArrayNotHasKey('kepler_tpl_fields.input_abc', $this->transient_storage);
		$this->assertArrayNotHasKey('kepler_tpl_fields.input_def', $this->transient_storage);
		$this->assertArrayHasKey('kepler_tpl_fields.select_ghi', $this->transient_storage);
	}

	public function test_delete_removes_single_transient(): void {
		$service = new ComponentCacheService($this->logger);

		// Setup
		$this->transient_storage['kepler_tpl_test_abc']    = 'value';
		$this->option_storage['template_cache_transients'] = array('kepler_tpl_test_abc');

		$result = $service->delete('kepler_tpl_test_abc', ComponentCacheService::PREFIX_TEMPLATE);

		$this->assertTrue($result);
		$this->assertArrayNotHasKey('kepler_tpl_test_abc', $this->transient_storage);
		$this->assertEmpty($this->option_storage['template_cache_transients']);
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Stats Tests
	// ─────────────────────────────────────────────────────────────────────────

	public function test_get_stats_returns_expected_structure(): void {
		$service = new ComponentCacheService($this->logger);

		$stats = $service->get_stats();

		$this->assertArrayHasKey('enabled', $stats);
		$this->assertArrayHasKey('ttl', $stats);
		$this->assertArrayHasKey('environment', $stats);
		$this->assertArrayHasKey('debug_mode', $stats);

		$this->assertIsBool($stats['enabled']);
		$this->assertIsInt($stats['ttl']);
		$this->assertIsString($stats['environment']);
		$this->assertIsBool($stats['debug_mode']);
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Prefix Constants Tests
	// ─────────────────────────────────────────────────────────────────────────

	public function test_prefix_constants_are_defined(): void {
		$this->assertEquals('kepler_tpl_', ComponentCacheService::PREFIX_TEMPLATE);
		$this->assertEquals('kepler_comp_meta_', ComponentCacheService::PREFIX_COMPONENT);
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Component vs Template Tracking Tests
	// ─────────────────────────────────────────────────────────────────────────

	public function test_clear_all_component_caches_uses_correct_option(): void {
		$service = new ComponentCacheService($this->logger);

		// Setup component transients
		$this->option_storage['component_cache_transients'] = array(
			'kepler_comp_meta_fields_input',
		);
		$this->transient_storage['kepler_comp_meta_fields_input'] = array('normalizer' => null);

		$cleared = $service->clear_all(ComponentCacheService::PREFIX_COMPONENT);

		$this->assertEquals(1, $cleared);
		$this->assertArrayNotHasKey('component_cache_transients', $this->option_storage);
	}
}
