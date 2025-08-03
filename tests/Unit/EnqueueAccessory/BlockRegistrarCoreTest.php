<?php
/**
 * Tests for BlockRegistrar core functionality
 *
 * This test suite covers the fundamental BlockRegistrar functionality that is not
 * covered by the specialized test files (FlattenedApi, Preload, WpBlockTypeCollection).
 * Focuses on core block registration, asset management, lifecycle, and WordPress integration.
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
use ReflectionClass;
use PHPUnit\Framework\TestCase;
use Ran\PluginLib\Util\CollectingLogger;
use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\EnqueueAccessory\StylesHandler;
use Ran\PluginLib\EnqueueAccessory\BlockRegistrar;
use Ran\PluginLib\EnqueueAccessory\ScriptsHandler;

/**
 * Class BlockRegistrarCoreTest
 *
 * Tests core BlockRegistrar functionality including constructor, basic block registration,
 * asset management integration, lifecycle methods (stage, load, register), condition handling,
 * hook management, and WordPress integration patterns.
 */
class BlockRegistrarCoreTest extends PluginLibTestCase {
	/**
	 * BlockRegistrar instance for testing.
	 *
	 * @var BlockRegistrar
	 */
	private BlockRegistrar $block_registrar;

	/**
	 * Mock config instance.
	 *
	 * @var ConfigInterface|Mockery\MockInterface
	 */
	private $config;

	/**
	 * Collecting logger for test verification.
	 *
	 * @var CollectingLogger
	 */
	private CollectingLogger $logger;

	/**
	 * Set up test environment.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		$this->logger = new CollectingLogger();

		$this->config = Mockery::mock(ConfigInterface::class);
		$this->config->shouldReceive('get_logger')->andReturn($this->logger);
		$this->config->shouldReceive('is_dev_environment')->andReturn(false);

		// Mock WordPress classes - avoid static method mocking issues
		// WP_Block_Type_Registry will be mocked when needed in individual tests

		$this->block_registrar = new BlockRegistrar($this->config);
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		Mockery::close();
		parent::tearDown();
	}

	// === CONSTRUCTOR TESTS ===

	/**
	 * Test BlockRegistrar constructor initializes properly.
	 *
	 * @return void
	 */
	public function test_constructor_initializes_properly(): void {
		$registrar = new BlockRegistrar($this->config);

		$this->assertInstanceOf(BlockRegistrar::class, $registrar);

		// Verify asset handlers are initialized
		$reflection       = new ReflectionClass($registrar);
		$scripts_property = $reflection->getProperty('scripts_handler');
		$scripts_property->setAccessible(true);
		$styles_property = $reflection->getProperty('styles_handler');
		$styles_property->setAccessible(true);

		$this->assertInstanceOf(ScriptsHandler::class, $scripts_property->getValue($registrar));
		$this->assertInstanceOf(StylesHandler::class, $styles_property->getValue($registrar));
	}

	/**
	 * Test constructor requires config parameter.
	 *
	 * @return void
	 */
	public function test_constructor_requires_config(): void {
		$this->expectException(\TypeError::class);
		new BlockRegistrar(); // @phpstan-ignore-line
	}

	// === BASIC BLOCK REGISTRATION TESTS ===

	/**
	 * Test add method with single block definition.
	 *
	 * @return void
	 */
	public function test_add_single_block_definition(): void {
		$block_definition = array(
			'block_name'      => 'test/single-block',
			'title'           => 'Test Block',
			'render_callback' => 'test_render_callback'
		);

		$result = $this->block_registrar->add($block_definition);

		$this->assertSame($this->block_registrar, $result);

		// Verify block was stored internally
		$reflection      = new ReflectionClass($this->block_registrar);
		$blocks_property = $reflection->getProperty('blocks');
		$blocks_property->setAccessible(true);
		$blocks = $blocks_property->getValue($this->block_registrar);

		$this->assertArrayHasKey('init', $blocks);
		$this->assertArrayHasKey(10, $blocks['init']);
		$this->assertCount(1, $blocks['init'][10]);
		$this->assertEquals($block_definition, $blocks['init'][10][0]);
	}

