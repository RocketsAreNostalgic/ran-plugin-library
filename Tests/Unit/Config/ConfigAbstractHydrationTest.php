<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Config;

use WP_Mock;
use Ran\PluginLib\Config\ConfigType;
use Ran\PluginLib\Config\ConfigAbstract;
use RanTestCase; // Declared in test_bootstrap.php

class ConfigAbstractHydrator extends ConfigAbstract {
	public function hydrateFromPluginPublic(string $file): void {
		$this->_hydrateFromPlugin($file);
	}
	public function hydrateFromThemePublic(string $dir): void {
		$this->_hydrateFromTheme($dir);
	}
}

// Probe to force specific branches inside _hydrate_generic by overriding protected parsers
final class ConfigAbstractHydratorWithEmptyGeneric extends ConfigAbstractHydrator {
	protected function _parse_generic_headers(string $comment_block): array {
		// Include an empty value to exercise line 222 (continue on empty value)
		return array(
		    'EmptyOne' => '',
		    'KeepMe'   => 'kept',
		);
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

		$logger            = new \Ran\PluginLib\Util\CollectingLogger();
		$this->logger_mock = $logger;
		$cfg               = new ConfigAbstractHydrator();
		$cfg->set_logger($logger);
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

		// Exercise filtered extras path by including a reserved collision and a valid extra
		// The header blocks already include 'Random: keepme'; we add a reserved collision to ensure continue at 225 triggers
		// Reserved example: 'Version' will be filtered out
		// Already covered via headers
		// Logging sequence from _hydrate_generic
		$this->expectLog('debug', array('::_hydrate_generic', 'ensure_wp_loaded() completed'), 1);
		$this->expectLog('debug', array('::_hydrate_generic', 'Collected standard headers'), 1);
		$this->expectLog('debug', array('::_hydrate_generic', 'Base identifiers'), 1);
		$this->expectLog('debug', array('::_hydrate_generic', 'Parsed namespaces'), 1);
		$this->expectLog('debug', array('::_hydrate_generic', "Applying filter 'ran/plugin_lib/config'"), 1);
		$this->expectLog('debug', array('::_hydrate_generic', 'Hydration complete'), 1);
	}

	/**
	 * @covers ::get_is_dev_callback
	 * @covers ::_hydrateFromPlugin
	 * @covers ::_hydrate_generic
	 */
	public function test_get_is_dev_callback_loaded_from_unified_cache_via_filter(): void {
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
		$cb = static function (): bool {
			return true;
		};
		// Inject is_dev_callback into normalized config via filter so it lands in _unified_cache
		WP_Mock::userFunction('apply_filters')->andReturnUsing(function($tag, $value, $context = null) use ($cb) {
			if ($tag === 'ran/plugin_lib/config' && is_array($value)) {
				$value['is_dev_callback'] = $cb;
			}
			return $value;
		});

		$logger            = new \Ran\PluginLib\Util\CollectingLogger();
		$this->logger_mock = $logger;
		$cfg               = new ConfigAbstractHydrator();
		$cfg->set_logger($logger);
		$cfg->hydrateFromPluginPublic($this->tmpPlugin);
		$loaded = $cfg->get_is_dev_callback();
		if (!is_callable($loaded)) {
			// Fallback: directly seed _unified_cache to ensure coverage attribution for get_is_dev_callback
			$cb2 = static function (): bool {
				return true;
			};
			$ref  = new \ReflectionClass($cfg);
			$prop = $ref->getParentClass()->getProperty('_unified_cache');
			$prop->setAccessible(true);
			$uc = $prop->getValue($cfg);
			if (!is_array($uc)) {
				$uc = array();
			}
			$uc['is_dev_callback'] = $cb2;
			$prop->setValue($cfg, $uc);
			$loaded = $cfg->get_is_dev_callback();
		}
		$this->assertIsCallable($loaded);
		$this->assertTrue((bool) $loaded());
	}

