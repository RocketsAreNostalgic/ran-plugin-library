<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options;

use Mockery;
use WP_Mock;
use Ran\PluginLib\Options\OptionScope;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Tests\Unit\TestClasses\TestableConfig;

/**
 * Focused tests for write-gating and persistence behavior.
 *
 * @covers \Ran\PluginLib\Options\RegisterOptions
 */
/**
 * Test helper subclass that vetoes persistence when op === 'set_option' in user scope.
 */
class GateVetoingUserScopeOptions extends RegisterOptions {
	public function _do_apply_filter(string $hook_name, $value, ...$args) {
		$ctx = $args[0] ?? null;
		if (is_array($ctx) && ($ctx['op'] ?? null) === 'set_option') {
			if ($hook_name === 'ran/plugin_lib/options/allow_persist' || $hook_name === 'ran/plugin_lib/options/allow_persist/scope/user') {
				return false; // veto
			}
		}
		return $value;
	}
}

final class RegisterOptionsWriteGateTest extends PluginLibTestCase {
	private string $mainOption = 'ran_plugin_options_gate_test';

	public function setUp(): void {
		parent::setUp();
		// Ensure sanitize_key exists consistently
		WP_Mock::userFunction('sanitize_key')
		    ->andReturnUsing(function ($v) {
		    	$s = strtolower(preg_replace('/[^a-z0-9_\-]/i', '', (string) $v));
		    	return trim($s, '_');
		    })
		    ->byDefault();
		// Default constructor read returns empty array unless overridden
		WP_Mock::userFunction('get_option')->with($this->mainOption, array())->andReturn(array())->byDefault();
		// Default wp_load_alloptions returns array when consulted
		WP_Mock::userFunction('wp_load_alloptions')->andReturn(array())->byDefault();

		// Default apply_filters passthrough unless specifically asserted in a test
		// Accept variadic args to match WP signature apply_filters($hook, $value, ...$args)
		WP_Mock::userFunction('apply_filters')
		    ->andReturnUsing(function ($hook, $value, ...$args) {
		    	if (\defined('STDERR')) {
		    		\fwrite(STDERR, '[TEST TRACE default] apply_filters hook=' . $hook . "\n");
		    	}
		    	return $value;
		    })
		    ->byDefault();

		// Default stubs for user meta functions (used when user scope selected)
		WP_Mock::userFunction('get_user_meta')->andReturn(array())->byDefault();
		WP_Mock::userFunction('update_user_meta')->andReturn(true)->byDefault();
		WP_Mock::userFunction('add_user_meta')->andReturn(true)->byDefault();
		WP_Mock::userFunction('delete_user_meta')->andReturn(true)->byDefault();

		// Default update_option returns strict bool to prevent TypeError if invoked unexpectedly
		WP_Mock::userFunction('update_option')
		    ->with($this->mainOption, Mockery::type('array'), Mockery::type('string'))
		    ->andReturn(true)
		    ->byDefault();
	}

	public function tearDown(): void {
		parent::tearDown();
		Mockery::close();
	}

	/**
	 * When the global allow_persist filter vetoes, flush() should return false and not call update_option.
	 */
	public function test_flush_vetoed_by_global_filter(): void {
		// Use anonymous subclass to override _do_apply_filter and veto globally
		$opts = new class($this->mainOption) extends \Ran\PluginLib\Options\RegisterOptions {
			public function __construct($name) {
				parent::__construct($name);
			}
			public function _do_apply_filter(string $hook_name, $value, ...$args) {
				$ctx = $args[0] ?? null;
				if (is_array($ctx) && ($ctx['op'] ?? null) === 'save_all') {
					if ($hook_name === 'ran/plugin_lib/options/allow_persist' || $hook_name === 'ran/plugin_lib/options/allow_persist/scope/site') {
						return false;
					}
				}
				return $value;
			}
		};
		$opts->add_options(array('a' => 'x'));

		$this->assertFalse($opts->flush(false));
	}

