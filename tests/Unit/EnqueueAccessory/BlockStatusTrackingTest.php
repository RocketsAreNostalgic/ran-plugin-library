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
use WP_Mock;
use PHPUnit\Framework\TestCase;
use Ran\PluginLib\Util\CollectingLogger;
use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\EnqueueAccessory\BlockFactory;
use Ran\PluginLib\EnqueueAccessory\BlockRegistrar;

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
	 * Default factory mock.
	 *
	 * @var BlockFactory|Mockery\MockInterface
	 */
	protected $defaultFactory;

	/**
	 * Default registrar mock.
	 *
	 * @var BlockRegistrar|Mockery\MockInterface
	 */
	protected $defaultRegistrar;

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
		$this->config->shouldReceive('get_logger')->andReturn(new CollectingLogger());

		// Create a partial mock of BlockRegistrar with mocked wrapper methods
		$this->defaultRegistrar = Mockery::mock(BlockRegistrar::class, array($this->config))
			->shouldAllowMockingProtectedMethods()
			->makePartial();

		// Mock the methods we need for testing
		$this->defaultRegistrar->shouldReceive('add')
			->andReturnSelf();

		$this->defaultRegistrar->shouldReceive('stage')
			->andReturnSelf();

		$this->defaultRegistrar->shouldReceive('get_block_status')
			->andReturn(array(
				'test/immediate' => array(
					'status'   => 'pending',
					'hook'     => 'init',
					'priority' => 10,
				),
				'test/deferred' => array(
					'status'   => 'pending',
					'hook'     => 'wp_enqueue_scripts',
					'priority' => 10,
				),
			));

		// Mock the protected methods
		$this->defaultRegistrar->shouldReceive('_has_hook_fired')
			->andReturnUsing(function($hook) {
				// Return true for 'init', false for all other hooks
				return $hook === 'init';
			});

		// This is the key method that's causing issues
		$this->defaultRegistrar->shouldReceive('_do_did_action')
			->andReturnUsing(function($hook) {
				// Return 1 for 'init', 0 for all other hooks
				return $hook === 'init' ? 1 : 0;
			});

		// Create a partial mock of BlockFactory
		$this->defaultFactory = Mockery::mock(BlockFactory::class, array($this->config))
			->makePartial();

		// Set the default registrar
		$this->defaultFactory->registrar = $this->defaultRegistrar;
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
	 * Helper method to create a BlockFactory with the given registrar.
	 *
	 * @param BlockRegistrar $registrar The registrar to use.
	 * @return BlockFactory The factory with the registrar set.
	 */
	protected function createFactoryWithRegistrar($registrar): BlockFactory {
		$factory = new BlockFactory($this->config);

		// Use reflection to set the private registrar property
		$reflection = new \ReflectionClass(BlockFactory::class);
		$property   = $reflection->getProperty('registrar');
		$property->setAccessible(true);
		$property->setValue($factory, $registrar);

		return $factory;
	}

	/**
	 * Test block status tracking with immediate registration.
	 *
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockRegistrar::get_block_status
	 * @return void
	 */
	public function test_block_status_immediate_registration(): void {
		// Use the default factory mock
		$manager = $this->defaultFactory;

		// Ensure _do_did_action returns 0 for any hook (not fired)
		// IMPORTANT: Must return an integer to match the method signature
		$this->defaultRegistrar->shouldReceive('_do_did_action')
			->withAnyArgs()
			->andReturn(0);

		// Add a block with immediate registration
		$manager->add_block('test/immediate', array(
			'title' => 'Immediate Block'
		));

		$status = $manager->get_block_status();

		$this->assertArrayHasKey('test/immediate', $status);
		$block_status = $status['test/immediate'];

		$this->assertEquals('pending', $block_status['status']);
		$this->assertEquals('init', $block_status['hook']);
		$this->assertStringContainsString('init', $block_status['message']);
	}

	/**
	 * Test block status tracking for deferred registration.
	 *
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockRegistrar::get_block_status
	 * @return void
	 */
	public function test_block_status_deferred_registration(): void {
		// Use the default factory mock
		$manager = $this->defaultFactory;

		// Ensure _do_did_action returns 0 for any hook (not fired)
		$this->defaultRegistrar->shouldReceive('_do_did_action')->andReturn(0);

		// Add a block with deferred registration
		$manager->add_block('test/deferred', array(
			'title' => 'Deferred Block',
			'hook'  => 'wp_loaded'
		));

		$status = $manager->get_block_status();

		$this->assertArrayHasKey('test/deferred', $status);
		$block_status = $status['test/deferred'];

		$this->assertEquals('pending', $block_status['status']);
		$this->assertEquals('wp_loaded', $block_status['hook']);
		$this->assertStringContainsString('wp_loaded', $block_status['message']);
		$this->assertFalse($block_status['has_assets']);
		$this->assertFalse($block_status['has_condition']);
		$this->assertFalse($block_status['preload_enabled']);
	}

	/**
	 * Test block status tracking with assets and conditions.
	 *
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockRegistrar::get_block_status
	 * @return void
	 */
	public function test_block_status_with_features(): void {
		// Use the default factory mock
		$manager = $this->defaultFactory;

		// Ensure _do_did_action returns 0 for any hook (not fired)
		$this->defaultRegistrar->shouldReceive('_do_did_action')->andReturn(0);

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
		// Set up WP_Mock for WordPress functions
		WP_Mock::userFunction('did_action', array(
			'return' => function($hook) {
				return $hook === 'init' ? 1 : 0;
			}
		));

		// Create a fresh registrar without the get_block_status mock
		$registrar = new BlockRegistrar($this->config);

		// Mock the protected methods directly on this instance
		$registrar = Mockery::mock($registrar)
			->shouldAllowMockingProtectedMethods()
			->makePartial();

		// We don't need to mock _has_hook_fired or _do_did_action anymore
		// since we've mocked the underlying WordPress function did_action

		// Create a factory with our test helper that allows setting the registrar
		$manager = $this->createFactoryWithRegistrar($registrar);

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
	 * Test block status with multiple blocks having different statuses.
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockRegistrar::get_block_status
	 * @return void
	 */
	public function test_multiple_blocks_different_statuses(): void {
		// Set up WP_Mock for WordPress functions
		WP_Mock::userFunction('did_action', array(
			'return' => function($hook) {
				return $hook === 'init' ? 1 : 0;
			}
		));

		// Create a fresh registrar without the get_block_status mock
		$registrar = new BlockRegistrar($this->config);

		// Mock the protected methods directly on this instance
		$registrar = Mockery::mock($registrar)
			->shouldAllowMockingProtectedMethods()
			->makePartial();

		// We don't need to mock _has_hook_fired or _do_did_action anymore
		// since we've mocked the underlying WordPress function did_action

		// Create a factory with our test helper that allows setting the registrar
		$manager = $this->createFactoryWithRegistrar($registrar);

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
		// Use the default factory mock
		$manager = $this->defaultFactory;

		// Ensure _do_did_action returns different values based on the hook
		$this->defaultRegistrar->shouldReceive('_do_did_action')
			->andReturnUsing(function($hook) {
				// Return 1 for 'my_custom_hook' (has fired), 0 for all other hooks
				return $hook === 'my_custom_hook' ? 1 : 0;
			});

		// No need to override _do_did_action since the default return of 0
		// already indicates that hooks have not fired

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