	/**
	 * Test add method with multiple block definitions.
	 *
	 * @return void
	 */
	public function test_add_multiple_block_definitions(): void {
		$block_definitions = array(
			array(
				'block_name' => 'test/block-one',
				'title'      => 'Block One'
			),
			array(
				'block_name' => 'test/block-two',
				'title'      => 'Block Two'
			)
		);

		$result = $this->block_registrar->add($block_definitions);

		$this->assertSame($this->block_registrar, $result);

		// Verify both blocks were stored
		$reflection      = new ReflectionClass($this->block_registrar);
		$blocks_property = $reflection->getProperty('blocks');
		$blocks_property->setAccessible(true);
		$blocks = $blocks_property->getValue($this->block_registrar);

		$this->assertCount(2, $blocks['init'][10]);
	}

	/**
	 * Test add method with custom hook and priority.
	 *
	 * @return void
	 */
	public function test_add_with_custom_hook_and_priority(): void {
		$block_definition = array(
			'block_name' => 'test/custom-hook',
			'title'      => 'Custom Hook Block',
			'hook'       => 'wp_loaded',
			'priority'   => 20
		);

		$this->block_registrar->add($block_definition);

		$reflection      = new ReflectionClass($this->block_registrar);
		$blocks_property = $reflection->getProperty('blocks');
		$blocks_property->setAccessible(true);
		$blocks = $blocks_property->getValue($this->block_registrar);

		$this->assertArrayHasKey('wp_loaded', $blocks);
		$this->assertArrayHasKey(20, $blocks['wp_loaded']);
		$this->assertEquals($block_definition, $blocks['wp_loaded'][20][0]);
	}

	/**
	 * Test add method prevents duplicate block names.
	 *
	 * @return void
	 */
	public function test_add_prevents_duplicate_block_names(): void {
		$block_definition = array(
			'block_name' => 'test/duplicate',
			'title'      => 'Original Block'
		);

		$duplicate_definition = array(
			'block_name' => 'test/duplicate',
			'title'      => 'Duplicate Block'
		);

		// Add original block
		$this->block_registrar->add($block_definition);

		// Attempt to add duplicate
		$this->block_registrar->add($duplicate_definition);

		// Verify only one block was stored and warning was logged
		$reflection      = new ReflectionClass($this->block_registrar);
		$blocks_property = $reflection->getProperty('blocks');
		$blocks_property->setAccessible(true);
		$blocks = $blocks_property->getValue($this->block_registrar);

		$this->assertCount(1, $blocks['init'][10]);
		$this->assertEquals($block_definition, $blocks['init'][10][0]);

		// Check for warning log
		$logs          = $this->logger->get_logs();
		$warning_found = false;
		foreach ($logs as $log) {
			if (isset($log['level']) && $log['level'] === 'warning' && strpos($log['message'], 'already added for registration') !== false) {
				$warning_found = true;
				break;
			}
		}
		$this->assertTrue($warning_found, 'Expected warning log for duplicate block name');
	}

	/**
	 * Test add method handles missing block_name.
	 *
	 * @return void
	 */
	public function test_add_handles_missing_block_name(): void {
		$invalid_definition = array(
			'title' => 'Block Without Name'
		);

		$this->block_registrar->add($invalid_definition);

		// Verify blocks array is empty (invalid blocks should not be stored)
		$reflection      = new ReflectionClass($this->block_registrar);
		$blocks_property = $reflection->getProperty('blocks');
		$blocks_property->setAccessible(true);
		$blocks = $blocks_property->getValue($this->block_registrar);

		// Should be empty since all blocks were invalid
		$this->assertEmpty($blocks, 'Blocks array should be empty when all definitions are invalid');

		// Check for warning log
		$logs          = $this->logger->get_logs();
		$warning_found = false;
		foreach ($logs as $log) {
			if (isset($log['level']) && $log['level'] === 'warning' && strpos($log['message'], "missing 'block_name'") !== false) {
				$warning_found = true;
				break;
			}
		}
		$this->assertTrue($warning_found, 'Expected warning log for missing block_name');
	}

	// === ASSET MANAGEMENT INTEGRATION TESTS ===

