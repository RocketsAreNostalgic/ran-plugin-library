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
 * Test-only subclass that guarantees _get_storage() returns our injected mock.
 */
class TestableSaveAllRegisterOptions extends RegisterOptions {
	private ?\Ran\PluginLib\Options\Storage\OptionStorageInterface $forcedStorage = null;

	public function with_forced_storage(\Ran\PluginLib\Options\Storage\OptionStorageInterface $s): self {
		$this->forcedStorage = $s;
		return $this;
	}

	protected function _get_storage(): \Ran\PluginLib\Options\Storage\OptionStorageInterface {
		if ($this->forcedStorage instanceof \Ran\PluginLib\Options\Storage\OptionStorageInterface) {
			return $this->forcedStorage;
		}
		return parent::_get_storage();
	}
}

/**
 * Ensures the merge_from_db non-array branch is deterministically executed by
 * forcing the storage used by _save_all_options.
 */
final class RegisterOptionsSaveAllStorageOverrideTest extends PluginLibTestCase {
	use ExpectLogTrait;

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_save_all_options
	 */
	public function test_save_all_options_uses_forced_storage_and_logs_non_array_normalization(): void {
		// Core WP lookups
		WP_Mock::userFunction('get_option')->andReturn(array());
		WP_Mock::userFunction('current_user_can')->andReturn(true)->byDefault();
		// Ensure apply_filters is passthrough so onFilter hooks behave as expected
		WP_Mock::userFunction('apply_filters')->andReturnUsing(function($hook,$value) {
			return $value;
		});
		WP_Mock::userFunction('wp_load_alloptions')->andReturn(array());
		// Allow write gate via filters for this test instance
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist')
			->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
			->reply(true);
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist/scope/site')
			->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
			->reply(true);

		$config = $this->getMockBuilder(ConfigInterface::class)->getMock();
		$main   = 'override_storage_opts';
		$config->method('get_options_key')->willReturn($main);
		$config->method('get_logger')->willReturn($this->logger_mock);

		$opts = new TestableSaveAllRegisterOptions($config->get_options_key(), StorageContext::forSite(), true, $this->logger_mock);

		// In-memory options to ensure foreach merge runs
		$this->_set_protected_property_value($opts, 'options', array('a1' => 1, 'a2' => 2));

		$storage = $this->createMock(\Ran\PluginLib\Options\Storage\OptionStorageInterface::class);
		$storage->method('read')->willReturn(null); // non-array snapshot to trigger normalization
		$storage->method('scope')->willReturn(OptionScope::Site);
		$storage->expects($this->once())
			->method('update')
			->with($main, $this->callback(function($saved) {
				return is_array($saved)
					&& array_key_exists('a1', $saved) && $saved['a1'] === 1
					&& array_key_exists('a2', $saved) && $saved['a2'] === 2;
			}))
			->willReturn(true);

		$opts->with_forced_storage($storage);

		$this->assertTrue($opts->commit_merge());
		$this->expectLog('debug', 'RegisterOptions: _save_all_options merge_from_db snapshot not array; normalizing to empty array', 1);
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_save_all_options
	 * Isolated add() branch: force missing row via _do_get_option sentinel and ensure storage->add() is used.
	 */
	public function test_save_all_options_add_branch_with_forced_storage_and_logs(): void {
		// Core WP lookups
		WP_Mock::userFunction('current_user_can')->andReturn(true)->byDefault();
		// Ensure apply_filters is passthrough so onFilter hooks behave as expected
		WP_Mock::userFunction('apply_filters')->andReturnUsing(function($hook,$value) {
			return $value;
		});
		WP_Mock::userFunction('get_option')->andReturnUsing(function ($name, $default = null) {
			// When default provided, return it to simulate missing row (sentinel will flow through)
			return func_num_args() >= 2 ? $default : false;
		});
		WP_Mock::userFunction('wp_load_alloptions')->andReturn(array());
		// Allow write gate via filters for this test instance
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist')
			->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
			->reply(true);
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist/scope/site')
			->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
			->reply(true);

		$config = $this->getMockBuilder(ConfigInterface::class)->getMock();
		$main   = 'override_add_opts';
		$config->method('get_options_key')->willReturn($main);
		$config->method('get_logger')->willReturn($this->logger_mock);

		$opts = new TestableSaveAllRegisterOptions($config->get_options_key(), StorageContext::forSite(), true, $this->logger_mock);

		// In-memory payload to persist
		$this->_set_protected_property_value($opts, 'options', array('z' => 3));

		$storage = $this->createMock(\Ran\PluginLib\Options\Storage\OptionStorageInterface::class);
		$storage->method('read')->willReturn(array());
		$storage->method('scope')->willReturn(OptionScope::Site);
		$storage->expects($this->once())
			->method('add')
			->with($main, array('z' => 3), true)
			->willReturn(true);

		$opts->with_forced_storage($storage);

		$this->assertTrue($opts->commit_replace());
		$this->expectLog('debug', 'RegisterOptions: storage->add() selected', 1);
	}
}
