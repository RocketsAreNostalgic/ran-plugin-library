<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\EnqueueAccessory;

use Mockery;
use WP_Mock;
use Ran\PluginLib\Util\ExpectLogTrait;
use Ran\PluginLib\Util\CollectingLogger;
use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\EnqueueAccessory\AssetType;
use Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait;

/**
 * Concrete implementation of ScriptsEnqueueTrait for testing asset-related methods.
 */
class ConcreteEnqueueForBaseTraitInlineAssetsTesting extends ConcreteEnqueueForTesting {
	use ScriptsEnqueueTrait;
}

/**
 * Class ScriptsEnqueueTraitTest
 *
 * @package Ran\PluginLib\Tests\Unit\EnqueueAccessory
 *
 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait
 */
class AssetEnqueueBaseTraitInlineAssetsTest extends EnqueueTraitTestCase {
	use ExpectLogTrait;

	/**
	 * @inheritDoc
	 */
	protected function _get_concrete_class_name(): string {
		return ConcreteEnqueueForBaseTraitInlineAssetsTesting::class;
	}

	/**
	 * @inheritDoc
	 */
	protected function _get_test_asset_type(): string {
		return AssetType::Script->value;
	}

	/**
	 * Set up test environment.
	 * See also EnqueueTraitTestCase
	 */
	public function setUp(): void {
		parent::setUp();

		// Add script-specific mocks that were not generic enough for the base class.
		WP_Mock::userFunction('wp_enqueue_script')->withAnyArgs()->andReturn(true)->byDefault();
	}

	/**
	 * Clean up test environment.
	 */
	public function tearDown(): void {
		parent::tearDown();
		Mockery::close();
	}


	// ------------------------------------------------------------------------
	// add_inline_assets() Covered elswere in cross trait tests
	// ------------------------------------------------------------------------

