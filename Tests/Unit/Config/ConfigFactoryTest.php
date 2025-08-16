<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Config;

use WP_Mock;
use Ran\PluginLib\Config\Config;

/**
 * Tests for Config factory methods and logger injection.
 *
 * @covers \Ran\PluginLib\Config\Config::fromPluginFileWithLogger
 * @covers \Ran\PluginLib\Config\Config::fromThemeDirWithLogger
 * @covers \Ran\PluginLib\Config\Config::fromPluginFile
 * @covers \Ran\PluginLib\Config\Config::fromThemeDir
 * @covers \Ran\PluginLib\Config\ConfigAbstract::get_config
 * @covers \Ran\PluginLib\Config\ConfigAbstract::_hydrateFromPlugin
 * @covers \Ran\PluginLib\Config\ConfigAbstract::_hydrateFromTheme
 */
final class ConfigFactoryTest extends ConfigTestCase {
	public function test_from_plugin_file_returns_instance_and_hydrates_without_logger(): void {
		// Arrange temporary plugin file and WP mocks
		$plugin_dir      = sys_get_temp_dir() . '/mock-plugin-no-logger-' . uniqid('', true);
		$plugin_file     = $plugin_dir . '/mock-plugin-file.php';
		$plugin_basename = 'mock-plugin-' . basename($plugin_dir) . '/mock-plugin-file.php';
		$plugin_url      = 'http://example.com/wp-content/plugins/mock-plugin/' . basename($plugin_dir) . '/';
		$plugin_data     = array(
		    'Name'       => 'Mock Plugin (No Logger)',
		    'Version'    => '1.2.3',
		    'PluginURI'  => 'http://example.com',
		    'TextDomain' => 'mock-plugin-no-logger',
		);

		if (!is_dir($plugin_dir)) {
			mkdir($plugin_dir, 0777, true);
		}
		file_put_contents(
			$plugin_file,
			"<?php\n/**\n * Plugin Name: {$plugin_data['Name']}\n * Version: {$plugin_data['Version']}\n * Text Domain: {$plugin_data['TextDomain']}\n */\n"
		);

		WP_Mock::setUp();
		WP_Mock::userFunction('plugin_dir_path')->with($plugin_file)->andReturn($plugin_dir . '/')->byDefault();
		WP_Mock::userFunction('plugin_dir_url')->with($plugin_file)->andReturn($plugin_url)->byDefault();
		WP_Mock::userFunction('plugin_basename')->with($plugin_file)->andReturn($plugin_basename)->byDefault();
		WP_Mock::userFunction('get_plugin_data')->with($plugin_file, false, false)->andReturn($plugin_data)->byDefault();
		WP_Mock::userFunction('sanitize_key')->andReturnUsing(fn($v) => strtolower(preg_replace('/[^a-z0-9_\-]/i', '_', (string) $v)))->byDefault();

		try {
			// Act
			$config = Config::fromPluginFile($plugin_file);
			$cfg    = $config->get_config();

			// Assert: instance and normalized keys present
			$this->assertInstanceOf(Config::class, $config);
			$this->assertSame('plugin', $cfg['Type']);
			$this->assertSame($plugin_data['Name'], $cfg['Name']);
			$this->assertSame($plugin_data['Version'], $cfg['Version']);
			$this->assertSame($plugin_data['TextDomain'], $cfg['TextDomain']);
			$this->assertSame($plugin_dir . '/', $cfg['PATH']);
			$this->assertSame($plugin_url, $cfg['URL']);
			$this->assertSame($plugin_basename, $cfg['Basename']);
			$this->assertSame($plugin_file, $cfg['File']);
		} finally {
			// Cleanup
			if (is_file($plugin_file)) {
				@unlink($plugin_file);
			}
			if (is_dir($plugin_dir)) {
				@rmdir($plugin_dir);
			}
			WP_Mock::tearDown();
		}
	}