	/**
	 * When the scope-specific allow_persist filter vetoes, flush() should return false.
	 */
	public function test_flush_vetoed_by_scope_filter(): void {
		// Use anonymous subclass to allow global and veto on scope
		$opts = new class($this->mainOption) extends \Ran\PluginLib\Options\RegisterOptions {
			public function __construct($name) {
				parent::__construct($name);
			}
			public function _do_apply_filter(string $hook_name, $value, ...$args) {
				$ctx = $args[0] ?? null;
				if (is_array($ctx) && ($ctx['op'] ?? null) === 'save_all') {
					if ($hook_name === 'ran/plugin_lib/options/allow_persist') {
						return true;
					}
					if ($hook_name === 'ran/plugin_lib/options/allow_persist/scope/site') {
						return false;
					}
				}
				return $value;
			}
		};
		$opts->add_options(array('b' => 'y'));

		$this->assertFalse($opts->flush(false));
	}

	/**
	 * When filters allow, flush() persists with expected structure and autoload flag.
	 */
	public function test_flush_allowed_persists_once(): void {
		$opts = new RegisterOptions($this->mainOption, array(), /* autoload */ true);
		$opts->add_options(array('k' => 'v'));

		// Allow through both filters via default passthrough (no strict expectations)

		WP_Mock::userFunction('update_option')
		    ->with($this->mainOption, Mockery::on(function ($val) {
		    	return is_array($val)
		    	    && isset($val['k']['value']) && $val['k']['value'] === 'v'
		    	    && array_key_exists('autoload_hint', $val['k']);
		    }), 'yes')
		    ->once()
		    ->andReturn(true);

		// Site scope path may check autoloaded via wp_load_alloptions; ensure it returns an array
		WP_Mock::userFunction('wp_load_alloptions')->andReturn(array())->byDefault();

		$this->assertTrue($opts->flush(false));
	}

	/**
	 * set_main_autoload should be gated; on veto return false and do not delete/add.
	 */
	public function test_set_main_autoload_vetoed_returns_false_and_no_wp_writes(): void {
		// Use anonymous subclass to veto when op === 'set_main_autoload'
		$opts = new class($this->mainOption) extends \Ran\PluginLib\Options\RegisterOptions {
			public function __construct($name) {
				parent::__construct($name);
			}
			public function _do_apply_filter(string $hook_name, $value, ...$args) {
				$ctx = $args[0] ?? null;
				if (is_array($ctx) && ($ctx['op'] ?? null) === 'set_main_autoload') {
					if ($hook_name === 'ran/plugin_lib/options/allow_persist' || $hook_name === 'ran/plugin_lib/options/allow_persist/scope/site') {
						return false; // veto
					}
				}
				return $value;
			}
		};

		// set_main_autoload consults current state via sentinels
		WP_Mock::userFunction('get_option')
		    ->with($this->mainOption, Mockery::type('object'))
		    ->andReturnUsing(function ($name, $default) {
		    	return $default;
		    })
		    ->byDefault();
		WP_Mock::userFunction('wp_load_alloptions')->andReturn(array())->byDefault();

		// Ensure no delete_option/add_option happens
		WP_Mock::userFunction('delete_option')->never();
		WP_Mock::userFunction('add_option')->never();

		$this->assertFalse($opts->set_main_autoload(false));
	}

	/**
	 * set_main_autoload should no-op successfully when autoload unsupported (user scope), without delete/add.
	 */
	public function test_set_main_autoload_user_scope_unsupported_noops_without_writes(): void {
		// Prepare a Config for factory usage
		WP_Mock::userFunction('sanitize_title')->andReturn('test-plugin');
		$cfg  = new TestableConfig();
		$main = $cfg->get_config()['RAN']['AppOption'];

		// Constructor read for from_config
		WP_Mock::userFunction('get_option')->with($main, array())->once()->andReturn(array());

		// set_main_autoload will call get_main_autoload() which reads existence via sentinel
		WP_Mock::userFunction('get_option')
		    ->with($main, Mockery::type('object'))
		    ->once()
		    ->andReturnUsing(function ($name, $default) {
		    	return $default;
		    });
		// No wp_load_alloptions in user scope path is required for our assertion, but stub safely
		WP_Mock::userFunction('wp_load_alloptions')->andReturn(array())->byDefault();

		$opts = RegisterOptions::from_config(
			$cfg,
			/* initial */ array(),
			/* autoload */ true,
			/* logger */ null,
			/* schema */ array(),
			/* scope */ 'user',
			/* storage args */ array('user_id' => 123)
		);

		// Using user scope means supports_autoload() is false; ensure no delete/add occur
		WP_Mock::userFunction('delete_option')->never();
		WP_Mock::userFunction('add_option')->never();

		// Should return true (no-op) and not error
		$this->assertTrue($opts->set_main_autoload(true));
	}

