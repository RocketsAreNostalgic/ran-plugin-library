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
use Ran\PluginLib\EnqueueAccessory\ScriptModulesEnqueueTrait;

/**
 * Concrete implementation of ScriptModulesEnqueueTrait for testing asset-related methods.
 */
class ConcreteEnqueueForScriptModulesTesting extends ConcreteEnqueueForTesting {
	use ScriptModulesEnqueueTrait;
}

/**
 * Class ScriptModulesEnqueueTraitTest
 *
 * @package Ran\PluginLib\Tests\Unit\EnqueueAccessory
 *
 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptModulesEnqueueTrait
 */
class ScriptModulesEnqueueTraitTest extends EnqueueTraitTestCase {
	use ExpectLogTrait;

	/**
	 * @inheritDoc
	 */
	protected function _get_concrete_class_name(): string {
		return ConcreteEnqueueForScriptModulesTesting::class;
	}

	/**
	 * @inheritDoc
	 */
	protected function _get_test_asset_type(): string {
		return AssetType::ScriptModule->value;
	}

	/**
	 * Set up test environment.
	 * See also EnqueueTraitTestCase
	 */
	public function setUp(): void {
		parent::setUp();

		// Add script module-specific mocks that were not generic enough for the base class.
		WP_Mock::userFunction('wp_enqueue_script_module')->withAnyArgs()->andReturn(true)->byDefault();
		WP_Mock::userFunction('wp_register_script_module')->withAnyArgs()->andReturn(true)->byDefault();
		WP_Mock::userFunction('wp_deregister_script_module')->withAnyArgs()->andReturn(true)->byDefault();

		// Mock wp_script_modules() function to return a mock instance
		WP_Mock::userFunction('wp_script_modules')->andReturn(
			Mockery::mock('WP_Script_Modules')
		)->byDefault();
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
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptModulesEnqueueTrait::_process_single_asset
	 */
	public function test_process_single_asset_with_incorrect_asset_type(): void {
		// Arrange
		$asset_definition = array(
			'handle'      => 'test-style',
			'src'         => 'path/to/my-component.js',
			'version'     => '1.2.3',
			'deps'        => array(),
			'condition'   => static fn() => true,
			'module_data' => array('config' => array('debug' => true)),
		);


		// Act - call with Style asset type instead of ScriptModule using reflection
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_process_single_asset',
			array(
				AssetType::Style, // Incorrect asset type
				$asset_definition,
				'test_context',
				null, // hook_name
				true, // do_register
				false // do_enqueue
			)
		);

		// Expect the warning log message
		$this->expectLog('warning', array('Incorrect asset type provided to _process_single_asset. Expected \'script_module\', got \'style\'.'), 1);


		// Assert - should return false due to incorrect asset type
		$this->assertFalse($result);
	}

