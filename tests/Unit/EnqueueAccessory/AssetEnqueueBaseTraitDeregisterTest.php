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
class ConcreteEnqueueForBaseTraitDeregisterTesting extends ConcreteEnqueueForTesting {
	use ScriptsEnqueueTrait;
}

/**
 * Class ScriptsEnqueueTraitTest
 *
 * @package Ran\PluginLib\Tests\Unit\EnqueueAccessory
 *
 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait
 */
class AssetEnqueueTraitBaseTraitDeregisterTest extends EnqueueTraitTestCase {
	use ExpectLogTrait;

	/**
	 * @inheritDoc
	 */
	protected function _get_concrete_class_name(): string {
		return ConcreteEnqueueForBaseTraitDeregisterTesting::class;
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

		// Add WordPress deregistration function mocks
		WP_Mock::userFunction('wp_dequeue_script')->withAnyArgs()->andReturn(true)->byDefault();
		WP_Mock::userFunction('wp_deregister_script')->withAnyArgs()->andReturn(true)->byDefault();
		WP_Mock::userFunction('wp_dequeue_style')->withAnyArgs()->andReturn(true)->byDefault();
		WP_Mock::userFunction('wp_deregister_style')->withAnyArgs()->andReturn(true)->byDefault();

		// Add WordPress asset status check mocks
		WP_Mock::userFunction('wp_script_is')->withAnyArgs()->andReturn(false)->byDefault();
		WP_Mock::userFunction('wp_style_is')->withAnyArgs()->andReturn(false)->byDefault();
	}

	/**
	 * Clean up test environment.
	 */
	public function tearDown(): void {
		parent::tearDown();
		Mockery::close();
	}

	// ------------------------------------------------------------------------
	// Deregistration Tests
	// ------------------------------------------------------------------------

	// ------------------------------------------------------------------------
	// Deregister via Replace Flag in add_assets() Tests
	// ------------------------------------------------------------------------

	/**
	 * Validates that the add_assets() method throws an exception when the replace flag is not a boolean.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::add_assets
	 */
	public function test_add_assets_validates_replace_flag(): void {
		// --- Test Setup ---
		$asset_type = \Ran\PluginLib\EnqueueAccessory\AssetType::Script;
		$handle     = 'my-script';
		$src        = 'path/to/script.js';

		// Valid asset with boolean replace flag
		$valid_asset = array(
			'handle'  => $handle,
			'src'     => $src,
			'replace' => true
		);

		// Invalid asset with non-boolean replace flag
		$invalid_asset = array(
			'handle'  => $handle,
			'src'     => $src,
			'replace' => 'yes' // Non-boolean value
		);

		// --- Act & Assert ---
		// Valid asset should not throw exception
		try {
			$this->_invoke_protected_method($this->instance, 'add_assets', array(array($valid_asset), $asset_type));
			$this->assertTrue(true, 'Valid asset with boolean replace flag should not throw exception');
		} catch (\InvalidArgumentException $e) {
			$this->fail('Valid asset with boolean replace flag should not throw exception');
		}

		// Invalid asset should throw exception
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage("Invalid {$asset_type->value} definition for handle '{$handle}'. The 'replace' property must be a boolean.");
		$this->_invoke_protected_method($this->instance, 'add_assets', array(array($invalid_asset), $asset_type));
	}

	/**
	 * Validates that the enqueue_deferred_assets() method processes deferred assets with the replace flag set to true.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_enqueue_deferred_assets
	 */
	public function test_enqueue_deferred_assets_with_replace_flag(): void {
		// --- Test Setup ---
		$asset_type = \Ran\PluginLib\EnqueueAccessory\AssetType::Script;
		$handle     = 'deferred-script';
		$src        = 'path/to/deferred-script.js';
		$hook_name  = 'wp_enqueue_scripts';
		$priority   = 10;

		// Create a deferred asset with replace flag
		$deferred_asset = array(
			'handle'  => $handle,
			'src'     => $src,
			'replace' => true,
			'defer'   => true
		);

		// Set up the deferred assets property
		$reflection = new \ReflectionClass($this->instance);
		$property   = $reflection->getProperty('deferred_assets');
		$property->setAccessible(true);
		$property->setValue($this->instance, array(
			$hook_name => array(
				$priority => array($deferred_asset)
			)
		));

		// Mock _process_single_asset to verify it's called with the deferred asset
		$this->instance->shouldReceive('_process_single_asset')
			->with($asset_type, $deferred_asset, Mockery::type('string'), $hook_name, true, true)
			->once()
			->andReturn($handle);

		// --- Act ---
		$this->_invoke_protected_method($this->instance, '_enqueue_deferred_assets', array($asset_type, $hook_name, $priority));

		// --- Assert ---
		// Verify the deferred assets were removed after processing
		$this->assertEmpty($property->getValue($this->instance), 'Deferred assets should be empty after processing');
	}

	/**
	 * Validates that the _concrete_process_single_asset() method processes a single asset with the replace flag set to true.

	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_concrete_process_single_asset
	 */
	public function test_concrete_process_single_asset_with_replace_flag(): void {
		// --- Test Setup ---
		$asset_type = \Ran\PluginLib\EnqueueAccessory\AssetType::Script;
		$handle     = 'my-script';
		$src        = 'path/to/script.js';
		$context    = 'TestContext';

		$asset_definition = array(
			'handle'  => $handle,
			'src'     => $src,
			'replace' => true // Set the replace flag
		);

		// Mock the _deregister_existing_asset method to verify it's called
		$this->instance->shouldReceive('_deregister_existing_asset')
			->with($handle, Mockery::type('string'), $asset_type)
			->once()
			->andReturn(true);

		// Mock other methods needed for _concrete_process_single_asset
		$this->instance->shouldReceive('_resolve_environment_src')
			->with($src)
			->andReturn($src);

		$this->instance->shouldReceive('_generate_asset_version')
			->andReturn('1.0');

		$this->instance->shouldReceive('_is_deferred_asset')
			->andReturn(null);

		$this->instance->shouldReceive('get_asset_url')
			->andReturn($src);

		$this->instance->shouldReceive('_do_register')
			->once();

		// --- Act ---
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_concrete_process_single_asset',
			array($asset_type, $asset_definition, $context, null, true, false, array())
		);

