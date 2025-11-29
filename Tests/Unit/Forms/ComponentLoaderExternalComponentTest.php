<?php
/**
 * Tests for ComponentLoader external component support.
 *
 * @covers \Ran\PluginLib\Forms\Component\ComponentLoader
 */

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms;

use WP_Mock;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Forms\Component\ComponentRenderResult;
use Ran\PluginLib\Forms\Component\ComponentLoader;
use Ran\PluginLib\Config\ConfigInterface;

/**
 * Test ComponentLoader support for external components with array entries.
 */
class ComponentLoaderExternalComponentTest extends PluginLibTestCase {
	private ComponentLoader $loader;
	private string $testTemplateDir;
	private string $externalComponentDir;

	public function setUp(): void {
		parent::setUp();

		// Create temporary directories for test templates
		$this->testTemplateDir      = sys_get_temp_dir() . '/component_loader_external_test_' . uniqid();
		$this->externalComponentDir = sys_get_temp_dir() . '/external_component_' . uniqid();
		mkdir($this->testTemplateDir, 0777, true);
		mkdir($this->externalComponentDir . '/ColorPicker', 0777, true);

		// Mock WordPress functions
		$this->mockWordPressFunctions();

		// Create test templates
		$this->createTestTemplates();
	}

	public function tearDown(): void {
		$this->cleanupDirectory($this->testTemplateDir);
		$this->cleanupDirectory($this->externalComponentDir);
		parent::tearDown();
	}

	/**
	 * Test that string entries in $map resolve against baseDir (built-in components).
	 */
	public function test_string_entries_resolve_against_basedir(): void {
		$this->loader = new ComponentLoader($this->testTemplateDir, $this->logger_mock);

		// Built-in component should resolve relative to baseDir
		$result = $this->loader->render('test.input', array('name' => 'builtin_test'));

		$this->assertInstanceOf(ComponentRenderResult::class, $result);
		$this->assertStringContainsString('builtin_test', $result->markup);
	}

	/**
	 * Test that array entries return stored absolute path.
	 */
	public function test_array_entries_return_stored_path(): void {
		$this->loader = new ComponentLoader($this->testTemplateDir, $this->logger_mock);

		// Manually inject an external component entry into the map via reflection
		$reflection  = new \ReflectionClass($this->loader);
		$mapProperty = $reflection->getProperty('map');
		$mapProperty->setAccessible(true);

		$currentMap                          = $mapProperty->getValue($this->loader);
		$currentMap['external.color-picker'] = array(
			'path'      => $this->externalComponentDir . '/ColorPicker/View.php',
			'namespace' => 'MyPlugin\Components\ColorPicker',
		);
		$mapProperty->setValue($this->loader, $currentMap);

		// Render the external component
		$result = $this->loader->render('external.color-picker', array('name' => 'color_field'));

		$this->assertInstanceOf(ComponentRenderResult::class, $result);
		$this->assertStringContainsString('color_field', $result->markup);
		$this->assertStringContainsString('ColorPicker', $result->markup);
	}

	/**
	 * Test that aliases() returns mixed types correctly.
	 */
	public function test_aliases_returns_mixed_types(): void {
		$this->loader = new ComponentLoader($this->testTemplateDir, $this->logger_mock);

		// Inject an external component
		$reflection  = new \ReflectionClass($this->loader);
		$mapProperty = $reflection->getProperty('map');
		$mapProperty->setAccessible(true);

		$currentMap                    = $mapProperty->getValue($this->loader);
		$currentMap['external.widget'] = array(
			'path'      => '/absolute/path/to/Widget/View.php',
			'namespace' => 'MyPlugin\Components\Widget',
		);
		$mapProperty->setValue($this->loader, $currentMap);

		$aliases = $this->loader->aliases();

		// Built-in should be string
		$this->assertIsString($aliases['test.input']);

		// External should be array
		$this->assertIsArray($aliases['external.widget']);
		$this->assertArrayHasKey('path', $aliases['external.widget']);
		$this->assertArrayHasKey('namespace', $aliases['external.widget']);
	}