	// ------------------------------------------------------------------------
	// add() Tests
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptModulesEnqueueTrait::add
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::add_assets
	 */
	public function test_add_script_modules_adds_asset_correctly(): void {
		// Arrange
		$asset_to_add = array(
			'handle' => '@my-plugin/component',
			'src'    => 'path/to/my-component.js',
		);

		// Act
		$this->instance->add($asset_to_add);

		// Assert
		$modules = $this->instance->get_info();
		// The array structure may vary in the test environment, so we don't assert count
		$this->assertEquals('@my-plugin/component', $modules['assets'][0]['handle']);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptModulesEnqueueTrait::add
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::add_assets
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::get_assets_info
	 */
	public function test_add_script_modules_should_store_assets_correctly(): void {
		// --- Test Setup ---
		$assets_to_add = array(
			array(
				'handle'      => '@my-plugin/component-1',
				'src'         => 'path/to/component-1.js',
				'deps'        => array('@wordpress/element'),
				'version'     => '1.0.0',
				'condition'   => static fn() => true,
				'module_data' => array('config' => array('debug' => true)),
			),
			array(
				'handle'  => '@my-plugin/component-2',
				'src'     => 'path/to/component-2.js',
				'deps'    => array(),
				'version' => false, // Use plugin version
				// No condition, should default to true
			),
		);
		// Call the method under test
		$result = $this->instance->add($assets_to_add);

		// Logger expectations for AssetEnqueueBaseTrait::add_assets() via ScriptModulesEnqueueTrait.
		$this->expectLog('debug', array('add_', 'Entered. Current', 'count: 0', 'Adding 2 new'), 1);
		$this->expectLog('debug', array('add_', 'Adding', 'Key: 0, Handle: @my-plugin/component-1, src: path/to/component-1.js'), 1);
		$this->expectLog('debug', array('add_', 'Adding', 'Key: 1, Handle: @my-plugin/component-2, src: path/to/component-2.js'), 1);
		$this->expectLog('debug', array('add_', 'Adding 2', 'Current total: 0'), 1);
		$this->expectLog('debug', array('add_', 'Exiting', 'count: 2'), 1);
		$this->expectLog('debug', array('add_', 'All current', '@my-plugin/component-1, @my-plugin/component-2'), 1);

		// Assert chainability
		$this->assertSame($this->instance, $result,
			'add() should be chainable and return an instance of the class.'
		);

		// get the results of get_info() and check that it contains the assets we added
		$assets = $this->instance->get_info();
		$this->assertArrayHasKey('assets', $assets);
		$this->assertArrayHasKey('deferred', $assets);
		$this->assertArrayHasKey('external_inline', $assets);
		$this->assertEquals('@my-plugin/component-1', $assets['assets'][0]['handle']);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptModulesEnqueueTrait::add
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::add_assets
	 */
	public function test_add_script_modules_handles_empty_input_gracefully(): void {
		// Act
		$result = $this->instance->add(array());

		// Logger expectations for AssetEnqueueBaseTrait::add_assets() via ScriptModulesEnqueueTrait.
		$this->expectLog('debug', array('add_', 'Entered with empty array'));

		// Assert that the method returns the instance for chainability
		$this->assertSame($this->instance, $result);

		// Assert that the modules array remains empty
		$assets = $this->instance->get_info();
		$this->assertEmpty($assets['assets']);
		$this->assertEmpty($assets['deferred']);
		$this->assertEmpty($assets['external_inline']);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptModulesEnqueueTrait::add
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::add_assets
	 */
	public function test_add_script_modules_throws_exception_for_missing_src(): void {
		// Assert
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage("Invalid script_module definition for handle '@my-plugin/module'. Asset must have a 'src' or 'src' must be explicitly set to false.");

		// Arrange
		$invalid_asset = array('handle' => '@my-plugin/module', 'src' => '');

		// Act
		$this->instance->add(array($invalid_asset));
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptModulesEnqueueTrait::add
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::add_assets
	 */
	public function test_add_script_modules_throws_exception_for_missing_handle(): void {
		// Assert
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage("Invalid script_module definition at index 0. Asset must have a 'handle'.");

		// Arrange
		$invalid_asset = array('src' => 'path/to/module.js');

		// Act
		$this->instance->add(array($invalid_asset));
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptModulesEnqueueTrait::add
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::add_assets
	 */
	public function test_add_script_modules_handles_single_asset_definition_correctly(): void {
		$asset_to_add = array(
			'handle' => '@my-plugin/single-module',
			'src'    => 'path/to/single.js',
			'deps'   => array(),
		);

		// Call the method under test
		$result = $this->instance->add($asset_to_add);

		// Assert chainability
		$this->assertSame($this->instance, $result);

		// Logger expectations
		$this->expectLog('debug', array('add_', 'Entered', 'count: 0', 'Adding 1 new'));
		$this->expectLog('debug', array('Adding script_module.', 'Key: 0', 'Handle: @my-plugin/single-module', 'src: path/to/single.js'));
		$this->expectLog('debug', array('add_', 'Adding 1', 'Current total: 0'));
		$this->expectLog('debug', array('add_', 'Exiting', 'count: 1'));
		$this->expectLog('debug', array('add_', 'All current', '@my-plugin/single-module'));

		// Assert that the asset was added
		$assets = $this->instance->get_info();
		$this->assertCount(1, $assets['assets']);
		$this->assertEquals('@my-plugin/single-module', $assets['assets'][0]['handle']);
	}

	// ------------------------------------------------------------------------
	// stage() Tests
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptModulesEnqueueTrait::stage
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseAbstract::stage_assets
	 */
	public function test_stage_script_modules_with_no_assets_to_process(): void {
		// Call the method under test
		$this->instance->stage();

		// Logger expectations for stage() with no assets.
		$this->expectLog('debug', array('stage_', 'Entered. Processing 0', 'definition(s) for registration.'), 1);
		$this->expectLog('debug', array('stage_', 'Exited. Remaining immediate', '0. Total deferred', '0.'), 1);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptModulesEnqueueTrait::add
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptModulesEnqueueTrait::stage
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::add_assets
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseAbstract::stage_assets
	 */
	public function test_stage_script_modules_skips_asset_if_condition_is_false(): void {
		// Arrange
		$handle       = '@my-plugin/conditional-module';
		$asset_to_add = array(
			'handle'    => $handle,
			'src'       => 'path/to/conditional.js',
			'condition' => fn() => false,
		);
		$this->instance->add($asset_to_add);

		WP_Mock::userFunction('wp_register_script_module')->never();

		// Act
		$this->instance->stage();
		// Assert: Set up log expectations
		$this->expectLog('debug', array('stage_', 'Entered. Processing 1', 'definition(s) for registration.'), 1);
		$this->expectLog('debug', array('stage_', 'Processing', "\"{$handle}\", original index: 0."), 1);
		$this->expectLog('debug', array('_process_single_', 'Condition not met for', "'{$handle}'. Skipping."), 1);
		$this->expectLog('debug', array('stage_', 'Exited. Remaining immediate', '0. Total deferred', '0.'), 1);

		$assets = $this->instance->get_info();
		$this->assertEmpty($assets['assets'], 'The general queue should be empty.');
		$this->assertEmpty($assets['deferred'], 'The deferred queue should be empty.');
		$this->assertEmpty($assets['external_inline'], 'The external_inline queue should be empty.');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptModulesEnqueueTrait::add
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptModulesEnqueueTrait::stage
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptModulesEnqueueTrait::enqueue_immediate
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::add_assets
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseAbstract::stage_assets
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::enqueue_immediate_assets
	 */
	public function test_stage_script_modules_registers_non_hooked_asset_correctly(): void {
		// --- Test Setup ---
		$asset_to_add = array(
			'handle'  => '@my-plugin/test-module',
			'src'     => 'path/to/test-module.js',
			'deps'    => array('@wordpress/element'),
			'version' => '1.0',
		);

		// Use the helper to mock WP functions for the asset lifecycle.
		$this->_mock_asset_lifecycle_functions(
			AssetType::ScriptModule,
			'wp_register_script_module',
			'wp_enqueue_script_module',
			null, // No status checking function for script modules
			$asset_to_add,
		);

		// --- Action ---
		$this->instance->add($asset_to_add);

		// --- Assert ---
		// Logger expectations for add()
		$this->expectLog('debug', array('add_', 'Entered. Current', 'count: 0', 'Adding 1 new'), 1);
		$this->expectLog('debug', array('add_', 'Adding', 'Handle: @my-plugin/test-module'), 1);
		$this->expectLog('debug', array('add_', 'Exiting', 'New total', 'count: 1'), 1);

		// --- Action ---
		$this->instance->stage();

		// --- Assert ---
		$this->expectLog('debug', array('stage_', 'Entered. Processing 1', 'script_module definition(s)'), 1);
		$this->expectLog('debug', array('stage_', 'Processing', '"@my-plugin/test-module"'), 1);
		$this->expectLog('debug', array('_process_single_', 'Registering', '@my-plugin/test-module'), 1);
		$this->expectLog('debug', array('_process_single_', 'Finished processing', '@my-plugin/test-module'), 1);
		$this->expectLog('debug', array('stage_', 'Exited. Remaining immediate', '1', 'Total deferred', '0'), 1);

		$this->instance->enqueue_immediate();

		// Assert that the asset has been removed from the queue after registration.
		$modules = $this->instance->get_info();
		$this->assertEmpty($modules['assets'], 'The general modules queue should be empty after registration.');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptModulesEnqueueTrait::stage
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseAbstract::stage_assets
	 */
	public function test_stage_script_modules_defers_hooked_asset_correctly(): void {
		// Arrange
		$hook_name    = 'wp_enqueue_scripts';
		$asset_to_add = array(
			'handle' => '@my-plugin/deferred-module',
			'src'    => 'path/to/deferred.js',
			'hook'   => $hook_name,
		);
		$this->instance->add($asset_to_add);

		// Expect the action to be added with a callable (closure).
		WP_Mock::expectActionAdded($hook_name, Mockery::type('callable'), 10, 0);

		// Arrange
		$multi_priority_hook_name = 'my_multi_priority_hook';
		$assets_to_add            = array(
			array(
				'handle'   => '@my-plugin/module-prio-10',
				'src'      => 'path/to/p10.js',
				'hook'     => $multi_priority_hook_name,
				'priority' => 10,
			),
			array(
				'handle'   => '@my-plugin/module-prio-20',
				'src'      => 'path/to/p20.js',
				'hook'     => $multi_priority_hook_name,
				'priority' => 20,
			),
		);
		$this->instance->add($assets_to_add);

		// Act
		$this->instance->stage();

		// Assert
		$assets = $this->instance->get_info();

		// With flattened structure, check if hook exists directly in deferred assets
		$deferred_assets = $this->_get_protected_property_value($this->instance, 'deferred_assets');
		$this->assertArrayHasKey($hook_name, $deferred_assets, 'Hook key should exist in deferred assets.');
		$this->assertArrayHasKey(10, $deferred_assets[$hook_name], 'Priority 10 key should exist.');
		$this->assertCount(1, $deferred_assets[$hook_name][10]);
		$this->assertEquals('@my-plugin/deferred-module', $deferred_assets[$hook_name][10][0]['handle']);
		$this->assertArrayHasKey($multi_priority_hook_name, $deferred_assets, 'Hook key should exist in deferred assets.');
		$this->assertArrayHasKey(10, $deferred_assets[$multi_priority_hook_name], 'Priority 10 key should exist.');
		$this->assertCount(1, $deferred_assets[$multi_priority_hook_name][10]);
		$this->assertEquals('@my-plugin/module-prio-10', $deferred_assets[$multi_priority_hook_name][10][0]['handle']);

		// Assert that the main assets queue is empty as the asset was deferred
		$main_assets = $this->instance->get_info();
		$this->assertEmpty($main_assets['assets']);
	}

	// ------------------------------------------------------------------------
	// _process_module_extras() Tests
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptModulesEnqueueTrait::_process_module_extras
	 */
	public function test_process_module_extras_with_module_data(): void {
		// Arrange
		$handle           = '@my-plugin/test-module';
		$asset_definition = array(
			'handle'      => $handle,
			'module_data' => array(
				'config'  => array('debug' => true),
				'user_id' => 123
			)
		);

		// Act
		$this->_invoke_protected_method(
			$this->instance,
			'_process_module_extras',
			array($asset_definition, $handle, null)
		);

		// Assert - check log messages
		$this->expectLog('debug', array("Adding module data to '{$handle}'"), 1);
		$this->expectLog('debug', array("Adding module data to '{$handle}' via script_module_data_{$handle} filter"), 1);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptModulesEnqueueTrait::_process_module_extras
	 */
	public function test_process_module_extras_with_empty_module_data(): void {
		// Arrange
		$handle           = '@my-plugin/test-module';
		$asset_definition = array(
			'handle'      => $handle,
			'module_data' => array()
		);

		// Act
		$this->_invoke_protected_method(
			$this->instance,
			'_process_module_extras',
			array($asset_definition, $handle, null)
		);

		// Assert - empty data still gets processed (filter still applied)
		$this->expectLog('debug', array("Adding module data to '{$handle}' via script_module_data_{$handle} filter"), 1);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptModulesEnqueueTrait::_process_module_extras
	 */
	public function test_process_module_extras_warns_about_unsupported_properties(): void {
		// Arrange
		$handle           = '@my-plugin/test-module';
		$asset_definition = array(
			'handle'     => $handle,
			'attributes' => array('test' => 'value'),   // Unsupported
			'inline'     => 'code',    // Unsupported
			'in_footer'  => true,      // Unsupported
			'data'       => array(),   // Unsupported
			'localize'   => array(),   // Unsupported
		);

		// Act
		$this->_invoke_protected_method(
			$this->instance,
			'_process_module_extras',
			array($asset_definition, $handle, null)
		);

		// Assert - check warning messages for unsupported properties
		$this->expectLog('warning', array("_process_module_extras - Processing module '{$handle}' - Feature 'localize' is not compatible with script modules. Use 'module_data' instead."), 1);
		$this->expectLog('warning', array("_process_module_extras - Processing module '{$handle}' - Feature 'data' is not compatible with script modules. Use 'module_data' instead."), 1);
		$this->expectLog('warning', array("_process_module_extras - Processing module '{$handle}' - Custom attributes for modules not yet supported in WordPress, as there currently is no 'script_module_loader_tag' filter or equivalent."), 1);
		$this->expectLog('warning', array("_process_module_extras - Processing module '{$handle}' - Feature 'inline' is not compatible with script modules."), 1);
		$this->expectLog('warning', array("_process_module_extras - Processing module '{$handle}' - Feature 'in_footer' is not compatible with script modules."), 1);
	}

	// ------------------------------------------------------------------------
	// deregister() Tests
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptModulesEnqueueTrait::deregister
	 */
	public function test_deregister_with_single_handle_string_immediate(): void {
		// Arrange - test immediate deregistration
		$handle = '@my-plugin/test-module';
		// After API normalization, deregister() only deregisters (no dequeue)
		WP_Mock::userFunction('wp_deregister_script_module')->with($handle)->once();

		// Act - deregister with immediate flag
		$result = $this->instance->deregister(array(array('handle' => $handle, 'immediate' => true)));

		// Assert chainability
		$this->assertSame($this->instance, $result);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptModulesEnqueueTrait::deregister
	 */
	public function test_deregister_with_single_handle_string_deferred(): void {
		// Arrange - test deferred deregistration (default behavior)
		$handle = '@my-plugin/test-module';

		// Act - deregister with default (deferred) behavior
		$result = $this->instance->deregister($handle);

		// Assert chainability
		$this->assertSame($this->instance, $result);

		// Expect the deferred scheduling log message
		$this->expectLog('debug', "ScriptModulesEnqueueTrait::deregister - Scheduled deregistration of script_module '{$handle}' on hook 'wp_enqueue_scripts' with priority 10.");
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptModulesEnqueueTrait::deregister
	 */
	public function test_deregister_with_array_of_handles_immediate(): void {
		// Arrange - test immediate deregistration of multiple handles
		$handles = array(
			array('handle' => '@my-plugin/module-1', 'immediate' => true),
			array('handle' => '@my-plugin/module-2', 'immediate' => true)
		);
		// After API normalization, deregister() only deregisters (no dequeue)
		WP_Mock::userFunction('wp_deregister_script_module')->with('@my-plugin/module-1')->once();
		WP_Mock::userFunction('wp_deregister_script_module')->with('@my-plugin/module-2')->once();

		// Act
		$result = $this->instance->deregister($handles);

		// Assert chainability
		$this->assertSame($this->instance, $result);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptModulesEnqueueTrait::deregister
	 */
	public function test_deregister_with_asset_definition_arrays(): void {
		// Arrange - test mixed immediate and deferred deregistration
		$modules_to_deregister = array(
			array('handle' => '@my-plugin/module-1', 'immediate' => true),
			array('handle' => '@my-plugin/module-2', 'hook' => 'wp_enqueue_scripts', 'immediate' => true)
		);
		// After API normalization, deregister() only deregisters (no dequeue)
		WP_Mock::userFunction('wp_deregister_script_module')->with('@my-plugin/module-1')->once();
		WP_Mock::userFunction('wp_deregister_script_module')->with('@my-plugin/module-2')->once();

		// Act
		$result = $this->instance->deregister($modules_to_deregister);

		// Assert chainability
		$this->assertSame($this->instance, $result);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptModulesEnqueueTrait::deregister
	 */
	public function test_deregister_with_mixed_formats(): void {
		// Arrange - test mixed formats with immediate deregistration
		$mixed_modules = array(
			array('handle' => '@my-plugin/string-handle', 'immediate' => true),
			array('handle' => '@my-plugin/array-handle', 'immediate' => true)
		);
		// After API normalization, deregister() only deregisters (no dequeue)
		WP_Mock::userFunction('wp_deregister_script_module')->with('@my-plugin/string-handle')->once();
		WP_Mock::userFunction('wp_deregister_script_module')->with('@my-plugin/array-handle')->once();

		// Act
		$result = $this->instance->deregister($mixed_modules);

		// Assert chainability
		$this->assertSame($this->instance, $result);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptModulesEnqueueTrait::deregister
	 */
	public function test_deregister_with_deferred_behavior(): void {
		// Arrange - test that deferred deregistration schedules correctly
		$handle   = '@my-plugin/deferred-module';
		$hook     = 'wp_footer';
		$priority = 15;

		// Act - deregister with deferred behavior (default)
		$result = $this->instance->deregister(array(
			array('handle' => $handle, 'hook' => $hook, 'priority' => $priority)
		));

		// Expect the action to be scheduled
		$this->expectLog('debug', "ScriptModulesEnqueueTrait::deregister - Scheduled deregistration of script_module '{$handle}' on hook '{$hook}' with priority {$priority}.");

		// Assert chainability
		$this->assertSame($this->instance, $result);
	}

	// ------------------------------------------------------------------------
	// dequeue() Tests
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptModulesEnqueueTrait::dequeue
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::dequeue
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::dequeue
	 */
	public function test_dequeue_with_string_handles(): void {
		// Arrange - test dequeuing with array of string handles
		$handles = array('@my-plugin/module-1', '@theme/module-2', '@shared/component');

		// Mock WordPress functions
		WP_Mock::userFunction('wp_dequeue_script_module')->with('@my-plugin/module-1')->once();
		WP_Mock::userFunction('wp_dequeue_script_module')->with('@theme/module-2')->once();
		WP_Mock::userFunction('wp_dequeue_script_module')->with('@shared/component')->once();

		// Act
		$result = $this->instance->dequeue($handles);

		// Assert chainability
		$this->assertSame($this->instance, $result);

		// Expect debug logs
		$this->expectLog('debug', array("dequeue - Attempted dequeue of script module '@my-plugin/module-1'"), 1);
		$this->expectLog('debug', array("dequeue - Attempted dequeue of script module '@theme/module-2'"), 1);
		$this->expectLog('debug', array("dequeue - Attempted dequeue of script module '@shared/component'"), 1);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptModulesEnqueueTrait::dequeue
	 */
	public function test_dequeue_with_definition_arrays(): void {
		// Arrange - test dequeuing with array of definition arrays
		$modules = array(
			array('handle' => '@my-plugin/module-1'),
			array('handle' => '@theme/module-2'),
			array('handle' => '@shared/component')
		);

		// Mock WordPress functions
		WP_Mock::userFunction('wp_dequeue_script_module')->with('@my-plugin/module-1')->once();
		WP_Mock::userFunction('wp_dequeue_script_module')->with('@theme/module-2')->once();
		WP_Mock::userFunction('wp_dequeue_script_module')->with('@shared/component')->once();

		// Act
		$result = $this->instance->dequeue($modules);

		// Assert chainability
		$this->assertSame($this->instance, $result);

		// Expect debug logs
		$this->expectLog('debug', array("dequeue - Attempted dequeue of script module '@my-plugin/module-1'"), 1);
		$this->expectLog('debug', array("dequeue - Attempted dequeue of script module '@theme/module-2'"), 1);
		$this->expectLog('debug', array("dequeue - Attempted dequeue of script module '@shared/component'"), 1);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptModulesEnqueueTrait::dequeue
	 */
	public function test_dequeue_with_mixed_formats(): void {
		// Arrange - test dequeuing with mixed string handles and definition arrays
		$mixed_modules = array(
			'@my-plugin/string-handle',
			array('handle' => '@theme/array-handle'),
			'@shared/another-string'
		);

		// Mock WordPress functions
		WP_Mock::userFunction('wp_dequeue_script_module')->with('@my-plugin/string-handle')->once();
		WP_Mock::userFunction('wp_dequeue_script_module')->with('@theme/array-handle')->once();
		WP_Mock::userFunction('wp_dequeue_script_module')->with('@shared/another-string')->once();

		// Act
		$result = $this->instance->dequeue($mixed_modules);

		// Assert chainability
		$this->assertSame($this->instance, $result);
		// Expect debug logs
		$this->expectLog('debug', array("dequeue - Attempted dequeue of script module '@my-plugin/string-handle'"), 1);
		$this->expectLog('debug', array("dequeue - Attempted dequeue of script module '@theme/array-handle'"), 1);
		$this->expectLog('debug', array("dequeue - Attempted dequeue of script module '@shared/another-string'"), 1);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptModulesEnqueueTrait::dequeue
	 */
	public function test_dequeue_skips_empty_handles(): void {
		// Arrange - test that empty handles are skipped with warning
		$modules_with_empty = array(
			'@valid/module-1',
			array('handle' => ''), // Empty handle
			array('handle' => '@valid/module-2'),
			array('invalid' => 'no-handle-key'), // Missing handle key
			'@valid/module-3'
		);

		// Mock WordPress functions - only for valid handles
		WP_Mock::userFunction('wp_dequeue_script_module')->with('@valid/module-1')->once();
		WP_Mock::userFunction('wp_dequeue_script_module')->with('@valid/module-2')->once();
		WP_Mock::userFunction('wp_dequeue_script_module')->with('@valid/module-3')->once();

		// Act
		$result = $this->instance->dequeue($modules_with_empty);

		// Assert chainability
		$this->assertSame($this->instance, $result);

		// Expect warning logs for empty handles (new centralized format)
		$this->expectLog('warning', array('Skipping asset definition with empty handle at index'), 1);
		$this->expectLog('warning', array('Invalid input type at index'), 1);

		// Expect debug logs for valid handles
		$this->expectLog('debug', array('dequeue - Attempted dequeue of script module'), 3);

		// Assert chainability
		$this->assertSame($this->instance, $result);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptModulesEnqueueTrait::dequeue
	 */
	public function test_dequeue_with_empty_array(): void {
		// Arrange - test dequeuing with empty array
		$empty_modules = array();

		// No WordPress functions should be called
		WP_Mock::userFunction('wp_dequeue_script_module')->never();

		// Act
		$result = $this->instance->dequeue($empty_modules);

		// Assert chainability
		$this->assertSame($this->instance, $result);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptModulesEnqueueTrait::dequeue
	 */
	public function test_dequeue_with_single_handle(): void {
		// Arrange - test dequeuing a single module
		$single_module = array('@my-plugin/single-module');

		// Mock WordPress function
		WP_Mock::userFunction('wp_dequeue_script_module')->with('@my-plugin/single-module')->once();

		// Act
		$result = $this->instance->dequeue($single_module);

		// Assert chainability
		$this->assertSame($this->instance, $result);

		// Expect debug log
		$this->expectLog('debug', array("dequeue - Attempted dequeue of script module '@my-plugin/single-module'"), 1);
	}

	// ------------------------------------------------------------------------
	// _enqueue_deferred_modules() Tests
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptModulesEnqueueTrait::_enqueue_deferred_modules
	 */
	public function test_enqueue_deferred_modules_processes_deferred_assets(): void {
		// Arrange
		$hook_name = 'wp_enqueue_scripts';
		$priority  = 10;
		$handle    = '@my-plugin/deferred-module';

		// Set up a deferred asset in the instance
		$deferred_asset = array(
			'handle'  => $handle,
			'src'     => 'path/to/deferred.js',
			'deps'    => array(),
			'version' => '1.0'
		);

		// Use reflection to set up deferred assets directly
		$deferred_assets = array($hook_name => array($priority => array($deferred_asset)));
		$this->_set_protected_property_value($this->instance, 'deferred_assets', $deferred_assets);

		// Mock WordPress functions
		WP_Mock::userFunction('wp_register_script_module')
			->with($handle, 'path/to/deferred.js', array(), '1.0')
			->once()
			->andReturn(true);
		WP_Mock::userFunction('wp_enqueue_script_module')
			->with($handle)
			->once();

		// Act
		$this->instance->_enqueue_deferred_modules($hook_name, $priority);

		// Assert - verify the deferred asset was processed
		$remaining_deferred = $this->_get_protected_property_value($this->instance, 'deferred_assets');
		$this->assertEmpty($remaining_deferred[$hook_name][$priority] ?? array());
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptModulesEnqueueTrait::_enqueue_deferred_modules
	 */
	public function test_enqueue_deferred_modules_with_no_matching_assets(): void {
		// Arrange
		$hook_name = 'wp_enqueue_scripts';
		$priority  = 10;

		// Set up empty deferred assets
		$this->_set_protected_property_value($this->instance, 'deferred_assets', array());

		// Act - should not throw any errors
		$this->instance->_enqueue_deferred_modules($hook_name, $priority);

		// Assert - verify no WordPress functions were called
		WP_Mock::userFunction('wp_register_script_module')->never();
		WP_Mock::userFunction('wp_enqueue_script_module')->never();

		// Assert - verify no deferred assets remain
		$remaining_deferred = $this->_get_protected_property_value($this->instance, 'deferred_assets');
		$this->assertEmpty($remaining_deferred[$hook_name][$priority] ?? array());
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptModulesEnqueueTrait::_enqueue_deferred_modules
	 */
	public function test_enqueue_deferred_modules_with_multiple_assets_same_priority(): void {
		// Arrange
		$hook_name = 'wp_enqueue_scripts';
		$priority  = 10;
		$assets    = array(
			array(
				'handle'  => '@my-plugin/module-1',
				'src'     => 'path/to/module-1.js',
				'deps'    => array(),
				'version' => '1.0'
			),
			array(
				'handle'  => '@my-plugin/module-2',
				'src'     => 'path/to/module-2.js',
				'deps'    => array('@my-plugin/module-1'),
				'version' => '2.0'
			)
		);

		// Set up deferred assets
		$deferred_assets = array($hook_name => array($priority => $assets));
		$this->_set_protected_property_value($this->instance, 'deferred_assets', $deferred_assets);

		// Mock WordPress functions for both modules
		WP_Mock::userFunction('wp_register_script_module')
			->with('@my-plugin/module-1', 'path/to/module-1.js', array(), '1.0')
			->once()
			->andReturn(true);
		WP_Mock::userFunction('wp_enqueue_script_module')
			->with('@my-plugin/module-1')
			->once();
		WP_Mock::userFunction('wp_register_script_module')
			->with('@my-plugin/module-2', 'path/to/module-2.js', array('@my-plugin/module-1'), '2.0')
			->once()
			->andReturn(true);
		WP_Mock::userFunction('wp_enqueue_script_module')
			->with('@my-plugin/module-2')
			->once();

		// Act
		$this->instance->_enqueue_deferred_modules($hook_name, $priority);

		// Assert - verify both assets were processed
		$remaining_deferred = $this->_get_protected_property_value($this->instance, 'deferred_assets');
		$this->assertEmpty($remaining_deferred[$hook_name][$priority] ?? array());
	}

	/**
	 * Test remove method with string handles.
	 *
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptModulesEnqueueTrait::remove
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::remove
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::remove
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_remove_assets
	 */
	public function test_remove_with_string_handles(): void {
		// Arrange
		$modules_to_remove = array('module-1', 'module-2');

		// Mock the _remove_assets method call
		$this->instance = Mockery::mock(ConcreteEnqueueForScriptModulesTesting::class)
			->makePartial()
			->shouldAllowMockingProtectedMethods();
		$this->instance->shouldReceive('_remove_assets')
			->with($modules_to_remove, AssetType::ScriptModule)
			->once()
			->andReturn($this->instance);

		// Act
		$result = $this->instance->remove($modules_to_remove);

		// Assert
		$this->assertSame($this->instance, $result);
	}

	/**
	 * Test remove method with definition arrays.
	 *
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptModulesEnqueueTrait::remove
	 */
	public function test_remove_with_definition_arrays(): void {
		// Arrange
		$modules_to_remove = array(
			array('handle' => 'module-1', 'immediate' => true),
			array('handle' => 'module-2', 'hook' => 'wp_footer', 'priority' => 20)
		);

		// Mock the _remove_assets method call
		$this->instance = Mockery::mock(ConcreteEnqueueForScriptModulesTesting::class)
			->makePartial()
			->shouldAllowMockingProtectedMethods();
		$this->instance->shouldReceive('_remove_assets')
			->with($modules_to_remove, AssetType::ScriptModule)
			->once()
			->andReturn($this->instance);

		// Act
		$result = $this->instance->remove($modules_to_remove);

		// Assert
		$this->assertSame($this->instance, $result);
	}

	/**
	 * Test remove method with mixed formats.
	 *
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptModulesEnqueueTrait::remove
	 */
	public function test_remove_with_mixed_formats(): void {
		// Arrange
		$modules_to_remove = array(
			'module-1', // string handle
			array('handle' => 'module-2', 'immediate' => true), // definition array
			'module-3' // another string handle
		);

		// Mock the _remove_assets method call
		$this->instance = Mockery::mock(ConcreteEnqueueForScriptModulesTesting::class)
			->makePartial()
			->shouldAllowMockingProtectedMethods();
		$this->instance->shouldReceive('_remove_assets')
			->with($modules_to_remove, AssetType::ScriptModule)
			->once()
			->andReturn($this->instance);

		// Act
		$result = $this->instance->remove($modules_to_remove);

		// Assert
		$this->assertSame($this->instance, $result);
	}

	/**
	 * Test remove method with single handle string.
	 *
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptModulesEnqueueTrait::remove
	 */
	public function test_remove_with_single_handle_string(): void {
		// Arrange
		$module_to_remove = 'single-module';

		// Mock the _remove_assets method call
		$this->instance = Mockery::mock(ConcreteEnqueueForScriptModulesTesting::class)
			->makePartial()
			->shouldAllowMockingProtectedMethods();
		$this->instance->shouldReceive('_remove_assets')
			->with($module_to_remove, AssetType::ScriptModule)
			->once()
			->andReturn($this->instance);

		// Act
		$result = $this->instance->remove($module_to_remove);

		// Assert
		$this->assertSame($this->instance, $result);
	}

	/**
	 * Test remove method with empty array.
	 *
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptModulesEnqueueTrait::remove
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_handle_asset_operation
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_normalize_asset_input
	 */
	public function test_remove_with_empty_array(): void {
		// Arrange
		$modules_to_remove = array();

		// Mock the _remove_assets method call
		$this->instance = Mockery::mock(ConcreteEnqueueForScriptModulesTesting::class)
			->makePartial()
			->shouldAllowMockingProtectedMethods();
		$this->instance->shouldReceive('_remove_assets')
			->with($modules_to_remove, AssetType::ScriptModule)
			->once()
			->andReturn($this->instance);

		// Act
		$result = $this->instance->remove($modules_to_remove);

		// Assert
		$this->assertSame($this->instance, $result);
	}
}