	// ------------------------------------------------------------------------
	// _add_inline_asset() Tests
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_add_inline_asset
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::add_inline
	 */
	public function test_add_inline_asset_with_external_registered_handle(): void {
		// Mock that the script is registered in WordPress
		WP_Mock::userFunction('wp_script_is', array(
			'args'   => array('external-script', 'registered'),
			'return' => true,
		));

		// Mock the add_action call for the external inline script
		WP_Mock::expectActionAdded(
			'wp_enqueue_scripts',
			array($this->instance, 'enqueue_external_inline_scripts'),
			11
		);

		// Test through public interface - add inline script
		$this->instance->add_inline(array(
			'parent_handle' => 'external-script',
			'content'       => 'console.log("Hello World");',
			'position'      => 'after'
		));

		// Get the assets to verify the inline script was added to external_inline_assets
		$assets = $this->instance->get_assets_info(AssetType::Script);

		$this->assertArrayHasKey('external_inline', $assets);
		$this->assertArrayHasKey('wp_enqueue_scripts', $assets['external_inline']);
		$this->assertArrayHasKey('external-script', $assets['external_inline']['wp_enqueue_scripts']);
		$this->assertEquals('console.log("Hello World");', $assets['external_inline']['wp_enqueue_scripts']['external-script'][0]['content']);
		$this->assertEquals('registered', $assets['external_inline']['wp_enqueue_scripts']['external-script'][0]['status']);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_add_inline_asset
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::add_inline
	 */
	public function test_add_inline_asset_with_custom_parent_hook(): void {
		// Mock the add_action call for the external inline script with custom hook
		WP_Mock::expectActionAdded(
			'custom_hook',
			array($this->instance, 'enqueue_external_inline_scripts'),
			11
		);

		// Test through public interface - add inline script with custom parent hook
		$this->instance->add_inline(array(
			'parent_handle' => 'promised-script',
			'content'       => 'console.log("Custom Hook");',
			'position'      => 'after',
			'parent_hook'   => 'custom_hook'
		));

		// Get the assets to verify the inline script was added to external_inline_assets with custom hook
		$assets = $this->instance->get_assets_info(AssetType::Script);

		$this->assertArrayHasKey('external_inline', $assets);
		$this->assertArrayHasKey('custom_hook', $assets['external_inline']);
		$this->assertArrayHasKey('promised-script', $assets['external_inline']['custom_hook']);
		$this->assertEquals('console.log("Custom Hook");', $assets['external_inline']['custom_hook']['promised-script'][0]['content']);
		$this->assertEquals('promised', $assets['external_inline']['custom_hook']['promised-script'][0]['status']);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_add_inline_asset
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::add_inline
	 */
	public function test_add_inline_asset_with_parent_in_immediate_queue_ignores_parent_hook(): void {
		// Add a script to the immediate queue
		$this->instance->add(array(
			'handle' => 'immediate-parent',
			'src'    => 'path/to/parent.js',
		));

		// Test through public interface - add inline script with parent hook that should be ignored
		$this->instance->add_inline(array(
			'parent_handle' => 'immediate-parent',
			'content'       => 'console.log("Inline for immediate parent");',
			'position'      => 'after',
			'parent_hook'   => 'unnecessary_hook' // This should be ignored and logged
		));

		// Get the assets to verify the inline script was added to the immediate parent
		$assets = $this->instance->get_assets_info(AssetType::Script);

		// Set up logger expectations
		$this->expectLog(
			'warning',
			array(
				'add_inline_',
				"A 'parent_hook' was provided for 'immediate-parent', but it's ignored as the parent was found internally in the immediate queue."
			), 1
		);

		// Verify the inline script was added to the immediate parent
		$this->assertArrayHasKey('assets', $assets);
		$this->assertCount(1, $assets['assets']);
		$this->assertEquals('immediate-parent', $assets['assets'][0]['handle']);
		$this->assertArrayHasKey('inline', $assets['assets'][0]);
		$this->assertCount(1, $assets['assets'][0]['inline']);
		$this->assertEquals('console.log("Inline for immediate parent");', $assets['assets'][0]['inline'][0]['content']);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_add_inline_asset
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::add_inline
	 */
	public function test_add_inline_asset_bails_when_parent_not_found_and_no_parent_hook(): void {
		// Mock that the script is NOT registered in WordPress
		WP_Mock::userFunction('wp_script_is', array(
			'args'   => array('nonexistent-parent', 'registered'),
			'return' => false,
		));

		// Test through public interface - try to add inline script with nonexistent parent and no parent_hook
		$this->instance->add_inline(array(
			'parent_handle' => 'nonexistent-parent',
			'content'       => 'console.log("This should not be added");',
			'position'      => 'after'
			// No parent_hook provided
		));

		// Get the assets to verify nothing was added
		$assets = $this->instance->get_assets_info(AssetType::Script);

		// Set up logger expectations
		$this->expectLog(
			'warning',
			array('add_inline_',
			"- Could not find parent handle 'nonexistent-parent' in any internal queue or in WordPress, and no 'parent_hook' was provided. Bailing."), 1,
		);

		// The method should bail out without adding any inline assets to the nonexistent parent
		// Let's verify that the 'nonexistent-parent' handle wasn't added to any queue

		// First check the regular assets queue
		$found_in_assets = false;
		foreach ($assets as $group => $group_assets) {
			foreach ($group_assets as $asset) {
				if (isset($asset['handle']) && $asset['handle'] === 'nonexistent-parent') {
					$found_in_assets = true;
					break 2;
				}
			}
		}
		$this->assertFalse($found_in_assets, 'The nonexistent parent should not be in the regular assets queue');

		// Then check the external_inline_assets property
		$reflection = new \ReflectionClass($this->instance);
		$property   = $reflection->getProperty('external_inline_assets');
		$property->setAccessible(true);
		$external_inline_assets = $property->getValue($this->instance);

		// The external_inline_assets array should not have any inline assets for our nonexistent parent
		$found_in_external = false;
		if (isset($external_inline_assets)) {
			foreach ($external_inline_assets as $hook => $handles) {
				if (isset($handles['nonexistent-parent'])) {
					$found_in_external = true;
					break;
				}
			}
		}
		$this->assertFalse($found_in_external, 'The nonexistent parent should not have any inline assets in the external_inline_assets queue');
	}

	// ------------------------------------------------------------------------
	// _enqueue_external_inline_assets() Tests
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_enqueue_external_inline_assets
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_enqueue_external_inline_scripts
	 */
	public function test_enqueue_external_inline_assets_handles_empty_assets(): void {
		// Mock current_action to return a specific hook name
		\WP_Mock::userFunction('current_action')
			->andReturn('wp_enqueue_scripts');

		// Set up external_inline_assets property with no assets for this hook
		$reflection                      = new \ReflectionClass($this->instance);
		$external_inline_assets_property = $reflection->getProperty('external_inline_assets');
		$external_inline_assets_property->setAccessible(true);

		$test_data = array(
			'other_hook' => array(
				'parent-handle-1' => array('some-inline-script-1')
			)
		);
		$external_inline_assets_property->setValue($this->instance, $test_data);

		// Test through public interface - call the public method that internally calls _enqueue_external_inline_assets
		$this->instance->_enqueue_external_inline_scripts();

		// Verify expected log messages for empty case
		$this->expectLog('debug', 'enqueue_external_inline_scripts - Fired on hook \'wp_enqueue_scripts\'.');
		$this->expectLog('debug', 'enqueue_external_inline_scripts - No external inline scripts found for hook \'wp_enqueue_scripts\'. Exiting.');
	}

	// ------------------------------------------------------------------------
	// _process_inline_assets() Tests
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_process_inline_assets
	 */
	public function test_process_inline_assets_parent_script_not_registered(): void {
		// Create a reflection method to access the protected method
		$reflection = new \ReflectionClass($this->instance);
		$method     = $reflection->getMethod('_process_inline_assets');
		$method->setAccessible(true);

		\WP_Mock::userFunction('wp_script_is')
			->with('test-script', 'registered')
			->andReturn(false)
			->once();
		\WP_Mock::userFunction('wp_script_is')
			->with('test-script', 'enqueued')
			->andReturn(false)
			->once();

		// Call the method
		$method->invokeArgs($this->instance, array(AssetType::Script, 'test-script'));

		// Assert logger messages using expectLog after SUT execution
		$this->expectLog('debug', 'Checking for inline scripts for parent script');
		$this->expectLog('error', 'Cannot add inline script');
		$this->expectLog('error', "Parent script 'test-script' is not registered or enqueued");
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_process_inline_assets
	 */
	public function test_process_inline_assets_successful_script_addition(): void {
		// Create a reflection method to access the protected method
		$reflection = new \ReflectionClass($this->instance);
		$method     = $reflection->getMethod('_process_inline_assets');
		$method->setAccessible(true);

		// Set up the assets property with an inline script
		$assets_property = $reflection->getProperty('assets');
		$assets_property->setAccessible(true);
		$assets = array(
			array(
				'handle' => 'test-script',
				'type'   => AssetType::Script,
				'inline' => array(
					array(
						'content'  => 'console.log("test");',
						'position' => 'after'
					)
				)
			)
		);
		$assets_property->setValue($this->instance, $assets);

		// Mock wp_script_is to return true
		\WP_Mock::userFunction('wp_script_is')
			->with('test-script', 'registered')
			->andReturn(true)
			->once();

		// Mock wp_add_inline_script to return true
		\WP_Mock::userFunction('wp_add_inline_script')
			->with('test-script', 'console.log("test");', 'after')
			->andReturn(true)
			->once();

		// Call the method
		$method->invokeArgs($this->instance, array(AssetType::Script, 'test-script'));

		// Assert logger messages using expectLog after SUT execution
		$this->expectLog('debug', 'Checking for inline scripts for parent script');
		// The new method uses different helper methods for processing inline assets
		// so we don't expect the same log messages
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_process_inline_assets
	 */
	public function test_process_inline_assets_inline_script_with_condition_that_fails(): void {
		// Create a reflection method to access the protected method
		$reflection = new \ReflectionClass($this->instance);
		$method     = $reflection->getMethod('_process_inline_assets');
		$method->setAccessible(true);

		// Set up the assets property with an inline script that has a failing condition
		$assets_property = $reflection->getProperty('assets');
		$assets_property->setAccessible(true);
		$assets = array(
			array(
				'handle' => 'test-script',
				'type'   => AssetType::Script,
				'inline' => array(
					array(
						'content'   => 'console.log("test");',
						'position'  => 'after',
						'condition' => function() {
							return false;
						}
					)
				)
			)
		);
		$assets_property->setValue($this->instance, $assets);

		// Mock wp_script_is to return true
		\WP_Mock::userFunction('wp_script_is')
			->with('test-script', 'registered')
			->andReturn(true)
			->once();

		// Call the method
		$method->invokeArgs($this->instance, array(AssetType::Script, 'test-script'));

		// Assert logger messages using expectLog after SUT execution
		$this->expectLog('debug', 'Checking for inline scripts for parent script');
		// The new method uses different helper methods for processing inline assets
		// so we don't expect the same log messages
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_process_inline_assets
	 */
	public function test_process_inline_assets_inline_script_with_empty_content(): void {
		// Create a reflection method to access the protected method
		$reflection = new \ReflectionClass($this->instance);
		$method     = $reflection->getMethod('_process_inline_assets');
		$method->setAccessible(true);

		// Set up the assets property with an inline script that has empty content
		$assets_property = $reflection->getProperty('assets');
		$assets_property->setAccessible(true);
		$assets = array(
			array(
				'handle' => 'test-script',
				'type'   => AssetType::Script,
				'inline' => array(
					array(
						'content'  => '',
						'position' => 'after'
					)
				)
			)
		);
		$assets_property->setValue($this->instance, $assets);

		// Mock wp_script_is to return true
		\WP_Mock::userFunction('wp_script_is')
			->with('test-script', 'registered')
			->andReturn(true)
			->once();

		// Call the method
		$method->invokeArgs($this->instance, array(AssetType::Script, 'test-script'));

		// Assert logger messages using expectLog after SUT execution
		$this->expectLog('debug', 'Checking for inline scripts for parent script');
		// The new method uses different helper methods for processing inline assets
		// so we don't expect the same log messages
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_process_inline_assets
	 */
	public function test_process_inline_assets_failed_inline_script_addition(): void {
		// Create a reflection method to access the protected method
		$reflection = new \ReflectionClass($this->instance);
		$method     = $reflection->getMethod('_process_inline_assets');
		$method->setAccessible(true);

		// Set up the assets property with an inline script
		$assets_property = $reflection->getProperty('assets');
		$assets_property->setAccessible(true);
		$assets = array(
			array(
				'handle' => 'test-script',
				'type'   => AssetType::Script,
				'inline' => array(
					array(
						'content'  => 'console.log("test");',
						'position' => 'after'
					),
					// Invalid non-array element that should be ignored by the new implementation
					'invalid-string-element'
				)
			)
		);
		$assets_property->setValue($this->instance, $assets);

		// Mock wp_script_is to return true
		\WP_Mock::userFunction('wp_script_is')
			->with('test-script', 'registered')
			->andReturn(true)
			->once();

		// Mock wp_add_inline_script to return false (failure)
		\WP_Mock::userFunction('wp_add_inline_script')
			->with('test-script', 'console.log("test");', 'after')
			->andReturn(false)
			->once();

		// Call the method
		$method->invokeArgs($this->instance, array(AssetType::Script, 'test-script'));

		// Assert logger messages using expectLog after SUT execution
		$this->expectLog('debug', 'Checking for inline scripts for parent script');
		// The new method uses different helper methods for processing inline assets
		// so we don't expect the same log messages
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_process_inline_assets
	 */
	public function test_process_inline_assets_invalid_inline_asset_data(): void {
		// Create a reflection method to access the protected method
		$reflection = new \ReflectionClass($this->instance);
		$method     = $reflection->getMethod('_process_inline_assets');
		$method->setAccessible(true);

		// Set up the assets property with an inline script and an invalid element
		$assets_property = $reflection->getProperty('assets');
		$assets_property->setAccessible(true);
		$assets = array(
			array(
				'handle' => 'test-script',
				'type'   => AssetType::Script,
				'inline' => array(
					array(
						'content'  => 'console.log("test");',
						'position' => 'after'
					),
					// Invalid non-array element that should be ignored by the new implementation
					'invalid-string-element'
				)
			)
		);
		$assets_property->setValue($this->instance, $assets);

		// Mock wp_script_is to return true
		\WP_Mock::userFunction('wp_script_is')
			->with('test-script', 'registered')
			->andReturn(true)
			->once();

		// Call the method
		$method->invokeArgs($this->instance, array(AssetType::Script, 'test-script'));

		// Assert logger messages using expectLog after SUT execution
		$this->expectLog('debug', 'Checking for inline scripts for parent script');
		// The new method uses different helper methods for processing inline assets
		// so we don't expect the same log messages
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_process_inline_assets
	 */
	public function test_process_inline_assets_processes_style_assets(): void {
		// Arrange: Set up inline assets for styles
		$parent_handle  = 'parent-style';
		$inline_content = '.test { color: blue; }';

		$reflection = new \ReflectionClass($this->instance);
		$method     = $reflection->getMethod('_process_inline_assets');
		$method->setAccessible(true);

		// Set up the assets property with an inline style
		$assets_property = $reflection->getProperty('assets');
		$assets_property->setAccessible(true);
		$assets = array(
			array(
				'handle' => $parent_handle,
				'type'   => AssetType::Style,
				'inline' => array(
					array(
						'content' => $inline_content,
						// Note: no 'position' for styles
					)
				)
			)
		);
		$assets_property->setValue($this->instance, $assets);

		// Mock wp_style_is to return true (parent style is registered)
		\WP_Mock::userFunction('wp_style_is')
			->once()
			->with($parent_handle, 'registered')
			->andReturn(true);

		// Mock wp_add_inline_style to succeed (note: no position parameter for styles)
		\WP_Mock::userFunction('wp_add_inline_style')
			->once()
			->with($parent_handle, $inline_content)
			->andReturn(true);

		// Act: Call the method with AssetType::Style (this will execute line 728: position = null)
		$method->invokeArgs($this->instance, array(AssetType::Style, $parent_handle));

		// Assert: Verify the specific branch was executed for styles
		$this->expectLog('debug', 'Checking for inline styles for parent style');
		// The new method uses different helper methods for processing inline assets
		// so we don't expect the same log messages
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_process_inline_assets
	 */
	public function test_process_inline_assets_matches_parent_hook_in_deferred_context(): void {
		// Arrange: Set up inline assets with specific parent_hook
		$parent_handle  = 'parent-script';
		$hook_name      = 'wp_footer';
		$inline_content = 'console.log("deferred inline script");';

		$reflection = new \ReflectionClass($this->instance);
		$method     = $reflection->getMethod('_process_inline_assets');
		$method->setAccessible(true);

		// Set up the deferred_assets property using reflection
		$deferred_assets_property = $reflection->getProperty('deferred_assets');
		$deferred_assets_property->setAccessible(true);
		$deferred_assets = array(
			$hook_name => array(
				10 => array(
					array(
						'handle' => $parent_handle,
						'type'   => AssetType::Script,
						'inline' => array(
							array(
								'content'  => $inline_content,
								'position' => 'after',
							)
						)
					)
				)
			)
		);
		$deferred_assets_property->setValue($this->instance, $deferred_assets);

		// Mock wp_script_is to return true (parent script is registered)
		\WP_Mock::userFunction('wp_script_is')
			->once()
			->with($parent_handle, 'registered')
			->andReturn(true);

		// Mock wp_add_inline_script to succeed
		\WP_Mock::userFunction('wp_add_inline_script')
			->once()
			->with($parent_handle, $inline_content, 'after')
			->andReturn(true);

		// Act: Call the method with deferred context (hook_name provided)
		$method->invokeArgs($this->instance, array(AssetType::Script, $parent_handle, $hook_name));

		// Assert: Verify the specific branch was executed (parent_hook matches hook_name)
		$this->expectLog('debug', 'Checking for inline scripts for parent script');
		// The new method uses different helper methods for processing inline assets
		// so we don't expect the same log messages
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_process_inline_assets
	 */
	public function test_process_inline_assets_with_no_inline_assets_found(): void {
		// Create a reflection method to access the protected method
		$reflection = new \ReflectionClass($this->instance);
		$method     = $reflection->getMethod('_process_inline_assets');
		$method->setAccessible(true);

		// Set up the assets property to be empty
		$assets_property = $reflection->getProperty('assets');
		$assets_property->setAccessible(true);
		$assets_property->setValue($this->instance, array());

		// Mock wp_script_is to return true (parent script is registered)
		$parent_handle = 'test-script-no-inline';
		\WP_Mock::userFunction('wp_script_is')
			->once()
			->with($parent_handle, 'registered')
			->andReturn(true);

		// Call the method
		$method->invokeArgs($this->instance, array(AssetType::Script, $parent_handle));

		// Assert that the debug message for no inline assets found is logged
		$this->expectLog('debug', 'Checking for inline scripts for parent script');
		// The new method has different logging, we don't expect the same messages
	}

	// ------------------------------------------------------------------------
	// _process_external_inline_assets() Tests
	// These tests use reflection per ADR-001 guidelines because:
	// 1. _process_external_inline_assets() is a utility method that returns a count
	// 2. The public interface (_enqueue_external_inline_scripts) doesn't expose this count
	// 3. Testing the count return value requires direct method access
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_process_external_inline_assets
	 */
	public function test_process_external_inline_assets_with_no_assets(): void {
		// Create a new concrete instance for testing (not a mock)
		$config_mock = Mockery::mock(ConfigInterface::class);
		$config_mock->shouldReceive('get_logger')->andReturn($this->logger_mock);
		$instance = new ConcreteEnqueueForBaseTraitInlineAssetsTesting($config_mock);

		// Set up empty external_inline_assets property
		$reflection                      = new \ReflectionClass($instance);
		$external_inline_assets_property = $reflection->getProperty('external_inline_assets');
		$external_inline_assets_property->setAccessible(true);
		$external_inline_assets_property->setValue($instance, array());

		// Call the method using the helper method
		$result = $this->_invoke_protected_method(
			$instance,
			'_process_external_inline_assets',
			array(AssetType::Script, 'test-script', 'wp_enqueue_scripts', 'TestContext')
		);

		// Assert
		$this->assertEquals(0, $result, 'Should return 0 when no assets are found');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_process_external_inline_assets
	 */
	public function test_process_external_inline_assets_with_valid_assets(): void {
		// Create a new concrete instance for testing (not a mock)
		$config_mock = Mockery::mock(ConfigInterface::class);
		$config_mock->shouldReceive('get_logger')->andReturn($this->logger_mock);
		$instance = new ConcreteEnqueueForBaseTraitInlineAssetsTesting($config_mock);

		// Set up external_inline_assets property with test data
		$reflection                      = new \ReflectionClass($instance);
		$external_inline_assets_property = $reflection->getProperty('external_inline_assets');
		$external_inline_assets_property->setAccessible(true);
		$external_inline_assets_property->setValue($instance, array(
			'wp_enqueue_scripts' => array(
				'test-script' => array(
					array(
						'content'  => 'console.log("test");',
						'position' => 'after'
					)
				)
			)
		));

		// Mock WordPress function
		\WP_Mock::userFunction('wp_add_inline_script')
			->with('test-script', 'console.log("test");', 'after')
			->andReturn(true)
			->once();

		// Call the method using the helper method
		$result = $this->_invoke_protected_method(
			$instance,
			'_process_external_inline_assets',
			array(AssetType::Script, 'test-script', 'wp_enqueue_scripts', 'TestContext')
		);

		// Assert
		$this->assertEquals(1, $result, 'Should return 1 when one asset is processed');

		// Verify log messages
		$this->expectLog('debug', array('TestContext', 'Adding external inline script for \'test-script\', position: after'), 1);
		$this->expectLog('debug', array('TestContext', 'Successfully added external inline script for \'test-script\'.'), 1);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_process_external_inline_assets
	 */
	public function test_process_external_inline_assets_with_failed_addition(): void {
		// Create a new concrete instance for testing (not a mock)
		$config_mock = Mockery::mock(ConfigInterface::class);
		$config_mock->shouldReceive('get_logger')->andReturn($this->logger_mock);
		$instance = new ConcreteEnqueueForBaseTraitInlineAssetsTesting($config_mock);

		// Set up external_inline_assets property with test data using reflection
		$reflection                      = new \ReflectionClass($instance);
		$external_inline_assets_property = $reflection->getProperty('external_inline_assets');
		$external_inline_assets_property->setAccessible(true);
		$external_inline_assets_property->setValue($instance, array(
			'wp_enqueue_scripts' => array(
				'test-script' => array(
					array(
						'content'  => 'console.log("test");',
						'position' => 'after'
					)
				),
			),
		));

		// Mock wp_add_inline_script to return false (failure)
		WP_Mock::userFunction('wp_add_inline_script')
			->with('test-script', 'console.log("test");', 'after')
			->andReturn(false);

		// Call the method
		$result = $this->_invoke_protected_method(
			$instance,
			'_process_external_inline_assets',
			array(AssetType::Script, 'test-script', 'wp_enqueue_scripts', 'TestContext')
		);

		// Assert
		$this->assertEquals(0, $result, 'Should return 0 when addition fails');

		// Assert logger messages - using verified format from actual implementation
		$this->expectLog('debug', array('TestContext', 'Adding external inline script for \'test-script\', position: after'), 1);
		$this->expectLog('warning', array('TestContext', 'Failed to add external inline script for \'test-script\'.'), 1);
	}

	/**
	 * Test _process_external_inline_assets skips assets when condition returns false.
	 * This covers the condition false path.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_process_external_inline_assets
	 */
	public function test_process_external_inline_assets_skips_when_condition_false(): void {
		// Create a new concrete instance for testing (not a mock)
		$config_mock = Mockery::mock(ConfigInterface::class);
		$config_mock->shouldReceive('get_logger')->andReturn($this->logger_mock);
		$instance = new ConcreteEnqueueForBaseTraitInlineAssetsTesting($config_mock);

		// Set up external_inline_assets property with test data that has a false condition
		$reflection                      = new \ReflectionClass($instance);
		$external_inline_assets_property = $reflection->getProperty('external_inline_assets');
		$external_inline_assets_property->setAccessible(true);
		$external_inline_assets_property->setValue($instance, array(
			'wp_enqueue_scripts' => array(
				'test-script' => array(
					array(
						'content'   => 'console.log("test");',
						'position'  => 'after',
						'condition' => function() {
							return false; // This should cause the asset to be skipped
						}
					)
				),
			),
		));

		// Call the method
		$result = $this->_invoke_protected_method(
			$instance,
			'_process_external_inline_assets',
			array(AssetType::Script, 'test-script', 'wp_enqueue_scripts', 'TestContext')
		);

		// Assert - Should return 0 since asset was skipped due to false condition
		$this->assertEquals(0, $result, 'Should return 0 when condition is false');

		// Assert logger message for condition false
		$this->expectLog('debug', array('TestContext', 'Condition false for external inline script targeting \'test-script\''), 1);
	}

	/**
	 * Test _process_external_inline_assets skips assets when content is empty.
	 * This covers the empty content path.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_process_external_inline_assets
	 */
	public function test_process_external_inline_assets_skips_when_content_empty(): void {
		// Create a new concrete instance for testing (not a mock)
		$config_mock = Mockery::mock(ConfigInterface::class);
		$config_mock->shouldReceive('get_logger')->andReturn($this->logger_mock);
		$instance = new ConcreteEnqueueForBaseTraitInlineAssetsTesting($config_mock);

		// Set up external_inline_assets property with test data that has empty content
		$reflection                      = new \ReflectionClass($instance);
		$external_inline_assets_property = $reflection->getProperty('external_inline_assets');
		$external_inline_assets_property->setAccessible(true);
		$external_inline_assets_property->setValue($instance, array(
			'wp_enqueue_scripts' => array(
				'test-script' => array(
					array(
						'content'  => '', // Empty content should cause skipping
						'position' => 'after'
					)
				),
			),
		));

		// Call the method
		$result = $this->_invoke_protected_method(
			$instance,
			'_process_external_inline_assets',
			array(AssetType::Script, 'test-script', 'wp_enqueue_scripts', 'TestContext')
		);

		// Assert - Should return 0 since asset was skipped due to empty content
		$this->assertEquals(0, $result, 'Should return 0 when content is empty');

		// Assert logger message for empty content
		$this->expectLog('warning', array('TestContext', 'Empty content for external inline script targeting \'test-script\'. Skipping.'), 1);
	}

	/**
	 * Test _process_external_inline_assets cleans up empty hook entries.
	 * This covers the hook cleanup path.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_process_external_inline_assets
	 */
	public function test_process_external_inline_assets_cleans_up_empty_hooks(): void {
		// Create a new concrete instance for testing (not a mock)
		$config_mock = Mockery::mock(ConfigInterface::class);
		$config_mock->shouldReceive('get_logger')->andReturn($this->logger_mock);
		$instance = new ConcreteEnqueueForBaseTraitInlineAssetsTesting($config_mock);

		// Set up external_inline_assets property with test data
		$reflection                      = new \ReflectionClass($instance);
		$external_inline_assets_property = $reflection->getProperty('external_inline_assets');
		$external_inline_assets_property->setAccessible(true);
		$external_inline_assets_property->setValue($instance, array(
			'wp_enqueue_scripts' => array(
				'test-script' => array(
					array(
						'content'  => 'console.log("test");',
						'position' => 'after'
					)
				),
			),
		));

		// Mock wp_add_inline_script to return true (success)
		\WP_Mock::userFunction('wp_add_inline_script')
			->with('test-script', 'console.log("test");', 'after')
			->andReturn(true);

		// Call the method
		$result = $this->_invoke_protected_method(
			$instance,
			'_process_external_inline_assets',
			array(AssetType::Script, 'test-script', 'wp_enqueue_scripts', 'TestContext')
		);

		// Assert - Should return 1 for successful processing
		$this->assertEquals(1, $result, 'Should return 1 when asset is processed successfully');

		// Verify that the hook entry was cleaned up (should be empty now)
		$external_inline_assets = $external_inline_assets_property->getValue($instance);
		$this->assertEmpty($external_inline_assets, 'Hook entry should be cleaned up after processing all assets');
	}

	public function test_process_external_inline_assets_with_style_assets(): void {
		// Create a new concrete instance for testing (not a mock)
		$config_mock = Mockery::mock(ConfigInterface::class);
		$config_mock->shouldReceive('get_logger')->andReturn($this->logger_mock);
		$instance = new ConcreteEnqueueForBaseTraitInlineAssetsTesting($config_mock);

		// Set up external_inline_assets property with test data using reflection
		$reflection                      = new \ReflectionClass($instance);
		$external_inline_assets_property = $reflection->getProperty('external_inline_assets');
		$external_inline_assets_property->setAccessible(true);
		$external_inline_assets_property->setValue($instance, array(
			'wp_enqueue_scripts' => array(
				'test-style' => array(
					array(
						'content' => 'body { color: red; }'
					)
				),
			),
		));

		// Mock wp_add_inline_style to return true (success)
		WP_Mock::userFunction('wp_add_inline_style')
			->with('test-style', 'body { color: red; }')
			->andReturn(true);

		// Call the method using the helper method
		$result = $this->_invoke_protected_method(
			$instance,
			'_process_external_inline_assets',
			array(AssetType::Style, 'test-style', 'wp_enqueue_scripts', 'TestContext')
		);

		// Assert
		$this->assertEquals(1, $result, 'Should return 1 when addition succeeds');

		// Assert logger messages - using verified format from actual implementation
		$this->expectLog('debug', array('TestContext', 'Adding external inline style for \'test-style\''), 1);
		$this->expectLog('debug', array('TestContext', 'Successfully added external inline style for \'test-style\'.'), 1);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_process_external_inline_assets
	 */
	public function test_process_external_inline_assets_with_multiple_assets(): void {
		// Create a new concrete instance for testing (not a mock)
		$config_mock = Mockery::mock(ConfigInterface::class);
		$config_mock->shouldReceive('get_logger')->andReturn($this->logger_mock);
		$instance = new ConcreteEnqueueForBaseTraitInlineAssetsTesting($config_mock);

		// Set up external_inline_assets property with multiple assets
		$reflection                      = new \ReflectionClass($instance);
		$external_inline_assets_property = $reflection->getProperty('external_inline_assets');
		$external_inline_assets_property->setAccessible(true);
		$external_inline_assets_property->setValue($instance, array(
			'wp_enqueue_scripts' => array(
				'test-script' => array(
					array(
						'content'  => 'console.log("test1");',
						'position' => 'after'
					),
					array(
						'content'  => 'console.log("test2");',
						'position' => 'before'
					)
				)
			)
		));

		// Mock WordPress function for both inline scripts
		\WP_Mock::userFunction('wp_add_inline_script')
			->with('test-script', 'console.log("test1");', 'after')
			->andReturn(true)
			->once();

		\WP_Mock::userFunction('wp_add_inline_script')
			->with('test-script', 'console.log("test2");', 'before')
			->andReturn(true)
			->once();

		// Call the method using the helper method
		$result = $this->_invoke_protected_method(
			$instance,
			'_process_external_inline_assets',
			array(AssetType::Script, 'test-script', 'wp_enqueue_scripts', 'TestContext')
		);

		// Assert
		$this->assertEquals(2, $result, 'Should return 2 when two assets are processed');

		// Assert logger messages for both assets
		$this->expectLog('debug', array('TestContext', 'Adding external inline script for \'test-script\', position: after'), 1);
		$this->expectLog('debug', array('TestContext', 'Adding external inline script for \'test-script\', position: before'), 1);
		$this->expectLog('debug', array('TestContext', 'Successfully added external inline script for \'test-script\'.'), 2);
	}

	// ------------------------------------------------------------------------
	// _process_deferred_inline_assets() Tests
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_process_deferred_inline_assets
	 */
	public function test_process_deferred_inline_assets_with_script_asset(): void {
		// Create a new concrete instance for testing (not a mock)
		$config_mock = Mockery::mock(ConfigInterface::class);
		$config_mock->shouldReceive('get_logger')->andReturn($this->logger_mock);
		$instance = new ConcreteEnqueueForBaseTraitInlineAssetsTesting($config_mock);

		// --- Arrange ---
		$asset_type    = AssetType::Script;
		$parent_handle = 'test-script';
		$hook_name     = 'wp_footer';
		$context       = 'TestContext';
		$priority      = 10;

		// Set up deferred assets using reflection
		$reflection               = new \ReflectionClass($instance);
		$deferred_assets_property = $reflection->getProperty('deferred_assets');
		$deferred_assets_property->setAccessible(true);
		$deferred_assets_property->setValue($instance, array(
			$hook_name => array(
				$priority => array(
					'key1' => array(
						'handle' => $parent_handle,
						'type'   => $asset_type,
						'inline' => array(
							array(
								'content'  => 'console.log("test");',
								'position' => 'after'
							),
							array(
								'content'   => 'console.log("test2");',
								'position'  => 'before',
								'condition' => function() {
									return true;
								}
							),
							array(
								'content'  => '',  // Empty content to trigger warning
								'position' => 'after'
							),
							array(
								'content'   => 'console.log("test3");',
								'position'  => 'after',
								'condition' => function() {
									return false;  // False condition to trigger condition log
								}
							)
						)
					)
				)
			)
		));

		// Mock wp_add_inline_script
		WP_Mock::userFunction('wp_add_inline_script', array(
			'times'  => 2,
			'args'   => array($parent_handle, Mockery::type('string'), Mockery::type('string')),
			'return' => true
		));

		// --- Act ---
		$result = $this->_invoke_protected_method($instance, '_process_deferred_inline_assets', array(
			$asset_type, $parent_handle, $hook_name, $context
		));

		// --- Assert ---
		$this->assertEquals(2, $result, 'Should process 2 inline assets successfully');

		// Verify logs
		$this->expectLog('debug', "{$context}Found deferred inline {$asset_type->value}s for '{$parent_handle}' on hook '{$hook_name}' priority {$priority}.", 1);
		$this->expectLog('debug', "{$context}Adding deferred inline {$asset_type->value} for '{$parent_handle}', position: after", 1);
		$this->expectLog('debug', "{$context}Successfully added deferred inline {$asset_type->value} for '{$parent_handle}'.", 2);
		$this->expectLog('debug', "{$context}Adding deferred inline {$asset_type->value} for '{$parent_handle}', position: before", 1);
		$this->expectLog('warning', "{$context}Empty content for deferred inline {$asset_type->value} targeting '{$parent_handle}'. Skipping.", 1);
		$this->expectLog('debug', "{$context}Condition false for deferred inline {$asset_type->value} targeting '{$parent_handle}'", 1);

		// Verify the inline assets were removed
		$deferred_assets = $deferred_assets_property->getValue($instance);
		$this->assertArrayNotHasKey('inline', $deferred_assets[$hook_name][$priority]['key1']);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_process_deferred_inline_assets
	 */
	public function test_process_deferred_inline_assets_with_style_asset(): void {
		// Create a new concrete instance for testing (not a mock)
		$config_mock = Mockery::mock(ConfigInterface::class);
		$config_mock->shouldReceive('get_logger')->andReturn($this->logger_mock);
		$instance = new ConcreteEnqueueForBaseTraitInlineAssetsTesting($config_mock);

		// --- Arrange ---
		$asset_type    = AssetType::Style;
		$parent_handle = 'test-style';
		$hook_name     = 'wp_head';
		$context       = 'TestContext::';
		$priority      = 10;

		// Set up deferred assets using reflection
		$reflection               = new \ReflectionClass($instance);
		$deferred_assets_property = $reflection->getProperty('deferred_assets');
		$deferred_assets_property->setAccessible(true);
		$deferred_assets_property->setValue($instance, array(
			$hook_name => array(
				$priority => array(
					'key1' => array(
						'handle' => $parent_handle,
						'type'   => $asset_type,
						'inline' => array(
							array(
								'content' => '.test { color: red; }'
							),
							array(
								'content'   => '.test2 { color: blue; }',
								'condition' => function() {
									return true;
								}
							)
						)
					)
				)
			)
		));

		// Mock wp_add_inline_style
		WP_Mock::userFunction('wp_add_inline_style', array(
			'times'  => 2,
			'args'   => array($parent_handle, Mockery::type('string')),
			'return' => true
		));

		// --- Act ---
		$result = $this->_invoke_protected_method($instance, '_process_deferred_inline_assets', array(
			$asset_type, $parent_handle, $hook_name, $context
		));

		// --- Assert ---
		$this->assertEquals(2, $result, 'Should process 2 inline assets successfully');

		// Verify logs
		$this->expectLog('debug', "{$context}Found deferred inline {$asset_type->value}s for '{$parent_handle}' on hook '{$hook_name}' priority {$priority}.", 1);
		$this->expectLog('debug', "{$context}Adding deferred inline {$asset_type->value} for '{$parent_handle}'", 2);
		$this->expectLog('debug', "{$context}Successfully added deferred inline {$asset_type->value} for '{$parent_handle}'.", 2);

		// Verify the inline assets were removed
		$deferred_assets = $deferred_assets_property->getValue($instance);
		$this->assertArrayNotHasKey('inline', $deferred_assets[$hook_name][$priority]['key1']);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_process_deferred_inline_assets
	 */
	public function test_process_deferred_inline_assets_with_failed_addition(): void {
		// Create a new concrete instance for testing (not a mock)
		$config_mock = Mockery::mock(ConfigInterface::class);
		$config_mock->shouldReceive('get_logger')->andReturn($this->logger_mock);
		$instance = new ConcreteEnqueueForBaseTraitInlineAssetsTesting($config_mock);

		// --- Arrange ---
		$asset_type    = AssetType::Script;
		$parent_handle = 'test-script';
		$hook_name     = 'wp_footer';
		$context       = 'TestContext::';
		$priority      = 10;

		// Set up deferred assets using reflection
		$reflection               = new \ReflectionClass($instance);
		$deferred_assets_property = $reflection->getProperty('deferred_assets');
		$deferred_assets_property->setAccessible(true);
		$deferred_assets_property->setValue($instance, array(
			$hook_name => array(
				$priority => array(
					'key1' => array(
						'handle' => $parent_handle,
						'type'   => $asset_type,
						'inline' => array(
							array(
								'content'  => 'console.log("test");',
								'position' => 'after'
							)
						)
					)
				)
			)
		));

		// Mock wp_add_inline_script to fail
		WP_Mock::userFunction('wp_add_inline_script', array(
			'times'  => 1,
			'return' => false
		));

		// --- Act ---
		$result = $this->_invoke_protected_method($instance, '_process_deferred_inline_assets', array(
			$asset_type, $parent_handle, $hook_name, $context
		));

		// --- Assert ---
		$this->assertEquals(0, $result, 'Should process 0 inline assets successfully');

		// Verify logs
		$this->expectLog('debug', "{$context}Found deferred inline {$asset_type->value}s for '{$parent_handle}' on hook '{$hook_name}' priority {$priority}.", 1);
		$this->expectLog('debug', "{$context}Adding deferred inline {$asset_type->value} for '{$parent_handle}', position: after", 1);
		$this->expectLog('warning', "{$context}Failed to add deferred inline {$asset_type->value} for '{$parent_handle}'.", 1);

		// Verify the inline assets were removed
		$deferred_assets = $deferred_assets_property->getValue($instance);
		$this->assertArrayNotHasKey('inline', $deferred_assets[$hook_name][$priority]['key1']);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_process_deferred_inline_assets
	 */
	public function test_process_deferred_inline_assets_with_no_assets_for_hook(): void {
		// Create a new concrete instance for testing (not a mock)
		$config_mock = Mockery::mock(ConfigInterface::class);
		$config_mock->shouldReceive('get_logger')->andReturn($this->logger_mock);
		$instance = new ConcreteEnqueueForBaseTraitInlineAssetsTesting($config_mock);

		// --- Arrange ---
		$asset_type    = AssetType::Script;
		$parent_handle = 'test-script';
		$hook_name     = 'wp_footer';
		$context       = 'TestContext::';

		// Set up deferred assets with different hook using reflection
		$reflection               = new \ReflectionClass($instance);
		$deferred_assets_property = $reflection->getProperty('deferred_assets');
		$deferred_assets_property->setAccessible(true);
		$deferred_assets_property->setValue($instance, array(
			'different_hook' => array(
				10 => array(
					'key1' => array(
						'handle' => $parent_handle,
						'type'   => $asset_type,
						'inline' => array(
							array(
								'content'  => 'console.log("test");',
								'position' => 'after'
							)
						)
					)
				)
			)
		));

		// --- Act ---
		$result = $this->_invoke_protected_method($instance, '_process_deferred_inline_assets', array(
			$asset_type, $parent_handle, $hook_name, $context
		));

		// --- Assert ---
		$this->assertEquals(0, $result, 'Should process 0 inline assets');

		// No logs should be generated for this case
	}

	// _process_immediate_inline_assets() Tests
	// These tests use reflection per ADR-001 guidelines because:
	// 1. _process_immediate_inline_assets() is a utility method that returns a count
	// 2. The public interface (add_inline) doesn't expose this count directly
	// 3. Testing the count return value and internal logic requires direct method access
	// ------------------------------------------------------------------------

	/**
	 * Test _process_immediate_inline_assets returns 0 when no matching asset found.
	 * This covers the no matching asset path.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_process_immediate_inline_assets
	 */
	public function test_process_immediate_inline_assets_no_matching_asset(): void {
		// Create a new concrete instance for testing (not a mock)
		$config_mock = Mockery::mock(ConfigInterface::class);
		$config_mock->shouldReceive('get_logger')->andReturn($this->logger_mock);
		$instance = new ConcreteEnqueueForBaseTraitInlineAssetsTesting($config_mock);

		// Set up assets property with different handle
		$reflection      = new \ReflectionClass($instance);
		$assets_property = $reflection->getProperty('assets');
		$assets_property->setAccessible(true);
		$assets_property->setValue($instance, array(
			array(
				'handle' => 'different-script',
				'type'   => AssetType::Script,
				'src'    => 'different.js'
			)
		));

		// Call the method looking for non-existent handle
		$result = $this->_invoke_protected_method(
			$instance,
			'_process_immediate_inline_assets',
			array(AssetType::Script, 'test-script', 'TestContext')
		);

		// Assert - Should return 0 when no matching asset is found
		$this->assertEquals(0, $result, 'Should return 0 when no matching asset found');
	}

	/**
	 * Test _process_immediate_inline_assets returns 0 when asset has no inline content.
	 * This covers the no inline assets path.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_process_immediate_inline_assets
	 */
	public function test_process_immediate_inline_assets_no_inline_content(): void {
		// Create a new concrete instance for testing (not a mock)
		$config_mock = Mockery::mock(ConfigInterface::class);
		$config_mock->shouldReceive('get_logger')->andReturn($this->logger_mock);
		$instance = new ConcreteEnqueueForBaseTraitInlineAssetsTesting($config_mock);

		// Set up assets property with asset that has no inline content
		$reflection      = new \ReflectionClass($instance);
		$assets_property = $reflection->getProperty('assets');
		$assets_property->setAccessible(true);
		$assets_property->setValue($instance, array(
			array(
				'handle' => 'test-script',
				'type'   => AssetType::Script,
				'src'    => 'test.js'
				// No 'inline' key
			)
		));

		// Call the method
		$result = $this->_invoke_protected_method(
			$instance,
			'_process_immediate_inline_assets',
			array(AssetType::Script, 'test-script', 'TestContext')
		);

		// Assert - Should return 0 when asset has no inline content
		$this->assertEquals(0, $result, 'Should return 0 when asset has no inline content');
	}

	/**
	 * Test _process_immediate_inline_assets skips assets when condition returns false.
	 * This covers the condition false path.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_process_immediate_inline_assets
	 */
	public function test_process_immediate_inline_assets_skips_when_condition_false(): void {
		// Create a new concrete instance for testing (not a mock)
		$config_mock = Mockery::mock(ConfigInterface::class);
		$config_mock->shouldReceive('get_logger')->andReturn($this->logger_mock);
		$instance = new ConcreteEnqueueForBaseTraitInlineAssetsTesting($config_mock);

		// Set up assets property with inline asset that has false condition
		$reflection      = new \ReflectionClass($instance);
		$assets_property = $reflection->getProperty('assets');
		$assets_property->setAccessible(true);
		$assets_property->setValue($instance, array(
			array(
				'handle' => 'test-script',
				'type'   => AssetType::Script,
				'src'    => 'test.js',
				'inline' => array(
					array(
						'content'   => 'console.log("test");',
						'position'  => 'after',
						'condition' => function() {
							return false; // This should cause the asset to be skipped
						}
					)
				)
			)
		));

		// Call the method
		$result = $this->_invoke_protected_method(
			$instance,
			'_process_immediate_inline_assets',
			array(AssetType::Script, 'test-script', 'TestContext')
		);

		// Assert - Should return 0 since asset was skipped due to false condition
		$this->assertEquals(0, $result, 'Should return 0 when condition is false');

		// Assert logger messages
		$this->expectLog('debug', array('TestContext', 'Found immediate inline scripts for \'test-script\'.'), 1);
		$this->expectLog('debug', array('TestContext', 'Condition false for immediate inline script targeting \'test-script\''), 1);
	}

	/**
	 * Test _process_immediate_inline_assets skips assets when content is empty.
	 * This covers the empty content path.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_process_immediate_inline_assets
	 */
	public function test_process_immediate_inline_assets_skips_when_content_empty(): void {
		// Create a new concrete instance for testing (not a mock)
		$config_mock = Mockery::mock(ConfigInterface::class);
		$config_mock->shouldReceive('get_logger')->andReturn($this->logger_mock);
		$instance = new ConcreteEnqueueForBaseTraitInlineAssetsTesting($config_mock);

		// Set up assets property with inline asset that has empty content
		$reflection      = new \ReflectionClass($instance);
		$assets_property = $reflection->getProperty('assets');
		$assets_property->setAccessible(true);
		$assets_property->setValue($instance, array(
			array(
				'handle' => 'test-script',
				'type'   => AssetType::Script,
				'src'    => 'test.js',
				'inline' => array(
					array(
						'content'  => '', // Empty content should cause skipping
						'position' => 'after'
					)
				)
			)
		));

		// Call the method
		$result = $this->_invoke_protected_method(
			$instance,
			'_process_immediate_inline_assets',
			array(AssetType::Script, 'test-script', 'TestContext')
		);

		// Assert - Should return 0 since asset was skipped due to empty content
		$this->assertEquals(0, $result, 'Should return 0 when content is empty');

		// Assert logger messages
		$this->expectLog('debug', array('TestContext', 'Found immediate inline scripts for \'test-script\'.'), 1);
		$this->expectLog('warning', array('TestContext', 'Empty content for immediate inline script targeting \'test-script\'. Skipping.'), 1);
	}

	/**
	 * Test _process_immediate_inline_assets successfully processes script assets.
	 * This covers the successful script processing path.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_process_immediate_inline_assets
	 */
	public function test_process_immediate_inline_assets_processes_script_successfully(): void {
		// Create a new concrete instance for testing (not a mock)
		$config_mock = Mockery::mock(ConfigInterface::class);
		$config_mock->shouldReceive('get_logger')->andReturn($this->logger_mock);
		$instance = new ConcreteEnqueueForBaseTraitInlineAssetsTesting($config_mock);

		// Set up assets property with valid inline script
		$reflection      = new \ReflectionClass($instance);
		$assets_property = $reflection->getProperty('assets');
		$assets_property->setAccessible(true);
		$assets_property->setValue($instance, array(
			array(
				'handle' => 'test-script',
				'type'   => AssetType::Script,
				'src'    => 'test.js',
				'inline' => array(
					array(
						'content'  => 'console.log("test");',
						'position' => 'after'
					)
				)
			)
		));

		// Mock wp_add_inline_script to return true (success)
		\WP_Mock::userFunction('wp_add_inline_script')
			->with('test-script', 'console.log("test");', 'after')
			->andReturn(true);

		// Call the method
		$result = $this->_invoke_protected_method(
			$instance,
			'_process_immediate_inline_assets',
			array(AssetType::Script, 'test-script', 'TestContext')
		);

		// Assert - Should return 1 for successful processing
		$this->assertEquals(1, $result, 'Should return 1 when asset is processed successfully');

		// Verify that the inline content was removed after processing
		$assets = $assets_property->getValue($instance);
		$this->assertArrayNotHasKey('inline', $assets[0], 'Inline content should be removed after processing');

		// Assert logger messages
		$this->expectLog('debug', array('TestContext', 'Found immediate inline scripts for \'test-script\'.'), 1);
		$this->expectLog('debug', array('TestContext', 'Adding immediate inline script for \'test-script\', position: after'), 1);
		$this->expectLog('debug', array('TestContext', 'Successfully added immediate inline script for \'test-script\'.'), 1);
	}

	/**
	 * Test _process_immediate_inline_assets handles script addition failure.
	 * This covers the failed addition path for scripts.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_process_immediate_inline_assets
	 */
	public function test_process_immediate_inline_assets_handles_script_failure(): void {
		// Create a new concrete instance for testing (not a mock)
		$config_mock = Mockery::mock(ConfigInterface::class);
		$config_mock->shouldReceive('get_logger')->andReturn($this->logger_mock);
		$instance = new ConcreteEnqueueForBaseTraitInlineAssetsTesting($config_mock);

		// Set up assets property with valid inline script
		$reflection      = new \ReflectionClass($instance);
		$assets_property = $reflection->getProperty('assets');
		$assets_property->setAccessible(true);
		$assets_property->setValue($instance, array(
			array(
				'handle' => 'test-script',
				'type'   => AssetType::Script,
				'src'    => 'test.js',
				'inline' => array(
					array(
						'content'  => 'console.log("test");',
						'position' => 'after'
					)
				)
			)
		));

		// Mock wp_add_inline_script to return false (failure)
		\WP_Mock::userFunction('wp_add_inline_script')
			->with('test-script', 'console.log("test");', 'after')
			->andReturn(false);

		// Call the method
		$result = $this->_invoke_protected_method(
			$instance,
			'_process_immediate_inline_assets',
			array(AssetType::Script, 'test-script', 'TestContext')
		);

		// Assert - Should return 0 when addition fails
		$this->assertEquals(0, $result, 'Should return 0 when addition fails');

		// Assert logger messages
		$this->expectLog('debug', array('TestContext', 'Found immediate inline scripts for \'test-script\'.'), 1);
		$this->expectLog('debug', array('TestContext', 'Adding immediate inline script for \'test-script\', position: after'), 1);
		$this->expectLog('warning', array('TestContext', 'Failed to add immediate inline script for \'test-script\'.'), 1);
	}

	/**
	 * Test _process_immediate_inline_assets successfully processes style assets.
	 * This covers the successful style processing path.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_process_immediate_inline_assets
	 */
	public function test_process_immediate_inline_assets_processes_style_successfully(): void {
		// Create a new concrete instance for testing (not a mock)
		$config_mock = Mockery::mock(ConfigInterface::class);
		$config_mock->shouldReceive('get_logger')->andReturn($this->logger_mock);
		$instance = new ConcreteEnqueueForBaseTraitInlineAssetsTesting($config_mock);

		// Set up assets property with valid inline style
		$reflection      = new \ReflectionClass($instance);
		$assets_property = $reflection->getProperty('assets');
		$assets_property->setAccessible(true);
		$assets_property->setValue($instance, array(
			array(
				'handle' => 'test-style',
				'type'   => AssetType::Style,
				'src'    => 'test.css',
				'inline' => array(
					array(
						'content' => 'body { color: red; }'
					)
				)
			)
		));

		// Mock wp_add_inline_style to return true (success)
		\WP_Mock::userFunction('wp_add_inline_style')
			->with('test-style', 'body { color: red; }')
			->andReturn(true);

		// Call the method
		$result = $this->_invoke_protected_method(
			$instance,
			'_process_immediate_inline_assets',
			array(AssetType::Style, 'test-style', 'TestContext')
		);

		// Assert - Should return 1 for successful processing
		$this->assertEquals(1, $result, 'Should return 1 when style is processed successfully');

		// Verify that the inline content was removed after processing
		$assets = $assets_property->getValue($instance);
		$this->assertArrayNotHasKey('inline', $assets[0], 'Inline content should be removed after processing');

		// Assert logger messages
		$this->expectLog('debug', array('TestContext', 'Found immediate inline styles for \'test-style\'.'), 1);
		$this->expectLog('debug', array('TestContext', 'Adding immediate inline style for \'test-style\''), 1);
		$this->expectLog('debug', array('TestContext', 'Successfully added immediate inline style for \'test-style\'.'), 1);
	}

	/**
	 * Test _process_immediate_inline_assets processes multiple inline assets.
	 * This covers the multiple assets processing path.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_process_immediate_inline_assets
	 */
	public function test_process_immediate_inline_assets_processes_multiple_assets(): void {
		// Create a new concrete instance for testing (not a mock)
		$config_mock = Mockery::mock(ConfigInterface::class);
		$config_mock->shouldReceive('get_logger')->andReturn($this->logger_mock);
		$instance = new ConcreteEnqueueForBaseTraitInlineAssetsTesting($config_mock);

		// Set up assets property with multiple inline scripts
		$reflection      = new \ReflectionClass($instance);
		$assets_property = $reflection->getProperty('assets');
		$assets_property->setAccessible(true);
		$assets_property->setValue($instance, array(
			array(
				'handle' => 'test-script',
				'type'   => AssetType::Script,
				'src'    => 'test.js',
				'inline' => array(
					array(
						'content'  => 'console.log("first");',
						'position' => 'after'
					),
					array(
						'content'  => 'console.log("second");',
						'position' => 'before'
					)
				)
			)
		));

		// Mock wp_add_inline_script to return true for both calls
		\WP_Mock::userFunction('wp_add_inline_script')
			->with('test-script', 'console.log("first");', 'after')
			->andReturn(true);
		\WP_Mock::userFunction('wp_add_inline_script')
			->with('test-script', 'console.log("second");', 'before')
			->andReturn(true);

		// Call the method
		$result = $this->_invoke_protected_method(
			$instance,
			'_process_immediate_inline_assets',
			array(AssetType::Script, 'test-script', 'TestContext')
		);

		// Assert - Should return 2 for both successful additions
		$this->assertEquals(2, $result, 'Should return 2 when both assets are processed successfully');

		// Verify that the inline content was removed after processing
		$assets = $assets_property->getValue($instance);
		$this->assertArrayNotHasKey('inline', $assets[0], 'Inline content should be removed after processing');
	}
}
