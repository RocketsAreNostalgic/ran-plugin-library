<?php

namespace Ran\PluginLib\Tests\Unit\EnqueueAccessory;

use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\EnqueueAccessory\BlockRegistrar;
use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\Util\CollectingLogger;
use WP_Mock;
use Mockery;
use ReflectionClass;

/**
 * Test BlockRegistrar flattened API functionality
 */
class BlockRegistrarFlattenedApiTest extends PluginLibTestCase {
	private $block_registrar;
	private $config;
	private $logger;

	public function setUp(): void {
		parent::setUp();

		$this->logger = new CollectingLogger();

		$this->config = Mockery::mock(ConfigInterface::class);
		$this->config->shouldReceive('get_logger')->andReturn($this->logger);
		$this->config->shouldReceive('is_dev_environment')->andReturn(false);

		$this->block_registrar = new BlockRegistrar($this->config);
	}

	/**
	 * Test that flattened API properties are stored correctly
	 */
	public function test_flattened_api_properties_stored() {
		// Add block with flattened properties
		$this->block_registrar->add(array(
		    'block_name'      => 'test/flattened-block',
		    'title'           => 'Test Block',
		    'description'     => 'A test block',
		    'category'        => 'common',
		    'icon'            => 'admin-tools',
		    'keywords'        => array('test', 'example'),
		    'supports'        => array('align' => true),
		    'render_callback' => 'test_render_callback',
		    'custom_property' => 'custom_value'
		));

		// Use reflection to check internal state - blocks are stored in blocks
		$reflection      = new ReflectionClass($this->block_registrar);
		$blocks_property = $reflection->getProperty('blocks');
		$blocks_property->setAccessible(true);
		$blocks = $blocks_property->getValue($this->block_registrar);

		// Blocks default to 'init' hook with priority 10
		$this->assertArrayHasKey('init', $blocks);
		$this->assertArrayHasKey(10, $blocks['init']);
		$this->assertNotEmpty($blocks['init'][10]);

		// Find our block in the deferred blocks
		$block_def = null;
		foreach ($blocks['init'][10] as $block) {
			if ($block['block_name'] === 'test/flattened-block') {
				$block_def = $block;
				break;
			}
		}

		$this->assertNotNull($block_def, 'Block should be found in deferred blocks');

		// Verify all properties are stored
		$this->assertEquals('Test Block', $block_def['title']);
		$this->assertEquals('A test block', $block_def['description']);
		$this->assertEquals('common', $block_def['category']);
		$this->assertEquals('admin-tools', $block_def['icon']);
		$this->assertEquals(array('test', 'example'), $block_def['keywords']);
		$this->assertEquals(array('align' => true), $block_def['supports']);
		$this->assertEquals('test_render_callback', $block_def['render_callback']);
		$this->assertEquals('custom_value', $block_def['custom_property']);
	}

	/**
	 * Test that flattened API works with different hook and priority
	 */
	public function test_custom_hook_and_priority() {
		// Add block with custom hook and priority
		$this->block_registrar->add(array(
		    'block_name'      => 'test/custom-hook-block',
		    'title'           => 'Custom Hook Block',
		    'hook'            => 'wp_loaded',
		    'priority'        => 20,
		    'custom_property' => 'test_value'
		));

		// Use reflection to check internal state
		$reflection      = new ReflectionClass($this->block_registrar);
		$blocks_property = $reflection->getProperty('blocks');
		$blocks_property->setAccessible(true);
		$blocks = $blocks_property->getValue($this->block_registrar);

		// Block should be stored under wp_loaded hook with priority 20
		$this->assertArrayHasKey('wp_loaded', $blocks);
		$this->assertArrayHasKey(20, $blocks['wp_loaded']);

		$block_def = $blocks['wp_loaded'][20][0];

		// Verify properties are stored correctly
		$this->assertEquals('test/custom-hook-block', $block_def['block_name']);
		$this->assertEquals('Custom Hook Block', $block_def['title']);
		$this->assertEquals('wp_loaded', $block_def['hook']);
		$this->assertEquals(20, $block_def['priority']);
		$this->assertEquals('test_value', $block_def['custom_property']);
	}



	/**
	 * Test that multiple blocks can be registered with different configurations
	 */
	public function test_multiple_blocks_registration() {
		// Add multiple blocks with different configurations
		$this->block_registrar->add(array(
		    array(
		        'block_name'    => 'test/first-block',
		        'title'         => 'First Block',
		        'category'      => 'common',
		        'custom_prop_1' => 'value_1'
		    ),
		    array(
		        'block_name'    => 'test/second-block',
		        'title'         => 'Second Block',
		        'category'      => 'design',
		        'hook'          => 'wp_loaded',
		        'priority'      => 15,
		        'custom_prop_2' => 'value_2'
		    )
		));

		// Use reflection to check internal state
		$reflection      = new ReflectionClass($this->block_registrar);
		$blocks_property = $reflection->getProperty('blocks');
		$blocks_property->setAccessible(true);
		$blocks = $blocks_property->getValue($this->block_registrar);

		// First block should be in init hook, priority 10
		$first_block = $blocks['init'][10][0];
		$this->assertEquals('test/first-block', $first_block['block_name']);
		$this->assertEquals('First Block', $first_block['title']);
		$this->assertEquals('value_1', $first_block['custom_prop_1']);

		// Second block should be in wp_loaded hook, priority 15
		$second_block = $blocks['wp_loaded'][15][0];
		$this->assertEquals('test/second-block', $second_block['block_name']);
		$this->assertEquals('Second Block', $second_block['title']);
		$this->assertEquals('value_2', $second_block['custom_prop_2']);
	}

	/**
	 * Test that arbitrary custom properties work with flattened API
	 */
	public function test_arbitrary_custom_properties() {
		// Add block with arbitrary custom properties
		$this->block_registrar->add(array(
		    'block_name'         => 'test/custom-block',
		    'title'              => 'Custom Block',
		    'api_config'         => array('endpoint' => 'https://api.test.com'),
		    'display_options'    => array('theme' => 'dark'),
		    'my_custom_property' => 'custom_value',
		    'nested_config'      => array(
		        'feature_flags' => array('lazy_loading', 'caching'),
		        'version'       => '2.1.0'
		    )
		));

		// Use reflection to check internal state
		$reflection      = new ReflectionClass($this->block_registrar);
		$blocks_property = $reflection->getProperty('blocks');
		$blocks_property->setAccessible(true);
		$blocks = $blocks_property->getValue($this->block_registrar);

        // Find our block in the deferred blocks
		$block_def = $blocks['init'][10][0]; // First block in init hook, priority 10

		// Verify all custom properties are stored
		$this->assertEquals('Custom Block', $block_def['title']);
		$this->assertEquals(array('endpoint' => 'https://api.test.com'), $block_def['api_config']);
		$this->assertEquals(array('theme' => 'dark'), $block_def['display_options']);
		$this->assertEquals('custom_value', $block_def['my_custom_property']);
		$this->assertEquals(array('feature_flags' => array('lazy_loading', 'caching'), 'version' => '2.1.0'), $block_def['nested_config']);
	}
}
