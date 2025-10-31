<?php
/**
 * Integration test for ComponentManifest caching with real component discovery.
 * Tests that caching integrates properly with existing component metadata discovery.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms;

use WP_Mock;
use Ran\PluginLib\Util\ExpectLogTrait;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Forms\Component\Validate\ValidatorInterface;
use Ran\PluginLib\Forms\Component\Normalize\NormalizeInterface;
use Ran\PluginLib\Forms\Component\ComponentRenderResult;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use Ran\PluginLib\Forms\Component\ComponentLoader;
use Mockery;

/**
 * Test ComponentManifest caching integration with component discovery.
 */
class ComponentManifestCacheIntegrationTest extends PluginLibTestCase {
	use ExpectLogTrait;

	private ComponentLoader $loader;

	public function setUp(): void {
		parent::setUp();

		// Mock dependencies
		$this->loader = Mockery::mock(ComponentLoader::class);

		// Mock WordPress functions for caching
		$this->mockWordPressFunctions();
		$this->logger_mock->collected_logs = array();
	}

	/**
	 * Test that component metadata discovery works identically with and without caching.
	 */
	public function test_component_metadata_discovery_identical_with_and_without_cache(): void {
		// Set up component aliases - use simple setup to avoid PHP internal function mocking
		$this->loader->shouldReceive('aliases')->andReturn(array(
			'test.simple' => 'simple.php'
		));

		// Mock component class resolution to return null (no special classes)
		$this->loader->shouldReceive('resolve_normalizer_class')->andReturn(null);
		$this->loader->shouldReceive('resolve_builder_class')->andReturn(null);
		$this->loader->shouldReceive('resolve_validator_class')->andReturn(null);

		// Test with caching enabled (production)
		WP_Mock::userFunction('wp_get_environment_type')->andReturn('production');
		$manifest_cached = new ComponentManifest($this->loader, $this->logger_mock);

		// Test with caching disabled (development)
		WP_Mock::userFunction('wp_get_environment_type')->andReturn('development');
		$manifest_uncached = new ComponentManifest($this->loader, $this->logger_mock);

		// Both should produce identical results (empty arrays since no classes are resolved)
		$normalizers_cached   = $manifest_cached->create_normalizers();
		$normalizers_uncached = $manifest_uncached->create_normalizers();

		$builders_cached   = $manifest_cached->builder_factories();
		$builders_uncached = $manifest_uncached->builder_factories();

		$validators_cached   = $manifest_cached->validator_factories();
		$validators_uncached = $manifest_uncached->validator_factories();

		// Results should be identical
		$this->assertEquals($normalizers_cached, $normalizers_uncached);
		$this->assertEquals($builders_cached, $builders_uncached);
		$this->assertEquals($validators_cached, $validators_uncached);

		// All should be empty arrays since no classes were resolved
		$this->assertIsArray($normalizers_cached);
		$this->assertIsArray($builders_cached);
		$this->assertIsArray($validators_cached);
	}

	/**
	 * Test that component registration works identically with caching enabled/disabled.
	 */
	public function test_component_registration_identical_with_cache_states(): void {
		$this->setupBasicLoaderExpectations();

		// Create manifests with different cache states
		WP_Mock::userFunction('wp_get_environment_type')->andReturn('production');
		$manifest_cached = new ComponentManifest($this->loader, $this->logger_mock);

		WP_Mock::userFunction('wp_get_environment_type')->andReturn('development');
		$manifest_uncached = new ComponentManifest($this->loader, $this->logger_mock);

		// Register identical components on both
		$factory = function(array $context): ComponentRenderResult {
			return new ComponentRenderResult(
				markup: '<input type="text" name="' . ($context['name'] ?? 'test') . '">',
				submits_data: true,
				component_type: 'form_field'
			);
		};

		$manifest_cached->register('integration.test', $factory);
		$manifest_uncached->register('integration.test', $factory);

		// Both should have the component
		$this->assertTrue($manifest_cached->has('integration.test'));
		$this->assertTrue($manifest_uncached->has('integration.test'));

		// Both should render identically
		$result_cached   = $manifest_cached->render('integration.test', array('name' => 'cached'));
		$result_uncached = $manifest_uncached->render('integration.test', array('name' => 'uncached'));

		$this->assertEquals('<input type="text" name="cached">', $result_cached->markup);
		$this->assertEquals('<input type="text" name="uncached">', $result_uncached->markup);
		$this->assertEquals($result_cached->submits_data, $result_uncached->submits_data);
		$this->assertEquals($result_cached->component_type, $result_uncached->component_type);
	}

