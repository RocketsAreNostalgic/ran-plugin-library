<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Config;

use WP_Mock;
use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Config\Config;
use Ran\PluginLib\Tests\Unit\Config\ConfigTestCase;

/**
 * Smoke tests for Config in a plugin context (happy-path behaviors).
 *
 * @covers \Ran\PluginLib\Config\Config::fromPluginFile
 * @covers \Ran\PluginLib\Config\ConfigAbstract::get_config
 * @covers \Ran\PluginLib\Config\ConfigAbstract::get_logger
 * @covers \Ran\PluginLib\Config\ConfigAbstract::is_dev_environment
 * @covers \Ran\PluginLib\Config\ConfigAbstract::validate_config
 */
final class ConfigPluginSmokeTest extends ConfigTestCase {
	private string $plugin_dir;
	private string $plugin_file;
	private string $plugin_basename;
	private string $plugin_url;
	private array $plugin_data;

	public function setUp(): void {
		parent::setUp();

		$this->plugin_dir      = sys_get_temp_dir() . '/mock-plugin-' . uniqid();
		$this->plugin_file     = $this->plugin_dir . '/mock-plugin-file.php';
		$this->plugin_basename = 'mock-plugin-' . basename($this->plugin_dir) . '/mock-plugin-file.php';
		$this->plugin_url      = 'http://example.com/wp-content/plugins/mock-plugin/' . basename($this->plugin_dir) . '/';

		if (!is_dir($this->plugin_dir)) {
			mkdir($this->plugin_dir, 0777, true);
		}

		$this->plugin_data = array(
			'Name'       => 'Mock Plugin for Testing',
			'Version'    => '1.0.0',
			'PluginURI'  => 'http://example.com',
			'TextDomain' => 'mock-plugin-textdomain'
		);

		file_put_contents(
			$this->plugin_file,
			"<?php\n/**\n * Plugin Name: {$this->plugin_data['Name']}\n * Version: {$this->plugin_data['Version']}\n * Text Domain: {$this->plugin_data['TextDomain']}\n */\n"
		);

		// Mock WP functions used during hydration
		WP_Mock::setUp();
		WP_Mock::userFunction('plugin_dir_path')->with($this->plugin_file)->andReturn($this->plugin_dir . '/')->byDefault();
		WP_Mock::userFunction('plugin_dir_url')->with($this->plugin_file)->andReturn($this->plugin_url)->byDefault();
		WP_Mock::userFunction('plugin_basename')->with($this->plugin_file)->andReturn($this->plugin_basename)->byDefault();
		WP_Mock::userFunction('get_plugin_data')->with($this->plugin_file, false, false)->andReturn($this->plugin_data)->byDefault();
		WP_Mock::userFunction('sanitize_key')->andReturnUsing(fn($v) => strtolower(preg_replace('/[^a-z0-9_\-]/i', '_', (string)$v)))->byDefault();
	}

	public function tearDown(): void {
		if (is_file($this->plugin_file)) {
			unlink($this->plugin_file);
		}
		if (is_dir($this->plugin_dir)) {
			rmdir($this->plugin_dir);
		}
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function test_happy_path_initialization_and_accessors(): void {
		// Arrange
		$config = $this->configFromPluginFileWithLogger($this->plugin_file);

		// Act
		$cfg = $config->get_config();

		// Assert: core normalized keys
		$this->assertSame('plugin', $cfg['Type']);
		$this->assertSame($this->plugin_data['Name'], $cfg['Name']);
		$this->assertSame($this->plugin_data['Version'], $cfg['Version']);
		$this->assertSame($this->plugin_data['TextDomain'], $cfg['TextDomain']);
		$this->assertSame($this->plugin_dir . '/', $cfg['PATH']);
		$this->assertSame($this->plugin_url, $cfg['URL']);
		$this->assertSame($this->plugin_basename, $cfg['Basename']);
		$this->assertSame($this->plugin_file, $cfg['File']);
		if (isset($cfg['RAN'])) {
			$this->assertArrayHasKey('AppOption', $cfg['RAN']);
		}

		// Logger should be constructible and returned
		$logger = $config->get_logger();
		$this->assertInstanceOf(Logger::class, $logger);

		// options key should default to RAN.AppOption or Slug
		$expected_key = $cfg['RAN']['AppOption'] ?? $cfg['Slug'];
		$this->assertSame($expected_key, $config->get_options_key());
	}
}