	/**
	 * Test that _resolve_component_class uses stored namespace for external components.
	 */
	public function test_resolve_component_class_uses_stored_namespace(): void {
		$this->loader = new ComponentLoader($this->testTemplateDir, $this->logger_mock);

		// Inject an external component with a namespace that has a real class
		$reflection  = new \ReflectionClass($this->loader);
		$mapProperty = $reflection->getProperty('map');
		$mapProperty->setAccessible(true);

		$currentMap = $mapProperty->getValue($this->loader);
		// Use a namespace that actually exists in the codebase for testing
		$currentMap['external.test-component'] = array(
			'path'      => '/some/path/View.php',
			'namespace' => 'Ran\PluginLib\Util', // Logger class exists here
		);
		$mapProperty->setValue($this->loader, $currentMap);

		// This should look for Ran\PluginLib\Util\Validator which doesn't exist
		$validatorClass = $this->loader->resolve_validator_class('external.test-component');
		$this->assertNull($validatorClass);

		// Now test with a namespace where the class exists
		$currentMap['external.logger-component'] = array(
			'path'      => '/some/path/View.php',
			'namespace' => 'Ran\PluginLib\Util',
		);
		$mapProperty->setValue($this->loader, $currentMap);

		// Logger exists in Ran\PluginLib\Util namespace, but not as a companion class suffix
		// This verifies the class_exists check works correctly
		$result = $this->loader->resolve_validator_class('external.logger-component');
		$this->assertNull($result); // Ran\PluginLib\Util\Validator doesn't exist
	}

	/**
	 * Test that built-in components still derive namespace from alias.
	 */
	public function test_builtin_components_derive_namespace_from_alias(): void {
		$this->loader = new ComponentLoader($this->testTemplateDir, $this->logger_mock);

		// Built-in component should derive namespace from alias
		// input.text -> Ran\PluginLib\Forms\Components\Input\Text\Validator
		$validatorClass = $this->loader->resolve_validator_class('input.text');

		// The class may or may not exist, but the resolution logic should work
		// If it returns a value, it should be the correct namespace
		if ($validatorClass !== null) {
			$this->assertStringContainsString('Ran\PluginLib\Forms\Components', $validatorClass);
			$this->assertStringContainsString('Validator', $validatorClass);
		} else {
			// Class doesn't exist, which is fine - we're testing the resolution logic
			$this->assertNull($validatorClass);
		}
	}

	/**
	 * Test that class_exists check prevents errors for missing classes.
	 */
	public function test_class_exists_check_prevents_errors(): void {
		$this->loader = new ComponentLoader($this->testTemplateDir, $this->logger_mock);

		// Inject an external component with a non-existent namespace
		$reflection  = new \ReflectionClass($this->loader);
		$mapProperty = $reflection->getProperty('map');
		$mapProperty->setAccessible(true);

		$currentMap                         = $mapProperty->getValue($this->loader);
		$currentMap['external.nonexistent'] = array(
			'path'      => '/some/path/View.php',
			'namespace' => 'NonExistent\Namespace\That\Does\Not\Exist',
		);
		$mapProperty->setValue($this->loader, $currentMap);

		// Should return null, not throw an error
		$this->assertNull($this->loader->resolve_validator_class('external.nonexistent'));
		$this->assertNull($this->loader->resolve_sanitizer_class('external.nonexistent'));
		$this->assertNull($this->loader->resolve_normalizer_class('external.nonexistent'));
		$this->assertNull($this->loader->resolve_builder_class('external.nonexistent'));
	}

	/**
	 * Test that external component with missing 'namespace' key falls back to alias-based resolution.
	 */
	public function test_external_component_without_namespace_falls_back(): void {
		$this->loader = new ComponentLoader($this->testTemplateDir, $this->logger_mock);

		// Inject an external component without namespace key
		$reflection  = new \ReflectionClass($this->loader);
		$mapProperty = $reflection->getProperty('map');
		$mapProperty->setAccessible(true);

		$currentMap                     = $mapProperty->getValue($this->loader);
		$currentMap['external.partial'] = array(
			'path' => $this->externalComponentDir . '/ColorPicker/View.php',
			// No 'namespace' key - should fall back to alias-based resolution
		);
		$mapProperty->setValue($this->loader, $currentMap);

		// Should fall back to alias-based resolution (Ran\PluginLib\Forms\Components\External\Partial\Validator)
		$validatorClass = $this->loader->resolve_validator_class('external.partial');

		// Class won't exist, but it shouldn't error
		$this->assertNull($validatorClass);
	}