	/**
	 * Test that cache clearing doesn't affect existing component functionality.
	 */
	public function test_cache_clearing_preserves_existing_functionality(): void {
		$this->setupBasicLoaderExpectations();

		$manifest = new ComponentManifest($this->loader, $this->logger_mock);

		// Register a component
		$manifest->register('cache.clear.test', function(array $context): ComponentRenderResult {
			return new ComponentRenderResult(
				markup: '<div>Test: ' . ($context['value'] ?? 'default') . '</div>',
				submits_data: false,
				component_type: 'display'
			);
		});

		// Render before cache clear
		$result_before = $manifest->render('cache.clear.test', array('value' => 'before'));
		$this->assertEquals('<div>Test: before</div>', $result_before->markup);

		// Clear cache
		$manifest->clear_cache();

		// Render after cache clear - should work identically
		$result_after = $manifest->render('cache.clear.test', array('value' => 'after'));
		$this->assertEquals('<div>Test: after</div>', $result_after->markup);

		// Component properties should be identical
		$this->assertEquals($result_before->submits_data, $result_after->submits_data);
		$this->assertEquals($result_before->component_type, $result_after->component_type);
		$this->assertEquals($result_before->requires_media, $result_after->requires_media);
		$this->assertEquals($result_before->repeatable, $result_after->repeatable);
	}

	/**
	 * Test that warm_cache doesn't affect existing component behavior.
	 */
	public function test_warm_cache_preserves_existing_behavior(): void {
		$this->setupBasicLoaderExpectations();

		$manifest = new ComponentManifest($this->loader, $this->logger_mock);

		// Register components
		$manifest->register('warm.test1', function(array $context): ComponentRenderResult {
			return new ComponentRenderResult(markup: '<input type="text">');
		});

		$manifest->register('warm.test2', function(array $context): array {
			return array(
				'result'   => new ComponentRenderResult(markup: '<select></select>'),
				'warnings' => array('Warm cache warning')
			);
		});

		// Render before warm cache
		$result1_before  = $manifest->render('warm.test1');
		$result2_before  = $manifest->render('warm.test2');
		$warnings_before = $manifest->take_warnings();

		// Warm cache
		$manifest->warm_cache();

		// Render after warm cache - should be identical
		$result1_after  = $manifest->render('warm.test1');
		$result2_after  = $manifest->render('warm.test2');
		$warnings_after = $manifest->take_warnings();

		// Results should be identical
		$this->assertEquals($result1_before->markup, $result1_after->markup);
		$this->assertEquals($result2_before->markup, $result2_after->markup);
		$this->assertEquals($warnings_before, $warnings_after);
	}

	/**
	 * Test that schema eligibility checking works with caching.
	 */
	public function test_schema_eligibility_works_with_caching(): void {
		$this->setupBasicLoaderExpectations();

		$manifest = new ComponentManifest($this->loader, $this->logger_mock);

		// Register components with different data submission properties
		$manifest->register('schema.submits', function(array $context): ComponentRenderResult {
			return new ComponentRenderResult(
				markup: '<input type="text">',
				submits_data: true
			);
		});

		$manifest->register('schema.display', function(array $context): ComponentRenderResult {
			return new ComponentRenderResult(
				markup: '<div>Display only</div>',
				submits_data: false
			);
		});

		// Test schema eligibility - should work with caching
		$this->assertTrue($manifest->is_component_schema_eligible('schema.submits'));
		$this->assertFalse($manifest->is_component_schema_eligible('schema.display'));
		$this->assertFalse($manifest->is_component_schema_eligible('nonexistent'));

		// Clear cache and test again - should be identical
		$manifest->clear_cache();

		$this->assertTrue($manifest->is_component_schema_eligible('schema.submits'));
		$this->assertFalse($manifest->is_component_schema_eligible('schema.display'));
		$this->assertFalse($manifest->is_component_schema_eligible('nonexistent'));
	}

	/**
	 * Test that ComponentLoader access works with caching.
	 */
	public function test_component_loader_access_works_with_caching(): void {
		$this->setupBasicLoaderExpectations();

		$manifest = new ComponentManifest($this->loader, $this->logger_mock);

		// Access ComponentLoader through ComponentManifest
		$loader = $manifest->get_component_loader();
		$this->assertSame($this->loader, $loader);

		// Clear cache and test again - should still work
		$manifest->clear_cache();
		$loader_after_clear = $manifest->get_component_loader();
		$this->assertSame($this->loader, $loader_after_clear);
		$this->assertSame($loader, $loader_after_clear);
	}

	// Helper methods

	private function mockWordPressFunctions(): void {
		// Mock WordPress transient functions
		WP_Mock::userFunction('get_transient')->andReturn(false);
		WP_Mock::userFunction('set_transient')->andReturn(true);
		WP_Mock::userFunction('delete_transient')->andReturn(true);

		// Mock WordPress option functions
		WP_Mock::userFunction('get_option')->andReturn(array());
		WP_Mock::userFunction('update_option')->andReturn(true);
		WP_Mock::userFunction('delete_option')->andReturn(true);

		// Mock WordPress environment function
		WP_Mock::userFunction('wp_get_environment_type')->andReturn('production');
	}

	private function setupBasicLoaderExpectations(): void {
		// Mock basic loader methods that ComponentManifest uses
		$this->loader->shouldReceive('aliases')->andReturn(array(
			'test.basic' => 'basic.php'
		));

		$this->loader->shouldReceive('resolve_normalizer_class')->andReturn(null);
		$this->loader->shouldReceive('resolve_builder_class')->andReturn(null);
		$this->loader->shouldReceive('resolve_validator_class')->andReturn(null);
	}
}
