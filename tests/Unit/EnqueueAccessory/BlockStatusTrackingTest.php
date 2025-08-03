<?php
/**
 * Tests for Block Status Tracking functionality.
 *
 * @package Ran\PluginLib\Tests\Unit\EnqueueAccessory
 * @author  Ran Plugin Lib
 * @license GPL-2.0+ <http://www.gnu.org/licenses/gpl-2.0.txt>
 * @link    https://github.com/RocketsAreNostalgic
 * @since   0.1.0
 */

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\EnqueueAccessory;

use Mockery;
use PHPUnit\Framework\TestCase;
use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\EnqueueAccessory\BlockFactory;
use Ran\PluginLib\EnqueueAccessory\BlockRegistrar;
use WP_Mock;

/**
 * Class BlockStatusTrackingTest
 *
 * Tests for comprehensive block status tracking functionality including
 * deferred registration, hook monitoring, and lifecycle status reporting.
 */
class BlockStatusTrackingTest extends TestCase {
	/**
	 * Mock config instance.
	 *
	 * @var ConfigInterface|Mockery\MockInterface
	 */
	private $config;

	/**
	 * Set up test environment.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();

		// Mock config
		$this->config = Mockery::mock(ConfigInterface::class);
		$this->config->shouldReceive('get')->with('debug', false)->andReturn(false);
		$this->config->shouldReceive('get')->with('plugin_url', '')->andReturn('https://example.com/plugin/');
		$this->config->shouldReceive('get')->with('plugin_path', '')->andReturn('/path/to/plugin/');
		$this->config->shouldReceive('get')->with('plugin_version', '1.0.0')->andReturn('1.0.0');

		// Mock get_logger() method that's called by AssetEnqueueBaseAbstract constructor
		$mockLogger = Mockery::mock('\Ran\PluginLib\Util\Logger');
		$mockLogger->shouldReceive('is_active')->zeroOrMoreTimes()->andReturn(false);
		$this->config->shouldReceive('get_logger')->zeroOrMoreTimes()->andReturn($mockLogger);

		// Mock WordPress functions
		// Note: did_action() will be mocked individually in tests that need specific behavior
		WP_Mock::userFunction('add_action')->zeroOrMoreTimes();
		WP_Mock::userFunction('add_filter')->zeroOrMoreTimes();
	}

	/**
	 * Tear down test environment.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		WP_Mock::tearDown();
		Mockery::close();
		parent::tearDown();
	}

	/**
	 * Test block status tracking for immediate registration (init hook).
	 *
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockRegistrar::get_block_status
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockFactory::get_block_status
	 * @return void
	 */
	public function test_block_status_immediate_registration(): void {
		// Mock did_action for all hooks - return 0 (not fired) for all hooks
		WP_Mock::userFunction('did_action')
			->withAnyArgs()
			->andReturn(0);

		$manager = new BlockFactory($this->config);

		// Add a block with default hook (init)
		$manager->add_block('test/immediate', array(
			'title' => 'Immediate Block'
		));

		$status = $manager->get_block_status();

		$this->assertIsArray($status);
		$this->assertArrayHasKey('test/immediate', $status);

		$block_status = $status['test/immediate'];
		$this->assertEquals('pending', $block_status['status']);
		$this->assertEquals('init', $block_status['hook']);
		$this->assertEquals(10, $block_status['priority']);
		$this->assertStringContainsString('init', $block_status['message']);
		$this->assertFalse($block_status['has_assets']);
		$this->assertFalse($block_status['has_condition']);
		$this->assertFalse($block_status['preload_enabled']);
	}

	/**
	 * Test block status tracking for deferred registration.
	 *
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockRegistrar::get_block_status
	 * @return void
	 */
	public function test_block_status_deferred_registration(): void {
		// Mock did_action for all hooks - return 0 (not fired) for all hooks
		WP_Mock::userFunction('did_action')
			->withAnyArgs()
			->andReturn(0);

		$manager = new BlockFactory($this->config);

		// Add blocks with different hooks and priorities
		$manager->add_block('test/deferred', array(
			'title'    => 'Deferred Block',
			'hook'     => 'wp_loaded',
			'priority' => 20
		));

		$manager->add_block('test/admin', array(
			'title'    => 'Admin Block',
			'hook'     => 'admin_init',
			'priority' => 5
		));

		$status = $manager->get_block_status();

		// Check deferred block
		$this->assertArrayHasKey('test/deferred', $status);
		$deferred_status = $status['test/deferred'];
		$this->assertEquals('pending', $deferred_status['status']);
		$this->assertEquals('wp_loaded', $deferred_status['hook']);
		$this->assertEquals(20, $deferred_status['priority']);
		$this->assertStringContainsString('wp_loaded', $deferred_status['message']);

		// Check admin block
		$this->assertArrayHasKey('test/admin', $status);
		$admin_status = $status['test/admin'];
		$this->assertEquals('pending', $admin_status['status']);
		$this->assertEquals('admin_init', $admin_status['hook']);
		$this->assertEquals(5, $admin_status['priority']);
	}

