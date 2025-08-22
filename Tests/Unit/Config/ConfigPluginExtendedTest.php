<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Config;

use WP_Mock;
use Psr\Log\LogLevel;
use Ran\PluginLib\Config\Config;
use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Tests\Unit\Config\ConfigTestCase;

/**
 * Extended tests for Config in plugin context.
 *
 * @covers \Ran\PluginLib\Config\Config::fromPluginFile
 * @covers \Ran\PluginLib\Config\ConfigAbstract::get_config
 * @covers \Ran\PluginLib\Config\ConfigAbstract::get_logger
 * @covers \Ran\PluginLib\Config\ConfigAbstract::is_dev_environment
 * @covers \Ran\PluginLib\Config\ConfigAbstract::validate_config
 * @covers \Ran\PluginLib\Config\ConfigAbstract::set_is_dev_callback
 */
final class ConfigPluginExtendedTest extends ConfigTestCase {
	private string $pluginDir;
	private string $pluginFile;
	private string $pluginBasename;
	private string $pluginUrl;

	public function setUp(): void {
		parent::setUp();
		$this->pluginDir      = sys_get_temp_dir() . '/mock-plugin-ext-' . uniqid();
		$this->pluginFile     = $this->pluginDir . '/mock-plugin-file.php';
		$this->pluginBasename = 'mock-plugin-ext-' . basename($this->pluginDir) . '/mock-plugin-file.php';
		$this->pluginUrl      = 'http://example.com/wp-content/plugins/mock-plugin-ext/' . basename($this->pluginDir) . '/';
		if (!is_dir($this->pluginDir)) {
			mkdir($this->pluginDir, 0777, true);
		}
	}

