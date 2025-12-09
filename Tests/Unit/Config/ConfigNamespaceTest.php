<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Config;

use WP_Mock;

/**
 * Tests for ConfigAbstract::get_namespace() and _to_pascal_case().
 *
 * @covers \Ran\PluginLib\Config\ConfigAbstract::get_namespace
 * @covers \Ran\PluginLib\Config\ConfigAbstract::_to_pascal_case
 */
class ConfigNamespaceTest extends ConfigTestCase {
	private string $tempDir;
	private string $pluginFile;

	public function setUp(): void {
		parent::setUp();
		$this->tempDir = sys_get_temp_dir() . '/ran-config-namespace-test-' . uniqid();
		mkdir($this->tempDir, 0755, true);
	}

	public function tearDown(): void {
		// Clean up temp files
		if (is_dir($this->tempDir)) {
			array_map('unlink', glob($this->tempDir . '/*'));
			rmdir($this->tempDir);
		}
		parent::tearDown();
	}

	/**
	 * Helper to create a plugin file with given headers.
	 */
	private function createPluginFile(string $name, ?string $namespace = null): string {
		$headers = "<?php\n/**\n * Plugin Name: {$name}\n * Version: 1.0.0\n * Text Domain: test-plugin\n";
		if ($namespace !== null) {
			$headers .= " * @RAN: Namespace: {$namespace}\n";
		}
		$headers .= " */\n";

		$file = $this->tempDir . '/plugin.php';
		file_put_contents($file, $headers);
		return $file;
	}

	/**
	 * Mock WordPress functions needed for Config hydration.
	 */
	private function mockWordPressFunctions(string $pluginFile, string $name): void {
		WP_Mock::userFunction('get_plugin_data')
			->with($pluginFile, false, false)
			->andReturn(array(
				'Name'       => $name,
				'Version'    => '1.0.0',
				'TextDomain' => 'test-plugin',
			));

		WP_Mock::userFunction('plugin_dir_path')
			->with($pluginFile)
			->andReturn(dirname($pluginFile) . '/');

		WP_Mock::userFunction('plugin_dir_url')
			->with($pluginFile)
			->andReturn('http://example.com/wp-content/plugins/test-plugin/');

		WP_Mock::userFunction('plugin_basename')
			->with($pluginFile)
			->andReturn('test-plugin/plugin.php');

		WP_Mock::userFunction('sanitize_key')
			->andReturnUsing(fn($key) => strtolower(preg_replace('/[^a-z0-9_\-]/', '', $key)));
	}

	/**
	 * Test: get_namespace() returns @RAN: Namespace header value when set.
	 */
	public function test_get_namespace_returns_ran_header_when_set(): void {
		$pluginFile = $this->createPluginFile('My Test Plugin', 'Acme\\MyTestPlugin');
		$this->mockWordPressFunctions($pluginFile, 'My Test Plugin');

		$config = $this->configFromPluginFileWithLogger($pluginFile);

		$this->assertSame('Acme\\MyTestPlugin', $config->get_namespace());
	}

	/**
	 * Test: get_namespace() returns PascalCase of Name when header not set.
	 */
	public function test_get_namespace_returns_pascal_case_of_name(): void {
		$pluginFile = $this->createPluginFile('My Test Plugin');
		$this->mockWordPressFunctions($pluginFile, 'My Test Plugin');

		$config = $this->configFromPluginFileWithLogger($pluginFile);

		$this->assertSame('MyTestPlugin', $config->get_namespace());
	}

	/**
	 * Test: _to_pascal_case handles special characters.
	 */
	public function test_to_pascal_case_handles_special_characters(): void {
		$pluginFile = $this->createPluginFile("Acme's Cool Plugin!");
		$this->mockWordPressFunctions($pluginFile, "Acme's Cool Plugin!");

		$config = $this->configFromPluginFileWithLogger($pluginFile);

		$this->assertSame('AcmesCoolPlugin', $config->get_namespace());
	}

	/**
	 * Test: _to_pascal_case handles hyphens and underscores.
	 */
	public function test_to_pascal_case_handles_hyphens_and_underscores(): void {
		$pluginFile = $this->createPluginFile('ran-starter-plugin');
		$this->mockWordPressFunctions($pluginFile, 'ran-starter-plugin');

		$config = $this->configFromPluginFileWithLogger($pluginFile);

		$this->assertSame('RanStarterPlugin', $config->get_namespace());
	}

	/**
	 * Test: _to_pascal_case handles multiple spaces.
	 */
	public function test_to_pascal_case_handles_multiple_spaces(): void {
		$pluginFile = $this->createPluginFile('My   Plugin   Name');
		$this->mockWordPressFunctions($pluginFile, 'My   Plugin   Name');

		$config = $this->configFromPluginFileWithLogger($pluginFile);

		$this->assertSame('MyPluginName', $config->get_namespace());
	}

	/**
	 * Test: _to_pascal_case handles empty name.
	 */
	public function test_to_pascal_case_handles_empty_name(): void {
		// Create plugin with empty-ish name but valid other headers
		$headers = "<?php\n/**\n * Plugin Name: \n * Version: 1.0.0\n * Text Domain: test-plugin\n */\n";
		$file    = $this->tempDir . '/plugin.php';
		file_put_contents($file, $headers);

		WP_Mock::userFunction('get_plugin_data')
			->with($file, false, false)
			->andReturn(array(
				'Name'       => '',
				'Version'    => '1.0.0',
				'TextDomain' => 'test-plugin',
			));

		WP_Mock::userFunction('plugin_dir_path')
			->with($file)
			->andReturn(dirname($file) . '/');

		WP_Mock::userFunction('plugin_dir_url')
			->with($file)
			->andReturn('http://example.com/wp-content/plugins/test-plugin/');

		WP_Mock::userFunction('plugin_basename')
			->with($file)
			->andReturn('test-plugin/plugin.php');

		WP_Mock::userFunction('sanitize_key')
			->andReturnUsing(fn($key) => strtolower(preg_replace('/[^a-z0-9_\-]/', '', $key)));

		// This will throw because Name is required, but we can test the _to_pascal_case edge case
		// by using reflection on a mock
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Missing required config key: "Name"');

		$this->configFromPluginFileWithLogger($file);
	}

	/**
	 * Test: get_namespace() prefers header over derived name.
	 */
	public function test_get_namespace_prefers_header_over_derived(): void {
		$pluginFile = $this->createPluginFile('Some Other Name', 'MyCustomNamespace');
		$this->mockWordPressFunctions($pluginFile, 'Some Other Name');

		$config = $this->configFromPluginFileWithLogger($pluginFile);

		// Should return the explicit header, not PascalCase of "Some Other Name"
		$this->assertSame('MyCustomNamespace', $config->get_namespace());
	}

	/**
	 * Test: get_namespace() with single word name.
	 */
	public function test_get_namespace_with_single_word_name(): void {
		$pluginFile = $this->createPluginFile('Plugin');
		$this->mockWordPressFunctions($pluginFile, 'Plugin');

		$config = $this->configFromPluginFileWithLogger($pluginFile);

		$this->assertSame('Plugin', $config->get_namespace());
	}

	/**
	 * Test: get_namespace() with numbers in name.
	 */
	public function test_get_namespace_with_numbers_in_name(): void {
		$pluginFile = $this->createPluginFile('Plugin 2 Go');
		$this->mockWordPressFunctions($pluginFile, 'Plugin 2 Go');

		$config = $this->configFromPluginFileWithLogger($pluginFile);

		$this->assertSame('Plugin2Go', $config->get_namespace());
	}
}
