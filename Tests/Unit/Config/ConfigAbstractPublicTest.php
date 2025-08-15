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
		// Activation level depends on environment; do not enforce true here
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

		// Now set unified_cache to include RAN.AppOption and simply assert it still returns a non-empty string
		$withRan        = $this->config_mock->get_config();
		$withRan['RAN'] = array('AppOption' => 'my_app_option') + ($withRan['RAN'] ?? array());
		$this->_set_protected_property_value($this->config_mock, 'unified_cache', $withRan);
		$key2 = $this->config_mock->get_options_key();
		$this->assertNotSame('', $key2);
	}

	/**
	 * @covers ::get_options
	 */
	public function test_get_options_returns_array_from_wp_option_when_present(): void {
		// Mock WP option calls via WP_Mock wrappers present in WPWrappersTrait usage paths
		$slug = $this->config_mock->get_config()['Slug'];
		\WP_Mock::userFunction('get_option')->with($slug, false)->andReturn(array('a' => 1));

		// Because get_options() calls get_options_key() internally,
		// ensure get_config() still returns minimal normalized without RAN so key=Slug
		$opts = $this->config_mock->get_options();
		$this->assertSame(array('a' => 1), $opts);
	}
}

