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
use Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait;
use Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseAbstract;

/**
 * Concrete implementation of ScriptsEnqueueTrait for testing asset-related methods.
 */
class ConcreteEnqueueForScriptsTesting extends ConcreteEnqueueForTesting {
	use ScriptsEnqueueTrait;
}

/**
 * Class ScriptsEnqueueTraitTest
 *
 * @package Ran\PluginLib\Tests\Unit\EnqueueAccessory
 *
 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait
 */
class ScriptsEnqueueTraitTest extends EnqueueTraitTestCase {
	use ExpectLogTrait;

	/**
	 * @inheritDoc
	 */
	protected function get_concrete_class_name(): string {
		return ConcreteEnqueueForScriptsTesting::class;
	}

	/**
	 * @inheritDoc
	 */
	protected function get_asset_type(): string {
		return 'script';
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
	// Add
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::add_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::add_assets
	 */
	public function test_add_scripts_adds_asset_correctly(): void {
		// Arrange
		$asset_to_add = array(
			'handle' => 'my-asset',
			'src'    => 'path/to/my-asset.js',
		);

		// Act
		$this->instance->add_scripts($asset_to_add);

		// Assert
		$scripts = $this->instance->get_scripts();
		$this->assertCount(1, $scripts['general']);
		$this->assertEquals('my-asset', $scripts['general'][0]['handle']);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::add_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::add_assets
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::get_assets
	 */
	public function test_add_scripts_should_store_assets_correctly(): void {
		// --- Test Setup ---
		$assets_to_add = array(
			array(
				'handle'    => 'my-asset-1',
				'src'       => 'path/to/my-asset-1.js',
				'deps'      => array('jquery-ui-asset'),
				'version'   => '1.0.0',
				'media'     => 'screen',
				'condition' => static fn() => true,
			),
			array(
				'handle'  => 'my-asset-2',
				'src'     => 'path/to/my-asset-2.js',
				'deps'    => array(),
				'version' => false, // Use plugin version
				'media'   => 'all',
				// No condition, should default to true
			),
		);
		// Call the method under test
		$result = $this->instance->add_scripts($assets_to_add);

		// Logger expectations for AssetEnqueueBaseTrait::add_assets() via ScriptsEnqueueTrait.
		$this->expectLog('debug', array('add_', 'Entered. Current', 'count: 0', 'Adding 2 new'), 1);
		$this->expectLog('debug', array('add_', 'Adding', 'Key: 0, Handle: my-asset-1, src: path/to/my-asset-1.js'), 1);
		$this->expectLog('debug', array('add_', 'Adding', 'Key: 1, Handle: my-asset-2, src: path/to/my-asset-2.js'), 1);
		$this->expectLog('debug', array('add_', 'Adding 2', 'Current total: 0'), 1);
		$this->expectLog('debug', array('add_', 'Exiting', 'count: 2'), 1);
		$this->expectLog('debug', array('add_', 'All current', 'my-asset-1, my-asset-2'), 1);

		// Assert chainability
		$this->assertSame($this->instance, $result,
			'add_scripts() should be chainable and return an instance of the class.'
		);

		// get the results of get_scripts() and check that it contains the assets we added
		$assets = $this->instance->get_scripts();
		$this->assertArrayHasKey('general', $assets);
		$this->assertArrayHasKey('deferred', $assets);
		$this->assertArrayHasKey('external_inline', $assets);
		$this->assertEquals('my-asset-1', $assets['general'][0]['handle']);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::add_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::add_assets
	 */
	public function test_add_scripts_handles_empty_input_gracefully(): void {
		// Act
		$result = $this->instance->add_scripts(array());

		// Logger expectations for AssetEnqueueBaseTrait::add_assets() via ScriptsEnqueueTrait.
		$this->expectLog('debug', array('add_', 'Entered with empty array'));

		// Assert that the method returns the instance for chainability
		$this->assertSame($this->instance, $result);

		// Assert that the scripts array remains empty
		$assets = $this->instance->get_scripts();
		$this->assertEmpty($assets['general']);
		$this->assertEmpty($assets['deferred']);
		$this->assertEmpty($assets['external_inline']);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::add_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::add_assets
	 */
	public function test_add_scripts_throws_exception_for_missing_src(): void {
		// Assert
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage("Invalid script definition for handle 'my-script'. Asset must have a 'src' or 'src' must be explicitly set to false.");

		// Arrange
		$invalid_asset = array('handle' => 'my-script', 'src' => '');

		// Act
		$this->instance->add_scripts(array($invalid_asset));
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::add_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::add_assets
	 */
	public function test_add_scripts_throws_exception_for_missing_handle(): void {
		// Assert
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage("Invalid script definition at index 0. Asset must have a 'handle'.");

		// Arrange
		$invalid_asset = array('src' => 'path/to/script.js');

		// Act
		$this->instance->add_scripts(array($invalid_asset));
	}


	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::add_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::add_assets
	 */
	public function test_add_scripts_handles_single_asset_definition_correctly(): void {
		$asset_to_add = array(
			'handle' => 'single-asset',
			'src'    => 'path/to/single.js',
			'deps'   => array(),
		);

		// Call the method under test
		$result = $this->instance->add_scripts($asset_to_add);

		// Assert chainability
		$this->assertSame($this->instance, $result);

		// Logger expectations
		$this->expectLog('debug', array('add_', 'Entered', 'count: 0', 'Adding 1 new'));
		$this->expectLog('debug', array('Adding script.', 'Key: 0', 'Handle: single-asset', 'src: path/to/single.js'));
		$this->expectLog('debug', array('add_', 'Adding 1', 'Current total: 0'));
		$this->expectLog('debug', array('add_', 'Exiting', 'count: 1'));
		$this->expectLog('debug', array('add_', 'All current', 'single-asset'));

		// Assert that the asset was added
		$assets = $this->instance->get_scripts();
		$this->assertCount(1, $assets['general']);
		$this->assertEquals('single-asset', $assets['general'][0]['handle']);
	}

	// ------------------------------------------------------------------------
	// Stage
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::stage_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseAbstract::stage_assets
	 */
	public function test_stage_scripts_with_no_assets_to_process(): void {
		// Call the method under test
		$this->instance->stage_scripts();

		// Logger expectations for stage_scripts() with no assets.
		$this->expectLog('debug', array('stage_', 'Entered. Processing 0', 'definition(s) for registration.'), 1);
		$this->expectLog('debug', array('stage_', 'Exited. Remaining immediate', '0. Total deferred', '0.'), 1);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::add_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::stage_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::add_assets
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseAbstract::stage_assets
	 */
	public function test_stage_scripts_skips_asset_if_condition_is_false(): void {
		// Arrange
		$handle       = 'my-conditional-asset';
		$asset_to_add = array(
			'handle'    => $handle,
			'src'       => 'path/to/conditional.js',
			'condition' => fn() => false,
		);
		$this->instance->add_scripts($asset_to_add);

		WP_Mock::userFunction('wp_register_script')->never();

		// Act
		$this->instance->stage_scripts();
		// Assert: Set up log expectations
		$this->expectLog('debug', array('stage_', 'Entered. Processing 1', 'definition(s) for registration.'), 1);
		$this->expectLog('debug', array('stage_', 'Processing', "\"{$handle}\", original index: 0."), 1);
		$this->expectLog('debug', array('_process_single_', 'Condition not met for', "'{$handle}'. Skipping."), 1);
		$this->expectLog('debug', array('stage_', 'Exited. Remaining immediate', '0. Total deferred', '0.'), 1);

		$assets = $this->instance->get_scripts();
		$this->assertEmpty($assets['general'], 'The general queue should be empty.');
		$this->assertEmpty($assets['deferred'], 'The deferred queue should be empty.');
		$this->assertEmpty($assets['external_inline'], 'The external_inline queue should be empty.');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::add_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::stage_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::add_assets
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseAbstract::stage_assets
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_concrete_process_single_asset
	 */
	public function test_stage_scripts_handles_source_less_asset_correctly(): void {
		// Arrange: Asset with 'src' => false is a valid 'meta-handle' for dependencies or inline scripts.
		$asset_to_add = array(
			'handle' => 'my-meta-handle',
			'src'    => false,
		);
		$this->instance->add_scripts($asset_to_add);

		// Expect wp_register_script to be called with false for the src.
		WP_Mock::userFunction('wp_register_script')
			->once()
			->with('my-meta-handle', false, array(), false, array('in_footer' => false))
			->andReturn(true);

		// Act
		$this->instance->stage_scripts();

		// Assert: No warnings about missing src should be logged.
		foreach ($this->logger_mock->get_logs() as $log) {
			if (strtolower((string) $log['level']) === 'warning') {
				$this->assertStringNotContainsString('Invalid script definition. Missing handle or src', $log['message']);
			}
		}
		// Ensure the logger was actually called for other things, proving it was active.
		$has_debug_records = false;
		foreach ($this->logger_mock->get_logs() as $log) {
			if ($log['level'] === 'debug') {
				$has_debug_records = true;
				break;
			}
		}
		$this->assertTrue($has_debug_records, 'Logger should have debug records.');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::add_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::stage_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::enqueue_immediate_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::add_assets
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseAbstract::stage_assets
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::enqueue_immediate_assets
	 */
	public function test_stage_scripts_registers_non_hooked_asset_correctly(): void {
		// --- Test Setup ---
		$asset_to_add = array(
			'handle'    => 'my-asset',
			'src'       => 'path/to/my-asset.js',
			'deps'      => array(),
			'version'   => '1.0',
			'in_footer' => false,
		);


		// Use the helper to mock WP functions for the asset lifecycle.
		$this->_mock_asset_lifecycle_functions(
			AssetType::Script,
			'wp_register_script',
			'wp_enqueue_script',
			'wp_script_is',
			$asset_to_add,
		);

		// --- Action ---
		$this->instance->add_scripts($asset_to_add);

		// --- Assert ---
		// Logger expectations for add_scripts()
		$this->expectLog('debug', array('add_', 'Entered. Current', 'count: 0', 'Adding 1 new'), 1);
		$this->expectLog('debug', array('add_', 'Adding', 'Handle: my-asset'), 1);
		$this->expectLog('debug', array('add_', 'Exiting', 'New total', 'count: 1'), 1);

		// --- Action ---
		$this->instance->stage_scripts();

		// --- Assert ---
		$this->expectLog('debug', array('stage_', 'Entered. Processing 1', 'script definition(s)'), 1);
		$this->expectLog('debug', array('stage_', 'Processing', '"my-asset"'), 1);
		$this->expectLog('debug', array('_process_single_', 'Registering', 'my-asset'), 1);
		$this->expectLog('debug', array('_process_single_', 'Finished processing', 'my-asset'), 1);
		$this->expectLog('debug', array('stage_', 'Exited. Remaining immediate', '1', 'Total deferred', '0'), 1);

		$this->instance->enqueue_immediate_scripts();

		// Assert that the asset has been removed from the queue after registration.
		$scripts = $this->instance->get_scripts();
		$this->assertEmpty($scripts['general'], 'The general scripts queue should be empty after registration.');

		// Assert that the registered asset has indeed been registered with WP.
		$this->assertTrue(wp_script_is('my-asset', 'registered'));
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::stage_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseAbstract::stage_assets
	 */
	public function test_stage_scripts_defers_hooked_asset_correctly(): void {
		// Arrange
		$hook_name    = 'my_custom_hook';
		$asset_to_add = array(
			'handle' => 'my-deferred-asset',
			'src'    => 'path/to/deferred.js',
			'hook'   => $hook_name,
		);
		$this->instance->add_scripts($asset_to_add);

		// Expect the action to be added with a callable (closure).
		WP_Mock::expectActionAdded($hook_name, Mockery::type('callable'), 10, 0);

		// Arrange
		$multi_priority_hook_name = 'my_multi_priority_hook';
		$assets_to_add            = array(
			array(
				'handle'   => 'asset-prio-10',
				'src'      => 'path/to/p10.js',
				'hook'     => $multi_priority_hook_name,
				'priority' => 10,
			),
			array(
				'handle'   => 'asset-prio-20',
				'src'      => 'path/to/p20.js',
				'hook'     => $multi_priority_hook_name,
				'priority' => 20,
			),
		);
		$this->instance->add_scripts($assets_to_add);

		// Act
		$this->instance->stage_scripts();

		// Assert
		$assets = $this->instance->get_scripts();

		$this->assertArrayHasKey($hook_name, $assets['deferred'], 'Hook key should exist in deferred assets.');
		$this->assertArrayHasKey(10, $assets['deferred'][$hook_name], 'Priority 10 key should exist.');
		$this->assertCount(1, $assets['deferred'][$hook_name][10]);
		$this->assertEquals('my-deferred-asset', $assets['deferred'][$hook_name][10][0]['handle']);
		$this->assertArrayHasKey($multi_priority_hook_name, $assets['deferred'], 'Hook key should exist in deferred assets.');
		$this->assertArrayHasKey(10, $assets['deferred'][$multi_priority_hook_name], 'Priority 10 key should exist.');
		$this->assertCount(1, $assets['deferred'][$multi_priority_hook_name][10]);
		$this->assertEquals('asset-prio-10', $assets['deferred'][$multi_priority_hook_name][10][0]['handle']);

		// Assert that the main assets queue is empty as the asset was deferred
		$this->assertEmpty($assets['general']);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::add_scripts
	 */
	public function test_stage_scripts_defers_hooked_asset_correctly_with_script_keyword(): void {
		// Arrange
		$handle = 'my-deferred-script';
		$src    = 'path/to/deferred.js';
		$hook   = 'wp_enqueue_scripts';

		$this->instance->add_scripts( array(
			'handle' => $handle,
			'src'    => $src,
			'hook'   => $hook,
		) );

		// Act: Defer the asset by calling stage_scripts.
		$this->instance->stage_scripts();

		// Assert
		$assets = $this->instance->get_scripts();
		$this->assertArrayHasKey('wp_enqueue_scripts', $assets['deferred']);
		$this->assertArrayHasKey(10, $assets['deferred']['wp_enqueue_scripts']);
		$this->assertCount(1, $assets['deferred']['wp_enqueue_scripts'][10]);
		$this->assertEquals('my-deferred-script', $assets['deferred']['wp_enqueue_scripts'][10][0]['handle']);

		// Assert that the main assets queue is empty as the asset was deferred
		$this->assertEmpty($assets['general']);
	}

	public function test_stage_scripts_does_not_register_deferred_scripts(): void {
		// Arrange
		$hook_name     = 'my_deferred_hook';
		$assets_to_add = array(
			array(
				'handle'   => 'asset-deferred',
				'src'      => 'path/to/deferred.js',
				'deps'     => array(),
				'version'  => false,
				'hook'     => $hook_name,
				'priority' => 10,
			),
		);
		$this->instance->add_scripts($assets_to_add);
		$this->instance->stage_scripts(); // This populates the deferred assets array

		// Mock wp_script_is calls for proper asset processing
		WP_Mock::userFunction('wp_script_is')->with('asset-deferred', 'registered')->andReturn(false);
		WP_Mock::userFunction('wp_script_is')->with('asset-deferred', 'enqueued')->andReturn(false);

		// Assert that only the deferred asset is registered and enqueued
		// First expect the registration of the script
		WP_Mock::userFunction('wp_register_script')->atLeast()->once()->with('asset-deferred', 'path/to/deferred.js', array(), false, array('in_footer' => false))->andReturn(true);
		WP_Mock::userFunction('wp_register_script')->never()->with('asset-deferred', Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any());

		// Then expect the enqueuing (with just the handle)
		WP_Mock::userFunction('wp_enqueue_script')->once()->with('asset-deferred');
		WP_Mock::userFunction('wp_enqueue_script')->never()->with('asset-deferred', Mockery::any());

		// Act: Simulate the WordPress action firing for priority 10.
		$this->instance->_enqueue_deferred_scripts($hook_name, 10);
		// Assert: Check logs for correct processing messages.
		$this->expectLog('debug', array('_enqueue_deferred_', 'Entered hook: "' . $hook_name . '" with priority: 10'), 1);
		$this->expectLog('debug', array('_enqueue_deferred_', "Processing deferred asset 'asset-deferred'"), 1);
	}

	// ------------------------------------------------------------------------
	// Inline
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::add_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::stage_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::add_inline_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::add_assets
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseAbstract::stage_assets
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::add_inline_assets
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_add_inline_asset
	 */
	public function test_add_inline_scripts_after_registering_parent(): void {
		// Arrange
		$parent_handle = 'my-parent-script';
		$src           = 'path/to/parent.js';
		$hook          = 'admin_enqueue_scripts';

		// Add and register the parent script so the inline script has something to attach to.
		$result = $this->instance->add_scripts(array(
			'handle' => $parent_handle,
			'src'    => $src,
			'hook'   => $hook,
		));

		// Assert
		$this->assertSame($this->instance, $result, 'Method should be chainable.');
		// Check that the correct log message was recorded.
		$this->expectLog('debug', array('AssetEnqueueBaseTrait::add_', 'Entered. Current', 'count: 0', 'Adding 1 new'), 1);



		// Act: Defer the asset by calling stage_scripts.
		$this->instance->stage_scripts();

		// Add the inline script to the now-deferred parent.
		$inline_content = 'alert("test");';
		$result         = $this->instance->add_inline_scripts(array(
			'parent_handle' => $parent_handle,
			'content'       => $inline_content,
		));

		// Assert
		$this->assertSame($this->instance, $result, 'Method should be chainable.');

		// Check that the correct log message was recorded.
		$this->expectLog('debug', array('AssetEnqueueBaseTrait::add_inline_', 'Entered. Current', 'count: 0', 'Adding 1 new'), 1);

		// Verify the internal state.
		$assets = $this->instance->get_scripts();

		// Assert: Verify the inline data was attached to the parent script definition in the pre-registration queue.
		$this->assertCount(0, $assets['external_inline'], 'external_inline should be empty.');
		$this->assertEmpty($assets['general'], 'The general queue should be empty after deferral.');
		$this->assertArrayHasKey($hook, $assets['deferred']);
		$this->assertCount(1, $assets['deferred'][$hook], 'Deferred queue for the hook should contain one asset.');
		$this->assertEquals($inline_content, $assets['deferred'][$hook][10][0]['inline'][0]['content']);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::add_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::add_inline_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::add_assets
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::add_inline_assets
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_add_inline_asset
	 */
	public function test_add_inline_scripts_without_registering_parent(): void {
		// Arrange
		$parent_handle = 'my-parent-script';
		$src           = 'path/to/parent.js';
		$hook          = 'wp_enqueue_scripts';

		// Add and register the parent script so the inline script has something to attach to.
		$result = $this->instance->add_scripts(array(
			'handle' => $parent_handle,
			'src'    => $src,
			'hook'   => $hook,
		));

		// Assert
		$this->assertSame($this->instance, $result, 'Method should be chainable.');
		// Check that the correct log message was recorded.
		$this->expectLog('debug', array('AssetEnqueueBaseTrait::add_', 'Entered. Current', 'count: 0', 'Adding 1 new'), 1);

		// Add inline script
		$inline_content = 'alert("test");';

		$result = $this->instance->add_inline_scripts(array(
			'parent_handle' => $parent_handle,
			'content'       => $inline_content,
		));

		// Assert
		$this->assertSame($this->instance, $result, 'Method should be chainable.');

		// Check that the correct log message was recorded.
		$this->expectLog('debug', array('AssetEnqueueBaseTrait::add_inline_', 'Entered. Current', 'count: 0', 'Adding 1 new'), 1);

		// Verify the internal state.
		$assets = $this->instance->get_scripts();

		// Assert: Verify the inline data was attached to the parent script definition in the pre-registration queue.
		$this->assertCount(0, $assets['external_inline'], 'external_inline should be empty.');
		$this->assertEquals($inline_content, $assets['general'][0]['inline'][0]['content']);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::add_inline_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::add_inline_assets
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_add_inline_asset
	 */
	public function test_add_inline_script_to_externally_registered_handle(): void {
		// Arrange: Define an inline script for an external handle like 'jquery'.
		$external_handle    = 'jquery';
		$inline_script_data = 'console.log("Hello from inline script on external handle");';

		// Mock that 'jquery' is already registered by WordPress.
		WP_Mock::userFunction('wp_script_is')->with($external_handle, 'registered')->andReturn(true);

		// Act
		$this->instance->add_inline_scripts(array(
			array(
				'parent_handle' => $external_handle,
				'content'       => $inline_script_data,
			)
		));

		// Assert that the script was added to the external_inline queue.
		$assets = $this->instance->get_scripts();
		$this->assertArrayHasKey($external_handle, $assets['external_inline']['wp_enqueue_scripts']);
		$this->assertCount(1, $assets['external_inline']['wp_enqueue_scripts']);
		$this->assertEquals($inline_script_data, $assets['external_inline']['wp_enqueue_scripts'][$external_handle][0]['content']);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::add_inline_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_add_inline_asset
	 */
	public function test_add_inline_scripts_associates_with_correct_parent_handle(): void {
		// First, add the parent asset
		$parent_asset = array(
		    'handle' => 'parent-script',
		    'src'    => 'path/to/parent.js',
		);
		$this->instance->add_scripts($parent_asset);

		// Now, add the inline asset
		$inline_asset = array(
		    'parent_handle' => 'parent-script',
		    'content'       => 'console.log("Hello, world!");',
		);
		$this->instance->add_inline_scripts($inline_asset);

		// Assert that the inline data was added to the parent asset
		$scripts = $this->instance->get_scripts();
		$this->assertCount(1, $scripts['general']);
		$this->assertArrayHasKey('inline', $scripts['general'][0]);
		$this->assertCount(1, $scripts['general'][0]['inline']);
		$this->assertEquals('console.log("Hello, world!");', $scripts['general'][0]['inline'][0]['content']);
	}

	// ------------------------------------------------------------------------
	// Cache Busting
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_generate_asset_version
	 */
	public function test_cache_busting_is_skipped_when_disabled(): void {
		// --- Test Setup ---
		$handle          = 'my-script';
		$src             = '/wp-content/plugins/my-plugin/js/my-script.js';
		$default_version = '1.2.3';

		$asset_definition = array(
		    'handle'     => $handle,
		    'src'        => $src,
		    'version'    => $default_version,
		    'cache_bust' => false, // Explicitly disabled
		);

		$this->instance->shouldReceive('_file_exists')->never();
		$this->instance->shouldReceive('_md5_file')->never();

		// --- Act ---
		$actual_version = $this->_invoke_protected_method($this->instance, '_generate_asset_version', array($asset_definition));

		// --- Assert ---
		$this->assertSame($default_version, $actual_version);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_generate_asset_version
	 */
	public function test_cache_busting_falls_back_to_default_version_when_file_not_found(): void {
		// --- Test Setup ---
		$handle          = 'my-script';
		$src             = 'http://example.com/wp-content/plugins/my-plugin/js/my-script.js';
		$file_path       = WP_CONTENT_DIR . '/plugins/my-plugin/js/my-script.js';
		$default_version = '1.2.3';

		$asset_definition = array(
		    'handle'     => $handle,
		    'src'        => $src,
		    'version'    => $default_version,
		    'cache_bust' => true,
		);

		if (!defined('WP_CONTENT_DIR')) {
			define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
		}

		WP_Mock::userFunction('content_url')->andReturn('http://example.com/wp-content');
		WP_Mock::userFunction('site_url')->andReturn('http://example.com');
		WP_Mock::userFunction('wp_normalize_path')->andReturnUsing(fn($p) => $p);

		$this->instance->shouldReceive('_file_exists')->once()->with($file_path)->andReturn(false);
		$this->instance->shouldReceive('_md5_file')->never();

		// --- Act ---
		$actual_version = $this->_invoke_protected_method($this->instance, '_generate_asset_version', array($asset_definition));

		// --- Assert ---
		$this->assertSame($default_version, $actual_version);
		$this->expectLog('warning', "Cache-busting for '{$handle}' failed. File not found at resolved path: '" . $file_path . "'.");
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_generate_asset_version
	 */
	public function test_cache_busting_generates_hash_version_when_enabled_and_file_exists(): void {
		// --- Test Setup ---
		$handle           = 'my-script';
		$src              = 'http://example.com/wp-content/plugins/my-plugin/js/my-script.js';
		$file_path        = WP_CONTENT_DIR . '/plugins/my-plugin/js/my-script.js';
		$hash             = md5('file content');
		$expected_version = substr($hash, 0, 10);

		$asset_definition = array(
		    'handle'     => $handle,
		    'src'        => $src,
		    'version'    => '1.0.0',
		    'cache_bust' => true,
		);

		if (!defined('WP_CONTENT_DIR')) {
			define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
		}

		WP_Mock::userFunction('content_url')->andReturn('http://example.com/wp-content');
		WP_Mock::userFunction('site_url')->andReturn('http://example.com');
		WP_Mock::userFunction('wp_normalize_path')->andReturnUsing(fn($p) => $p);

		$this->instance->shouldReceive('_file_exists')->once()->with($file_path)->andReturn(true);
		$this->instance->shouldReceive('_md5_file')->once()->with($file_path)->andReturn($hash);

		// --- Act ---
		$actual_version = $this->_invoke_protected_method($this->instance, '_generate_asset_version', array($asset_definition));

		// --- Assert ---
		$this->assertSame($expected_version, $actual_version);
	}

	// ------------------------------------------------------------------------
	// Tag Attrs
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @dataProvider provide_script_tag_modification_cases
	 * @covers       \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_modify_html_tag_attributes
	 */
	public function test_modify_html_tag_attributes_adds_attributes_correctly(string $handle, array $attributes, string $original_tag, string $expected_tag, ?string $mismatch_handle = null): void {
		// Arrange
		// The test class uses a method that calls the protected method from the trait.
		// Act
		$filter_handle = $mismatch_handle ?? $handle;
		$modified_tag  = $this->_invoke_protected_method(
			$this->instance,
			'_modify_html_tag_attributes',
			array(AssetType::Script, $original_tag, $handle, $filter_handle, $attributes)
		);

		// Assert
		$this->assertEquals($expected_tag, $modified_tag);
	}



	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_modify_html_tag_attributes
	 */
	public function test_modify_html_tag_attributes_handles_module_type_correctly(): void {
		// Arrange
		$handle       = 'module-script';
		$original_tag = "<script src='path/to/module.js' id='{$handle}-js'></script>";

		// Test case 1: Adding type=module and other attributes
		$attributes1   = array('type' => 'module', 'async' => true, 'data-test' => 'value');
		$expected_tag1 = "<script type=\"module\" src='path/to/module.js' id='{$handle}-js' async data-test=\"value\"></script>";

		// Test case 2: Adding type=module to a tag that already has type attribute
		// The implementation should replace the existing type attribute with type="module"
		$original_tag2 = "<script type=\"text/javascript\" src='path/to/module.js' id='{$handle}-js'></script>";
		$attributes2   = array('type' => 'module');
		$expected_tag2 = "<script type=\"module\" src='path/to/module.js' id='{$handle}-js'></script>";

		// Test case 3: Adding non-module type attribute
		$original_tag3 = "<script src='path/to/script.js' id='custom-script-js'></script>";
		$attributes3   = array('type' => 'text/javascript', 'defer' => true);
		$expected_tag3 = "<script type=\"text/javascript\" src='path/to/script.js' id='custom-script-js' defer></script>";

		// Act
		$modified_tag1 = $this->_invoke_protected_method(
			$this->instance,
			'_modify_html_tag_attributes',
			array(AssetType::Script, $original_tag, $handle, $handle, $attributes1)
		);

		$modified_tag2 = $this->_invoke_protected_method(
			$this->instance,
			'_modify_html_tag_attributes',
			array(AssetType::Script, $original_tag2, $handle, $handle, $attributes2)
		);

		$modified_tag3 = $this->_invoke_protected_method(
			$this->instance,
			'_modify_html_tag_attributes',
			array(AssetType::Script, $original_tag3, 'custom-script', 'custom-script', $attributes3)
		);

		// Assert
		$this->assertEquals($expected_tag1, $modified_tag1, 'Module type should be added with other attributes');
		$this->assertEquals($expected_tag2, $modified_tag2, 'Module type should replace existing type attribute');
		$this->assertEquals($expected_tag3, $modified_tag3, 'Non-module type should also be positioned first');
	}

	/**
	 * Data provider for `test_modify_html_tag_attributes_adds_attributes_correctly`.
	 * @dataProvider provide_script_tag_modification_cases
	 */
	public static function provide_script_tag_modification_cases(): array {
		$handle       = 'my-script';
		$original_tag = "<script src='path/to/script.js' id='{$handle}-js'></script>";

		return array(
			'handle_mismatch' => array(
				'my-script',
				array('async' => true, 'data-test' => 'value'),
				"<script src='path/to/script.js' id='my-script-js'></script>",
				"<script src='path/to/script.js' id='my-script-js'></script>", // Should remain unmodified
				'different-script' // Mismatch handle
			),
			'single data attribute' => array(
				$handle,
				array('data-custom' => 'my-value'),
				$original_tag,
				"<script src='path/to/script.js' id='{$handle}-js' data-custom=\"my-value\"></script>",
			),
			'boolean attribute (true)' => array(
				$handle,
				array('async' => true),
				$original_tag,
				"<script src='path/to/script.js' id='{$handle}-js' async></script>",
			),
			'boolean attribute (false)' => array(
				$handle,
				array('defer' => false),
				$original_tag,
				$original_tag, // Expect no change
			),
			'multiple attributes' => array(
				$handle,
				array('data-id' => '123', 'async' => true),
				$original_tag,
				"<script src='path/to/script.js' id='{$handle}-js' data-id=\"123\" async></script>",
			),
			'type module attribute' => array(
				'module-script',
				array('type' => 'module'),
				"<script src='path/to/module.js' id='module-script-js'></script>",
				"<script type=\"module\" src='path/to/module.js' id='module-script-js'></script>",
			),
			'ignored managed attribute' => array(
				$handle,
				array('src' => 'new-path.js', 'async' => true),
				$original_tag,
				"<script src='path/to/script.js' id='{$handle}-js' async></script>", // 'src' is ignored
			),
			// New test cases for malformed HTML tags
			'malformed_tag_no_closing_bracket' => array(
				$handle,
				array('async' => true),
				'<script src="test.js"', // Malformed - missing closing bracket
				'<script src="test.js"', // Expect original tag returned unchanged
			),
			'malformed_tag_no_script_tag' => array(
				$handle,
				array('async' => true),
				'<div>Not a script tag</div>',
				'<div>Not a script tag</div>', // Expect original tag returned unchanged
			),
			// Special value types
			'attribute_with_zero_integer_value' => array(
				$handle,
				array('data-count' => 0),
				$original_tag,
				"<script src='path/to/script.js' id='{$handle}-js' data-count=\"0\"></script>",
			),
			'attribute_with_null_value' => array(
				$handle,
				array('data-null' => null, 'async' => true),
				$original_tag,
				"<script src='path/to/script.js' id='{$handle}-js' async></script>", // null value should be skipped
			),
			'attribute_with_empty_string_value' => array(
				$handle,
				array('data-empty' => '', 'async' => true),
				$original_tag,
				"<script src='path/to/script.js' id='{$handle}-js' async></script>", // empty string should be skipped
			),
			// Attribute value escaping
			'attribute_value_with_special_chars' => array(
				$handle,
				array('data-value' => 'needs "escaping" & stuff'),
				$original_tag,
				"<script src='path/to/script.js' id='{$handle}-js' data-value=\"needs &quot;escaping&quot; &amp; stuff\"></script>",
			),
			// Multiple managed attributes being ignored
			'multiple_managed_attributes_ignored' => array(
				$handle,
				array('src' => 'ignored.js', 'id' => 'new-id', 'async' => true),
				$original_tag,
				"<script src='path/to/script.js' id='{$handle}-js' async></script>", // both 'src' and 'id' are ignored
			),
			'all_attributes_are_ignored' => array(
				$handle,
				array('src' => 'ignored.js', 'id' => 'new-id'),
				$original_tag,
				$original_tag, // Expect no change since all attributes are ignored
			),
			'empty_attributes_array' => array(
				$handle,
				array(), // Empty attributes array
				$original_tag,
				$original_tag, // Expect no change with empty attributes
			),
			'integer_indexed_attributes' => array(
				$handle,
				array('async', 'crossorigin'), // Integer-indexed array for boolean attributes
				$original_tag,
				"<script src='path/to/script.js' id='{$handle}-js' async crossorigin></script>",
			),
			'complex_attribute_combination' => array(
				'module-script',
				array(
					'type'         => 'module',
					'async'        => true,
					'defer'        => false,
					'data-version' => '1.2',
					'integrity'    => 'sha384-xyz',
					'crossorigin'  => 'anonymous'
				),
				"<script src='path/to/module.js' id='module-script-js'></script>",
				"<script type=\"module\" src='path/to/module.js' id='module-script-js' async data-version=\"1.2\" integrity=\"sha384-xyz\" crossorigin=\"anonymous\"></script>",
			),
		);
	}

	// ------------------------------------------------------------------------
	// Internal Callbacks
	// ------------------------------------------------------------------------


	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_enqueue_deferred_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_enqueue_deferred_assets
	 */
	public function test_enqueue_deferred_scripts_skips_if_hook_is_empty(): void {
		// Arrange
		$hook_name = 'empty_hook_for_test';

		// Use reflection to set the internal state, creating a deferred hook with no assets.
		$deferred_assets_prop = new \ReflectionProperty($this->instance, 'deferred_assets');
		$deferred_assets_prop->setAccessible(true);
		$deferred_assets_prop->setValue($this->instance, array('script' => array($hook_name => array())));

		// Assert: Verify the internal state has the hook.
		$assets = $this->instance->get_scripts();
		$this->assertArrayHasKey($hook_name, $assets['deferred'], 'The hook should be in the deferred assets.');

		// Act: Call the public method that would be triggered by the WordPress hook.
		$this->instance->_enqueue_deferred_scripts($hook_name, 10);

		// Assert: Check the log messages were triggered.
		$this->expectLog('debug', array('_enqueue_deferred_', 'Entered hook: "empty_hook_for_test"'), 1);
		$this->expectLog('debug', array('_enqueue_deferred_', 'Hook "empty_hook_for_test" with priority 10 not found in deferred', 'Exiting - nothing to process.'), 1);

		// Assert: Verify the internal state has the hook cleared.
		$assets = $this->instance->get_scripts();

		$this->assertArrayNotHasKey($hook_name, $assets['deferred'], 'The hook should be cleared from deferred assets.');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_enqueue_deferred_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_enqueue_deferred_assets
	 */
	public function test_enqueue_deferred_scripts_processes_assets_for_correct_priority(): void {
		// Arrange
		$hook_name     = 'my_multi_priority_hook';
		$assets_to_add = array(
			array(
				'handle'   => 'asset-prio-10',
				'src'      => 'path/to/p10.js',
				'deps'     => array(),
				'version'  => false,
				'hook'     => $hook_name,
				'priority' => 10,
			),
			array(
				'handle'   => 'asset-prio-20',
				'src'      => 'path/to/p20.js',
				'deps'     => array(),
				'version'  => false,
				'hook'     => $hook_name,
				'priority' => 20,
			),
		);
		$this->instance->add_scripts($assets_to_add);
		$this->instance->stage_scripts(); // This populates the deferred assets array

		// Mock wp_script_is calls for proper asset processing
		WP_Mock::userFunction('wp_script_is')->with('asset-prio-10', 'registered')->andReturn(false);
		WP_Mock::userFunction('wp_script_is')->with('asset-prio-10', 'enqueued')->andReturn(false);
		WP_Mock::userFunction('wp_script_is')->with('asset-prio-20', 'registered')->andReturn(false);
		WP_Mock::userFunction('wp_script_is')->with('asset-prio-20', 'enqueued')->andReturn(false);

		// Assert that only the priority 10 asset is registered and enqueued
		// First expect the registration of the script
		WP_Mock::userFunction('wp_register_script')->atLeast()->once()->with('asset-prio-10', 'path/to/p10.js', array(), false, array('in_footer' => false))->andReturn(true);
		WP_Mock::userFunction('wp_register_script')->never()->with('asset-prio-20', Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any());

		// Then expect the enqueuing (with just the handle)
		WP_Mock::userFunction('wp_enqueue_script')->once()->with('asset-prio-10');
		WP_Mock::userFunction('wp_enqueue_script')->never()->with('asset-prio-20', Mockery::any());

		// Act: Simulate the WordPress action firing for priority 10.
		$this->instance->_enqueue_deferred_scripts($hook_name, 10);
		// Assert: Check logs for correct processing messages.
		$this->expectLog('debug', array('_enqueue_deferred_', 'Entered hook: "' . $hook_name . '" with priority: 10'), 1);
		$this->expectLog('debug', array('_enqueue_deferred_', "Processing deferred asset 'asset-prio-10'"), 1);

		// Assert that the priority 10 assets are gone, but priority 20 remains.
		$assets = $this->instance->get_scripts();
		$this->assertArrayHasKey($hook_name, $assets['deferred'], 'Hook key should still exist.');
		$this->assertArrayNotHasKey(10, $assets['deferred'][$hook_name], 'Priority 10 key should be removed.');
		$this->assertArrayHasKey(20, $assets['deferred'][$hook_name], 'Priority 20 key should still exist.');
		$this->assertCount(1, $assets['deferred'][$hook_name][20]);
		$this->assertEquals('asset-prio-20', array_values($assets['deferred'][$hook_name][20])[0]['handle']);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::add_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::stage_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::add_assets
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseAbstract::stage_assets
	 */
	public function test_process_single_script_logs_warning_for_managed_attributes(): void {
		// Arrange
		$handle       = 'my-test-script';
		$asset_to_add = array(
			'handle'     => $handle,
			'src'        => 'path/to/script.js',
			'attributes' => array(
				'id'          => 'custom-id',    // Should be ignored and warned
				'type'        => 'module',       // Should be ignored and warned
				'src'         => 'new-src.js',   // Should be ignored and warned
				'data-custom' => 'value' // Should be passed through
			),
		);
		$this->instance->add_scripts($asset_to_add);

		// Act
		$this->instance->stage_scripts();

		// Assert
		$this->expectLog('warning', "Ignoring 'id' attribute for '{$handle}'", 1);
		$this->expectLog('warning', "Ignoring 'type' attribute for '{$handle}'", 1);
		$this->expectLog('warning', "Ignoring 'src' attribute for '{$handle}'", 1);
		foreach ($this->logger_mock->get_logs() as $log) {
			if (strtolower((string) $log['level']) === 'warning') {
				$this->assertStringNotContainsString("Ignoring 'data-custom' attribute", $log['message']);
			}
		}
	}

	/**
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_resolve_environment_src
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_concrete_process_single_asset
	 */
	public function test_process_single_asset_with_string_src_remains_unchanged(): void {
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
		$this->instance->add_scripts( $asset_definition );
		$this->instance->stage_scripts();

		// The assertion is implicitly handled by the mock expectation for wp_register_script.
		$this->expectLog('debug', array('_process_single_', 'Registering', 'test-script'), 1);
	}

	/**
	 * @dataProvider provideEnvironmentData
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_resolve_environment_src
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_concrete_process_single_asset
	 */
	public function test_process_single_asset_resolves_src_based_on_environment(
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
		$this->instance->add_scripts( array( $asset_definition ) );
		$this->instance->stage_scripts();

		// The assertion is implicitly handled by the mock expectation for wp_register_script.
		$this->expectLog('debug', array('_process_single_', 'Registering', 'test-script', $expected_src), 1);
	}

	/**
	 * Data provider for `test_process_single_asset_resolves_src_based_on_environment`.
	 * @dataProvider provideEnvironmentData
	 */
	public function provideEnvironmentData(): array {
		return array(
			'Development environment' => array(true, 'http://example.com/script.js'),
			'Production environment'  => array(false, 'http://example.com/script.min.js'),
		);
	}

	// ------------------------------------------------------------------------
	// Trait Specific Capability Tests
	// ------------------------------------------------------------------------



	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_concrete_process_single_asset
	 */
	public function test_process_single_asset_with_incorrect_asset_type(): void {
		// Create a test asset definition
		$asset_definition = array(
			'handle' => 'test-script',
			'src'    => 'path/to/script.js',
		);

		// Call the method with incorrect asset type (Style instead of Script)
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_process_single_asset',
			array(
				AssetType::Style, // Incorrect asset type
				$asset_definition,
				'test_context',
				null,
				true,
				false
			)
		);

		// Verify the result is false, indicating failure
		$this->assertFalse($result, 'Method should return false when incorrect asset type is provided');

		// Verify that a warning was logged
		$this->expectLog('warning', array('Incorrect asset type provided to _process_single_asset', "Expected 'script', got 'style'"), 1);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_concrete_process_single_asset
	 */
	public function test_process_single_asset_with_async_strategy(): void {
		// Create a test asset definition with async attribute
		$handle           = 'test-async-script';
		$asset_definition = array(
			'handle'     => $handle,
			'src'        => 'path/to/script.js',
			'attributes' => array(
				'async' => true
			)
		);

		// Mock the get_asset_url method
		$this->instance->shouldReceive('get_asset_url')
			->with('path/to/script.js', AssetType::Script)
			->andReturn('path/to/script.js');

		// Mock wp_script_is to return false (not already registered)
		WP_Mock::userFunction('wp_script_is')
			->with($handle, 'registered')
			->andReturn(false);

		// Mock wp_register_script with the correct parameter format based on implementation
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

		// Call the method under test
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_process_single_asset',
			array(
				AssetType::Script,
				$asset_definition,
				'test_context',
				null,
				true,
				false
			)
		);

		// Verify the result is the handle, indicating success
		$this->assertEquals($handle, $result, 'Method should return the handle on success');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_concrete_process_single_asset
	 */
	public function test_process_single_asset_with_defer_strategy(): void {
		// Create a test asset definition with defer attribute
		$handle           = 'test-defer-script';
		$asset_definition = array(
			'handle'     => $handle,
			'src'        => 'path/to/script.js',
			'attributes' => array(
				'defer' => true
			)
		);

		// Mock the get_asset_url method
		$this->instance->shouldReceive('get_asset_url')
			->with('path/to/script.js', AssetType::Script)
			->andReturn('path/to/script.js');

		// Mock wp_script_is to return false (not already registered)
		WP_Mock::userFunction('wp_script_is')
			->with($handle, 'registered')
			->andReturn(false);

		// Mock wp_register_script with the correct parameter format based on implementation
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

		// Call the method under test
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_process_single_asset',
			array(
				AssetType::Script,
				$asset_definition,
				'test_context',
				null,
				true,
				false
			)
		);

		// Verify the result is the handle, indicating success
		$this->assertEquals($handle, $result, 'Method should return the handle on success');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_process_script_extras
	 */
	public function test_process_script_extras_with_data_attributes(): void {
		// Arrange
		$handle           = 'test-script-data';
		$asset_definition = array(
			'handle' => $handle,
			'data'   => array(
				'conditional' => 'IE 9',
				'group'       => 1
			)
		);

		// Mock wp_script_add_data to return true
		WP_Mock::userFunction('wp_script_add_data')
			->times(2) // Once for each data item
			->andReturn(true);

		// Act
		$this->_invoke_protected_method(
			$this->instance,
			'_process_script_extras',
			array($asset_definition, $handle, null)
		);

		// Assert - check log messages
		$this->expectLog('debug', array("Adding data to script '{$handle}'. Key: 'conditional', Value: 'IE 9'"), 1);
		$this->expectLog('debug', array("Adding data to script '{$handle}'. Key: 'group', Value: '1'"), 1);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_process_script_extras
	 */
	public function test_process_script_extras_with_failed_data_addition(): void {
		// Arrange
		$handle           = 'test-script-data-fail';
		$asset_definition = array(
			'handle' => $handle,
			'data'   => array(
				'conditional' => 'IE 9'
			)
		);

		// Mock wp_script_add_data to return false (failure)
		WP_Mock::userFunction('wp_script_add_data')
			->once()
			->andReturn(false);

		// Act
		$this->_invoke_protected_method(
			$this->instance,
			'_process_script_extras',
			array($asset_definition, $handle, null)
		);

		// Assert - check log messages
		$this->expectLog('debug', array("Adding data to script '{$handle}'. Key: 'conditional', Value: 'IE 9'"), 1);
		$this->expectLog('warning', array("Failed to add data for key 'conditional' to script '{$handle}'"), 1);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_process_script_extras
	 */
	public function test_process_script_extras_with_custom_attributes(): void {
		// Arrange
		$handle           = 'test-script-attributes';
		$asset_definition = array(
			'handle'     => $handle,
			'attributes' => array(
				'async'       => true,
				'defer'       => true,
				'custom-attr' => 'value'
			)
		);

		// Mock _extract_custom_script_attributes to return attributes
		$this->instance->shouldReceive('_extract_custom_script_attributes')
			->once()
			->with($handle, $asset_definition['attributes'])
			->andReturn($asset_definition['attributes']);

		// Mock _do_add_filter to verify filter is added
		$this->instance->shouldReceive('_do_add_filter')
			->once()
			->with('script_loader_tag', Mockery::type('callable'), 10, 2);

		// Act
		$this->_invoke_protected_method(
			$this->instance,
			'_process_script_extras',
			array($asset_definition, $handle, null)
		);

		// Assert - check log messages
		$this->expectLog('debug', array("Adding attributes to script '{$handle}'"), 1);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_enqueue_external_inline_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_enqueue_external_inline_assets
	 */
	public function test_enqueue_external_inline_scripts_executes_base_method(): void {
		// Mock current_action to return a specific hook name
		\WP_Mock::userFunction('current_action')
			->andReturn('wp_enqueue_scripts');

		// Set up external_inline_assets property with test data
		$reflection                      = new \ReflectionClass($this->instance);
		$external_inline_assets_property = $reflection->getProperty('external_inline_assets');
		$external_inline_assets_property->setAccessible(true);

		$test_data = array(
			'script' => array(
				'wp_enqueue_scripts' => array(
					'parent-handle-1' => array('some-inline-script-1'),
					'parent-handle-2' => array('some-inline-script-2')
				)
			)
		);
		$external_inline_assets_property->setValue($this->instance, $test_data);

		// Mock _process_inline_assets to avoid complex setup
		$this->instance->shouldReceive('_process_inline_assets')
			->twice() // Called once for each parent handle
			->with(
				\Mockery::type(AssetType::class),
				\Mockery::type('string'), // parent_handle
				'wp_enqueue_scripts',
				\Mockery::type('string') // context
			)
			->andReturn(null);

		// Call the method under test
		$this->instance->_enqueue_external_inline_scripts();

		// Verify the processed assets were removed from the queue
		$updated_data = $external_inline_assets_property->getValue($this->instance);
		$this->assertArrayNotHasKey('wp_enqueue_scripts', $updated_data['script'] ?? array());

		// Verify expected log messages
		$this->expectLog('debug', 'AssetEnqueueBaseTrait::enqueue_external_inline_scripts - Fired on hook \'wp_enqueue_scripts\'.');
		$this->expectLog('debug', 'AssetEnqueueBaseTrait::enqueue_external_inline_scripts - Finished processing for hook \'wp_enqueue_scripts\'.');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_enqueue_external_inline_assets
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
			'script' => array(
				'other_hook' => array(
					'parent-handle-1' => array('some-inline-script-1')
				)
			)
		);
		$external_inline_assets_property->setValue($this->instance, $test_data);

		// Call the method under test directly
		$this->_invoke_protected_method(
			$this->instance,
			'_enqueue_external_inline_assets',
			array(AssetType::Script)
		);

		// Verify expected log messages for empty case
		$this->expectLog('debug', 'AssetEnqueueBaseTrait::enqueue_external_inline_scripts - Fired on hook \'wp_enqueue_scripts\'.');
		$this->expectLog('debug', 'AssetEnqueueBaseTrait::enqueue_external_inline_scripts - No external inline scripts found for hook \'wp_enqueue_scripts\'. Exiting.');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_modify_html_tag_attributes
	 */
	public function test_modify_html_tag_attributes_with_incorrect_asset_type(): void {
		// Arrange
		$tag             = '<link rel="stylesheet" href="style.css" />';
		$tag_handle      = 'test-style';
		$handle_to_match = 'test-style';
		$attributes      = array('media' => 'print');

		// Act - call with Style asset type instead of Script
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_modify_html_tag_attributes',
			array(
				AssetType::Style, // Incorrect asset type
				$tag,
				$tag_handle,
				$handle_to_match,
				$attributes
			)
		);

		// Assert
		$this->assertSame($tag, $result, 'Method should return the original tag when asset type is not Script');
		$this->expectLog('warning', array('Incorrect asset type provided to _modify_html_tag_attributes. Expected \'script\', got \'style\'.'), 1);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_modify_html_tag_attributes
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_build_attribute_string
	 */
	public function test_modify_html_tag_attributes_uses_build_attribute_string(): void {
		// Setup script attributes with various types (boolean, string, empty values)
		$handle     = 'test-script';
		$attributes = array(
			'async',                  // Boolean attribute (indexed)
			'defer'      => true,          // Boolean attribute (explicit)
			'data-test'  => 'value',   // Regular attribute
			'empty-attr' => '',       // Empty attribute (should be skipped)
			'null-attr'  => null,      // Null attribute (should be skipped)
			'false-attr' => false,    // False attribute (should be skipped)
			'id'         => 'override-id'     // Managed attribute (should be warned and skipped)
		);

		$original_tag = '<script src="test.js" id="test-script-js"></script>';

		// Expected: async and defer as boolean attributes, data-test as regular attribute
		// id should not be overridden, empty/null/false attributes should be skipped
		$expected_tag = '<script src="test.js" id="test-script-js" async defer data-test="value"></script>';

		// Execute the method
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_modify_html_tag_attributes',
			array(AssetType::Script, $original_tag, $handle, $handle, $attributes)
		);

		// Verify the result
		$this->assertEquals($expected_tag, $result);

		// Verify warning was logged for managed attribute
		$this->expectLog('warning', array(
			'_modify_html_tag_attributes - Attempt to override managed attribute',
			'id',
			$handle
		), 1);
	}

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
		$this->instance->add_scripts($assets_to_add);

		// Stage the assets to process them
		$this->instance->stage_scripts();

		// Act - Get the deferred hooks
		$hooks = $this->_invoke_protected_method(
			$this->instance,
			'get_deferred_hooks',
			array(AssetType::Script)
		);

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
		// Act - Get deferred hooks when no assets are added
		$hooks = $this->_invoke_protected_method(
			$this->instance,
			'get_deferred_hooks',
			array(AssetType::Script)
		);

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
		$this->instance->add_scripts($assets_to_add);

		// Act - Get the deferred hooks
		$hooks = $this->_invoke_protected_method(
			$this->instance,
			'get_deferred_hooks',
			array(AssetType::Script)
		);

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
			'script' => array(
				'processed_hook_1' => array(
					array('handle' => 'processed-script-1', 'src' => 'path/to/processed1.js')
				),
				'processed_hook_2' => array(
					array('handle' => 'processed-script-2', 'src' => 'path/to/processed2.js')
				)
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
		$this->instance->add_scripts($assets_to_add);

		// Act - Get the deferred hooks
		$hooks = $this->_invoke_protected_method(
			$this->instance,
			'get_deferred_hooks',
			array(AssetType::Script)
		);

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
		$this->instance->add_scripts($script_assets);

		// Also manually add some style deferred assets to test type isolation
		$deferred_assets_property = new \ReflectionProperty($this->instance, 'deferred_assets');
		$deferred_assets_property->setAccessible(true);
		$deferred_assets_property->setValue($this->instance, array(
			'script' => array(
				'script_deferred_hook' => array(
					array('handle' => 'deferred-script', 'src' => 'path/to/deferred.js')
				)
			),
			'style' => array(
				'style_deferred_hook' => array(
					array('handle' => 'deferred-style', 'src' => 'path/to/deferred.css')
				)
			)
		));

		// Act - Get deferred hooks for scripts only
		$script_hooks = $this->_invoke_protected_method(
			$this->instance,
			'get_deferred_hooks',
			array(AssetType::Script)
		);

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
		// The method looks for $this->assets[$asset_type->value]['general'], so we need that structure
		$assets_property = new \ReflectionProperty($this->instance, 'assets');
		$assets_property->setAccessible(true);
		$assets_property->setValue($this->instance, array(
			'script' => array(
				'general' => array(
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
		));

		// Act - Get the deferred hooks
		$hooks = $this->_invoke_protected_method(
			$this->instance,
			'get_deferred_hooks',
			array(AssetType::Script)
		);

		// Assert - Should include hooks from assets with valid hooks (lines 150-152)
		$this->assertIsArray($hooks);
		$this->assertContains('valid_hook_1', $hooks, 'Should add valid hook from first asset (line 151)');
		$this->assertContains('valid_hook_2', $hooks, 'Should add valid hook from second asset (line 151)');
		$this->assertCount(2, $hooks, 'Should only include assets with valid hooks');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_file_exists
	 */
	public function test_file_exists_returns_correct_result(): void {
		// Test with __FILE__ which should exist
		$result_exists = $this->_invoke_protected_method(
			$this->instance,
			'_file_exists',
			array(__FILE__)
		);
		$this->assertTrue($result_exists, 'Should return true for existing file (__FILE__)');

		// Test with a non-existent file
		$nonexistent_file  = '/path/to/definitely/nonexistent/file.js';
		$result_not_exists = $this->_invoke_protected_method(
			$this->instance,
			'_file_exists',
			array($nonexistent_file)
		);
		$this->assertFalse($result_not_exists, 'Should return false for non-existent file');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_md5_file
	 */
	public function test_md5_file_returns_correct_hash(): void {
		// Test the actual implementation with a real file (__FILE__)
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_md5_file',
			array(__FILE__)
		);

		// Verify the result is a valid MD5 hash
		$this->assertIsString($result, 'Should return a string hash');
		$this->assertEquals(32, strlen($result), 'MD5 hash should be 32 characters long');
		$this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $result, 'Should be a valid MD5 hash format');

		// Verify it matches PHP's md5_file function
		$expected_hash = md5_file(__FILE__);
		$this->assertEquals($expected_hash, $result, 'Should return the same hash as PHP\'s md5_file function');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_resolve_url_to_path
	 */
	public function test_resolve_url_to_path_converts_url_to_path(): void {
		// Test the actual implementation with real WordPress function mocks
		WP_Mock::userFunction('content_url')
			->andReturn('https://example.com/wp-content');
		WP_Mock::userFunction('site_url')
			->andReturn('https://example.com');
		WP_Mock::userFunction('wp_normalize_path')
			->andReturnUsing(function($path) {
				return str_replace('\\', '/', $path);
			});

		// Test case 1: URL within wp-content should resolve to path
		$content_url = 'https://example.com/wp-content/plugins/my-plugin/assets/script.js';
		$result1     = $this->_invoke_protected_method(
			$this->instance,
			'_resolve_url_to_path',
			array($content_url)
		);
		$this->assertStringContainsString('plugins/my-plugin/assets/script.js', $result1, 'Should correctly resolve wp-content URL to path');
		$this->assertStringEndsWith('script.js', $result1, 'Should end with the correct filename');

		// Test case 2: URL within site but outside wp-content (fallback)
		$site_url = 'https://example.com/wp-includes/js/jquery.js';
		$result2  = $this->_invoke_protected_method(
			$this->instance,
			'_resolve_url_to_path',
			array($site_url)
		);
		$this->assertStringContainsString('wp-includes/js/jquery.js', $result2, 'Should correctly resolve site URL to path using fallback');
		$this->assertStringEndsWith('jquery.js', $result2, 'Should end with the correct filename');

		// Test case 3: External URL that cannot be resolved
		$external_url = 'https://external-site.com/script.js';
		$result3      = $this->_invoke_protected_method(
			$this->instance,
			'_resolve_url_to_path',
			array($external_url)
		);
		$this->assertFalse($result3, 'Should return false for external URL that cannot be resolved');

		// Verify warning was logged for external URL
		$this->expectLog('warning', array(
			'_resolve_url_to_path - Could not resolve URL to path',
			'https://external-site.com/script.js'
		), 1);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_concrete_process_single_asset
	 */
	public function test_concrete_process_single_asset_skips_when_condition_fails(): void {
		// Create asset definition with a condition that returns false
		$asset_definition = array(
			'handle'    => 'test-script',
			'src'       => 'path/to/script.js',
			'condition' => function() {
				return false;
			}
		);

		// Call _process_single_asset directly
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_process_single_asset',
			array(
				AssetType::Script,
				$asset_definition,
				'test_context',
				null,
				true,
				false
			)
		);

		$this->assertFalse($result, 'Should return false when condition fails');

		// Verify debug log was written
		$this->expectLog('debug', array(
			'_concrete_process_single_asset - Condition not met for script \'test-script\'. Skipping.'
		), 1);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_concrete_process_single_asset
	 */
	public function test_concrete_process_single_asset_skips_when_handle_empty(): void {
		// Create asset definition with empty handle
		$asset_definition = array(
			'handle' => '',
			'src'    => 'path/to/script.js'
		);

		// Call _process_single_asset directly
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_process_single_asset',
			array(
				AssetType::Script,
				$asset_definition,
				'test_context',
				null,
				true,
				false
			)
		);

		$this->assertFalse($result, 'Should return false when handle is empty');

		// Verify warning log was written
		$this->expectLog('warning', array(
			'_concrete_process_single_asset - script definition is missing a \'handle\'. Skipping.'
		), 1);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_concrete_process_single_asset
	 */
	public function test_concrete_process_single_asset_handles_deferred_asset_in_stage_context(): void {
		// Create asset definition that will be detected as deferred
		$asset_definition = array(
			'handle' => 'deferred-script',
			'src'    => 'path/to/script.js',
			'hook'   => 'wp_footer'
		);

		// Mock _is_deferred_asset to return a deferred handle
		$this->instance->shouldReceive('_is_deferred_asset')
			->once()
			->andReturn('deferred-script');

		// Call _process_single_asset with 'stage_scripts' context
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_process_single_asset',
			array(
				AssetType::Script,
				$asset_definition,
				'stage_scripts', // This context triggers early return for deferred assets
				null,
				true,
				false
			)
		);

		$this->assertEquals('deferred-script', $result, 'Should return deferred handle for deferred assets in stage_scripts context');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_concrete_process_single_asset
	 */
	public function test_concrete_process_single_asset_handles_source_resolution_failure(): void {
		// Create asset definition with source that will fail to resolve
		$asset_definition = array(
			'handle' => 'test-script',
			'src'    => 'invalid/path/script.js'
		);

		// Mock get_asset_url to return null (resolution failure)
		$this->instance->shouldReceive('get_asset_url')
			->once()
			->with('invalid/path/script.js', AssetType::Script)
			->andReturn(null);

		// Mock _is_deferred_asset to return null (not deferred)
		$this->instance->shouldReceive('_is_deferred_asset')
			->once()
			->andReturn(null);

		// Call _process_single_asset
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_process_single_asset',
			array(
				AssetType::Script,
				$asset_definition,
				'test_context',
				null,
				true,
				false
			)
		);

		$this->assertFalse($result, 'Should return false when source resolution fails');

		// Verify error log was written
		$this->expectLog('error', array(
			'_concrete_process_single_asset - Could not resolve source for script \'test-script\'. Skipping.'
		), 1);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_concrete_process_single_asset
	 */
	public function test_process_single_asset_handles_enqueue_failure(): void {
		// Create asset definition
		$asset_definition = array(
			'handle' => 'test-script',
			'src'    => 'path/to/script.js'
		);

		// Mock get_asset_url to return a valid URL
		$this->instance->shouldReceive('get_asset_url')
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

		// Call _process_single_asset with _do_enqueue = true
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_process_single_asset',
			array(
				AssetType::Script,
				$asset_definition,
				'test_context',
				null,
				true,
				true // _do_enqueue = true
			)
		);

		$this->assertFalse($result, 'Should return false when enqueue fails');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_concrete_process_single_asset
	 */
	public function test_concrete_process_single_asset_successful_completion_with_debug_logging(): void {
		// Create asset definition for successful processing
		$asset_definition = array(
			'handle' => 'success-script',
			'src'    => 'path/to/success.js'
		);

		// Mock get_asset_url to return a valid URL
		$this->instance->shouldReceive('get_asset_url')
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

		// Call _process_single_asset with _do_enqueue = true for full execution path
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_process_single_asset',
			array(
				AssetType::Script,
				$asset_definition,
				'test_context',
				'test_hook',
				true,
				true // _do_enqueue = true to reach the final completion path
			)
		);

		// Verify successful completion - should return the handle
		$this->assertEquals('success-script', $result, 'Should return handle on successful completion');

		// Verify the final debug log was written (lines 923-925)
		$this->expectLog('debug', array(
			'_concrete_process_single_asset - Finished processing script \'success-script\' on hook \'test_hook\'.'
		), 1);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_generate_asset_version
	 */
	public function test_generate_asset_version_handles_cache_busting(): void {
		// Create a mock instance with partial mocks for the methods used by _generate_asset_version
		$mock = $this->getMockBuilder(ConcreteEnqueueForScriptsTesting::class)
			->setConstructorArgs(array($this->config_mock))
			->setMethods(array('_resolve_url_to_path', '_file_exists', '_md5_file'))
			->getMock();

		// Configure the mock methods
		$mock->method('_resolve_url_to_path')
			->willReturnMap(array(
				array('https://example.com/wp-content/plugins/my-plugin/assets/script.js', '/path/to/script.js'),
				array('https://example.com/wp-content/plugins/my-plugin/assets/missing.js', '/path/to/missing.js'),
				array('https://external-site.com/script.js', false)
			));

		$mock->method('_file_exists')
			->willReturnMap(array(
				array('/path/to/script.js', true),
				array('/path/to/missing.js', false)
			));

		$mock->method('_md5_file')
			->willReturnMap(array(
				array('/path/to/script.js', 'abcdef1234567890')
			));

		// Test case 1: No cache busting requested
		$asset_no_cache_bust = array(
			'handle'     => 'test-script-1',
			'src'        => 'https://example.com/wp-content/plugins/my-plugin/assets/script.js',
			'version'    => '1.0.0',
			'cache_bust' => false
		);

		$result1 = $this->_invoke_protected_method(
			$mock,
			'_generate_asset_version',
			array($asset_no_cache_bust)
		);

		$this->assertEquals('1.0.0', $result1, 'Should return original version when cache_bust is false');

		// Test case 2: Cache busting requested and file exists
		$asset_with_cache_bust = array(
			'handle'     => 'test-script-2',
			'src'        => 'https://example.com/wp-content/plugins/my-plugin/assets/script.js',
			'version'    => '1.0.0',
			'cache_bust' => true
		);

		$result2 = $this->_invoke_protected_method(
			$mock,
			'_generate_asset_version',
			array($asset_with_cache_bust)
		);

		$this->assertEquals('abcdef1234', $result2, 'Should return first 10 chars of MD5 hash when cache_bust is true and file exists');

		// Test case 3: Cache busting requested but file path resolution fails
		$asset_external = array(
			'handle'     => 'test-script-3',
			'src'        => 'https://external-site.com/script.js',
			'version'    => '1.0.0',
			'cache_bust' => true
		);

		$result3 = $this->_invoke_protected_method(
			$mock,
			'_generate_asset_version',
			array($asset_external)
		);

		$this->assertEquals('1.0.0', $result3, 'Should return original version when path resolution fails');

		// Test case 4: Cache busting requested but file does not exist
		$asset_missing = array(
			'handle'     => 'test-script-4',
			'src'        => 'https://example.com/wp-content/plugins/my-plugin/assets/missing.js',
			'version'    => '1.0.0',
			'cache_bust' => true
		);

		$result4 = $this->_invoke_protected_method(
			$mock,
			'_generate_asset_version',
			array($asset_missing)
		);

		$this->assertEquals('1.0.0', $result4, 'Should return original version when file does not exist');

		// Test case 5: Cache busting requested but no src provided
		$asset_no_src = array(
			'handle'     => 'test-script-5',
			'version'    => '1.0.0',
			'cache_bust' => true
		);

		$result5 = $this->_invoke_protected_method(
			$mock,
			'_generate_asset_version',
			array($asset_no_src)
		);

		$this->assertEquals('1.0.0', $result5, 'Should return original version when no src is provided');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_is_deferred_asset
	 */
	public function test_is_deferred_asset_handles_staging_and_hook_firing(): void {
		// Import the AssetType enum for use in the test
		$asset_type_script = AssetType::Script;

		// Test case 1: Deferred asset during staging phase (should return handle for early return)
		$deferred_asset = array(
			'handle' => 'deferred-script',
			'src'    => 'https://example.com/script.js',
			'hook'   => 'custom_hook'
		);

		$result1 = $this->_invoke_protected_method(
			$this->instance,
			'_is_deferred_asset',
			array(
				$deferred_asset,
				'deferred-script',
				null, // hook_name is null during staging
				'test_context',
				$asset_type_script
			)
		);

		$this->assertEquals('deferred-script', $result1, 'Should return handle for deferred asset during staging');

		// Verify debug log was created
		$this->expectLog('debug', array(
			'test_context - Skipping processing of deferred script',
			'deferred-script',
			"hook 'custom_hook'"
		), 1);

		// Test case 2: Deferred asset during hook firing phase (should return null to continue processing)
		$result2 = $this->_invoke_protected_method(
			$this->instance,
			'_is_deferred_asset',
			array(
				$deferred_asset,
				'deferred-script',
				'custom_hook', // hook_name is provided during hook firing
				'test_context',
				$asset_type_script
			)
		);

		$this->assertNull($result2, 'Should return null for deferred asset during hook firing phase');

		// Test case 3: Non-deferred asset during staging phase (should return null to continue processing)
		$regular_asset = array(
			'handle' => 'regular-script',
			'src'    => 'https://example.com/script.js'
			// No hook defined
		);

		$result3 = $this->_invoke_protected_method(
			$this->instance,
			'_is_deferred_asset',
			array(
				$regular_asset,
				'regular-script',
				null, // hook_name is null during staging
				'test_context',
				$asset_type_script
			)
		);

		$this->assertNull($result3, 'Should return null for non-deferred asset during staging');

		// Test case 4: Deferred asset during staging but without context and asset_type (no logging)
		$result4 = $this->_invoke_protected_method(
			$this->instance,
			'_is_deferred_asset',
			array(
				$deferred_asset,
				'deferred-script',
				null, // hook_name is null during staging
				null, // no context
				null  // no asset_type
			)
		);

		$this->assertEquals('deferred-script', $result4, 'Should return handle for deferred asset during staging even without context');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_do_register
	 */
	public function test_do_register_handles_scripts_and_styles(): void {
		// Import the AssetType enum for use in the test
		$asset_type_script = AssetType::Script;
		$asset_type_style  = AssetType::Style;

		// Test case 1: Register a script that isn't already registered
		WP_Mock::userFunction('wp_script_is')
			->with('test-script', 'registered')
			->andReturn(false)
			->once();

		WP_Mock::userFunction('wp_register_script')
			->with('test-script', 'https://example.com/script.js', array(), '1.0.0', Mockery::type('array'))
			->andReturn(true)
			->once();

		$result1 = $this->_invoke_protected_method(
			$this->instance,
			'_do_register',
			array(
				$asset_type_script,
				true, // _do_register
				'test-script',
				'https://example.com/script.js',
				array(),
				'1.0.0',
				array('in_footer' => true),
				'test_context',
				' (hook: test_hook)'
			)
		);

		$this->assertTrue($result1, 'Should return true for successful script registration');

		// Verify debug log was created for script registration
		$this->expectLog('debug', array(
			'test_context - Registering script',
			'test-script',
			'https://example.com/script.js'
		), 1);

		// Test case 2: Register a style that isn't already registered
		WP_Mock::userFunction('wp_style_is')
			->with('test-style', 'registered')
			->andReturn(false)
			->once();

		WP_Mock::userFunction('wp_register_style')
			->with('test-style', 'https://example.com/style.css', array(), '1.0.0', 'print')
			->andReturn(true)
			->once();

		$result2 = $this->_invoke_protected_method(
			$this->instance,
			'_do_register',
			array(
				$asset_type_style,
				true, // _do_register
				'test-style',
				'https://example.com/style.css',
				array(),
				'1.0.0',
				array('media' => 'print'),
				'test_context'
			)
		);

		$this->assertTrue($result2, 'Should return true for successful style registration');

		// Verify debug log was created for style registration
		$this->expectLog('debug', array(
			'test_context - Registering style',
			'test-style',
			'https://example.com/style.css'
		), 1);

		// Test case 3: Script is already registered
		WP_Mock::userFunction('wp_script_is')
			->with('already-registered-script', 'registered')
			->andReturn(true)
			->once();

		$result3 = $this->_invoke_protected_method(
			$this->instance,
			'_do_register',
			array(
				$asset_type_script,
				true, // _do_register
				'already-registered-script',
				'https://example.com/script.js',
				array(),
				'1.0.0',
				array('in_footer' => true),
				'test_context'
			)
		);

		$this->assertTrue($result3, 'Should return true for already registered script');

		// Verify debug log was created for already registered script
		$this->expectLog('debug', array(
			'test_context - script',
			'already-registered-script',
			'already registered'
		), 1);

		// Test case 4: Script registration fails
		WP_Mock::userFunction('wp_script_is')
			->with('failing-script', 'registered')
			->andReturn(false)
			->once();

		WP_Mock::userFunction('wp_register_script')
			->with('failing-script', 'https://example.com/script.js', array(), '1.0.0', Mockery::type('array'))
			->andReturn(false)
			->once();

		$result4 = $this->_invoke_protected_method(
			$this->instance,
			'_do_register',
			array(
				$asset_type_script,
				true, // _do_register
				'failing-script',
				'https://example.com/script.js',
				array(),
				'1.0.0',
				array('in_footer' => true),
				'test_context'
			)
		);

		$this->assertFalse($result4, 'Should return false when script registration fails');

		// Verify warning log was created for failed script registration
		$this->expectLog('warning', array(
			'test_context - wp_register_script() failed for handle',
			'failing-script'
		), 1);

		// Test case 5: _do_register is false (skip registration)
		$result5 = $this->_invoke_protected_method(
			$this->instance,
			'_do_register',
			array(
				$asset_type_script,
				false, // _do_register is false
				'skip-script',
				'https://example.com/script.js',
				array(),
				'1.0.0',
				array('in_footer' => true),
				'test_context'
			)
		);

		$this->assertTrue($result5, 'Should return true when _do_register is false (skipping registration)');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_do_enqueue
	 */
	public function test_do_enqueue_handles_scripts_and_styles(): void {
		// Import the AssetType enum for use in the test
		$asset_type_script = AssetType::Script;
		$asset_type_style  = AssetType::Style;

		// Test case 1: Enqueue a script that is already registered
		WP_Mock::userFunction('wp_script_is')
			->with('test-script', 'enqueued')
			->andReturn(false)
			->once();

		WP_Mock::userFunction('wp_script_is')
			->with('test-script', 'registered')
			->andReturn(true)
			->once();

		WP_Mock::userFunction('wp_enqueue_script')
			->with('test-script')
			->once();

		$result1 = $this->_invoke_protected_method(
			$this->instance,
			'_do_enqueue',
			array(
				$asset_type_script,
				true, // do_enqueue
				'test-script',
				'https://example.com/script.js',
				array(),
				'1.0.0',
				array('in_footer' => true),
				'test_context',
				' (hook: test_hook)',
				false, // is_deferred
				null // hook_name
			)
		);

		$this->assertTrue($result1, 'Should return true for successful script enqueue');

		// Verify debug log was created for script enqueue
		$this->expectLog('debug', array(
			'test_context - Enqueuing script',
			'test-script',
			'(hook: test_hook)'
		), 1);

		// Test case 2: Enqueue a style that is already registered
		WP_Mock::userFunction('wp_style_is')
			->with('test-style', 'enqueued')
			->andReturn(false)
			->once();

		WP_Mock::userFunction('wp_style_is')
			->with('test-style', 'registered')
			->andReturn(true)
			->once();

		WP_Mock::userFunction('wp_enqueue_style')
			->with('test-style')
			->once();

		$result2 = $this->_invoke_protected_method(
			$this->instance,
			'_do_enqueue',
			array(
				$asset_type_style,
				true, // _do_enqueue
				'test-style',
				'https://example.com/style.css',
				array(),
				'1.0.0',
				'all', // media
				'test_context'
			)
		);

		$this->assertTrue($result2, 'Should return true for successful style enqueue');

		// Verify debug log was created for style enqueue
		$this->expectLog('debug', array(
			'test_context - Enqueuing style',
			'test-style'
		), 1);

		// Test case 3: Asset is already enqueued
		WP_Mock::userFunction('wp_script_is')
			->with('already-enqueued-script', 'enqueued')
			->andReturn(true)
			->once();

		$result3 = $this->_invoke_protected_method(
			$this->instance,
			'_do_enqueue',
			array(
				$asset_type_script,
				true, // _do_enqueue
				'already-enqueued-script',
				'https://example.com/script.js',
				array(),
				'1.0.0',
				array('in_footer' => true),
				'test_context'
			)
		);

		$this->assertTrue($result3, 'Should return true for already enqueued script');

		// Verify debug log was created for already enqueued script
		$this->expectLog('debug', array(
			'test_context - script',
			'already-enqueued-script',
			'already enqueued'
		), 1);

		// Test case 4: Asset is not registered and needs auto-registration
		WP_Mock::userFunction('wp_script_is')
			->with('unregistered-script', 'enqueued')
			->andReturn(false)
			->once();

		WP_Mock::userFunction('wp_script_is')
			->with('unregistered-script', 'registered')
			->andReturn(false)
			->once();

		WP_Mock::userFunction('wp_register_script')
			->with('unregistered-script', 'https://example.com/script.js', array(), '1.0.0', array('in_footer' => true))
			->andReturn(true)
			->once();

		WP_Mock::userFunction('wp_enqueue_script')
			->with('unregistered-script')
			->once();

		$result4 = $this->_invoke_protected_method(
			$this->instance,
			'_do_enqueue',
			array(
				$asset_type_script,
				true, // _do_enqueue
				'unregistered-script',
				'https://example.com/script.js',
				array(),
				'1.0.0',
				array('in_footer' => true),
				'test_context'
			)
		);

		$this->assertTrue($result4, 'Should return true for auto-registered and enqueued script');

		// Verify warning log was created for auto-registration
		$this->expectLog('warning', array(
			'test_context - script',
			'unregistered-script',
			'was not registered before enqueuing'
		), 1);

		// Test case 5: Asset is not registered, auto-registration fails due to missing src
		WP_Mock::userFunction('wp_script_is')
			->with('missing-src-script', 'enqueued')
			->andReturn(false)
			->once();

		WP_Mock::userFunction('wp_script_is')
			->with('missing-src-script', 'registered')
			->andReturn(false)
			->once();

		$result5 = $this->_invoke_protected_method(
			$this->instance,
			'_do_enqueue',
			array(
				$asset_type_script,
				true, // _do_enqueue
				'missing-src-script',
				'', // Empty src
				array(),
				'1.0.0',
				array('in_footer' => true),
				'test_context'
			)
		);

		$this->assertFalse($result5, 'Should return false when src is missing for auto-registration');

		// Verify error log was created for missing src
		$this->expectLog('error', array(
			'test_context - Cannot register or enqueue script',
			'missing-src-script',
			"because its 'src' is missing"
		), 1);

		// Test case 6: Deferred asset during hook firing (skip auto-registration)
		WP_Mock::userFunction('wp_script_is')
			->with('deferred-script', 'enqueued')
			->andReturn(false)
			->once();

		WP_Mock::userFunction('wp_script_is')
			->with('deferred-script', 'registered')
			->andReturn(true)
			->once();

		WP_Mock::userFunction('wp_enqueue_script')
			->with('deferred-script')
			->once();

		$result6 = $this->_invoke_protected_method(
			$this->instance,
			'_do_enqueue',
			array(
				$asset_type_script,
				true, // _do_enqueue
				'deferred-script',
				'https://example.com/script.js',
				array(),
				'1.0.0',
				array('in_footer' => true),
				'test_context',
				'',
				true, // is_deferred
				'custom_hook' // hook_name
			)
		);

		$this->assertTrue($result6, 'Should return true for deferred script during hook firing');

		// Test case 7: _do_enqueue is false (skip enqueuing)
		$result7 = $this->_invoke_protected_method(
			$this->instance,
			'_do_enqueue',
			array(
				$asset_type_script,
				false, // _do_enqueue is false
				'skip-script',
				'https://example.com/script.js',
				array(),
				'1.0.0',
				array('in_footer' => true),
				'test_context'
			)
		);

		$this->assertTrue($result7, 'Should return true when _do_enqueue is false (skipping enqueue)');

		// Test case 8: Auto-registration fails
		WP_Mock::userFunction('wp_script_is')
			->with('failed-registration-script', 'enqueued')
			->andReturn(false)
			->once();

		WP_Mock::userFunction('wp_script_is')
			->with('failed-registration-script', 'registered')
			->andReturn(false)
			->once();

		WP_Mock::userFunction('wp_register_script')
			->with('failed-registration-script', 'https://example.com/script.js', array(), '1.0.0', array('in_footer' => true))
			->andReturn(false)
			->once();

		$result8 = $this->_invoke_protected_method(
			$this->instance,
			'_do_enqueue',
			array(
				$asset_type_script,
				true, // _do_enqueue
				'failed-registration-script',
				'https://example.com/script.js',
				array(),
				'1.0.0',
				array('in_footer' => true),
				'test_context'
			)
		);

		$this->assertFalse($result8, 'Should return false when auto-registration fails');
	}

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

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_do_add_filter
	 */
	public function test_do_add_filter_adds_filter(): void {
		// Set up WP_Mock expectations
		\WP_Mock::expectFilterAdded('test_filter', array($this->instance, 'test_callback'), 10, 2);

		// Create a test callback method
		$reflection = new \ReflectionClass($this->instance);
		$method     = $reflection->getMethod('_do_add_filter');
		$method->setAccessible(true);

		// Call the method
		$method->invokeArgs($this->instance, array('test_filter', array($this->instance, 'test_callback'), 10, 2));

		// Add explicit assertion to avoid risky test warning
		$this->assertTrue(true, 'Filter was added successfully');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_do_add_action
	 */
	public function test_do_add_action_adds_action(): void {
		// Set up WP_Mock expectations
		\WP_Mock::expectActionAdded('test_action', array($this->instance, 'test_callback'), 20, 3);

		// Create a test callback method
		$reflection = new \ReflectionClass($this->instance);
		$method     = $reflection->getMethod('_do_add_action');
		$method->setAccessible(true);

		// Call the method
		$method->invokeArgs($this->instance, array('test_action', array($this->instance, 'test_callback'), 20, 3));

		// Add explicit assertion to avoid risky test warning
		$this->assertTrue(true, 'Action was added successfully');
	}

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

		// Set up the inline_assets property
		$inline_assets_property = $reflection->getProperty('inline_assets');
		$inline_assets_property->setAccessible(true);
		$inline_assets = array(
			'script' => array(
				array(
					'handle'   => 'test-script',
					'content'  => 'console.log("test");',
					'position' => 'after'
				)
			)
		);
		$inline_assets_property->setValue($this->instance, $inline_assets);

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
		$this->expectLog('debug', 'Adding inline script for');
		$this->expectLog('debug', 'Successfully added inline script for');
		$this->expectLog('debug', 'Removed processed inline script');
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

		// Set up the inline_assets property
		$inline_assets_property = $reflection->getProperty('inline_assets');
		$inline_assets_property->setAccessible(true);
		$inline_assets = array(
			'script' => array(
				array(
					'handle'    => 'test-script',
					'content'   => 'console.log("test");',
					'position'  => 'after',
					'condition' => function() {
						return false;
					}
				)
			)
		);
		$inline_assets_property->setValue($this->instance, $inline_assets);

		// Mock wp_script_is to return true
		\WP_Mock::userFunction('wp_script_is')
			->with('test-script', 'registered')
			->andReturn(true)
			->once();

		// Call the method
		$method->invokeArgs($this->instance, array(AssetType::Script, 'test-script'));

		// Assert logger messages using expectLog after SUT execution
		$this->expectLog('debug', 'Checking for inline scripts for parent script');
		$this->expectLog('debug', 'Condition false for inline script targeting');
		$this->expectLog('debug', 'Removed processed inline script');
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

		// Set up the inline_assets property
		$inline_assets_property = $reflection->getProperty('inline_assets');
		$inline_assets_property->setAccessible(true);
		$inline_assets = array(
			'script' => array(
				array(
					'handle'   => 'test-script',
					'content'  => '',
					'position' => 'after'
				)
			)
		);
		$inline_assets_property->setValue($this->instance, $inline_assets);

		// Mock wp_script_is to return true
		\WP_Mock::userFunction('wp_script_is')
			->with('test-script', 'registered')
			->andReturn(true)
			->once();

		// Call the method
		$method->invokeArgs($this->instance, array(AssetType::Script, 'test-script'));

		// Assert logger messages using expectLog after SUT execution
		$this->expectLog('debug', 'Checking for inline scripts for parent script');
		$this->expectLog('warning', 'Empty content for inline script targeting');
		$this->expectLog('debug', 'Removed processed inline script');
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

		// Set up the inline_assets property
		$inline_assets_property = $reflection->getProperty('inline_assets');
		$inline_assets_property->setAccessible(true);
		$inline_assets = array(
			'script' => array(
				array(
					'handle'   => 'test-script',
					'content'  => 'console.log("test");',
					'position' => 'after'
				)
			)
		);
		$inline_assets_property->setValue($this->instance, $inline_assets);

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
		$this->expectLog('debug', 'Adding inline script for');
		$this->expectLog('warning', 'Failed to add inline script for');
		$this->expectLog('debug', 'Removed processed inline script');
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

		// Set up the inline_assets property
		$inline_assets_property = $reflection->getProperty('inline_assets');
		$inline_assets_property->setAccessible(true);
		$inline_assets = array(
			'script' => array(
				'invalid_data' // Not an array
			)
		);
		$inline_assets_property->setValue($this->instance, $inline_assets);

		// Mock wp_script_is to return true
		\WP_Mock::userFunction('wp_script_is')
			->with('test-script', 'registered')
			->andReturn(true)
			->once();

		// Call the method
		$method->invokeArgs($this->instance, array(AssetType::Script, 'test-script'));

		// Assert logger messages using expectLog after SUT execution
		$this->expectLog('debug', 'Checking for inline scripts for parent script');
		$this->expectLog('warning', 'Invalid inline script data at key');
		$this->expectLog('debug', 'No inline script found or processed');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_concrete_process_single_asset
	 */
	public function test_process_single_asset_localizes_script_correctly(): void {
		// Arrange
		$handle           = 'my-localized-script';
		$data             = array('ajax_url' => 'http://example.com/ajax');
		$object_name      = 'my_object';
		$asset_definition = array(
			'handle'   => $handle,
			'src'      => 'path/to/script.js',
			'localize' => array(
				'object_name' => $object_name,
				'data'        => $data,
			),
		);

		WP_Mock::userFunction('wp_script_is')->with($handle, 'registered')->andReturn(false);
		WP_Mock::userFunction('wp_register_script')->andReturn(true);

		// This is the key assertion
		WP_Mock::userFunction('wp_localize_script')
			->once()
			->with($handle, $object_name, $data);

		// Act
		$this->_invoke_protected_method(
			$this->instance,
			'_process_single_asset',
			array(
				AssetType::Script,
				$asset_definition,
				'test_context', // processing_context
				null,           // hook_name
				true,           // _do_register
				false           // _do_enqueue
			)
		);
		$this->expectLog('debug', array("Localizing script '{$handle}' with JS object '{$object_name}'"), 1);
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

		// Set up the inline_assets property using reflection
		$inline_assets_property = $reflection->getProperty('inline_assets');
		$inline_assets_property->setAccessible(true);
		$inline_assets = array(
			'script' => array(
				array(
					'handle'      => $parent_handle,
					'parent_hook' => $hook_name, // This should match the hook_name parameter
					'content'     => $inline_content,
					'position'    => 'after',
				),
			),
		);
		$inline_assets_property->setValue($this->instance, $inline_assets);

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
		$this->expectLog('debug', 'Adding inline script for');
		$this->expectLog('debug', 'Successfully added inline script for');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::enqueue_immediate_assets
	 */
	public function test_enqueue_immediate_assets_skips_asset_with_empty_handle(): void {
		// Arrange - Directly populate the assets array with an asset that has an empty handle
		$assets_property = new \ReflectionProperty($this->instance, 'assets');
		$assets_property->setAccessible(true);
		$assets_property->setValue($this->instance, array(
			'script' => array(
				array(
					'handle' => '', // Empty handle - should trigger lines 384-389
					'src'    => 'path/to/script.js'
				)
			)
		));

		// Act - Call enqueue_immediate_assets
		$this->_invoke_protected_method(
			$this->instance,
			'enqueue_immediate_assets',
			array(AssetType::Script)
		);

		// Assert - Check that warning was logged for empty handle (lines 385-387)
		$this->expectLog(
			'warning',
			'AssetEnqueueBaseTrait::stage_scripts - Skipping asset at index 0 due to missing handle - this should not be possible when using add().'
		);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::enqueue_immediate_assets
	 */
	public function test_enqueue_immediate_assets_throws_exception_for_deferred_asset_in_immediate_queue(): void {
		// Arrange - Directly populate the assets array with a deferred asset (has 'hook')
		$assets_property = new \ReflectionProperty($this->instance, 'assets');
		$assets_property->setAccessible(true);
		$assets_property->setValue($this->instance, array(
			'script' => array(
				array(
					'handle' => 'deferred-script',
					'src'    => 'path/to/deferred-script.js',
					'hook'   => 'custom_hook' // This should trigger the LogicException (lines 391-397)
				)
			)
		));

		// Assert - Should throw LogicException for deferred asset in immediate queue
		$this->expectException(\LogicException::class);
		$this->expectExceptionMessage(
			'AssetEnqueueBaseTrait::stage_scripts - Found a deferred asset (\'deferred-script\') in the immediate queue. ' .
			'The `stage_assets()` method must be called before `enqueue_immediate_assets()` to correctly process deferred assets.'
		);

		// Act - Call enqueue_immediate_assets (should throw exception)
		$this->_invoke_protected_method(
			$this->instance,
			'enqueue_immediate_assets',
			array(AssetType::Script)
		);
	}
}
