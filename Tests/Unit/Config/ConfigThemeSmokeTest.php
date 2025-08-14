<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Config;

use WP_Mock;
use Ran\PluginLib\Config\Config;
use RanTestCase; // Declared in test_bootstrap.php

/**
 * Smoke tests for Config in a theme context (happy-path behaviors).
 *
 * @covers \Ran\PluginLib\Config\Config::fromThemeDir
 * @covers \Ran\PluginLib\Config\ConfigAbstract::get_config
 * @covers \Ran\PluginLib\Config\ConfigAbstract::validate_config
 */
final class ConfigThemeSmokeTest extends RanTestCase {
	private string $theme_dir;

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		$this->theme_dir = sys_get_temp_dir() . '/mock-theme-' . uniqid();
		if (!is_dir($this->theme_dir)) {
			mkdir($this->theme_dir, 0777, true);
		}
		// Minimal style.css with a comment header block
		file_put_contents($this->theme_dir . '/style.css',
			"/*\nTheme Name: Mock Theme\nVersion: 1.2.3\nText Domain: mock-theme\n*/\n"
		);

		// Mock wp_get_theme to return expected values
		$themeMock = new class {
			public function get(string $key) {
				return match ($key) {
					'Name'       => 'Mock Theme',
					'Version'    => '1.2.3',
					'TextDomain' => 'mock-theme',
					default      => ''
				};
			}
		};
		WP_Mock::userFunction('wp_get_theme')
			->andReturn($themeMock)
			->byDefault();
		WP_Mock::userFunction('get_stylesheet_directory')
			->andReturn($this->theme_dir)
			->byDefault();
		WP_Mock::userFunction('get_stylesheet_directory_uri')
			->andReturn('http://example.com/wp-content/themes/mock-theme')
			->byDefault();
		WP_Mock::userFunction('sanitize_key')
			->andReturnUsing(fn($v) => strtolower(preg_replace('/[^a-z0-9_\-]/i', '_', (string)$v)));
	}

	public function tearDown(): void {
		if (is_dir($this->theme_dir)) {
			array_map('unlink', glob($this->theme_dir . '/*') ?: array());
			rmdir($this->theme_dir);
		}
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function test_happy_path_theme_initialization_and_accessors(): void {
		$config = Config::fromThemeDir($this->theme_dir);
		$cfg    = $config->get_config();

		$this->assertSame('theme', $cfg['Type']);
		$this->assertSame('Mock Theme', $cfg['Name']);
		$this->assertSame('1.2.3', $cfg['Version']);
		$this->assertSame('mock-theme', $cfg['TextDomain']);
		$this->assertArrayHasKey('StylesheetDir', $cfg);
		$this->assertArrayHasKey('StylesheetURL', $cfg);
	}
}


