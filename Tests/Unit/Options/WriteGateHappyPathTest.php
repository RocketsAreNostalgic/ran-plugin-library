<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options;

use WP_Mock;
use Ran\PluginLib\Options\OptionScope;
use Ran\PluginLib\Util\ExpectLogTrait;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;

/**
 * Focused happy-path tests for write-gate behavior where persistence is allowed.
 *
 * This suite isolates common operations and verifies they succeed when the
 * allow_persist filters return true.
 */
final class WriteGateHappyPathTest extends PluginLibTestCase {
	use ExpectLogTrait;
	public function setUp(): void {
		parent::setUp();

		// Basic WP wrappers used by SUT
		WP_Mock::userFunction('get_option')->andReturn(array());
		WP_Mock::userFunction('get_site_option')->andReturn(array());
		WP_Mock::userFunction('get_blog_option')->andReturn(array());
		WP_Mock::userFunction('get_user_option')->andReturn(array());
		WP_Mock::userFunction('get_user_meta')->andReturn(array());
		WP_Mock::userFunction('wp_load_alloptions')->andReturn(array());

		// Key normalization as in other tests
		WP_Mock::userFunction('sanitize_key')->andReturnUsing(function ($key) {
			$key = strtolower((string) $key);
			$key = preg_replace('/[^a-z0-9_\-]+/i', '_', $key) ?? '';
			return trim($key, '_');
		});

		// Ensure filters default to pass-through (prevents any residual veto stubs impacting these tests)
		WP_Mock::userFunction('apply_filters')->andReturnUsing(function($hook, $value) {
			return $value;
		});

		// Write functions default to success unless overridden in a test
		WP_Mock::userFunction('add_option')->andReturn(true);
		WP_Mock::userFunction('update_option')->andReturn(true);
		WP_Mock::userFunction('delete_option')->andReturn(true);
	}

	private function allow_all_persist_filters_for_site(): void {
		// Allow both general and site-scoped gates
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist')
			->with(WP_Mock\Functions::type('bool'), WP_Mock\Functions::type('array'))
			->reply(true);
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist/scope/site')
			->with(WP_Mock\Functions::type('bool'), WP_Mock\Functions::type('array'))
			->reply(true);
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::__construct
	 * @covers \Ran\PluginLib\Options\RegisterOptions::supports_autoload
	 * @covers \Ran\PluginLib\Options\RegisterOptions::delete_option
	 * @covers \Ran\PluginLib\Options\RegisterOptions::add_options
	 * @covers \Ran\PluginLib\Options\RegisterOptions::set_option
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_apply_write_gate
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_save_all_options
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_get_storage
	 */
	public function test_set_option_persists_when_allowed(): void {
		$opts = RegisterOptions::site('test_options');
		$opts->with_logger($this->logger_mock);
		$this->allow_all_persist_filters_for_site();

		// update_option returns true by default from setUp
		$result = $opts->set_option('foo', 'bar');

		$this->assertTrue($result);
		$this->assertSame('bar', $opts->get_option('foo'));
		// Logs: three write-gate sequences (pre-mutation, pre-persist, inside save)
		$this->expectLog('debug', '_apply_write_gate policy decision', 3);
		$this->expectLog('debug', '_apply_write_gate applying general allow_persist filter', 3);
		$this->expectLog('debug', '_apply_write_gate general filter result', 3);
		$this->expectLog('debug', '_apply_write_gate applying scoped allow_persist filter', 3);
		$this->expectLog('debug', '_apply_write_gate scoped filter result', 3);
		$this->expectLog('debug', '_apply_write_gate final decision', 3);
		$this->expectLog('debug', 'storage->update() completed.');
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::add_options
	 * @covers \Ran\PluginLib\Options\RegisterOptions::flush
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_apply_write_gate
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_save_all_options
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_get_storage
	 */
	public function test_add_options_and_flush_merge_from_db_allowed(): void {
		$opts = RegisterOptions::site('test_options');
		$opts->with_logger($this->logger_mock);
		$this->allow_all_persist_filters_for_site();

		// Stage two new options in memory
		$opts->add_options(array('x' => 10, 'y' => 20));

		// Simulate DB state to be merged
		$mockStorage = $this->createMock(\Ran\PluginLib\Options\Storage\OptionStorageInterface::class);
		$mockStorage->method('read')->willReturn(array('db_key' => 'db_value'));
		$mockStorage->method('scope')->willReturn(\Ran\PluginLib\Options\OptionScope::Site);
		$mockStorage->method('update')->willReturn(true);
		$this->_set_protected_property_value($opts, 'storage', $mockStorage);

		WP_Mock::userFunction('get_option')->andReturn(array('db_key' => 'db_value'));
		// Ensure the low-level update succeeds
		WP_Mock::userFunction('update_option')->andReturn(true);

		$result = $opts->flush(true); // merge_from_db = true
		$this->assertTrue($result);
		$this->assertSame(10, $opts->get_option('x'));
		$this->assertSame(20, $opts->get_option('y'));
		$this->assertSame('db_value', $opts->get_option('db_key'));
		// Logs: two write-gate sequences (add_options, save_all on flush)
		$this->expectLog('debug', '_apply_write_gate policy decision', 2);
		$this->expectLog('debug', '_apply_write_gate applying general allow_persist filter', 2);
		$this->expectLog('debug', '_apply_write_gate general filter result', 2);
		$this->expectLog('debug', '_apply_write_gate applying scoped allow_persist filter', 2);
		$this->expectLog('debug', '_apply_write_gate scoped filter result', 2);
		$this->expectLog('debug', '_apply_write_gate final decision', 2);
		$this->expectLog('debug', '_save_all_options starting');
		$this->expectLog('debug', 'storage->update() completed.');
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::delete_option
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_apply_write_gate
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_save_all_options
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_get_storage
	 */
	public function test_delete_option_persists_when_allowed(): void {
		$opts = RegisterOptions::site('test_options');
		$opts->with_logger($this->logger_mock);
		$this->allow_all_persist_filters_for_site();

		// Seed an option (allowed path)
		$opts->set_option('to_del', 123);
		$this->assertTrue($opts->has_option('to_del'));

		// Delete and persist
		$result = $opts->delete_option('to_del');
		$this->assertTrue($result);
		$this->assertFalse($opts->has_option('to_del'));
		// Logs: pre-seed set_option (3), then delete_option (1), then save_all (1) => 5
		$this->expectLog('debug', '_apply_write_gate policy decision', 5);
		$this->expectLog('debug', '_apply_write_gate applying general allow_persist filter', 5);
		$this->expectLog('debug', '_apply_write_gate general filter result', 5);
		$this->expectLog('debug', '_apply_write_gate applying scoped allow_persist filter', 5);
		$this->expectLog('debug', '_apply_write_gate scoped filter result', 5);
		$this->expectLog('debug', '_apply_write_gate final decision', 5);
		$this->expectLog('debug', '_save_all_options starting', 2);
		$this->expectLog('debug', 'storage->update() completed.', 2);
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::supports_autoload
	 */
	public function test_supports_autoload_happy_path(): void {
		$this->allow_all_persist_filters_for_site();
		$site = RegisterOptions::site('test_options');
		$net  = RegisterOptions::network('test_options');
		$this->assertTrue($site->supports_autoload());
		$this->assertFalse($net->supports_autoload());
	}
}