		// --- Assert ---
		$this->assertSame($handle, $result, 'Should return the handle when successful');
	}

	// ------------------------------------------------------------------------
	// deregister() Tests
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::deregister
	 */
	public function test_deregister_with_single_handle(): void {
		// --- Test Setup ---
		$handle   = 'jquery-migrate';
		$hook     = 'wp_enqueue_scripts';
		$priority = 10;

		// Mock _do_add_action to verify it's called with correct parameters
		$this->instance->shouldReceive('_do_add_action')
			->with($hook, Mockery::type('Closure'), $priority)
			->once();

		// --- Act ---
		$result = $this->instance->deregister($handle);

		// --- Assert ---
		$this->assertSame($this->instance, $result, 'Method should return instance for chaining');
		$this->expectLog('debug', "ScriptsEnqueueTrait::deregister - Scheduled deregistration of script '{$handle}' on hook '{$hook}' with priority {$priority}.");
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::deregister
	 */
	public function test_deregister_with_multiple_handles(): void {
		// --- Test Setup ---
		$handles  = array('script1', 'script2', 'script3');
		$hook     = 'wp_enqueue_scripts';
		$priority = 10;

		// Mock _do_add_action to verify it's called for each handle
		$this->instance->shouldReceive('_do_add_action')
			->with($hook, Mockery::type('Closure'), $priority)
			->times(3);

		// --- Act ---
		$result = $this->instance->deregister($handles);

		// --- Assert ---
		$this->assertSame($this->instance, $result, 'Method should return instance for chaining');

		// Verify log messages for each handle
		foreach ($handles as $handle) {
			$this->expectLog('debug', "ScriptsEnqueueTrait::deregister - Scheduled deregistration of script '{$handle}' on hook '{$hook}' with priority {$priority}.");
		}
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::deregister
	 */
	public function test_deregister_with_complex_configuration(): void {
		// --- Test Setup ---
		$configs = array(
			array(
				'handle'   => 'theme-script',
				'hook'     => 'wp_enqueue_scripts',
				'priority' => 5,
			),
			array(
				'handle'   => 'admin-script',
				'hook'     => 'admin_enqueue_scripts',
				'priority' => 1,
			)
		);

		// Mock _do_add_action to verify it's called with correct parameters
		$this->instance->shouldReceive('_do_add_action')
			->with('wp_enqueue_scripts', Mockery::type('Closure'), 5)
			->once();

		$this->instance->shouldReceive('_do_add_action')
			->with('admin_enqueue_scripts', Mockery::type('Closure'), 1)
			->once();

		// --- Act ---
		$result = $this->instance->deregister($configs);

		// --- Assert ---
		$this->assertSame($this->instance, $result, 'Method should return instance for chaining');

		// Verify log messages for each configuration
		$this->expectLog('debug', "ScriptsEnqueueTrait::deregister - Scheduled deregistration of script 'theme-script' on hook 'wp_enqueue_scripts' with priority 5.");
		$this->expectLog('debug', "ScriptsEnqueueTrait::deregister - Scheduled deregistration of script 'admin-script' on hook 'admin_enqueue_scripts' with priority 1.");
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::deregister
	 */
	public function test_deregister_with_mixed_input_types(): void {
		// --- Test Setup ---
		$mixed_inputs = array(
			'simple-handle',  // Uses defaults
			array(
				'handle'   => 'complex-handle',
				'hook'     => 'wp_footer',
				'priority' => 15,
			)
		);

		// Mock _do_add_action to verify it's called with correct parameters
		$this->instance->shouldReceive('_do_add_action')
			->with('wp_enqueue_scripts', Mockery::type('Closure'), 10)
			->once();

		$this->instance->shouldReceive('_do_add_action')
			->with('wp_footer', Mockery::type('Closure'), 15)
			->once();

		// --- Act ---
		$result = $this->instance->deregister($mixed_inputs);

		// --- Assert ---
		$this->assertSame($this->instance, $result, 'Method should return instance for chaining');

		// Verify log messages
		$this->expectLog('debug', "ScriptsEnqueueTrait::deregister - Scheduled deregistration of script 'simple-handle' on hook 'wp_enqueue_scripts' with priority 10.");
		$this->expectLog('debug', "ScriptsEnqueueTrait::deregister - Scheduled deregistration of script 'complex-handle' on hook 'wp_footer' with priority 15.");
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::deregister
	 */
	public function test_deregister_with_invalid_input(): void {
		// --- Test Setup ---
		$invalid_inputs = array(
			42,  // Invalid type
			array('missing_handle' => true),  // Missing required 'handle' key
			'valid-handle'  // This one should work
		);

		// Mock _normalize_deregister_input to pass through the invalid array
		// This ensures _process_single_deregistration gets called with the invalid input
		$this->instance->shouldReceive('_normalize_deregister_input')
			->with($invalid_inputs)
			->andReturn(array(
				array('missing_handle' => true),  // This will trigger the warning we want to test
				array('handle' => 'valid-handle')
			));

		// Mock _do_add_action to verify it's called only for the valid handle
		$this->instance->shouldReceive('_do_add_action')
			->with('wp_enqueue_scripts', Mockery::type('Closure'), 10)
			->once();

		// --- Act ---
		$result = $this->instance->deregister($invalid_inputs);

		// --- Assert ---
		$this->assertSame($this->instance, $result, 'Method should return instance for chaining');

		// Verify log messages
		$process_context = 'Ran\\PluginLib\\EnqueueAccessory\\AssetEnqueueBaseTrait::_process_single_deregistration';
		$this->expectLog('warning', "{$process_context} - Invalid script configuration. A 'handle' is required and must be a string.", 1);

		$this->expectLog('debug', "ScriptsEnqueueTrait::deregister - Scheduled deregistration of script 'valid-handle' on hook 'wp_enqueue_scripts' with priority 10.", 1);
	}


	// ------------------------------------------------------------------------
	// Deregister _deregister_existing_asset() Tests
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_deregister_existing_asset
	 */
	public function test_deregister_existing_asset_with_non_registered_asset(): void {
		// --- Test Setup ---
		$asset_type       = \Ran\PluginLib\EnqueueAccessory\AssetType::Script;
		$handle           = 'non-registered-script';
		$context          = 'TestContext';
		$expected_context = __TRAIT__ . '::' . '_deregister_existing_asset(script)';

		// Set up WP_Mock for wp_script_is to return false (not registered)
		WP_Mock::userFunction('wp_script_is')
			->with($handle, 'registered')
			->andReturn(false)
			->once();

		// Also mock the check for enqueued status
		WP_Mock::userFunction('wp_script_is')
			->with($handle, 'enqueued')
			->andReturn(false)
			->once();

		// --- Act ---
		$result = $this->_invoke_protected_method($this->instance, '_deregister_existing_asset', array($handle, $context, $asset_type));

		// --- Assert ---
		$this->assertTrue($result, 'Non-registered assets should return true (no need to deregister)');
		$this->expectLog('debug', "{$expected_context} called from {$context} - '{$handle}' was not registered or enqueued. Nothing to deregister.", 1);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_deregister_existing_asset
	 */
	public function test_deregister_existing_asset_with_registered_asset(): void {
		// --- Test Setup ---
		$asset_type       = \Ran\PluginLib\EnqueueAccessory\AssetType::Script;
		$handle           = 'registered-script';
		$context          = 'TestContext';
		$expected_context = __TRAIT__ . '::' . '_deregister_existing_asset(script)';

		// Set up WP_Mock for wp_script_is to return true (registered)
		WP_Mock::userFunction('wp_script_is')
			->with($handle, 'registered')
			->andReturn(true)
			->once();

		// Set up WP_Mock for wp_script_is to check if registered after deregistration
		WP_Mock::userFunction('wp_script_is')
			->with($handle, 'registered')
			->andReturn(false)
			->once();

		// Set up WP_Mock for wp_script_is with 'enqueued' parameter (not enqueued)
		WP_Mock::userFunction('wp_script_is')
			->with($handle, 'enqueued')
			->andReturn(false)
			->once();

		// Set up WP_Mock for wp_deregister_script
		WP_Mock::userFunction('wp_deregister_script')
			->with($handle)
			->once();

		// --- Act ---
		$result = $this->_invoke_protected_method($this->instance, '_deregister_existing_asset', array($handle, $context, $asset_type));

		// --- Assert ---
		$this->assertTrue($result, 'Registered assets should be deregistered successfully');
		$this->expectLog('debug', "{$expected_context} called from {$context} - Successfully deregistered '{$handle}'.");
	}

	/**
	 * Validates that the _deregister_existing_asset() method deregisters an existing style asset.

	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_deregister_existing_asset
	 */
	public function test_deregister_existing_asset_with_style_asset(): void {
		// --- Test Setup ---
		$asset_type       = \Ran\PluginLib\EnqueueAccessory\AssetType::Style;
		$handle           = 'registered-style';
		$context          = 'TestContext';
		$expected_context = __TRAIT__ . '::' . '_deregister_existing_asset(style)';

		// No need to mock function_exists as it's a PHP internal function

		// Set up WP_Mock for wp_style_is to return true for 'registered' initially
		WP_Mock::userFunction('wp_style_is')
			->with($handle, 'registered')
			->andReturn(true)
			->once();

		// Set up WP_Mock for wp_style_is with 'enqueued' parameter (not enqueued)
		WP_Mock::userFunction('wp_style_is')
			->with($handle, 'enqueued')
			->andReturn(false)
			->once();

		// Set up WP_Mock for wp_deregister_style
		WP_Mock::userFunction('wp_deregister_style')
			->with($handle)
			->once();

		// Set up WP_Mock for wp_style_is to return false for 'registered' after deregistration
		WP_Mock::userFunction('wp_style_is')
			->with($handle, 'registered')
			->andReturn(false)
			->once();

		// --- Act ---
		$result = $this->_invoke_protected_method($this->instance, '_deregister_existing_asset', array($handle, $context, $asset_type));

		// --- Assert ---
		$this->assertTrue($result, 'Registered style assets should be deregistered successfully');
		$this->expectLog('debug', "{$expected_context} called from {$context} - Successfully deregistered '{$handle}'");
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_deregister_existing_asset
	 */
	public function test_deregister_existing_asset_with_nonexistent_asset(): void {
		// --- Test Setup ---
		$handle     = 'nonexistent-asset';
		$asset_type = AssetType::Script;
		$context    = 'Test Context';

		// Mock the WordPress deregister function
		WP_Mock::userFunction('wp_deregister_script')
			->with($handle)
			->never();

		// --- Act ---
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_deregister_existing_asset',
			array($handle, $context, $asset_type)
		);

		// --- Assert ---
		$this->assertTrue($result, 'Should return true when asset not found (success=true by default)');
		$this->expectLog('debug', "{$context} - Attempting to deregister existing '{$handle}' for replacement.", 1);
		$this->expectLog('debug', "{$context} - '{$handle}' was not registered or enqueued. Nothing to deregister.", 1);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_deregister_existing_asset
	 */
	public function test_deregister_existing_asset_with_wp_registered_asset(): void {
		// --- Test Setup ---
		$handle     = 'wp-registered-asset';
		$asset_type = AssetType::Script;
		$context    = 'Test Context';

		// Mock the WordPress functions
		WP_Mock::userFunction('wp_script_is')
			->with($handle, 'registered')
			->andReturnValues(array(true, false)); // First call returns true, second call returns false

		WP_Mock::userFunction('wp_script_is')
			->with($handle, 'enqueued')
			->andReturn(false);

		WP_Mock::userFunction('wp_deregister_script')
			->with($handle)
			->once()
			->andReturn(true);

		// --- Act ---
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_deregister_existing_asset',
			array($handle, $context, $asset_type)
		);

		// --- Assert ---
		$this->assertTrue($result, 'Should return true when asset deregistered');
		$expected_context = 'Ran\\PluginLib\\EnqueueAccessory\\AssetEnqueueBaseTrait::_deregister_existing_asset(script)';
		$this->expectLog('debug', "{$expected_context} called from {$context} - Attempting to deregister existing '{$handle}' for replacement.");
		$this->expectLog('debug', "{$expected_context} called from {$context} - Successfully deregistered '{$handle}'");
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_deregister_existing_asset
	 */
	public function test_deregister_existing_asset_with_deferred_asset(): void {
		// --- Test Setup ---
		$handle     = 'deferred-asset';
		$asset_type = AssetType::Script;
		$context    = 'test-context';
		$hook_name  = 'wp_footer';
		$priority   = 10;

		// Mock WordPress functions
		WP_Mock::userFunction('wp_script_is')
			->with($handle, 'registered')
			->andReturn(false);

		WP_Mock::userFunction('wp_deregister_script')
			->never();

		// Set up a deferred asset in the instance
		$reflection = new \ReflectionClass($this->instance);
		$property   = $reflection->getProperty('deferred_assets');
		$property->setAccessible(true);

		$deferred_assets = array(
			$hook_name => array(
				$priority => array(
					0 => array(
						'handle' => $handle,
						'type'   => $asset_type,
						'src'    => 'test.js'
					)
				)
			)
		);

		$property->setValue($this->instance, $deferred_assets);

		// --- Act ---
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_deregister_existing_asset',
			array($handle, $context, $asset_type)
		);

		// --- Assert ---
		$this->assertTrue($result, 'Should return true when asset found and cleaned up');
		$this->expectLog('debug', array("{$context} - Asset '{$handle}' found in internal queues at 1 location(s). Will clean up after deregistration."), 1);
		$this->expectLog('debug', array("{$context} - Removed '{$handle}' from internal deferred queue (hook '{$hook_name}', priority {$priority})."), 1);

		// Verify the asset was removed from deferred_assets
		$updated_deferred_assets = $property->getValue($this->instance);
		$this->assertEmpty($updated_deferred_assets, 'Deferred assets should be empty after cleanup');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_deregister_existing_asset
	 */
	public function test_deregister_existing_asset_with_multiple_locations(): void {
		// --- Test Setup ---
		$handle     = 'multi-location-asset';
		$asset_type = AssetType::Script;
		$context    = 'Test Context';

		// Mock WordPress functions
		WP_Mock::userFunction('wp_script_is')
			->with($handle, 'registered')
			->andReturnValues(array(true, false)); // First call returns true, second call returns false

		WP_Mock::userFunction('wp_script_is')
			->with($handle, 'enqueued')
			->andReturn(false);

		WP_Mock::userFunction('wp_deregister_script')
			->with($handle)
			->once()
			->andReturn(true);

		// Set up multiple deferred assets with the same handle
		$reflection = new \ReflectionClass($this->instance);
		$property   = $reflection->getProperty('deferred_assets');
		$property->setAccessible(true);

		$deferred_assets = array(
			'wp_footer' => array(
				10 => array(
					0 => array(
						'handle' => $handle,
						'type'   => $asset_type,
						'src'    => 'test1.js'
					)
				)
			),
			'wp_head' => array(
				20 => array(
					1 => array(
						'handle' => $handle,
						'type'   => $asset_type,
						'src'    => 'test2.js'
					)
				)
			)
		);

		$property->setValue($this->instance, $deferred_assets);

		// --- Act ---
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_deregister_existing_asset',
			array($handle, $context, $asset_type)
		);

		// --- Assert ---
		$this->assertTrue($result, 'Should return true when asset found and cleaned up');
		$expected_context = 'Ran\\PluginLib\\EnqueueAccessory\\AssetEnqueueBaseTrait::_deregister_existing_asset(script)';
		$this->expectLog('debug', "{$expected_context} called from {$context} - Attempting to deregister existing '{$handle}' for replacement.", 1);
		$this->expectLog('debug', "{$expected_context} called from {$context} - Asset '{$handle}' found in internal queues at 2 location(s). Will clean up after deregistration.", 1);
		$this->expectLog('debug', "{$expected_context} called from {$context} - Removed '{$handle}' from internal deferred queue (hook 'wp_footer', priority 10).", 1);
		$this->expectLog('debug', "{$expected_context} called from {$context} - Removed '{$handle}' from internal deferred queue (hook 'wp_head', priority 20).", 1);

		// Verify all assets were removed from deferred_assets
		$updated_deferred_assets = $property->getValue($this->instance);
		$this->assertEmpty($updated_deferred_assets, 'Deferred assets should be empty after cleanup');
	}

	/**
	 * Test _deregister_existing_asset handles dequeue failure.
	 * This covers the dequeue failure path.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_deregister_existing_asset
	 */
	public function test_deregister_existing_asset_handles_dequeue_failure(): void {
		// --- Test Setup ---
		$asset_type       = \Ran\PluginLib\EnqueueAccessory\AssetType::Script;
		$handle           = 'protected-script';
		$context          = 'TestContext';
		$expected_context = 'Ran\\PluginLib\\EnqueueAccessory\\AssetEnqueueBaseTrait::_deregister_existing_asset(script)';

		// Mock _asset_exists_in_internal_queues to return empty array
		$this->instance->shouldReceive('_asset_exists_in_internal_queues')
			->with($handle, $asset_type)
			->andReturn(array());

		// Mock wp_script_is calls in sequence
		\WP_Mock::userFunction('wp_script_is')
			->with($handle, 'registered')
			->andReturn(true)
			->once();
		\WP_Mock::userFunction('wp_script_is')
			->with($handle, 'enqueued')
			->andReturn(true)
			->once();

		// Mock wp_dequeue_script to be called
		\WP_Mock::userFunction('wp_dequeue_script')
			->with($handle)
			->once();

		// Mock wp_script_is to still return true after dequeue (failure)
		\WP_Mock::userFunction('wp_script_is')
			->with($handle, 'enqueued')
			->andReturn(true)
			->once(); // Still enqueued = dequeue failed

		// Mock wp_deregister_script to be called
		\WP_Mock::userFunction('wp_deregister_script')
			->with($handle)
			->once();

		// Mock wp_script_is to return false after deregister (success)
		\WP_Mock::userFunction('wp_script_is')
			->with($handle, 'registered')
			->andReturn(false)
			->once(); // Not registered = deregister succeeded

		// --- Act ---
		$result = $this->_invoke_protected_method($this->instance, '_deregister_existing_asset', array($handle, $context, $asset_type));

		// --- Assert ---
		$this->assertFalse($result, 'Should return false when dequeue fails');
		$this->expectLog('warning', "{$expected_context} called from {$context} - Failed to dequeue '{$handle}'. It may be protected or re-enqueued by another plugin.", 1);
		$this->expectLog('debug', "{$expected_context} called from {$context} - Successfully deregistered '{$handle}'.", 1);
		$this->expectLog('warning', "{$expected_context} called from {$context} - Deregistration of '{$handle}' was only partially successful. Proceeding with replacement anyway.", 1);
	}

	/**
	 * Test _deregister_existing_asset handles deregister failure.
	 * This covers the deregister failure path.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_deregister_existing_asset
	 */
	public function test_deregister_existing_asset_handles_deregister_failure(): void {
		// --- Test Setup ---
		$asset_type       = \Ran\PluginLib\EnqueueAccessory\AssetType::Style;
		$handle           = 'protected-style';
		$context          = 'TestContext';
		$expected_context = 'Ran\\PluginLib\\EnqueueAccessory\\AssetEnqueueBaseTrait::_deregister_existing_asset(style)';

		// Mock _asset_exists_in_internal_queues to return empty array
		$this->instance->shouldReceive('_asset_exists_in_internal_queues')
			->with($handle, $asset_type)
			->andReturn(array());

		// Mock wp_style_is to return true for registered, false for enqueued
		\WP_Mock::userFunction('wp_style_is')
			->with($handle, 'registered')
			->andReturn(true);
		\WP_Mock::userFunction('wp_style_is')
			->with($handle, 'enqueued')
			->andReturn(false);

		// Mock wp_deregister_style to be called
		\WP_Mock::userFunction('wp_deregister_style')
			->with($handle)
			->once();

		// Mock wp_style_is to still return true after deregister (failure)
		\WP_Mock::userFunction('wp_style_is')
			->with($handle, 'registered')
			->andReturn(true); // Still registered = deregister failed

		// --- Act ---
		$result = $this->_invoke_protected_method($this->instance, '_deregister_existing_asset', array($handle, $context, $asset_type));

		// --- Assert ---
		$this->assertFalse($result, 'Should return false when deregister fails');
		$this->expectLog('warning', "{$expected_context} called from {$context} - Failed to deregister '{$handle}'. It may be a protected WordPress core style or re-registered by another plugin.", 1);
		$this->expectLog('warning', "{$expected_context} called from {$context} - Deregistration of '{$handle}' was only partially successful. Proceeding with replacement anyway.", 1);
	}

	/**
	 * Test _deregister_existing_asset handles assets queue cleanup.
	 * This covers the assets queue type cleanup path.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_deregister_existing_asset
	 */
	public function test_deregister_existing_asset_handles_assets_queue_cleanup(): void {
		// --- Test Setup ---
		$asset_type       = \Ran\PluginLib\EnqueueAccessory\AssetType::Script;
		$handle           = 'test-script';
		$context          = 'TestContext';
		$expected_context = 'Ran\\PluginLib\\EnqueueAccessory\\AssetEnqueueBaseTrait::_deregister_existing_asset(script)';

		// Mock _asset_exists_in_internal_queues to return assets queue location
		$asset_locations = array(
			array(
				'queue_type' => 'assets',
				'index'      => 0
			)
		);
		$this->instance->shouldReceive('_asset_exists_in_internal_queues')
			->with($handle, $asset_type)
			->andReturn($asset_locations);

		// Mock wp_script_is to return false for both (not registered or enqueued)
		\WP_Mock::userFunction('wp_script_is')
			->with($handle, 'registered')
			->andReturn(false);
		\WP_Mock::userFunction('wp_script_is')
			->with($handle, 'enqueued')
			->andReturn(false);

		// --- Act ---
		$result = $this->_invoke_protected_method($this->instance, '_deregister_existing_asset', array($handle, $context, $asset_type));

		// --- Assert ---
		$this->assertTrue($result, 'Should return true when no deregistration needed');
		$this->expectLog('debug', "{$expected_context} called from {$context} - Asset '{$handle}' found in internal queues at 1 location(s). Will clean up after deregistration.", 1);
		$this->expectLog('debug', "{$expected_context} called from {$context} - Asset '{$handle}' in assets queue will be cleaned up by WordPress core functions.", 1);
		$this->expectLog('debug', "{$expected_context} called from {$context} - '{$handle}' was not registered or enqueued. Nothing to deregister.", 1);
	}

	/**
	 * Test _deregister_existing_asset handles deferred queue cleanup with empty priority cleanup.
	 * This covers the empty priority array cleanup path.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_deregister_existing_asset
	 */
	public function test_deregister_existing_asset_cleans_up_empty_priority(): void {
		// --- Test Setup ---
		$asset_type       = \Ran\PluginLib\EnqueueAccessory\AssetType::Script;
		$handle           = 'test-script';
		$context          = 'TestContext';
		$hook_name        = 'wp_enqueue_scripts';
		$priority         = 10;
		$index            = 0;
		$expected_context = 'Ran\\PluginLib\\EnqueueAccessory\\AssetEnqueueBaseTrait::_deregister_existing_asset(script)';

		// Set up deferred_assets property with the asset to be removed
		$reflection               = new \ReflectionClass($this->instance);
		$deferred_assets_property = $reflection->getProperty('deferred_assets');
		$deferred_assets_property->setAccessible(true);
		$deferred_assets_property->setValue($this->instance, array(
			$hook_name => array(
				$priority => array(
					$index => array(
						'handle' => $handle,
						'type'   => $asset_type
					)
				)
			)
		));

		// Mock _asset_exists_in_internal_queues to return deferred queue location
		$asset_locations = array(
			array(
				'queue_type' => 'deferred',
				'hook_name'  => $hook_name,
				'priority'   => $priority,
				'index'      => $index
			)
		);
		$this->instance->shouldReceive('_asset_exists_in_internal_queues')
			->with($handle, $asset_type)
			->andReturn($asset_locations);

		// Mock wp_script_is to return false for both (not registered or enqueued)
		\WP_Mock::userFunction('wp_script_is')
			->with($handle, 'registered')
			->andReturn(false);
		\WP_Mock::userFunction('wp_script_is')
			->with($handle, 'enqueued')
			->andReturn(false);

		// --- Act ---
		$result = $this->_invoke_protected_method($this->instance, '_deregister_existing_asset', array($handle, $context, $asset_type));

		// --- Assert ---
		$this->assertTrue($result, 'Should return true when cleanup succeeds');

		// Verify that the deferred asset was removed and empty arrays cleaned up
		$deferred_assets = $deferred_assets_property->getValue($this->instance);
		$this->assertEmpty($deferred_assets, 'Deferred assets should be completely cleaned up');

		// Verify log messages
		$this->expectLog('debug', "{$expected_context} called from {$context} - Removed '{$handle}' from internal deferred queue (hook '{$hook_name}', priority {$priority}).", 1);
		$this->expectLog('debug', "{$expected_context} called from {$context} - Cleaned up empty priority {$priority} for hook '{$hook_name}' in internal deferred queue.", 1);
		$this->expectLog('debug', "{$expected_context} called from {$context} - Cleaned up empty hook '{$hook_name}' from internal deferred queue.", 1);
		$this->expectLog('debug', "{$expected_context} called from {$context} - '{$handle}' was not registered or enqueued. Nothing to deregister.", 1);
	}

	/**
	 * Test _deregister_existing_asset handles successful dequeue and deregister.
	 * This covers the complete success path.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_deregister_existing_asset
	 */
	public function test_deregister_existing_asset_complete_success(): void {
		// --- Test Setup ---
		$asset_type       = \Ran\PluginLib\EnqueueAccessory\AssetType::Script;
		$handle           = 'test-script';
		$context          = 'TestContext';
		$expected_context = 'Ran\\PluginLib\\EnqueueAccessory\\AssetEnqueueBaseTrait::_deregister_existing_asset(script)';

		// Mock _asset_exists_in_internal_queues to return empty array
		$this->instance->shouldReceive('_asset_exists_in_internal_queues')
			->with($handle, $asset_type)
			->andReturn(array());

		// Mock wp_script_is calls in sequence
		\WP_Mock::userFunction('wp_script_is')
			->with($handle, 'registered')
			->andReturn(true)
			->once();
		\WP_Mock::userFunction('wp_script_is')
			->with($handle, 'enqueued')
			->andReturn(true)
			->once();

		// Mock wp_dequeue_script to be called
		\WP_Mock::userFunction('wp_dequeue_script')
			->with($handle)
			->once();

		// Mock wp_script_is to return false after dequeue (success)
		\WP_Mock::userFunction('wp_script_is')
			->with($handle, 'enqueued')
			->andReturn(false)
			->once(); // Not enqueued = dequeue succeeded

		// Mock wp_deregister_script to be called
		\WP_Mock::userFunction('wp_deregister_script')
			->with($handle)
			->once();

		// Mock wp_script_is to return false after deregister (success)
		\WP_Mock::userFunction('wp_script_is')
			->with($handle, 'registered')
			->andReturn(false)
			->once(); // Not registered = deregister succeeded

		// --- Act ---
		$result = $this->_invoke_protected_method($this->instance, '_deregister_existing_asset', array($handle, $context, $asset_type));

		// --- Assert ---
		$this->assertTrue($result, 'Should return true when both dequeue and deregister succeed');
		$this->expectLog('debug', "{$expected_context} called from {$context} - Successfully dequeued '{$handle}'.", 1);
		$this->expectLog('debug', "{$expected_context} called from {$context} - Successfully deregistered '{$handle}'.", 1);
		$this->expectLog('debug', "{$expected_context} called from {$context} - Successfully completed deregistration of '{$handle}'.", 1);
	}

	// ------------------------------------------------------------------------
	// _deregister_assets() Tests
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_deregister_assets
	 */
	public function test_deregister_assets_with_multiple_inputs(): void {
		// --- Test Setup ---
		$configs = array(
			'simple-handle',
			array(
				'handle'   => 'complex-handle',
				'hook'     => 'admin_enqueue_scripts',
				'priority' => 20
			),
			123,            // Invalid integer (should be skipped/logged)
			array()         // Invalid array (should be skipped/logged)
		);
		$asset_type = AssetType::Script;
		$context    = __TRAIT__ . '::' . '_normalize_deregister_input';

		// Don't mock _normalize_deregister_input so it can generate real logs
		// Instead, mock _process_single_deregistration to verify it's called correctly with the expected outputs
		$this->instance->shouldReceive('_process_single_deregistration')
			->with(array('handle' => 'simple-handle'), $asset_type)
			->once();

		$this->instance->shouldReceive('_process_single_deregistration')
			->with(array(
				'handle'   => 'complex-handle',
				'hook'     => 'admin_enqueue_scripts',
				'priority' => 20
			), $asset_type)
			->once();

		// --- Act ---
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_deregister_assets',
			array($configs, $asset_type)
		);

		// --- Assert ---
		$this->assertSame($this->instance, $result, 'Method should return instance for chaining');

		// Verify warning logs for invalid inputs
		$this->expectLog('warning', "{$context} - Invalid input type at index 2. Expected string or array, got integer.");
	}

	// ------------------------------------------------------------------------
	// _process_single_deregistration() Tests
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_process_single_deregistration
	 */
	public function test_process_single_deregistration(): void {
		// --- Test Setup ---
		$config = array(
			'handle'   => 'test-handle',
			'hook'     => 'wp_footer',
			'priority' => 15
		);
		$asset_type = AssetType::Script;

		// Mock _do_add_action to verify it's called with correct parameters
		$this->instance->shouldReceive('_do_add_action')
			->with('wp_footer', Mockery::type('Closure'), 15)
			->once();

		// --- Act ---
		$this->_invoke_protected_method(
			$this->instance,
			'_process_single_deregistration',
			array($config, $asset_type)
		);

		// --- Assert ---
		$this->expectLog('debug', "ScriptsEnqueueTrait::deregister - Scheduled deregistration of script 'test-handle' on hook 'wp_footer' with priority 15.");
	}

	/**
	 * Test _process_single_deregistration with invalid handle (missing).
	 * This covers the invalid handle validation path.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_process_single_deregistration
	 */
	public function test_process_single_deregistration_with_missing_handle(): void {
		// --- Test Setup ---
		$config = array(
			'hook'     => 'wp_footer',
			'priority' => 15
			// Missing 'handle'
		);
		$asset_type       = \Ran\PluginLib\EnqueueAccessory\AssetType::Script;
		$expected_context = 'Ran\\PluginLib\\EnqueueAccessory\\AssetEnqueueBaseTrait::_process_single_deregistration';

		// --- Act ---
		$this->_invoke_protected_method(
			$this->instance,
			'_process_single_deregistration',
			array($config, $asset_type)
		);

		// --- Assert ---
		$this->expectLog('warning', "{$expected_context} - Invalid script configuration. A 'handle' is required and must be a string.", 1);
	}

	/**
	 * Test _process_single_deregistration with invalid handle (non-string).
	 * This covers the non-string handle validation path.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_process_single_deregistration
	 */
	public function test_process_single_deregistration_with_non_string_handle(): void {
		// --- Test Setup ---
		$config = array(
			'handle'   => 123, // Non-string handle
			'hook'     => 'wp_footer',
			'priority' => 15
		);
		$asset_type       = \Ran\PluginLib\EnqueueAccessory\AssetType::Style;
		$expected_context = 'Ran\\PluginLib\\EnqueueAccessory\\AssetEnqueueBaseTrait::_process_single_deregistration';

		// --- Act ---
		$this->_invoke_protected_method(
			$this->instance,
			'_process_single_deregistration',
			array($config, $asset_type)
		);

		// --- Assert ---
		$this->expectLog('warning', "{$expected_context} - Invalid style configuration. A 'handle' is required and must be a string.", 1);
	}

	/**
	 * Test _process_single_deregistration with empty handle.
	 * This covers the empty handle validation path.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_process_single_deregistration
	 */
	public function test_process_single_deregistration_with_empty_handle(): void {
		// --- Test Setup ---
		$config = array(
			'handle'   => '', // Empty handle
			'hook'     => 'wp_footer',
			'priority' => 15
		);
		$asset_type       = \Ran\PluginLib\EnqueueAccessory\AssetType::Script;
		$expected_context = 'Ran\\PluginLib\\EnqueueAccessory\\AssetEnqueueBaseTrait::_process_single_deregistration';

		// --- Act ---
		$this->_invoke_protected_method(
			$this->instance,
			'_process_single_deregistration',
			array($config, $asset_type)
		);

		// --- Assert ---
		$this->expectLog('warning', "{$expected_context} - Invalid script configuration. A 'handle' is required and must be a string.", 1);
	}

	/**
	 * Test _process_single_deregistration with immediate deregistration.
	 * This covers the immediate deregistration path.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_process_single_deregistration
	 */
	public function test_process_single_deregistration_with_immediate_flag(): void {
		// --- Test Setup ---
		$config = array(
			'handle'    => 'test-handle',
			'immediate' => true
		);
		$asset_type = \Ran\PluginLib\EnqueueAccessory\AssetType::Script;

		// Mock _deregister_existing_asset to verify it's called
		$this->instance->shouldReceive('_deregister_existing_asset')
			->with('test-handle', '_process_single_deregistration', $asset_type)
			->once();

		// --- Act ---
		$this->_invoke_protected_method(
			$this->instance,
			'_process_single_deregistration',
			array($config, $asset_type)
		);

		// --- Assert ---
		$this->expectLog('debug', "ScriptsEnqueueTrait::deregister - Immediately deregistered script 'test-handle'.", 1);
	}

	/**
	 * Test _process_single_deregistration with style asset type.
	 * This covers the StylesEnqueueTrait path.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_process_single_deregistration
	 */
	public function test_process_single_deregistration_with_style_asset(): void {
		// --- Test Setup ---
		$config = array(
			'handle'    => 'test-style',
			'immediate' => true
		);
		$asset_type = \Ran\PluginLib\EnqueueAccessory\AssetType::Style;

		// Mock _deregister_existing_asset to verify it's called
		$this->instance->shouldReceive('_deregister_existing_asset')
			->with('test-style', '_process_single_deregistration', $asset_type)
			->once();

		// --- Act ---
		$this->_invoke_protected_method(
			$this->instance,
			'_process_single_deregistration',
			array($config, $asset_type)
		);

		// --- Assert ---
		$this->expectLog('debug', "StylesEnqueueTrait::deregister - Immediately deregistered style 'test-style'.", 1);
	}

	/**
	 * Test _process_single_deregistration with default values.
	 * This covers the default hook and priority assignment.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_process_single_deregistration
	 */
	public function test_process_single_deregistration_with_defaults(): void {
		// --- Test Setup ---
		$config = array(
			'handle' => 'test-handle'
			// No hook, priority, or immediate specified - should use defaults
		);
		$asset_type = \Ran\PluginLib\EnqueueAccessory\AssetType::Script;

		// Mock _do_add_action to verify it's called with default values
		$this->instance->shouldReceive('_do_add_action')
			->with('wp_enqueue_scripts', \Mockery::type('Closure'), 10)
			->once();

		// --- Act ---
		$this->_invoke_protected_method(
			$this->instance,
			'_process_single_deregistration',
			array($config, $asset_type)
		);

		// --- Assert ---
		$this->expectLog('debug', "ScriptsEnqueueTrait::deregister - Scheduled deregistration of script 'test-handle' on hook 'wp_enqueue_scripts' with priority 10.", 1);
	}

	/**
	 * Test _process_single_deregistration with logger inactive.
	 * This covers the logger inactive scenario.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_process_single_deregistration
	 */
	public function test_process_single_deregistration_with_logger_inactive(): void {
		// --- Test Setup ---
		$config = array(
			'handle' => '', // Empty handle to trigger warning
		);
		$asset_type = \Ran\PluginLib\EnqueueAccessory\AssetType::Script;

		// Mock logger to be inactive
		$logger = \Mockery::mock('Ran\\PluginLib\\Util\\Logger');
		$logger->shouldReceive('is_active')
			->andReturn(false);
		$logger->shouldNotReceive('warning'); // Should not be called when inactive

		$this->instance->shouldReceive('get_logger')
			->andReturn($logger);

		// --- Act ---
		$this->_invoke_protected_method(
			$this->instance,
			'_process_single_deregistration',
			array($config, $asset_type)
		);

		// --- Assert ---
		// No logs should be generated when logger is inactive
		$this->assertTrue(true); // Test passes if no exceptions thrown
	}

	/**
	 * Test _process_single_deregistration with deferred style deregistration.
	 * This covers the deferred path with StylesEnqueueTrait.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_process_single_deregistration
	 */
	public function test_process_single_deregistration_deferred_style(): void {
		// --- Test Setup ---
		$config = array(
			'handle'   => 'test-style',
			'hook'     => 'wp_head',
			'priority' => 5
		);
		$asset_type = \Ran\PluginLib\EnqueueAccessory\AssetType::Style;

		// Mock _do_add_action to verify it's called with correct parameters
		$this->instance->shouldReceive('_do_add_action')
			->with('wp_head', \Mockery::type('Closure'), 5)
			->once();

		// --- Act ---
		$this->_invoke_protected_method(
			$this->instance,
			'_process_single_deregistration',
			array($config, $asset_type)
		);

		// --- Assert ---
		$this->expectLog('debug', "StylesEnqueueTrait::deregister - Scheduled deregistration of style 'test-style' on hook 'wp_head' with priority 5.", 1);
	}

	// ------------------------------------------------------------------------
	// _asset_exists_in_internal_queues() Tests
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_asset_exists_in_internal_queues
	 */
	public function test_asset_exists_in_internal_queues_returns_empty_array_when_asset_not_found(): void {
		// --- Test Setup ---
		$handle     = 'nonexistent-asset';
		$asset_type = AssetType::Script;

		// --- Act ---
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_asset_exists_in_internal_queues',
			array($handle, $asset_type)
		);

		// --- Assert ---
		$this->assertIsArray($result);
		$this->assertEmpty($result, 'Should return empty array when asset not found');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_asset_exists_in_internal_queues
	 */
	public function test_asset_exists_in_internal_queues_finds_deferred_asset(): void {
		// --- Test Setup ---
		$handle     = 'test-deferred-script';
		$asset_type = AssetType::Script;
		$hook_name  = 'wp_footer';
		$priority   = 20;

		// Set up a deferred asset in the instance
		$reflection = new \ReflectionClass($this->instance);
		$property   = $reflection->getProperty('deferred_assets');
		$property->setAccessible(true);

		$deferred_assets = array(
			$hook_name => array(
				$priority => array(
					0 => array(
						'handle' => $handle,
						'type'   => $asset_type,
						'src'    => 'test.js'
					)
				)
			)
		);

		$property->setValue($this->instance, $deferred_assets);

		// --- Act ---
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_asset_exists_in_internal_queues',
			array($handle, $asset_type)
		);

		// --- Assert ---
		$this->assertIsArray($result);
		$this->assertNotEmpty($result, 'Should return non-empty array when asset found');
		$this->assertCount(1, $result, 'Should find exactly one asset location');

		$location = $result[0];
		$this->assertArrayHasKey('queue_type', $location);
		$this->assertArrayHasKey('hook_name', $location);
		$this->assertArrayHasKey('priority', $location);
		$this->assertArrayHasKey('index', $location);

		$this->assertEquals('deferred', $location['queue_type']);
		$this->assertEquals($hook_name, $location['hook_name']);
		$this->assertEquals($priority, $location['priority']);
		$this->assertEquals(0, $location['index']);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_asset_exists_in_internal_queues
	 */
	public function test_asset_exists_in_internal_queues_finds_multiple_asset_locations(): void {
		// --- Test Setup ---
		$handle     = 'test-multiple-locations';
		$asset_type = AssetType::Script;

		// Set up multiple deferred assets with the same handle in different hooks/priorities
		$reflection = new \ReflectionClass($this->instance);
		$property   = $reflection->getProperty('deferred_assets');
		$property->setAccessible(true);

		$deferred_assets = array(
			'wp_footer' => array(
				10 => array(
					0 => array(
						'handle' => $handle,
						'type'   => $asset_type,
						'src'    => 'test1.js'
					)
				)
			),
			'wp_head' => array(
				20 => array(
					1 => array(
						'handle' => $handle,
						'type'   => $asset_type,
						'src'    => 'test2.js'
					)
				)
			)
		);

		$property->setValue($this->instance, $deferred_assets);

		// --- Act ---
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_asset_exists_in_internal_queues',
			array($handle, $asset_type)
		);

		// --- Assert ---
		$this->assertIsArray($result);
		$this->assertCount(2, $result, 'Should find both asset locations');

		// Check first location
		$this->assertEquals('deferred', $result[0]['queue_type']);
		$this->assertEquals('wp_footer', $result[0]['hook_name']);
		$this->assertEquals(10, $result[0]['priority']);
		$this->assertEquals(0, $result[0]['index']);

		// Check second location
		$this->assertEquals('deferred', $result[1]['queue_type']);
		$this->assertEquals('wp_head', $result[1]['hook_name']);
		$this->assertEquals(20, $result[1]['priority']);
		$this->assertEquals(1, $result[1]['index']);
	}

	/**
	 * Test _asset_exists_in_internal_queues finds asset in general assets queue.
	 * This covers the assets queue search path.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_asset_exists_in_internal_queues
	 */
	public function test_asset_exists_in_internal_queues_finds_asset_in_assets_queue(): void {
		// --- Test Setup ---
		$handle     = 'test-general-asset';
		$asset_type = \Ran\PluginLib\EnqueueAccessory\AssetType::Script;

		// Mock get_assets_info to return asset in general queue
		$this->instance->shouldReceive('get_assets_info')
			->andReturn(array(
				'assets' => array(
					$handle => array(
						'handle' => $handle,
						'src'    => 'test.js'
					)
				),
				'external_inline' => array()
			));

		// --- Act ---
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_asset_exists_in_internal_queues',
			array($handle, $asset_type)
		);

		// --- Assert ---
		$this->assertIsArray($result);
		$this->assertCount(1, $result, 'Should find asset in general assets queue');
		$this->assertEquals('assets', $result[0]['queue_type']);
		$this->assertEquals($handle, $result[0]['handle']);
		$this->assertArrayNotHasKey('hook_name', $result[0], 'Assets queue should not have hook_name');
		$this->assertArrayNotHasKey('priority', $result[0], 'Assets queue should not have priority');
	}

	/**
	 * Test _asset_exists_in_internal_queues finds asset in external inline queue.
	 * This covers the external_inline queue search path.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_asset_exists_in_internal_queues
	 */
	public function test_asset_exists_in_internal_queues_finds_asset_in_external_inline_queue(): void {
		// --- Test Setup ---
		$handle     = 'test-external-inline';
		$asset_type = \Ran\PluginLib\EnqueueAccessory\AssetType::Style;
		$hook_name  = 'wp_head';
		$priority   = 15;

		// Mock get_assets_info to return asset in external inline queue
		$this->instance->shouldReceive('get_assets_info')
			->andReturn(array(
				'assets'          => array(),
				'external_inline' => array(
					$hook_name => array(
						$priority => array(
							$handle => array(
								'content'  => 'body { color: red; }',
								'position' => 'after'
							)
						)
					)
				)
			));

		// --- Act ---
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_asset_exists_in_internal_queues',
			array($handle, $asset_type)
		);

		// --- Assert ---
		$this->assertIsArray($result);
		$this->assertCount(1, $result, 'Should find asset in external inline queue');
		$this->assertEquals('external_inline', $result[0]['queue_type']);
		$this->assertEquals($hook_name, $result[0]['hook_name']);
		$this->assertEquals($priority, $result[0]['priority']);
		$this->assertEquals($handle, $result[0]['handle']);
	}

	/**
	 * Test _asset_exists_in_internal_queues with unset deferred_assets property.
	 * This covers the case when deferred_assets is not initialized.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_asset_exists_in_internal_queues
	 */
	public function test_asset_exists_in_internal_queues_with_unset_deferred_assets(): void {
		// --- Test Setup ---
		$handle     = 'test-handle';
		$asset_type = \Ran\PluginLib\EnqueueAccessory\AssetType::Script;

		// Ensure deferred_assets is empty (simulating unset state)
		$reflection = new \ReflectionClass($this->instance);
		$property   = $reflection->getProperty('deferred_assets');
		$property->setAccessible(true);
		$property->setValue($this->instance, array());

		// Mock get_assets_info to return empty queues
		$this->instance->shouldReceive('get_assets_info')
			->andReturn(array(
				'assets'          => array(),
				'external_inline' => array()
			));

		// --- Act ---
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_asset_exists_in_internal_queues',
			array($handle, $asset_type)
		);

		// --- Assert ---
		$this->assertIsArray($result);
		$this->assertEmpty($result, 'Should return empty array when deferred_assets is not set');
	}

	/**
	 * Test _asset_exists_in_internal_queues with asset type mismatch in deferred queue.
	 * This covers the asset type validation in deferred queue.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_asset_exists_in_internal_queues
	 */
	public function test_asset_exists_in_internal_queues_with_asset_type_mismatch(): void {
		// --- Test Setup ---
		$handle     = 'test-mismatch';
		$asset_type = \Ran\PluginLib\EnqueueAccessory\AssetType::Script; // Looking for script
		$hook_name  = 'wp_footer';
		$priority   = 10;

		// Set up deferred asset with different type (Style instead of Script)
		$reflection = new \ReflectionClass($this->instance);
		$property   = $reflection->getProperty('deferred_assets');
		$property->setAccessible(true);

		$deferred_assets = array(
			$hook_name => array(
				$priority => array(
					0 => array(
						'handle' => $handle,
						'type'   => \Ran\PluginLib\EnqueueAccessory\AssetType::Style, // Different type
						'src'    => 'test.css'
					)
				)
			)
		);

		$property->setValue($this->instance, $deferred_assets);

		// Mock get_assets_info to return empty queues
		$this->instance->shouldReceive('get_assets_info')
			->andReturn(array(
				'assets'          => array(),
				'external_inline' => array()
			));

		// --- Act ---
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_asset_exists_in_internal_queues',
			array($handle, $asset_type)
		);

		// --- Assert ---
		$this->assertIsArray($result);
		$this->assertEmpty($result, 'Should not find asset when type does not match');
	}

	/**
	 * Test _asset_exists_in_internal_queues with malformed deferred asset (missing handle).
	 * This covers the handle validation in deferred queue.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_asset_exists_in_internal_queues
	 */
	public function test_asset_exists_in_internal_queues_with_missing_handle_in_deferred(): void {
		// --- Test Setup ---
		$handle     = 'test-handle';
		$asset_type = \Ran\PluginLib\EnqueueAccessory\AssetType::Script;
		$hook_name  = 'wp_footer';
		$priority   = 10;

		// Set up deferred asset without handle
		$reflection = new \ReflectionClass($this->instance);
		$property   = $reflection->getProperty('deferred_assets');
		$property->setAccessible(true);

		$deferred_assets = array(
			$hook_name => array(
				$priority => array(
					0 => array(
						// Missing 'handle' key
						'type' => $asset_type,
						'src'  => 'test.js'
					)
				)
			)
		);

		$property->setValue($this->instance, $deferred_assets);

		// Mock get_assets_info to return empty queues
		$this->instance->shouldReceive('get_assets_info')
			->andReturn(array(
				'assets'          => array(),
				'external_inline' => array()
			));

		// --- Act ---
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_asset_exists_in_internal_queues',
			array($handle, $asset_type)
		);

		// --- Assert ---
		$this->assertIsArray($result);
		$this->assertEmpty($result, 'Should not find asset when handle is missing from deferred asset definition');
	}

	/**
	 * Test _asset_exists_in_internal_queues with malformed deferred asset (missing type).
	 * This covers the type validation in deferred queue.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_asset_exists_in_internal_queues
	 */
	public function test_asset_exists_in_internal_queues_with_missing_type_in_deferred(): void {
		// --- Test Setup ---
		$handle     = 'test-handle';
		$asset_type = \Ran\PluginLib\EnqueueAccessory\AssetType::Script;
		$hook_name  = 'wp_footer';
		$priority   = 10;

		// Set up deferred asset without type
		$reflection = new \ReflectionClass($this->instance);
		$property   = $reflection->getProperty('deferred_assets');
		$property->setAccessible(true);

		$deferred_assets = array(
			$hook_name => array(
				$priority => array(
					0 => array(
						'handle' => $handle,
						// Missing 'type' key
						'src' => 'test.js'
					)
				)
			)
		);

		$property->setValue($this->instance, $deferred_assets);

		// Mock get_assets_info to return empty queues
		$this->instance->shouldReceive('get_assets_info')
			->andReturn(array(
				'assets'          => array(),
				'external_inline' => array()
			));

		// --- Act ---
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_asset_exists_in_internal_queues',
			array($handle, $asset_type)
		);

		// --- Assert ---
		$this->assertIsArray($result);
		$this->assertEmpty($result, 'Should not find asset when type is missing from deferred asset definition');
	}

	/**
	 * Test _asset_exists_in_internal_queues finds asset in multiple queue types.
	 * This covers finding the same asset in both assets and deferred queues.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_asset_exists_in_internal_queues
	 */
	public function test_asset_exists_in_internal_queues_finds_asset_in_multiple_queues(): void {
		// --- Test Setup ---
		$handle     = 'test-multi-queue';
		$asset_type = \Ran\PluginLib\EnqueueAccessory\AssetType::Script;
		$hook_name  = 'wp_footer';
		$priority   = 10;

		// Set up deferred asset
		$reflection = new \ReflectionClass($this->instance);
		$property   = $reflection->getProperty('deferred_assets');
		$property->setAccessible(true);

		$deferred_assets = array(
			$hook_name => array(
				$priority => array(
					0 => array(
						'handle' => $handle,
						'type'   => $asset_type,
						'src'    => 'test.js'
					)
				)
			)
		);

		$property->setValue($this->instance, $deferred_assets);

		// Mock get_assets_info to return asset in both assets and external_inline queues
		$this->instance->shouldReceive('get_assets_info')
			->andReturn(array(
				'assets' => array(
					$handle => array(
						'handle' => $handle,
						'src'    => 'test.js'
					)
				),
				'external_inline' => array(
					'wp_head' => array(
						5 => array(
							$handle => array(
								'content' => 'console.log("test");'
							)
						)
					)
				)
			));

		// --- Act ---
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_asset_exists_in_internal_queues',
			array($handle, $asset_type)
		);

		// --- Assert ---
		$this->assertIsArray($result);
		$this->assertCount(3, $result, 'Should find asset in all three queue types');

		// Check assets queue location
		$assets_location = array_filter($result, function($location) {
			return $location['queue_type'] === 'assets';
		});
		$this->assertCount(1, $assets_location, 'Should find one location in assets queue');

		// Check deferred queue location
		$deferred_location = array_filter($result, function($location) {
			return $location['queue_type'] === 'deferred';
		});
		$this->assertCount(1, $deferred_location, 'Should find one location in deferred queue');

		// Check external_inline queue location
		$external_location = array_filter($result, function($location) {
			return $location['queue_type'] === 'external_inline';
		});
		$this->assertCount(1, $external_location, 'Should find one location in external_inline queue');
	}

	/**
	 * Test _asset_exists_in_internal_queues with empty external_inline queue structure.
	 * This covers the external_inline queue when it has no matching assets.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_asset_exists_in_internal_queues
	 */
	public function test_asset_exists_in_internal_queues_with_empty_external_inline(): void {
		// --- Test Setup ---
		$handle     = 'test-handle';
		$asset_type = \Ran\PluginLib\EnqueueAccessory\AssetType::Script;

		// Mock get_assets_info with empty external_inline structure
		$this->instance->shouldReceive('get_assets_info')
			->andReturn(array(
				'assets'          => array(),
				'external_inline' => array(
					'wp_head' => array(
						10 => array(
							'other-handle' => array('content' => 'test')
						)
					)
				)
			));

		// --- Act ---
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_asset_exists_in_internal_queues',
			array($handle, $asset_type)
		);

		// --- Assert ---
		$this->assertIsArray($result);
		$this->assertEmpty($result, 'Should return empty array when asset not found in external_inline queue');
	}

	// ------------------------------------------------------------------------
	// _normalize_deregister_input() Tests
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_normalize_deregister_input
	 */
	public function test_normalize_deregister_input_with_string(): void {
		// --- Test Setup ---
		$input = 'test-handle';

		// --- Act ---
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_normalize_deregister_input',
			array($input)
		);

		// --- Assert ---
		$this->assertIsArray($result);
		$this->assertCount(1, $result, 'Should return array with one item');
		$this->assertArrayHasKey(0, $result);
		$this->assertIsArray($result[0]);
		$this->assertArrayHasKey('handle', $result[0]);
		$this->assertEquals('test-handle', $result[0]['handle']);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_normalize_deregister_input
	 */
	public function test_normalize_deregister_input_with_array(): void {
		// --- Test Setup ---
		$input = array(
			'handle'   => 'test-handle',
			'hook'     => 'admin_enqueue_scripts',
			'priority' => 20
		);

		// --- Act ---
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_normalize_deregister_input',
			array($input)
		);

		// --- Assert ---
		$this->assertIsArray($result);
		$this->assertCount(1, $result, 'Should return array with one item');
		$this->assertArrayHasKey(0, $result);
		$this->assertIsArray($result[0]);
		$this->assertEquals('test-handle', $result[0]['handle']);
		$this->assertEquals('admin_enqueue_scripts', $result[0]['hook']);
		$this->assertEquals(20, $result[0]['priority']);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_normalize_deregister_input
	 */
	public function test_normalize_deregister_input_with_invalid_type(): void {
		// --- Test Setup ---
		$input = 42; // Invalid type (integer)

		// --- Act & Assert ---
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid input for deregister(). Expected string, array of strings, or array of asset definitions, got integer.');

		$this->_invoke_protected_method(
			$this->instance,
			'_normalize_deregister_input',
			array($input)
		);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_normalize_deregister_input
	 */
	public function test_normalize_deregister_input_with_invalid_array(): void {
		// --- Test Setup ---
		$input = array('priority' => 10); // Missing required 'handle' key

		// --- Act ---
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_normalize_deregister_input',
			array($input)
		);

		// --- Assert ---
		$this->assertIsArray($result);
		$this->assertEmpty($result, 'Should return empty array for invalid input');
	}

	/**
	 * Test _normalize_deregister_input with mixed array types.
	 * This covers the foreach loop processing mixed string/array items.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_normalize_deregister_input
	 */
	public function test_normalize_deregister_input_with_mixed_array_types(): void {
		// --- Test Setup ---
		$input = array(
			'string-handle',
			array(
				'handle'   => 'array-handle',
				'hook'     => 'wp_enqueue_scripts',
				'priority' => 15
			),
			'another-string-handle'
		);

		// --- Act ---
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_normalize_deregister_input',
			array($input)
		);

		// --- Assert ---
		$this->assertIsArray($result);
		$this->assertCount(3, $result, 'Should return array with three items');

		// First item (string)
		$this->assertEquals('string-handle', $result[0]['handle']);

		// Second item (array)
		$this->assertEquals('array-handle', $result[1]['handle']);
		$this->assertEquals('wp_enqueue_scripts', $result[1]['hook']);
		$this->assertEquals(15, $result[1]['priority']);

		// Third item (string)
		$this->assertEquals('another-string-handle', $result[2]['handle']);
	}

	/**
	 * Test _normalize_deregister_input with invalid items in array.
	 * This covers the invalid item logging path.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_normalize_deregister_input
	 */
	public function test_normalize_deregister_input_with_invalid_items_in_array(): void {
		// --- Test Setup ---
		$input = array(
			'valid-handle',
			123, // Invalid integer
			true, // Invalid boolean
			array('invalid' => 'no-handle'), // Invalid array (no handle)
			array('handle' => 123), // Invalid array (non-string handle)
			null, // Invalid null
			'another-valid-handle'
		);
		$expected_context = 'Ran\\PluginLib\\EnqueueAccessory\\AssetEnqueueBaseTrait::_normalize_deregister_input';

		// --- Act ---
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_normalize_deregister_input',
			array($input)
		);

		// --- Assert ---
		$this->assertIsArray($result);
		$this->assertCount(2, $result, 'Should return array with only valid items');

		// Valid items should be preserved
		$this->assertEquals('valid-handle', $result[0]['handle']);
		$this->assertEquals('another-valid-handle', $result[1]['handle']);

		// Verify warning logs for invalid items
		$this->expectLog('warning', "{$expected_context} - Invalid input type at index 1. Expected string or array, got integer.", 1);
		$this->expectLog('warning', "{$expected_context} - Invalid input type at index 2. Expected string or array, got boolean.", 1);
		$this->expectLog('warning', "{$expected_context} - Invalid input type at index 3. Expected string or array, got array.", 1);
		$this->expectLog('warning', "{$expected_context} - Invalid input type at index 4. Expected string or array, got array.", 1);
		$this->expectLog('warning', "{$expected_context} - Invalid input type at index 5. Expected string or array, got NULL.", 1);
	}

	/**
	 * Test _normalize_deregister_input with logger inactive.
	 * This covers the logger inactive scenario during invalid item processing.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_normalize_deregister_input
	 */
	public function test_normalize_deregister_input_with_logger_inactive(): void {
		// --- Test Setup ---
		$input = array(
			'valid-handle',
			123, // Invalid integer
			'another-valid-handle'
		);

		// Mock logger to be inactive
		$logger = \Mockery::mock('Ran\\PluginLib\\Util\\Logger');
		$logger->shouldReceive('is_active')
			->andReturn(false);
		$logger->shouldNotReceive('warning'); // Should not be called when inactive

		$this->instance->shouldReceive('get_logger')
			->andReturn($logger);

		// --- Act ---
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_normalize_deregister_input',
			array($input)
		);

		// --- Assert ---
		$this->assertIsArray($result);
		$this->assertCount(2, $result, 'Should return array with only valid items');
		$this->assertEquals('valid-handle', $result[0]['handle']);
		$this->assertEquals('another-valid-handle', $result[1]['handle']);
	}

	/**
	 * Test _normalize_deregister_input with empty array.
	 * This covers the empty array input scenario.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_normalize_deregister_input
	 */
	public function test_normalize_deregister_input_with_empty_array(): void {
		// --- Test Setup ---
		$input = array();

		// --- Act ---
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_normalize_deregister_input',
			array($input)
		);

		// --- Assert ---
		$this->assertIsArray($result);
		$this->assertEmpty($result, 'Should return empty array for empty input');
	}

	/**
	 * Test _normalize_deregister_input with array containing only invalid items.
	 * This covers the scenario where all items in array are invalid.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_normalize_deregister_input
	 */
	public function test_normalize_deregister_input_with_all_invalid_items(): void {
		// --- Test Setup ---
		$input = array(
			123, // Invalid integer
			true, // Invalid boolean
			array('no-handle' => 'value'), // Invalid array
			null // Invalid null
		);
		$expected_context = 'Ran\\PluginLib\\EnqueueAccessory\\AssetEnqueueBaseTrait::_normalize_deregister_input';

		// --- Act ---
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_normalize_deregister_input',
			array($input)
		);

		// --- Assert ---
		$this->assertIsArray($result);
		$this->assertEmpty($result, 'Should return empty array when all items are invalid');

		// Verify warning logs for all invalid items
		$this->expectLog('warning', "{$expected_context} - Invalid input type at index 0. Expected string or array, got integer.", 1);
		$this->expectLog('warning', "{$expected_context} - Invalid input type at index 1. Expected string or array, got boolean.", 1);
		$this->expectLog('warning', "{$expected_context} - Invalid input type at index 2. Expected string or array, got array.", 1);
		$this->expectLog('warning', "{$expected_context} - Invalid input type at index 3. Expected string or array, got NULL.", 1);
	}

	/**
	 * Test _normalize_deregister_input with array having non-string handle.
	 * This covers the specific edge case where isset($item['handle']) is true but is_string($item['handle']) is false.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_normalize_deregister_input
	 */
	public function test_normalize_deregister_input_with_non_string_handle(): void {
		// --- Test Setup ---
		$input = array(
			'valid-handle',
			array('handle' => 123), // Array with handle key but non-string value
			array('handle' => null), // Array with handle key but null value
			array('handle' => true), // Array with handle key but boolean value
			array('handle' => array()), // Array with handle key but array value
			'another-valid-handle'
		);
		$expected_context = 'Ran\\PluginLib\\EnqueueAccessory\\AssetEnqueueBaseTrait::_normalize_deregister_input';

		// --- Act ---
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_normalize_deregister_input',
			array($input)
		);

		// --- Assert ---
		$this->assertIsArray($result);
		$this->assertCount(2, $result, 'Should return array with only valid items');

		// Valid items should be preserved
		$this->assertEquals('valid-handle', $result[0]['handle']);
		$this->assertEquals('another-valid-handle', $result[1]['handle']);

		// Verify warning logs for arrays with non-string handles
		$this->expectLog('warning', "{$expected_context} - Invalid input type at index 1. Expected string or array, got array.", 1);
		$this->expectLog('warning', "{$expected_context} - Invalid input type at index 2. Expected string or array, got array.", 1);
		$this->expectLog('warning', "{$expected_context} - Invalid input type at index 3. Expected string or array, got array.", 1);
		$this->expectLog('warning', "{$expected_context} - Invalid input type at index 4. Expected string or array, got array.", 1);
	}

	// ------------------------------------------------------------------------
	// Comprehensive Deferred Asset Replace Flag Integration Tests
	// ------------------------------------------------------------------------

	/**
	 * Test that deferred assets with replace flag are processed correctly by _enqueue_deferred_assets.
	 * This verifies that the replace flag is passed through the deferred asset processing chain.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_enqueue_deferred_assets
	 */
	public function test_deferred_asset_replace_flag_integration_complete_flow(): void {
		// --- Test Setup ---
		$handle     = 'deferred-script-with-replace';
		$src        = 'path/to/deferred-script.js';
		$hook_name  = 'wp_footer';
		$priority   = 15;
		$asset_type = \Ran\PluginLib\EnqueueAccessory\AssetType::Script;

		// Create a deferred asset with replace flag
		$deferred_asset = array(
			'handle'  => $handle,
			'src'     => $src,
			'replace' => true
		);

		// Set up the deferred assets property
		$reflection = new \ReflectionClass($this->instance);
		$property   = $reflection->getProperty('deferred_assets');
		$property->setAccessible(true);
		$property->setValue($this->instance, array(
			$hook_name => array(
				$priority => array($deferred_asset)
			)
		));

		// Mock _process_single_asset to verify it's called with the deferred asset containing replace flag
		$this->instance->shouldReceive('_process_single_asset')
			->with($asset_type, $deferred_asset, Mockery::type('string'), $hook_name, true, true)
			->once()
			->andReturn($handle);

		// --- Act ---
		$this->_invoke_protected_method($this->instance, '_enqueue_deferred_assets', array($asset_type, $hook_name, $priority));

		// --- Assert ---
		// Verify the deferred assets were removed after processing
		$this->assertEmpty($property->getValue($this->instance), 'Deferred assets should be empty after processing');
	}

	/**
	 * Test deferred asset with replace flag is processed correctly.
	 * This verifies that the replace flag is passed through to _process_single_asset.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_enqueue_deferred_assets
	 */
	public function test_deferred_asset_replace_flag_with_nonexistent_target(): void {
		// --- Test Setup ---
		$handle     = 'nonexistent-deferred-script';
		$src        = 'path/to/script.js';
		$hook_name  = 'wp_footer';
		$priority   = 10;
		$asset_type = \Ran\PluginLib\EnqueueAccessory\AssetType::Script;

		$deferred_asset = array(
			'handle'  => $handle,
			'src'     => $src,
			'replace' => true
		);

		// Set up the deferred assets property
		$reflection = new \ReflectionClass($this->instance);
		$property   = $reflection->getProperty('deferred_assets');
		$property->setAccessible(true);
		$property->setValue($this->instance, array(
			$hook_name => array(
				$priority => array($deferred_asset)
			)
		));

		// Mock _process_single_asset to verify it's called with the deferred asset containing replace flag
		$this->instance->shouldReceive('_process_single_asset')
			->with($asset_type, $deferred_asset, Mockery::type('string'), $hook_name, true, true)
			->once()
			->andReturn($handle);

		// --- Act ---
		$this->_invoke_protected_method($this->instance, '_enqueue_deferred_assets', array($asset_type, $hook_name, $priority));

		// --- Assert ---
		// Verify the deferred assets were removed after processing
		$this->assertEmpty($property->getValue($this->instance), 'Deferred assets should be empty after processing');
	}

	/**
	 * Test deferred style asset with replace flag.
	 * This verifies that the replace flag works for style assets in deferred processing.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_enqueue_deferred_assets
	 */
	public function test_deferred_asset_replace_flag_with_protected_asset(): void {
		// --- Test Setup ---
		$handle     = 'deferred-style-with-replace';
		$src        = 'path/to/style.css';
		$hook_name  = 'wp_head';
		$priority   = 10;
		$asset_type = \Ran\PluginLib\EnqueueAccessory\AssetType::Style;

		$deferred_asset = array(
			'handle'  => $handle,
			'src'     => $src,
			'replace' => true
		);

		// Set up the deferred assets property
		$reflection = new \ReflectionClass($this->instance);
		$property   = $reflection->getProperty('deferred_assets');
		$property->setAccessible(true);
		$property->setValue($this->instance, array(
			$hook_name => array(
				$priority => array($deferred_asset)
			)
		));

		// Mock _process_single_asset to verify it's called with the deferred asset containing replace flag
		$this->instance->shouldReceive('_process_single_asset')
			->with($asset_type, $deferred_asset, Mockery::type('string'), $hook_name, true, true)
			->once()
			->andReturn($handle);

		// --- Act ---
		$this->_invoke_protected_method($this->instance, '_enqueue_deferred_assets', array($asset_type, $hook_name, $priority));

		// --- Assert ---
		// Verify the deferred assets were removed after processing
		$this->assertEmpty($property->getValue($this->instance), 'Deferred assets should be empty after processing');
	}

	/**
	 * Test multiple deferred assets with replace flags on the same hook.
	 * This verifies that multiple assets with replace flags are processed correctly.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_enqueue_deferred_assets
	 */
	public function test_multiple_deferred_assets_with_replace_flags(): void {
		// --- Test Setup ---
		$hook_name  = 'wp_footer';
		$priority   = 10;
		$asset_type = \Ran\PluginLib\EnqueueAccessory\AssetType::Script;

		$asset1 = array(
			'handle'  => 'first-deferred-script',
			'src'     => 'path/to/first.js',
			'replace' => true
		);

		$asset2 = array(
			'handle'  => 'second-deferred-script',
			'src'     => 'path/to/second.js',
			'replace' => true
		);

		// Set up the deferred assets property
		$reflection = new \ReflectionClass($this->instance);
		$property   = $reflection->getProperty('deferred_assets');
		$property->setAccessible(true);
		$property->setValue($this->instance, array(
			$hook_name => array(
				$priority => array($asset1, $asset2)
			)
		));

		// Mock _process_single_asset to verify it's called for both assets with replace flags
		$this->instance->shouldReceive('_process_single_asset')
			->with($asset_type, $asset1, Mockery::type('string'), $hook_name, true, true)
			->once()
			->andReturn($asset1['handle']);

		$this->instance->shouldReceive('_process_single_asset')
			->with($asset_type, $asset2, Mockery::type('string'), $hook_name, true, true)
			->once()
			->andReturn($asset2['handle']);

		// --- Act ---
		$this->_invoke_protected_method($this->instance, '_enqueue_deferred_assets', array($asset_type, $hook_name, $priority));

		// --- Assert ---
		// Verify the deferred assets were removed after processing
		$this->assertEmpty($property->getValue($this->instance), 'Deferred assets should be empty after processing');
	}

	/**
	 * Test deferred style asset with replace flag.
	 * This verifies that the replace flag works for style assets in deferred processing.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_enqueue_deferred_assets
	 */
	public function test_deferred_style_asset_with_replace_flag(): void {
		// --- Test Setup ---
		$handle     = 'deferred-style-with-replace';
		$src        = 'path/to/style.css';
		$hook_name  = 'wp_head';
		$priority   = 10;
		$asset_type = \Ran\PluginLib\EnqueueAccessory\AssetType::Style;

		$deferred_asset = array(
			'handle'  => $handle,
			'src'     => $src,
			'replace' => true
		);

		// Set up the deferred assets property
		$reflection = new \ReflectionClass($this->instance);
		$property   = $reflection->getProperty('deferred_assets');
		$property->setAccessible(true);
		$property->setValue($this->instance, array(
			$hook_name => array(
				$priority => array($deferred_asset)
			)
		));

		// Mock _process_single_asset to verify it's called with the deferred asset containing replace flag
		$this->instance->shouldReceive('_process_single_asset')
			->with($asset_type, $deferred_asset, Mockery::type('string'), $hook_name, true, true)
			->once()
			->andReturn($handle);

		// --- Act ---
		$this->_invoke_protected_method($this->instance, '_enqueue_deferred_assets', array($asset_type, $hook_name, $priority));

		// --- Assert ---
		// Verify the deferred assets were removed after processing
		$this->assertEmpty($property->getValue($this->instance), 'Deferred assets should be empty after processing');
	}
}
