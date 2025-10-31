<?php
/**
 * Tests for Block class.
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
use Ran\PluginLib\EnqueueAccessory\Block;
use Ran\PluginLib\EnqueueAccessory\BlockFactory;
use Ran\PluginLib\Util\CollectingLogger;
use Ran\PluginLib\Util\ExpectLogTrait;

/**
 * Class BlockTest
 *
 * Tests for the Block class that provides object-oriented block configuration.
 * Covers individual block manipulation, manager synchronization, and all
 * fluent interface methods.
 */
class BlockTest extends TestCase {
	use ExpectLogTrait;
	/**
	 * Mock config instance.
	 *
	 * @var ConfigInterface|Mockery\MockInterface
	 */
	private $config;

	/**
	 * Mock BlockFactory instance.
	 *
	 * @var BlockFactory|Mockery\MockInterface
	 */
	private $manager;

	/**
	 * Shared CollectingLogger instance.
	 */
	private CollectingLogger $logger;

	/**
	 * Set up test environment.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->config                 = Mockery::mock(ConfigInterface::class);
		$this->logger                 = new CollectingLogger();
		$this->logger->collected_logs = array();
		$this->config->shouldReceive('get_logger')->andReturn($this->logger);

		// Create mock manager with default expectations
		$this->manager = Mockery::mock(BlockFactory::class);
		$this->manager->shouldReceive('has_block')->andReturn(false)->byDefault();
		$this->manager->shouldReceive('get_block')->andReturn(array())->byDefault();
		$this->manager->shouldReceive('update_block')->andReturnSelf()->byDefault();
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		// Reset BlockFactory shared instance for clean test state
		BlockFactory::enableTestingMode();
		BlockFactory::disableTestingMode();
		Mockery::close();
		parent::tearDown();
	}

	// === CONSTRUCTOR TESTS ===

	/**
	 * Test Block constructor with basic parameters.
	 *
	 * @return void
	 */
	public function test_constructor_basic(): void {
		$block = new Block('test/block', $this->manager);

		$this->assertEquals('test/block', $block->get_name());
		$this->assertEquals(array('block_name' => 'test/block'), $block->get_config());
	}

	/**
	 * Test Block constructor gets config from manager.
	 *
	 * @return void
	 */
	public function test_constructor_with_config(): void {
		$config = array(
			'title'           => 'Test Block',
			'render_callback' => 'test_render'
		);

		// Mock manager to return the config when Block constructor calls _sync_from_manager
		$this->manager->shouldReceive('has_block')
			->with('test/block')
			->once()
			->andReturn(true);
		$this->manager->shouldReceive('get_block')
			->with('test/block')
			->once()
			->andReturn($config);

		$block = new Block('test/block', $this->manager);

		$expected = array_merge(array('block_name' => 'test/block'), $config);
		$this->assertEquals($expected, $block->get_config());
	}

	/**
	 * Test Block constructor with manager reference.
	 *
	 * @return void
	 */
	public function test_constructor_with_manager(): void {
		$this->manager->shouldReceive('update_block')->once();

		$block = new Block('test/block', $this->manager);
		$block->set('test_key', 'test_value');

		$this->assertEquals('test/block', $block->get_name());
	}

	/**
	 * Test Block constructor without manager when no shared instance exists.
	 *
	 * @covers Ran\PluginLib\EnqueueAccessory\Block::__construct
	 */
	public function test_constructor_without_manager_no_shared_instance(): void {
		// Ensure no shared instance exists
		BlockFactory::enableTestingMode();
		BlockFactory::disableTestingMode();

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('No shared BlockFactory instance available. Create a BlockFactory instance first.');

		new Block('test/block');
	}

	/**
	 * Test Block constructor without manager when shared instance exists.
	 *
	 * @covers Ran\PluginLib\EnqueueAccessory\Block::__construct
	 * @covers Ran\PluginLib\EnqueueAccessory\Block::get_name
	 */
	public function test_constructor_without_manager_with_shared_instance(): void {
		// Create a shared BlockFactory instance
		$sharedManager = new BlockFactory($this->config);

		// Now Block should use the shared instance
		$block = new Block('test/block');
		$this->assertSame('test/block', $block->get_name());
	}

	// === BASIC CONFIGURATION TESTS ===

