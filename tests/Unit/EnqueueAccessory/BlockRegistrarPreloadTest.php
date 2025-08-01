<?php
/**
 * Tests for BlockRegistrar preload functionality
 *
 * ADR-001 COMPLIANCE STATUS: PARTIALLY COMPLIANT WITH DOCUMENTED EXCEPTIONS
 *
 * This test suite follows ADR-001 public interface testing patterns where possible,
 * but includes documented exceptions for WordPress hook callback testing.
 *
 * COMPLIANT ASPECTS:
 * - Uses public interface (add(), stage()) for main functionality testing
 * - Uses reflection only for test setup and internal state verification
 * - Tests behavior through realistic WordPress integration patterns
 * - Comprehensive WordPress function mocking
 *
 * DOCUMENTED EXCEPTIONS (3 tests):
 * - test_generate_preload_tags_for_assets_outputs_script_tags()
 * - test_generate_preload_tags_for_assets_outputs_style_tags()
 * - test_generate_preload_tags_with_inherit()
 *
 * JUSTIFICATION FOR EXCEPTIONS:
 * 1. WordPress Hook Integration: Methods are designed as WordPress hook callbacks,
 *    not accessible through public interfaces
 * 2. HTML Output Testing: Critical to verify correct HTML preload tag generation
 *    for security and functionality
 * 3. WP_Mock Limitations: WP_Mock doesn't execute hook callbacks, making
 *    integration testing through do_action() impossible
 * 4. Complex WordPress Integration: Testing through public interface would require
 *    full WordPress hook system simulation, which is not feasible in unit tests
 *
 * These exceptions represent legitimate cases where reflection-based testing
 * provides better test reliability and maintainability than forced public
 * interface testing.
 *
 * @package Ran\PluginLib\Tests\Unit\EnqueueAccessory
 * @author  Ran Plugin Lib
 * @license GPL-2.0+ <http://www.gnu.org/licenses/gpl-2.0.txt>
 * @link    https://github.com/RocketsAreNostalgic
 * @since   0.1.0
 */

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\EnqueueAccessory;

use Ran\PluginLib\EnqueueAccessory\BlockRegistrar;
use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Util\CollectingLogger;
use WP_Mock;
use Mockery;
use ReflectionClass;

/**
 * Class BlockRegistrarPreloadTest
 *
 * Tests the preload functionality of the BlockRegistrar class.
 */
class BlockRegistrarPreloadTest extends PluginLibTestCase {
	/**
	 * The BlockRegistrar instance under test.
	 *
	 * @var BlockRegistrar
	 */
	protected BlockRegistrar $instance;

	/**
	 * Mock configuration object.
	 *
	 * @var ConfigInterface
	 */
	protected ConfigInterface $config;

	/**
	 * Logger instance for testing.
	 *
	 * @var CollectingLogger
	 */
	protected CollectingLogger $logger;

