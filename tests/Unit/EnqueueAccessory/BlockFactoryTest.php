<?php
/**
 * Tests for BlockFactory fluent interface wrapper.
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
use Ran\PluginLib\Util\CollectingLogger;
use Ran\PluginLib\Util\ExpectLogTrait;

/**
 * Class BlockFactoryTest
 *
 * Tests for the BlockFactory fluent interface wrapper around BlockRegistrar.
 * Covers singleton behavior, testing mode, fluent interface methods, and
 * cross-plugin override capability.
 */
class BlockFactoryTest extends TestCase {
	use ExpectLogTrait;
	/**
	 * Mock config instance.
	 *
	 * @var ConfigInterface|Mockery\MockInterface
	 */
	private $config;

	/**
	 * @var CollectingLogger
	 */
	private CollectingLogger $logger;

	/**
	 * Set up test environment.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		// Enable testing mode for isolated instances
		BlockFactory::enable_testing_mode();

		$this->config = Mockery::mock(ConfigInterface::class);
		$this->logger = new CollectingLogger();
		$this->config->shouldReceive('get_logger')->andReturn($this->logger);
		$this->logger->collected_logs = array();
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		// Reset testing mode
		BlockFactory::disable_testing_mode();

		Mockery::close();
		parent::tearDown();
	}

	// === TESTING MODE TESTS ===

	/**
	 * Test testing mode can be enabled and disabled.
	 *
	 * @return void
	 */
	public function test_testing_mode_management(): void {
		// Initially enabled from setUp
		$this->assertTrue(BlockFactory::is_testing_mode());

		// Disable testing mode
		BlockFactory::disable_testing_mode();
		$this->assertFalse(BlockFactory::is_testing_mode());

		// Re-enable testing mode
		BlockFactory::enable_testing_mode();
		$this->assertTrue(BlockFactory::is_testing_mode());
	}

	/**
	 * Test that testing mode provides unique instances.
	 *
	 * @return void
	 */
	public function test_testing_mode_unique_instances(): void {
		// Testing mode is enabled from setUp
		$manager1 = new BlockFactory($this->config);
		$manager2 = new BlockFactory($this->config);

		// Add block to first instance
		$manager1->add_block('test/unique-block');

		// Second instance should NOT see the block (unique instances)
		$this->assertFalse($manager2->has_block('test/unique-block'));
	}

	/**
	 * Test get_shared() when no shared instance exists.
	 *
	 * @covers Ran\PluginLib\EnqueueAccessory\BlockFactory::get_shared
	 */
	public function test_get_shared_no_instance(): void {
		// Ensure no shared instance exists
		BlockFactory::enable_testing_mode();
		BlockFactory::disable_testing_mode();

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('No shared BlockFactory instance available. Create a BlockFactory instance first.');

		BlockFactory::get_shared();
	}

	/**
	 * Test get_shared() when shared instance exists.
	 *
	 * @covers Ran\PluginLib\EnqueueAccessory\BlockFactory::get_shared
	 */
	public function test_get_shared_with_instance(): void {
		// Disable testing mode to enable shared instance behavior
		BlockFactory::disable_testing_mode();

		// Create a shared instance
		$manager = new BlockFactory($this->config);
		$manager->add_block('test/shared-block');

		// get_shared() should return the same instance
		$sharedManager = BlockFactory::get_shared();
		$this->assertTrue($sharedManager->has_block('test/shared-block'));

		// Re-enable testing mode for cleanup
		BlockFactory::enable_testing_mode();
	}

	/**
	 * Test that production mode provides shared instances.
	 *
	 * @return void
	 */
	public function test_production_mode_provides_shared_instances(): void {
		// Disable testing mode to simulate production
		BlockFactory::disable_testing_mode();

		// Create first instance and add a block
		$manager1 = new BlockFactory($this->config);
		$manager1->add_block('test/shared-block');

		// Create second instance - should share state with first
		$manager2 = new BlockFactory($this->config);

		// Second instance should see the same block (shared state)
		$this->assertTrue($manager2->has_block('test/shared-block'));

		// Re-enable testing mode for cleanup
		BlockFactory::enable_testing_mode();
	}

