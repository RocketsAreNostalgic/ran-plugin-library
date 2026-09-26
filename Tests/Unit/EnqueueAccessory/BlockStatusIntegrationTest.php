<?php
/**
 * Block Status Integration Tests
 *
 * Tests demonstrating how the block status system integrates with
 * the Block::register() process to provide real-time feedback.
 *
 * @package Ran\PluginLib\Tests\Unit\EnqueueAccessory
 * @author  Ran Plugin Lib
 * @since   0.1.0
 */

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\EnqueueAccessory;

use Mockery;
use Ran\PluginLib\EnqueueAccessory\Block;
use Ran\PluginLib\EnqueueAccessory\BlockFactory;
use Ran\PluginLib\Util\CollectingLogger;
use Ran\PluginLib\Util\ExpectLogTrait;
use WP_Mock\Tools\TestCase;
use WP_Mock;

/**
 * Class BlockStatusIntegrationTest
 *
 * Tests the integration between Block objects and the status tracking system.
 */
class BlockStatusIntegrationTest extends TestCase {
	use ExpectLogTrait;
	/**
	 * Mock configuration object.
	 *
	 * @var \Mockery\MockInterface
	 */
	private $config;

	/**
	 * Shared CollectingLogger instance.
	 */
	private CollectingLogger $logger;

	/**
	 * Set up test environment.
	 *
	 * @return void
	 */
	public function setUp(): void {
		WP_Mock::setUp();
		parent::setUp();

		// Create config mock with shared CollectingLogger
		$this->config = Mockery::mock('\\Ran\\PluginLib\\Config\\ConfigInterface');
		$this->config->shouldReceive('get')->with('plugin_url', '')->andReturn('https://example.com/plugin/');
		$this->config->shouldReceive('get')->with('plugin_path', '')->andReturn('/path/to/plugin/');
		$this->config->shouldReceive('get')->with('plugin_version', '1.0.0')->andReturn('1.0.0');

		$this->logger                 = new CollectingLogger();
		$this->logger->collected_logs = array();
		$this->config->shouldReceive('get_logger')->andReturn($this->logger);
	}

	/**
	 * Tear down test environment.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		WP_Mock::tearDown();
		Mockery::close();
		parent::tearDown();
	}

	/**
	 * Test Block::get_status() during registration process - pending state.
	 *
	 * @covers \Ran\PluginLib\EnqueueAccessory\Block::get_status
	 * @return void
	 */
	public function test_block_get_status_during_registration_pending(): void {
		// Mock that hooks have not fired yet
		WP_Mock::userFunction('did_action')
			->withAnyArgs()
			->andReturn(0);

		$manager = new BlockFactory($this->config);
		$block   = $manager->block('test/status-demo');

		// Configure the block
		$block->set('title', 'Status Demo Block')
			->hook('wp_loaded', 15);

		// Check status before registration - should be pending
		$status = $block->get_status();

		$this->assertIsArray($status);
		$this->assertEquals('pending', $status['status']);
		$this->assertEquals('wp_loaded', $status['hook']);
		$this->assertEquals(15, $status['priority']);
		$this->assertStringContainsString('wp_loaded', $status['message']);
		$this->assertFalse($status['has_assets']);
		$this->assertFalse($status['has_condition']);
		$this->assertFalse($status['preload_enabled']);
	}

	/**
	 * Test Block::get_status() during registration process - failed state.
	 *
	 * @covers \Ran\PluginLib\EnqueueAccessory\Block::get_status
	 * @return void
	 */
	public function test_block_get_status_during_registration_failed(): void {
		// Mock that init hook has fired but registration failed
		WP_Mock::userFunction('did_action')
			->withAnyArgs()
			->andReturn(1);

		$manager = new BlockFactory($this->config);
		$block   = $manager->block('test/failed-demo');

		// Configure the block
		$block->set('title', 'Failed Demo Block')
			->hook('init');

		// Attempt registration (will fail in test environment)
		$result = $block->register();
		$this->assertIsArray($result); // Should return status array on failure
		$this->assertEquals('failed', $result['status']);
		$this->assertFalse($result['registration_successful']);

		// Check status after failed registration
		$status = $block->get_status();

		$this->assertIsArray($status);
		$this->assertEquals('failed', $status['status']);
		$this->assertEquals('init', $status['hook']);
		$this->assertEquals(10, $status['priority']); // Default priority
		$this->assertStringContainsString('failed', $status['error']);
	}

