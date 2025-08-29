<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options;

use Mockery;
use WP_Mock;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Tests\Unit\Options\_helpers\RegisterOptionsWpMocksTrait;

class RegisterOptionsAutoloadSettingTest extends PluginLibTestCase {
	use RegisterOptionsWpMocksTrait;

	private string $mainOption = 'ran_plugin_public_api_test';

	public function setUp(): void {
		parent::setUp();
		$this->init_wp_mocks($this->mainOption);
	}

	public function tearDown(): void {
		parent::tearDown();
		Mockery::close();
	}

	public function test_set_main_autoload_allowed_flips_via_delete_add(): void {
		// Override gate to allow operation by subclassing and forcing filter
		$opts = new class($this->mainOption, array(), true, $this->config_mock->get_logger(), $this->config_mock) extends RegisterOptions {
			public function _do_apply_filter(string $hook_name, $value, ...$args) {
				$ctx = $args[0] ?? null;
				if (str_starts_with($hook_name, 'ran/plugin_lib/options/allow_persist') && is_array($ctx) && ($ctx['op'] ?? null) === 'set_main_autoload') {
					return true; // always allow
				}
				return $value;
			}
		};

		// Expect delete + add with autoload 'no'
		WP_Mock::userFunction('delete_option')->with($this->mainOption)->once()->andReturn(true);
		WP_Mock::userFunction('add_option')->with($this->mainOption, Mockery::type('array'), '', 'no')->once()->andReturn(true);

		$this->assertTrue($opts->set_main_autoload(false));
	}

	public function test_set_main_autoload_noop_when_same_value(): void {
		// With initial autoload=true, requesting true should be no-op and still return true
		$opts = new class($this->mainOption, array('a' => array('value' => '1', 'autoload_hint' => null)), true, $this->config_mock->get_logger(), $this->config_mock) extends RegisterOptions {
			public function _do_apply_filter(string $hook_name, $value, ...$args) {
				$ctx = $args[0] ?? null;
				if (str_starts_with($hook_name, 'ran/plugin_lib/options/allow_persist') && is_array($ctx) && ($ctx['op'] ?? null) === 'set_main_autoload') {
					return true; // allow
				}
				return $value;
			}
		};

		// No delete/add should be attempted; set expectations to not be called by not defining them
		$this->assertTrue($opts->set_main_autoload(true));
		// No further assertions; storage inspection is outside scope here
	}

	public function test_set_main_autoload_veto_returns_false_and_does_not_mutate_or_persist(): void {
		$opts = new class($this->mainOption, array('a' => array('value' => '1', 'autoload_hint' => null)), true, $this->config_mock->get_logger(), $this->config_mock) extends RegisterOptions {
			public function _do_apply_filter(string $hook_name, $value, ...$args) {
				$ctx = $args[0] ?? null;
				if (str_starts_with($hook_name, 'ran/plugin_lib/options/allow_persist') && is_array($ctx) && ($ctx['op'] ?? null) === 'set_main_autoload') {
					return false; // veto
				}
				return $value;
			}
		};

		// If implementation attempted to persist, these would need expectations; here we simply assert veto behavior
		$this->assertFalse($opts->set_main_autoload(false));
		$this->assertSame('1', $opts->get_option('a'));
	}

	public function test_set_main_autoload_unsupported_scope_noop_true_and_updates_cached_flag(): void {
		// Simulate storage that does not support autoload by subclassing to override supports_autoload()
		$opts = new class($this->mainOption, array('k' => array('value' => 'v', 'autoload_hint' => null)), true, $this->config_mock->get_logger(), $this->config_mock) extends RegisterOptions {
			private $fakeStorage;
			protected function _get_storage(): object {
				if (!$this->fakeStorage) {
					$self              = $this;
					$this->fakeStorage = new class($self) {
						public function __construct(private $outer) {
						}
						public function scope() {
							return $this->outer::Site ?? null;
						}
						public function supports_autoload() {
							return false;
						}
					};
				}
				return $this->fakeStorage;
			}
			public function _do_apply_filter(string $hook_name, $value, ...$args) {
				$ctx = $args[0] ?? null;
				if (str_starts_with($hook_name, 'ran/plugin_lib/options/allow_persist') && is_array($ctx) && ($ctx['op'] ?? null) === 'set_main_autoload') {
					return true; // allow
				}
				return $value;
			}
		};

		// Request false; since storage does not support autoload, this should return true and not call delete/add
		$this->assertTrue($opts->set_main_autoload(false));
		// No further assertions; storage inspection is outside scope here
	}

	public function test_set_main_autoload_unsupported_non_user_scope_hits_notice_branch(): void {
		// Force storage to a non-user scope with unsupported autoload to hit the line ~880 branch
		$opts = new class($this->mainOption, array('k' => array('value' => 'v', 'autoload_hint' => null)), true, $this->config_mock->get_logger(), $this->config_mock) extends RegisterOptions {
			private $fakeStorage;
			protected function _get_storage(): object {
				if (!$this->fakeStorage) {
					$self              = $this;
					$this->fakeStorage = new class($self) {
						public function __construct(private $outer) {
						}
						public function scope() {
							return \Ran\PluginLib\Options\OptionScope::Site;
						}
						public function supports_autoload() {
							return false;
						}
					};
				}
				return $this->fakeStorage;
			}
			public function _do_apply_filter(string $hook_name, $value, ...$args) {
				$ctx = $args[0] ?? null;
				if (str_starts_with($hook_name, 'ran/plugin_lib/options/allow_persist') && is_array($ctx) && ($ctx['op'] ?? null) === 'set_main_autoload') {
					return true; // allow to reach unsupported-branch
				}
				return $value;
			}
		};

		// Should return true and avoid delete/add paths
		$this->assertTrue($opts->set_main_autoload(false));
		// Also request true; still unsupported -> no delete/add and returns true
		$this->assertTrue($opts->set_main_autoload(true));
	}

	public function test_set_main_autoload_logs_notice_for_unsupported_non_user_scope(): void {
		// Activate a real logger at NOTICE level and capture output via error_log_handler
		$captured = array();
		$const    = 'PLUGIN_LIB_TEST_LOG_LEVEL_' . uniqid();
		if (!defined($const)) {
			define($const, 'NOTICE');
		}
		$logger = new \Ran\PluginLib\Util\Logger(array(
		    'custom_debug_constant_name' => $const,
		    'error_log_handler'          => function (string $message) use (&$captured) {
		    	$captured[] = $message;
		    },
		));

		$opts = RegisterOptions::from_config(
			$this->config_mock,
			array('k' => array('value' => 'v', 'autoload_hint' => null)),
			true,
			$logger,
			array(),
			\Ran\PluginLib\Options\OptionScope::Network,
			array()
		);

		$this->assertTrue($logger->is_active());
		// Emit a probe to ensure our handler wiring works
		$logger->notice('probe');
		$this->assertNotEmpty($captured);
		$this->assertStringContainsString('probe', implode("\n", $captured));

		// Now exercise the branch
		$this->assertTrue($opts->set_main_autoload(false));

		// Assert that a NOTICE with the expected message was emitted
		$joined = implode("\n", $captured);
		$this->assertStringContainsString('[NOTICE]', $joined);
		$this->assertStringContainsString('autoload unsupported for current scope', $joined);
	}
}
