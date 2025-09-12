<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options;

use WP_Mock;
use Ran\PluginLib\Options\OptionScope;
use Ran\PluginLib\Util\ExpectLogTrait;
use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Options\Storage\StorageContext;

/**
 * Test-only subclass that forces allow_persist filters to veto by overriding
 * the WPWrappersTrait apply_filter wrapper.
 */
class TestableGateRegisterOptions extends RegisterOptions {
	public function _do_apply_filter(string $hook_name, $value, ...$args) {
		if (strpos($hook_name, 'ran/plugin_lib/options/allow_persist') === 0) {
			return false;
		}
		return $value;
	}
}

/**
 * Deterministic test to assert the notice-level log from _apply_write_gate when filters veto.
 */
class RegisterOptionsGateNoticeTest extends PluginLibTestCase {
	use ExpectLogTrait;

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_apply_write_gate
	 */
	public function test_apply_write_gate_emits_notice_on_filter_veto(): void {
		// Ensure core WP lookups used during construction are stubbed
		WP_Mock::userFunction('get_option')->andReturn(array())->byDefault();
		WP_Mock::userFunction('wp_load_alloptions')->andReturn(array())->byDefault();

		$config = $this->getMockBuilder(ConfigInterface::class)->getMock();
		$config->method('get_options_key')->willReturn('gate_notice_opts');
		$config->method('get_logger')->willReturn($this->logger_mock);

		$opts = TestableGateRegisterOptions::from_config($config, StorageContext::forSite(), true);

		// Provide a storage mock so scope resolves to 'site'.
		$storage = $this->createMock(\Ran\PluginLib\Options\Storage\OptionStorageInterface::class);
		$storage->method('scope')->willReturn(OptionScope::Site);
		$this->_set_protected_property_value($opts, 'storage', $storage);

		$wc = \Ran\PluginLib\Options\WriteContext::for_clear('gate_notice_opts', OptionScope::Site->value, null, null, 'meta', false);

		$result = $this->_invoke_protected_method($opts, '_apply_write_gate', array('save_all', $wc));
		$this->assertFalse($result);
		$this->expectLog('notice', 'RegisterOptions: Write vetoed by allow_persist filter.', 1);
		$this->expectLog('debug', 'RegisterOptions: _apply_write_gate final decision', 1);
	}
}
