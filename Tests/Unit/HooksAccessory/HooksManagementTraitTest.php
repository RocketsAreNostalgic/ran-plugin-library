<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\HooksAccessory;

use Mockery;
use Ran\PluginLib\HooksAccessory\HooksManager;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;

/**
 * @covers \Ran\PluginLib\HooksAccessory\HooksManagementTrait::_get_hooks_manager
 * @covers \Ran\PluginLib\HooksAccessory\HooksManagementTrait::_init_hooks
 * @covers \Ran\PluginLib\HooksAccessory\HooksManagementTrait::_register_action
 * @covers \Ran\PluginLib\HooksAccessory\HooksManagementTrait::_register_filter
 * @covers \Ran\PluginLib\HooksAccessory\HooksManagementTrait::_register_action_method
 * @covers \Ran\PluginLib\HooksAccessory\HooksManagementTrait::_register_filter_method
 * @covers \Ran\PluginLib\HooksAccessory\HooksManagementTrait::_register_conditional_action
 * @covers \Ran\PluginLib\HooksAccessory\HooksManagementTrait::_register_conditional_filter
 * @covers \Ran\PluginLib\HooksAccessory\HooksManagementTrait::_register_hooks_bulk
 * @covers \Ran\PluginLib\HooksAccessory\HooksManagementTrait::_register_hook_group
 * @covers \Ran\PluginLib\HooksAccessory\HooksManagementTrait::_register_universal_action
 * @covers \Ran\PluginLib\HooksAccessory\HooksManagementTrait::_register_admin_action
 * @covers \Ran\PluginLib\HooksAccessory\HooksManagementTrait::_register_frontend_action
 * @covers \Ran\PluginLib\HooksAccessory\HooksManagementTrait::_register_asset_hooks
 * @covers \Ran\PluginLib\HooksAccessory\HooksManagementTrait::_register_deferred_hooks
 * @covers \Ran\PluginLib\HooksAccessory\HooksManagementTrait::_is_hook_registered
 * @covers \Ran\PluginLib\HooksAccessory\HooksManagementTrait::_get_registered_hooks
 * @covers \Ran\PluginLib\HooksAccessory\HooksManagementTrait::_get_hook_stats
 * @covers \Ran\PluginLib\HooksAccessory\HooksManagementTrait::_get_hooks_by_group
 * @covers \Ran\PluginLib\HooksAccessory\HooksManagementTrait::_get_hooks_debug_report
 * @covers \Ran\PluginLib\HooksAccessory\HooksManagementTrait::_clear_hooks
 * @covers \Ran\PluginLib\HooksAccessory\HooksManagementTrait::get_hook_stats
 * @covers \Ran\PluginLib\HooksAccessory\HooksManagementTrait::get_registered_hooks
 */