	/**
	 * Test Block::get_status() with features enabled.
	 *
	 * @covers \Ran\PluginLib\EnqueueAccessory\Block::get_status
	 * @return void
	 */
	public function test_block_get_status_with_features(): void {
		// Mock that hooks have not fired yet
		WP_Mock::userFunction('did_action')
			->withAnyArgs()
			->andReturn(0);

		$manager = new BlockFactory($this->config);
		$block   = $manager->block('test/featured-demo');

		// Configure the block with various features
		$block->set('title', 'Featured Demo Block')
			->set('assets', array(
				'scripts' => array(array('handle' => 'demo-script', 'src' => 'demo.js'))
			))
			->set('preload', true);

		// Note: Skip condition for now due to type requirements

		// Check status - should show feature flags
		$status = $block->get_status();

		$this->assertIsArray($status);
		$this->assertEquals('pending', $status['status']);
		$this->assertTrue($status['has_assets']);
		$this->assertFalse($status['has_condition']); // No condition set
		$this->assertTrue($status['preload_enabled']);
		$this->assertArrayHasKey('config', $status);
		$this->assertArrayHasKey('assets', $status['config']);
		// Skip condition check since we didn't set one
		$this->assertArrayHasKey('preload', $status['config']);
	}

	/**
	 * Test Block::get_status() returns null when block not found in manager.
	 *
	 * @covers \Ran\PluginLib\EnqueueAccessory\Block::get_status
	 * @return void
	 */
	public function test_block_get_status_not_found_in_manager(): void {
		// Create mock manager that doesn't have this block in status tracking
		$manager = Mockery::mock(BlockFactory::class);
		$manager->shouldReceive('has_block')->andReturn(false)->byDefault();
		$manager->shouldReceive('get_block')->andReturn(array())->byDefault();
		$manager->shouldReceive('get_block_status')
			->withNoArgs()
			->once()
			->andReturn(array()); // Empty array - no blocks tracked

		$block  = new Block('test/standalone', $manager);
		$status = $block->get_status();

		$this->assertNull($status);
	}

	/**
	 * Test practical workflow: configure, check status, register, check status again.
	 *
	 * @covers \Ran\PluginLib\EnqueueAccessory\Block::get_status
	 * @covers \Ran\PluginLib\EnqueueAccessory\Block::register
	 * @return void
	 */
	public function test_practical_workflow_with_status_tracking(): void {
		// Mock hook states
		WP_Mock::userFunction('did_action')
			->withAnyArgs()
			->andReturn(0); // Not fired initially

		$manager = new BlockFactory($this->config);
		$block   = $manager->block('test/workflow-demo');

		// Step 1: Configure block
		$block->set('title', 'Workflow Demo Block')
			->hook('wp_loaded')
			->set('assets', array('scripts' => array(array('handle' => 'workflow-script', 'src' => 'workflow.js'))));

		// Step 2: Check initial status
		$initial_status = $block->get_status();
		$this->assertEquals('pending', $initial_status['status']);
		$this->assertEquals('wp_loaded', $initial_status['hook']);
		$this->assertTrue($initial_status['has_assets']);
		$this->assertStringContainsString('wp_loaded', $initial_status['message']);

		// Step 3: Attempt registration (hook hasn't fired yet, should remain pending)
		$result = $block->register();
		$this->assertIsArray($result); // Should return status array on pending
		$this->assertEquals('pending', $result['status']);
		$this->assertFalse($result['registration_successful']);

		// Step 4: Check final status (should still be pending since hook didn't fire)
		$final_status = $block->get_status();
		$this->assertEquals('pending', $final_status['status']);
		$this->assertEquals('wp_loaded', $final_status['hook']);
		$this->assertStringContainsString('wp_loaded', $final_status['message']);

		// Step 5: Demonstrate that status would change if hook fired
		// (This shows the system works, even though we can't easily simulate hook state change in tests)
	}
}