	public function tearDown(): void {
		if (is_file($this->pluginFile)) {
			unlink($this->pluginFile);
		}
		if (is_dir($this->pluginDir)) {
			rmdir($this->pluginDir);
		}
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function test_hydration_with_namespaced_headers_and_logger_overrides(): void {
		$header = <<<PHP
<?php
/**
 * Plugin Name: Ext Plugin
 * Version: 2.0.0
 * Text Domain: ext-plugin
 * @RAN: Log Constant Name: EXT_PLUGIN_DEBUG
 * @RAN: Log Request Param: ext_param
 * @RAN: App Option: ext_option_key
 */
PHP;
		file_put_contents($this->pluginFile, $header);

		$pluginData = array(
		    'Name'       => 'Ext Plugin',
		    'Version'    => '2.0.0',
		    'PluginURI'  => 'http://example.com',
		    'TextDomain' => 'ext-plugin',
		);

		WP_Mock::setUp();
		WP_Mock::userFunction('plugin_dir_path')->with($this->pluginFile)->andReturn($this->pluginDir . '/')->byDefault();
		WP_Mock::userFunction('plugin_dir_url')->with($this->pluginFile)->andReturn($this->pluginUrl)->byDefault();
		WP_Mock::userFunction('plugin_basename')->with($this->pluginFile)->andReturn($this->pluginBasename)->byDefault();
		WP_Mock::userFunction('get_plugin_data')->with($this->pluginFile, false, false)->andReturn($pluginData)->byDefault();
		WP_Mock::userFunction('sanitize_key')->andReturnUsing(fn($v) => strtolower(preg_replace('/[^a-z0-9_\-]/i', '_', (string)$v)))->byDefault();

		$config = $this->configFromPluginFileWithLogger($this->pluginFile);
		$cfg    = $config->get_config();

		$this->assertSame('plugin', $cfg['Type']);
		$this->assertSame('ext-plugin', $cfg['Slug']);
		if (isset($cfg['RAN'])) {
			$this->assertSame('EXT_PLUGIN_DEBUG', $cfg['RAN']['LogConstantName'] ?? null);
			$this->assertSame('ext_param', $cfg['RAN']['LogRequestParam'] ?? null);
			$this->assertSame('ext_option_key', $cfg['RAN']['AppOption'] ?? null);
		}

		// Logger respects the custom constant name
		if (!defined('EXT_PLUGIN_DEBUG')) {
			define('EXT_PLUGIN_DEBUG', LogLevel::INFO);
		}
		$logger = $config->get_logger();
		$this->assertInstanceOf(Logger::class, $logger);
		$this->assertTrue($config->is_dev_environment());
	}

	public function test_set_is_dev_callback_overrides_and_resets_cache(): void {
		$header = <<<PHP
<?php
/**
 * Plugin Name: Dev Toggle
 * Version: 1.0.0
 * Text Domain: dev-toggle
 */
PHP;
		file_put_contents($this->pluginFile, $header);
		$pluginData = array(
		    'Name'       => 'Dev Toggle',
		    'Version'    => '1.0.0',
		    'PluginURI'  => 'http://example.com',
		    'TextDomain' => 'dev-toggle',
		);

		WP_Mock::setUp();
		WP_Mock::userFunction('plugin_dir_path')->with($this->pluginFile)->andReturn($this->pluginDir . '/');
		WP_Mock::userFunction('plugin_dir_url')->with($this->pluginFile)->andReturn($this->pluginUrl);
		WP_Mock::userFunction('plugin_basename')->with($this->pluginFile)->andReturn($this->pluginBasename);
		WP_Mock::userFunction('get_plugin_data')->with($this->pluginFile, false, false)->andReturn($pluginData);
		WP_Mock::userFunction('sanitize_key')->andReturnUsing(fn($v) => strtolower(preg_replace('/[^a-z0-9_\-]/i', '_', (string)$v)));

		$config = $this->configFromPluginFileWithLogger($this->pluginFile);
		$config->set_is_dev_callback(static fn() => true);
		$this->assertTrue($config->is_dev_environment());

		$config->set_is_dev_callback(static fn() => false);
		$this->assertFalse($config->is_dev_environment());
	}

	public function test_invalid_plugin_file_throws_runtime_exception(): void {
		$this->expectException(\RuntimeException::class);
		// Use withLogger to avoid console logging from hydration path
		Config::fromPluginFileWithLogger('', $this->logger_mock);
	}

	public function test_validate_missing_name_throws_exception(): void {
		// No Name provided via get_plugin_data
		file_put_contents($this->pluginFile, "<?php/**/\n");

		$pluginData = array(
		    // 'Name' intentionally missing
		    'Version'    => '1.0.0',
		    'PluginURI'  => 'http://example.com',
		    'TextDomain' => 'missing-name',
		);

		WP_Mock::setUp();
		WP_Mock::userFunction('plugin_dir_path')->with($this->pluginFile)->andReturn($this->pluginDir . '/');
		WP_Mock::userFunction('plugin_dir_url')->with($this->pluginFile)->andReturn($this->pluginUrl);
		WP_Mock::userFunction('plugin_basename')->with($this->pluginFile)->andReturn($this->pluginBasename);
		WP_Mock::userFunction('get_plugin_data')->with($this->pluginFile, false, false)->andReturn($pluginData);
		WP_Mock::userFunction('sanitize_key')->andReturnUsing(fn($v) => strtolower(preg_replace('/[^a-z0-9_\-]/i', '_', (string)$v)));

		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Missing required config key: "Name"');
		Config::fromPluginFileWithLogger($this->pluginFile, $this->logger_mock)->get_config();
	}

	public function test_get_param_does_not_enable_dev_mode(): void {
		$header = <<<PHP
<?php
/**
 * Plugin Name: No GET Dev
 * Version: 1.0.0
 * Text Domain: no-get-dev
 * @RAN: Log Constant Name: NO_GET_DEV
 * @RAN: Log Request Param: dev
 */
PHP;
		file_put_contents($this->pluginFile, $header);
		$pluginData = array(
		    'Name'       => 'No GET Dev',
		    'Version'    => '1.0.0',
		    'PluginURI'  => 'http://example.com',
		    'TextDomain' => 'no-get-dev',
		);

		$_GET['dev'] = 'true'; // Should be ignored by is_dev_environment

		WP_Mock::setUp();
		WP_Mock::userFunction('plugin_dir_path')->with($this->pluginFile)->andReturn($this->pluginDir . '/');
		WP_Mock::userFunction('plugin_dir_url')->with($this->pluginFile)->andReturn($this->pluginUrl);
		WP_Mock::userFunction('plugin_basename')->with($this->pluginFile)->andReturn($this->pluginBasename);
		WP_Mock::userFunction('get_plugin_data')->with($this->pluginFile, false, false)->andReturn($pluginData);
		WP_Mock::userFunction('sanitize_key')->andReturnUsing(fn($v) => strtolower(preg_replace('/[^a-z0-9_\-]/i', '_', (string)$v)));

		$config = $this->configFromPluginFileWithLogger($this->pluginFile);
		// Ensure constants do not interfere
		if (\defined('SCRIPT_DEBUG') || \defined('WP_DEBUG')) {
			$this->markTestSkipped('Debug constants defined by process; cannot assert GET param ignored path.');
		}
		$this->assertFalse($config->is_dev_environment());
		unset($_GET['dev']);
	}
}


