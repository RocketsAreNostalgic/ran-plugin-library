<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\EnqueueAccessory;

use Mockery;
use WP_Mock;
use Ran\PluginLib\Util\ExpectLogTrait;
use Ran\PluginLib\Util\CollectingLogger;
use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\EnqueueAccessory\AssetType;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseAbstract;

/**
 * Concrete implementation of AssetEnqueueBaseAbstract for testing.
 *
 * This implementation includes the ScriptsEnqueueTrait to provide the actual
 * implementation of methods like _process_single_asset.
 */
class ConcreteAssetEnqueueBase extends AssetEnqueueBaseAbstract {
	// Include the trait that provides the _process_single_asset implementation
	use \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait;

	/**
	 * @inheritDoc
	 */
	public function load(): void {
		// Intentionally empty for testing.
	}

	/**
	 * Get the config instance.
	 *
	 * Required by AssetEnqueueBaseTrait.
	 *
	 * @return ConfigInterface
	 */
	protected function _get_config(): ConfigInterface {
		return $this->config;
	}

	/**
	 * Store the config instance.
	 *
	 * Must be protected to match parent class visibility.
	 */
	protected ConfigInterface $config;

	/**
	 * Constructor to store the config.
	 */
	public function __construct(ConfigInterface $config) {
		$this->config = $config;
		parent::__construct($config);
	}

	/**
	 * Get the asset URL for a given path.
	 *
	 * Required by AssetEnqueueBaseTrait.
	 *
	 * @param string $path The asset path.
	 * @return string The full asset URL.
	 */
	protected function _get_asset_url(string $path): string {
		return 'https://example.com/' . ltrim($path, '/');
	}
}

/**
 * Class AssetEnqueueBaseAbstractTest
 *
 * @package Ran\PluginLib\Tests\Unit\EnqueueAccessory
 *
 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseAbstract
 */
class AssetEnqueueBaseAbstractTest extends PluginLibTestCase {
	use ExpectLogTrait;

	/**
	 * @var ConcreteAssetEnqueueBase
	 */
	protected $instance;

	/**
	 * @var ConfigInterface|Mockery\MockInterface
	 */
	protected $config_mock;

	/**
	 * @var CollectingLogger|null
	 */
	protected ?CollectingLogger $logger_mock;

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		// Create a fresh logger for each test
		$this->logger_mock = new CollectingLogger();

		// Create and configure the config mock to return our logger
		$this->config_mock = Mockery::mock(ConfigInterface::class);
		$this->config_mock->shouldReceive('get_logger')
		    ->andReturn($this->logger_mock)
		    ->byDefault();

		// Create a new instance with our mocked config
		$this->instance = new ConcreteAssetEnqueueBase($this->config_mock);