	/**
	 * Test set and get methods.
	 *
	 * @return void
	 */
	public function test_set_and_get(): void {
		$block = new Block('test/block', $this->manager);

		$block->set('custom_property', 'custom_value');

		$this->assertEquals('custom_value', $block->get('custom_property'));
		$this->assertEquals('default', $block->get('nonexistent', 'default'));
		$this->assertNull($block->get('nonexistent'));
	}

	/**
	 * Test get_config returns complete configuration.
	 *
	 * @return void
	 */
	public function test_get_config(): void {
		$block = new Block('test/block', $this->manager);
		$block->set('title', 'Test Block');
		$block->set('category', 'layout');

		$config = $block->get_config();

		$this->assertEquals('test/block', $config['block_name']);
		$this->assertEquals('Test Block', $config['title']);
		$this->assertEquals('layout', $config['category']);
	}

	// === FLUENT INTERFACE TESTS ===

	/**
	 * Test condition method.
	 *
	 * @return void
	 */
	public function test_condition(): void {
		$block  = new Block('test/block', $this->manager);
		$result = $block->condition('is_admin');

		$this->assertSame($block, $result);
		$this->assertEquals('is_admin', $block->get('condition'));
	}

	/**
	 * Test hook method.
	 *
	 * @return void
	 */
	public function test_hook(): void {
		$block  = new Block('test/block', $this->manager);
		$result = $block->hook('wp_loaded', 15);

		$this->assertSame($block, $result);
		$this->assertEquals('wp_loaded', $block->get('hook'));
		$this->assertEquals(15, $block->get('priority'));
	}

	/**
	 * Test hook method with default priority.
	 *
	 * @return void
	 */
	public function test_hook_default_priority(): void {
		$block = new Block('test/block', $this->manager);
		$block->hook('init');

		$this->assertEquals('init', $block->get('hook'));
		$this->assertEquals(10, $block->get('priority'));
	}

	/**
	 * Test assets method.
	 *
	 * @return void
	 */
	public function test_assets(): void {
		$block  = new Block('test/block', $this->manager);
		$assets = array(
			'scripts' => array(array('handle' => 'test-script')),
			'styles'  => array(array('handle' => 'test-style'))
		);

		$result = $block->assets($assets);

		$this->assertSame($block, $result);
		$this->assertEquals($assets, $block->get('assets'));
	}

	/**
	 * Test preload method.
	 *
	 * @return void
	 */
	public function test_preload(): void {
		$block  = new Block('test/block', $this->manager);
		$result = $block->preload(true);

		$this->assertSame($block, $result);
		$this->assertTrue($block->get('preload'));
	}

	/**
	 * Test add_script method.
	 *
	 * @return void
	 */
	public function test_add_script(): void {
		$block  = new Block('test/block', $this->manager);
		$script = array('handle' => 'test-script', 'src' => 'test.js');

		$result = $block->add_script($script);

		$this->assertSame($block, $result);
		$assets = $block->get('assets');
		$this->assertEquals(array($script), $assets['scripts']);
	}

	/**
	 * Test add_style method.
	 *
	 * @return void
	 */
	public function test_add_style(): void {
		$block = new Block('test/block', $this->manager);
		$style = array('handle' => 'test-style', 'src' => 'test.css');

		$result = $block->add_style($style);

		$this->assertSame($block, $result);
		$assets = $block->get('assets');
		$this->assertEquals(array($style), $assets['styles']);
	}

	/**
	 * Test adding multiple scripts and styles.
	 *
	 * @return void
	 */
	public function test_add_multiple_assets(): void {
		$block = new Block('test/block', $this->manager);

		$script1 = array('handle' => 'script-1', 'src' => 'script1.js');
		$script2 = array('handle' => 'script-2', 'src' => 'script2.js');
		$style1  = array('handle' => 'style-1', 'src' => 'style1.css');
		$style2  = array('handle' => 'style-2', 'src' => 'style2.css');

		$block
			->add_script($script1)
			->add_script($script2)
			->add_style($style1)
			->add_style($style2);

		$assets = $block->get('assets');
		$this->assertEquals(array($script1, $script2), $assets['scripts']);
		$this->assertEquals(array($style1, $style2), $assets['styles']);
	}

	// === BLOCK PROPERTY TESTS ===

	/**
	 * Test render_callback method.
	 *
	 * @return void
	 */
	public function test_render_callback(): void {
		$block  = new Block('test/block', $this->manager);
		$result = $block->render_callback('my_render_function');

		$this->assertSame($block, $result);
		$this->assertEquals('my_render_function', $block->get('render_callback'));
	}

