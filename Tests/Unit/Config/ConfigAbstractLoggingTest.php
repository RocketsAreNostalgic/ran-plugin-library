<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Config;

use WP_Mock;
use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Config\ConfigType;
use Ran\PluginLib\Config\ConfigAbstract;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;

final class LoggingProbe extends ConfigAbstract {
	private array $overrideConfig = array();

	public function setConfig(array $c): void {
		$this->overrideConfig = $c;
	}

	public function get_config(): array {
		if (!empty($this->overrideConfig)) {
			return $this->overrideConfig;
		}
		return array(
		    'Name'     => 'LogProbe', 'Version' => '1.0.0', 'TextDomain' => 'log-probe',
		    'PATH'     => '/p', 'URL' => 'https://e', 'Slug' => 'log-probe', 'Type' => ConfigType::Plugin->value,
		    'Basename' => 'probe/probe.php', 'File' => __FILE__,
		);
	}

	public function hydrateFromPluginPublic(string $file): void {
		$this->_hydrateFromPlugin($file);
	}
	public function hydrateFromThemePublic(string $dir): void {
		$this->_hydrateFromTheme($dir);
	}
	public function callReadHeader(string $path) {
		return $this->_read_header_content($path);
	}
	public function callParseNS(string $block, array $reserved): array {
		return $this->_parse_namespaced_headers($block, $reserved);
	}
	public function callReservedPlugin(): array {
		return $this->_reserved_plugin_headers();
	}
}

/**
 * @coversDefaultClass \Ran\PluginLib\Config\ConfigAbstract
 */
final class ConfigAbstractLoggingTest extends PluginLibTestCase {
	public function test_hydrateFromPlugin_logs_entered_and_invalid_warning(): void {
		$probe = new LoggingProbe();
		// Use the test harness collecting logger and inject into probe
		$probe->set_logger($this->logger_mock);
		try {
			$probe->hydrateFromPluginPublic('/not/real/file.php');
		} catch (\RuntimeException $e) {
		}
		$this->expectLog('debug', array('::_hydrateFromPlugin', 'Entered.'), 1);
		$this->expectLog('warning', array('::_hydrateFromPlugin', 'Invalid or unreadable plugin file'), 1);
	}

	public function test_parse_namespaced_headers_reserved_collision_logs(): void {
		$probe = new LoggingProbe();
		$probe->set_logger($this->logger_mock);
		$reserved = $probe->callReservedPlugin();
		try {
			$probe->callParseNS("@RAN: Version: 2\n", $reserved);
		} catch (\Exception $e) {
		}
		$this->expectLog('warning', array('::_parse_namespaced_headers', 'Reserved header collision'), 1);
	}

	public function test_read_header_content_logs_failure_and_cache_hit(): void {
		$probe = new LoggingProbe();
		$probe->set_logger($this->logger_mock);
		$non = sys_get_temp_dir() . '/no_file_' . uniqid() . '.php';
		@unlink($non);
		@$probe->callReadHeader($non);
		$tmp = tempnam(sys_get_temp_dir(), 'hdr_');
		file_put_contents($tmp, 'ABC');
		$probe->callReadHeader($tmp);
		$probe->callReadHeader($tmp); // cache hit
		$this->expectLog('warning', array('::_read_header_content', 'Failed to read header content'), 1);
		$this->expectLog('debug', array('::_read_header_content', 'Cache hit'), 1);
	}

	public function test_is_dev_environment_logs_callback(): void {
		$probe = new LoggingProbe();
		$probe->setConfig(array(
		    'Name' => 'P', 'Version' => '1', 'TextDomain' => 'p', 'PATH' => '/p', 'URL' => 'https://e', 'Slug' => 'p',
		    'Type' => ConfigType::Plugin->value, 'Basename' => 'p/p.php', 'File' => __FILE__,
		));
		$probe->set_logger($this->logger_mock);
		$probe->set_is_dev_callback(function() {
			return true;
		});
		$probe->is_dev_environment();
		$this->expectLog('debug', array('::is_dev_environment', 'Decision via callback'), 1);
	}

	public function test_validate_config_and_get_options_key_log_messages(): void {
		$probe = new LoggingProbe();
		$probe->set_logger($this->logger_mock);
		$cfg = $probe->get_config();
		$probe->validate_config($cfg);
		$probe->get_options_key();
		$this->expectLog('debug', array('::validate_config', 'Validation OK'), 1);
		$this->expectLog('debug', array('::get_options_key', 'Resolved options key'), 1);
	}

	/**
	 * @covers ::_hydrate_generic
	 */
	public function test_hydrate_generic_logs_sequence(): void {
		$probe = new LoggingProbe();
		$probe->set_logger($this->logger_mock);
		$tmpPlugin = tempnam(sys_get_temp_dir(), 'plg_') . '.php';
		file_put_contents($tmpPlugin, "<?php\n/**\n * Plugin Name: X\n * Version: 1\n * Text Domain: x\n * @RAN: App Option: x_opts\n * Extra: kept\n */");
		WP_Mock::userFunction('plugin_dir_url')->with($tmpPlugin)->andReturn('https://example/plugins/x/');
		WP_Mock::userFunction('plugin_dir_path')->with($tmpPlugin)->andReturn('/var/www/plugins/x/');
		WP_Mock::userFunction('plugin_basename')->with($tmpPlugin)->andReturn('x/x.php');
		WP_Mock::userFunction('get_plugin_data')->with($tmpPlugin, false, false)->andReturn(array(
		    'Name' => 'X', 'Version' => '1', 'TextDomain' => 'x',
		));
		WP_Mock::userFunction('apply_filters')->andReturnArg(0);
		$probe->hydrateFromPluginPublic($tmpPlugin);
		@unlink($tmpPlugin);
		$this->expectLog('debug', array('::_hydrate_generic', 'ensure_wp_loaded() completed'), 1);
		$this->expectLog('debug', array('::_hydrate_generic', 'Collected standard headers'), 1);
		$this->expectLog('debug', array('::_hydrate_generic', 'Base identifiers'), 1);
		$this->expectLog('debug', array('::_hydrate_generic', 'Parsed namespaces'), 1);
		$this->expectLog('debug', array('::_hydrate_generic', "Applying filter 'ran/plugin_lib/config'"), 1);
		$this->expectLog('debug', array('::_hydrate_generic', 'Hydration complete'), 1);
	}
}


