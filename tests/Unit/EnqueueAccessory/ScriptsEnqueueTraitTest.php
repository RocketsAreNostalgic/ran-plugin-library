<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\EnqueueAccessory;

use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\EnqueueAccessory\AssetType;
use Ran\PluginLib\EnqueueAccessory\EnqueueAssetBaseAbstract;
use Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait;
use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Util\CollectingLogger;
use InvalidArgumentException;
use WP_Mock;
use Mockery;

/**
 * Concrete implementation of ScriptsEnqueueTrait for testing asset-related methods.
 */
class ConcreteEnqueueForScriptsTesting extends EnqueueAssetBaseAbstract {
	use ScriptsEnqueueTrait;
	public function __construct(ConfigInterface $config) {
		parent::__construct($config);
	}

	public function load(): void {
		// Minimal implementation for testing purposes.
	}

	public function get_logger(): Logger {
		return $this->config->get_logger();
	}

	// Mocked implementation for trait's dependency.
	protected function _add_action(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): void {
	}

	// Mocked implementation for trait's dependency.
	public function enqueue_external_inline_scripts(): void {
	}
}

/**
 * Class ScriptsEnqueueTraitTest
 *
 * @package Ran\PluginLib\Tests\Unit\EnqueueAccessory
 *
 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait
 */
class ScriptsEnqueueTraitTest extends PluginLibTestCase {
	/**
	 * @var (ConcreteEnqueueForScriptsTesting&Mockery\MockInterface)|Mockery\LegacyMockInterface
	 */
	protected $instance;

	/**
	 * @var CollectingLogger|null
	 */
	protected ?CollectingLogger $logger_mock = null;

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		// Configure the config mock to return the logger instance used by the test suite.
		$this->config_mock->method('get_logger')->willReturn($this->logger_mock);

		// Instantiate the test class with the configured mock.
		$this->instance = new ConcreteEnqueueForScriptsTesting($this->config_mock);

		// Default WP_Mock function mocks for asset functions
		WP_Mock::userFunction('wp_register_script')->withAnyArgs()->andReturn(true)->byDefault();
		WP_Mock::userFunction('wp_enqueue_script')->withAnyArgs()->andReturnNull()->byDefault();
		WP_Mock::userFunction('wp_add_inline_script')->withAnyArgs()->andReturn(true)->byDefault();
		WP_Mock::userFunction('wp_script_is')->withAnyArgs()->andReturn(false)->byDefault();
		WP_Mock::userFunction('did_action')->withAnyArgs()->andReturn(0)->byDefault(); // 0 means false
		WP_Mock::userFunction('current_action')->withAnyArgs()->andReturn(null)->byDefault();
		WP_Mock::userFunction('is_admin')->andReturn(false)->byDefault(); // Default to not admin context
		WP_Mock::userFunction('wp_doing_ajax')->andReturn(false)->byDefault();
		WP_Mock::userFunction('_doing_it_wrong')->withAnyArgs()->andReturnNull()->byDefault();

		// Tests that need `wp_script_is` should mock it directly.
		WP_Mock::userFunction('wp_json_encode', array(
			'return' => static fn($data) => json_encode($data),
		))->byDefault();
		// Tests that need `esc_attr` should mock it directly.
		WP_Mock::userFunction('esc_attr', array(
			'return' => static fn($text) => htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'),
		))->byDefault();

