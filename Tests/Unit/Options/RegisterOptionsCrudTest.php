<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options;

use Mockery;
use WP_Mock;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Tests\Unit\Options\_helpers\RegisterOptionsWpMocksTrait;

class RegisterOptionsCrudTest extends PluginLibTestCase {
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

	public function test_set_and_add_and_delete_and_clear_and_flush(): void {
		$opts = new RegisterOptions($this->mainOption, array(), true, $this->config_mock->get_logger(), $this->config_mock);

		// set_option()
		$opts->set_option('alpha', 'A');
		$this->assertSame('A', $opts->get_option('alpha'));

		// add_option()
		$opts->add_option('beta', 'B', true);
		$this->assertSame('B', $opts->get_option('beta'));

		// add_options()
		$opts->add_options(array('gamma' => 'G', 'delta' => array('value' => 'D', 'autoload_hint' => null)));
		$this->assertSame('G', $opts->get_option('gamma'));
		$this->assertSame('D', $opts->get_option('delta'));

		// delete_option()
		$opts->delete_option('beta');
		$this->assertNull($opts->get_option('beta', null));

		// Expect one update on flush(false)
		WP_Mock::userFunction('update_option')
		    ->with($this->mainOption, Mockery::on(function ($arr) {
		    	return is_array($arr) && isset($arr['alpha'], $arr['gamma'], $arr['delta']) && !isset($arr['beta']);
		    }), 'yes')
		    ->once()
		    ->andReturn(true);
		$this->assertTrue($opts->flush(false));

		// clear()
		$opts->clear();
		WP_Mock::userFunction('update_option')
		    ->with($this->mainOption, array(), 'yes')
		    ->once()
		    ->andReturn(true);
		$this->assertTrue($opts->flush(false));
	}

	public function test_set_option_persists_immediately_and_noop_on_same_value(): void {
		$opts = new RegisterOptions($this->mainOption, array(), true, $this->config_mock->get_logger(), $this->config_mock);
		// First set should trigger a save immediately
		WP_Mock::userFunction('update_option')
		    ->with($this->mainOption, Mockery::on(function ($arr) {
		    	return is_array($arr) && isset($arr['foo']) && $arr['foo']['value'] === 'bar';
		    }), 'yes')
		    ->once()
		    ->andReturn(true);
		$this->assertTrue($opts->set_option('foo', 'bar'));
		$this->assertSame('bar', $opts->get_option('foo'));

		// No-op: setting same value and implied same hint should not cause another write
		$this->assertTrue($opts->set_option('foo', 'bar'));
	}

	public function test_add_options_veto_does_not_mutate(): void {
		// Subclass to veto add_options via filter
		$opts = new class($this->mainOption, array(), true, $this->config_mock->get_logger(), $this->config_mock) extends RegisterOptions {
			public function _do_apply_filter(string $hook_name, $value, ...$args) {
				$ctx = $args[0] ?? null;
				if (str_starts_with($hook_name, 'ran/plugin_lib/options/allow_persist') && is_array($ctx) && ($ctx['op'] ?? '') === 'add_options') {
					return false; // veto
				}
				return $value;
			}
		};
		$opts->add_options(array('k' => 'v'));
		$this->assertNull($opts->get_option('k', null));
	}

	public function test_add_option_veto_and_noop(): void {
		// Veto path
		$opts = new class($this->mainOption, array(), true, $this->config_mock->get_logger(), $this->config_mock) extends RegisterOptions {
			public function _do_apply_filter(string $hook_name, $value, ...$args) {
				$ctx = $args[0] ?? null;
				if (str_starts_with($hook_name, 'ran/plugin_lib/options/allow_persist') && is_array($ctx) && ($ctx['op'] ?? '') === 'add_option') {
					return false; // veto
				}
				return $value;
			}
		};
		$opts->add_option('x', '1');
		$this->assertNull($opts->get_option('x', null));

		// No-op path: same value/hint
		$opts2 = new RegisterOptions($this->mainOption, array('y' => array('value' => '2', 'autoload_hint' => true)), true, $this->config_mock->get_logger(), $this->config_mock);
		$opts2->add_option('y', '2', true); // should return self without change
		$this->assertSame('2', $opts2->get_option('y'));
	}

