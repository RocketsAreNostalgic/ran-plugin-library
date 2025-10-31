<?php
/**
 * Backward compatibility test for ComponentLoader functionality.
 * Ensures that all existing ComponentLoader methods continue to work unchanged
 * and that template caching is completely transparent to existing template usage.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms;

use Mockery;
use WP_Mock;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Forms\Component\ComponentLoader;
use Ran\PluginLib\Forms\Component\ComponentRenderResult;

/**
 * Test ComponentLoader backward compatibility with caching system.
 * Requirements: 7.2, 7.4, 7.5, 7.6, 7.7, 7.8
 */
class ComponentLoaderBackwardCompatibilityTest extends PluginLibTestCase {
	private ComponentLoader $loader;
	private string $testTemplateDir;

	public function setUp(): void {
		parent::setUp();

		// Create temporary directory for test templates
		$this->testTemplateDir = sys_get_temp_dir() . '/component_loader_test_' . uniqid();
		mkdir($this->testTemplateDir, 0777, true);

		// Mock WordPress functions for caching
		$this->mockWordPressFunctions();

		// Create test template files
		$this->createTestTemplates();
	}

	public function tearDown(): void {
		// Clean up test templates
		$this->cleanupTestTemplates();
		parent::tearDown();
	}

	/**
	 * Test that all existing ComponentLoader methods continue to work unchanged.
	 * Requirements: 7.2, 7.5, 7.6
	 */
	public function test_existing_methods_work_unchanged(): void {
		$this->loader = new ComponentLoader($this->testTemplateDir);

		// Test get_base_directory() method - should work exactly as before
		$this->assertEquals($this->testTemplateDir, $this->loader->get_base_directory());

		// Test register() method - should work exactly as before
		$this->loader->register('custom.template', 'custom/template.php');
		$aliases = $this->loader->aliases();
		$this->assertArrayHasKey('custom.template', $aliases);
		$this->assertEquals('custom/template.php', $aliases['custom.template']);

		// Test aliases() method - should work exactly as before
		$aliases = $this->loader->aliases();
		$this->assertIsArray($aliases);
		$this->assertArrayHasKey('test.input', $aliases);
		$this->assertArrayHasKey('test.select', $aliases);

		// Test render() method - now returns ComponentRenderResult
		$result = $this->loader->render('test.input', array('name' => 'test_field', 'value' => 'test_value'));
		$this->assertInstanceOf(ComponentRenderResult::class, $result);
		$this->assertStringContainsString('test_field', $result->markup);
		$this->assertStringContainsString('test_value', $result->markup);

		// Test render_payload() method - should work exactly as before
		$payload = $this->loader->render_payload('test.data', array('key' => 'value'));
		$this->assertIsArray($payload);
		$this->assertEquals('value', $payload['key']);

		// Test component class resolution methods - should work exactly as before
		$normalizer = $this->loader->resolve_normalizer_class('test.input');
		$builder    = $this->loader->resolve_builder_class('test.input');
		$validator  = $this->loader->resolve_validator_class('test.input');

		// These may return null if classes don't exist, which is expected behavior
		$this->assertTrue($normalizer === null || is_string($normalizer));
		$this->assertTrue($builder === null || is_string($builder));
		$this->assertTrue($validator === null || is_string($validator));
	}

	/**
	 * Test that template caching is completely transparent to existing template usage.
	 * Requirements: 7.2, 7.4, 7.5
	 */
	public function test_caching_is_transparent_to_existing_usage(): void {
		// Test with caching enabled (production mode)
		WP_Mock::userFunction('wp_get_environment_type')->andReturn('production');

		$this->loader = new ComponentLoader($this->testTemplateDir);

		// First render - should work identically with or without cache
		$result1 = $this->loader->render('test.input', array('name' => 'field1', 'value' => 'value1'));
		$this->assertInstanceOf(ComponentRenderResult::class, $result1);
		$this->assertStringContainsString('field1', $result1->markup);
		$this->assertStringContainsString('value1', $result1->markup);

		// Second render with same context - should work identically (cache hit)
		$result2 = $this->loader->render('test.input', array('name' => 'field1', 'value' => 'value1'));
		$this->assertInstanceOf(ComponentRenderResult::class, $result2);
		$this->assertEquals($result1->markup, $result2->markup);

		// Third render with different context - should work identically (cache miss)
		$result3 = $this->loader->render('test.input', array('name' => 'field2', 'value' => 'value2'));
		$this->assertInstanceOf(ComponentRenderResult::class, $result3);
		$this->assertStringContainsString('field2', $result3->markup);
		$this->assertStringContainsString('value2', $result3->markup);
		$this->assertNotEquals($result1->markup, $result3->markup);
	}

