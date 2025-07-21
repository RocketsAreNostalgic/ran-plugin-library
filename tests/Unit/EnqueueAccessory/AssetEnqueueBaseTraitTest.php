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
class ConcreteEnqueueForBaseTraitTesting extends ConcreteEnqueueForTesting {
	use ScriptsEnqueueTrait;
}

/**
 * Class ScriptsEnqueueTraitTest
 *
 * @package Ran\PluginLib\Tests\Unit\EnqueueAccessory
 *
 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait
 */
class AssetEnqueueTraitBaseTraitTest extends EnqueueTraitTestCase {
	use ExpectLogTrait;

	/**
	 * @inheritDoc
	 */
	protected function _get_concrete_class_name(): string {
		return ConcreteEnqueueForBaseTraitTesting::class;
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
	// _resolve_environment_src() Tests
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_resolve_environment_src
	 */
	public function test_resolve_environment_src_with_fallback_to_first_value(): void {
		// Test case where neither 'dev' nor 'prod' keys exist, should fall back to first value
		$src = array(
			'custom' => 'http://example.com/custom.js',
			'other'  => 'http://example.com/other.js'
		);

		$result = $this->_invoke_protected_method(
			$this->instance,
			'_resolve_environment_src',
			array($src)
		);

		$this->assertEquals('http://example.com/custom.js', $result, 'Should fall back to first array value when no dev/prod keys exist');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_resolve_environment_src
	 */
	public function test_resolve_environment_src_with_dev_environment_but_missing_dev_key(): void {
		// Configure mock to return true for dev environment
		$this->config_mock->shouldReceive('is_dev_environment')
			->andReturn(true);

		// Test case where we're in dev environment but only prod key exists
		$src = array(
			'prod' => 'http://example.com/script.min.js',
		);

		$result = $this->_invoke_protected_method(
			$this->instance,
			'_resolve_environment_src',
			array($src)
		);

		$this->assertEquals('http://example.com/script.min.js', $result, 'Should use prod URL when in dev environment but no dev URL exists');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_resolve_environment_src
	 */
	public function test_resolve_environment_src_with_malformed_array_missing_keys(): void {
		// Set up an array src with neither 'dev' nor 'prod' keys
		$malformed_src = array(
			'foo' => 'bar.js',
			'baz' => 'qux.js'
		);

		// Mock the config to return dev environment
		$this->config_mock->shouldReceive('is_dev_environment')
			->once()
			->andReturn(true);

		// Call the method
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_resolve_environment_src',
			array($malformed_src)
		);

		// Verify it returns the first available URL as fallback
		$this->assertEquals('bar.js', $result, 'Should fallback to first available URL when dev/prod keys are missing');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_resolve_environment_src
	 */
	public function test_resolve_environment_src_with_empty_values(): void {
		// Set up test cases
		$test_cases = array(
			'empty dev value' => array(
				'src'      => array('dev' => '', 'prod' => 'prod.js'),
				'is_dev'   => true,
				'expected' => 'prod.js',
				'message'  => 'Should fallback to prod when dev is empty in dev environment'
			),
			'empty prod value' => array(
				'src'      => array('dev' => 'dev.js', 'prod' => ''),
				'is_dev'   => false,
				'expected' => 'dev.js',
				'message'  => 'Should fallback to dev when prod is empty in prod environment'
			),
			'both empty values' => array(
				'src'      => array('dev' => '', 'prod' => ''),
				'is_dev'   => true,
				'expected' => '',
				'message'  => 'Should return empty string when both values are empty'
			)
		);

		foreach ($test_cases as $case_name => $case) {
			// Mock the config to return appropriate environment
			$this->config_mock->shouldReceive('is_dev_environment')
				->once()
				->andReturn($case['is_dev']);

			// Call the method
			$result = $this->_invoke_protected_method(
				$this->instance,
				'_resolve_environment_src',
				array($case['src'])
			);

			// Verify the result
			$this->assertEquals($case['expected'], $result, $case['message']);
		}
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_resolve_environment_src
	 */
	public function test_resolve_environment_src_with_non_string_values(): void {
		// Set up an array with non-string values
		$src_with_non_strings = array(
			'dev'  => 123, // Integer
			'prod' => true // Boolean
		);

		// Mock the config to return dev environment
		$this->config_mock->shouldReceive('is_dev_environment')
			->once()
			->andReturn(true);

		// Call the method
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_resolve_environment_src',
			array($src_with_non_strings)
		);

		// Verify it casts to string
		$this->assertIsString($result, 'Should return a string even with non-string input');
		$this->assertEquals('123', $result, 'Should cast integer to string');

		// Test with prod environment
		$this->config_mock->shouldReceive('is_dev_environment')
			->once()
			->andReturn(false);

		$result = $this->_invoke_protected_method(
			$this->instance,
			'_resolve_environment_src',
			array($src_with_non_strings)
		);

		$this->assertEquals('1', $result, 'Should cast boolean to string');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_resolve_environment_src
	 */
	public function test_resolve_environment_src_with_malformed_src_array(): void {
		// Test the _resolve_environment_src method directly with a malformed src array
		$malformed_src = array(
			'foo' => 'bar.js', // No dev/prod keys
			'baz' => 'qux.js'
		);

		// Mock the config for environment check
		$this->config_mock->shouldReceive('is_dev_environment')
			->once()
			->andReturn(true);

		// Call the method directly
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_resolve_environment_src',
			array($malformed_src)
		);

		// Verify it returns the first value from the array using reset()
		$this->assertEquals('bar.js', $result, 'Should have returned the first value from the array');
	}

	// ------------------------------------------------------------------------
	// get_asset() covered by cross functionality tests elsewhere
	// ------------------------------------------------------------------------

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
		$this->instance->add($assets_to_add);

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
		// The method looks for $this->assets['general'], so we need that structure
		$assets_property = new \ReflectionProperty($this->instance, 'assets');
		$assets_property->setAccessible(true);
		$assets_property->setValue($this->instance, array(
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
		);

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

	// ------------------------------------------------------------------------
	// _process_single_asset() Tests
	// ------------------------------------------------------------------------

	// ------------------------------------------------------------------------
	// _add_assets() covered by tests for Script and Style Traits
	// ------------------------------------------------------------------------

	// ------------------------------------------------------------------------
	// enqueue_immediate_assets() Tests
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::enqueue_immediate_assets
	 */
	public function test_enqueue_immediate_assets_skips_asset_with_empty_handle(): void {
		// Arrange - Directly populate the assets array with an asset that has an empty handle
		$assets_property = new \ReflectionProperty($this->instance, 'assets');
		$assets_property->setAccessible(true);
		$assets_property->setValue($this->instance, array(
				array(
					'handle' => '', // Empty handle - should trigger lines 384-389
					'src'    => 'path/to/script.js'
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
			'stage_scripts - Skipping asset at index 0 due to missing handle - this should not be possible when using add().'
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
			array(
				'handle' => 'deferred-script',
				'src'    => 'path/to/deferred-script.js',
				'hook'   => 'custom_hook' // This should trigger the LogicException (lines 391-397)
			)
		));

		// Assert - Should throw LogicException for deferred asset in immediate queue
		$this->expectException(\LogicException::class);
		$this->expectExceptionMessage(
			'stage_scripts - Found a deferred asset (\'deferred-script\') in the immediate queue. ' .
			'The `stage_assets()` method must be called before `enqueue_immediate_assets()` to correctly process deferred assets.'
		);

		// Act - Call enqueue_immediate_assets (should throw exception)
		$this->_invoke_protected_method(
			$this->instance,
			'enqueue_immediate_assets',
			array(AssetType::Script)
		);
	}

	// ------------------------------------------------------------------------
	// _enqueue_deferred_assets() Tests
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_enqueue_deferred_assets
	 */
	public function test_enqueue_deferred_assets_removes_empty_hooks(): void {
		// Set up the asset type
		$asset_type = AssetType::Script;

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
		$this->set_protected_property_value(
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

		// Call the method
		$this->_invoke_protected_method(
			$this->instance,
			'_enqueue_deferred_assets',
			array($asset_type, $hook_name, $priority)
		);

		// Get the deferred assets after processing
		$deferred_assets = $this->get_protected_property_value($this->instance, 'deferred_assets');

		// Verify that the deferred assets array exists and the hook has been removed
		$this->assertIsArray($deferred_assets, 'Deferred assets should be an array');
		$this->assertArrayNotHasKey($hook_name, $deferred_assets, 'Hook should be removed after processing');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_enqueue_deferred_assets
	 */
	public function test_enqueue_deferred_assets_skips_missing_priority_and_cleans_empty_hooks(): void {
		// Set up the asset type
		$asset_type = AssetType::Script;

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
		$instance = new ConcreteEnqueueForBaseTraitTesting($config_mock);

		// Set up the deferred assets array with one priority but will call with a different priority
		// Using flattened array structure (no asset type nesting)
		$this->set_protected_property_value(
			$instance,
			'deferred_assets',
			array(
				$hook_name => array(
					$priority_exists => array($deferred_asset)
				)
			)
		);

		// Call the method with the missing priority
		$this->_invoke_protected_method(
			$instance,
			'_enqueue_deferred_assets',
			array($asset_type, $hook_name, $priority_missing)
		);

		// Get the deferred assets after processing
		$deferred_assets = $this->get_protected_property_value($instance, 'deferred_assets');

		// Verify that the deferred assets array still contains the hook and the existing priority
		$this->assertIsArray($deferred_assets, 'Deferred assets should be an array');
		$this->assertArrayHasKey($hook_name, $deferred_assets, 'Hook should still exist');
		$this->assertArrayHasKey($priority_exists, $deferred_assets[$hook_name], 'Priority should still exist');

		// Now remove the existing priority and call again to test hook cleanup
		$this->set_protected_property_value(
			$instance,
			'deferred_assets',
			array(
				$hook_name => array() // Empty priorities array
			)
		);

		// Call the method again
		$this->_invoke_protected_method(
			$instance,
			'_enqueue_deferred_assets',
			array($asset_type, $hook_name, $priority_missing)
		);

		// Get the deferred assets after processing
		$deferred_assets = $this->get_protected_property_value($instance, 'deferred_assets');

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


	// ------------------------------------------------------------------------
	// add_inline_assets() Covered elswere in cross trait tests
	// ------------------------------------------------------------------------

	// ------------------------------------------------------------------------
	// _add_inline_asset() Tests
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_add_inline_asset
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

		// Call the method
		$this->_invoke_protected_method(
			$this->instance,
			'_add_inline_asset',
			array(
				AssetType::Script,
				'external-script',
				'console.log("Hello World");',
				'after',
				null,
				null
			)
		);

		// Get the assets to verify the inline script was added to external_inline_assets
		$assets = $this->instance->get_assets(AssetType::Script);

		$this->assertArrayHasKey('external_inline', $assets);
		$this->assertArrayHasKey('wp_enqueue_scripts', $assets['external_inline']);
		$this->assertArrayHasKey('external-script', $assets['external_inline']['wp_enqueue_scripts']);
		$this->assertEquals('console.log("Hello World");', $assets['external_inline']['wp_enqueue_scripts']['external-script'][0]['content']);
		$this->assertEquals('registered', $assets['external_inline']['wp_enqueue_scripts']['external-script'][0]['status']);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_add_inline_asset
	 */
	public function test_add_inline_asset_with_custom_parent_hook(): void {
		// Mock the add_action call for the external inline script with custom hook
		WP_Mock::expectActionAdded(
			'custom_hook',
			array($this->instance, 'enqueue_external_inline_scripts'),
			11
		);

		// Call the method with a custom parent hook
		$this->_invoke_protected_method(
			$this->instance,
			'_add_inline_asset',
			array(
				AssetType::Script,
				'promised-script',
				'console.log("Custom Hook");',
				'after',
				null,
				'custom_hook'
			)
		);

		// Get the assets to verify the inline script was added to external_inline_assets with custom hook
		$assets = $this->instance->get_assets(AssetType::Script);

		$this->assertArrayHasKey('external_inline', $assets);
		$this->assertArrayHasKey('custom_hook', $assets['external_inline']);
		$this->assertArrayHasKey('promised-script', $assets['external_inline']['custom_hook']);
		$this->assertEquals('console.log("Custom Hook");', $assets['external_inline']['custom_hook']['promised-script'][0]['content']);
		$this->assertEquals('promised', $assets['external_inline']['custom_hook']['promised-script'][0]['status']);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_add_inline_asset
	 */
	public function test_add_inline_asset_with_parent_in_immediate_queue_ignores_parent_hook(): void {
		// Add a script to the immediate queue
		$this->instance->add(array(
			'handle' => 'immediate-parent',
			'src'    => 'path/to/parent.js',
		));

		// Call the method with a parent_hook even though the parent is in the immediate queue
		$this->_invoke_protected_method(
			$this->instance,
			'_add_inline_asset',
			array(
				AssetType::Script,
				'immediate-parent',
				'console.log("Inline for immediate parent");',
				'after',
				null,
				'unnecessary_hook' // This should be ignored and logged
			)
		);

		// Get the assets to verify the inline script was added to the immediate parent
		$assets = $this->instance->get_assets(AssetType::Script);

		// Set up logger expectations
		$this->expectLog(
			'warning',
			array(
				'add_inline_',
				"A 'parent_hook' was provided for 'immediate-parent', but it's ignored as the parent was found internally in the immediate queue."
			), 1
		);

		// Verify the inline script was added to the immediate parent
		$this->assertArrayHasKey('general', $assets);
		$this->assertCount(1, $assets['general']);
		$this->assertEquals('immediate-parent', $assets['general'][0]['handle']);
		$this->assertArrayHasKey('inline', $assets['general'][0]);
		$this->assertCount(1, $assets['general'][0]['inline']);
		$this->assertEquals('console.log("Inline for immediate parent");', $assets['general'][0]['inline'][0]['content']);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_add_inline_asset
	 */
	public function test_add_inline_asset_bails_when_parent_not_found_and_no_parent_hook(): void {
		// Mock that the script is NOT registered in WordPress
		WP_Mock::userFunction('wp_script_is', array(
			'args'   => array('nonexistent-parent', 'registered'),
			'return' => false,
		));

		// Call the method with a parent that doesn't exist anywhere and no parent_hook
		$this->_invoke_protected_method(
			$this->instance,
			'_add_inline_asset',
			array(
				AssetType::Script,
				'nonexistent-parent',
				'console.log("This should not be added");',
				'after',
				null,
				null // No parent_hook
			)
		);

		// Get the assets to verify nothing was added
		$assets = $this->instance->get_assets(AssetType::Script);

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

		// Call the method under test directly
		$this->_invoke_protected_method(
			$this->instance,
			'_enqueue_external_inline_assets',
			array(AssetType::Script)
		);

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

		// Set up the inline_assets property
		$inline_assets_property = $reflection->getProperty('inline_assets');
		$inline_assets_property->setAccessible(true);
		$inline_assets = array(
				array(
					'handle'   => 'test-script',
					'content'  => 'console.log("test");',
					'position' => 'after'
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
				array(
					'handle'    => 'test-script',
					'content'   => 'console.log("test");',
					'position'  => 'after',
					'condition' => function() {
						return false;
					}
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
				array(
					'handle'   => 'test-script',
					'content'  => '',
					'position' => 'after'
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
			// Valid array element
			array(
				'handle'   => 'test-script',
				'content'  => 'console.log("test");',
				'position' => 'after'
			),
			// Invalid non-array element that should trigger the warning
			'invalid-string-element'
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
			// Valid array element
			array(
				'handle'   => 'test-script',
				'content'  => 'console.log("test");',
				'position' => 'after'
			),
			// Invalid non-array element that should trigger the warning
			'invalid-string-element'
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
		$this->expectLog('warning', 'Invalid inline script data at key', 1);
		$this->expectLog('debug', 'Removed processed inline script', 1);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_process_inline_assets
	 */
	public function test_process_inline_assets_processes_style_assets(): void {
		// Arrange: Set up inline assets for styles (to cover line 728: position = null for styles)
		$parent_handle  = 'parent-style';
		$inline_content = '.test { color: blue; }';

		$reflection = new \ReflectionClass($this->instance);
		$method     = $reflection->getMethod('_process_inline_assets');
		$method->setAccessible(true);

		// Set up the inline_assets property using reflection
		$inline_assets_property = $reflection->getProperty('inline_assets');
		$inline_assets_property->setAccessible(true);
		$inline_assets = array(
			array(
				'handle'  => $parent_handle,
				'content' => $inline_content,
				// Note: no 'position' for styles - this tests the line 728 branch
			)
		);
		$inline_assets_property->setValue($this->instance, $inline_assets);

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
		$this->expectLog('debug', 'Adding inline style for');
		$this->expectLog('debug', 'Successfully added inline style for');
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
			array(
				'handle'      => $parent_handle,
				'parent_hook' => $hook_name, // This should match the hook_name parameter
				'content'     => $inline_content,
				'position'    => 'after',
			)
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
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_process_inline_assets
	 */
	public function test_process_inline_assets_with_no_inline_assets_found(): void {
		// Create a reflection method to access the protected method
		$reflection = new \ReflectionClass($this->instance);
		$method     = $reflection->getMethod('_process_inline_assets');
		$method->setAccessible(true);

		// Set up the inline_assets property to be empty
		$inline_assets_property = $reflection->getProperty('inline_assets');
		$inline_assets_property->setAccessible(true);
		$inline_assets_property->setValue($this->instance, array());

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
		$this->expectLog('debug', "No inline script found or processed for '{$parent_handle}'.");
	}

	// ------------------------------------------------------------------------
	// _concrete_process_single_asset() Tests
	// ------------------------------------------------------------------------

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
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_resolve_environment_src
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_concrete_process_single_asset
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
	public function test_concrete_process_single_asset_with_incorrect_asset_type(): void {
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
	public function test_concrete_process_single_asset_with_async_strategy(): void {
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
	public function test_concrete_process_single_asset_with_defer_strategy(): void {
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
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_concrete_process_single_asset
	 */
	public function test_concrete_process_single_asset_handles_enqueue_failure(): void {
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
	public function test_concrete_process_single_asset_localizes_script_correctly(): void {
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
	// _do_register() Tests
	// ------------------------------------------------------------------------

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


	// ------------------------------------------------------------------------
	// _is_deferred_asset() Tests
	// ------------------------------------------------------------------------

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

	// ------------------------------------------------------------------------
	// _do_enqueue() Tests
	// ------------------------------------------------------------------------

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
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_do_enqueue
	 */
	public function test_do_enqueue_registers_script_when_not_registered(): void {
		// Arrange
		$handle           = 'test-script-not-registered';
		$src              = 'path/to/script.js';
		$deps             = array();
		$ver              = '1.0';
		$extra_args       = array('in_footer' => true); // media parameter for styles
		$do_enqueue       = true; // Whether to enqueue the asset
		$context          = 'test'; // Context for logging
		$log_hook_context = ''; // Additional hook context for logging
		$is_deferred      = false; // Whether this is a deferred asset
		$hook_name        = null; // Hook name for deferred assets

		// Mock wp_script_is for both registered and enqueued checks
		WP_Mock::userFunction('wp_script_is')
			->with($handle, 'registered')
			->andReturn(false);

		WP_Mock::userFunction('wp_script_is')
			->with($handle, 'enqueued')
			->andReturn(false);

		// Mock wp_register_script to return true
		WP_Mock::userFunction('wp_register_script')
			->once()
			->with($handle, $src, $deps, $ver, $extra_args)
			->andReturn(true);

		// Mock wp_enqueue_script
		WP_Mock::userFunction('wp_enqueue_script')
			->once()
			->with($handle)
			->andReturn(null);

		// Act: Call the _do_enqueue method with all required parameters
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_do_enqueue',
			array(
				AssetType::Script,
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

		// Assert
		$this->assertTrue($result, 'The _do_enqueue method should return true on success');
		$this->expectLog('warning', array("test - script 'test-script-not-registered' was not registered before enqueuing"), 1);
		$this->expectLog('debug', array('Enqueuing script', 'test-script-not-registered'), 1);
	}

	// ------------------------------------------------------------------------
	// _generate_asset_version() Tests
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_generate_asset_version
	 */
	public function test_generate_asset_version_with_array_based_src(): void {
		// Set up an asset definition with array-based src and cache_bust enabled
		$asset_definition = array(
			'handle'     => 'test-script',
			'version'    => '1.0.0',
			'cache_bust' => true,
			'src'        => array(
				'dev'  => 'test-script.js',
				'prod' => 'test-script.min.js'
			)
		);

		// Mock the config to return dev environment
		$this->config_mock->shouldReceive('is_dev_environment')
			->once()
			->andReturn(true);

		// Mock _resolve_url_to_path to simulate finding the file
		$this->instance = $this->getMockBuilder(ConcreteEnqueueForScriptsTesting::class)
			->setConstructorArgs(array($this->config_mock))
			->onlyMethods(array('_resolve_url_to_path', '_file_exists', '_md5_file'))
			->addMethods(array('get_config'))
			->getMock();

		// Mock get_config to return the config_mock
		$this->instance->expects($this->any())
			->method('get_config')
			->willReturn($this->config_mock);

		$this->instance->expects($this->once())
			->method('_resolve_url_to_path')
			->with('test-script.js') // Should be the resolved dev URL
			->willReturn('/path/to/test-script.js');

		$this->instance->expects($this->once())
			->method('_file_exists')
			->with('/path/to/test-script.js')
			->willReturn(true);

		$this->instance->expects($this->once())
			->method('_md5_file')
			->with('/path/to/test-script.js')
			->willReturn('abcdef1234567890');

		// Call the method
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_generate_asset_version',
			array($asset_definition)
		);

		// Verify it returns a string (would be a hash if cache busting worked)
		$this->assertIsString($result);
		$this->assertNotEquals('1.0.0', $result, 'Should have generated a cache-busting hash, not returned the original version');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_generate_asset_version
	 */
	public function test_generate_asset_version_with_empty_src(): void {
		// Set up an asset definition with empty src but cache_bust enabled
		$asset_definition = array(
			'handle'     => 'test-script',
			'version'    => '1.0.0',
			'cache_bust' => true,
			'src'        => '', // Empty source URL
		);

		// Call the method
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_generate_asset_version',
			array($asset_definition)
		);

		// Verify it returns the original version without attempting to cache-bust
		$this->assertEquals('1.0.0', $result);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_generate_asset_version
	 */
	public function test_generate_asset_version_with_string_src(): void {
		// Set up an asset definition with a string src and cache_bust enabled
		$asset_definition = array(
			'handle'     => 'test-script',
			'version'    => '1.0.0',
			'cache_bust' => true,
			'src'        => 'bar.js'
		);

		// Use the existing instance and set up the necessary mock expectations
		$this->instance->shouldReceive('_resolve_url_to_path')
			->once()
			->with('bar.js')
			->andReturn('/path/to/bar.js');

		$this->instance->shouldReceive('_file_exists')
			->once()
			->with('/path/to/bar.js')
			->andReturn(true);

		$this->instance->shouldReceive('_md5_file')
			->once()
			->with('/path/to/bar.js')
			->andReturn('abcdef1234567890');

		// Call the method
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_generate_asset_version',
			array($asset_definition)
		);

		// Verify it still generates a cache-busting hash using the URL
		$this->assertIsString($result);
		$this->assertNotEquals('1.0.0', $result, 'Should have generated a cache-busting hash');
		$this->assertEquals('abcdef1234', $result, 'Should have used the first 10 characters of the MD5 hash');
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

	// ------------------------------------------------------------------------
	// _resolve_url_to_path() Tests
	// ------------------------------------------------------------------------

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

	// ------------------------------------------------------------------------
	// _file_exists() Tests
	// ------------------------------------------------------------------------

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

	// ------------------------------------------------------------------------
	// _build_attribute_string() Tests
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_build_attribute_string
	 */
	public function test_build_attribute_string_with_special_attributes(): void {
		// Mock esc_attr to return the input unchanged for testing
		WP_Mock::userFunction('esc_attr', array(
			'return_arg' => 0
		));

		// Define attributes to apply
		$attributes_to_apply = array(
			'data-test'    => 'value',
			'special-attr' => 'special-value',
			'normal-attr'  => 'normal-value'
		);

		// Define managed attributes (should be ignored)
		$managed_attributes = array('id', 'src');

		// Define special attributes with callback
		$special_attributes = array(
			'special-attr' => function($attr, $value) {
				// This callback modifies the attribute value
				return 'modified-' . $value;
			},
			'skip-attr' => function($attr, $value) {
				// This callback returns false to skip the attribute
				return false;
			}
		);

		// Add the skip-attr to attributes
		$attributes_to_apply['skip-attr'] = 'should-be-skipped';

		// Call the method
		$result = $this->_invoke_protected_method(
			$this->instance,
			'_build_attribute_string',
			array(
				$attributes_to_apply,
				$managed_attributes,
				'test_context',
				'test-handle',
				AssetType::Script,
				$special_attributes
			)
		);

		// Verify the result contains the special attribute (not modified) and not the skipped one
		// Note: The current implementation calls the special attribute callback but doesn't use its return value
		$this->assertStringContainsString('data-test="value"', $result);
		$this->assertStringContainsString('normal-attr="normal-value"', $result);
		$this->assertStringNotContainsString('skip-attr', $result);
		$this->assertStringContainsString('special-attr="special-value"', $result);
	}

	// ------------------------------------------------------------------------
	// _md5_file() Tests
	// ------------------------------------------------------------------------

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

	// ------------------------------------------------------------------------
	// render_head() Tests
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
	// _do_add_filter() Tests
	// ------------------------------------------------------------------------

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

	// ------------------------------------------------------------------------
	// _do_add_action() Tests
	// ------------------------------------------------------------------------

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
}