final class HooksManagementTraitTest extends PluginLibTestCase {
	public function test_register_action_and_public_getters_delegate_to_manager(): void {
		// Anonymous class using the trait with an exposing method
		$obj = new class() {
			use \Ran\PluginLib\HooksAccessory\HooksManagementTrait;
			public function foo(): void {
			}
			public function exposeRegister(string $hook, callable $cb, int $p = 10, int $a = 1, array $ctx = array()): bool {
				return $this->_register_action($hook, $cb, $p, $a, $ctx);
			}
		};

		$manager = Mockery::mock(HooksManager::class);
		$manager->shouldReceive('register_action')
		    ->with('wp_init', Mockery::type('callable'), 10, 1, array('context' => 'x'))
		    ->once()
		    ->andReturn(true);
		$manager->shouldReceive('get_stats')->andReturn(array('totals' => 1));
		$manager->shouldReceive('get_registered_hooks')->andReturn(array('action|wp_init|10|callable' => true));

		// Inject mock manager into the trait holder
		$ref  = new \ReflectionObject($obj);
		$prop = $ref->getProperty('hooks_manager');
		$prop->setAccessible(true);
		$prop->setValue($obj, $manager);

		$this->assertTrue($obj->exposeRegister('wp_init', array($obj, 'foo'), 10, 1, array('context' => 'x')));
		$this->assertSame(array('totals' => 1), $obj->get_hook_stats());
		$this->assertIsArray($obj->get_registered_hooks());
		// call various helpers
		$obj2 = new class() {
			use \Ran\PluginLib\HooksAccessory\HooksManagementTrait;
			public function bar(): void {
			}
			public function expose(): void {
				$this->_register_action_method('wp_init', 'bar', 5, 1, array('k' => 'v'));
				$this->_register_filter('the_content', array($this, 'bar'), 12, 1, array('c' => 'f'));
				$this->_register_conditional_action('admin_init', array($this, 'bar'), 'is_admin', 10, 0, array());
				$this->_register_conditional_filter('the_title', array($this, 'bar'), fn() => true, 10, 1, array());
				$this->_register_hooks_bulk(array(
				    array('type' => 'action', 'hook' => 'wp_footer', 'callback' => array($this, 'bar'), 'priority' => 99, 'accepted_args' => 0, 'context' => array()),
				));
				$this->_register_hook_group('g', array());
				$this->_register_universal_action('wp_enqueue_scripts', array($this, 'bar'));
				$this->_register_universal_action('init', array($this, 'bar'));
				$this->_register_filter_method('the_title', 'bar', 11, 1, array('x' => 'y'));
				$this->_register_admin_action('admin_init', array($this, 'bar'));
				$this->_register_frontend_action('wp_head', array($this, 'bar'));
				$this->_is_hook_registered('action','wp_init',10);
				$this->_get_hooks_by_group('g');
				$this->_get_hooks_debug_report();
			}
		};

		$m2 = Mockery::mock(HooksManager::class);
		$m2->shouldReceive('register_method_hook')->with('action','wp_init','bar',5,1, array('k' => 'v'))->once()->andReturn(true);
		$m2->shouldReceive('register_filter')->with('the_content', Mockery::type('callable'),12,1, array('c' => 'f'))->once()->andReturn(true);
		$m2->shouldReceive('register_conditional_hooks')->with(Mockery::type('array'))->andReturn(array(array('success' => true)));
		$m2->shouldReceive('register_hook_group')->with('g', Mockery::type('array'))->once()->andReturn(true);
		$m2->shouldReceive('register_method_hook')->with('filter','the_title','bar',11,1, array('x' => 'y'))->once()->andReturn(true);
		$m2->shouldReceive('register_action')->andReturn(true);
		$m2->shouldReceive('is_hook_registered')->with('action','wp_init',10)->andReturn(true);
		$m2->shouldReceive('get_hooks_by_group')->with('g')->andReturn(array());
		$m2->shouldReceive('generate_debug_report')->andReturn(array());

		$ref2 = new \ReflectionObject($obj2);
		$p2   = $ref2->getProperty('hooks_manager');
		$p2->setAccessible(true);
		$p2->setValue($obj2, $m2);
		$obj2->expose();
	}

	public function test_init_asset_deferred_and_clear_and_protected_getters(): void {
		$obj = new class() {
			use \Ran\PluginLib\HooksAccessory\HooksManagementTrait;
			public bool $hit = false;
			public function register_hooks(): void {
				$this->hit = true;
			}
			public function enqueue_scripts(): void {
			}
			public function exInit(): void {
				$this->_init_hooks();
			}
			public function exAsset(): bool {
				return $this->_register_asset_hooks('script');
			}
			public function exDeferred(): bool {
				return $this->_register_deferred_hooks(array(
				    'wp_footer' => array(
				        'priority' => 99,
				        'callback' => array($this, 'enqueue_scripts'),
				        'context'  => array()
				    ),
				));
			}
			public function exProtectedGetters(): array {
				return array($this->_get_registered_hooks(), $this->_get_hook_stats());
			}
			public function exClear(): void {
				$this->_clear_hooks();
			}
		};

		$m = Mockery::mock(HooksManager::class);
		$m->shouldReceive('init_declarative_hooks')->once();
		// asset hooks register two methods by default: wp_enqueue_scripts and admin_enqueue_scripts
		$m->shouldReceive('register_method_hook')->with('action', 'wp_enqueue_scripts', 'enqueue_scripts', 10, 1, Mockery::type('array'))->once()->andReturn(true);
		$m->shouldReceive('register_method_hook')->with('action', 'admin_enqueue_scripts', 'enqueue_scripts', 10, 1, Mockery::type('array'))->once()->andReturn(true);
		$m->shouldReceive('register_hook_group')->with('deferred_processing', Mockery::type('array'))->once()->andReturn(true);
		$m->shouldReceive('get_registered_hooks')->andReturn(array());
		$m->shouldReceive('get_stats')->andReturn(array('totals' => 0));
		$m->shouldReceive('clear_hooks')->once();

		$ref  = new \ReflectionObject($obj);
		$prop = $ref->getProperty('hooks_manager');
		$prop->setAccessible(true);
		$prop->setValue($obj, $m);

		$obj->exInit();
		$this->assertTrue($obj->hit);
		$this->assertTrue($obj->exAsset());
		$this->assertTrue($obj->exDeferred());
		$this->assertIsArray($obj->exProtectedGetters());
		$obj->exClear();
	}