	/**
	 * Test attributes method.
	 *
	 * @return void
	 */
	public function test_attributes(): void {
		$block      = new Block('test/block', $this->manager);
		$attributes = array(
			'title' => array('type' => 'string', 'default' => ''),
			'count' => array('type' => 'number', 'default' => 0)
		);

		$result = $block->attributes($attributes);

		$this->assertSame($block, $result);
		$this->assertEquals($attributes, $block->get('attributes'));
	}

	/**
	 * Test category method.
	 *
	 * @return void
	 */
	public function test_category(): void {
		$block  = new Block('test/block', $this->manager);
		$result = $block->category('layout');

		$this->assertSame($block, $result);
		$this->assertEquals('layout', $block->get('category'));
	}

	/**
	 * Test icon method.
	 *
	 * @return void
	 */
	public function test_icon(): void {
		$block  = new Block('test/block', $this->manager);
		$result = $block->icon('dashicons-admin-post');

		$this->assertSame($block, $result);
		$this->assertEquals('dashicons-admin-post', $block->get('icon'));
	}

	/**
	 * Test icon method with array.
	 *
	 * @return void
	 */
	public function test_icon_array(): void {
		$block = new Block('test/block', $this->manager);
		$icon  = array('src' => 'custom-icon.svg', 'background' => '#fff');

		$result = $block->icon($icon);

		$this->assertSame($block, $result);
		$this->assertEquals($icon, $block->get('icon'));
	}

	/**
	 * Test keywords method.
	 *
	 * @return void
	 */
	public function test_keywords(): void {
		$block    = new Block('test/block', $this->manager);
		$keywords = array('test', 'example', 'demo');

		$result = $block->keywords($keywords);

		$this->assertSame($block, $result);
		$this->assertEquals($keywords, $block->get('keywords'));
	}

	/**
	 * Test description method.
	 *
	 * @return void
	 */
	public function test_description(): void {
		$block  = new Block('test/block', $this->manager);
		$result = $block->description('A test block for demonstrations');

		$this->assertSame($block, $result);
		$this->assertEquals('A test block for demonstrations', $block->get('description'));
	}

	/**
	 * Test title method.
	 *
	 * @return void
	 */
	public function test_title(): void {
		$block  = new Block('test/block', $this->manager);
		$result = $block->title('Test Block');

		$this->assertSame($block, $result);
		$this->assertEquals('Test Block', $block->get('title'));
	}

	/**
	 * Test supports method.
	 *
	 * @return void
	 */
	public function test_supports(): void {
		$block    = new Block('test/block', $this->manager);
		$supports = array(
			'align'   => array('wide', 'full'),
			'color'   => array('background' => true, 'text' => true),
			'spacing' => array('padding' => true)
		);

		$result = $block->supports($supports);

		$this->assertSame($block, $result);
		$this->assertEquals($supports, $block->get('supports'));
	}

	// === MANAGER SYNCHRONIZATION TESTS ===

	/**
	 * Test that changes sync to manager.
	 *
	 * @return void
	 */
	public function test_manager_sync(): void {
		$this->manager->shouldReceive('update_block')
			->with('test/block', Mockery::type('array'))
			->times(3);

		$block = new Block('test/block', $this->manager);

		$block->title('Test Block');
		$block->category('layout');
		$block->condition('is_admin');

		// Expectations verified by Mockery
		$this->assertTrue(true);
	}

	/**
	 * Test register method calls manager and returns registration result.
	 *
	 * @return void
	 */
	public function test_register_calls_manager(): void {
		// Mock manager to return registration results
		$this->manager->shouldReceive('register')->once()->andReturn(array(
			'test/block' => false // Simulate failed registration in test
		));

		// Mock get_block_status call that happens internally
		$this->manager->shouldReceive('get_block_status')->once()->andReturn(array(
			'test/block' => array(
				'status'          => 'failed',
				'hook'            => 'init',
				'priority'        => 10,
				'error'           => 'Registration failed',
				'config'          => array('block_name' => 'test/block'),
				'has_assets'      => false,
				'has_condition'   => false,
				'preload_enabled' => false
			)
		));

		$block  = new Block('test/block', $this->manager);
		$result = $block->register();

		// Should return status array on failure (new API behavior)
		$this->assertIsArray($result);
		$this->assertEquals('failed', $result['status']);
		$this->assertFalse($result['registration_successful']);
		$this->assertFalse($result['registration_result']);
	}