	public function test_delete_option_persists_immediately(): void {
		$opts = new RegisterOptions($this->mainOption, array('a' => 'A', 'b' => 'B'), true, $this->config_mock->get_logger(), $this->config_mock);
		// Expect update on delete
		WP_Mock::userFunction('update_option')
		    ->with($this->mainOption, Mockery::on(function ($arr) {
		    	return is_array($arr) && isset($arr['a']) && !isset($arr['b']);
		    }), 'yes')
		    ->once()
		    ->andReturn(true);
		$this->assertTrue($opts->delete_option('b'));
		$this->assertNull($opts->get_option('b', null));
	}

	public function test_clear_persists_immediately(): void {
		$opts = new RegisterOptions($this->mainOption, array('a' => 'A'), true, $this->config_mock->get_logger(), $this->config_mock);
		WP_Mock::userFunction('update_option')
		    ->with($this->mainOption, array(), 'yes')
		    ->once()
		    ->andReturn(true);
		$this->assertTrue($opts->clear());
		$this->assertNull($opts->get_option('a', null));
	}

	public function test_set_option_veto_does_not_mutate_and_returns_false(): void {
		$opts = new class($this->mainOption, array(), true, $this->config_mock->get_logger(), $this->config_mock) extends RegisterOptions {
			public function _do_apply_filter(string $hook_name, $value, ...$args) {
				$ctx = $args[0] ?? null;
				if (str_starts_with($hook_name, 'ran/plugin_lib/options/allow_persist') && is_array($ctx) && ($ctx['op'] ?? '') === 'set_option') {
					return false;
				}
				return $value;
			}
		};
		$this->assertFalse($opts->set_option('nope', 'x'));
		$this->assertNull($opts->get_option('nope', null));
	}

	public function test_delete_option_veto_returns_false_and_keeps_value(): void {
		$opts = new class($this->mainOption, array('k' => 'v'), true, $this->config_mock->get_logger(), $this->config_mock) extends RegisterOptions {
			public function _do_apply_filter(string $hook_name, $value, ...$args) {
				$ctx = $args[0] ?? null;
				if (str_starts_with($hook_name, 'ran/plugin_lib/options/allow_persist') && is_array($ctx) && ($ctx['op'] ?? '') === 'delete_option') {
					return false;
				}
				return $value;
			}
		};
		$this->assertFalse($opts->delete_option('k'));
		$this->assertSame('v', $opts->get_option('k'));
	}

	public function test_clear_veto_returns_false_and_retains_state(): void {
		$opts = new class($this->mainOption, array('k' => 'v'), true, $this->config_mock->get_logger(), $this->config_mock) extends RegisterOptions {
			public function _do_apply_filter(string $hook_name, $value, ...$args) {
				$ctx = $args[0] ?? null;
				if (str_starts_with($hook_name, 'ran/plugin_lib/options/allow_persist') && is_array($ctx) && ($ctx['op'] ?? '') === 'clear') {
					return false;
				}
				return $value;
			}
		};
		$this->assertFalse($opts->clear());
		$this->assertSame('v', $opts->get_option('k'));
	}

	public function test_seed_if_missing_creates_when_absent(): void {
		$opts = new RegisterOptions($this->mainOption, array(), true, $this->config_mock->get_logger(), $this->config_mock);
		// After seeding, defaults should be present in memory; add_option is stubbed to true
		$opts->seed_if_missing(array('first' => 'one', 'second' => 'two'));
		$this->assertSame('one', $opts->get_option('first'));
		$this->assertSame('two', $opts->get_option('second'));
	}

	public function test_seed_if_missing_veto_noop(): void {
		$opts = new class($this->mainOption, array(), true, $this->config_mock->get_logger(), $this->config_mock) extends RegisterOptions {
			public function _do_apply_filter(string $hook_name, $value, ...$args) {
				$ctx = $args[0] ?? null;
				if (str_starts_with($hook_name, 'ran/plugin_lib/options/allow_persist') && is_array($ctx) && ($ctx['op'] ?? '') === 'seed_if_missing') {
					return false; // veto
				}
				return $value;
			}
		};
		$opts->seed_if_missing(array('a' => 'A'));
		$this->assertNull($opts->get_option('a', null));
	}
}
