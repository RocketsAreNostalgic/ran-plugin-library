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
 * Concrete implementation of ScriptsEnqueueTrait for testing _handle_asset_operation method.
 */
class ConcreteEnqueueForHandleOperationTesting extends ConcreteEnqueueForTesting {
	use ScriptsEnqueueTrait;
}

/**
 * Class AssetEnqueueBaseTraitHandleOperationTest
 *
 * Comprehensive tests for the _handle_asset_operation method to improve coverage
 * from 35% to near 100%. This method has high complexity (42) and needs thorough
 * testing of all conditional branches.
 *
 * @package Ran\PluginLib\Tests\Unit\EnqueueAccessory
 *
 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_handle_asset_operation
 */
class AssetEnqueueBaseTraitHandleOperationTest extends EnqueueTraitTestCase {
	use ExpectLogTrait;

	/**
	 * @inheritDoc
	 */
	protected function _get_concrete_class_name(): string {
		return ConcreteEnqueueForHandleOperationTesting::class;
	}

	/**
	 * @inheritDoc
	 */
	protected function _get_test_asset_type(): string {
		return AssetType::Script->value;
	}

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		// Add script-specific mocks
		WP_Mock::userFunction('wp_enqueue_script')->withAnyArgs()->andReturn(true)->byDefault();
		WP_Mock::userFunction('wp_dequeue_script')->withAnyArgs()->andReturn(true)->byDefault();
		WP_Mock::userFunction('wp_deregister_script')->withAnyArgs()->andReturn(true)->byDefault();
		WP_Mock::userFunction('wp_script_is')->withAnyArgs()->andReturn(false)->byDefault();

		// Add style-specific mocks
		WP_Mock::userFunction('wp_dequeue_style')->withAnyArgs()->andReturn(true)->byDefault();
		WP_Mock::userFunction('wp_deregister_style')->withAnyArgs()->andReturn(true)->byDefault();
		WP_Mock::userFunction('wp_style_is')->withAnyArgs()->andReturn(false)->byDefault();