	public function test_register_asset_hooks_missing_method_returns_false(): void {
		$obj = new class() {
			use \Ran\PluginLib\HooksAccessory\HooksManagementTrait;
			// Note: no enqueue_styles method defined
			public function exAsset(string $type): bool {
				return $this->_register_asset_hooks($type);
			}
		};
		$m = Mockery::mock(HooksManager::class);
		// No register_method_hook expected because method doesn't exist
		$ref  = new \ReflectionObject($obj);
		$prop = $ref->getProperty('hooks_manager');
		$prop->setAccessible(true);
		$prop->setValue($obj, $m);
		$this->assertFalse($obj->exAsset('style'));
	}

	public function test_register_asset_hooks_partial_failure_returns_false(): void {
		$obj = new class() {
			use \Ran\PluginLib\HooksAccessory\HooksManagementTrait;
			public function enqueue_scripts(): void {
			}
			public function exAsset(): bool {
				return $this->_register_asset_hooks('script');
			}
		};
		$m = Mockery::mock(HooksManager::class);
		$m->shouldReceive('register_method_hook')->with('action', 'wp_enqueue_scripts', 'enqueue_scripts', 10, 1, Mockery::type('array'))->once()->andReturn(true);
		$m->shouldReceive('register_method_hook')->with('action', 'admin_enqueue_scripts', 'enqueue_scripts', 10, 1, Mockery::type('array'))->once()->andReturn(false);
		$ref  = new \ReflectionObject($obj);
		$prop = $ref->getProperty('hooks_manager');
		$prop->setAccessible(true);
		$prop->setValue($obj, $m);
		$this->assertFalse($obj->exAsset());
	}

	public function test_register_deferred_hooks_skips_null_and_handles_empty(): void {
		$obj = new class() {
			use \Ran\PluginLib\HooksAccessory\HooksManagementTrait;
			public function enqueue_scripts(): void {
			}
			public function exDeferred(array $cfg): bool {
				return $this->_register_deferred_hooks($cfg);
			}
		};
		// Case 1: one valid + one null
		$m1 = Mockery::mock(HooksManager::class);
		$m1->shouldReceive('register_hook_group')->with('deferred_processing', Mockery::on(function($defs) {
			return is_array($defs) && count($defs) === 1 && $defs[0]['hook'] === 'wp_footer';
		}))->once()->andReturn(true);
		$ref1  = new \ReflectionObject($obj);
		$prop1 = $ref1->getProperty('hooks_manager');
		$prop1->setAccessible(true);
		$prop1->setValue($obj, $m1);
		$this->assertTrue($obj->exDeferred(array(
		    'wp_head'   => array('priority' => 5, 'callback' => null, 'context' => array()),
		    'wp_footer' => array('priority' => 99, 'callback' => array($obj, 'enqueue_scripts'), 'context' => array()),
		)));
		// Case 2: all null -> empty definitions
		$m2 = Mockery::mock(HooksManager::class);
		$m2->shouldReceive('register_hook_group')->with('deferred_processing', array())->once()->andReturn(true);
		$prop1->setValue($obj, $m2);
		$this->assertTrue($obj->exDeferred(array(
		    'wp_head' => array('priority' => 5, 'callback' => null, 'context' => array()),
		)));
	}
}
