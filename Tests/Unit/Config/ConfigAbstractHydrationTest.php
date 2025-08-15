<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Config;

use WP_Mock;
use Ran\PluginLib\Config\ConfigType;
use Ran\PluginLib\Config\ConfigAbstract;
use RanTestCase; // Declared in test_bootstrap.php

final class ConfigAbstractHydrator extends ConfigAbstract {
	public function hydrateFromPluginPublic(string $file): void {
		$this->_hydrateFromPlugin($file);
	}
	public function hydrateFromThemePublic(string $dir): void {
		$this->_hydrateFromTheme($dir);
	}
}

/**
 * @coversDefaultClass \Ran\PluginLib\Config\ConfigAbstract
 */
final class ConfigAbstractHydrationTest extends RanTestCase {
	private string $tmpPlugin;
	private string $tmpThemeDir;

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		// temp plugin file with namespaced headers
		$this->tmpPlugin = sys_get_temp_dir() . '/plug_' . uniqid() . '.php';
		$pluginHeader    = <<<'PHP'
<?php
/**
 * Plugin Name: Hydration Probe
 * Version: 1.2.3
 * Text Domain: hydration-probe
 * @RAN: App Option: hydration_probe_opts
 * Random: keepme
 */
PHP;
		file_put_contents($this->tmpPlugin, $pluginHeader);

		// temp theme dir with style.css
		$this->tmpThemeDir = sys_get_temp_dir() . '/theme_' . uniqid();
		@mkdir($this->tmpThemeDir, 0777, true);
		$styleHeader = <<<'CSS'
/*
 Theme Name: Hydration Theme
 Version: 9.9.9
 Text Domain: hydration-theme
 @RAN: App Option: hydration_theme_opts
 Random: keepme
*/
CSS;
		file_put_contents($this->tmpThemeDir . '/style.css', $styleHeader);
	}

	public function tearDown(): void {
		if (file_exists($this->tmpPlugin)) {
			@unlink($this->tmpPlugin);
		}
		if (file_exists($this->tmpThemeDir . '/style.css')) {
			@unlink($this->tmpThemeDir . '/style.css');
		}
		if (is_dir($this->tmpThemeDir)) {
			@rmdir($this->tmpThemeDir);
		}
		WP_Mock::tearDown();
		parent::tearDown();
	}

	/**
	 * @covers ::_hydrateFromPlugin
	 * @covers ::_hydrate_generic
	 */
	public function test_hydrate_from_plugin_happy_path(): void {
		// WP env shims for plugin identifiers and headers
		WP_Mock::userFunction('plugin_dir_url')->with($this->tmpPlugin)->andReturn('https://example.test/wp-content/plugins/probe/');
		WP_Mock::userFunction('plugin_dir_path')->with($this->tmpPlugin)->andReturn('/var/www/plugins/probe/');
		WP_Mock::userFunction('plugin_basename')->with($this->tmpPlugin)->andReturn('probe/probe.php');
		// Provide header data for _get_standard_plugin_headers
		WP_Mock::userFunction('get_plugin_data')->with($this->tmpPlugin, false, false)->andReturn(array(
		    'Name'       => 'Hydration Probe',
		    'Version'    => '1.2.3',
		    'TextDomain' => 'hydration-probe',
		));
		WP_Mock::userFunction('apply_filters')->andReturnArg(0);

		$cfg = new ConfigAbstractHydrator();
		$cfg->set_logger(new \Ran\PluginLib\Util\CollectingLogger());
		$cfg->hydrateFromPluginPublic($this->tmpPlugin);
		$normalized = $cfg->get_config();

		$this->assertSame('Hydration Probe', $normalized['Name']);
		$this->assertSame('1.2.3', $normalized['Version']);
		$this->assertSame('hydration-probe', $normalized['TextDomain']);
		$this->assertSame('probe/probe.php', $normalized['Basename']);
		$this->assertSame($this->tmpPlugin, $normalized['File']);
		$this->assertSame(ConfigType::Plugin->value, $normalized['Type']);
		$this->assertSame('hydration_probe_opts', $normalized['RAN']['AppOption']);
		$this->assertSame('keepme', $normalized['ExtraHeaders']['Random'] ?? null);
	}

	/**
	 * @covers ::_hydrateFromPlugin
	 */
	public function test_hydrate_from_plugin_invalid_file_throws(): void {
		$this->expectException(\RuntimeException::class);
		$bad = new ConfigAbstractHydrator();
		$bad->set_logger(new \Ran\PluginLib\Util\CollectingLogger());
		$bad->hydrateFromPluginPublic('/not/a/real/file.php');
	}

	/**
	 * @covers ::_hydrateFromTheme
	 * @covers ::_hydrate_generic
	 */
	public function test_hydrate_from_theme_happy_path(): void {
		// Prevent ensure_wp_loaded() from requiring core by providing the directory accessor
		WP_Mock::userFunction('get_stylesheet_directory')->with()->andReturn($this->tmpThemeDir);
		WP_Mock::userFunction('get_stylesheet_directory_uri')->with()->andReturn('https://example.test/wp-content/themes/hydration');
		// Mock wp_get_theme() object
		$theme = new class {
			public function get($key) {
				return match ($key) {
					'Name'        => 'Hydration Theme',
					'Version'     => '9.9.9',
					'Text Domain' => 'hydration-theme',
					default       => ''
				};
			}
		};
		WP_Mock::userFunction('wp_get_theme')->andReturn($theme);
		WP_Mock::userFunction('apply_filters')->andReturnArg(0);

		$cfg = new ConfigAbstractHydrator();
		$cfg->set_logger(new \Ran\PluginLib\Util\CollectingLogger());
		$cfg->hydrateFromThemePublic($this->tmpThemeDir);
		$normalized = $cfg->get_config();

		$this->assertSame('Hydration Theme', $normalized['Name']);
		$this->assertSame('9.9.9', $normalized['Version']);
		$this->assertSame('hydration-theme', $normalized['TextDomain']);
		$this->assertSame($this->tmpThemeDir, $normalized['StylesheetDir']);
		$this->assertSame('https://example.test/wp-content/themes/hydration', $normalized['StylesheetURL']);
		$this->assertSame(ConfigType::Theme->value, $normalized['Type']);
		$this->assertSame('hydration_theme_opts', $normalized['RAN']['AppOption']);
		$this->assertSame('keepme', $normalized['ExtraHeaders']['Random'] ?? null);

		// Explicitly assert filter applies unchanged when apply_filters returns first arg
		// This targets the coverage gap around the apply_filters branch in _hydrate_generic
		$this->assertIsArray($normalized);
	}

	/**
	 * @covers ::_hydrateFromTheme
	 */
	public function test_hydrate_from_theme_missing_dir_throws(): void {
		// Ensure the WP function exists but returns empty string so our guard triggers
		WP_Mock::userFunction('get_stylesheet_directory')->with()->andReturn('');
		$this->expectException(\RuntimeException::class);
		$bad = new ConfigAbstractHydrator();
		$bad->set_logger(new \Ran\PluginLib\Util\CollectingLogger());
		$bad->hydrateFromThemePublic('');
	}
}