	/**
	 * Test block status tracking with assets and conditions.
	 *
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockRegistrar::get_block_status
	 * @return void
	 */
	public function test_block_status_with_features(): void {
		// Mock did_action for all hooks - return 0 (not fired) for all hooks
		WP_Mock::userFunction('did_action')
			->withAnyArgs()
			->andReturn(0);

		$manager = new BlockFactory($this->config);

		// Add a block with assets, condition, and preload
		$manager->add_block('test/featured', array(
			'title'  => 'Featured Block',
			'assets' => array(
				'scripts' => array(array('handle' => 'test-script', 'src' => 'test.js'))
			),
			'condition' => function() {
				return is_front_page();
			},
			'preload' => true
		));

		$status = $manager->get_block_status();

		$this->assertArrayHasKey('test/featured', $status);
		$block_status = $status['test/featured'];

		$this->assertTrue($block_status['has_assets']);
		$this->assertTrue($block_status['has_condition']);
		$this->assertTrue($block_status['preload_enabled']);
		$this->assertArrayHasKey('config', $block_status);
		$this->assertEquals('Featured Block', $block_status['config']['title']);
	}

	/**
	 * Test block status after hook has fired (simulated).
	 *
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockRegistrar::_has_hook_fired
	 * @return void
	 */
	public function test_block_status_after_hook_fired(): void {
		// Mock that init hook has fired
		WP_Mock::userFunction('did_action')
			->with('init')
			->andReturn(1); // Hook has fired
		WP_Mock::userFunction('did_action')
			->with('wp_loaded')
			->andReturn(0); // Hook has not fired
		WP_Mock::userFunction('did_action')
			->with('admin_init')
			->andReturn(0); // Hook has not fired

		$manager = new BlockFactory($this->config);
		$manager->add_block('test/fired', array(
			'title' => 'Fired Block'
		));

		$status = $manager->get_block_status();

		$this->assertArrayHasKey('test/fired', $status);
		$block_status = $status['test/fired'];

		// Since hook fired but no actual WordPress registration happened in test,
		// status should be 'failed'
		$this->assertEquals('failed', $block_status['status']);
		$this->assertEquals('WordPress registration failed', $block_status['error']);
	}

	/**
	 * Test block status with successful registration (mocked).
	 *
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockRegistrar::get_block_status
	 * @return void
	 */
	public function test_block_status_successful_registration(): void {
		// Mock that init hook has not fired yet
		WP_Mock::userFunction('did_action')
			->with('init')
			->andReturn(0);

		$registrar = new BlockRegistrar($this->config);

		// Add a block
		$registrar->add(array(
			'block_name' => 'test/success',
			'title'      => 'Success Block'
		));

		// Mock successful registration by directly setting the registered block type
		$mock_block_type       = Mockery::mock('WP_Block_Type');
		$mock_block_type->name = 'test/success';

		// Use reflection to access private property
		$reflection = new \ReflectionClass($registrar);
		$property   = $reflection->getProperty('registered_wp_block_types');
		$property->setAccessible(true);
		$property->setValue($registrar, array('test/success' => $mock_block_type));

		$status = $registrar->get_block_status();

		$this->assertArrayHasKey('test/success', $status);
		$block_status = $status['test/success'];

		$this->assertEquals('registered', $block_status['status']);
		$this->assertSame($mock_block_type, $block_status['wp_block_type']);
		$this->assertEquals('test/success', $block_status['registered_at']);
	}

	/**
	 * Test multiple blocks with different statuses.
	 *
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockRegistrar::get_block_status
	 * @return void
	 */
	public function test_multiple_blocks_different_statuses(): void {
		// Mock hook firing status for different hooks
		WP_Mock::userFunction('did_action')
			->with('init')
			->andReturn(1); // Hook has fired
		WP_Mock::userFunction('did_action')
			->with('wp_loaded')
			->andReturn(0); // Hook has not fired
		WP_Mock::userFunction('did_action')
			->with('admin_init')
			->andReturn(0); // Hook has not fired
		WP_Mock::userFunction('did_action')
			->with('my_custom_hook')
			->andReturn(0); // Custom hook has not fired

		$manager = new BlockFactory($this->config);

		// Add blocks with different configurations
		$manager->add_block('test/failed', array(
			'title' => 'Failed Block',
			'hook'  => 'init' // Hook fired, will show as failed
		));

		$manager->add_block('test/pending', array(
			'title' => 'Pending Block',
			'hook'  => 'wp_loaded' // Hook not fired, will show as pending
		));

		$status = $manager->get_block_status();

		// Check failed block
		$this->assertEquals('failed', $status['test/failed']['status']);
		$this->assertEquals('init', $status['test/failed']['hook']);

		// Check pending block
		$this->assertEquals('pending', $status['test/pending']['status']);
		$this->assertEquals('wp_loaded', $status['test/pending']['hook']);
	}

	/**
	 * Test block status with custom hooks.
	 *
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockRegistrar::_has_hook_fired
	 * @return void
	 */
	public function test_block_status_custom_hooks(): void {
		// Mock that hooks have not fired yet
		WP_Mock::userFunction('did_action')
			->with('my_custom_hook')
			->andReturn(0);
		WP_Mock::userFunction('did_action')
			->with('init')
			->andReturn(0);
		WP_Mock::userFunction('did_action')
			->with('wp_loaded')
			->andReturn(0);
		WP_Mock::userFunction('did_action')
			->with('admin_init')
			->andReturn(0);

		$manager = new BlockFactory($this->config);
		$manager->add_block('test/custom', array(
			'title' => 'Custom Hook Block',
			'hook'  => 'my_custom_hook'
		));

		$status = $manager->get_block_status();

		$this->assertArrayHasKey('test/custom', $status);
		$block_status = $status['test/custom'];

		$this->assertEquals('pending', $block_status['status']);
		$this->assertEquals('my_custom_hook', $block_status['hook']);
		$this->assertStringContainsString('my_custom_hook', $block_status['message']);
	}
}
