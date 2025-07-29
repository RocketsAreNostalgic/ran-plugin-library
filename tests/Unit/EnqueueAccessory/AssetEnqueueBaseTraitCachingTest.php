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
class ConcreteEnqueueForBaseTraitCachingTesting extends ConcreteEnqueueForTesting {
	use ScriptsEnqueueTrait;
}

/**
 * Class ScriptsEnqueueTraitTest
 *
 * @package Ran\PluginLib\Tests\Unit\EnqueueAccessory
 *
 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait
 */
class AssetEnqueueBaseTraitCachingTest extends EnqueueTraitTestCase {
	use ExpectLogTrait;

	/**
	 * @inheritDoc
	 */
	protected function _get_concrete_class_name(): string {
		return ConcreteEnqueueForBaseTraitCachingTesting::class;
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
	// We have to rely heavily on reflection for this suite via _invoke_protected_method
	// because most tests here are testing pure utility methods and complex internal logic
	// that don't have meaningful public interfaces (per ADR-001 guidelines):
	//
	// 1. _generate_asset_version() - Complex cache-busting logic with file system operations
	// 2. _resolve_environment_src() - URL resolution utility for dev/prod environments
	// 3. _resolve_url_to_path() - File system path conversion utility
	// 4. _file_exists() / _md5_file() - Pure file system utility methods
	//
	// These methods are self-contained utilities that are clearer to test directly
	// rather than forcing integration tests through complex public interface setups.
	// This is documented as an acceptable exception in ADR-001.
	// ------------------------------------------------------------------------

	// ------------------------------------------------------------------------
	// _generate_asset_version() Tests
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

		// Verify that file system methods are never called when cache_bust is false
		$this->instance->shouldReceive('_file_exists')->never();
		$this->instance->shouldReceive('_md5_file')->never();

		// --- Act ---
		// Test utility method directly - cache-busting logic is complex internal behavior
		$actual_version = $this->_invoke_protected_method($this->instance, '_generate_asset_version', array($asset_definition));

		// --- Assert ---
		$this->assertSame($default_version, $actual_version, 'Should use default version when cache_bust is disabled');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_generate_asset_version
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_concrete_process_single_asset
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::add
	 */
	public function test_cache_busting_falls_back_to_default_version_when_file_not_found(): void {
		// --- Test Setup ---
		$handle          = 'my-script';
		$src             = 'http://example.com/wp-content/plugins/my-plugin/js/my-script.js';
		$file_path       = WP_CONTENT_DIR . '/plugins/my-plugin/js/my-script.js';
		$default_version = '1.2.3';

		if (!defined('WP_CONTENT_DIR')) {
			define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
		}

		// Mock WordPress functions
		WP_Mock::userFunction('content_url')->andReturn('http://example.com/wp-content');
		WP_Mock::userFunction('site_url')->andReturn('http://example.com');
		WP_Mock::userFunction('wp_normalize_path')->andReturnUsing(fn($p) => $p);
		WP_Mock::userFunction('wp_register_script')->once();

		// Mock file system calls
		$this->instance->shouldReceive('_file_exists')->once()->with($file_path)->andReturn(false);
		$this->instance->shouldReceive('_md5_file')->never();

		// --- Act ---
		// Test through public interface - add asset with cache_bust=true, then stage to trigger processing
		$this->instance->add(array(
		    'handle'     => $handle,
		    'src'        => $src,
		    'version'    => $default_version,
		    'cache_bust' => true,
		));
		$this->instance->stage();

		// Get the processed asset to verify the version
		$assets          = $this->instance->get_assets_info($this->instance->_get_asset_type());
		$processed_asset = $assets['assets'][0];

		// --- Assert ---
		$this->assertSame($default_version, $processed_asset['version'], 'Should fall back to default version when file not found');
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

		// Mock WordPress functions
		WP_Mock::userFunction('content_url')->andReturn('http://example.com/wp-content');
		WP_Mock::userFunction('site_url')->andReturn('http://example.com');
		WP_Mock::userFunction('wp_normalize_path')->andReturnUsing(fn($p) => $p);

		// Mock file system calls for successful cache-busting
		$this->instance->shouldReceive('_file_exists')->once()->with($file_path)->andReturn(true);
		$this->instance->shouldReceive('_md5_file')->once()->with($file_path)->andReturn($hash);

		// --- Act ---
		// Test utility method directly - cache-busting logic is complex internal behavior
		$actual_version = $this->_invoke_protected_method($this->instance, '_generate_asset_version', array($asset_definition));

		// --- Assert ---
		$this->assertSame($expected_version, $actual_version, 'Should use hash-based version when cache_bust is enabled and file exists');
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
		$this->instance = $this->getMockBuilder(ConcreteEnqueueForBaseTraitCachingTesting::class)
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
		$mock = $this->getMockBuilder(ConcreteEnqueueForBaseTraitCachingTesting::class)
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
}