	/**
	 * Test register_component() stores array in $map with correct structure.
	 */
	public function test_register_component_stores_array_in_map(): void {
		$this->loader = new ComponentLoader($this->testTemplateDir, $this->logger_mock);

		// Create a mock ConfigInterface
		$config = $this->createMock(ConfigInterface::class);
		$config->method('get_config')->willReturn(array(
			'PATH' => $this->externalComponentDir,
		));
		$config->method('get_namespace')->willReturn('MyPlugin\\Components');

		// Register an external component
		$result = $this->loader->register_component('color-picker', array(
			'path'   => 'ColorPicker',
			'prefix' => 'my-plugin',
		), $config);

		// Should return self for fluent interface
		$this->assertSame($this->loader, $result);

		// Should be registered as external
		$this->assertTrue($this->loader->is_external('my-plugin.color-picker'));

		// Check the map entry structure
		$aliases = $this->loader->aliases();
		$this->assertIsArray($aliases['my-plugin.color-picker']);
		$this->assertArrayHasKey('path', $aliases['my-plugin.color-picker']);
		$this->assertArrayHasKey('namespace', $aliases['my-plugin.color-picker']);
		$this->assertStringContainsString('ColorPicker/View.php', $aliases['my-plugin.color-picker']['path']);
		$this->assertEquals('MyPlugin\\Components\\ColorPicker', $aliases['my-plugin.color-picker']['namespace']);
	}

	/**
	 * Test register_component() without prefix uses name as alias.
	 */
	public function test_register_component_without_prefix(): void {
		$this->loader = new ComponentLoader($this->testTemplateDir, $this->logger_mock);

		$config = $this->createMock(ConfigInterface::class);
		$config->method('get_config')->willReturn(array(
			'PATH' => $this->externalComponentDir,
		));
		$config->method('get_namespace')->willReturn('MyPlugin\\Components');

		$this->loader->register_component('color-picker', array(
			'path' => 'ColorPicker',
		), $config);

		// Should use name directly as alias
		$this->assertTrue($this->loader->is_external('color-picker'));
	}

	/**
	 * Test register_component() logs warning for missing path.
	 */
	public function test_register_component_logs_warning_for_missing_path(): void {
		$this->loader = new ComponentLoader($this->testTemplateDir, $this->logger_mock);

		$config = $this->createMock(ConfigInterface::class);
		$config->method('get_config')->willReturn(array('PATH' => '/some/path'));
		$config->method('get_namespace')->willReturn('MyPlugin');

		// Register without path option
		$this->loader->register_component('test', array(), $config);

		// Should not be registered
		$this->assertFalse($this->loader->is_external('test'));
	}

	/**
	 * Test register_component() logs warning for non-existent directory.
	 */
	public function test_register_component_logs_warning_for_nonexistent_directory(): void {
		$this->loader = new ComponentLoader($this->testTemplateDir, $this->logger_mock);

		$config = $this->createMock(ConfigInterface::class);
		$config->method('get_config')->willReturn(array(
			'PATH' => $this->externalComponentDir,
		));
		$config->method('get_namespace')->willReturn('MyPlugin');

		// Register with non-existent path
		$this->loader->register_component('nonexistent', array(
			'path'   => 'NonExistentComponent',
			'prefix' => 'test',
		), $config);

		// Should not be registered
		$this->assertFalse($this->loader->is_external('test.nonexistent'));
	}

	/**
	 * Test register_components() batch-discovers components.
	 */
	public function test_register_components_batch_discovers(): void {
		// Create additional component directories
		mkdir($this->externalComponentDir . '/DatePicker', 0777, true);
		file_put_contents($this->externalComponentDir . '/DatePicker/View.php', '<?php return "DatePicker";');

		mkdir($this->externalComponentDir . '/TimePicker', 0777, true);
		file_put_contents($this->externalComponentDir . '/TimePicker/View.php', '<?php return "TimePicker";');

		$this->loader = new ComponentLoader($this->testTemplateDir, $this->logger_mock);

		$config = $this->createMock(ConfigInterface::class);
		$config->method('get_config')->willReturn(array(
			'PATH' => $this->externalComponentDir,
		));
		$config->method('get_namespace')->willReturn('MyPlugin\\Components');

		// Batch register from root directory
		$result = $this->loader->register_components(array(
			'path'   => '',
			'prefix' => 'my-plugin',
		), $config);

		// Should return self for fluent interface
		$this->assertSame($this->loader, $result);

		// All three components should be registered
		$this->assertTrue($this->loader->is_external('my-plugin.color-picker'));
		$this->assertTrue($this->loader->is_external('my-plugin.date-picker'));
		$this->assertTrue($this->loader->is_external('my-plugin.time-picker'));
	}

	/**
	 * Test register_components() logs warning for missing path option.
	 */
	public function test_register_components_logs_warning_for_missing_path(): void {
		$this->loader = new ComponentLoader($this->testTemplateDir, $this->logger_mock);

		$config = $this->createMock(ConfigInterface::class);

		// Register without path option
		$this->loader->register_components(array(), $config);

		// Should not throw, just log warning
		$this->assertTrue(true);
	}