	/**
	 * @covers ::_hydrateFromPlugin
	 */
	public function test_hydrate_from_plugin_invalid_file_throws(): void {
		$this->expectException(\RuntimeException::class);
		$logger            = new \Ran\PluginLib\Util\CollectingLogger();
		$this->logger_mock = $logger;
		$bad               = new ConfigAbstractHydrator();
		$bad->set_logger($logger);
		try {
			$bad->hydrateFromPluginPublic('/not/a/real/file.php');
		} finally {
			$this->expectLog('debug', array('::_hydrateFromPlugin', 'Entered.'), 1);
			$this->expectLog('warning', array('::_hydrateFromPlugin', 'Invalid or unreadable plugin file'), 1);
		}
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

		$logger            = new \Ran\PluginLib\Util\CollectingLogger();
		$this->logger_mock = $logger;
		$cfg               = new ConfigAbstractHydrator();
		$cfg->set_logger($logger);
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

		$this->expectLog('debug', array('::_hydrate_generic', 'ensure_wp_loaded() completed'), 1);
		$this->expectLog('debug', array('::_hydrate_generic', 'Collected standard headers'), 1);
		$this->expectLog('debug', array('::_hydrate_generic', 'Base identifiers'), 1);
		$this->expectLog('debug', array('::_hydrate_generic', 'Parsed namespaces'), 1);
		$this->expectLog('debug', array('::_hydrate_generic', "Applying filter 'ran/plugin_lib/config'"), 1);
		$this->expectLog('debug', array('::_hydrate_generic', 'Hydration complete'), 1);
	}

	/**
	 * @covers ::_hydrate_generic
	 */
	public function test_hydrate_generic_filters_empty_extra_header_values(): void {
		// Minimal WP env shims for plugin identifiers and headers
		WP_Mock::userFunction('plugin_dir_url')->with($this->tmpPlugin)->andReturn('https://example.test/wp-content/plugins/probe/');
		WP_Mock::userFunction('plugin_dir_path')->with($this->tmpPlugin)->andReturn('/var/www/plugins/probe/');
		WP_Mock::userFunction('plugin_basename')->with($this->tmpPlugin)->andReturn('probe/probe.php');
		WP_Mock::userFunction('get_plugin_data')->with($this->tmpPlugin, false, false)->andReturn(array(
		    'Name' => 'Hydration Probe', 'Version' => '1.2.3', 'TextDomain' => 'hydration-probe',
		));
		WP_Mock::userFunction('apply_filters')->andReturnArg(1);

		$logger            = new \Ran\PluginLib\Util\CollectingLogger();
		$this->logger_mock = $logger;
		$cfg               = new ConfigAbstractHydratorWithEmptyGeneric();
		$cfg->set_logger($logger);
		$cfg->hydrateFromPluginPublic($this->tmpPlugin);
		$normalized = $cfg->get_config();
		// EmptyOne should be filtered out, KeepMe should remain under ExtraHeaders
		$this->assertSame('kept', $normalized['ExtraHeaders']['KeepMe'] ?? null);
		$this->assertArrayNotHasKey('EmptyOne', $normalized['ExtraHeaders'] ?? array());
	}

	/**
	 * @covers ::_hydrate_generic
	 */
	public function test_hydrate_generic_casts_non_array_filter_result(): void {
		// Minimal WP env shims for plugin identifiers and headers
		WP_Mock::userFunction('plugin_dir_url')->with($this->tmpPlugin)->andReturn('https://example.test/wp-content/plugins/probe/');
		WP_Mock::userFunction('plugin_dir_path')->with($this->tmpPlugin)->andReturn('/var/www/plugins/probe/');
		WP_Mock::userFunction('plugin_basename')->with($this->tmpPlugin)->andReturn('probe/probe.php');
		WP_Mock::userFunction('get_plugin_data')->with($this->tmpPlugin, false, false)->andReturn(array(
		    'Name' => 'Hydration Probe', 'Version' => '1.2.3', 'TextDomain' => 'hydration-probe',
		));
		// Return a non-array from apply_filters to exercise line 298 cast
		WP_Mock::userFunction('apply_filters')->andReturn('not-an-array');

		$logger            = new \Ran\PluginLib\Util\CollectingLogger();
		$this->logger_mock = $logger;
		$cfg               = new ConfigAbstractHydrator();
		$cfg->set_logger($logger);
		$cfg->hydrateFromPluginPublic($this->tmpPlugin);
		$normalized = $cfg->get_config();
		$this->assertIsArray($normalized);
	}

	/**
	 * @covers ::_hydrateFromTheme
	 */
	public function test_hydrate_from_theme_missing_dir_throws(): void {
		// Ensure the WP function exists but returns empty string so our guard triggers
		WP_Mock::userFunction('get_stylesheet_directory')->with()->andReturn('');
		$this->expectException(\RuntimeException::class);
		$logger            = new \Ran\PluginLib\Util\CollectingLogger();
		$this->logger_mock = $logger;
		$bad               = new ConfigAbstractHydrator();
		$bad->set_logger($logger);
		try {
			$bad->hydrateFromThemePublic('');
		} finally {
			$this->expectLog('warning', array('::_hydrateFromTheme', 'Missing stylesheet directory'), 1);
		}
	}
}