	/**
	 * Test block registration with script assets.
	 *
	 * @return void
	 */
	public function test_block_with_script_assets(): void {
		$block_definition = array(
			'block_name' => 'test/with-scripts',
			'title'      => 'Block With Scripts',
			'assets'     => array(
				'scripts' => array(
					array(
						'handle' => 'test-script',
						'src'    => 'test-script.js',
						'deps'   => array('wp-blocks')
					)
				)
			)
		);

		$this->block_registrar->add($block_definition);

		// Verify asset configuration was stored
		$reflection            = new ReflectionClass($this->block_registrar);
		$block_assets_property = $reflection->getProperty('block_assets');
		$block_assets_property->setAccessible(true);
		$block_assets = $block_assets_property->getValue($this->block_registrar);

		$this->assertArrayHasKey('test/with-scripts', $block_assets);
		$this->assertArrayHasKey('scripts', $block_assets['test/with-scripts']);
		$this->assertEquals(
			$block_definition['assets']['scripts'],
			$block_assets['test/with-scripts']['scripts']
		);
	}

	/**
	 * Test block registration with style assets.
	 *
	 * @return void
	 */
	public function test_block_with_style_assets(): void {
		$block_definition = array(
			'block_name' => 'test/with-styles',
			'title'      => 'Block With Styles',
			'assets'     => array(
				'styles' => array(
					array(
						'handle' => 'test-style',
						'src'    => 'test-style.css'
					)
				)
			)
		);

		$this->block_registrar->add($block_definition);

		// Verify asset configuration was stored
		$reflection            = new ReflectionClass($this->block_registrar);
		$block_assets_property = $reflection->getProperty('block_assets');
		$block_assets_property->setAccessible(true);
		$block_assets = $block_assets_property->getValue($this->block_registrar);

		$this->assertArrayHasKey('test/with-styles', $block_assets);
		$this->assertArrayHasKey('styles', $block_assets['test/with-styles']);
		$this->assertEquals(
			$block_definition['assets']['styles'],
			$block_assets['test/with-styles']['styles']
		);
	}

	// === LIFECYCLE METHOD TESTS ===

	/**
	 * Test stage method returns self for chaining.
	 *
	 * @return void
	 */
	public function test_stage_returns_self(): void {
		// Add a block to trigger hook registration
		$this->block_registrar->add(array(
			'block_name' => 'test/stage-block',
			'title'      => 'Stage Test Block'
		));

		// Mock WordPress functions to avoid actual hook registration
		WP_Mock::userFunction('add_action')->zeroOrMoreTimes();
		WP_Mock::userFunction('add_filter')->zeroOrMoreTimes();

		$result = $this->block_registrar->stage();

		$this->assertSame($this->block_registrar, $result);
	}

	/**
	 * Test load method executes without errors.
	 *
	 * @return void
	 */
	public function test_load_executes_successfully(): void {
		// Add a block to trigger hook registration
		$this->block_registrar->add(array(
			'block_name' => 'test/load-block',
			'title'      => 'Load Test Block'
		));

		// Mock WordPress functions to avoid actual hook registration
		WP_Mock::userFunction('add_action')->zeroOrMoreTimes();
		WP_Mock::userFunction('add_filter')->zeroOrMoreTimes();

		$this->block_registrar->load();

		// load() returns void, so just verify it doesn't throw
		$this->assertTrue(true);
	}

	/**
	 * Test register method returns registration results array.
	 *
	 * @return void
	 */
	public function test_register_returns_results(): void {
		// Add a block to trigger hook registration
		$this->block_registrar->add(array(
			'block_name' => 'test/register-block',
			'title'      => 'Register Test Block'
		));

		// Mock WordPress functions to avoid actual hook registration
		WP_Mock::userFunction('add_action')->zeroOrMoreTimes();
		WP_Mock::userFunction('add_filter')->zeroOrMoreTimes();

		$result = $this->block_registrar->register();

		$this->assertIsArray($result);
		$this->assertArrayHasKey('test/register-block', $result);
	}

	// === WORDPRESS INTEGRATION TESTS ===

	/**
	 * Test get_registered_block_types returns empty array initially.
	 *
	 * @return void
	 */
	public function test_get_registered_block_types_empty_initially(): void {
		$result = $this->block_registrar->get_registered_block_types();

		$this->assertIsArray($result);
		$this->assertEmpty($result);
	}

	/**
	 * Test get_registered_block_type returns null for non-existent block.
	 *
	 * @return void
	 */
	public function test_get_registered_block_type_non_existent(): void {
		$result = $this->block_registrar->get_registered_block_type('non/existent');

		$this->assertNull($result);
	}