	/**
	 * Set up test fixtures before each test method.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->logger = new CollectingLogger();

		$this->config = Mockery::mock(ConfigInterface::class);
		$this->config->shouldReceive('get_logger')->andReturn($this->logger);
		$this->config->shouldReceive('is_dev_environment')->andReturn(false);

		$this->instance = new BlockRegistrar($this->config);
	}

	/**
	 * Clean up test environment.
	 */
	public function tearDown(): void {
		parent::tearDown();
		Mockery::close();
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockRegistrar::add
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockRegistrar::_register_block_for_preloading
	 */
	public function test_add_registers_block_for_always_preloading(): void {
		// Arrange
		$block_definition = array(
			'block_name' => 'my-plugin/hero-block',
			'preload'    => true,
			'assets'     => array(
				'scripts' => array(
					array(
						'handle' => 'hero-script',
						'src'    => 'path/to/hero.js'
					)
				)
			)
		);

		// Act
		$result = $this->instance->add($block_definition);

		// Assert
		$this->assertInstanceOf(BlockRegistrar::class, $result);

		// Use reflection to check internal state
		$reflection              = new \ReflectionClass($this->instance);
		$preload_blocks_property = $reflection->getProperty('preload_blocks');
		$preload_blocks_property->setAccessible(true);
		$preload_blocks = $preload_blocks_property->getValue($this->instance);

		$this->assertArrayHasKey('my-plugin/hero-block', $preload_blocks);
		$this->assertTrue($preload_blocks['my-plugin/hero-block']);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockRegistrar::add
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockRegistrar::_register_block_for_preloading
	 */
	public function test_add_registers_block_for_conditional_preloading(): void {
		// Arrange
		$condition = function() {
			return is_front_page();
		};
		$block_definition = array(
			'block_name' => 'my-plugin/hero-block',
			'preload'    => $condition,
			'assets'     => array(
				'scripts' => array(
					array(
						'handle' => 'hero-script',
						'src'    => 'path/to/hero.js'
					)
				)
			)
		);

		// Act
		$result = $this->instance->add($block_definition);

		// Assert
		$this->assertInstanceOf(BlockRegistrar::class, $result);

		// Use reflection to check internal state
		$reflection                   = new \ReflectionClass($this->instance);
		$conditional_preload_property = $reflection->getProperty('conditional_preload_blocks');
		$conditional_preload_property->setAccessible(true);
		$conditional_preload_blocks = $conditional_preload_property->getValue($this->instance);

		$this->assertArrayHasKey('my-plugin/hero-block', $conditional_preload_blocks);
		$this->assertSame($condition, $conditional_preload_blocks['my-plugin/hero-block']);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockRegistrar::_setup_preload_callbacks
	 */
	public function test_setup_preload_callbacks_registers_wp_head_hook(): void {
		// Arrange
		$this->instance->add(array(
			'block_name' => 'my-plugin/hero-block',
			'preload'    => true
		));

		WP_Mock::expectActionAdded('wp_head', array($this->instance, '_generate_preload_tags'), 2);

		// Act
		$result = $this->instance->stage();

		// Assert
		$this->assertInstanceOf(BlockRegistrar::class, $result);
		$this->assertConditionsMet();
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockRegistrar::_generate_preload_tags_for_assets
	 *
	 * ADR-001 Exception: Direct private method testing justified because:
	 * 1. WordPress Hook Integration: Method is designed as WordPress hook callback, not accessible through public interface
	 * 2. HTML Output Testing: Critical to verify correct HTML preload tag generation for security/functionality
	 * 3. WP_Mock Limitations: WP_Mock doesn't execute hook callbacks, making integration testing impossible
	 * 4. Complex WordPress Integration: Testing through public interface would require full WordPress hook system simulation
	 */
	public function test_generate_preload_tags_for_assets_outputs_script_tags(): void {
		// Arrange
		$assets = array(
			array(
				'handle' => 'test-script',
				'src'    => 'https://example.com/script.js'
			)
		);

		// Use reflection to test WordPress hook callback method
		$reflection = new \ReflectionClass($this->instance);
		$method     = $reflection->getMethod('_generate_preload_tags_for_assets');
		$method->setAccessible(true);

		// Capture output
		ob_start();

		// Act - Test the method that WordPress would call via wp_head hook
		$method->invoke($this->instance, $assets, 'script');
		$output = ob_get_clean();

		// Assert - Verify correct HTML preload tag generation
		$this->assertStringContainsString('<link rel="preload"', $output);
		$this->assertStringContainsString('href="https://example.com/script.js"', $output);
		$this->assertStringContainsString('as="script"', $output);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockRegistrar::_generate_preload_tags_for_assets
	 *
	 * ADR-001 Exception: Direct private method testing justified because:
	 * 1. WordPress Hook Integration: Method is designed as WordPress hook callback, not accessible through public interface
	 * 2. HTML Output Testing: Critical to verify correct HTML preload tag generation for security/functionality
	 * 3. WP_Mock Limitations: WP_Mock doesn't execute hook callbacks, making integration testing impossible
	 * 4. Complex WordPress Integration: Testing through public interface would require full WordPress hook system simulation
	 */
	public function test_generate_preload_tags_for_assets_outputs_style_tags(): void {
		// Arrange
		$assets = array(
			array(
				'handle' => 'test-style',
				'src'    => 'https://example.com/style.css'
			)
		);

		// Use reflection to test WordPress hook callback method
		$reflection = new \ReflectionClass($this->instance);
		$method     = $reflection->getMethod('_generate_preload_tags_for_assets');
		$method->setAccessible(true);

		// Capture output
		ob_start();

		// Act - Test the method that WordPress would call via wp_head hook
		$method->invoke($this->instance, $assets, 'style');
		$output = ob_get_clean();

		// Assert - Verify correct HTML preload tag generation
		$this->assertStringContainsString('<link rel="preload"', $output);
		$this->assertStringContainsString('href="https://example.com/style.css"', $output);
		$this->assertStringContainsString('as="style"', $output);
		$this->assertStringContainsString('type="text/css"', $output);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockRegistrar::_register_block_for_preloading
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockRegistrar::_find_block_definition
	 */
	public function test_preload_inherit_with_block_condition(): void {
		// Arrange
		$condition = function() {
			return true;
		};

		$this->instance->add(array(
			'block_name' => 'my-plugin/conditional-block',
			'condition'  => $condition,
			'preload'    => 'inherit',
			'assets'     => array(
				'scripts' => array(
					array(
						'handle' => 'conditional-script',
						'src'    => 'https://example.com/conditional.js'
					)
				)
			)
		));

		// Act
		$reflection                 = new ReflectionClass($this->instance);
		$conditional_preload_blocks = $reflection->getProperty('conditional_preload_blocks');
		$conditional_preload_blocks->setAccessible(true);

		// Assert
		$conditional_blocks = $conditional_preload_blocks->getValue($this->instance);
		$this->assertArrayHasKey('my-plugin/conditional-block', $conditional_blocks);
		$this->assertSame($condition, $conditional_blocks['my-plugin/conditional-block']);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockRegistrar::_register_block_for_preloading
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockRegistrar::_find_block_definition
	 */
	public function test_preload_inherit_without_block_condition(): void {
		// Arrange
		$this->instance->add(array(
			'block_name' => 'my-plugin/always-block',
			'preload'    => 'inherit',
			'assets'     => array(
				'scripts' => array(
					array(
						'handle' => 'always-script',
						'src'    => 'https://example.com/always.js'
					)
				)
			)
		));

		// Act
		$reflection     = new ReflectionClass($this->instance);
		$preload_blocks = $reflection->getProperty('preload_blocks');
		$preload_blocks->setAccessible(true);

		// Assert
		$always_preload_blocks = $preload_blocks->getValue($this->instance);
		$this->assertArrayHasKey('my-plugin/always-block', $always_preload_blocks);
		$this->assertTrue($always_preload_blocks['my-plugin/always-block']);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockRegistrar::_generate_preload_tags
	 *
	 * ADR-001 Exception: Direct private method testing justified because:
	 * 1. WordPress Hook Integration: Method is designed as WordPress hook callback, not accessible through public interface
	 * 2. Complex Conditional Logic: Testing inherit preload logic requires direct method access
	 * 3. WP_Mock Limitations: WP_Mock doesn't execute hook callbacks, making integration testing impossible
	 * 4. Critical Functionality: Preload inheritance logic is complex and needs direct verification
	 */
	public function test_generate_preload_tags_with_inherit(): void {
		// Arrange - Set up block with inherit preload and passing condition
		$this->instance->add(array(
			'block_name' => 'test/inherit-block',
			'condition'  => function() {
				return true;
			}, // Condition that passes
			'preload' => 'inherit',
			'assets'  => array(
				'scripts' => array(
					array(
						'handle' => 'inherit-script',
						'src'    => 'https://example.com/inherit.js'
					)
				)
			)
		));

		// Use reflection to test WordPress hook callback method
		$reflection = new \ReflectionClass($this->instance);
		$method     = $reflection->getMethod('_generate_preload_tags');
		$method->setAccessible(true);

		// Capture output
		ob_start();

		// Act - Test the method that WordPress would call via wp_head hook
		$method->invoke($this->instance);
		$output = ob_get_clean();

		// Assert - Verify preload tags are generated when inherit condition is met
		$this->assertStringContainsString('<link rel="preload"', $output);
		$this->assertStringContainsString('href="https://example.com/inherit.js"', $output);
		$this->assertStringContainsString('as="script"', $output);
	}
}
