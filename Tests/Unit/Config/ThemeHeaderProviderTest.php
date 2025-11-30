<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Config;

use WP_Mock;
use Ran\PluginLib\Config\ConfigType;
use Ran\PluginLib\Config\ConfigAbstract;
use Ran\PluginLib\Config\ThemeHeaderProvider;
use RanTestCase; // Declared in test_bootstrap.php

/**
 * @covers \Ran\PluginLib\Config\ThemeHeaderProvider
 */
final class ThemeHeaderProviderTest extends RanTestCase {
	private string $stylesheetDir;

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		$this->stylesheetDir = sys_get_temp_dir() . '/mock-theme-' . uniqid();
		if (!is_dir($this->stylesheetDir)) {
			mkdir($this->stylesheetDir, 0777, true);
		}
		// Ensure style.css exists
		if (!file_exists($this->stylesheetDir . '/style.css')) {
			touch($this->stylesheetDir . '/style.css');
		}
	}

	public function tearDown(): void {
		if (file_exists($this->stylesheetDir . '/style.css')) {
			@unlink($this->stylesheetDir . '/style.css');
		}
		if (is_dir($this->stylesheetDir)) {
			@rmdir($this->stylesheetDir);
		}
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function test_get_standard_headers_delegates_to_config(): void {
		$cfg      = $this->createMock(ConfigAbstract::class);
		$expected = array('Name' => 'Mock Theme');
		$cfg->expects($this->once())
		    ->method('_get_standard_theme_headers')
		    ->with($this->stylesheetDir)
		    ->willReturn($expected);

		$provider = new ThemeHeaderProvider($this->stylesheetDir, $cfg);
		$this->assertSame($expected, $provider->get_standard_headers());
	}

	public function test_get_base_identifiers_uses_wp_functions_when_available(): void {
		$dir = $this->stylesheetDir;

		// Mock WordPress function that the provider now calls directly via WPWrappersTrait
		WP_Mock::userFunction('get_stylesheet_directory_uri')
			->once()
			->andReturn('https://example.com/wp-content/themes/mock-theme');

		$cfg      = $this->createMock(ConfigAbstract::class);
		$provider = new ThemeHeaderProvider($dir, $cfg);

		[$path, $url, $name] = $provider->get_base_identifiers();

		$this->assertSame($this->stylesheetDir, $path);
		$this->assertSame('https://example.com/wp-content/themes/mock-theme', $url);
		$this->assertSame(basename($this->stylesheetDir), $name);
	}

	public function test_comment_path_type_defaults_and_env_specific_keys(): void {
		$cfg      = $this->createMock(ConfigAbstract::class);
		$provider = new ThemeHeaderProvider($this->stylesheetDir, $cfg);

		$this->assertSame(rtrim($this->stylesheetDir, '/\\') . '/style.css', $provider->get_comment_source_path());
		$this->assertSame(ConfigType::Theme, $provider->get_type());
		$this->assertSame('ran_mytheme_app', $provider->get_default_app_option_slug('mytheme'));

		$env = $provider->get_env_specific_normalized_keys(array('base_path' => '/path', 'base_url' => 'https://example.com/theme'));
		$this->assertSame(
			array('StylesheetDir' => '/path', 'StylesheetURL' => 'https://example.com/theme'),
			$env
		);
	}
}