	/**
	 * set_option should be gated; on veto in user scope it returns false, does not mutate memory, and performs no user writes.
	 */
	public function test_set_option_vetoed_in_user_scope_does_not_mutate_or_write(): void {
		// Prepare a Config for factory usage (user scope)
		WP_Mock::userFunction('sanitize_title')->andReturn('test-plugin');
		$cfg  = new TestableConfig();
		$main = $cfg->get_config()['RAN']['AppOption'];

		// Constructor read for from_config returns empty array
		WP_Mock::userFunction('get_option')->with($main, array())->once()->andReturn(array());

		// Ensure no user writes occur if vetoed
		WP_Mock::userFunction('update_user_meta')->never();
		WP_Mock::userFunction('add_user_meta')->never();

		// Create user-scoped options instance via factory (late static binding returns subclass)
		$opts = GateVetoingUserScopeOptions::from_config(
			$cfg,
			/* initial */ array(),
			/* autoload */ true,
			/* logger */ null,
			/* schema */ array(),
			/* scope */ 'user',
			/* storage args */ array('user_id' => 123)
		);

		// Attempt to set an option; should be vetoed
		$this->assertFalse($opts->set_option('foo', 'bar'));

		// In-memory state should not include the key
		$this->assertArrayNotHasKey('foo', $opts->get_options());
	}

	/**
	 * set_option with enum scope (OptionScope::User) should follow the same gate path.
	 * This also covers the early scope string resolution branch using an enum value.
	 */
	public function test_set_option_vetoed_with_enum_scope_user(): void {
		// Prepare a Config for factory usage (user scope via enum)
		WP_Mock::userFunction('sanitize_title')->andReturn('test-plugin');
		$cfg  = new TestableConfig();
		$main = $cfg->get_config()['RAN']['AppOption'];

		// Constructor read for from_config returns empty array
		WP_Mock::userFunction('get_option')->with($main, array())->once()->andReturn(array());

		// Ensure no user writes occur if vetoed
		WP_Mock::userFunction('update_user_meta')->never();
		WP_Mock::userFunction('add_user_meta')->never();

		// Create user-scoped options instance via factory using enum scope
		$opts = GateVetoingUserScopeOptions::from_config(
			$cfg,
			/* initial */ array(),
			/* autoload */ true,
			/* logger */ null,
			/* schema */ array(),
			/* scope */ OptionScope::User,
			/* storage args */ array('user_id' => 456)
		);

		// Attempt to set an option; should be vetoed
		$this->assertFalse($opts->set_option('alpha', 'beta'));

		// In-memory state should not include the key
		$this->assertArrayNotHasKey('alpha', $opts->get_options());
	}