		// Verify the logger is properly set up
		$this->assertSame($this->logger_mock, $this->instance->get_logger());
	}

	/**
	 * Clean up test environment.
	 */
	public function tearDown(): void {
		parent::tearDown();
		Mockery::close();
	}

	// ------------------------------------------------------------------------
	// __construct() Tests


	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseAbstract::__construct
	 */
	public function test_constructor_initializes_properly(): void {
		// Arrange
		$config = Mockery::mock(ConfigInterface::class);
		$config->shouldReceive('get_logger')->andReturn($this->logger_mock);

		// Act
		$instance = new ConcreteAssetEnqueueBase($config);

		// Assert
		$this->assertInstanceOf(AssetEnqueueBaseAbstract::class, $instance);
	}

	// ------------------------------------------------------------------------
	// get_logger() Tests
	// ------------------------------------------------------------------------


	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseAbstract::get_logger
	 */
	public function test_get_logger_returns_logger_from_config(): void {
		// Arrange
		$logger_mock = new CollectingLogger();
		$config      = Mockery::mock(ConfigInterface::class);
		$config->shouldReceive('get_logger')->once()->andReturn($logger_mock);
		$instance = new ConcreteAssetEnqueueBase($config);

		// Act
		$result = $instance->get_logger();

		// Assert
		$this->assertSame($logger_mock, $result);
	}

	// ------------------------------------------------------------------------
	// _process_single_asset() Tests
	// This method is ignored as it is a placeholder meant to be overrideen by traits. It is not directly used in this class.
	// Any tests here would fall through to the redefined method in the trait.
	// ------------------------------------------------------------------------


	// ------------------------------------------------------------------------
	// stage_assets() Tests
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseAbstract::stage_assets
	 */
	public function test_stage_assets_processes_assets(): void {
		// Arrange
		$asset_type       = AssetType::Script;
		$asset_definition = array(
		    'handle' => 'test-script',
		    'src'    => 'path/to/script.js',
		);

		// Set up the assets property using reflection
		$reflection = new \ReflectionClass($this->instance);
		$property   = $reflection->getProperty('assets');
		$property->setAccessible(true);
		// Using flattened array structure (no asset type nesting)
		$property->setValue($this->instance, array($asset_definition));

		// Mock the WordPress functions that would be called
		WP_Mock::userFunction('wp_register_script')->once()->andReturn(true);
		WP_Mock::userFunction('wp_script_is')->andReturn(false);
		WP_Mock::userFunction('wp_enqueue_script')->andReturn(null);
		WP_Mock::userFunction('wp_add_inline_script')->andReturn(null);
		WP_Mock::userFunction('wp_localize_script')->andReturn(null);
		WP_Mock::userFunction('wp_script_add_data')->andReturn(null);

		// Act
		$result = $this->instance->stage_assets($asset_type);

		// Assert
		$this->assertSame($this->instance, $result, 'Method should return $this for chaining');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseAbstract::stage_assets
	 */
	public function test_stage_assets_handles_deferred_assets(): void {
		// Arrange
		$asset_type = AssetType::Script;
		$hook_name  = 'test_hook';
		$priority   = 20;

		$asset_definition = array(
			'handle'   => 'deferred-script',
			'src'      => 'path/to/script.js',
			'hook'     => $hook_name,
			'priority' => $priority
		);

		// Set up the assets property using reflection
		$reflection = new \ReflectionClass($this->instance);
		$property   = $reflection->getProperty('assets');
		$property->setAccessible(true);
		// Using flattened array structure (no asset type nesting)
		$property->setValue($this->instance, array($asset_definition));

		// Mock WordPress add_action function
		WP_Mock::expectActionAdded($hook_name, \WP_Mock\Functions::type('callable'), $priority, 0);

		// Act
		$result = $this->instance->stage_assets($asset_type);

		// Assert
		$this->assertSame($this->instance, $result);

		// Check that the asset was moved to deferred_assets
		$deferred_property = $reflection->getProperty('deferred_assets');
		$deferred_property->setAccessible(true);
		$deferred_assets = $deferred_property->getValue($this->instance);

		// Using flattened array structure (no asset type nesting)
		$this->assertArrayHasKey($hook_name, $deferred_assets);
		$this->assertArrayHasKey($priority, $deferred_assets[$hook_name]);
		$this->assertCount(1, $deferred_assets[$hook_name][$priority]);
		$this->assertSame('deferred-script', $deferred_assets[$hook_name][$priority][0]['handle']);

		// Check that the original assets array is now empty
		$assets = $property->getValue($this->instance);
		$this->assertEmpty($assets);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseAbstract::stage_assets
	 */
	public function test_stage_assets_initializes_assets_array_if_not_set(): void {
		// Arrange
		$asset_type = AssetType::Script;

		// Set up the assets property using reflection to be empty
		$reflection = new \ReflectionClass($this->instance);
		$property   = $reflection->getProperty('assets');
		$property->setAccessible(true);
		$property->setValue($this->instance, array());

		// Act
		$result = $this->instance->stage_assets($asset_type);

		// Assert
		$this->assertSame($this->instance, $result);

		// Check that the assets array was initialized
		$assets = $property->getValue($this->instance);
		// With flattened structure, we just check that it's an array
		$this->assertIsArray($assets);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseAbstract::stage_assets
	 */
	public function test_stage_assets_handles_invalid_priority(): void {
		// Arrange
		$asset_type       = AssetType::Script;
		$hook_name        = 'test_hook';
		$invalid_priority = 'not-a-number';

		$asset_definition = array(
			'handle'   => 'deferred-script',
			'src'      => 'path/to/script.js',
			'hook'     => $hook_name,
			'priority' => $invalid_priority
		);

		// Set up the assets property using reflection
		$reflection = new \ReflectionClass($this->instance);
		$property   = $reflection->getProperty('assets');
		$property->setAccessible(true);
		// Using flattened array structure (no asset type nesting)
		$property->setValue($this->instance, array($asset_definition));

		// Mock WordPress add_action function - should use default priority 10
		WP_Mock::expectActionAdded($hook_name, \WP_Mock\Functions::type('callable'), 10, 0);

		// Act
		$result = $this->instance->stage_assets($asset_type);

		// Assert
		$this->assertSame($this->instance, $result);

		// Check that the asset was moved to deferred_assets with default priority
		$deferred_property = $reflection->getProperty('deferred_assets');
		$deferred_property->setAccessible(true);
		$deferred_assets = $deferred_property->getValue($this->instance);

		// Using flattened array structure (no asset type nesting)
		$this->assertArrayHasKey($hook_name, $deferred_assets);
		$this->assertArrayHasKey(10, $deferred_assets[$hook_name]); // Default priority
	}

	// ------------------------------------------------------------------------
	// _get_head_callbacks() Tests
	// @deprecated - functionality not required due to stage() and hook processing
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseAbstract::get_head_callbacks
	 */
	public function test_get_head_callbacks_returns_empty_array_when_no_callbacks(): void {
		// Act
		$result = $this->instance->get_head_callbacks('script');

		// Assert
		$this->assertIsArray($result);
		$this->assertEmpty($result);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseAbstract::get_head_callbacks
	 */
	public function test_get_head_callbacks_returns_existing_callbacks(): void {
		// Arrange - Set head_callbacks property using reflection
		$reflection = new \ReflectionClass($this->instance);
		$property   = $reflection->getProperty('head_callbacks');
		$property->setAccessible(true);
		$property->setValue($this->instance, array(function() {
		}));

		// Act
		$result = $this->instance->get_head_callbacks('script');

		// Assert
		$this->assertIsArray($result);
		$this->assertNotEmpty($result);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseAbstract::get_head_callbacks
	 */
	public function test_get_head_callbacks_returns_callbacks(): void {
		// Arrange - Set head_callbacks property using reflection
		$reflection = new \ReflectionClass($this->instance);
		$property   = $reflection->getProperty('head_callbacks');
		$property->setAccessible(true);
		$property->setValue($this->instance, array(function() {
		}));

		// Act
		$result = $this->instance->get_head_callbacks('script');

		// Assert
		$this->assertIsArray($result);
		$this->assertNotEmpty($result);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseAbstract::get_head_callbacks
	 */
	public function test_get_head_callbacks_returns_array_when_assets_have_data(): void {
		// Looking at the get_head_callbacks implementation, it expects $this->assets to be
		// an array of assets, not an array of asset types containing arrays of assets.
		// Let's set it up correctly:
		$reflection = new \ReflectionClass($this->instance);
		$property   = $reflection->getProperty('assets');
		$property->setAccessible(true);
		$property->setValue($this->instance, array(
			array(
				'handle' => 'test-script',
				'src'    => 'path/to/script.js',
				'data'   => array('key' => 'value')
			)
		));

		// Act
		$result = $this->instance->get_head_callbacks('script');

		// Assert
		$this->assertIsArray($result);
		$this->assertNotEmpty($result);
		$this->assertTrue($result[0]);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseAbstract::get_head_callbacks
	 */
	public function test_get_head_callbacks_skips_scripts_with_in_footer(): void {
		// Set up assets with a script that has data but is marked for footer
		$reflection = new \ReflectionClass($this->instance);
		$property   = $reflection->getProperty('assets');
		$property->setAccessible(true);
		$property->setValue($this->instance, array(
			array(
				'handle'    => 'test-script',
				'src'       => 'path/to/script.js',
				'data'      => array('key' => 'value'),
				'in_footer' => true // This should cause it to be skipped in head callbacks
			)
		));

		// Act
		$result = $this->instance->get_head_callbacks('script');

		// Assert - should be empty since the script is marked for footer
		$this->assertIsArray($result);
		$this->assertEmpty($result);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseAbstract::get_head_callbacks
	 */
	public function test_get_head_callbacks_ignores_footer_scripts(): void {
		// Arrange - Set assets property with data and in_footer flag
		$reflection = new \ReflectionClass($this->instance);
		$property   = $reflection->getProperty('assets');
		$property->setAccessible(true);
		$property->setValue($this->instance, array(
			'script' => array(
				array(
					'handle'    => 'test-script',
					'src'       => 'path/to/script.js',
					'data'      => array('key' => 'value'),
					'in_footer' => true
				)
			)
		));

		// Act
		$result = $this->instance->get_head_callbacks('script');

		// Assert
		$this->assertIsArray($result);
		$this->assertEmpty($result);
	}

	// ------------------------------------------------------------------------
	// _get_footer_callbacks() Tests
	// @deprecated - functionality not required due to stage() and hook processing
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseAbstract::get_footer_callbacks
	 */
	public function test_get_footer_callbacks_returns_empty_array_when_no_callbacks(): void {
		// Act
		$result = $this->instance->get_footer_callbacks('script');

		// Assert
		$this->assertIsArray($result);
		$this->assertEmpty($result);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseAbstract::get_footer_callbacks
	 */
	public function test_get_footer_callbacks_returns_existing_callbacks(): void {
		// Arrange - Set footer_callbacks property using reflection
		$reflection = new \ReflectionClass($this->instance);
		$property   = $reflection->getProperty('footer_callbacks');
		$property->setAccessible(true);
		$property->setValue($this->instance, array(function() {
		}));

		// Act
		$result = $this->instance->get_footer_callbacks('script');

		// Assert
		$this->assertIsArray($result);
		$this->assertNotEmpty($result);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseAbstract::get_footer_callbacks
	 */
	public function test_get_footer_callbacks_returns_callbacks(): void {
		// Arrange - Set footer_callbacks property using reflection
		$reflection = new \ReflectionClass($this->instance);
		$property   = $reflection->getProperty('footer_callbacks');
		$property->setAccessible(true);
		$property->setValue($this->instance, array(function() {
		}));

		// Act
		$result = $this->instance->get_footer_callbacks('script');

		// Assert
		$this->assertIsArray($result);
		$this->assertNotEmpty($result);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseAbstract::get_footer_callbacks
	 */
	public function test_get_footer_callbacks_returns_empty_for_non_script_asset_type(): void {
		// Act
		$result = $this->instance->get_footer_callbacks('style');

		// Assert
		$this->assertIsArray($result);
		$this->assertEmpty($result);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseAbstract::get_footer_callbacks
	 */
	public function test_get_footer_callbacks_returns_array_for_script_with_data_and_in_footer(): void {
		// Looking at the get_footer_callbacks implementation, it expects $this->assets to be
		// an array of assets, not an array of asset types containing arrays of assets.
		$reflection = new \ReflectionClass($this->instance);
		$property   = $reflection->getProperty('assets');
		$property->setAccessible(true);
		$property->setValue($this->instance, array(
			array(
				'handle'    => 'test-script',
				'src'       => 'path/to/script.js',
				'data'      => array('key' => 'value'),
				'in_footer' => true
			)
		));

		// Act
		$result = $this->instance->get_footer_callbacks('script');

		// Assert
		$this->assertIsArray($result);
		$this->assertNotEmpty($result);
		$this->assertTrue($result[0]);
	}
}