	public function test_from_plugin_file_with_logger_injects_logger_and_hydrates(): void {
		// Arrange temporary plugin file and WP mocks
		$plugin_dir      = sys_get_temp_dir() . '/mock-plugin-' . uniqid('', true);
		$plugin_file     = $plugin_dir . '/mock-plugin-file.php';
		$plugin_basename = 'mock-plugin-' . basename($plugin_dir) . '/mock-plugin-file.php';
		$plugin_url      = 'http://example.com/wp-content/plugins/mock-plugin/' . basename($plugin_dir) . '/';
		$plugin_data     = array(
			'Name'       => 'Mock Plugin (Factory)',
			'Version'    => '9.9.9',
			'PluginURI'  => 'http://example.com',
			'TextDomain' => 'mock-plugin-factory',
		);

		if (!is_dir($plugin_dir)) {
			mkdir($plugin_dir, 0777, true);
		}
		file_put_contents(
			$plugin_file,
			"<?php\n/**\n * Plugin Name: {$plugin_data['Name']}\n * Version: {$plugin_data['Version']}\n * Text Domain: {$plugin_data['TextDomain']}\n */\n"
		);

		WP_Mock::setUp();
		WP_Mock::userFunction('plugin_dir_path')->with($plugin_file)->andReturn($plugin_dir . '/')->byDefault();
		WP_Mock::userFunction('plugin_dir_url')->with($plugin_file)->andReturn($plugin_url)->byDefault();
		WP_Mock::userFunction('plugin_basename')->with($plugin_file)->andReturn($plugin_basename)->byDefault();
		WP_Mock::userFunction('get_plugin_data')->with($plugin_file, false, false)->andReturn($plugin_data)->byDefault();
		WP_Mock::userFunction('sanitize_key')->andReturnUsing(fn($v) => strtolower(preg_replace('/[^a-z0-9_\-]/i', '_', (string) $v)))->byDefault();

		try {
			// Act
			$config = $this->configFromPluginFileWithLogger($plugin_file);
			$cfg    = $config->get_config();

			// Assert: injected logger is used by the instance
			$this->assertSame($this->logger_mock, $config->get_logger());

			// Assert: normalized keys present
			$this->assertSame('plugin', $cfg['Type']);
			$this->assertSame($plugin_data['Name'], $cfg['Name']);
			$this->assertSame($plugin_data['Version'], $cfg['Version']);
			$this->assertSame($plugin_data['TextDomain'], $cfg['TextDomain']);
			$this->assertSame($plugin_dir . '/', $cfg['PATH']);
			$this->assertSame($plugin_url, $cfg['URL']);
			$this->assertSame($plugin_basename, $cfg['Basename']);
			$this->assertSame($plugin_file, $cfg['File']);
		} finally {
			// Cleanup
			if (is_file($plugin_file)) {
				@unlink($plugin_file);
			}
			if (is_dir($plugin_dir)) {
				@rmdir($plugin_dir);
			}
			WP_Mock::tearDown();
		}
	}

	public function test_from_theme_dir_with_logger_injects_logger_and_hydrates(): void {
		// Arrange temporary theme directory and WP mocks
		$theme_dir = sys_get_temp_dir() . '/mock-theme-' . uniqid('', true);
		if (!is_dir($theme_dir)) {
			mkdir($theme_dir, 0777, true);
		}
		file_put_contents($theme_dir . '/style.css', "/*\nTheme Name: Mock Theme (Factory)\nVersion: 2.3.4\nText Domain: mock-theme-factory\n*/\n");

		$themeMock = new class {
			public function get(string $key) {
				return match ($key) {
					'Name'       => 'Mock Theme (Factory)',
					'Version'    => '2.3.4',
					'TextDomain' => 'mock-theme-factory',
					default      => ''
				};
			}
		};

		WP_Mock::setUp();
		WP_Mock::userFunction('wp_get_theme')->andReturn($themeMock)->byDefault();
		WP_Mock::userFunction('get_stylesheet_directory')->andReturn($theme_dir)->byDefault();
		WP_Mock::userFunction('get_stylesheet_directory_uri')->andReturn('http://example.com/wp-content/themes/mock-theme-factory')->byDefault();
		WP_Mock::userFunction('sanitize_key')->andReturnUsing(fn($v) => strtolower(preg_replace('/[^a-z0-9_\-]/i', '_', (string) $v)))->byDefault();

		try {
			// Act
			$config = $this->configFromThemeDirWithLogger($theme_dir);
			$cfg    = $config->get_config();

			// Assert: injected logger is used by the instance
			$this->assertSame($this->logger_mock, $config->get_logger());

			// Assert: normalized keys present
			$this->assertSame('theme', $cfg['Type']);
			$this->assertSame('Mock Theme (Factory)', $cfg['Name']);
			$this->assertSame('2.3.4', $cfg['Version']);
			$this->assertSame('mock-theme-factory', $cfg['TextDomain']);
			$this->assertArrayHasKey('StylesheetDir', $cfg);
			$this->assertArrayHasKey('StylesheetURL', $cfg);
		} finally {
			// Cleanup
			$style = $theme_dir . '/style.css';
			if (is_file($style)) {
				@unlink($style);
			}
			if (is_dir($theme_dir)) {
				@rmdir($theme_dir);
			}
			WP_Mock::tearDown();
		}
	}

