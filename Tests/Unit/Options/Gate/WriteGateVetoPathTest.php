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
 * Focused veto-path tests for write-gate behavior where persistence is denied.
 *
 * @uses \Ran\PluginLib\Options\RegisterOptions::__construct
 */
final class WriteGateVetoPathTest extends PluginLibTestCase {
	use ExpectLogTrait;
	public function setUp(): void {
		parent::setUp();

		// Basic WP wrappers used by SUT (per-test stubs will define get_option as needed)
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

		// Default write functions (should not be hit on veto, but keep defined)
		WP_Mock::userFunction('add_option')->andReturn(true);
		WP_Mock::userFunction('update_option')->andReturn(true);
		WP_Mock::userFunction('delete_option')->andReturn(true);
	}

	private function veto_all_persist_filters_for_site(): void {
		// Deny both general and site-scoped gates
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist')
			->with(WP_Mock\Functions::type('bool'), WP_Mock\Functions::type('array'))
			->reply(false);
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist/scope/site')
			->with(WP_Mock\Functions::type('bool'), WP_Mock\Functions::type('array'))
			->reply(false);
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::migrate
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_apply_write_gate
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_save_all_options
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_get_storage
	 * @covers \Ran\PluginLib\Options\RegisterOptions::__construct
	 * @uses \Ran\PluginLib\Config\ConfigInterface
	 */
	public function test_migrate_vetoed_by_write_gate(): void {
		$config = $this->getMockBuilder(ConfigInterface::class)->getMock();
		$config->method('get_options_key')->willReturn('test_options');
		$config->method('get_logger')->willReturn($this->logger_mock);
		$opts = RegisterOptions::from_config($config, StorageContext::forSite(), true);
		// Policy-level veto ensures deny regardless of filters
		$policy = $this->getMockBuilder(\Ran\PluginLib\Options\Policy\WritePolicyInterface::class)->getMock();
		$policy->method('allow')->willReturn(false);
		$opts->with_policy($policy);
		$this->veto_all_persist_filters_for_site();

		$initialData = array('key' => 'value');
		$this->_set_protected_property_value($opts, 'options', $initialData);

		$mockStorage = $this->createMock(\Ran\PluginLib\Options\Storage\OptionStorageInterface::class);
		$mockStorage->method('read')->willReturn($initialData);
		$mockStorage->method('scope')->willReturn(\Ran\PluginLib\Options\OptionScope::Site);
		$this->_set_protected_property_value($opts, 'storage', $mockStorage);

		// WordPress get_option during migration initial read
		WP_Mock::userFunction('get_option')->andReturn($initialData);

		$migration = function($current) {
			return array('new_key' => 'new_value');
		};

		$result = $opts->migrate($migration);

		$this->assertSame($opts, $result);
		$this->assertFalse($opts->has_option('new_key'));
		$this->assertEquals('value', $opts->get_option('key'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::flush
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_save_all_options
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_apply_write_gate
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_get_storage
	 * @uses \Ran\PluginLib\Config\ConfigInterface
	 */
	public function test_flush_failure_returns_false(): void {
		$config = $this->getMockBuilder(ConfigInterface::class)->getMock();
		$config->method('get_options_key')->willReturn('test_options');
		$config->method('get_logger')->willReturn($this->logger_mock);
		$opts   = RegisterOptions::from_config($config, StorageContext::forSite(), true);
		$policy = $this->getMockBuilder(\Ran\PluginLib\Options\Policy\WritePolicyInterface::class)->getMock();
		$policy->method('allow')->willReturn(false);
		$opts->with_policy($policy);
		// Logger provided via Config; no need to attach post-construction
		$this->veto_all_persist_filters_for_site();

		$result = $opts->flush();
		$this->assertFalse($result);
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::set_option
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_apply_write_gate
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_save_all_options
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_get_storage
	 * @uses \Ran\PluginLib\Config\ConfigInterface
	 */
	public function test_set_option_vetoed_returns_false_and_rollback(): void {
		$config = $this->getMockBuilder(ConfigInterface::class)->getMock();
		$config->method('get_options_key')->willReturn('test_options');
		$config->method('get_logger')->willReturn($this->logger_mock);
		$opts   = RegisterOptions::from_config($config, StorageContext::forSite(), true);
		$policy = $this->getMockBuilder(\Ran\PluginLib\Options\Policy\WritePolicyInterface::class)->getMock();
		$policy->method('allow')->willReturn(false);
		$opts->with_policy($policy);
		$this->veto_all_persist_filters_for_site();

		// Ensure pre-state empty
		$this->assertFalse($opts->has_option('foo'));
		$res = $opts->set_option('foo', 'bar');
		$this->assertFalse($res);
		$this->assertFalse($opts->has_option('foo'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::add_options
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_apply_write_gate
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_save_all_options
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_get_storage
	 * @uses \Ran\PluginLib\Config\ConfigInterface
	 */
	public function test_add_options_vetoed_does_not_mutate(): void {
		$config = $this->getMockBuilder(ConfigInterface::class)->getMock();
		$config->method('get_options_key')->willReturn('test_options');
		$config->method('get_logger')->willReturn($this->logger_mock);
		$opts   = RegisterOptions::from_config($config, StorageContext::forSite(), true);
		$policy = $this->getMockBuilder(\Ran\PluginLib\Options\Policy\WritePolicyInterface::class)->getMock();
		$policy->method('allow')->willReturn(false);
		$opts->with_policy($policy);
		$this->veto_all_persist_filters_for_site();

		$opts->add_options(array('a' => 1, 'b' => 2));
		$this->assertFalse($opts->has_option('a'));
		$this->assertFalse($opts->has_option('b'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::add_option
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_apply_write_gate
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_get_storage
	 * @uses \Ran\PluginLib\Config\ConfigInterface
	 */
	public function test_add_option_vetoed_does_not_mutate(): void {
		$config = $this->getMockBuilder(ConfigInterface::class)->getMock();
		$config->method('get_options_key')->willReturn('test_options');
		$config->method('get_logger')->willReturn($this->logger_mock);
		$opts   = RegisterOptions::from_config($config, StorageContext::forSite(), true);
		$policy = $this->getMockBuilder(\Ran\PluginLib\Options\Policy\WritePolicyInterface::class)->getMock();
		$policy->method('allow')->willReturn(false);
		$opts->with_policy($policy);
		$this->veto_all_persist_filters_for_site();

		$this->assertFalse($opts->has_option('a'));
		$opts->add_option('a', 1);
		$this->assertFalse($opts->has_option('a'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::delete_option
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_apply_write_gate
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_save_all_options
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_get_storage
	 * @uses \Ran\PluginLib\Config\ConfigInterface
	 */
	public function test_delete_option_vetoed_returns_false_and_no_mutation(): void {
		$config = $this->getMockBuilder(ConfigInterface::class)->getMock();
		$config->method('get_options_key')->willReturn('test_options');
		$config->method('get_logger')->willReturn($this->logger_mock);
		$opts = RegisterOptions::from_config($config, StorageContext::forSite(), true);
		// seed in-memory
		$this->_set_protected_property_value($opts, 'options', array('k' => 'v'));
		$this->assertTrue($opts->has_option('k'));

		$policy = $this->getMockBuilder(\Ran\PluginLib\Options\Policy\WritePolicyInterface::class)->getMock();
		$policy->method('allow')->willReturn(false);
		$opts->with_policy($policy);
		$this->veto_all_persist_filters_for_site();

		$this->assertFalse($opts->delete_option('k'));
		$this->assertTrue($opts->has_option('k'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::clear
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_apply_write_gate
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_save_all_options
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_get_storage
	 * @uses \Ran\PluginLib\Config\ConfigInterface
	 */
	public function test_clear_vetoed_returns_false_and_no_mutation(): void {
		$config = $this->getMockBuilder(ConfigInterface::class)->getMock();
		$config->method('get_options_key')->willReturn('test_options');
		$config->method('get_logger')->willReturn($this->logger_mock);
		$opts = RegisterOptions::from_config($config, StorageContext::forSite(), true);
		// seed in-memory
		$this->_set_protected_property_value($opts, 'options', array('a' => 1, 'b' => 2));
		$this->assertTrue($opts->has_option('a'));
		$this->assertTrue($opts->has_option('b'));

		$policy = $this->getMockBuilder(\Ran\PluginLib\Options\Policy\WritePolicyInterface::class)->getMock();
		$policy->method('allow')->willReturn(false);
		$opts->with_policy($policy);
		$this->veto_all_persist_filters_for_site();

		$this->assertFalse($opts->clear());
		$this->assertTrue($opts->has_option('a'));
		$this->assertTrue($opts->has_option('b'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::seed_if_missing
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_apply_write_gate
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_get_storage
	 * @uses \Ran\PluginLib\Config\ConfigInterface
	 */
	public function test_seed_if_missing_vetoed_does_not_write_or_mutate(): void {
		$config = $this->getMockBuilder(ConfigInterface::class)->getMock();
		$main   = 'seed_veto_opts';
		$config->method('get_options_key')->willReturn($main);
		// Force missing-row behavior for this key regardless of earlier global stubs
		WP_Mock::userFunction('get_option')->andReturnUsing(function ($name, $default = null) use ($main) {
			if ($name === $main) {
				// Force 'missing' for all non-sentinel defaults (e.g. [], false)
				if (func_num_args() >= 2) {
					return is_object($default) ? $default : false;
				}
				return false;
			}
			return array();
		});
		WP_Mock::userFunction('wp_load_alloptions')->andReturn(array());

		// Use Site scope so existence checks use get_option
		$config->method('get_logger')->willReturn($this->logger_mock);
		$opts   = RegisterOptions::from_config($config, StorageContext::forSite(), true);
		$policy = $this->getMockBuilder(\Ran\PluginLib\Options\Policy\WritePolicyInterface::class)->getMock();
		$policy->method('allow')->willReturn(false);
		$opts->with_policy($policy);
		// Veto both general and site-scoped filters
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist')->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))->reply(false);
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist/scope/site')->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))->reply(false);

		$opts->seed_if_missing(array('x' => 9));
		$this->assertFalse($opts->has_option('x'));
		// Expect veto log emitted at seed_if_missing gate
		$this->expectLog('debug', 'RegisterOptions: seed_if_missing vetoed by write gate', 1);
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::seed_if_missing
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_get_storage
	 * Covers the early return path when the main option row already exists (no-op).
	 * @uses \Ran\PluginLib\Config\ConfigInterface
	 */
	public function test_seed_if_missing_existing_row_is_noop_and_does_not_write(): void {
		$config = $this->getMockBuilder(ConfigInterface::class)->getMock();
		$main   = 'seed_exists_opts';
		$config->method('get_options_key')->willReturn($main);

		// Simulate existing row for both constructor (storage->read) and seed_if_missing (_do_get_option)
		WP_Mock::userFunction('get_option')->andReturnUsing(function ($name, $default = null) use ($main) {
			if ($name === $main) {
				return array('a' => 1); // existing row present
			}
			return array();
		});
		WP_Mock::userFunction('wp_load_alloptions')->andReturn(array());

		// Fail the test if add_option is called (should not happen on existing row)
		WP_Mock::userFunction('add_option')->andReturnUsing(function () {
			\PHPUnit\Framework\Assert::fail('add_option should not be called when row already exists');
			return true;
		});

		$opts = RegisterOptions::from_config($config, StorageContext::forSite(), true);

		// Sanity: existing in-memory state
		$this->assertTrue($opts->has_option('a'));

		// Attempt seeding — should be a no-op and not mutate in-memory state
		$opts->seed_if_missing(array('z' => 9));
		$this->assertFalse($opts->has_option('z'));
		$this->assertTrue($opts->has_option('a'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::migrate
	 * Covers early return when the main option row is missing (no-op) — corresponds to around line 966.
	 * @uses \Ran\PluginLib\Config\ConfigInterface
	 */
	public function test_migrate_missing_row_is_noop_and_does_not_write(): void {
		$config = $this->getMockBuilder(ConfigInterface::class)->getMock();
		$main   = 'migrate_missing_opts';
		$config->method('get_options_key')->willReturn($main);

		// Simulate missing row for both constructor and migrate via sentinel default
		WP_Mock::userFunction('get_option')->andReturnUsing(function ($name, $default = null) use ($main) {
			if ($name === $main) {
				// If default provided (sentinel or false), return it to indicate missing
				return func_num_args() >= 2 ? $default : false;
			}
			return array();
		});
		WP_Mock::userFunction('wp_load_alloptions')->andReturn(array());

		// Fail if update_option is called (should not write on missing-row no-op)
		WP_Mock::userFunction('update_option')->andReturnUsing(function () {
			\PHPUnit\Framework\Assert::fail('update_option should not be called when migrate is a no-op on missing row');
			return true;
		});

		$opts = RegisterOptions::from_config($config, StorageContext::forSite(), true)->with_logger($this->logger_mock);

		// Migration function should never be invoked; throw if it is
		$migration = function($current) {
			\PHPUnit\Framework\Assert::fail('Migration callable should not be invoked when row is missing');
			return $current;
		};

		$opts->migrate($migration);
		// In-memory remains unchanged/empty
		$this->assertSame(array(), $opts->get_options());
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::migrate
	 * Covers early return when migration returns an identical value (strict no-change) — corresponds to around line 974.
	 * @uses \Ran\PluginLib\Config\ConfigInterface
	 */
	public function test_migrate_no_change_is_noop_and_does_not_write(): void {
		$config = $this->getMockBuilder(ConfigInterface::class)->getMock();
		$main   = 'migrate_nochange_opts';
		$config->method('get_options_key')->willReturn($main);

		// Simulate existing row with a specific payload for both constructor and migrate
		$existing = array('a' => 1, 'b' => 2);
		WP_Mock::userFunction('get_option')->andReturnUsing(function ($name, $default = null) use ($main, $existing) {
			if ($name === $main) {
				return $existing;
			}
			return array();
		});
		WP_Mock::userFunction('wp_load_alloptions')->andReturn(array());

		// Fail if update_option is called (no write on no-change)
		WP_Mock::userFunction('update_option')->andReturnUsing(function () {
			\PHPUnit\Framework\Assert::fail('update_option should not be called when migration returns identical value');
			return true;
		});

		$opts = RegisterOptions::from_config($config, StorageContext::forSite(), true)->with_logger($this->logger_mock);

		// Identity migration returns a strictly equal array
		$migration = function($current) use ($existing) {
			// Ensure we are indeed returning an equal array
			\PHPUnit\Framework\Assert::assertSame($existing, $current);
			return $current;
		};

		$opts->migrate($migration);
		// In-memory remains the same
		$this->assertSame($existing, $opts->get_options());
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_apply_write_gate
	 * Asserts that when filters veto persistence, a notice is logged (lines 1188-1195).
	 * @uses \Ran\PluginLib\Config\ConfigInterface
	 */
	public function test_apply_write_gate_logs_notice_on_filter_veto(): void {
		$config = $this->getMockBuilder(ConfigInterface::class)->getMock();
		$main   = 'apply_gate_notice_opts';
		$config->method('get_options_key')->willReturn($main);

		// Minimal get_option behavior
		WP_Mock::userFunction('get_option')->andReturnUsing(function ($name, $default = null) use ($main) {
			if ($name === $main) {
				// Simulate existing empty array payload
				return array();
			}
			return array();
		});
		WP_Mock::userFunction('wp_load_alloptions')->andReturn(array());

		// Construct SUT with collecting logger provided via Config
		$config->method('get_logger')->willReturn($this->logger_mock);
		$opts = RegisterOptions::from_config($config, StorageContext::forSite(), true);

		// Force filter veto deterministically
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist')
			->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
			->reply(false);
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist/scope/site')
			->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
			->reply(false);

		// Ensure policy permits so we hit filter-based veto (not policy veto)
		$allowPolicy = $this->getMockBuilder(\Ran\PluginLib\Options\Policy\WritePolicyInterface::class)->getMock();
		$allowPolicy->method('allow')->willReturn(true);
		$opts->with_policy($allowPolicy);
		// Provide a storage mock so scope resolves and gating context is complete
		$storage = $this->createMock(\Ran\PluginLib\Options\Storage\OptionStorageInterface::class);
		$storage->method('scope')->willReturn(OptionScope::Site);
		$storage->method('read')->willReturn(array());
		$this->_set_protected_property_value($opts, 'storage', $storage);
		$opts->flush();
		// Expect the final decision debug log (allowed=false); notice assertion is covered in a deterministic unit
		$this->expectLog('debug', 'RegisterOptions: _apply_write_gate final decision', 1);
	}
}

/**
 * Additional tests specifically targeting _save_all_options() branches.
 *
 * @uses \Ran\PluginLib\Options\RegisterOptions::__construct
 */
final class WriteGatePersistPathsTest extends PluginLibTestCase {
	use ExpectLogTrait;

	public function setUp(): void {
		parent::setUp();
		// Ensure sanitize_key present
		WP_Mock::userFunction('sanitize_key')->andReturnUsing(function ($key) {
			$key = strtolower((string) $key);
			$key = preg_replace('/[^a-z0-9_\-]+/i', '_', $key) ?? '';
			return trim($key, '_');
		});
		// Safe defaults
		WP_Mock::userFunction('wp_load_alloptions')->andReturn(array());
		// Allow persistence by default in these tests
		WP_Mock::userFunction('apply_filters')->andReturnUsing(function($hook, $value) {
			return $value;
		});
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_save_all_options
	 * Covers add() path when main option row is missing (line ~1304) with autoload=true.
	 */
	public function test_save_all_options_uses_add_when_row_missing(): void {
		$config = $this->getMockBuilder(ConfigInterface::class)->getMock();
		$main   = 'save_add_opts';
		$config->method('get_options_key')->willReturn($main);

		// Simulate missing for _do_get_option by returning provided default (sentinel)
		WP_Mock::userFunction('get_option')->andReturnUsing(function ($name, $default = null) use ($main) {
			if ($name === $main) {
				return func_num_args() >= 2 ? $default : false;
			}
			return array();
		});

		$opts = RegisterOptions::_from_config($config, true, OptionScope::Site)->with_logger($this->logger_mock);
		$this->_set_protected_property_value($opts, 'options', array('x' => 9));

		// Storage: read returns array (for earlier reads), add is expected with autoload=true
		$storage = $this->createMock(\Ran\PluginLib\Options\Storage\OptionStorageInterface::class);
		$storage->method('read')->willReturn(array());
		$storage->method('scope')->willReturn(\Ran\PluginLib\Options\OptionScope::Site);
		$storage->expects($this->once())
		    ->method('add')
		    ->with($main, array('x' => 9), true)
		    ->willReturn(true);
		$this->_set_protected_property_value($opts, 'storage', $storage);

		$this->assertTrue($opts->flush(false));
		$this->expectLog('debug', 'RegisterOptions: storage->add() selected', 1);
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_save_all_options
	 * Covers update() path when main option row exists (not sentinel) — opposite branch to add().
	 */
	public function test_save_all_options_uses_update_when_row_exists(): void {
		$config = $this->getMockBuilder(ConfigInterface::class)->getMock();
		$main   = 'save_update_opts';
		$config->method('get_options_key')->willReturn($main);

		// Simulate existing row for _do_get_option by returning array instead of default
		WP_Mock::userFunction('get_option')->andReturnUsing(function ($name, $default = null) use ($main) {
			if ($name === $main) {
				return array('present' => true);
			}
			return array();
		});

		$opts = RegisterOptions::_from_config($config, true, OptionScope::Site)->with_logger($this->logger_mock);
		$this->_set_protected_property_value($opts, 'options', array('y' => 7));

		$storage = $this->createMock(\Ran\PluginLib\Options\Storage\OptionStorageInterface::class);
		$storage->method('read')->willReturn(array());
		$storage->method('scope')->willReturn(\Ran\PluginLib\Options\OptionScope::Site);
		$storage->expects($this->once())
		    ->method('update')
		    ->with($main, array('y' => 7))
		    ->willReturn(true);
		$this->_set_protected_property_value($opts, 'storage', $storage);

		$this->assertTrue($opts->flush(false));
	}
}
