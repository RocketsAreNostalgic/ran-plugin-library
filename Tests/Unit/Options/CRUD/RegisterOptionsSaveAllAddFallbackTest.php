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
 * Expanded coverage for RegisterOptions::_save_all_options add() fallback path (lines 1248-1251).
 */
final class RegisterOptionsSaveAllAddFallbackTest extends PluginLibTestCase {
	use ExpectLogTrait;

	public function setUp(): void {
		parent::setUp();
		// Common WP functions used by wrappers
		WP_Mock::userFunction('get_site_option')->andReturn(array())->byDefault();
		WP_Mock::userFunction('get_blog_option')->andReturn(array())->byDefault();
		WP_Mock::userFunction('get_user_option')->andReturn(array())->byDefault();
		WP_Mock::userFunction('get_user_meta')->andReturn(array())->byDefault();
		WP_Mock::userFunction('wp_load_alloptions')->andReturn(array())->byDefault();
		// Make get_option emulate a missing row by returning the provided default (sentinel)
		WP_Mock::userFunction('get_option')->andReturnUsing(function ($option, $default = false) {
			return $default; // behave like WordPress when the option is truly missing
		})->byDefault();
		// Key normalization
		WP_Mock::userFunction('sanitize_key')->andReturnUsing(function ($key) {
			$key = strtolower((string) $key);
			$key = preg_replace('/[^a-z0-9_\-]+/i', '_', $key) ?? '';
			return trim($key, '_');
		});
		// Allow persistence via filters (site scope)
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist')
		    ->with(WP_Mock\Functions::type('bool'), WP_Mock\Functions::type('array'))
		    ->reply(true);
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist/scope/site')
		    ->with(WP_Mock\Functions::type('bool'), WP_Mock\Functions::type('array'))
		    ->reply(true);
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::flush
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_save_all_options
	 */
	public function test_save_all_options_falls_back_to_update_when_add_returns_false(): void {
		// Minimal config
		$config = $this->getMockBuilder(ConfigInterface::class)->getMock();
		$config->method('get_options_key')->willReturn('test_options');
		$config->method('get_logger')->willReturn($this->logger_mock);

		$opts = RegisterOptions::from_config($config, StorageContext::forSite(), true);

		// Stage a value so there's something to persist
		$opts->add_option('alpha', 'one');

		// Ensure immutable policy cannot veto this test path
		$this->_set_protected_property_value($opts, 'write_policy', new class implements \Ran\PluginLib\Options\Policy\WritePolicyInterface {
			public function allow(string $op, \Ran\PluginLib\Options\WriteContext $wc): bool {
				return true;
			}
		});

		// Inject storage mock: add() returns false, then update() returns true
		$storage = $this->createMock(\Ran\PluginLib\Options\Storage\OptionStorageInterface::class);
		$storage->method('read')->willReturn(array());
		$storage->method('scope')->willReturn(OptionScope::Site);
		$storage->expects($this->once())
		    ->method('add')
		    ->with('test_options', $this->isType('array'), true)
		    ->willReturn(false);
		$storage->expects($this->once())
		    ->method('update')
		    ->with('test_options', $this->isType('array'))
		    ->willReturn(true);
		$this->_set_protected_property_value($opts, 'storage', $storage);

		// Execute
		$this->assertTrue($opts->flush(false));

		// Assert logs after SUT ran
		$this->expectLog('debug', 'RegisterOptions: storage->add() selected');
		$this->expectLog('debug', 'RegisterOptions: storage->add() returned false; falling back to storage->update().');
		$this->expectLog('debug', 'RegisterOptions: storage->update() completed.');
		$this->expectLog('debug', 'RegisterOptions: _save_all_options completed');
	}
}