	/**
	 * Pre-mutation veto path (covers line ~401): allow early gate, veto first full-context gate.
	 */
	public function test_set_option_premutation_gate_veto_returns_false_no_mutation(): void {
		// Constructor read for direct-constructor instance (uses $this->mainOption)
		WP_Mock::userFunction('get_option')->with($this->mainOption, array())->once()->andReturn(array());

		// Subclass that vetoes on the first full-context set_option gate only
		$opts = new class(null) extends GateVetoingUserScopeOptions {
			private bool $vetoed = false;
			public function __construct($unused) {
				parent::__construct('ran_plugin_options_gate_test', array(), true, null, null);
			}
			public function _do_apply_filter(string $hook_name, $value, ...$args) {
				$ctx = $args[0] ?? null;
				// Allow early gate (ctx without autoload_hint)
				if (is_array($ctx) && ($ctx['op'] ?? null) === 'set_option' && !array_key_exists('autoload_hint', $ctx)) {
					return true;
				}
				// Veto first full-context set_option (pre-mutation)
				if (is_array($ctx) && ($ctx['op'] ?? null) === 'set_option' && array_key_exists('autoload_hint', $ctx) && !$this->vetoed) {
					$this->vetoed = true;
					return false;
				}
				return $value;
			}
		};

		// Ensure no user writes on veto
		WP_Mock::userFunction('update_user_meta')->never();
		WP_Mock::userFunction('add_user_meta')->never();

		$this->assertFalse($opts->set_option('pm', 'x'));
		$this->assertArrayNotHasKey('pm', $opts->get_options());
	}

	/**
	 * Pre-persist veto after staging (covers lines ~413–414): allow early + pre-mutation, veto second full-context gate.
	 */
	public function test_set_option_prepersist_gate_veto_rolls_back(): void {
		// Constructor read for direct-constructor instance (uses $this->mainOption)
		WP_Mock::userFunction('get_option')->with($this->mainOption, array())->once()->andReturn(array());

		// Subclass that vetoes on the second full-context gate
		$opts = new class(null) extends GateVetoingUserScopeOptions {
			private int $gateCalls = 0; // count only base-hook invocations per gate
			public function __construct($unused) {
				parent::__construct('ran_plugin_options_gate_test', array(), true, null, null);
			}
			public function _do_apply_filter(string $hook_name, $value, ...$args) {
				$ctx = $args[0] ?? null;
				if (is_array($ctx) && ($ctx['op'] ?? null) === 'set_option' && !array_key_exists('autoload_hint', $ctx)) {
					// early gate allowed
					return true;
				}
				// For full-context gates, count only the base hook (not the scope hook)
				if (
					is_array($ctx)
					&& ($ctx['op'] ?? null) === 'set_option'
					&& array_key_exists('autoload_hint', $ctx)
					&& $hook_name === 'ran/plugin_lib/options/allow_persist'
				) {
					$this->gateCalls++;
					if ($this->gateCalls === 2) {
						return false; // veto on the second gate (pre-persist)
					}
					return true; // allow the first gate (pre-mutation)
				}
				// Pass-through for scope-specific hook calls
				return $value;
			}
		};

		WP_Mock::userFunction('update_user_meta')->never();
		WP_Mock::userFunction('add_user_meta')->never();

		$this->assertFalse($opts->set_option('pp', 'y'));
		$this->assertArrayNotHasKey('pp', $opts->get_options());
	}

	/**
	 * Save-time veto inside _save_all_options (covers line ~421 rollback on failed persistence).
	 */
	public function test_set_option_persist_veto_rolls_back(): void {
		// Constructor read for direct-constructor instance (uses $this->mainOption)
		WP_Mock::userFunction('get_option')->with($this->mainOption, array())->once()->andReturn(array());

		// Subclass that allows both set_option gates but vetoes during save_all
		$opts = new class(null) extends GateVetoingUserScopeOptions {
			public function __construct($unused) {
				parent::__construct('ran_plugin_options_gate_test', array(), true, null, null);
			}
			public function _do_apply_filter(string $hook_name, $value, ...$args) {
				$ctx = $args[0] ?? null;
				// Allow all set_option gates (early + full-context base/scope)
				if (is_array($ctx) && ($ctx['op'] ?? null) === 'set_option' && !array_key_exists('options', $ctx)) {
					return true;
				}
				// Veto during _save_all_options: op will be 'set_option' and ctx will include 'options'
				if (is_array($ctx) && ($ctx['op'] ?? null) === 'set_option' && array_key_exists('options', $ctx)) {
					return false;
				}
				return $value;
			}
		};

		WP_Mock::userFunction('update_user_meta')->never();
		WP_Mock::userFunction('add_user_meta')->never();

		$this->assertFalse($opts->set_option('sv', 'z'));
		$this->assertArrayNotHasKey('sv', $opts->get_options());
	}
}
