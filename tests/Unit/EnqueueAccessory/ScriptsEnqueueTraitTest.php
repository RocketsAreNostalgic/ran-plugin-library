<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\EnqueueAccessory;

use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\EnqueueAccessory\AssetType;
use Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseAbstract;
use Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait;
use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Util\CollectingLogger;
use InvalidArgumentException;
use WP_Mock;
use Mockery;

/**
 * Concrete implementation of ScriptsEnqueueTrait for testing asset-related methods.
 */
class ConcreteEnqueueForScriptsTesting extends AssetEnqueueBaseAbstract {
	use ScriptsEnqueueTrait;

	protected array $registered_hooks = array();

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
		add_action($hook, $callback, $priority, $accepted_args);
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

		$this->config_mock = Mockery::mock(ConfigInterface::class);
		$this->config_mock->shouldReceive('get_is_dev_callback')->andReturn(null)->byDefault();
		$this->config_mock->shouldReceive('is_dev_environment')->andReturn(false)->byDefault();

		$this->logger_mock = new CollectingLogger();
		$this->config_mock->shouldReceive('get_logger')->andReturn($this->logger_mock)->byDefault();

		// Create a partial mock for the class under test.
		// This allows us to mock protected methods like _file_exists and _md5_file.
		$this->instance = Mockery::mock(ConcreteEnqueueForScriptsTesting::class, array($this->config_mock))
		    ->makePartial()
		    ->shouldAllowMockingProtectedMethods();

		// Mock the get_asset_url method to return the source by default
		// This handles the null URL check we added in the ScriptsEnqueueTrait
		$this->instance->shouldReceive('get_asset_url')
		    ->withAnyArgs()
		    ->andReturnUsing(function($src, $type) {
		    	return $src;
		    })
		    ->byDefault();

		$this->instance->shouldReceive('stage_scripts')->passthru();

		// Ensure the mock instance uses our collecting logger.
		$this->instance->shouldReceive('get_logger')->andReturn($this->logger_mock)->byDefault();
		$this->instance->shouldReceive('get_config')->andReturn($this->config_mock)->byDefault();

		// Default WP_Mock function mocks for asset functions
		WP_Mock::userFunction('wp_register_script')->withAnyArgs()->andReturn(true)->byDefault();
		WP_Mock::userFunction('wp_enqueue_script')->withAnyArgs()->andReturn(true)->byDefault();
		WP_Mock::userFunction('wp_add_inline_script')->withAnyArgs()->andReturn(true)->byDefault();
		WP_Mock::userFunction('wp_script_is')->withAnyArgs()->andReturn(false)->byDefault();
		WP_Mock::userFunction('did_action')->withAnyArgs()->andReturn(0)->byDefault(); // 0 means false
		WP_Mock::userFunction('current_action')->withAnyArgs()->andReturn(null)->byDefault();
		WP_Mock::userFunction('is_admin')->andReturn(false)->byDefault(); // Default to not admin context
		WP_Mock::userFunction('wp_doing_ajax')->andReturn(false)->byDefault();
		WP_Mock::userFunction('_doing_it_wrong')->withAnyArgs()->andReturnNull()->byDefault();

		// Tests that need `wp_json_encode` should mock it directly.
		WP_Mock::userFunction('wp_json_encode', array(
			'return' => static function($data) {
				return json_encode($data);
			},
		))->byDefault();

		// Tests that need `esc_attr` should mock it directly.
		WP_Mock::userFunction('esc_attr', array(
			'return' => static function($text) {
				return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
			},
		))->byDefault();

		// Tests that need `has_action` should mock it directly.
		WP_Mock::userFunction('has_action')
		    ->with(Mockery::any(), Mockery::any())
		    ->andReturnUsing(function ($hook, $callback) {
		    	return false;
		    })
		    ->byDefault();

		// Tests that need `esc_html` should mock it directly.
		WP_Mock::userFunction('esc_html', array(
			'return' => static function($text) {
				return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
			},
		))->byDefault();
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
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::stage_assets
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
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_process_single_script_asset
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::add_assets
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::stage_assets
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_process_single_asset
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
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_process_single_script_asset
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::add_assets
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::stage_assets
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
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_process_single_script_asset
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::add_assets
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::stage_assets
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_process_single_asset
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
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::stage_assets
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
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_process_single_asset
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
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::stage_assets
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
	 * @covers       \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_modify_script_tag_attributes
	 */
	public function test_modify_script_tag_attributes_adds_attributes_correctly(string $handle, array $attributes, string $original_tag, string $expected_tag, ?string $mismatch_handle = null): void {
		// Arrange
		// The test class uses a method that calls the protected method from the trait.
		// Act
		$filter_handle = $mismatch_handle ?? $handle;
		$modified_tag  = $this->_invoke_protected_method(
			$this->instance,
			'_modify_script_tag_attributes',
			array(AssetType::Script, $original_tag, $handle, $filter_handle, $attributes)
		);

		// Assert
		$this->assertEquals($expected_tag, $modified_tag);
	}



	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_modify_script_tag_attributes
	 */
	public function test_modify_script_tag_attributes_handles_module_type_correctly(): void {
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
			'_modify_script_tag_attributes',
			array(AssetType::Script, $original_tag, $handle, $handle, $attributes1)
		);