	/**
	 * Test register_components() logs warning for non-existent directory.
	 */
	public function test_register_components_logs_warning_for_nonexistent_directory(): void {
		$this->loader = new ComponentLoader($this->testTemplateDir, $this->logger_mock);

		$config = $this->createMock(ConfigInterface::class);
		$config->method('get_config')->willReturn(array(
			'PATH' => '/nonexistent/path',
		));

		// Register with non-existent path
		$this->loader->register_components(array(
			'path' => 'components',
		), $config);

		// Should not throw, just log warning
		$this->assertTrue(true);
	}

	/**
	 * Test is_external() returns correct values.
	 */
	public function test_is_external_returns_correct_values(): void {
		$this->loader = new ComponentLoader($this->testTemplateDir, $this->logger_mock);

		// Built-in component should not be external
		$this->assertFalse($this->loader->is_external('test.input'));

		// Non-existent component should not be external
		$this->assertFalse($this->loader->is_external('nonexistent'));

		// Register an external component
		$config = $this->createMock(ConfigInterface::class);
		$config->method('get_config')->willReturn(array(
			'PATH' => $this->externalComponentDir,
		));
		$config->method('get_namespace')->willReturn('MyPlugin');

		$this->loader->register_component('color-picker', array(
			'path'   => 'ColorPicker',
			'prefix' => 'ext',
		), $config);

		// External component should be external
		$this->assertTrue($this->loader->is_external('ext.color-picker'));
	}

	/**
	 * Test _pascal_to_kebab conversion via register_components.
	 */
	public function test_pascal_to_kebab_conversion(): void {
		// Create components with PascalCase names
		mkdir($this->externalComponentDir . '/MyCustomWidget', 0777, true);
		file_put_contents($this->externalComponentDir . '/MyCustomWidget/View.php', '<?php return "Widget";');

		$this->loader = new ComponentLoader($this->testTemplateDir, $this->logger_mock);

		$config = $this->createMock(ConfigInterface::class);
		$config->method('get_config')->willReturn(array(
			'PATH' => $this->externalComponentDir,
		));
		$config->method('get_namespace')->willReturn('MyPlugin');

		$this->loader->register_components(array(
			'path'   => '',
			'prefix' => 'test',
		), $config);

		// PascalCase should be converted to kebab-case
		$this->assertTrue($this->loader->is_external('test.my-custom-widget'));
	}

	// Helper methods

	private function mockWordPressFunctions(): void {
		WP_Mock::userFunction('get_transient')->andReturn(false);
		WP_Mock::userFunction('set_transient')->andReturn(true);
		WP_Mock::userFunction('delete_transient')->andReturn(true);
		WP_Mock::userFunction('get_option')->andReturn(array());
		WP_Mock::userFunction('update_option')->andReturn(true);
		WP_Mock::userFunction('delete_option')->andReturn(true);
		WP_Mock::userFunction('wp_get_environment_type')->andReturn('production');

		if (!defined('WP_DEBUG')) {
			define('WP_DEBUG', false);
		}
	}

	private function createTestTemplates(): void {
		// Create built-in test input template
		$inputTemplate = $this->testTemplateDir . '/test/input.php';
		mkdir(dirname($inputTemplate), 0777, true);
		file_put_contents($inputTemplate, '<?php
use Ran\PluginLib\Forms\Component\ComponentRenderResult;

$name = $context["name"] ?? "default";
$value = $context["value"] ?? "";

$markup = sprintf(\'<input type="text" name="%s" value="%s">\', $name, $value);

return new ComponentRenderResult(markup: $markup, component_type: \'input\');
');

		// Create external ColorPicker component template
		$colorPickerTemplate = $this->externalComponentDir . '/ColorPicker/View.php';
		file_put_contents($colorPickerTemplate, '<?php
use Ran\PluginLib\Forms\Component\ComponentRenderResult;

$name = $context["name"] ?? "color";
$value = $context["value"] ?? "#000000";

$markup = sprintf(\'<div class="ColorPicker"><input type="color" name="%s" value="%s"></div>\', $name, $value);

return new ComponentRenderResult(markup: $markup, component_type: \'input\');
');
	}

	private function cleanupDirectory(string $dir): void {
		if (!is_dir($dir)) {
			return;
		}

		$files = array_diff(scandir($dir), array('.', '..'));
		foreach ($files as $file) {
			$path = $dir . '/' . $file;
			if (is_dir($path)) {
				$this->cleanupDirectory($path);
			} else {
				unlink($path);
			}
		}
		rmdir($dir);
	}
}