	/**
	 * Test that template rendering behavior remains identical when cache is disabled.
	 * Requirements: 7.4, 7.5, 7.6
	 */
	public function test_rendering_behavior_identical_when_cache_disabled(): void {
		// Test with caching disabled (development mode)
		WP_Mock::userFunction('wp_get_environment_type')->andReturn('development');

		$this->loader = new ComponentLoader($this->testTemplateDir);

		// Template rendering should work exactly as before
		$result1 = $this->loader->render('test.input', array('name' => 'dev_field', 'value' => 'dev_value'));
		$result2 = $this->loader->render('test.select', array('name' => 'dev_select', 'options' => array('a', 'b')));

		foreach (array($result1, $result2) as $result) {
			$this->assertInstanceOf(ComponentRenderResult::class, $result);
		}

		$this->assertStringContainsString('dev_field', $result1->markup);
		$this->assertStringContainsString('dev_value', $result1->markup);
		$this->assertStringContainsString('dev_select', $result2->markup);

		// render_payload should work exactly as before
		$payload = $this->loader->render_payload('test.data', array('dev_key' => 'dev_data'));
		$this->assertIsArray($payload);
		$this->assertEquals('dev_data', $payload['dev_key']);

		// All existing functionality should work without any changes
		$this->assertTrue(method_exists($this->loader, 'render'));
		$this->assertTrue(method_exists($this->loader, 'render_payload'));
		$this->assertTrue(method_exists($this->loader, 'register'));
		$this->assertTrue(method_exists($this->loader, 'aliases'));
		$this->assertTrue(method_exists($this->loader, 'get_base_directory'));
		$this->assertTrue(method_exists($this->loader, 'resolve_normalizer_class'));
		$this->assertTrue(method_exists($this->loader, 'resolve_builder_class'));
		$this->assertTrue(method_exists($this->loader, 'resolve_validator_class'));
	}

	/**
	 * Test that existing template registration and rendering APIs are unaffected.
	 * Requirements: 7.6, 7.7, 7.8
	 */
	public function test_existing_apis_unaffected(): void {
		$this->loader = new ComponentLoader($this->testTemplateDir);

		// Test template registration API - should work exactly as before
		$this->loader->register('api.test1', 'templates/test1.php');
		$this->loader->register('api.test2', 'templates/test2.php');

		$aliases = $this->loader->aliases();
		$this->assertArrayHasKey('api.test1', $aliases);
		$this->assertArrayHasKey('api.test2', $aliases);
		$this->assertEquals('templates/test1.php', $aliases['api.test1']);
		$this->assertEquals('templates/test2.php', $aliases['api.test2']);

		// Test fluent interface - should work exactly as before
		$result = $this->loader
			->register('api.fluent1', 'fluent1.php')
			->register('api.fluent2', 'fluent2.php');

		$this->assertInstanceOf(ComponentLoader::class, $result);
		$aliases = $this->loader->aliases();
		$this->assertArrayHasKey('api.fluent1', $aliases);
		$this->assertArrayHasKey('api.fluent2', $aliases);

		// Test rendering with various contexts - should work exactly as before
		$simple_result  = $this->loader->render('test.input', array('name' => 'simple'));
		$complex_result = $this->loader->render('test.input', array(
			'name'       => 'complex',
			'value'      => 'complex_value',
			'attributes' => array('class' => 'form-control', 'id' => 'complex_id')
		));

		foreach (array($simple_result, $complex_result) as $result) {
			$this->assertInstanceOf(ComponentRenderResult::class, $result);
		}

		$this->assertStringContainsString('simple', $simple_result->markup);
		$this->assertStringContainsString('complex', $complex_result->markup);
		$this->assertStringContainsString('complex_value', $complex_result->markup);
	}