		$modified_tag2 = $this->_invoke_protected_method(
			$this->instance,
			'_modify_script_tag_attributes',
			array(AssetType::Script, $original_tag2, $handle, $handle, $attributes2)
		);

		$modified_tag3 = $this->_invoke_protected_method(
			$this->instance,
			'_modify_script_tag_attributes',
			array(AssetType::Script, $original_tag3, 'custom-script', 'custom-script', $attributes3)
		);

		// Assert
		$this->assertEquals($expected_tag1, $modified_tag1, 'Module type should be added with other attributes');
		$this->assertEquals($expected_tag2, $modified_tag2, 'Module type should replace existing type attribute');
		$this->assertEquals($expected_tag3, $modified_tag3, 'Non-module type should also be positioned first');
	}

	/**
	 * Data provider for `test_modify_script_tag_attributes_adds_attributes_correctly`.
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
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_process_single_script_asset
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::add_assets
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::stage_assets
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_process_single_asset
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
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_process_single_script_asset
	 */
	public function test_process_single_script_asset_with_string_src_remains_unchanged(): void {
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
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_process_single_script_asset
	 */
	public function test_process_single_script_asset_resolves_src_based_on_environment(
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
	 * Data provider for `test_process_single_script_asset_resolves_src_based_on_environment`.
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
					)
				)
			)
		)
	));

	// Mock current_action to return our hook name
	WP_Mock::userFunction('current_action')
		->andReturn($hook_name);

	// Mock wp_script_is to return true for our external handle
	WP_Mock::userFunction('wp_script_is')
		->with($external_handle, 'registered')
		->andReturn(true);
		// Mock wp_script_is to return true for our external handle
		WP_Mock::userFunction('wp_script_is')
			->with($external_handle, 'registered')
			->andReturn(true);

		// Expect wp_add_inline_script to be called with our parameters
		WP_Mock::userFunction('wp_add_inline_script')
			->once()
			->with($external_handle, $inline_content, $position)
			->andReturn(true);

		// Call the method under test
		$this->_invoke_protected_method($this->instance, '_enqueue_external_inline_assets', [AssetType::Script]);

		// Verify that the appropriate log messages were generated
		// Check for the hook firing message
		$this->expectLog('debug', ["::enqueue_external_inline_scripts - Fired on hook '{$hook_name}'"], 1);
		// Check for the debug message about processing for the hook
		$this->expectLog('debug', ["::enqueue_external_inline_scripts - Finished processing for hook '{$hook_name}'"], 1);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_process_single_script_asset
	 */
	public function test_process_single_script_asset_with_incorrect_asset_type(): void {
		// Create a test asset definition
		$asset_definition = array(
			'handle' => 'test-script',
			'src'    => 'path/to/script.js',
		);

		// Call the method with incorrect asset type (Style instead of Script)
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_process_single_script_asset',
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
		$this->expectLog('warning', array('Incorrect asset type provided to _process_single_script_asset', "Expected 'script', got 'style'"), 1);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_process_single_script_asset
	 */
	public function test_process_single_script_asset_with_async_strategy(): void {
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
			'_process_single_script_asset',
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
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_process_single_script_asset
	 */
	public function test_process_single_script_asset_with_defer_strategy(): void {
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
			'_process_single_script_asset',
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
	 */
	public function test_enqueue_external_inline_scripts_calls_base_method(): void {
		// Create a spy for _enqueue_external_inline_assets
		$this->instance->shouldReceive('_enqueue_external_inline_assets')
			->once()
			->with(AssetType::Script)
			->andReturn(null); // Ensure the method returns as expected

		// Call the method under test
		$this->instance->_enqueue_external_inline_scripts();
		
		// Add an explicit assertion to avoid the risky test warning
		$this->assertTrue(true, 'Method called without errors');
		
		// The real assertion is in the Mockery expectation above, which will
		// fail if _enqueue_external_inline_assets is not called exactly once with AssetType::Script
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_modify_script_tag_attributes
	 */
	public function test_modify_script_tag_attributes_with_incorrect_asset_type(): void {
		// Arrange
		$tag             = '<link rel="stylesheet" href="style.css" />';
		$tag_handle      = 'test-style';
		$handle_to_match = 'test-style';
		$attributes      = array('media' => 'print');
		
		// Act - call with Style asset type instead of Script
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_modify_script_tag_attributes',
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
		$this->expectLog('warning', array('Incorrect asset type provided to _modify_script_tag_attributes. Expected \'script\', got \'style\'.'), 1);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_process_single_script_asset
	 */
	public function test_process_single_script_asset_localizes_script_correctly(): void {
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
			'_process_single_script_asset',
			array(
				AssetType::Script,
				$asset_definition,
				'test_context', // processing_context
				null,           // hook_name
				true,           // do_register
				false           // do_enqueue
			)
		);
		$this->expectLog('debug', array("Localizing script '{$handle}' with JS object '{$object_name}'"), 1);
	}
}