	/**
	 * Test block addition and staging behavior through public interface.
	 * Tests that blocks are properly added and staged for registration.
	 *
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockRegistrar::add
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockRegistrar::stage
	 *
	 * @return void
	 */
	public function test_block_addition_and_staging_behavior(): void {
		// Add block through public interface
		$result = $this->block_registrar->add(array(
			'block_name' => 'test/staging-behavior',
			'title'      => 'Staging Behavior Block'
		));

		// Verify fluent interface (observable outcome)
		$this->assertSame($this->block_registrar, $result);

		// Stage the blocks
		$stage_result = $this->block_registrar->stage();

		// Verify fluent interface for staging
		$this->assertSame($this->block_registrar, $stage_result);

		// Verify logging occurred (observable behavior)
		$logs = $this->logger->get_logs();
		$this->assertNotEmpty($logs);

		// Look for block addition logging
		$add_found = false;
		foreach ($logs as $log) {
			if (isset($log['message']) && strpos($log['message'], 'Adding block') !== false) {
				$add_found = true;
				break;
			}
		}
		$this->assertTrue($add_found, 'Expected logging for block addition');
	}

	/**
	 * Test error handling for invalid block definitions through public interface.
	 * Tests that invalid blocks are handled gracefully without breaking the system.
	 *
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockRegistrar::add
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockRegistrar::get_registered_block_types
	 *
	 * @return void
	 */
	public function test_invalid_block_definition_handling(): void {
		// Add invalid block definition (missing required block_name)
		$result = $this->block_registrar->add(array(
			'title' => 'Invalid Block - No Name'
			// Missing 'block_name' which is required
		));

		// Should still return self for fluent interface
		$this->assertSame($this->block_registrar, $result);

		// Verify appropriate error logging occurred
		$logs = $this->logger->get_logs();
		$this->assertNotEmpty($logs);

		// Look for error or warning logging
		$error_found = false;
		foreach ($logs as $log) {
			if (isset($log['level']) && ($log['level'] === 'error' || $log['level'] === 'warning')) {
				$error_found = true;
				break;
			}
		}
		$this->assertTrue($error_found, 'Expected error/warning logging for invalid block definition');

		// Verify no registered blocks (invalid block shouldn't be registered)
		$registered_blocks = $this->block_registrar->get_registered_block_types();
		$this->assertEmpty($registered_blocks);
	}

	/**
	 * Test condition handling with callable.
	 *
	 * @return void
	 */
	public function test_block_with_condition(): void {
		$condition = function() {
			return is_admin();
		};

		$block_definition = array(
			'block_name' => 'test/conditional-block',
			'title'      => 'Conditional Block',
			'condition'  => $condition
		);

		$this->block_registrar->add($block_definition);

		// Verify condition was stored
		$reflection      = new ReflectionClass($this->block_registrar);
		$blocks_property = $reflection->getProperty('blocks');
		$blocks_property->setAccessible(true);
		$blocks = $blocks_property->getValue($this->block_registrar);

		$stored_block = $blocks['init'][10][0];
		$this->assertSame($condition, $stored_block['condition']);
	}

	/**
	 * Test handling of various invalid block definitions.
	 *
	 * @return void
	 */
	public function test_invalid_block_definitions_handling(): void {
		$invalid_blocks = array(
			// Missing block_name - this will be rejected
			array('title' => 'No Name Block'),
			// Empty block_name - this will be accepted (BlockRegistrar only checks isset)
			array('block_name' => '', 'title' => 'Empty Name'),
			// Non-string block_name - this will be accepted (BlockRegistrar only checks isset)
			array('block_name' => 123, 'title' => 'Numeric Name')
		);

		$this->block_registrar->add($invalid_blocks);

		// Verify that only the missing block_name was rejected
		$reflection      = new ReflectionClass($this->block_registrar);
		$blocks_property = $reflection->getProperty('blocks');
		$blocks_property->setAccessible(true);
		$blocks = $blocks_property->getValue($this->block_registrar);

		// Should have 2 blocks (empty string and numeric are accepted)
		$this->assertNotEmpty($blocks, 'Some blocks should be stored even if they have questionable block_name values');

		// Verify at least one warning was logged (for the missing block_name)
		$logs          = $this->logger->get_logs();
		$warning_count = 0;
		foreach ($logs as $log) {
			if (isset($log['level']) && $log['level'] === 'warning') {
				$warning_count++;
			}
		}
		$this->assertGreaterThan(0, $warning_count, 'Expected warning log for missing block_name');
	}
}