	/**
	 * Test register method returns WP_Block_Type on successful registration.
	 *
	 * @return void
	 */
	public function test_register_returns_wp_block_type_on_success(): void {
		// Create a mock WP_Block_Type object
		$mockBlockType       = Mockery::mock('WP_Block_Type');
		$mockBlockType->name = 'test/block';

		// Mock manager to return successful registration
		$this->manager->shouldReceive('register')->once()->andReturn(array(
			'test/block' => $mockBlockType // Simulate successful registration
		));

		$block  = new Block('test/block', $this->manager);
		$result = $block->register();

		// Should return WP_Block_Type object directly on success (line 306)
		$this->assertSame($mockBlockType, $result);
		$this->assertEquals('test/block', $result->name);
	}

	/**
	 * Test register method when manager registration fails.
	 *
	 * @return void
	 */
	public function test_register_when_manager_fails(): void {
		// Mock manager to return empty results (registration failure)
		$this->manager->shouldReceive('register')
			->once()
			->andReturn(array());

		// Mock get_block_status to return failure status for all blocks
		$this->manager->shouldReceive('get_block_status')
			->withNoArgs()
			->once()
			->andReturn(array(
				'test/block' => array(
					'status'          => 'failed',
					'hook'            => 'init',
					'priority'        => 10,
					'error'           => 'Registration failed',
					'config'          => array('block_name' => 'test/block'),
					'has_assets'      => false,
					'has_condition'   => false,
					'preload_enabled' => false
				)
			));

		$block  = new Block('test/block', $this->manager);
		$result = $block->register();

		// Should return status array on failure (new API behavior)
		$this->assertIsArray($result);
		$this->assertEquals('failed', $result['status']);
		$this->assertFalse($result['registration_successful']);
	}

	// === COMPLEX CONFIGURATION TESTS ===

	/**
	 * Test complex block configuration.
	 *
	 * @return void
	 */
	public function test_complex_configuration(): void {
		$block = new Block('test/complex-block', $this->manager);

		$block
			->title('Complex Test Block')
			->description('A complex block with many features')
			->category('layout')
			->icon('dashicons-admin-post')
			->keywords(array('complex', 'test', 'demo'))
			->attributes(array(
				'title'   => array('type' => 'string', 'default' => ''),
				'content' => array('type' => 'string', 'default' => ''),
				'count'   => array('type' => 'number', 'default' => 0)
			))
			->supports(array(
				'align'   => array('wide', 'full'),
				'color'   => array('background' => true),
				'spacing' => array('padding' => true, 'margin' => true)
			))
			->render_callback('complex_render_function')
			->add_script(array(
				'handle' => 'complex-script',
				'src'    => 'complex.js',
				'deps'   => array('wp-blocks', 'wp-element')
			))
			->add_style(array(
				'handle' => 'complex-style',
				'src'    => 'complex.css'
			))
			->condition('is_admin')
			->hook('wp_loaded', 20)
			->preload(true);

		$config = $block->get_config();

		$this->assertEquals('test/complex-block', $config['block_name']);
		$this->assertEquals('Complex Test Block', $config['title']);
		$this->assertEquals('A complex block with many features', $config['description']);
		$this->assertEquals('layout', $config['category']);
		$this->assertEquals('dashicons-admin-post', $config['icon']);
		$this->assertEquals(array('complex', 'test', 'demo'), $config['keywords']);
		$this->assertArrayHasKey('attributes', $config);
		$this->assertArrayHasKey('supports', $config);
		$this->assertEquals('complex_render_function', $config['render_callback']);
		$this->assertArrayHasKey('assets', $config);
		$this->assertEquals('is_admin', $config['condition']);
		$this->assertEquals('wp_loaded', $config['hook']);
		$this->assertEquals(20, $config['priority']);
		$this->assertTrue($config['preload']);
	}

	/**
	 * Test fluent interface chaining.
	 *
	 * @return void
	 */
	public function test_fluent_chaining(): void {
		$block = new Block('test/block', $this->manager);

		$result = $block
			->title('Chained Block')
			->category('layout')
			->condition('is_admin')
			->preload(true);

		$this->assertSame($block, $result);
		$this->assertEquals('Chained Block', $block->get('title'));
		$this->assertEquals('layout', $block->get('category'));
		$this->assertEquals('is_admin', $block->get('condition'));
		$this->assertTrue($block->get('preload'));
	}
}
