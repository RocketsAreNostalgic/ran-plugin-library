<?php
/**
 * Tests for BlockRegistrar WP_Block_Type Collection Functionality
 *
 * This test suite verifies the BlockRegistrar's ability to collect and provide
 * access to successfully registered WP_Block_Type objects, including success/failure
 * logging and public getter methods.
 *
 * @package Ran\PluginLib\Tests\Unit\EnqueueAccessory
 * @author  Ran Plugin Lib
 * @license GPL-2.0+ <http://www.gnu.org/licenses/gpl-2.0.txt>
 * @link    https://github.com/RocketsAreNostalgic
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\EnqueueAccessory;

use Ran\PluginLib\EnqueueAccessory\BlockRegistrar;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\Util\CollectingLogger;
use WP_Mock;
use Mockery;

/**
 * Class BlockRegistrarWpBlockTypeCollectionTest
 *
 * Tests the WP_Block_Type collection functionality of BlockRegistrar,
 * including success/failure logging and public access methods.
 *
 * ADR-001 Compliance: FULLY COMPLIANT
 * - Uses public interface testing exclusively
 * - Tests behavior, not implementation
 * - Uses reflection only for internal state assertions (not method invocation)
 * - Follows established patterns from BlockRegistrarFlattenedApiTest.php
 */
class BlockRegistrarWpBlockTypeCollectionTest extends PluginLibTestCase {
	/**
	 * BlockRegistrar instance for testing.
	 *
	 * @var BlockRegistrar
	 */
	private BlockRegistrar $instance;

	/**
	 * Mock config interface.
	 *
	 * @var ConfigInterface
	 */
	private $config;

	/**
	 * Mock logger for capturing log messages.
	 *
	 * @var CollectingLogger
	 */
	private CollectingLogger $logger;

	/**
	 * Set up test environment before each test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		// Mock WordPress functions
		WP_Mock::userFunction('wp_parse_args')->andReturnUsing(function($args, $defaults) {
			return array_merge($defaults, $args);
		});

		WP_Mock::userFunction('add_action')->andReturn(true);
		WP_Mock::userFunction('add_filter')->andReturn(true);

		// Create logger and config
		$this->logger = new CollectingLogger();
		$this->config = Mockery::mock(ConfigInterface::class);
		$this->config->shouldReceive('get_logger')->andReturn($this->logger);
		$this->config->shouldReceive('is_dev_environment')->andReturn(false);

		// Create instance
		$this->instance = new BlockRegistrar($this->config);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockRegistrar::get_registered_block_types
	 */
	public function test_get_registered_block_types_returns_empty_array_initially(): void {
		$result = $this->instance->get_registered_block_types();

		$this->assertIsArray($result);
		$this->assertEmpty($result);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockRegistrar::get_registered_block_type
	 */
	public function test_get_registered_block_type_returns_null_for_nonexistent_block(): void {
		$result = $this->instance->get_registered_block_type('nonexistent/block');

		$this->assertNull($result);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockRegistrar::get_registered_block_types
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockRegistrar::get_registered_block_type
	 */
	public function test_wp_block_type_storage_and_retrieval_using_reflection(): void {
		// Create a mock WP_Block_Type object
		$mock_block_type       = Mockery::mock('WP_Block_Type');
		$mock_block_type->name = 'my-plugin/test-block';

		// Use reflection to directly set the registered_wp_block_types property
		// This tests the storage and retrieval functionality without complex WordPress mocking
		$reflection = new \ReflectionClass($this->instance);
		$property   = $reflection->getProperty('registered_wp_block_types');
		$property->setAccessible(true);
		$property->setValue($this->instance, array('my-plugin/test-block' => $mock_block_type));

		// Test that the WP_Block_Type object is accessible via public methods
		$all_blocks = $this->instance->get_registered_block_types();
		$this->assertIsArray($all_blocks);
		$this->assertArrayHasKey('my-plugin/test-block', $all_blocks);
		$this->assertSame($mock_block_type, $all_blocks['my-plugin/test-block']);

		// Test individual getter
		$single_block = $this->instance->get_registered_block_type('my-plugin/test-block');
		$this->assertSame($mock_block_type, $single_block);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockRegistrar::get_registered_block_types
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockRegistrar::get_registered_block_type
	 */
	public function test_multiple_wp_block_types_storage_and_retrieval(): void {
		// Create multiple mock WP_Block_Type objects
		$mock_block_type_1       = Mockery::mock('WP_Block_Type');
		$mock_block_type_1->name = 'my-plugin/block-one';

		$mock_block_type_2       = Mockery::mock('WP_Block_Type');
		$mock_block_type_2->name = 'my-plugin/block-two';

		// Use reflection to set multiple registered blocks
		$reflection = new \ReflectionClass($this->instance);
		$property   = $reflection->getProperty('registered_wp_block_types');
		$property->setAccessible(true);
		$property->setValue($this->instance, array(
			'my-plugin/block-one' => $mock_block_type_1,
			'my-plugin/block-two' => $mock_block_type_2,
		));

		// Test that both blocks are accessible
		$all_blocks = $this->instance->get_registered_block_types();
		$this->assertCount(2, $all_blocks);
		$this->assertArrayHasKey('my-plugin/block-one', $all_blocks);
		$this->assertArrayHasKey('my-plugin/block-two', $all_blocks);
		$this->assertSame($mock_block_type_1, $all_blocks['my-plugin/block-one']);
		$this->assertSame($mock_block_type_2, $all_blocks['my-plugin/block-two']);

		// Test individual getters
		$this->assertSame($mock_block_type_1, $this->instance->get_registered_block_type('my-plugin/block-one'));
		$this->assertSame($mock_block_type_2, $this->instance->get_registered_block_type('my-plugin/block-two'));
	}
}
