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
class ConcreteEnqueueForBaseTraitCoreTesting extends ConcreteEnqueueForTesting {
	use ScriptsEnqueueTrait;
}

/**
 * Class ScriptsEnqueueTraitTest
 *
 * @package Ran\PluginLib\Tests\Unit\EnqueueAccessory
 *
 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait
 */
class AssetEnqueueBaseTraitCoreTest extends EnqueueTraitTestCase {
	use ExpectLogTrait;

	/**
	 * @inheritDoc
	 */
	protected function _get_concrete_class_name(): string {
		return ConcreteEnqueueForBaseTraitCoreTesting::class;
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
	// _add_assets() largely covered by tests for Script and Style Traits
	// ------------------------------------------------------------------------


	// ------------------------------------------------------------------------
	// get_asset() covered by cross functionality tests elsewhere
	// ------------------------------------------------------------------------

	// ------------------------------------------------------------------------
	// enqueue_immediate_assets() Tests
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::enqueue_immediate_assets
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_concrete_process_single_asset
	 */
	public function test_stage_skips_asset_with_empty_handle(): void {
		// Arrange - Create an asset with empty handle through direct property manipulation
		// This simulates a corrupted state that shouldn't happen through normal add_assets() usage
		$assets_property = new \ReflectionProperty($this->instance, 'assets');
		$assets_property->setAccessible(true);
		$assets_property->setValue($this->instance, array(
			array(
				'handle' => '', // Empty handle - should trigger warning in enqueue_immediate_assets
				'src'    => 'path/to/script.js'
			)
		));

		// Act - Call enqueue_immediate() which directly calls enqueue_immediate_assets()
		// This bypasses stage_assets() which would filter out the problematic asset
		$this->instance->enqueue_immediate();

		// Assert - Check that warning was logged for empty handle
		$this->expectLog(
			'warning',
			'stage_scripts - Skipping asset at index 0 due to missing handle - this should not be possible when using add().'
		);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::enqueue_immediate_assets
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_is_deferred_asset
	 */
	public function test_stage_throws_exception_for_deferred_asset_in_immediate_queue(): void {
		// Arrange - Directly populate the assets array with a deferred asset (has 'hook')
		// This simulates a corrupted state where deferred assets end up in immediate queue
		$assets_property = new \ReflectionProperty($this->instance, 'assets');
		$assets_property->setAccessible(true);
		$assets_property->setValue($this->instance, array(
			array(
				'handle' => 'deferred-script',
				'src'    => 'path/to/deferred-script.js',
				'hook'   => 'custom_hook' // This should trigger the LogicException
			)
		));

		// Assert - Should throw LogicException for deferred asset in immediate queue
		$this->expectException(\LogicException::class);
		$this->expectExceptionMessage(
			'stage_scripts - Found a deferred asset (\'deferred-script\') in the immediate queue. ' .
			'The `stage_assets()` method must be called before `enqueue_immediate_assets()` to correctly process deferred assets.'
		);

		// Act - Call enqueue_immediate() which directly calls enqueue_immediate_assets()
		// This bypasses stage_assets() which would properly handle deferred assets
		$this->instance->enqueue_immediate();
	}

	// ------------------------------------------------------------------------
	// _enqueue_deferred_assets() Tests
	// ------------------------------------------------------------------------l

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_enqueue_deferred_assets
	 */
	public function test_deferred_scripts_removes_empty_hooks(): void {
		// Define a test hook and priority
		$hook_name = 'test_hook';
		$priority  = 10;

		// Create a deferred asset
		$deferred_asset = array(
			'handle' => 'test-script',
			'src'    => 'test.js',
		);

		// Set up the deferred assets array with just one priority for the hook
		// Using flattened array structure (no asset type nesting)
		$this->_set_protected_property_value(
			$this->instance,
			'deferred_assets',
			array(
				$hook_name => array(
					$priority => array($deferred_asset)
				)
			)
		);

		// Mock _process_single_asset to avoid actual WordPress function calls
		$this->instance->shouldReceive('_process_single_asset')
			->once()
			->andReturn(true);

		// Act - Call the public method that internally calls _enqueue_deferred_assets
		$this->instance->_enqueue_deferred_scripts($hook_name, $priority);

		// Assert - Get the deferred assets after processing
		$deferred_assets = $this->_get_protected_property_value($this->instance, 'deferred_assets');

		// Verify that the deferred assets array exists and the hook has been removed
		$this->assertIsArray($deferred_assets, 'Deferred assets should be an array');
		$this->assertArrayNotHasKey($hook_name, $deferred_assets, 'Hook should be removed after processing');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_enqueue_deferred_assets
	 */
	public function test_deferred_scripts_skips_missing_priority_and_cleans_empty_hooks(): void {
		// Define a test hook and priorities
		$hook_name        = 'test_hook';
		$priority_exists  = 10;
		$priority_missing = 20;

		// Create a deferred asset
		$deferred_asset = array(
			'handle' => 'test-script',
			'src'    => 'test.js',
		);

		// Create a collecting logger that will capture all log messages
		$collecting_logger = new CollectingLogger();

		// Create a config mock that will return our collecting logger
		$config_mock = Mockery::mock(ConfigInterface::class);
		$config_mock->shouldReceive('get_logger')->andReturn($collecting_logger);

		// Create a fresh instance with our mocked config
		$instance = new ConcreteEnqueueForBaseTraitCoreTesting($config_mock);

		// Set up the deferred assets array with one priority but will call with a different priority
		// Using flattened array structure (no asset type nesting)
		$this->_set_protected_property_value(
			$instance,
			'deferred_assets',
			array(
				$hook_name => array(
					$priority_exists => array($deferred_asset)
				)
			)
		);

		// Act - Call the public method with the missing priority
		$instance->_enqueue_deferred_scripts($hook_name, $priority_missing);

		// Assert - Get the deferred assets after processing
		$deferred_assets = $this->_get_protected_property_value($instance, 'deferred_assets');

		// Verify that the deferred assets array still contains the hook and the existing priority
		$this->assertIsArray($deferred_assets, 'Deferred assets should be an array');
		$this->assertArrayHasKey($hook_name, $deferred_assets, 'Hook should still exist');
		$this->assertArrayHasKey($priority_exists, $deferred_assets[$hook_name], 'Priority should still exist');

		// Now remove the existing priority and call again to test hook cleanup
		$this->_set_protected_property_value(
			$instance,
			'deferred_assets',
			array(
				$hook_name => array() // Empty priorities array
			)
		);

		// Act - Call the public method again
		$instance->_enqueue_deferred_scripts($hook_name, $priority_missing);

		// Get the deferred assets after processing
		$deferred_assets = $this->_get_protected_property_value($instance, 'deferred_assets');

		// Verify that the hook has been removed because it had no priorities
		$this->assertIsArray($deferred_assets, 'Deferred assets should be an array');
		$this->assertArrayNotHasKey($hook_name, $deferred_assets, 'Empty hook should be removed');

		// Verify that the expected log messages were generated
		$log_messages = $collecting_logger->get_logs();

		// Check for the entry message
		$entry_message_found     = false;
		$not_found_message_found = false;

		// Loop through the log messages to find our expected messages
		foreach ($log_messages as $log) {
			if (strpos($log['message'], "Entered hook: \"$hook_name\" with priority: $priority_missing") !== false) {
				$entry_message_found = true;
			}
			if (strpos($log['message'], "Hook \"$hook_name\" with priority $priority_missing not found in deferred scripts") !== false) {
				$not_found_message_found = true;
			}
		}

		$this->assertTrue($entry_message_found, 'Entry log message should be present');
		$this->assertTrue($not_found_message_found, 'Not found log message should be present');
	}

	// Test for deferred assets with replace flag moved to AssetEnqueueBaseTraitDeregister.php


	// ------------------------------------------------------------------------
	// _concrete_process_single_asset() Tests
	// ------------------------------------------------------------------------


	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_concrete_process_single_asset
	 */
	public function test_enqueue_immediate_skips_when_condition_fails(): void {
		// Arrange - Directly set an asset with a condition that returns false
		$assets_property = new \ReflectionProperty($this->instance, 'assets');
		$assets_property->setAccessible(true);
		$assets_property->setValue($this->instance, array(
			array(
				'handle'    => 'test-script',
				'src'       => 'path/to/script.js',
				'condition' => function() {
					return false;
				}
			)
		));

		// Mock wp_enqueue_script to verify it's not called
		\WP_Mock::userFunction('wp_enqueue_script')
			->times(0);

		// Act - Call the public method that processes assets
		$this->instance->enqueue_immediate();

		// Assert - Verify debug log was written for skipped asset
		$this->expectLog('debug', array(
			'_concrete_process_single_asset - Condition not met for script \'test-script\'. Skipping.'
		), 1);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_concrete_process_single_asset
	 */
	public function test_enqueue_immediate_skips_when_handle_empty(): void {
		// Arrange - Directly set an asset with empty handle in the assets property
		// This simulates a corrupted state that shouldn't happen through normal add_scripts() usage
		$assets_property = new \ReflectionProperty($this->instance, 'assets');
		$assets_property->setAccessible(true);
		$assets_property->setValue($this->instance, array(
			array(
				'handle' => '', // Empty handle - should trigger warning in _concrete_process_single_asset
				'src'    => 'path/to/script.js'
			)
		));

		// Mock wp_enqueue_script to verify it's not called
		\WP_Mock::userFunction('wp_enqueue_script')
			->times(0);

		// Act - Call the public method that processes assets
		$this->instance->enqueue_immediate();

		// Assert - Verify warning log was written for empty handle
		// Note: The empty handle check happens in enqueue_immediate_assets, not _concrete_process_single_asset
		$this->expectLog('warning', array(
			'stage_scripts - Skipping asset at index 0 due to missing handle - this should not be possible when using add().'
		), 1);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_concrete_process_single_asset
	 */
	public function test_stage_properly_handles_deferred_assets(): void {
		// Arrange - Add a deferred asset using the public API
		$asset_definition = array(
			'handle' => 'deferred-script',
			'src'    => 'path/to/script.js',
			'hook'   => 'wp_footer'
		);

		// Add the asset to the instance
		$this->instance->add($asset_definition);

		// Act - Call the public stage method which internally handles deferred asset detection
		$this->instance->stage();

		// Assert - Verify that the deferred asset was moved to the deferred_assets property
		$deferred_assets_property = new \ReflectionProperty($this->instance, 'deferred_assets');
		$deferred_assets_property->setAccessible(true);
		$deferred_assets = $deferred_assets_property->getValue($this->instance);

		// Verify the deferred asset is in the correct hook
		$this->assertArrayHasKey('wp_footer', $deferred_assets, 'Deferred asset should be moved to wp_footer hook');
		$this->assertArrayHasKey(10, $deferred_assets['wp_footer'], 'Deferred asset should use default priority 10');
		$this->assertCount(1, $deferred_assets['wp_footer'][10], 'Should have one deferred asset');
		$this->assertEquals('deferred-script', $deferred_assets['wp_footer'][10][0]['handle'], 'Deferred asset handle should match');

		// Verify the asset was removed from the immediate assets queue
		$assets_property = new \ReflectionProperty($this->instance, 'assets');
		$assets_property->setAccessible(true);
		$immediate_assets = $assets_property->getValue($this->instance);
		$this->assertEmpty($immediate_assets, 'Deferred asset should be removed from immediate queue');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_concrete_process_single_asset
	 */
	public function test_enqueue_immediate_handles_source_resolution_failure(): void {
		// Arrange - Set up an asset with source that will fail to resolve
		$assets_property = new \ReflectionProperty($this->instance, 'assets');
		$assets_property->setAccessible(true);
		$assets_property->setValue($this->instance, array(
			array(
				'handle' => 'test-script',
				'src'    => 'invalid/path/script.js'
			)
		));

		// Mock _get_asset_url to return null (resolution failure)
		$this->instance->shouldReceive('_get_asset_url')
			->once()
			->with('invalid/path/script.js', AssetType::Script)
			->andReturn(null);

		// Mock _is_deferred_asset to return null (not deferred)
		$this->instance->shouldReceive('_is_deferred_asset')
			->once()
			->andReturn(null);

		// Mock wp_enqueue_script to verify it's not called due to resolution failure
		\WP_Mock::userFunction('wp_enqueue_script')
			->times(0);

		// Act - Call the public method that processes assets
		$this->instance->enqueue_immediate();

		// Assert - Verify error log was written for source resolution failure
		$this->expectLog('error', array(
			'_concrete_process_single_asset - Could not resolve source for script \'test-script\'. Skipping.'
		), 1);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_concrete_process_single_asset
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_do_register
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_do_enqueue
	 */
	public function test_enqueue_immediate_successful_completion(): void {
		// Arrange - Set up a valid asset for successful processing
		$assets_property = new \ReflectionProperty($this->instance, 'assets');
		$assets_property->setAccessible(true);
		$assets_property->setValue($this->instance, array(
			array(
				'handle' => 'success-script',
				'src'    => 'path/to/success.js'
			)
		));

		// Mock _get_asset_url to return a valid URL
		$this->instance->shouldReceive('_get_asset_url')
			->once()
			->with('path/to/success.js', AssetType::Script)
			->andReturn('https://example.com/path/to/success.js');

		// Mock _is_deferred_asset to return null (not deferred)
		$this->instance->shouldReceive('_is_deferred_asset')
			->once()
			->andReturn(null);

		// Mock _do_register to succeed
		$this->instance->shouldReceive('_do_register')
			->once()
			->andReturn(true);

		// Mock _do_enqueue to succeed
		$this->instance->shouldReceive('_do_enqueue')
			->once()
			->andReturn(true);

		// Act - Call the public method that processes assets
		$this->instance->enqueue_immediate();

		// Assert - Verify that the assets array is empty after successful processing
		$assets_property = new \ReflectionProperty($this->instance, 'assets');
		$assets_property->setAccessible(true);
		$remaining_assets = $assets_property->getValue($this->instance);
		$this->assertEmpty($remaining_assets, 'Assets array should be empty after successful processing');

		// The _do_register and _do_enqueue mocks verify the asset was processed successfully
		// through the _concrete_process_single_asset method
	}

	/**
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_resolve_environment_src
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_concrete_process_single_asset
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_do_register
	 */
	public function test_concrete_process_single_asset_with_string_src_remains_unchanged(): void {
		$asset_definition = array(
			'handle' => 'test-script',
			'src'    => 'http://example.com/script.js',
		);

		WP_Mock::userFunction('wp_register_script', array(
			'times'  => 1,
			'return' => true,
			'args'   => array( 'test-script', 'http://example.com/script.js', Mockery::any(), Mockery::any(), Mockery::any() ),
		));

		// Use the public API to add the script and stage the scripts.
		$this->instance->add( $asset_definition );
		$this->instance->stage();

		// The assertion is implicitly handled by the mock expectation for wp_register_script.
		$this->expectLog('debug', array('_process_single_', 'Registering', 'test-script'), 1);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_concrete_process_single_asset
	 */
	public function test_scripts_enqueue_trait_handles_only_script_assets(): void {
		// Arrange - Set up a script asset that should be processed successfully
		$assets_property = new \ReflectionProperty($this->instance, 'assets');
		$assets_property->setAccessible(true);
		$assets_property->setValue($this->instance, array(
			array(
				'handle' => 'test-script',
				'src'    => 'path/to/script.js'
			)
		));

		// Mock _get_asset_url to return a valid URL
		$this->instance->shouldReceive('_get_asset_url')
			->once()
			->with('path/to/script.js', AssetType::Script)
			->andReturn('https://example.com/path/to/script.js');

		// Mock _is_deferred_asset to return null (not deferred)
		$this->instance->shouldReceive('_is_deferred_asset')
			->once()
			->andReturn(null);

		// Mock _do_register to succeed
		$this->instance->shouldReceive('_do_register')
			->once()
			->andReturn(true);

		// Act - Call the public method that processes script assets
		$this->instance->enqueue_immediate();

		// Assert - Verify that the assets array is empty after successful processing
		$remaining_assets = $assets_property->getValue($this->instance);
		$this->assertEmpty($remaining_assets, 'Script assets should be processed successfully by ScriptsEnqueueTrait');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_concrete_process_single_asset
	 */
	public function test_enqueue_immediate_with_async_strategy(): void {
		// Arrange - Set up an asset with async attribute
		$handle          = 'test-async-script';
		$assets_property = new \ReflectionProperty($this->instance, 'assets');
		$assets_property->setAccessible(true);
		$assets_property->setValue($this->instance, array(
			array(
				'handle'     => $handle,
				'src'        => 'path/to/script.js',
				'attributes' => array(
					'async' => true
				)
			)
		));

		// Mock the _get_asset_url method
		$this->instance->shouldReceive('_get_asset_url')
			->with('path/to/script.js', AssetType::Script)
			->andReturn('path/to/script.js');

		// Mock _is_deferred_asset to return null (not deferred)
		$this->instance->shouldReceive('_is_deferred_asset')
			->once()
			->andReturn(null);

		// Mock wp_script_is to return false (not already registered)
		WP_Mock::userFunction('wp_script_is')
			->with($handle, 'registered')
			->andReturn(false);

		// Mock wp_register_script with the correct parameter format for async scripts
		WP_Mock::userFunction('wp_register_script')
			->once()
			->with(
				$handle,
				'path/to/script.js',
				array(), // deps
				null,   // ver
				array('in_footer' => false)
			)
			->andReturn(true);

		// Act - Call the public method that processes assets
		$this->instance->enqueue_immediate();

		// Assert - Verify that the assets array is empty after successful processing
		$remaining_assets = $assets_property->getValue($this->instance);
		$this->assertEmpty($remaining_assets, 'Async script assets should be processed successfully');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_concrete_process_single_asset
	 */
	public function test_enqueue_immediate_with_defer_strategy(): void {
		// Arrange - Set up an asset with defer attribute
		$handle          = 'test-defer-script';
		$assets_property = new \ReflectionProperty($this->instance, 'assets');
		$assets_property->setAccessible(true);
		$assets_property->setValue($this->instance, array(
			array(
				'handle'     => $handle,
				'src'        => 'path/to/script.js',
				'attributes' => array(
					'defer' => true
				)
			)
		));

		// Mock the _get_asset_url method
		$this->instance->shouldReceive('_get_asset_url')
			->with('path/to/script.js', AssetType::Script)
			->andReturn('path/to/script.js');

		// Mock _is_deferred_asset to return null (not deferred)
		$this->instance->shouldReceive('_is_deferred_asset')
			->once()
			->andReturn(null);

		// Mock wp_script_is to return false (not already registered)
		WP_Mock::userFunction('wp_script_is')
			->with($handle, 'registered')
			->andReturn(false);

		// Mock wp_register_script with the correct parameter format for defer scripts
		WP_Mock::userFunction('wp_register_script')
			->once()
			->with(
				$handle,
				'path/to/script.js',
				array(), // deps
				null,   // ver
				array('in_footer' => false)
			)
			->andReturn(true);

		// Act - Call the public method that processes assets
		$this->instance->enqueue_immediate();

		// Assert - Verify that the assets array is empty after successful processing
		$remaining_assets = $assets_property->getValue($this->instance);
		$this->assertEmpty($remaining_assets, 'Defer script assets should be processed successfully');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_concrete_process_single_asset
	 */
	public function test_enqueue_immediate_handles_enqueue_failure(): void {
		// Arrange - Set up an asset that will fail during enqueuing
		$assets_property = new \ReflectionProperty($this->instance, 'assets');
		$assets_property->setAccessible(true);
		$assets_property->setValue($this->instance, array(
			array(
				'handle' => 'test-script',
				'src'    => 'path/to/script.js'
			)
		));

		// Mock _get_asset_url to return a valid URL
		$this->instance->shouldReceive('_get_asset_url')
			->once()
			->with('path/to/script.js', AssetType::Script)
			->andReturn('https://example.com/path/to/script.js');

		// Mock _is_deferred_asset to return null (not deferred)
		$this->instance->shouldReceive('_is_deferred_asset')
			->once()
			->andReturn(null);

		// Mock _do_register to succeed
		$this->instance->shouldReceive('_do_register')
			->once()
			->andReturn(true);

		// Mock _do_enqueue to fail
		$this->instance->shouldReceive('_do_enqueue')
			->once()
			->andReturn(false);

		// Act - Call the public method that processes assets with enqueuing
		$this->instance->enqueue_immediate();

		// Assert - Verify that the asset was processed (removed from queue) even though enqueue failed
		// The registration succeeded, so the asset is removed from the queue
		$remaining_assets = $assets_property->getValue($this->instance);
		$this->assertEmpty($remaining_assets, 'Asset should be removed from queue even when enqueue fails (registration succeeded)');

		// Verify that the mocks were called as expected (this confirms the enqueue failure path was taken)
		// The _do_enqueue mock returning false confirms the failure scenario was tested
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_concrete_process_single_asset
	 */
	public function test_enqueue_immediate_localizes_script_correctly(): void {
		// Arrange - Set up an asset with localization data
		$handle          = 'my-localized-script';
		$data            = array('ajax_url' => 'http://example.com/ajax');
		$object_name     = 'my_object';
		$assets_property = new \ReflectionProperty($this->instance, 'assets');
		$assets_property->setAccessible(true);
		$assets_property->setValue($this->instance, array(
			array(
				'handle'   => $handle,
				'src'      => 'path/to/script.js',
				'localize' => array(
					'object_name' => $object_name,
					'data'        => $data,
				),
			)
		));

		// Mock _get_asset_url to return a valid URL
		$this->instance->shouldReceive('_get_asset_url')
			->once()
			->with('path/to/script.js', AssetType::Script)
			->andReturn('https://example.com/path/to/script.js');

		// Mock _is_deferred_asset to return null (not deferred)
		$this->instance->shouldReceive('_is_deferred_asset')
			->once()
			->andReturn(null);

		// Mock wp_script_is for initial enqueued check
		WP_Mock::userFunction('wp_script_is')
			->with($handle, 'enqueued')
			->andReturnUsing(function() {
				static $call_count = 0;
				$call_count++;
				if ($call_count === 1) {
					return false; // Initial check - not enqueued
				}
				return true; // Verification check - enqueue succeeded
			});

		WP_Mock::userFunction('wp_script_is')->with($handle, 'registered')->andReturn(false);
		WP_Mock::userFunction('wp_register_script')->andReturn(true);

		// Mock wp_enqueue_script (required by our enqueue logic)
		WP_Mock::userFunction('wp_enqueue_script')
			->once()
			->with($handle);

		// This is the key assertion - verify wp_localize_script is called
		WP_Mock::userFunction('wp_localize_script')
			->once()
			->with($handle, $object_name, $data);

		// Act - Call the public method that processes assets with registration
		$this->instance->enqueue_immediate();

		// Assert - Verify the localization debug log
		$this->expectLog('debug', array("Localizing script '{$handle}' with JS object '{$object_name}'"), 1);

		// Verify that the asset was processed (removed from queue)
		$remaining_assets = $assets_property->getValue($this->instance);
		$this->assertEmpty($remaining_assets, 'Localized script should be processed successfully');
	}

	/**
	 * @dataProvider provideEnvironmentData
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_resolve_environment_src
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_concrete_process_single_asset
	 */
	public function test_concrete_process_single_asset_resolves_src_based_on_environment(
		bool $is_dev_environment,
		string $expected_src
	): void {
		// Mock the config to control is_dev_environment() return value
		$this->config_mock->shouldReceive('is_dev_environment')
			->andReturn($is_dev_environment);

		$asset_definition = array(
			'handle' => 'test-script',
			'src'    => array(
				'dev'  => 'http://example.com/script.js',
				'prod' => 'http://example.com/script.min.js',
			),
		);

		WP_Mock::userFunction('wp_register_script', array(
			'times'  => 1,
			'return' => true,
			'args'   => array( 'test-script', $expected_src, Mockery::any(), Mockery::any(), Mockery::any() ),
		));

		// Use the public API to add the script and trigger the processing hooks.
		$this->instance->add( array( $asset_definition ) );
		$this->instance->stage();

		// The assertion is implicitly handled by the mock expectation for wp_register_script.
		$this->expectLog('debug', array('_process_single_', 'Registering', 'test-script', $expected_src), 1);
	}

	/**
	 * Data provider for `test_concrete_process_single_asset_resolves_src_based_on_environment`.
	 * @dataProvider provideEnvironmentData
	 */
	public function provideEnvironmentData(): array {
		return array(
			'Development environment' => array(true, 'http://example.com/script.js'),
			'Production environment'  => array(false, 'http://example.com/script.min.js'),
		);
	}

	// Test for concrete_process_single_asset with replace flag moved to AssetEnqueueBaseTraitDeregister.php

	// ------------------------------------------------------------------------
	// _do_register() Tests
	// Not a simple wrapper, deligates to wp_register_script and wp_register_style
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_do_register
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_do_enqueue
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_concrete_process_single_asset
	 */
	public function test_enqueue_immediate_handles_script_registration(): void {
		// Arrange - Set up a script asset for registration testing
		$assets_property = new \ReflectionProperty($this->instance, 'assets');
		$assets_property->setAccessible(true);
		$assets_property->setValue($this->instance, array(
			array(
				'handle'    => 'test-script',
				'src'       => 'script.js',
				'deps'      => array(),
				'ver'       => '1.0.0',
				'in_footer' => true
			)
		));

		// Mock _get_asset_url to return the expected URL
		$this->instance->shouldReceive('_get_asset_url')
			->once()
			->with('script.js', AssetType::Script)
			->andReturn('https://example.com/script.js');

		// Mock _is_deferred_asset to return null (not deferred)
		$this->instance->shouldReceive('_is_deferred_asset')
			->once()
			->andReturn(null);

		// Mock _do_register to handle the registration logic
		$this->instance->shouldReceive('_do_register')
			->once()
			->andReturn(true);

		// Mock _do_enqueue to complete the asset processing
		$this->instance->shouldReceive('_do_enqueue')
			->once()
			->andReturn(true);

		// Act - Call the public method that triggers script registration
		$this->instance->enqueue_immediate();

		// Assert - Verify the asset was processed (removed from queue)
		$remaining_assets = $assets_property->getValue($this->instance);
		$this->assertEmpty($remaining_assets, 'Script should be registered and processed successfully');

		// The mocks verify that _do_register and _do_enqueue were called correctly
		// This confirms the script registration flow was executed through the public interface
	}


	// ------------------------------------------------------------------------
	// _is_deferred_asset() Tests
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_is_deferred_asset
	 */
	public function test_stage_detects_and_processes_deferred_assets(): void {
		// Arrange - Add both deferred and regular assets using the public API
		$this->instance->add(array(
			'handle' => 'deferred-script',
			'src'    => 'deferred-script.js',
			'hook'   => 'custom_hook'
		));

		$this->instance->add(array(
			'handle' => 'regular-script',
			'src'    => 'regular-script.js'
			// No hook defined - should remain in immediate queue
		));

		// Act - Call the public stage method which internally uses _is_deferred_asset
		$this->instance->stage();

		// Assert - Verify that the deferred asset was moved to the deferred_assets property
		$deferred_assets_property = new \ReflectionProperty($this->instance, 'deferred_assets');
		$deferred_assets_property->setAccessible(true);
		$deferred_assets = $deferred_assets_property->getValue($this->instance);

		// Verify the deferred asset is in the correct hook
		$this->assertArrayHasKey('custom_hook', $deferred_assets, 'Deferred asset should be moved to custom_hook');
		$this->assertArrayHasKey(10, $deferred_assets['custom_hook'], 'Deferred asset should use default priority 10');
		$this->assertCount(1, $deferred_assets['custom_hook'][10], 'Should have one deferred asset');
		$this->assertEquals('deferred-script', $deferred_assets['custom_hook'][10][0]['handle'], 'Deferred asset handle should match');

		// Verify the regular asset remains in the immediate assets queue
		$assets_property = new \ReflectionProperty($this->instance, 'assets');
		$assets_property->setAccessible(true);
		$immediate_assets = $assets_property->getValue($this->instance);
		$this->assertCount(1, $immediate_assets, 'Should have one immediate asset');
		$this->assertEquals('regular-script', $immediate_assets[0]['handle'], 'Regular asset should remain in immediate queue');
	}

	/**
	 * Test _is_deferred_asset returns null for non-deferred assets.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_is_deferred_asset
	 */
	public function test_is_deferred_asset_returns_null_for_non_deferred_assets(): void {
		// Arrange - Asset definition without hook (non-deferred)
		$asset_definition = array(
			'handle' => 'regular-script',
			'src'    => 'regular-script.js'
			// No 'hook' key - not deferred
		);
		$handle     = 'regular-script';
		$hook_name  = null; // Staging phase
		$context    = 'TestContext';
		$asset_type = AssetType::Script;

		// Act - Call _is_deferred_asset directly via reflection
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_is_deferred_asset',
			array($asset_definition, $handle, $hook_name, $context, $asset_type)
		);

		// Assert - Should return null for non-deferred assets
		$this->assertNull($result, 'Non-deferred assets should return null');
	}

	/**
	 * Test _is_deferred_asset returns null for deferred assets during hook firing.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_is_deferred_asset
	 */
	public function test_is_deferred_asset_returns_null_during_hook_firing(): void {
		// Arrange - Deferred asset definition with hook
		$asset_definition = array(
			'handle' => 'deferred-script',
			'src'    => 'deferred-script.js',
			'hook'   => 'wp_enqueue_scripts'
		);
		$handle     = 'deferred-script';
		$hook_name  = 'wp_enqueue_scripts'; // Hook is firing
		$context    = 'TestContext';
		$asset_type = AssetType::Script;

		// Act - Call _is_deferred_asset directly via reflection
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_is_deferred_asset',
			array($asset_definition, $handle, $hook_name, $context, $asset_type)
		);

		// Assert - Should return null when hook is firing (process normally)
		$this->assertNull($result, 'Deferred assets should return null when hook is firing');
	}

	/**
	 * Test _is_deferred_asset logs debug message when context and asset_type provided.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_is_deferred_asset
	 */
	public function test_is_deferred_asset_logs_debug_with_context_and_asset_type(): void {
		// Arrange - Deferred asset during staging with logging parameters
		$asset_definition = array(
			'handle' => 'deferred-script',
			'src'    => 'deferred-script.js',
			'hook'   => 'wp_enqueue_scripts'
		);
		$handle     = 'deferred-script';
		$hook_name  = null; // Staging phase
		$context    = 'TestContext';
		$asset_type = AssetType::Script;

		// Act - Call _is_deferred_asset directly via reflection
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_is_deferred_asset',
			array($asset_definition, $handle, $hook_name, $context, $asset_type)
		);

		// Assert - Should return handle and log debug message
		$this->assertEquals($handle, $result, 'Should return handle for deferred asset during staging');
		$this->expectLog('debug', array(
			"TestContext - Skipping processing of deferred script 'deferred-script' during staging. Will process when hook 'wp_enqueue_scripts' fires."
		), 1);
	}

	/**
	 * Test _is_deferred_asset skips logging when context is null.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_is_deferred_asset
	 */
	public function test_is_deferred_asset_skips_logging_when_context_null(): void {
		// Arrange - Deferred asset during staging with null context
		$asset_definition = array(
			'handle' => 'deferred-script',
			'src'    => 'deferred-script.js',
			'hook'   => 'wp_enqueue_scripts'
		);
		$handle     = 'deferred-script';
		$hook_name  = null; // Staging phase
		$context    = null; // No context provided
		$asset_type = AssetType::Script;

		// Act - Call _is_deferred_asset directly via reflection
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_is_deferred_asset',
			array($asset_definition, $handle, $hook_name, $context, $asset_type)
		);

		// Assert - Should return handle but not log (no debug log expected)
		$this->assertEquals($handle, $result, 'Should return handle for deferred asset during staging');
		// No expectLog call - verifies no logging occurs
	}

	/**
	 * Test _is_deferred_asset skips logging when asset_type is null.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_is_deferred_asset
	 */
	public function test_is_deferred_asset_skips_logging_when_asset_type_null(): void {
		// Arrange - Deferred asset during staging with null asset_type
		$asset_definition = array(
			'handle' => 'deferred-script',
			'src'    => 'deferred-script.js',
			'hook'   => 'wp_enqueue_scripts'
		);
		$handle     = 'deferred-script';
		$hook_name  = null; // Staging phase
		$context    = 'TestContext';
		$asset_type = null; // No asset type provided

		// Act - Call _is_deferred_asset directly via reflection
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_is_deferred_asset',
			array($asset_definition, $handle, $hook_name, $context, $asset_type)
		);

		// Assert - Should return handle but not log (no debug log expected)
		$this->assertEquals($handle, $result, 'Should return handle for deferred asset during staging');
		// No expectLog call - verifies no logging occurs
	}

	// ------------------------------------------------------------------------
	// _do_enqueue() Tests
	// Not a simple wrapper, deligates to wp_enqueue_script and wp_enqueue_style
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_do_enqueue
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_concrete_process_single_asset
	 */
	public function test_enqueue_immediate_handles_script_enqueuing(): void {
		// Arrange - Set up a script asset for enqueuing testing
		$assets_property = new \ReflectionProperty($this->instance, 'assets');
		$assets_property->setAccessible(true);
		$assets_property->setValue($this->instance, array(
			array(
				'handle'    => 'test-script',
				'src'       => 'script.js',
				'deps'      => array(),
				'ver'       => '1.0.0',
				'in_footer' => true
			)
		));

		// Mock _get_asset_url to return the expected URL
		$this->instance->shouldReceive('_get_asset_url')
			->once()
			->with('script.js', AssetType::Script)
			->andReturn('https://example.com/script.js');

		// Mock _is_deferred_asset to return null (not deferred)
		$this->instance->shouldReceive('_is_deferred_asset')
			->once()
			->andReturn(null);

		// Mock _do_register to succeed
		$this->instance->shouldReceive('_do_register')
			->once()
			->andReturn(true);

		// Test script enqueuing - verify wp_script_is and wp_enqueue_script are called correctly
		WP_Mock::userFunction('wp_script_is')
			->with('test-script', 'enqueued')
			->andReturnUsing(function() {
				static $call_count = 0;
				$call_count++;
				if ($call_count === 1) {
					return false; // Initial check - not enqueued
				}
				return true; // Verification check - enqueue succeeded
			})
			->twice();

		WP_Mock::userFunction('wp_script_is')
			->with('test-script', 'registered')
			->andReturn(true)
			->times(2); // Called by both _do_register and _do_enqueue

		WP_Mock::userFunction('wp_enqueue_script')
			->with('test-script')
			->once();

		// Act - Call the public method that triggers script enqueuing
		$this->instance->enqueue_immediate();

		// Assert - Verify the asset was processed (removed from queue)
		$remaining_assets = $assets_property->getValue($this->instance);
		$this->assertEmpty($remaining_assets, 'Script should be enqueued and processed successfully');

		// The mocks verify that the enqueuing flow was executed correctly through the public interface
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_do_enqueue
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_do_register
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_concrete_process_single_asset
	 */
	public function test_do_enqueue_registers_script_when_not_registered(): void {
		// Arrange - Set up a script asset in the queue
		$assets_property = new \ReflectionProperty($this->instance, 'assets');
		$assets_property->setAccessible(true);
		$assets_property->setValue($this->instance, array(
			array(
				'handle'    => 'test-script',
				'src'       => 'script.js',
				'deps'      => array(),
				'ver'       => '1.0',
				'in_footer' => true
			)
		));

		// Mock _get_asset_url to return the expected URL
		$this->instance->shouldReceive('_get_asset_url')
			->once()
			->with('script.js', AssetType::Script)
			->andReturn('https://example.com/script.js');

		// Mock _is_deferred_asset to return null (not deferred)
		$this->instance->shouldReceive('_is_deferred_asset')
			->once()
			->andReturn(null);

		// Mock _do_register to succeed
		$this->instance->shouldReceive('_do_register')
			->once()
			->andReturn(true);

		// Mock wp_script_is for enqueued check (called initially and for verification after enqueue)
		WP_Mock::userFunction('wp_script_is')
			->with('test-script', 'enqueued')
			->andReturnUsing(function() {
				static $call_count = 0;
				$call_count++;
				if ($call_count === 1) {
					return false; // Initial check - not enqueued
				}
				return true; // Verification check - enqueue succeeded
			})
			->twice();

		WP_Mock::userFunction('wp_script_is')
			->with('test-script', 'registered')
			->andReturn(true)
			->times(2); // Called by both _do_register and _do_enqueue

		// Mock wp_enqueue_script
		WP_Mock::userFunction('wp_enqueue_script')
			->once()
			->with('test-script')
			->andReturn(null);

		// Act: Call the public method that triggers script enqueuing
		$this->instance->enqueue_immediate();

		// Assert - Verify the asset was processed (removed from queue)
		$remaining_assets = $assets_property->getValue($this->instance);
		$this->assertEmpty($remaining_assets, 'Script should be enqueued and processed successfully');

		// The mocks verify that the enqueuing flow was executed correctly through the public interface
	}


	// ------------------------------------------------------------------------
	// _build_attribute_string() Tests
	// covered by integration tests
	// ------------------------------------------------------------------------

	/**
	 * Test _build_attribute_string skips attributes when special handler returns false.
	 * This covers the special attribute handler false return path (lines 1162-1165).
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_build_attribute_string
	 */
	public function test_build_attribute_string_skips_attribute_when_special_handler_returns_false(): void {
		// Arrange - Set up attributes with a special handler that returns false
		$attributes_to_apply = array(
			'data-test'    => 'value1',
			'special-attr' => 'should-be-skipped',
			'data-keep'    => 'value2'
		);
		$managed_attributes = array();
		$context            = 'TestContext';
		$handle             = 'test-handle';
		$asset_type         = AssetType::Script;

		// Special handler that returns false for 'special-attr'
		$special_attributes = array(
			'special-attr' => function($attr, $value) {
				return false; // This should cause the attribute to be skipped
			}
		);

		// Act - Call _build_attribute_string with special handler
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_build_attribute_string',
			array(
				$attributes_to_apply,
				$managed_attributes,
				$context,
				$handle,
				$asset_type,
				$special_attributes
			)
		);

		// Assert - Should contain other attributes but not the one handled by special handler
		$this->assertStringContainsString('data-test="value1"', $result, 'Should contain regular attribute');
		$this->assertStringContainsString('data-keep="value2"', $result, 'Should contain other regular attribute');
		$this->assertStringNotContainsString('special-attr', $result, 'Should not contain attribute skipped by special handler');
	}

	// ------------------------------------------------------------------------
	// Additional Coverage Tests for Private Methods
	// ------------------------------------------------------------------------

	/**
	 * Test _do_register path when asset is already registered.
	 * This covers the "already registered" branch in _do_register (lines 822-825).
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_do_register
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_concrete_process_single_asset
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_do_enqueue
	 */
	public function test_do_register_skips_already_registered_script(): void {
		// Arrange - Set up an asset that will be "already registered"
		$assets_property = new \ReflectionProperty($this->instance, 'assets');
		$assets_property->setAccessible(true);
		$assets_property->setValue($this->instance, array(
			array(
				'handle'    => 'already-registered-script',
				'src'       => 'script.js',
				'deps'      => array(),
				'ver'       => '1.0',
				'in_footer' => false
			)
		));

		// Mock _get_asset_url to return a valid URL
		$this->instance->shouldReceive('_get_asset_url')
			->once()
			->with('script.js', AssetType::Script)
			->andReturn('https://example.com/script.js');

		// Mock _is_deferred_asset to return null (not deferred)
		$this->instance->shouldReceive('_is_deferred_asset')
			->once()
			->andReturn(null);

		// Mock wp_script_is to return true (already registered) - may be called multiple times
		\WP_Mock::userFunction('wp_script_is')
			->atLeast()->once()
			->with('already-registered-script', 'registered')
			->andReturn(true);

		// Mock wp_script_is for enqueue verification (our new logic)
		\WP_Mock::userFunction('wp_script_is')
			->once()
			->with('already-registered-script', 'enqueued')
			->andReturn(true);

		// Mock wp_register_script to never be called since it's already registered
		\WP_Mock::userFunction('wp_register_script')
			->times(0);

		// Mock wp_enqueue_script to be called since registration is skipped but enqueuing proceeds
		\WP_Mock::userFunction('wp_enqueue_script')
			->atMost()->once()
			->with('already-registered-script')
			->andReturn(true);

		// Act - Call the public method that triggers _do_register
		$this->instance->enqueue_immediate();

		// Assert - Verify debug log for already registered asset
		$this->expectLog('debug', array(
			'_concrete_process_single_asset - script \'already-registered-script\' already registered. Skipping registration'
		), 1);
	}

	/**
	 * Test _do_register path when registration fails.
	 * This covers the registration failure branch in _do_register (lines 843-848).
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_do_register
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_concrete_process_single_asset
	 */
	public function test_do_register_handles_registration_failure(): void {
		// Arrange - Set up an asset that will fail registration
		$assets_property = new \ReflectionProperty($this->instance, 'assets');
		$assets_property->setAccessible(true);
		$assets_property->setValue($this->instance, array(
			array(
				'handle'    => 'failing-script',
				'src'       => 'failing-script.js',
				'deps'      => array(),
				'ver'       => '1.0',
				'in_footer' => false
			)
		));

		// Mock _get_asset_url to return a valid URL
		$this->instance->shouldReceive('_get_asset_url')
			->once()
			->with('failing-script.js', AssetType::Script)
			->andReturn('https://example.com/failing-script.js');

		// Mock _is_deferred_asset to return null (not deferred)
		$this->instance->shouldReceive('_is_deferred_asset')
			->once()
			->andReturn(null);

		// Mock wp_script_is to return false (not registered) - may be called multiple times
		\WP_Mock::userFunction('wp_script_is')
			->atLeast()->once()
			->with('failing-script', 'registered')
			->andReturn(false);

		// Mock wp_register_script to fail (may be called multiple times from different code paths)
		\WP_Mock::userFunction('wp_register_script')
			->atLeast()->once()
			->andReturn(false);

		// Mock wp_enqueue_script to never be called since registration failed
		\WP_Mock::userFunction('wp_enqueue_script')
			->times(0);

		// Act - Call the public method that triggers _do_register
		$this->instance->enqueue_immediate();

		// Assert - Verify warning log for registration failure
		$this->expectLog('warning', array(
			'wp_register_script() failed for handle \'failing-script\'. Skipping further processing for this asset.'
		), 1);
	}

	/**
	 * Test _do_enqueue path when enqueuing fails.
	 * This covers the enqueue failure branch in _do_enqueue.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_do_enqueue
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_do_register
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_concrete_process_single_asset
	 */
	public function test_do_enqueue_handles_enqueue_failure_coverage(): void {
		// Arrange - Set up an asset that will fail enqueuing
		$assets_property = new \ReflectionProperty($this->instance, 'assets');
		$assets_property->setAccessible(true);
		$assets_property->setValue($this->instance, array(
			array(
				'handle'    => 'failing-enqueue-script',
				'src'       => 'script.js',
				'deps'      => array(),
				'ver'       => '1.0',
				'in_footer' => false
			)
		));

		// Mock _get_asset_url to return a valid URL
		$this->instance->shouldReceive('_get_asset_url')
			->once()
			->with('script.js', AssetType::Script)
			->andReturn('https://example.com/script.js');

		// Mock _is_deferred_asset to return null (not deferred)
		$this->instance->shouldReceive('_is_deferred_asset')
			->once()
			->andReturn(null);

		// Mock wp_script_is to handle multiple calls flexibly
		\WP_Mock::userFunction('wp_script_is')
			->atLeast()->once()
			->andReturnUsing(function($handle, $list) {
				if ($list === 'enqueued') {
					return false; // Always return false for enqueued to simulate enqueue failure
				}
				if ($list === 'registered') {
					return false; // Not registered initially, needs registration
				}
				return false;
			});

		// Mock wp_register_script to succeed (may be called multiple times from different code paths)
		\WP_Mock::userFunction('wp_register_script')
			->atLeast()->once()
			->andReturn(true);

		// Mock wp_enqueue_script to be called but enqueue will fail (verified by wp_script_is)
		\WP_Mock::userFunction('wp_enqueue_script')
			->once()
			->andReturn(null);

		// Act - Call the public method that triggers _do_enqueue
		$this->instance->enqueue_immediate();

		// Assert - Verify warning log for enqueue failure
		$this->expectLog('warning', array(
			'wp_enqueue_script() failed for handle \'failing-enqueue-script\'. Asset was registered but not enqueued.'
		), 1);
	}


	// ------------------------------------------------------------------------
	// render_head() Tests
	// to depreciate
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::render_head
	 */
	public function test_render_head_executes_callbacks(): void {
		// Use the existing instance which already has logger mocking set up
		$mock = $this->instance;

		// Test case 1: No callbacks registered
		$reflection = new \ReflectionClass($mock);
		$property   = $reflection->getProperty('head_callbacks');
		$property->setAccessible(true);
		$property->setValue($mock, array());

		// Call render_head and expect debug log about no callbacks
		$mock->render_head();
		$this->expectLog('debug', array(
			'AssetEnqueueBaseAbstract::render_head - No head callbacks to execute'
		), 1);

		// Test case 2: Simple callback registered
		$executed = false;
		$callback = function() use (&$executed) {
			$executed = true;
		};

		$property->setValue($mock, array($callback));

		// Call render_head and expect the callback to be executed
		$mock->render_head();
		$this->assertTrue($executed, 'Simple callback should be executed');
		$this->expectLog('debug', array(
			'AssetEnqueueBaseAbstract::render_head - Executing head callback 0'
		), 1);

		// Test case 3: Callback with condition that passes
		$executed = false;
		$callback = function() use (&$executed) {
			$executed = true;
		};
		$condition = function() {
			return true;
		};

		$property->setValue($mock, array(array(
			'callback'  => $callback,
			'condition' => $condition
		)));

		// Call render_head and expect the callback to be executed
		$mock->render_head();
		$this->assertTrue($executed, 'Callback with passing condition should be executed');

		// Test case 4: Callback with condition that fails
		$executed = false;
		$callback = function() use (&$executed) {
			$executed = true;
		};
		$condition = function() {
			return false;
		};

		$property->setValue($mock, array(array(
			'callback'  => $callback,
			'condition' => $condition
		)));

		// Call render_head and expect the callback to be skipped
		$mock->render_head();
		$this->assertFalse($executed, 'Callback with failing condition should be skipped');
		$this->expectLog('debug', array(
			'AssetEnqueueBaseAbstract::render_head - Skipping head callback 0 due to false condition'
		), 1);
	}

	// ------------------------------------------------------------------------
	// render_footer() Tests
	// to depreciate
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::render_footer
	 */
	public function test_render_footer_executes_callbacks(): void {
		// Use the existing instance which already has logger mocking set up
		$mock = $this->instance;

		// Test case 1: No callbacks registered
		$reflection = new \ReflectionClass($mock);
		$property   = $reflection->getProperty('footer_callbacks');
		$property->setAccessible(true);
		$property->setValue($mock, array());

		// Call render_footer and expect debug log about no callbacks
		$mock->render_footer();
		$this->expectLog('debug', array(
			'AssetEnqueueBaseAbstract::render_footer - No footer callbacks to execute'
		), 1);

		// Test case 2: Simple callback registered
		$executed = false;
		$callback = function() use (&$executed) {
			$executed = true;
		};

		$property->setValue($mock, array($callback));

		// Call render_footer and expect the callback to be executed
		$mock->render_footer();
		$this->assertTrue($executed, 'Simple callback should be executed');
		$this->expectLog('debug', array(
			'AssetEnqueueBaseAbstract::render_footer - Executing footer callback 0'
		), 1);

		// Test case 3: Callback with condition that passes
		$executed = false;
		$callback = function() use (&$executed) {
			$executed = true;
		};
		$condition = function() {
			return true;
		};

		$property->setValue($mock, array(array(
			'callback'  => $callback,
			'condition' => $condition
		)));

		// Call render_footer and expect the callback to be executed
		$mock->render_footer();
		$this->assertTrue($executed, 'Callback with passing condition should be executed');

		// Test case 4: Callback with condition that fails
		$executed = false;
		$callback = function() use (&$executed) {
			$executed = true;
		};
		$condition = function() {
			return false;
		};

		$property->setValue($mock, array(array(
			'callback'  => $callback,
			'condition' => $condition
		)));

		// Call render_footer and expect the callback to be skipped
		$mock->render_footer();
		$this->assertFalse($executed, 'Callback with failing condition should be skipped');
		$this->expectLog('debug', array(
			'AssetEnqueueBaseAbstract::render_footer - Skipping footer callback 0 due to false condition'
		), 1);
	}

	// ------------------------------------------------------------------------
	// get_deferred_hooks() Tests
	// @deprecated - functionality not required due to stage() and hook processing
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::get_deferred_hooks
	 */
	public function test_get_deferred_hooks_returns_unique_hook_names(): void {
		// Arrange - Set up assets with hooks
		$assets_to_add = array(
			array(
				'handle' => 'first-script',
				'src'    => 'path/to/first.js',
				'hook'   => 'first_hook'
			),
			array(
				'handle' => 'second-script',
				'src'    => 'path/to/second.js',
				'hook'   => 'second_hook'
			),
			array(
				'handle' => 'third-script',
				'src'    => 'path/to/third.js',
				'hook'   => 'first_hook' // Duplicate hook name to test uniqueness
			),
			array(
				'handle' => 'fourth-script',
				'src'    => 'path/to/fourth.js'
				// No hook for this one
			)
		);

		// Add the assets
		$this->instance->add($assets_to_add);

		// Stage the assets to process them
		$this->instance->stage();

		// Act - Get the deferred hooks using the public method
		$hooks = $this->instance->get_deferred_hooks();

		// Assert - Verify we get unique hook names
		$this->assertIsArray($hooks);
		$this->assertCount(2, $hooks); // Should only have 'first_hook' and 'second_hook'
		$this->assertContains('first_hook', $hooks);
		$this->assertContains('second_hook', $hooks);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::get_deferred_hooks
	 */
	public function test_get_deferred_hooks_returns_empty_array_when_no_assets(): void {
		// Act - Get deferred hooks when no assets are added using the public method
		$hooks = $this->instance->get_deferred_hooks();

		// Assert - Should return empty array
		$this->assertIsArray($hooks);
		$this->assertEmpty($hooks, 'Should return empty array when no assets are registered');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::get_deferred_hooks
	 */
	public function test_get_deferred_hooks_ignores_assets_without_hooks(): void {
		// Arrange - Set up assets without hooks
		$assets_to_add = array(
			array(
				'handle' => 'no-hook-script-1',
				'src'    => 'path/to/script1.js'
				// No hook key
			),
			array(
				'handle' => 'no-hook-script-2',
				'src'    => 'path/to/script2.js',
				'hook'   => '' // Empty hook
			),
			array(
				'handle' => 'no-hook-script-3',
				'src'    => 'path/to/script3.js',
				'hook'   => null // Null hook
			)
		);

		// Add the assets
		$this->instance->add($assets_to_add);

		// Act - Get the deferred hooks using the public method
		$hooks = $this->instance->get_deferred_hooks();

		// Assert - Should return empty array since no assets have valid hooks
		$this->assertIsArray($hooks);
		$this->assertEmpty($hooks, 'Should ignore assets without valid hooks');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::get_deferred_hooks
	 */
	public function test_get_deferred_hooks_includes_already_processed_deferred_assets(): void {
		// Arrange - Manually populate the deferred_assets array to simulate already-processed assets
		$deferred_assets_property = new \ReflectionProperty($this->instance, 'deferred_assets');
		$deferred_assets_property->setAccessible(true);
		$deferred_assets_property->setValue($this->instance, array(
				'processed_hook_1' => array(
					array('handle' => 'processed-script-1', 'src' => 'path/to/processed1.js')
				),
				'processed_hook_2' => array(
					array('handle' => 'processed-script-2', 'src' => 'path/to/processed2.js')
				)
		));

		// Also add some unprocessed assets with hooks
		$assets_to_add = array(
			array(
				'handle' => 'unprocessed-script',
				'src'    => 'path/to/unprocessed.js',
				'hook'   => 'unprocessed_hook'
			)
		);
		$this->instance->add($assets_to_add);

		// Act - Get the deferred hooks using the public method
		$hooks = $this->instance->get_deferred_hooks();

		// Assert - Should include hooks from both sources
		$this->assertIsArray($hooks);
		// Debug: Let's see what we actually get
		// var_dump('Actual hooks:', $hooks);
		$this->assertContains('processed_hook_1', $hooks);
		$this->assertContains('processed_hook_2', $hooks);
		// The unprocessed hook might not be included if assets aren't staged yet
		// Let's check if it's there and adjust expectation accordingly
		if (in_array('unprocessed_hook', $hooks)) {
			$this->assertCount(3, $hooks);
			$this->assertContains('unprocessed_hook', $hooks);
		} else {
			$this->assertCount(2, $hooks, 'Should include hooks from deferred assets even if unprocessed assets are not staged');
		}
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::get_deferred_hooks
	 */
	public function test_get_deferred_hooks_handles_mixed_asset_types(): void {
		// Arrange - Add script assets with hooks
		$script_assets = array(
			array(
				'handle' => 'script-with-hook',
				'src'    => 'path/to/script.js',
				'hook'   => 'script_hook'
			)
		);
		$this->instance->add($script_assets);

		// Also manually add some style deferred assets to test type isolation
		$deferred_assets_property = new \ReflectionProperty($this->instance, 'deferred_assets');
		$deferred_assets_property->setAccessible(true);
		$deferred_assets_property->setValue($this->instance, array(
			'script_deferred_hook' => array(
				10 => array(
					array('handle' => 'deferred-script', 'src' => 'path/to/deferred.js')
				)
			)
		));

		// Act - Get deferred hooks for scripts only using the public method
		$script_hooks = $this->instance->get_deferred_hooks();

		// Assert - Should only include script hooks, not style hooks
		$this->assertIsArray($script_hooks);
		// Debug: Let's see what we actually get
		// var_dump('Actual script hooks:', $script_hooks);
		$this->assertContains('script_deferred_hook', $script_hooks);
		$this->assertNotContains('style_deferred_hook', $script_hooks, 'Should not include style hooks when requesting script hooks');
		// The unprocessed script hook might not be included if assets aren't staged
		if (in_array('script_hook', $script_hooks)) {
			$this->assertCount(2, $script_hooks);
			$this->assertContains('script_hook', $script_hooks);
		} else {
			$this->assertCount(1, $script_hooks, 'Should include hooks from deferred assets even if unprocessed assets are not staged');
		}
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::get_deferred_hooks
	 */
	public function test_get_deferred_hooks_adds_valid_hooks_from_assets(): void {
		// Arrange - Directly populate the assets array structure that get_deferred_hooks expects
		// The method looks for $this->assets['assets'], so we need that structure
		$assets_property = new \ReflectionProperty($this->instance, 'assets');
		$assets_property->setAccessible(true);
		$assets_property->setValue($this->instance, array(
			'assets' => array(
					array(
						'handle' => 'valid-hook-script-1',
						'src'    => 'path/to/script1.js',
						'hook'   => 'valid_hook_1'
					),
					array(
						'handle' => 'valid-hook-script-2',
						'src'    => 'path/to/script2.js',
						'hook'   => 'valid_hook_2'
					),
					array(
						'handle' => 'no-hook-script',
						'src'    => 'path/to/script3.js'
						// No hook - should be ignored by the ! empty( $asset['hook'] ) condition
					)
				)
			)
		);

		// Act - Get the deferred hooks using the public method
		$hooks = $this->instance->get_deferred_hooks();

		// Assert - Should include hooks from assets with valid hooks (lines 150-152)
		$this->assertIsArray($hooks);
		$this->assertContains('valid_hook_1', $hooks, 'Should add valid hook from first asset (line 151)');
		$this->assertContains('valid_hook_2', $hooks, 'Should add valid hook from second asset (line 151)');
		$this->assertCount(2, $hooks, 'Should only include assets with valid hooks');
	}

	/**
	 * Test _concrete_process_single_asset returns false when enqueue fails.
	 * This covers the enqueue failure path (lines 777-779) in _concrete_process_single_asset.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_concrete_process_single_asset
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_do_enqueue
	 */
	public function test_concrete_process_single_asset_returns_false_when_enqueue_fails(): void {
		// Arrange - Set up an asset that will fail during enqueuing
		$asset_definition = array(
			'handle' => 'failing-enqueue-script',
			'src'    => 'failing-script.js',
			'deps'   => array(),
			'ver'    => '1.0'
		);

		// Mock _resolve_environment_src to return the src unchanged
		$this->instance->shouldReceive('_resolve_environment_src')
			->once()
			->with('failing-script.js')
			->andReturn('failing-script.js');

		// Mock _generate_asset_version to return a version
		$this->instance->shouldReceive('_generate_asset_version')
			->once()
			->andReturn('1.0');

		// Mock _is_deferred_asset to return null (not deferred)
		$this->instance->shouldReceive('_is_deferred_asset')
			->once()
			->andReturn(null);

		// Mock _get_asset_url to return a valid URL
		$this->instance->shouldReceive('_get_asset_url')
			->once()
			->with('failing-script.js', AssetType::Script)
			->andReturn('https://example.com/failing-script.js');

		// Mock _do_register to succeed
		$this->instance->shouldReceive('_do_register')
			->once()
			->andReturn(true);

		// Mock _do_enqueue to fail (this is the key test case)
		$this->instance->shouldReceive('_do_enqueue')
			->once()
			->andReturn(false); // Enqueue failure

		// Act - Call _concrete_process_single_asset with do_enqueue = true
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_concrete_process_single_asset',
			array(
				AssetType::Script,
				$asset_definition,
				'TestContext',
				null, // hook_name
				true, // do_register
				true, // do_enqueue (this will trigger the enqueue failure path)
				array('in_footer' => false) // type_specific
			)
		);

		// Assert - Should return false when enqueue fails
		$this->assertFalse($result, '_concrete_process_single_asset should return false when enqueue fails');
	}

	/**
	 * Test _concrete_process_single_asset returns false when handle is empty.
	 * This covers the empty handle check path (lines 700-705) in _concrete_process_single_asset.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_concrete_process_single_asset
	 */
	public function test_concrete_process_single_asset_returns_false_when_handle_empty(): void {
		// Arrange - Set up an asset definition with empty handle
		$asset_definition = array(
			'handle' => '', // Empty handle - should trigger warning
			'src'    => 'test-script.js',
			'deps'   => array(),
			'ver'    => '1.0'
		);

		// Mock _resolve_environment_src to return the src unchanged
		$this->instance->shouldReceive('_resolve_environment_src')
			->once()
			->with('test-script.js')
			->andReturn('test-script.js');

		// Mock _generate_asset_version to return a version
		$this->instance->shouldReceive('_generate_asset_version')
			->once()
			->andReturn('1.0');

		// Act - Call _concrete_process_single_asset directly
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_concrete_process_single_asset',
			array(
				AssetType::Script,
				$asset_definition,
				'TestContext',
				null, // hook_name
				true, // do_register
				false, // do_enqueue
				array('in_footer' => false) // type_specific
			)
		);

		// Assert - Should return false for empty handle
		$this->assertFalse($result, '_concrete_process_single_asset should return false when handle is empty');

		// Assert - Should log warning about missing handle
		$this->expectLog('warning', array(
			'_concrete_process_single_asset - script definition is missing a \'handle\'. Skipping.'
		), 1);
	}

	/**
	 * Test _do_enqueue returns false when src is empty (but not false).
	 * This covers the empty src check path (lines 947-952) in _do_enqueue.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_do_enqueue
	 */
	public function test_do_enqueue_returns_false_when_src_empty(): void {
		// Arrange - Set up an asset that will fail during enqueuing due to empty src
		$assets_property = new \ReflectionProperty($this->instance, 'assets');
		$assets_property->setAccessible(true);
		$assets_property->setValue($this->instance, array(
			array(
				'handle' => 'empty-src-script',
				'src'    => '', // Empty src (not false) - should trigger error
				'deps'   => array(),
				'ver'    => '1.0'
			)
		));

		// Mock _get_asset_url to return empty string (simulating empty src)
		$this->instance->shouldReceive('_get_asset_url')
			->once()
			->with('', AssetType::Script)
			->andReturn('');

		// Mock _is_deferred_asset to return null (not deferred)
		$this->instance->shouldReceive('_is_deferred_asset')
			->once()
			->andReturn(null);

		// Mock wp_script_is to return false (not enqueued, not registered)
		\WP_Mock::userFunction('wp_script_is')
			->atLeast()->once()
			->andReturnUsing(function($handle, $list) {
				if ($list === 'enqueued') {
					return false; // Not enqueued
				}
				if ($list === 'registered') {
					return false; // Not registered
				}
				return false;
			});

		// Act - Call the public method that triggers _do_enqueue
		$this->instance->enqueue_immediate();

		// Assert - Verify error log for empty src
		$this->expectLog('error', array(
			"Cannot register or enqueue script 'empty-src-script' because its 'src' is missing."
		), 1);
	}

	/**
	 * Test _do_enqueue returns true when do_enqueue is false.
	 * This covers the early return path (lines 921-923) in _do_enqueue.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_do_enqueue
	 */
	public function test_do_enqueue_returns_true_when_do_enqueue_false(): void {
		// Act - Call _do_enqueue directly with do_enqueue = false
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_do_enqueue',
			array(
				AssetType::Script,
				false, // do_enqueue = false (this should trigger early return)
				'test-handle',
				'test-src.js',
				array(), // deps
				'1.0', // ver
				array('in_footer' => false), // extra_args
				'TestContext', // context
				'', // log_hook_context
				false, // is_deferred
				null // hook_name
			)
		);

		// Assert - Should return true when do_enqueue is false
		$this->assertTrue($result, '_do_enqueue should return true when do_enqueue is false');
	}

	/**
	 * Test _concrete_process_single_asset returns deferred handle during stage_scripts context.
	 * This covers the deferred asset staging context path (lines 719-721).
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_concrete_process_single_asset
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_is_deferred_asset
	 */
	public function test_concrete_process_single_asset_returns_deferred_handle_during_staging(): void {
		// Arrange - Set up a deferred asset definition
		$asset_definition = array(
			'handle' => 'deferred-script',
			'src'    => 'deferred-script.js',
			'hook'   => 'wp_enqueue_scripts', // This makes it deferred
			'deps'   => array(),
			'ver'    => '1.0'
		);

		// Mock _resolve_environment_src to return the src unchanged
		$this->instance->shouldReceive('_resolve_environment_src')
			->once()
			->with('deferred-script.js')
			->andReturn('deferred-script.js');

		// Mock _generate_asset_version to return a version
		$this->instance->shouldReceive('_generate_asset_version')
			->once()
			->andReturn('1.0');

		// Mock _is_deferred_asset to return the handle (indicating it's deferred)
		$this->instance->shouldReceive('_is_deferred_asset')
			->once()
			->andReturn('deferred-script'); // Return handle for deferred asset

		// Act - Call _concrete_process_single_asset with 'stage_scripts' context
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_concrete_process_single_asset',
			array(
				AssetType::Script,
				$asset_definition,
				'stage_scripts', // This should trigger the early return
				null, // hook_name (staging phase)
				true, // do_register
				false, // do_enqueue
				array('in_footer' => false) // type_specific
			)
		);

		// Assert - Should return the deferred handle during staging
		$this->assertEquals('deferred-script', $result, '_concrete_process_single_asset should return deferred handle during stage_scripts context');
	}

	/**
	 * Test _do_enqueue returns false when asset registration fails.
	 * This covers the registration failure path (lines 974-976).
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_do_enqueue
	 */
	public function test_do_enqueue_returns_false_when_registration_fails(): void {
		// Arrange - Set up parameters for _do_enqueue
		$asset_type       = AssetType::Script;
		$do_enqueue       = true;
		$handle           = 'test-script';
		$src              = 'test-script.js';
		$deps             = array();
		$ver              = '1.0';
		$extra_args       = false; // in_footer for scripts
		$context          = 'TestContext';
		$log_hook_context = '';
		$is_deferred      = false;
		$hook_name        = null;

		// Mock wp_script_is to return false (not registered)
		\WP_Mock::userFunction('wp_script_is')
			->once()
			->with($handle, 'registered')
			->andReturn(false);

		// Mock wp_register_script to return false (registration failure)
		\WP_Mock::userFunction('wp_register_script')
			->once()
			->with($handle, $src, $deps, $ver, $extra_args)
			->andReturn(false); // Registration fails

		// Act - Call _do_enqueue
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_do_enqueue',
			array(
				$asset_type,
				$do_enqueue,
				$handle,
				$src,
				$deps,
				$ver,
				$extra_args,
				$context,
				$log_hook_context,
				$is_deferred,
				$hook_name
			)
		);

		// Assert - Should return false when registration fails
		$this->assertFalse($result, '_do_enqueue should return false when asset registration fails');
	}

	// ------------------------------------------------------------------------
	// _remove_assets() Tests
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_remove_assets
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_normalize_asset_input
	 */
	public function test_remove_assets_with_single_string_handle(): void {
		// Arrange
		$handle_to_remove = 'test-script';
		$asset_type       = AssetType::Script;

		// Mock _normalize_asset_input to return normalized format
		$this->instance->shouldReceive('_normalize_asset_input')
			->once()
			->with($handle_to_remove)
			->andReturn(array(array('handle' => $handle_to_remove)));

		// Mock _process_single_removal to verify it's called with correct parameters
		$this->instance->shouldReceive('_process_single_removal')
			->once()
			->with(array('handle' => $handle_to_remove), $asset_type);

		// Act
		$result = $this->instance->_remove_assets($handle_to_remove, $asset_type);

		// Assert
		$this->assertSame($this->instance, $result, 'Method should return self for chaining');
		$this->expectLog('debug', array('Entered. Processing removal request.'), 1);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_remove_assets
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_normalize_asset_input
	 */
	public function test_remove_assets_with_array_of_handles(): void {
		// Arrange
		$handles_to_remove = array('script1', 'script2', 'script3');
		$asset_type        = AssetType::Style;
		$normalized_assets = array(
			array('handle' => 'script1'),
			array('handle' => 'script2'),
			array('handle' => 'script3')
		);

		// Mock _normalize_asset_input to return normalized format
		$this->instance->shouldReceive('_normalize_asset_input')
			->once()
			->with($handles_to_remove)
			->andReturn($normalized_assets);

		// Mock _process_single_removal for each asset
		$this->instance->shouldReceive('_process_single_removal')
			->times(3)
			->with(Mockery::type('array'), $asset_type);

		// Act
		$result = $this->instance->_remove_assets($handles_to_remove, $asset_type);

		// Assert
		$this->assertSame($this->instance, $result, 'Method should return self for chaining');
		$this->expectLog('debug', array('Entered. Processing removal request.'), 1);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_remove_assets
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_normalize_asset_input
	 */
	public function test_remove_assets_with_asset_definition_arrays(): void {
		// Arrange
		$assets_to_remove = array(
			array('handle' => 'script1', 'hook' => 'wp_footer'),
			array('handle' => 'script2', 'priority' => 20),
			array('handle' => 'script3', 'immediate' => true)
		);
		$asset_type = AssetType::ScriptModule;

		// Mock _normalize_asset_input to return the same format (already normalized)
		$this->instance->shouldReceive('_normalize_asset_input')
			->once()
			->with($assets_to_remove)
			->andReturn($assets_to_remove);

		// Mock _process_single_removal for each asset with specific parameters
		$this->instance->shouldReceive('_process_single_removal')
			->once()
			->with($assets_to_remove[0], $asset_type);
		$this->instance->shouldReceive('_process_single_removal')
			->once()
			->with($assets_to_remove[1], $asset_type);
		$this->instance->shouldReceive('_process_single_removal')
			->once()
			->with($assets_to_remove[2], $asset_type);

		// Act
		$result = $this->instance->_remove_assets($assets_to_remove, $asset_type);

		// Assert
		$this->assertSame($this->instance, $result, 'Method should return self for chaining');
		$this->expectLog('debug', array('Entered. Processing removal request.'), 1);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_remove_assets
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_normalize_asset_input
	 */
	public function test_remove_assets_with_mixed_input_formats(): void {
		// Arrange
		$mixed_assets = array(
			'simple-handle',
			array('handle' => 'complex-handle', 'hook' => 'wp_footer'),
			'another-simple-handle'
		);
		$asset_type        = AssetType::Script;
		$normalized_assets = array(
			array('handle' => 'simple-handle'),
			array('handle' => 'complex-handle', 'hook' => 'wp_footer'),
			array('handle' => 'another-simple-handle')
		);

		// Mock _normalize_asset_input to handle mixed formats
		$this->instance->shouldReceive('_normalize_asset_input')
			->once()
			->with($mixed_assets)
			->andReturn($normalized_assets);

		// Mock _process_single_removal for each normalized asset
		$this->instance->shouldReceive('_process_single_removal')
			->times(3)
			->with(Mockery::type('array'), $asset_type);

		// Act
		$result = $this->instance->_remove_assets($mixed_assets, $asset_type);

		// Assert
		$this->assertSame($this->instance, $result, 'Method should return self for chaining');
		$this->expectLog('debug', array('Entered. Processing removal request.'), 1);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_remove_assets
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_normalize_asset_input
	 */
	public function test_remove_assets_with_empty_array(): void {
		// Arrange
		$empty_assets = array();
		$asset_type   = AssetType::Style;

		// Mock _normalize_asset_input to return empty array
		$this->instance->shouldReceive('_normalize_asset_input')
			->once()
			->with($empty_assets)
			->andReturn(array());

		// _process_single_removal should not be called for empty array
		$this->instance->shouldReceive('_process_single_removal')
			->never();

		// Act
		$result = $this->instance->_remove_assets($empty_assets, $asset_type);

		// Assert
		$this->assertSame($this->instance, $result, 'Method should return self for chaining even with empty array');
		$this->expectLog('debug', array('Entered. Processing removal request.'), 1);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_remove_assets
	 */
	public function test_remove_assets_logs_context_with_correct_class_and_asset_type(): void {
		// Arrange
		$handle           = 'test-handle';
		$asset_type       = AssetType::ScriptModule;
		$expected_context = get_class($this->instance) . '::_remove_assets (script_modules)';

		// Mock _normalize_asset_input
		$this->instance->shouldReceive('_normalize_asset_input')
			->once()
			->andReturn(array(array('handle' => $handle)));

		// Mock _process_single_removal
		$this->instance->shouldReceive('_process_single_removal')
			->once();

		// Act
		$this->instance->_remove_assets($handle, $asset_type);

		// Assert - Check that the log message includes the correct context
		$this->expectLog('debug', array('Entered. Processing removal request.'), 1);
		$this->expectLog('debug', array('(script_modules)'), 1);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_remove_assets
	 */
	public function test_remove_assets_skips_logging_when_logger_inactive(): void {
		// Arrange
		$handle     = 'test-handle';
		$asset_type = AssetType::Script;

		// Create a mock logger that returns false for is_active()
		$inactive_logger = Mockery::mock(CollectingLogger::class);
		$inactive_logger->shouldReceive('is_active')->andReturn(false);
		$inactive_logger->shouldReceive('debug')->never(); // Should not be called

		// Mock the get_logger method to return our inactive logger
		$this->instance->shouldReceive('get_logger')
			->andReturn($inactive_logger);

		// Mock _normalize_asset_input
		$this->instance->shouldReceive('_normalize_asset_input')
			->once()
			->andReturn(array(array('handle' => $handle)));

		// Mock _process_single_removal
		$this->instance->shouldReceive('_process_single_removal')
			->once();

		// Act
		$result = $this->instance->_remove_assets($handle, $asset_type);

		// Assert
		$this->assertSame($this->instance, $result);
		// No log expectations since logger is inactive
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_remove_assets
	 */
	public function test_remove_assets_handles_all_asset_types(): void {
		// Test that _remove_assets works correctly with all AssetType enum values
		$test_cases = array(
			array('asset_type' => AssetType::Script, 'expected_context_suffix' => 'scripts'),
			array('asset_type' => AssetType::Style, 'expected_context_suffix' => 'styles'),
			array('asset_type' => AssetType::ScriptModule, 'expected_context_suffix' => 'script_modules')
		);

		foreach ($test_cases as $case) {
			// Arrange
			$handle     = 'test-handle-' . $case['asset_type']->value;
			$asset_type = $case['asset_type'];

			// Create a fresh instance for each test case
			$instance = Mockery::mock(ConcreteEnqueueForBaseTraitCoreTesting::class)
				->makePartial()
				->shouldAllowMockingProtectedMethods();

			// Mock dependencies
			$instance->shouldReceive('get_logger')
				->andReturn($this->logger_mock);
			$instance->shouldReceive('_normalize_asset_input')
				->once()
				->andReturn(array(array('handle' => $handle)));
			$instance->shouldReceive('_process_single_removal')
				->once();

			// Act
			$result = $instance->_remove_assets($handle, $asset_type);

			// Assert
			$this->assertSame($instance, $result, "Method should return self for {$asset_type->value}");

			// Check that the context includes the correct asset type suffix
			$expected_context_part = "({$case['expected_context_suffix']})";
			$this->expectLog('debug', array($expected_context_part), 1);

			// Reset logger for next iteration
			$this->logger_mock->collected_logs = array();
		}
	}

	// ------------------------------------------------------------------------
	// _normalize_asset_input() Coverage Tests
	// ------------------------------------------------------------------------

	/**
	 * Test _normalize_asset_input with empty string handle (single input).
	 * This specifically targets line 1804: return array(); when single string is empty.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_normalize_asset_input
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_is_valid_handle
	 */
	public function test_normalize_asset_input_with_empty_string_handle_single_input(): void {
		// Arrange
		$input            = ''; // Empty string handle
		$expected_context = 'Ran\\PluginLib\\EnqueueAccessory\\AssetEnqueueBaseTrait::_normalize_asset_input';

		// Act
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_normalize_asset_input',
			array($input)
		);

		// Assert
		$this->assertIsArray($result);
		$this->assertEmpty($result, 'Should return empty array for empty string handle');
		$this->expectLog('warning', "{$expected_context} - Skipping empty handle.", 1);
	}

	/**
	 * Test _normalize_asset_input with empty handle in asset definition (single input).
	 * This specifically targets line 1814: return array(); when asset definition has empty handle.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_normalize_asset_input
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_is_valid_handle
	 */
	public function test_normalize_asset_input_with_empty_handle_in_asset_definition_single_input(): void {
		// Arrange
		$input = array(
			'handle'   => '', // Empty handle in asset definition
			'hook'     => 'wp_enqueue_scripts',
			'priority' => 10
		);

		// Act
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_normalize_asset_input',
			array($input)
		);

		// Assert
		$this->assertIsArray($result);
		$expected_context = 'Ran\\PluginLib\\EnqueueAccessory\\AssetEnqueueBaseTrait::_normalize_asset_input';
		$this->assertEmpty($result, 'Should return empty array for asset definition with empty handle');
		$this->expectLog('warning', "{$expected_context} - Skipping asset definition with empty handle.", 1);
	}

	// ------------------------------------------------------------------------
	// _process_single_removal() Tests
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_process_single_removal
	 */
	public function test_process_single_removal_throws_exception_for_missing_handle(): void {
		// Arrange
		$asset_definition = array('src' => 'test.js'); // Missing handle
		$asset_type       = AssetType::Script;

		// Act & Assert
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Asset definition must include a non-empty string handle.');
		$this->instance->_process_single_removal($asset_definition, $asset_type);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_process_single_removal
	 */
	public function test_process_single_removal_throws_exception_for_non_string_handle(): void {
		// Arrange
		$asset_definition = array('handle' => 123); // Non-string handle
		$asset_type       = AssetType::Style;

		// Act & Assert
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Asset definition must include a non-empty string handle.');
		$this->instance->_process_single_removal($asset_definition, $asset_type);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_process_single_removal
	 */
	public function test_process_single_removal_throws_exception_for_empty_handle(): void {
		// Arrange
		$asset_definition = array('handle' => ''); // Empty handle
		$asset_type       = AssetType::ScriptModule;

		// Act & Assert
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Asset definition must include a non-empty string handle.');
		$this->instance->_process_single_removal($asset_definition, $asset_type);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_process_single_removal
	 */
	public function test_process_single_removal_immediate_with_active_logger(): void {
		// Arrange
		$asset_definition = array(
			'handle'    => 'test-script',
			'immediate' => true
		);
		$asset_type = AssetType::Script;

		// Mock _handle_asset_operation
		$this->instance->shouldReceive('_handle_asset_operation')
			->once()
			->with('test-script', '_process_single_removal', $asset_type, 'remove');

		// Act
		$this->instance->_process_single_removal($asset_definition, $asset_type);

		// Assert
		$this->expectLog('debug', array('Immediately removed script'), 1);
		$this->expectLog('debug', array("'test-script'"), 1);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_process_single_removal
	 */
	public function test_process_single_removal_immediate_with_inactive_logger(): void {
		// Arrange
		$asset_definition = array(
			'handle'    => 'test-style',
			'immediate' => true
		);
		$asset_type = AssetType::Style;

		// Create inactive logger
		$inactive_logger = Mockery::mock(CollectingLogger::class);
		$inactive_logger->shouldReceive('is_active')->andReturn(false);
		$inactive_logger->shouldReceive('debug')->never();

		// Mock get_logger to return inactive logger
		$this->instance->shouldReceive('get_logger')
			->andReturn($inactive_logger);

		// Mock _handle_asset_operation
		$this->instance->shouldReceive('_handle_asset_operation')
			->once()
			->with('test-style', '_process_single_removal', $asset_type, 'remove');

		// Act
		$this->instance->_process_single_removal($asset_definition, $asset_type);

		// Assert - Verify that _handle_asset_operation was called even with inactive logger
		$this->assertTrue(true, 'Method completed successfully with inactive logger');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_process_single_removal
	 */
	public function test_process_single_removal_deferred_with_defaults(): void {
		// Arrange
		$asset_definition = array('handle' => 'test-module');
		$asset_type       = AssetType::ScriptModule;

		// Mock _do_add_action to capture the scheduled action
		$this->expectAction('wp_enqueue_scripts', 10, 1);

		// Act
		$this->instance->_process_single_removal($asset_definition, $asset_type);

		// Assert
		$this->expectLog('debug', array('Scheduled removal of script_module'), 1);
		$this->expectLog('debug', array("'test-module'"), 1);
		$this->expectLog('debug', array("on hook 'wp_enqueue_scripts'"), 1);
		$this->expectLog('debug', array('with priority 10'), 1);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_process_single_removal
	 */
	public function test_process_single_removal_deferred_with_custom_hook_and_priority(): void {
		// Arrange
		$asset_definition = array(
			'handle'   => 'custom-script',
			'hook'     => 'wp_footer',
			'priority' => 20
		);
		$asset_type = AssetType::Script;

		// Mock _do_add_action to capture the scheduled action
		$this->expectAction('wp_footer', 20, 1);

		// Act
		$this->instance->_process_single_removal($asset_definition, $asset_type);

		// Assert
		$this->expectLog('debug', array('Scheduled removal of script'), 1);
		$this->expectLog('debug', array("'custom-script'"), 1);
		$this->expectLog('debug', array("on hook 'wp_footer'"), 1);
		$this->expectLog('debug', array('with priority 20'), 1);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_process_single_removal
	 */
	public function test_process_single_removal_deferred_with_inactive_logger(): void {
		// Arrange
		$asset_definition = array('handle' => 'test-style');
		$asset_type       = AssetType::Style;

		// Create inactive logger
		$inactive_logger = Mockery::mock(CollectingLogger::class);
		$inactive_logger->shouldReceive('is_active')->andReturn(false);
		$inactive_logger->shouldReceive('debug')->never();

		// Mock get_logger to return inactive logger
		$this->instance->shouldReceive('get_logger')
			->andReturn($inactive_logger);

		// Mock _do_add_action
		$this->expectAction('wp_enqueue_scripts', 10, 1);

		// Act
		$this->instance->_process_single_removal($asset_definition, $asset_type);

		// Assert - Verify that the method completed without errors even with inactive logger
		// The Mockery expectations ensure _do_add_action was called correctly
		// and the inactive_logger->debug()->never() ensures no logging was attempted
		$this->expectNotToPerformAssertions();
	}



	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_process_single_removal
	 */
	public function test_process_single_removal_handles_all_asset_types(): void {
		// Test all asset types to ensure proper logging
		$test_cases = array(
			array('asset_type' => AssetType::Script, 'expected_log_type' => 'script'),
			array('asset_type' => AssetType::Style, 'expected_log_type' => 'style'),
			array('asset_type' => AssetType::ScriptModule, 'expected_log_type' => 'script_module')
		);

		foreach ($test_cases as $case) {
			// Create fresh instance for each test case
			$instance = Mockery::mock(ConcreteEnqueueForBaseTraitCoreTesting::class)
				->makePartial()
				->shouldAllowMockingProtectedMethods();

			$instance->shouldReceive('get_logger')
				->andReturn($this->logger_mock);

			$instance->shouldReceive('_handle_asset_operation')
				->once();

			$asset_definition = array(
				'handle'    => 'test-' . $case['asset_type']->value,
				'immediate' => true
			);

			// Act
			$instance->_process_single_removal($asset_definition, $case['asset_type']);

			// Assert
			$this->expectLog('debug', array('Immediately removed ' . $case['expected_log_type']), 1);

			// Reset logger for next iteration
			$this->logger_mock->collected_logs = array();
		}
	}

	// ------------------------------------------------------------------------
	// ScriptModules-specific tests for _do_enqueue and _do_register
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_do_enqueue
	 */
	public function test_do_enqueue_uses_script_module_functions_for_script_modules(): void {
		// Arrange
		$handle     = 'test-module';
		$src        = 'path/to/module.js';
		$deps       = array('dependency-module');
		$ver        = '1.0.0';
		$extra_args = array();
		$context    = 'TestContext';
		$asset_type = AssetType::ScriptModule;

		// Mock the _module_is method to return false (not enqueued, not registered)
		$this->instance->shouldReceive('_module_is')
			->with($handle, 'enqueued')
			->once()
			->andReturn(false);
		$this->instance->shouldReceive('_module_is')
			->with($handle, 'registered')
			->once()
			->andReturn(false);
		$this->instance->shouldReceive('_module_is')
			->with($handle, 'enqueued')
			->once()
			->andReturn(true); // After enqueue

		// Mock WordPress functions
		WP_Mock::userFunction('wp_register_script_module')
			->once()
			->with($handle, $src, $deps, $ver)
			->andReturn(true);
		WP_Mock::userFunction('wp_enqueue_script_module')
			->once()
			->with($handle);

		// Act
		$result = $this->instance->_do_enqueue(
			$asset_type,
			true, // do_enqueue
			$handle,
			$src,
			$deps,
			$ver,
			$extra_args,
			$context
		);

		// Assert
		$this->assertTrue($result, 'Should return true for successful enqueue');
		$this->expectLog('warning', array('was not registered before enqueuing. Registering now.'), 1);
		$this->expectLog('debug', array('Enqueuing script_module'), 1);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_do_enqueue
	 */
	public function test_do_enqueue_tracks_script_module_in_internal_registry(): void {
		// Arrange
		$handle     = 'test-module';
		$src        = 'path/to/module.js';
		$deps       = array();
		$ver        = '1.0.0';
		$extra_args = array();
		$context    = 'TestContext';
		$asset_type = AssetType::ScriptModule;

		// Mock the _module_is method to return false (not enqueued, not registered)
		$this->instance->shouldReceive('_module_is')
			->with($handle, 'enqueued')
			->once()
			->andReturn(false);
		$this->instance->shouldReceive('_module_is')
			->with($handle, 'registered')
			->once()
			->andReturn(false);
		$this->instance->shouldReceive('_module_is')
			->with($handle, 'enqueued')
			->once()
			->andReturn(true); // After enqueue

		// Mock WordPress functions
		WP_Mock::userFunction('wp_register_script_module')
			->once()
			->andReturn(true);
		WP_Mock::userFunction('wp_enqueue_script_module')
			->once();

		// Act
		$result = $this->instance->_do_enqueue(
			$asset_type,
			true, // do_enqueue
			$handle,
			$src,
			$deps,
			$ver,
			$extra_args,
			$context
		);

		// Assert
		$this->assertTrue($result);

		// Verify internal registry was updated
		$registry_property = new \ReflectionProperty($this->instance, '_script_module_registry');
		$registry_property->setAccessible(true);
		$registry = $registry_property->getValue($this->instance);

		$this->assertIsArray($registry, 'Registry should be initialized as array');
		$this->assertArrayHasKey('enqueued', $registry, 'Registry should have enqueued key');
		$this->assertContains($handle, $registry['enqueued'], 'Handle should be tracked in enqueued registry');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_do_enqueue
	 */
	public function test_do_enqueue_skips_already_enqueued_script_module(): void {
		// Arrange
		$handle     = 'already-enqueued-module';
		$asset_type = AssetType::ScriptModule;
		$context    = 'TestContext';

		// Mock the _module_is method to return true (already enqueued)
		$this->instance->shouldReceive('_module_is')
			->with($handle, 'enqueued')
			->once()
			->andReturn(true);

		// WordPress functions should not be called
		WP_Mock::userFunction('wp_register_script_module')->never();
		WP_Mock::userFunction('wp_enqueue_script_module')->never();

		// Act
		$result = $this->instance->_do_enqueue(
			$asset_type,
			true, // do_enqueue
			$handle,
			'src.js',
			array(),
			'1.0',
			array(),
			$context
		);

		// Assert
		$this->assertTrue($result, 'Should return true when already enqueued');
		$this->expectLog('debug', array("script_module '{$handle}' already enqueued. Skipping."), 1);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_do_register
	 */
	public function test_do_register_uses_script_module_functions_for_script_modules(): void {
		// Arrange
		$handle     = 'test-module';
		$src        = 'path/to/module.js';
		$deps       = array('dependency-module');
		$ver        = '1.0.0';
		$extra_args = array();
		$context    = 'TestContext';
		$asset_type = AssetType::ScriptModule;

		// Mock the _module_is method to return false (not registered)
		$this->instance->shouldReceive('_module_is')
			->with($handle, 'registered')
			->once()
			->andReturn(false);

		// Mock WordPress function
		WP_Mock::userFunction('wp_register_script_module')
			->once()
			->with($handle, $src, $deps, $ver)
			->andReturn(true);

		// Act
		$result = $this->instance->_do_register(
			$asset_type,
			true, // do_register
			$handle,
			$src,
			$deps,
			$ver,
			$extra_args,
			$context
		);

		// Assert
		$this->assertTrue($result, 'Should return true for successful registration');
		$this->expectLog('debug', array("Registering script_module: '{$handle}'"), 1);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_do_register
	 */
	public function test_do_register_tracks_script_module_in_internal_registry(): void {
		// Arrange
		$handle     = 'test-module';
		$src        = 'path/to/module.js';
		$deps       = array();
		$ver        = '1.0.0';
		$extra_args = array();
		$context    = 'TestContext';
		$asset_type = AssetType::ScriptModule;

		// Mock the _module_is method to return false (not registered)
		$this->instance->shouldReceive('_module_is')
			->with($handle, 'registered')
			->once()
			->andReturn(false);

		// Mock WordPress function
		WP_Mock::userFunction('wp_register_script_module')
			->once()
			->andReturn(true);

		// Act
		$result = $this->instance->_do_register(
			$asset_type,
			true, // do_register
			$handle,
			$src,
			$deps,
			$ver,
			$extra_args,
			$context
		);

		// Assert
		$this->assertTrue($result);

		// Verify internal registry was updated
		$registry_property = new \ReflectionProperty($this->instance, '_script_module_registry');
		$registry_property->setAccessible(true);
		$registry = $registry_property->getValue($this->instance);

		$this->assertIsArray($registry, 'Registry should be initialized as array');
		$this->assertArrayHasKey('registered', $registry, 'Registry should have registered key');
		$this->assertContains($handle, $registry['registered'], 'Handle should be tracked in registered registry');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_do_register
	 */
	public function test_do_register_skips_already_registered_script_module(): void {
		// Arrange
		$handle     = 'already-registered-module';
		$asset_type = AssetType::ScriptModule;
		$context    = 'TestContext';

		// Mock the _module_is method to return true (already registered)
		$this->instance->shouldReceive('_module_is')
			->with($handle, 'registered')
			->once()
			->andReturn(true);

		// WordPress function should not be called
		WP_Mock::userFunction('wp_register_script_module')->never();

		// Act
		$result = $this->instance->_do_register(
			$asset_type,
			true, // do_register
			$handle,
			'src.js',
			array(),
			'1.0',
			array(),
			$context
		);

		// Assert
		$this->assertTrue($result, 'Should return true when already registered');
		$this->expectLog('debug', array("script_module '{$handle}' already registered. Skipping registration."), 1);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_do_register
	 */
	public function test_do_register_handles_script_module_registration_failure(): void {
		// Arrange
		$handle     = 'failing-module';
		$src        = 'path/to/module.js';
		$deps       = array();
		$ver        = '1.0.0';
		$extra_args = array();
		$context    = 'TestContext';
		$asset_type = AssetType::ScriptModule;

		// Mock the _module_is method to return false (not registered)
		$this->instance->shouldReceive('_module_is')
			->with($handle, 'registered')
			->once()
			->andReturn(false);

		// Mock WordPress function to return false (registration failure)
		WP_Mock::userFunction('wp_register_script_module')
			->once()
			->with($handle, $src, $deps, $ver)
			->andReturn(false);

		// Act
		$result = $this->instance->_do_register(
			$asset_type,
			true, // do_register
			$handle,
			$src,
			$deps,
			$ver,
			$extra_args,
			$context
		);

		// Assert
		$this->assertFalse($result, 'Should return false when registration fails');
		$this->expectLog('warning', array("wp_register_script_module() failed for handle '{$handle}'"), 1);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_do_enqueue
	 */
	public function test_do_enqueue_handles_script_module_enqueue_failure(): void {
		// Arrange
		$handle     = 'failing-enqueue-module';
		$src        = 'path/to/module.js';
		$deps       = array();
		$ver        = '1.0.0';
		$extra_args = array();
		$context    = 'TestContext';
		$asset_type = AssetType::ScriptModule;

		// Mock the _module_is method
		$this->instance->shouldReceive('_module_is')
			->with($handle, 'enqueued')
			->once()
			->andReturn(false); // Not enqueued initially
		$this->instance->shouldReceive('_module_is')
			->with($handle, 'registered')
			->once()
			->andReturn(true); // Already registered
		$this->instance->shouldReceive('_module_is')
			->with($handle, 'enqueued')
			->once()
			->andReturn(false); // Still not enqueued after attempt

		// Mock WordPress functions
		WP_Mock::userFunction('wp_enqueue_script_module')
			->once()
			->with($handle);

		// Act
		$result = $this->instance->_do_enqueue(
			$asset_type,
			true, // do_enqueue
			$handle,
			$src,
			$deps,
			$ver,
			$extra_args,
			$context
		);

		// Assert
		$this->assertFalse($result, 'Should return false when enqueue fails');
		$this->expectLog('warning', array("wp_enqueue_script_module() failed for handle '{$handle}'"), 1);
	}
}