		// Mock has_action to control its return value for specific tests
		WP_Mock::userFunction('has_action')
			->with(Mockery::any(), Mockery::any())
			->andReturnUsing(function ($hook, $callback) {
				// Default behavior: no action exists.
				// Tests can add more specific expectations.
				return false;
			})
			->byDefault();
	}

	/**
	 * Clean up test environment.
	 */
	public function tearDown(): void {
		parent::tearDown();
		Mockery::close();

		// Reset protected properties to ensure a clean state for each test.
		$reflection = new \ReflectionObject($this->instance);

		$props_to_reset = array('assets', 'inline_assets', 'deferred_assets');

		foreach ($props_to_reset as $prop_name) {
			if ($reflection->hasProperty($prop_name)) {
				$property = $reflection->getProperty($prop_name);
				$property->setAccessible(true);
				$property->setValue($this->instance, array());
			}
		}
	}

	// ------------------------------------------------------------------------
	// Test Methods for Script Functionalities
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::add_inline_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAssetTraitBase::add_inline_assets
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAssetTraitBase::_add_inline_asset
	 */
	public function test_add_inline_script_to_externally_registered_handle(): void {
		// Arrange: Define an inline script for an external handle like 'jquery'.
		$external_handle    = 'jquery';
		$inline_script_data = 'console.log("Hello from inline script on external handle");';

		// Mock that 'jquery' is already registered by WordPress.
		WP_Mock::userFunction('wp_script_is')->with($external_handle, 'registered')->andReturn(true);

		// Expect that an action is added to handle this external inline script later.
		WP_Mock::userFunction('has_action')
			->with('wp_enqueue_scripts', array($this->instance, 'enqueue_external_inline_scripts'))
			->andReturn(false);

		WP_Mock::expectActionAdded('wp_enqueue_scripts', array($this->instance, 'enqueue_external_inline_scripts'), 11, 1);

		// Act
		$this->instance->add_inline_scripts(array(
			array(
				'parent_handle' => $external_handle,
				'content'       => $inline_script_data,
			)
		));

		// Assert that the script was added to the external_inline queue.
		$scripts = $this->instance->get_scripts();
		$this->assertArrayHasKey($external_handle, $scripts['external_inline']['wp_enqueue_scripts']);
		$this->assertCount(1, $scripts['external_inline']['wp_enqueue_scripts']);
		$this->assertEquals($inline_script_data, $scripts['external_inline']['wp_enqueue_scripts'][$external_handle][0]['content']);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::add_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::stage_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_process_single_script_asset
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAssetTraitBase::add_assets
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAssetTraitBase::stage_assets
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
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_process_single_script_asset
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAssetTraitBase::add_assets
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAssetTraitBase::stage_assets
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
		$this->expectLog('warning', "Ignoring 'id' attribute for '{$handle}'");
		$this->expectLog('warning', "Ignoring 'type' attribute for '{$handle}'");
		$this->expectLog('warning', "Ignoring 'src' attribute for '{$handle}'");
		foreach ($this->logger_mock->get_logs() as $log) {
			if (strtolower((string) $log['level']) === 'warning') {
				$this->assertStringNotContainsString("Ignoring 'data-custom' attribute", $log['message']);
			}
		}
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::add_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAssetTraitBase::add_assets
	 */
	public function test_add_scripts_handles_empty_input_gracefully(): void {
		// Call the method under test
		$result = $this->instance->add_scripts(array());

		// Logger expectations for EnqueueAssetTraitBase::add_assets() via ScriptsEnqueueTrait.
		$this->expectLog('debug', array('add_', 'Entered with empty array'));

		// Assert that the method returns the instance for chainability
		$this->assertSame($this->instance, $result);

		// Assert that the scripts array remains empty
		$scripts = $this->instance->get_scripts();
		$this->assertEmpty($scripts['general']);
		$this->assertEmpty($scripts['deferred']);
		$this->assertEmpty($scripts['external_inline']);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::add_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAssetTraitBase::add_assets
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
		$scripts = $this->instance->get_scripts();
		$this->assertCount(1, $scripts['general']);
		$this->assertEquals('single-asset', $scripts['general'][0]['handle']);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::add_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAssetTraitBase::add_assets
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

		// Logger expectations for EnqueueAssetTraitBase::add_assets() via ScriptsEnqueueTrait.
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
		$scripts = $this->instance->get_scripts();
		$this->assertArrayHasKey('general', $scripts);
		$this->assertArrayHasKey('deferred', $scripts);
		$this->assertArrayHasKey('external_inline', $scripts);
		$this->assertEquals('my-asset-1', $scripts['general'][0]['handle']);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::add_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::stage_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::enqueue_immediate_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_process_single_script_asset
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAssetTraitBase::add_assets
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAssetTraitBase::stage_assets
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAssetTraitBase::_process_single_asset
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAssetTraitBase::enqueue_immediate_assets
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
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::add_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::stage_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::get_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_process_single_script_asset
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAssetTraitBase::add_assets
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAssetTraitBase::stage_assets
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAssetTraitBase::get_assets
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAssetTraitBase::_process_single_asset
	 */
	public function test_stage_scripts_defers_hooked_asset_correctly_with_asset_keyword(): void {
		// Arrange
		$asset_to_add = array(
			'handle' => 'my-deferred-asset',
			'src'    => 'path/to/deferred.js',
			'hook'   => 'admin_stage_assets',
		);

		WP_Mock::userFunction('has_action')->withAnyArgs()->andReturn(false);
		WP_Mock::expectActionAdded('admin_stage_assets', array($this->instance, 'enqueue_deferred_scripts'), 10, 1);

		// Act: Add the script, which should store it as deferred.
		$this->instance->add_scripts(array($asset_to_add));

		// Assert: check the log for add_scripts()
		$this->expectLog('debug', array('add_scripts', 'Adding 1 new script(s)'), 1);

		// Act: Register scripts, which should set up the action hook.
		$this->instance->stage_scripts();

		// Assert: check the log for stage_scripts()
		$this->expectLog('debug', "Deferring registration of script 'my-deferred-asset' (original index 0) to hook: admin_stage_assets", 1);

		// Assert: Check that the asset was moved to the deferred queue.
		$scripts = $this->instance->get_scripts();
		$this->assertEmpty($scripts['general']);
		$this->assertArrayHasKey('admin_stage_assets', $scripts['deferred']);
		$this->assertCount(1, $scripts['deferred']['admin_stage_assets']);
		$this->assertEquals('my-deferred-asset', $scripts['deferred']['admin_stage_assets'][0]['handle']);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::stage_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAssetTraitBase::stage_assets
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
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::add_inline_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAssetTraitBase::add_assets
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAssetTraitBase::add_inline_assets
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAssetTraitBase::_add_inline_asset
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
		$this->expectLog('debug', array('EnqueueAssetTraitBase::add_', 'Entered. Current', 'count: 0', 'Adding 1 new'), 1);

		// Add inline script
		$inline_content = 'alert("test");';

		$result = $this->instance->add_inline_scripts(array(
			'parent_handle' => $parent_handle,
			'content'       => $inline_content,
		));

		// Assert
		$this->assertSame($this->instance, $result, 'Method should be chainable.');

		// Check that the correct log message was recorded.
		$this->expectLog('debug', array('EnqueueAssetTraitBase::add_inline_', 'Entered. Current', 'count: 0', 'Adding 1 new'), 1);

		// Verify the internal state.
		$scripts = $this->instance->get_scripts();

		// Assert: Verify the inline data was attached to the parent script definition in the pre-registration queue.
		$this->assertCount(0, $scripts['external_inline'], 'external_inline should be empty.');
		$this->assertEquals($inline_content, $scripts['general'][0]['inline'][0]['content']);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::add_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::stage_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::add_inline_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAssetTraitBase::add_assets
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAssetTraitBase::stage_assets
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAssetTraitBase::add_inline_assets
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAssetTraitBase::_add_inline_asset
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
		$this->expectLog('debug', array('EnqueueAssetTraitBase::add_', 'Entered. Current', 'count: 0', 'Adding 1 new'), 1);

		// Expect that the system checks if the action is already set, and then adds it.
		WP_Mock::userFunction('has_action')->once()->with($hook, array($this->instance, 'enqueue_deferred_scripts'))->andReturn(false);
		WP_Mock::expectActionAdded($hook, array($this->instance, 'enqueue_deferred_scripts'), 10, 1);

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
		$this->expectLog('debug', array('EnqueueAssetTraitBase::add_inline_', 'Entered. Current', 'count: 0', 'Adding 1 new'), 1);

		// Verify the internal state.
		$scripts = $this->instance->get_scripts();

		// Assert: Verify the inline data was attached to the parent script definition in the pre-registration queue.
		$this->assertCount(0, $scripts['external_inline'], 'external_inline should be empty.');
		$this->assertEmpty($scripts['general'], 'The general queue should be empty after deferral.');
		$this->assertArrayHasKey($hook, $scripts['deferred']);
		$this->assertCount(1, $scripts['deferred'][$hook], 'Deferred queue for the hook should contain one asset.');
		$this->assertEquals($inline_content, $scripts['deferred'][$hook][0]['inline'][0]['content']);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::add_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::stage_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_process_single_script_asset
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAssetTraitBase::add_assets
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAssetTraitBase::stage_assets
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAssetTraitBase::_process_single_asset
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

		$scripts = $this->instance->get_scripts();
		$this->assertEmpty($scripts['general'], 'The general queue should be empty.');
		$this->assertEmpty($scripts['deferred'], 'The deferred queue should be empty.');
		$this->assertEmpty($scripts['external_inline'], 'The external_inline queue should be empty.');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::add_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::stage_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_process_single_script_asset
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAssetTraitBase::add_assets
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAssetTraitBase::stage_assets
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAssetTraitBase::_process_single_asset
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

		// Assert: check the log for add_assets()
		$this->expectLog('debug', array('add_', 'Adding 1 new'), 1);

		// Set mock expectations for stage_scripts()
		WP_Mock::userFunction('has_action')->once()->with('wp_enqueue_scripts', array($this->instance, 'enqueue_deferred_scripts'))->andReturn(false);
		WP_Mock::expectActionAdded('wp_enqueue_scripts', array($this->instance, 'enqueue_deferred_scripts'), 10, 1);

		// Act: Register the script
		$this->instance->stage_scripts();

		// Assert: check the log for stage_scripts()
		$this->expectLog('debug', "Deferring registration of script 'my-deferred-script' (original index 0) to hook: wp_enqueue_scripts", 1);

		// Assert
		$scripts = $this->instance->get_scripts();
		$this->assertArrayHasKey('wp_enqueue_scripts', $scripts['deferred']);
		$this->assertCount(1, $scripts['deferred']['wp_enqueue_scripts']);
		$this->assertEquals('my-deferred-script', $scripts['deferred']['wp_enqueue_scripts'][0]['handle']);

		// Assert that the main assets queue is empty as the asset was deferred
		$this->assertEmpty($scripts['general']);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::add_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAssetTraitBase::add_assets
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
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAssetTraitBase::add_assets
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
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::enqueue_deferred_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAssetTraitBase::_enqueue_deferred_assets
	 */
	public function test_enqueue_deferred_scripts_skips_if_hook_is_empty(): void {
		// Arrange
		$hook_name = 'empty_hook_for_test';

		// Use reflection to set the internal state, creating a deferred hook with no assets.
		$deferred_assets_prop = new \ReflectionProperty($this->instance, 'deferred_assets');
		$deferred_assets_prop->setAccessible(true);
		$deferred_assets_prop->setValue($this->instance, array('script' => array($hook_name => array())));

		// Assert: Verify the internal state has the hook.
		$scripts = $this->instance->get_scripts();
		$this->assertArrayHasKey($hook_name, $scripts['deferred'], 'The hook should be in the deferred assets.');

		// Act: Call the public method that would be triggered by the WordPress hook.
		$this->instance->enqueue_deferred_scripts($hook_name);

		// Assert: Check the log messages were triggered.
		$this->expectLog('debug', array('enqueue_deferred_', 'Entered hook: "empty_hook_for_test"'), 1);
		$this->expectLog('debug', array('enqueue_deferred_', 'Hook "empty_hook_for_test" not found in deferred', 'Exiting - nothing to process.'), 1);

		// Assert: Verify the internal state has the hook cleared.
		$scripts = $this->instance->get_scripts();
		$this->assertArrayNotHasKey($hook_name, $scripts['deferred'], 'The hook should be cleared from deferred assets.');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::stage_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAssetTraitBase::stage_assets
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

		WP_Mock::userFunction('has_action')
			->with($hook_name, array($this->instance, 'enqueue_deferred_scripts'))
			->andReturn(false); // Mock that the action hasn't been added yet

		WP_Mock::expectActionAdded($hook_name, array($this->instance, 'enqueue_deferred_scripts'), 10, 1);

		// Act
		$this->instance->stage_scripts();

		// Assert
		$this->expectLog('debug', array('stage_', 'Deferring registration of', "'my-deferred-asset' (original index 0) to hook: {$hook_name}."), 1);
		$this->expectLog('debug', array('stage_', "Added action for 'enqueue_deferred_", "on hook: {$hook_name}."), 1);

		// Assert that the asset is in the deferred queue
		$scripts = $this->instance->get_scripts();
		$this->assertArrayHasKey($hook_name, $scripts['deferred']);
		$this->assertCount(1, $scripts['deferred'][$hook_name]);
		$this->assertEquals('my-deferred-asset', $scripts['deferred'][$hook_name][0]['handle']);

		// Assert that the main assets queue is empty as the asset was deferred
		$this->assertEmpty($scripts['general']);
	}
}
