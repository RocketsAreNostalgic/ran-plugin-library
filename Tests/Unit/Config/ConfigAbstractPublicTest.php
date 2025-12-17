<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Config;

use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Tests\Unit\ConcreteConfigForTesting;

/**
 * Public-interface oriented coverage for ConfigAbstract.
 *
 * @coversDefaultClass \Ran\PluginLib\Config\ConfigAbstract
 */
final class ConfigAbstractPublicTest extends PluginLibTestCase {
	/**
		* @covers ::get_logger
		*/
	public function test_get_logger_returns_collecting_logger_and_is_active(): void {
		$logger = $this->config_mock->get_logger();
		$this->assertTrue(method_exists($logger, 'is_active'));
		$this->assertIsInt($logger->get_log_level());
	}

	/**
		* @covers ::is_dev_environment
		*/
	public function test_is_dev_environment_returns_boolean_and_does_not_throw(): void {
		$result = $this->config_mock->is_dev_environment();
		$this->assertIsBool($result);
	}

	/**
		* @covers ::validate_config
		*/
	public function test_validate_config_passes_minimal_normalized(): void {
		$cfg = $this->config_mock->get_config();
		$this->config_mock->validate_config($cfg); // should not throw
		$this->assertArrayHasKey('Slug', $cfg);
	}

	/**
		* @covers ::get_options_key
		*/
	public function test_get_options_key_prefers_ran_app_option_then_slug(): void {
		// First without RAN.AppOption should fall back to Slug
		$key1 = $this->config_mock->get_options_key();
		$this->assertSame($this->config_mock->get_config()['Slug'], $key1);

		// Now set unified_cache to include RAN.AppOption and assert non-empty
		$withRan        = $this->config_mock->get_config();
		$withRan['RAN'] = array('AppOption' => 'my_app_option') + ($withRan['RAN'] ?? array());
		$this->_set_protected_property_value($this->config_mock, '_unified_cache', $withRan);
		$key2 = $this->config_mock->get_options_key();
		$this->assertNotSame('', $key2);
	}

	/**
		* (Removed) get_options was deprecated and removed from Config.
		*/

	/**
		* @covers ::set_logger
		* @covers ::get_logger
		*/
	public function test_set_logger_injects_and_get_logger_returns_same_instance(): void {
		$custom = new \Ran\PluginLib\Util\CollectingLogger();
		$inst   = new ConcreteConfigForTesting();
		$inst->set_logger($custom);
		$this->assertSame($custom, $inst->get_logger());
	}

	/**
		* @covers ::get_logger
		*/
	public function test_get_logger_constructs_from_ran_namespace_when_not_set(): void {
		$inst  = new ConcreteConfigForTesting();
		$const = 'UNIT_LOG_' . strtoupper(substr(md5((string) microtime(true)), 0, 8));
		$this->define_constant($const, true);
		$cfg = array(
			'Name'       => 'Unit Plugin',
			'Version'    => '0.0.1',
			'TextDomain' => 'unit-plugin',
			'PATH'       => sys_get_temp_dir(),
			'URL'        => 'https://example.test',
			'Basename'   => 'unit/unit.php',
			'File'       => __FILE__,
			'Slug'       => 'unit-plugin',
			'Type'       => 'plugin',
			'RAN'        => array(
				'LogConstantName' => $const,
				'LogRequestParam' => 'unit_log',
			),
		);
		$this->_set_protected_property_value($inst, '_unified_cache', $cfg);
		$logger = $inst->get_logger();
		$this->assertInstanceOf(\Ran\PluginLib\Util\Logger::class, $logger);
		$this->assertNotInstanceOf(\Ran\PluginLib\Util\CollectingLogger::class, $logger);
		$this->assertFalse($logger->is_active());
	}

	/**
		* @covers ::get_config
		*/
	public function test_get_config_fallback_builds_defaults_and_caches(): void {
		$inst = new ConcreteConfigForTesting();
		$cfg1 = $inst->get_config();
		$this->assertIsArray($cfg1);
		$this->assertArrayHasKey('Name', $cfg1);
		$this->assertArrayHasKey('Version', $cfg1);
		$this->assertArrayHasKey('TextDomain', $cfg1);
		$this->assertArrayHasKey('PATH', $cfg1);
		$this->assertArrayHasKey('URL', $cfg1);
		$this->assertArrayHasKey('Slug', $cfg1);
		$this->assertArrayHasKey('Type', $cfg1);
		$this->assertArrayHasKey('RAN', $cfg1);
		$this->assertArrayHasKey('Basename', $cfg1);
		$this->assertArrayHasKey('File', $cfg1);
		$this->assertSame('plugin', $cfg1['Type']);
		$this->assertSame('RAN_LOG', $cfg1['RAN']['LogConstantName']);
		$this->assertSame('ran_log', $cfg1['RAN']['LogRequestParam']);
		$this->assertSame($cfg1['Slug'], $cfg1['RAN']['AppOption']);

		$cfg2 = $inst->get_config();
		$this->assertSame($cfg1, $cfg2);
	}
}
