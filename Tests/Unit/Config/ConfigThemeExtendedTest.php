<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Config;

use WP_Mock;
use Ran\PluginLib\Config\Config;
use RanTestCase; // Declared in test_bootstrap.php

/**
 * Extended tests for Config in theme context.
 *
 * @covers \Ran\PluginLib\Config\Config::fromThemeDir
 * @covers \Ran\PluginLib\Config\ConfigAbstract::get_config
 * @covers \Ran\PluginLib\Config\ConfigAbstract::validate_config
 */
final class ConfigThemeExtendedTest extends RanTestCase {
	private string $themeDir;

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		$this->themeDir = sys_get_temp_dir() . '/mock-theme-ext-' . uniqid();
		if (!is_dir($this->themeDir)) {
			mkdir($this->themeDir, 0777, true);
		}
		file_put_contents($this->themeDir . '/style.css', "/*\nTheme Name: Ext Theme\nVersion: 3.1.4\nText Domain: ext-theme\n*/\n");

		$themeMock = new class {
			public function get(string $key) {
				return match ($key) {
					'Name'       => 'Ext Theme',
					'Version'    => '3.1.4',
					'TextDomain' => 'ext-theme',
					default      => ''
				};
			}
		};

		WP_Mock::userFunction('wp_get_theme')->andReturn($themeMock)->byDefault();
		WP_Mock::userFunction('get_stylesheet_directory')->andReturn($this->themeDir)->byDefault();
		WP_Mock::userFunction('get_stylesheet_directory_uri')->andReturn('http://example.com/wp-content/themes/ext-theme')->byDefault();
		WP_Mock::userFunction('sanitize_key')->andReturnUsing(fn($v) => strtolower(preg_replace('/[^a-z0-9_\-]/i', '_', (string)$v)))->byDefault();
	}

	public function tearDown(): void {
		if (is_dir($this->themeDir)) {
			array_map('unlink', glob($this->themeDir . '/*') ?: array());
			rmdir($this->themeDir);
		}
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function test_theme_happy_path_and_required_keys(): void {
		$config = Config::fromThemeDir($this->themeDir);
		$cfg    = $config->get_config();

		$this->assertSame('theme', $cfg['Type']);
		$this->assertSame('Ext Theme', $cfg['Name']);
		$this->assertSame('3.1.4', $cfg['Version']);
		$this->assertSame('ext-theme', $cfg['TextDomain']);
		$this->assertArrayHasKey('StylesheetDir', $cfg);
		$this->assertArrayHasKey('StylesheetURL', $cfg);
	}

	public function test_validate_throws_when_stylesheet_keys_missing(): void {
		// Re-mock wp_get_theme to simulate missing data that would cause validate to fail after hydration
		$themeMock = new class {
			public function get(string $key) {
				return '';
			}
		};
		WP_Mock::tearDown();
		WP_Mock::setUp();
		WP_Mock::userFunction('wp_get_theme')->andReturn($themeMock);
		WP_Mock::userFunction('get_stylesheet_directory')->andReturn($this->themeDir);
		WP_Mock::userFunction('get_stylesheet_directory_uri')->andReturn('');
		WP_Mock::userFunction('sanitize_key')->andReturnUsing(fn($v) => strtolower(preg_replace('/[^a-z0-9_\-]/i', '_', (string)$v)));

		$this->expectException(\Exception::class);
		Config::fromThemeDir($this->themeDir)->get_config();
	}
}