	/**
	 * Test that existing template usage patterns remain compatible.
	 * Requirements: 7.7, 7.8
	 */
	public function test_existing_usage_patterns_compatible(): void {
		$this->loader = new ComponentLoader($this->testTemplateDir);

		// Test pattern 1: Simple template rendering
		$result = $this->loader->render('test.input', array('name' => 'pattern1'));
		$this->assertInstanceOf(ComponentRenderResult::class, $result);
		$this->assertStringContainsString('pattern1', $result->markup);

		// Test pattern 2: Template with complex context
		$context = array(
			'name'       => 'pattern2',
			'value'      => 'complex_value',
			'attributes' => array('class' => 'test-class'),
			'nested'     => array('data' => array('key' => 'value'))
		);
		$result = $this->loader->render('test.input', $context);
		$this->assertStringContainsString('pattern2', $result->markup);
		$this->assertStringContainsString('complex_value', $result->markup);

		// Test pattern 3: Multiple template renders
		$results = array();
		for ($i = 1; $i <= 3; $i++) {
			$results[] = $this->loader->render('test.input', array('name' => "multi_{$i}"));
		}

		$this->assertCount(3, $results);
		foreach ($results as $index => $renderResult) {
			$this->assertInstanceOf(ComponentRenderResult::class, $renderResult);
			$this->assertStringContainsString('multi_' . ($index + 1), $renderResult->markup);
		}

		// Test pattern 4: Template discovery and alias mapping
		$aliases = $this->loader->aliases();
		$this->assertIsArray($aliases);
		$this->assertNotEmpty($aliases);

		// Verify discovered templates are accessible
		foreach (array('test.input', 'test.select') as $alias) {
			$this->assertArrayHasKey($alias, $aliases);
			$renderResult = $this->loader->render($alias, array('name' => 'discovery_test'));
			$this->assertInstanceOf(ComponentRenderResult::class, $renderResult);
			$this->assertStringContainsString('discovery_test', $renderResult->markup);
		}
	}

	/**
	 * Test that new caching methods don't break existing functionality.
	 * Requirements: 7.6, 7.8
	 */
	public function test_new_caching_methods_dont_break_existing_functionality(): void {
		$this->loader = new ComponentLoader($this->testTemplateDir);

		// Render a template using existing API
		$result1 = $this->loader->render('test.input', array('name' => 'cache_test'));
		$this->assertInstanceOf(ComponentRenderResult::class, $result1);
		$this->assertStringContainsString('cache_test', $result1->markup);

		// Use new caching methods
		$this->loader->clear_template_cache('test.input');
		$this->loader->clear_template_cache(); // Clear all

		// Existing functionality should still work after cache operations
		$result2 = $this->loader->render('test.input', array('name' => 'cache_test'));
		$this->assertInstanceOf(ComponentRenderResult::class, $result2);
		$this->assertStringContainsString('cache_test', $result2->markup);

		// Results should be identical (same template, same context)
		$this->assertEquals($result1->markup, $result2->markup);

		// Test that cache clearing doesn't affect other methods
		$aliases = $this->loader->aliases();
		$this->assertIsArray($aliases);
		$this->assertArrayHasKey('test.input', $aliases);

		$baseDir = $this->loader->get_base_directory();
		$this->assertEquals($this->testTemplateDir, $baseDir);
	}

	/**
	 * Test that caching can be completely disabled without affecting functionality.
	 * Requirements: 7.4, 7.5, 7.8
	 */
	public function test_caching_disabled_preserves_all_functionality(): void {
		// Mock caching as disabled
		WP_Mock::userFunction('wp_get_environment_type')->andReturn('development');

		$this->loader = new ComponentLoader($this->testTemplateDir);

		// All existing functionality should work identically
		$result1 = $this->loader->render('test.input', array('name' => 'disabled1'));
		$result2 = $this->loader->render('test.input', array('name' => 'disabled2'));
		$result3 = $this->loader->render('test.select', array('name' => 'disabled_select'));

		foreach (array($result1, $result2, $result3) as $renderResult) {
			$this->assertInstanceOf(ComponentRenderResult::class, $renderResult);
		}

		$this->assertStringContainsString('disabled1', $result1->markup);
		$this->assertStringContainsString('disabled2', $result2->markup);
		$this->assertStringContainsString('disabled_select', $result3->markup);

		// Test multiple renders with same context (no caching)
		$repeat1 = $this->loader->render('test.input', array('name' => 'repeat'));
		$repeat2 = $this->loader->render('test.input', array('name' => 'repeat'));
		$this->assertEquals($repeat1->markup, $repeat2->markup);

		// All methods should work
		$aliases = $this->loader->aliases();
		$this->assertIsArray($aliases);

		$payload = $this->loader->render_payload('test.data', array('disabled' => true));
		$this->assertIsArray($payload);
		$this->assertTrue($payload['disabled']);

		// Registration should work
		$this->loader->register('disabled.test', 'disabled.php');
		$aliases = $this->loader->aliases();
		$this->assertArrayHasKey('disabled.test', $aliases);
	}