	// === CONSTRUCTOR TESTS ===

	/**
	 * Test constructor requires config instance.
	 *
	 * @return void
	 */
	public function test_constructor_requires_config(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Config instance is required for BlockFactory');

		new BlockFactory();
	}

	/**
	 * Test constructor accepts valid config.
	 *
	 * @return void
	 */
	public function test_constructor_accepts_valid_config(): void {
		$manager = new BlockFactory($this->config);

		$this->assertInstanceOf(BlockFactory::class, $manager);
	}

	// === BLOCK MANAGEMENT TESTS ===

	/**
	 * Test adding and retrieving blocks.
	 *
	 * @return void
	 */
	public function test_add_and_get_block(): void {
		$manager = new BlockFactory($this->config);

		$config = array('render_callback' => 'my_render_function');
		$manager->add_block('test/my-block', $config);

		$retrieved = $manager->get_block('test/my-block');
		$this->assertEquals('test/my-block', $retrieved['block_name']);
		$this->assertEquals('my_render_function', $retrieved['render_callback']);
	}

	/**
	 * Test checking if block exists.
	 *
	 * @return void
	 */
	public function test_has_block(): void {
		$manager = new BlockFactory($this->config);

		$this->assertFalse($manager->has_block('test/nonexistent'));

		$manager->add_block('test/existing');
		$this->assertTrue($manager->has_block('test/existing'));
	}

	/**
	 * Test removing blocks.
	 *
	 * @return void
	 */
	public function test_remove_block(): void {
		$manager = new BlockFactory($this->config);

		$manager->add_block('test/removable');
		$this->assertTrue($manager->has_block('test/removable'));

		$manager->remove_block('test/removable');
		$this->assertFalse($manager->has_block('test/removable'));
	}

	/**
	 * Test getting nonexistent block returns empty array.
	 *
	 * @return void
	 */
	public function test_get_nonexistent_block_returns_empty_array(): void {
		$manager = new BlockFactory($this->config);

		$result = $manager->get_block('test/nonexistent');
		$this->assertEquals(array(), $result);
	}

	// === FLUENT INTERFACE TESTS ===

	/**
	 * Test fluent interface returns self for chaining.
	 *
	 * @return void
	 */
	public function test_fluent_interface_chaining(): void {
		$manager = new BlockFactory($this->config);

		$result = $manager
		    ->add_block('test/fluent')
		    ->stage();

		$this->assertSame($manager, $result);
	}


	// === BLOCKREGISTRAR DELEGATION TESTS ===

	/**
	 * Test stage method returns self for chaining.
	 *
	 * @return void
	 */
	public function test_stage_returns_self(): void {
		$manager = new BlockFactory($this->config);

		$result = $manager->stage();
		$this->assertSame($manager, $result);
	}

	/**
	 * Test load method returns self for chaining.
	 *
	 * @return void
	 */
	public function test_load_returns_self(): void {
		$manager = new BlockFactory($this->config);

		$result = $manager->load();
		$this->assertSame($manager, $result);
	}

	/**
	 * Test register method returns registration results array.
	 *
	 * @return void
	 */
	public function test_register_returns_results(): void {
		$manager = new BlockFactory($this->config);
		$manager->add_block('test/example');

		$result = $manager->register();
		$this->assertIsArray($result);
		// In test environment, blocks may not actually register with WordPress
		// but we should get an array with our block name as key
		$this->assertArrayHasKey('test/example', $result);
	}

	/**
	 * Test get_registered_block_types returns array.
	 *
	 * @return void
	 */
	public function test_get_registered_block_types_returns_array(): void {
		$manager = new BlockFactory($this->config);
		$result  = $manager->get_registered_block_types();

		// Should return array (empty in test environment)
		$this->assertIsArray($result);
	}

