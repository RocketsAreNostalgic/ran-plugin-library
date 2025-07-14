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

		$this->config_mock = $this->get_and_register_concrete_config_instance();

		$this->logger_mock = new CollectingLogger();

		// Create a partial mock for the class under test.
		// This allows us to mock protected methods like _file_exists and _md5_file.
		$this->instance = Mockery::mock(ConcreteEnqueueForScriptsTesting::class, array($this->config_mock))
		    ->makePartial()
		    ->shouldAllowMockingProtectedMethods();

		// Ensure the mock instance uses our collecting logger.
		$this->instance->shouldReceive('get_logger')->andReturn($this->logger_mock)->byDefault();

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

		// Tests that need `wp_script_is` should mock it directly.
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

		if (!defined('ABSPATH')) {
			define('ABSPATH', '/var/www/html/');
		}
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
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_process_single_script_asset
	 */
	public function test_process_single_script_asset_localizes_script_correctly(): void {
		// Arrange
		$handle           = 'my-localized-script';
		$object_name      = 'myPluginData';
		$data             = array('ajax_url' => 'http://example.com/ajax');
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
		$this->expectLog('debug', array("Localizing script '{$handle}' with JS object '{$object_name}'"), 1, true);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_generate_asset_version
	 */
	public function test_cache_busting_generates_hash_version_when_enabled_and_file_exists(): void {
		// --- Test Setup ---
		$handle           = 'my-script';
		$src              = '/wp-content/plugins/my-plugin/js/my-script.js';
		$file_path        = ABSPATH . 'wp-content/plugins/my-plugin/js/my-script.js';
		$hash             = md5('file content');
		$expected_version = substr($hash, 0, 10);

		$asset_definition = array(
		    'handle'     => $handle,
		    'src'        => $src,
		    'version'    => '1.0.0',
		    'cache_bust' => true,
		);

		WP_Mock::userFunction('site_url')->andReturn('http://example.com');
		WP_Mock::userFunction('wp_normalize_path')->andReturnUsing(fn($p) => $p);

		$this->instance->shouldReceive('_file_exists')->once()->with($file_path)->andReturn(true);
		$this->instance->shouldReceive('_md5_file')->once()->with($file_path)->andReturn($hash);

		// --- Act ---
		$actual_version = $this->_invoke_protected_method($this->instance, '_generate_asset_version', array($asset_definition));

		// --- Assert ---
		$this->assertSame($expected_version, $actual_version);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_generate_asset_version
	 */
	public function test_cache_busting_falls_back_to_default_version_when_file_not_found(): void {
		// --- Test Setup ---
		$handle          = 'my-script';
		$src             = '/wp-content/plugins/my-plugin/js/my-script.js';
		$file_path       = ABSPATH . 'wp-content/plugins/my-plugin/js/my-script.js';
		$default_version = '1.2.3';

		$asset_definition = array(
		    'handle'     => $handle,
		    'src'        => $src,
		    'version'    => $default_version,
		    'cache_bust' => true,
		);

		WP_Mock::userFunction('site_url')->andReturn('http://example.com');
		WP_Mock::userFunction('wp_normalize_path')->andReturnUsing(fn($p) => $p);

		$this->instance->shouldReceive('_file_exists')->once()->with($file_path)->andReturn(false);
		$this->instance->shouldReceive('_md5_file')->never();

		// --- Act ---
		$actual_version = $this->_invoke_protected_method($this->instance, '_generate_asset_version', array($asset_definition));

		// --- Assert ---
		$this->assertSame($default_version, $actual_version);
		$this->assertLogContains('warning', "Cache-busting for '{$handle}' failed. File not found at resolved path: '{$file_path}'.");
	}

	// ------------------------------------------------------------------------
	// Helper Methods
	// ------------------------------------------------------------------------

	protected function assertLogContains(string $level, string $message): void {
		$logs         = $this->logger_mock->get_logs();
		$found        = false;
		$log_messages = array();

		foreach ($logs as $log) {
			if ($log['level'] === $level && strpos($log['message'], $message) !== false) {
				$found = true;
				break;
			}
			$log_messages[] = "[{$log['level']}] {$log['message']}";
		}

		$this->assertTrue(
			$found,
			sprintf(
				"Failed to find expected log message.\n- Expected level: '%s'\n- Expected message containing: '%s'\n- Actual logs:\n%s",
				$level,
				$message,
				implode("\n", $log_messages)
			)
		);
	}

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

	// ------------------------------------------------------------------------
	// Test Methods for Script Functionalities
	// ------------------------------------------------------------------------

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

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::add_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::add_assets
	 */
	public function test_add_scripts_handles_empty_input_gracefully(): void {
		// Call the method under test
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
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::add_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::stage_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::get_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_process_single_script_asset
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::add_assets
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::stage_assets
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::get_assets
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_process_single_asset
	 */
	public function test_stage_scripts_defers_hooked_asset_correctly_with_asset_keyword(): void {
		// Arrange
		$asset_to_add = array(
			'handle' => 'my-deferred-asset',
			'src'    => 'path/to/deferred.js',
			'hook'   => 'admin_stage_assets',
		);

		// Act: Add the script, which should store it as deferred.
		$this->instance->add_scripts(array($asset_to_add));

		// Assert: check the log for add_scripts()
		$this->expectLog('debug', array('add_scripts', 'Adding 1 new script(s)'), 1);

		// Act: Register scripts, which should set up the action hook.
		$this->instance->stage_scripts();

		// Assert: check the log for stage_scripts()
		$this->expectLog('debug', array('Deferring registration', 'my-deferred-asset', "to hook 'admin_stage_assets' with priority 10"), 1);

		// Assert: Check that the asset was moved to the deferred queue.
		$assets = $this->instance->get_scripts();
		$this->assertEmpty($assets['general']);
		$this->assertArrayHasKey('admin_stage_assets', $assets['deferred']);
		$this->assertCount(1, $assets['deferred']['admin_stage_assets']);
		$this->assertEquals('my-deferred-asset', $assets['deferred']['admin_stage_assets'][10][0]['handle']);
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
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_process_single_script_asset
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::add_assets
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::stage_assets
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
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::stage_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_process_single_script_asset
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::add_assets
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::stage_assets
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

		// Assert that only the priority 10 asset is enqueued
		WP_Mock::userFunction('wp_enqueue_script')->once()->with('asset-prio-10', 'path/to/p10.js', array(), false, array('in_footer' => false));
		WP_Mock::userFunction('wp_enqueue_script')->never()->with('asset-prio-20', Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any());

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
	 * @dataProvider provide_script_tag_modification_cases
	 * @covers       \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_modify_script_tag_attributes
	 */
	public function test_modify_script_tag_attributes_adds_attributes_correctly(string $handle, array $attributes, string $original_tag, string $expected_tag): void {
		// Arrange
		// The test class uses a method that calls the protected method from the trait.
		// Act
		$modified_tag = $this->_invoke_protected_method(
			$this->instance,
			'_modify_script_tag_attributes',
			array(AssetType::Script, $original_tag, $handle, $handle, $attributes)
		);

		// Assert
		$this->assertEquals($expected_tag, $modified_tag);
	}

	/**
	 * Data provider for `test_modify_script_tag_attributes_adds_attributes_correctly`.
	 */
	public static function provide_script_tag_modification_cases(): array {
		$handle       = 'my-script';
		$original_tag = "<script src='path/to/script.js' id='{$handle}-js'></script>";

		return array(
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
		);
	}
}