	/**
	 * Test error handling remains unchanged with caching.
	 * Requirements: 7.5, 7.6, 7.8
	 */
	public function test_error_handling_unchanged_with_caching(): void {
		$this->loader = new ComponentLoader($this->testTemplateDir);

		// Test that missing template errors work exactly as before
		$this->expectException(\LogicException::class);
		$this->expectExceptionMessage('No template mapping registered for "nonexistent.template"');
		$this->loader->render('nonexistent.template');
	}

	/**
	 * Test that template file changes are handled correctly.
	 * Requirements: 7.4, 7.5, 7.8
	 */
	public function test_template_file_changes_handled_correctly(): void {
		$this->loader = new ComponentLoader($this->testTemplateDir);

		// Render template first time
		$result1 = $this->loader->render('test.input', array('name' => 'change_test'));
		$this->assertInstanceOf(ComponentRenderResult::class, $result1);
		$this->assertStringContainsString('change_test', $result1->markup);

		// Simulate template file change by creating a new template
		$dynamicTemplate = $this->testTemplateDir . '/dynamic.php';
		file_put_contents($dynamicTemplate, '<?php return "Dynamic: " . ($context["name"] ?? "default");');

		// Register and render new template
		$this->loader->register('dynamic.test', 'dynamic.php');
		$result2 = $this->loader->render('dynamic.test', array('name' => 'dynamic_test'));
		$this->assertInstanceOf(ComponentRenderResult::class, $result2);
		$this->assertStringContainsString('Dynamic: dynamic_test', $result2->markup);

		// Clean up
		unlink($dynamicTemplate);
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

		// Mock WordPress constants
		if (!defined('WP_DEBUG')) {
			define('WP_DEBUG', false);
		}
	}

	private function createTestTemplates(): void {
		// Create test input template
		$inputTemplate = $this->testTemplateDir . '/test/input.php';
		mkdir(dirname($inputTemplate), 0777, true);
		file_put_contents($inputTemplate, '<?php
use Ran\\PluginLib\\Forms\\Component\\ComponentRenderResult;

$name = $context["name"] ?? "default";
$value = $context["value"] ?? "";
$attributes = $context["attributes"] ?? [];

$attr_string = "";
foreach ($attributes as $key => $val) {
	$attr_string .= " {$key}=\"" . $val . "\"";
}

$markup = sprintf(\'<input type="text" name="%s" value="%s"%s>\', $name, $value, $attr_string);

return new ComponentRenderResult(markup: $markup, submits_data: true, component_type: \'form_field\');
');

		// Create test select template
		$selectTemplate = $this->testTemplateDir . '/test/select.php';
		file_put_contents($selectTemplate, '<?php
use Ran\\PluginLib\\Forms\\Component\\ComponentRenderResult;

$name = $context["name"] ?? "default";
$options = $context["options"] ?? [];

$html = \'<select name="\'.$name.\'">\';
foreach ($options as $option) {
	$html .= \'<option value="\'.$option.\'">\'.$option.\'</option>\';
}
$html .= \'</select>\';

return new ComponentRenderResult(markup: $html, submits_data: true, component_type: \'form_field\');
');

		// Create test data template (returns array)
		$dataTemplate = $this->testTemplateDir . '/test/data.php';
		file_put_contents($dataTemplate, '<?php
return $context;
');
	}

	private function cleanupTestTemplates(): void {
		if (is_dir($this->testTemplateDir)) {
			$this->recursiveDelete($this->testTemplateDir);
		}
	}

	private function recursiveDelete(string $dir): void {
		if (!is_dir($dir)) {
			return;
		}

		$files = array_diff(scandir($dir), array('.', '..'));
		foreach ($files as $file) {
			$path = $dir . '/' . $file;
			if (is_dir($path)) {
				$this->recursiveDelete($path);
			} else {
				unlink($path);
			}
		}
		rmdir($dir);
	}
}