	public function test_from_theme_dir_without_logger_hydrates(): void {
		// Arrange temporary theme directory and WP mocks
		$theme_dir = sys_get_temp_dir() . '/mock-theme-no-logger-' . uniqid('', true);
		if (!is_dir($theme_dir)) {
			mkdir($theme_dir, 0777, true);
		}
		file_put_contents($theme_dir . '/style.css', "/*\nTheme Name: Mock Theme (No Logger)\nVersion: 3.4.5\nText Domain: mock-theme-no-logger\n*/\n");

		$themeMock = new class {
			public function get(string $key) {
				return match ($key) {
					'Name'       => 'Mock Theme (No Logger)',
					'Version'    => '3.4.5',
					'TextDomain' => 'mock-theme-no-logger',
					default      => ''
				};
			}
		};

		WP_Mock::setUp();
		WP_Mock::userFunction('wp_get_theme')->andReturn($themeMock)->byDefault();
		WP_Mock::userFunction('get_stylesheet_directory')->andReturn($theme_dir)->byDefault();
		WP_Mock::userFunction('get_stylesheet_directory_uri')->andReturn('http://example.com/wp-content/themes/mock-theme-no-logger')->byDefault();
		WP_Mock::userFunction('sanitize_key')->andReturnUsing(fn($v) => strtolower(preg_replace('/[^a-z0-9_\-]/i', '_', (string) $v)))->byDefault();

		try {
			// Act
			$config = Config::fromThemeDir($theme_dir);
			$cfg    = $config->get_config();

			// Assert: normalized keys present
			$this->assertSame('theme', $cfg['Type']);
			$this->assertSame('Mock Theme (No Logger)', $cfg['Name']);
			$this->assertSame('3.4.5', $cfg['Version']);
			$this->assertSame('mock-theme-no-logger', $cfg['TextDomain']);
			$this->assertArrayHasKey('StylesheetDir', $cfg);
			$this->assertArrayHasKey('StylesheetURL', $cfg);
		} finally {
			// Cleanup
			$style = $theme_dir . '/style.css';
			if (is_file($style)) {
				@unlink($style);
			}
			if (is_dir($theme_dir)) {
				@rmdir($theme_dir);
			}
			WP_Mock::tearDown();
		}
	}

	public function test_from_plugin_file_throws_on_invalid_path(): void {
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Config::fromPlugin requires a valid, readable plugin root file.');
		$bad = sys_get_temp_dir() . '/does-not-exist-' . uniqid('', true) . '.php';
		Config::fromPluginFile($bad); // should throw
	}

	public function test_from_theme_dir_throws_when_no_dir_and_wp_unavailable(): void {
		// Ensure WP shim is active and simulate missing stylesheet directory resolution
		WP_Mock::setUp();
		WP_Mock::userFunction('get_stylesheet_directory')->andReturn('');
		try {
			$this->expectException(\RuntimeException::class);
			$this->expectExceptionMessage('Config::fromThemeDir requires a stylesheet directory or WordPress runtime.');
			Config::fromThemeDir(''); // should throw due to empty dir
		} finally {
			WP_Mock::tearDown();
		}
	}
}