	/**
	 * Test get_registered_block_type accepts string.
	 *
	 * @return void
	 */
	public function test_get_registered_block_type_accepts_string(): void {
		$manager = new BlockFactory($this->config);
		$result  = $manager->get_registered_block_type('test/block');

		// Should return null for non-registered block
		$this->assertNull($result);
	}

	// Test for _ensure_block_exists removed - method no longer exists in streamlined BlockFactory
	// Block validation is now handled by Block objects in the Block-first approach

	// === BLOCK OBJECT SUPPORT TESTS ===

	/**
	 * Test block method creates new block.
	 *
	 * @return void
	 */
	public function test_block_method_creates_new_block(): void {
		$manager = new BlockFactory($this->config);
		$block   = $manager->block('test/new-block');

		$this->assertInstanceOf('Ran\PluginLib\EnqueueAccessory\Block', $block);
		$this->assertEquals('test/new-block', $block->get_name());
		$this->assertTrue($manager->has_block('test/new-block'));
	}

	/**
	 * Test block method with initial config.
	 *
	 * @return void
	 */
	public function test_block_method_with_initial_config(): void {
		$manager = new BlockFactory($this->config);
		$config  = array('title' => 'Test Block', 'category' => 'layout');
		$block   = $manager->block('test/configured-block', $config);

		$this->assertInstanceOf('Ran\PluginLib\EnqueueAccessory\Block', $block);
		$this->assertEquals('Test Block', $block->get('title'));
		$this->assertEquals('layout', $block->get('category'));
	}

	/**
	 * Test block method returns existing block.
	 *
	 * @return void
	 */
	public function test_block_method_returns_existing_block(): void {
		$manager = new BlockFactory($this->config);
		$manager->add_block('test/existing', array('title' => 'Existing Block'));

		$block = $manager->block('test/existing');

		$this->assertInstanceOf('Ran\PluginLib\EnqueueAccessory\Block', $block);
		$this->assertEquals('test/existing', $block->get_name());
		$this->assertEquals('Existing Block', $block->get('title'));
	}

	/**
	 * Test update_block method.
	 *
	 * @return void
	 */
	public function test_update_block_method(): void {
		$manager = new BlockFactory($this->config);
		$manager->add_block('test/update', array('title' => 'Original'));

		$updated_config = array(
			'block_name' => 'test/update',
			'title'      => 'Updated Block',
			'category'   => 'layout'
		);

		$result = $manager->update_block('test/update', $updated_config);

		$this->assertSame($manager, $result);
		$this->assertEquals($updated_config, $manager->get_block('test/update'));
	}

	/**
	 * Test Block object integration with manager.
	 *
	 * @return void
	 */
	public function test_block_object_integration(): void {
		$manager = new BlockFactory($this->config);
		$block   = $manager->block('test/integration');

		// Configure block using Block object
		$block
			->title('Integration Test')
			->category('widgets')
			->condition('is_admin');

		// Verify changes are reflected in manager
		$config = $manager->get_block('test/integration');
		$this->assertEquals('Integration Test', $config['title']);
		$this->assertEquals('widgets', $config['category']);
		$this->assertEquals('is_admin', $config['condition']);
	}

	/**
	 * Test multiple Block objects from same manager.
	 *
	 * @return void
	 */
	public function test_multiple_block_objects(): void {
		$manager = new BlockFactory($this->config);

		$block1 = $manager->block('test/block-1');
		$block2 = $manager->block('test/block-2');

		$block1->title('Block One')->category('layout');
		$block2->title('Block Two')->category('widgets');

		// Verify both blocks exist and have correct config
		$this->assertTrue($manager->has_block('test/block-1'));
		$this->assertTrue($manager->has_block('test/block-2'));

		$config1 = $manager->get_block('test/block-1');
		$config2 = $manager->get_block('test/block-2');

		$this->assertEquals('Block One', $config1['title']);
		$this->assertEquals('layout', $config1['category']);
		$this->assertEquals('Block Two', $config2['title']);
		$this->assertEquals('widgets', $config2['category']);
	}
}