		// Add script module mocks
		WP_Mock::userFunction('wp_dequeue_script_module')->withAnyArgs()->andReturn(true)->byDefault();
		WP_Mock::userFunction('wp_deregister_script_module')->withAnyArgs()->andReturn(true)->byDefault();
	}

	/**
	 * Clean up test environment.
	 */
	public function tearDown(): void {
		parent::tearDown();
		Mockery::close();
	}

	// ------------------------------------------------------------------------
	// Remove Operation Tests (covers lines 1566-1568)
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_handle_asset_operation
	 */
	public function test_handle_asset_operation_remove_with_asset_locations(): void {
		// --- Test Setup ---
		$handle          = 'test-script';
		$context         = 'TestContext::test_method';
		$asset_type      = AssetType::Script;
		$operation       = 'remove';
		$asset_locations = array('immediate' => array(0), 'deferred' => array('wp_enqueue_scripts' => array(10 => array(0))));

		// Mock initial asset status checks (called first)
		WP_Mock::userFunction('wp_script_is')
			->with($handle, 'registered')
			->andReturn(true)
			->once()
			->ordered();
		WP_Mock::userFunction('wp_script_is')
			->with($handle, 'enqueued')
			->andReturn(true)
			->once()
			->ordered();

		// Mock dequeue and deregister operations
		WP_Mock::userFunction('wp_dequeue_script')
			->with($handle)
			->once()
			->ordered();
		WP_Mock::userFunction('wp_deregister_script')
			->with($handle)
			->once()
			->ordered();

		// Mock verification checks (called after operations)
		WP_Mock::userFunction('wp_script_is')
			->with($handle, 'enqueued')
			->andReturn(false)
			->once()
			->ordered();
		WP_Mock::userFunction('wp_script_is')
			->with($handle, 'registered')
			->andReturn(false)
			->once()
			->ordered();

		// Mock cleanup method
		$this->instance->shouldReceive('_clean_deferred_asset_queues')
			->with($handle, $asset_type, $asset_locations)
			->once();

		// --- Execute Test ---
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_handle_asset_operation',
			array($handle, $context, $asset_type, $operation, $asset_locations)
		);

		// --- Verify Results ---
		$this->assertTrue($result, 'Remove operation with asset locations should succeed');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_handle_asset_operation
	 */
	public function test_handle_asset_operation_remove_finds_internal_locations(): void {
		// --- Test Setup ---
		$handle          = 'test-script';
		$context         = 'TestContext::test_method';
		$asset_type      = AssetType::Script;
		$operation       = 'remove';
		$found_locations = array('immediate' => array(0));

		// Mock finding asset in internal queues
		$this->instance->shouldReceive('_asset_exists_in_internal_queues')
			->with($handle, $asset_type)
			->andReturn($found_locations)
			->once();

		// Mock asset status checks
		WP_Mock::userFunction('wp_script_is')
			->with($handle, 'registered')
			->andReturn(false);
		WP_Mock::userFunction('wp_script_is')
			->with($handle, 'enqueued')
			->andReturn(false);

		// Mock cleanup method
		$this->instance->shouldReceive('_clean_deferred_asset_queues')
			->with($handle, $asset_type, $found_locations)
			->once();

		// --- Execute Test ---
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_handle_asset_operation',
			array($handle, $context, $asset_type, $operation)
		);

		// --- Verify Results ---
		$this->assertTrue($result, 'Remove operation should find and use internal locations');
	}

	// ------------------------------------------------------------------------
	// ScriptModule Asset Type Tests (covers lines 1584-1586, 1588-1589, 1637-1638)
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_handle_asset_operation
	 */
	public function test_handle_asset_operation_scriptmodule_dequeue(): void {
		// --- Test Setup ---
		$handle     = 'test-module';
		$context    = 'TestContext::test_method';
		$asset_type = AssetType::ScriptModule;
		$operation  = 'dequeue';

		// Mock initial module status checks
		$this->instance->shouldReceive('_module_is')
			->with($handle, 'registered')
			->andReturn(true)
			->once()
			->ordered();
		$this->instance->shouldReceive('_module_is')
			->with($handle, 'enqueued')
			->andReturn(true)
			->once()
			->ordered();

		// Mock dequeue operation
		WP_Mock::userFunction('wp_dequeue_script_module')
			->with($handle)
			->once()
			->ordered();

		// Mock verification check (called after operation)
		$this->instance->shouldReceive('_module_is')
			->with($handle, 'enqueued')
			->andReturn(false)
			->once()
			->ordered();

		// --- Execute Test ---
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_handle_asset_operation',
			array($handle, $context, $asset_type, $operation)
		);

		// --- Verify Results ---
		$this->assertTrue($result, 'ScriptModule dequeue operation should succeed');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_handle_asset_operation
	 */
	public function test_handle_asset_operation_scriptmodule_deregister(): void {
		// --- Test Setup ---
		$handle     = 'test-module';
		$context    = 'TestContext::test_method';
		$asset_type = AssetType::ScriptModule;
		$operation  = 'deregister';

		// Mock initial module status checks
		$this->instance->shouldReceive('_module_is')
			->with($handle, 'registered')
			->andReturn(true)
			->once()
			->ordered();
		$this->instance->shouldReceive('_module_is')
			->with($handle, 'enqueued')
			->andReturn(false)
			->once()
			->ordered();

		// Mock deregister operation
		WP_Mock::userFunction('wp_deregister_script_module')
			->with($handle)
			->once()
			->ordered();

		// Mock verification check (called after operation)
		$this->instance->shouldReceive('_module_is')
			->with($handle, 'registered')
			->andReturn(false)
			->once()
			->ordered();

		// --- Execute Test ---
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_handle_asset_operation',
			array($handle, $context, $asset_type, $operation)
		);

		// --- Verify Results ---
		$this->assertTrue($result, 'ScriptModule deregister operation should succeed');
	}

	// ------------------------------------------------------------------------
	// Dequeue Operation Failure Tests (covers lines 1594-1597, 1599-1600, 1602, 1604)
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_handle_asset_operation
	 */
	public function test_handle_asset_operation_dequeue_failure_script(): void {
		// --- Test Setup ---
		$handle     = 'protected-script';
		$context    = 'TestContext::test_method';
		$asset_type = AssetType::Script;
		$operation  = 'dequeue';

		// Mock asset initially enqueued
		WP_Mock::userFunction('wp_script_is')
			->with($handle, 'registered')
			->andReturn(true);
		WP_Mock::userFunction('wp_script_is')
			->with($handle, 'enqueued')
			->andReturn(true);

		// Mock dequeue failure (asset still enqueued after attempt)
		WP_Mock::userFunction('wp_script_is')
			->with($handle, 'enqueued')
			->andReturn(true)
			->ordered();

		// --- Execute Test ---
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_handle_asset_operation',
			array($handle, $context, $asset_type, $operation)
		);

		// --- Verify Results ---
		$this->assertFalse($result, 'Dequeue operation should fail when asset remains enqueued');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_handle_asset_operation
	 */
	public function test_handle_asset_operation_dequeue_not_enqueued(): void {
		// --- Test Setup ---
		$handle     = 'not-enqueued-script';
		$context    = 'TestContext::test_method';
		$asset_type = AssetType::Script;
		$operation  = 'dequeue';

		// Mock asset not enqueued
		WP_Mock::userFunction('wp_script_is')
			->with($handle, 'registered')
			->andReturn(true);
		WP_Mock::userFunction('wp_script_is')
			->with($handle, 'enqueued')
			->andReturn(false);

		// --- Execute Test ---
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_handle_asset_operation',
			array($handle, $context, $asset_type, $operation)
		);

		// --- Verify Results ---
		$this->assertTrue($result, 'Dequeue operation should succeed when asset is not enqueued');
	}

	// ------------------------------------------------------------------------
	// Deregister Operation Failure Tests (covers lines 1610-1613, 1615)
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_handle_asset_operation
	 */
	public function test_handle_asset_operation_deregister_failure_script(): void {
		// --- Test Setup ---
		$handle     = 'protected-script';
		$context    = 'TestContext::test_method';
		$asset_type = AssetType::Script;
		$operation  = 'deregister';

		// Mock asset initially registered
		WP_Mock::userFunction('wp_script_is')
			->with($handle, 'registered')
			->andReturn(true);
		WP_Mock::userFunction('wp_script_is')
			->with($handle, 'enqueued')
			->andReturn(false);

		// Mock deregister failure (asset still registered after attempt)
		WP_Mock::userFunction('wp_script_is')
			->with($handle, 'registered')
			->andReturn(true)
			->ordered();

		// --- Execute Test ---
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_handle_asset_operation',
			array($handle, $context, $asset_type, $operation)
		);

		// --- Verify Results ---
		$this->assertFalse($result, 'Deregister operation should fail when asset remains registered');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_handle_asset_operation
	 */
	public function test_handle_asset_operation_deregister_not_registered(): void {
		// --- Test Setup ---
		$handle     = 'not-registered-script';
		$context    = 'TestContext::test_method';
		$asset_type = AssetType::Script;
		$operation  = 'deregister';

		// Mock asset not registered
		WP_Mock::userFunction('wp_script_is')
			->with($handle, 'registered')
			->andReturn(false);
		WP_Mock::userFunction('wp_script_is')
			->with($handle, 'enqueued')
			->andReturn(false);

		// --- Execute Test ---
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_handle_asset_operation',
			array($handle, $context, $asset_type, $operation)
		);

		// --- Verify Results ---
		$this->assertTrue($result, 'Deregister operation should succeed when asset is not registered');
	}

	// ------------------------------------------------------------------------
	// Style Asset Type Tests (covers style-specific branches)
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_handle_asset_operation
	 */
	public function test_handle_asset_operation_style_dequeue_success(): void {
		// --- Test Setup ---
		$handle     = 'test-style';
		$context    = 'TestContext::test_method';
		$asset_type = AssetType::Style;
		$operation  = 'dequeue';

		// Mock initial style status checks
		WP_Mock::userFunction('wp_style_is')
			->with($handle, 'registered')
			->andReturn(true)
			->once()
			->ordered();
		WP_Mock::userFunction('wp_style_is')
			->with($handle, 'enqueued')
			->andReturn(true)
			->once()
			->ordered();

		// Mock dequeue operation
		WP_Mock::userFunction('wp_dequeue_style')
			->with($handle)
			->once()
			->ordered();

		// Mock verification check (called after operation)
		WP_Mock::userFunction('wp_style_is')
			->with($handle, 'enqueued')
			->andReturn(false)
			->once()
			->ordered();

		// --- Execute Test ---
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_handle_asset_operation',
			array($handle, $context, $asset_type, $operation)
		);

		// --- Verify Results ---
		$this->assertTrue($result, 'Style dequeue operation should succeed');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_handle_asset_operation
	 */
	public function test_handle_asset_operation_style_deregister_success(): void {
		// --- Test Setup ---
		$handle     = 'test-style';
		$context    = 'TestContext::test_method';
		$asset_type = AssetType::Style;
		$operation  = 'deregister';

		// Mock initial style status checks
		WP_Mock::userFunction('wp_style_is')
			->with($handle, 'registered')
			->andReturn(true)
			->once()
			->ordered();
		WP_Mock::userFunction('wp_style_is')
			->with($handle, 'enqueued')
			->andReturn(false)
			->once()
			->ordered();

		// Mock deregister operation
		WP_Mock::userFunction('wp_deregister_style')
			->with($handle)
			->once()
			->ordered();

		// Mock verification check (called after operation)
		WP_Mock::userFunction('wp_style_is')
			->with($handle, 'registered')
			->andReturn(false)
			->once()
			->ordered();

		// --- Execute Test ---
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_handle_asset_operation',
			array($handle, $context, $asset_type, $operation)
		);

		// --- Verify Results ---
		$this->assertTrue($result, 'Style deregister operation should succeed');
	}

	// ------------------------------------------------------------------------
	// Status Logging Tests (covers lines 1618-1621, 1624-1625, 1628-1629)
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_handle_asset_operation
	 */
	public function test_handle_asset_operation_neither_registered_nor_enqueued(): void {
		// --- Test Setup ---
		$handle     = 'nonexistent-script';
		$context    = 'TestContext::test_method';
		$asset_type = AssetType::Script;
		$operation  = 'dequeue';

		// Mock asset not registered or enqueued
		WP_Mock::userFunction('wp_script_is')
			->with($handle, 'registered')
			->andReturn(false);
		WP_Mock::userFunction('wp_script_is')
			->with($handle, 'enqueued')
			->andReturn(false);

		// --- Execute Test ---
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_handle_asset_operation',
			array($handle, $context, $asset_type, $operation)
		);

		// --- Verify Results ---
		$this->assertTrue($result, 'Operation should succeed when asset does not exist');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_handle_asset_operation
	 */
	public function test_handle_asset_operation_partial_success_remove(): void {
		// --- Test Setup ---
		$handle     = 'partial-success-script';
		$context    = 'TestContext::test_method';
		$asset_type = AssetType::Script;
		$operation  = 'remove';

		// Mock asset initially registered and enqueued
		WP_Mock::userFunction('wp_script_is')
			->with($handle, 'registered')
			->andReturn(true);
		WP_Mock::userFunction('wp_script_is')
			->with($handle, 'enqueued')
			->andReturn(true);

		// Mock successful dequeue but failed deregister
		WP_Mock::userFunction('wp_script_is')
			->with($handle, 'enqueued')
			->andReturn(false)
			->ordered();
		WP_Mock::userFunction('wp_script_is')
			->with($handle, 'registered')
			->andReturn(true)
			->ordered();

		// Mock no asset locations found
		$this->instance->shouldReceive('_asset_exists_in_internal_queues')
			->with($handle, $asset_type)
			->andReturn(array());

		// --- Execute Test ---
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_handle_asset_operation',
			array($handle, $context, $asset_type, $operation)
		);

		// --- Verify Results ---
		$this->assertFalse($result, 'Remove operation should fail when only partially successful');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_handle_asset_operation
	 */
	public function test_handle_asset_operation_successful_remove_with_logging(): void {
		// --- Test Setup ---
		$handle     = 'successful-remove-script';
		$context    = 'TestContext::test_method';
		$asset_type = AssetType::Script;
		$operation  = 'remove';

		// Mock no asset locations found initially
		$this->instance->shouldReceive('_asset_exists_in_internal_queues')
			->with($handle, $asset_type)
			->andReturn(array())
			->once()
			->ordered();

		// Mock initial asset status checks
		WP_Mock::userFunction('wp_script_is')
			->with($handle, 'registered')
			->andReturn(true)
			->once()
			->ordered();
		WP_Mock::userFunction('wp_script_is')
			->with($handle, 'enqueued')
			->andReturn(true)
			->once()
			->ordered();

		// Mock dequeue and deregister operations
		WP_Mock::userFunction('wp_dequeue_script')
			->with($handle)
			->once()
			->ordered();
		WP_Mock::userFunction('wp_deregister_script')
			->with($handle)
			->once()
			->ordered();

		// Mock verification checks (called after operations)
		WP_Mock::userFunction('wp_script_is')
			->with($handle, 'enqueued')
			->andReturn(false)
			->once()
			->ordered();
		WP_Mock::userFunction('wp_script_is')
			->with($handle, 'registered')
			->andReturn(false)
			->once()
			->ordered();

		// --- Execute Test ---
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_handle_asset_operation',
			array($handle, $context, $asset_type, $operation)
		);

		// --- Verify Results ---
		$this->assertTrue($result, 'Remove operation should succeed when both dequeue and deregister work');
	}
}
