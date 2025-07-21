<?php
/**
 * Unit tests for the Config class array_filter handling.
 *
 * This file contains tests specifically for ensuring proper handling of null values
 * in array_filter operations within ConfigAbstract.
 *
 * @package  Ran\PluginLib\Tests\Unit
 */

declare(strict_types = 1);

namespace Ran\PluginLib\Tests\Unit;

use RanTestCase; // Declared in test_bootstrap.php.
use Ran\PluginLib\Config\Config;
use Ran\PluginLib\Config\ConfigAbstract;
use WP_Mock;

/**
 * Test for Config class array_filter handling.
 *
 * @covers \Ran\PluginLib\Config\ConfigAbstract
 */
final class ConfigArrayFilterTest extends RanTestCase {
	/**
	 * The plugin root path.
	 *
	 * @var string
	 */
	private string $plugin_root_path;

	/**
	 * The plugin basename.
	 *
	 * @var string
	 */
	private string $plugin_basename;

	/**
	 * The full plugin file path.
	 *
	 * @var string
	 */
	private string $full_plugin_file_path;

	/**
	 * The plugin root URL.
	 *
	 * @var string
	 */
	private string $plugin_root_url;

	/**
	 * The plugin data.
	 *
	 * @var array
	 */
	private array $plugin_data;

	/**
	 * Sets up the test environment.
	 */
	public function setUp(): void {
		parent::setUp(); // Crucial for WP_Mock

		// ConfigTest.php is in .../vendor/ran/plugin-lib/tests/Unit/
		// Root of ran-starter-plugin is 5 levels up from __DIR__ (directory of ConfigTest.php)
		$this->plugin_root_path = dirname(__DIR__, 5) . '/';

		// Name of the main plugin file for ran-starter-plugin
		$this->plugin_basename = 'ran-starter-plugin.php';

		// Full path to the main plugin file
		$this->full_plugin_file_path = $this->plugin_root_path . $this->plugin_basename;

		// Mock URL for the plugin directory
		$this->plugin_root_url = 'http://example.com/wp-content/plugins/ran-starter-plugin/';

		// Update plugin_data to reflect the plugin being tested
		$this->plugin_data = array(
			'Name'        => 'Ran Starter Plugin Test',
			'Version'     => '1.0.0',
			'TextDomain'  => 'ran-starter-plugin-lib',
			'DomainPath'  => '/languages',
			'Description' => 'Test Description',
			'Author'      => 'Test Author',
			'PluginURI'   => 'http://example.com/plugin-uri-test',
			'AuthorURI'   => 'http://example.com/author-uri-test',
			'UpdatesURI'  => 'http://example.com/updates-uri-test',
			'RequiresPHP' => '7.4',
			'RequiresWP'  => '5.5',
		);

		// IMPORTANT: Set the static plugin_file property on ConfigAbstract for all tests.
		// This ensures that direct `new Config()` instantiations use the correct file path.
		$configReflection   = new \ReflectionClass(\Ran\PluginLib\Config\ConfigAbstract::class);
		$pluginFileProperty = $configReflection->getProperty('plugin_file');
		$pluginFileProperty->setAccessible(true);
		$pluginFileProperty->setValue(null, $this->full_plugin_file_path);

		// Mock for plugin_dir_path, used by ConfigAbstract to determine plugin_root_path
		WP_Mock::userFunction('plugin_dir_path')
			->with($this->full_plugin_file_path)
			->zeroOrMoreTimes()
			->andReturn($this->plugin_root_path);

		WP_Mock::userFunction('wp_unslash')->andReturnUsing(fn($value) => $value);

		// Mock for plugins_url, used by ConfigAbstract to determine plugin_dir_url
		WP_Mock::userFunction('plugins_url')
			->zeroOrMoreTimes()
			->andReturn($this->plugin_root_url);

		// Mock for plugin_dir_url, which can be called internally by plugins_url()
		WP_Mock::userFunction('plugin_dir_url')
			->zeroOrMoreTimes()
			->andReturn($this->plugin_root_url);
	}

	/**
	 * Tests that get_plugin_options handles array_filter with null values correctly.
	 *
	 * @covers \Ran\PluginLib\Config\ConfigAbstract::get_plugin_options
	 */
	public function test_get_plugin_options_handles_array_filter_with_null_values(): void {
		// Create a mock for the Config class
		$config = \Mockery::mock('\Ran\PluginLib\Config\Config')
			->makePartial()
			->shouldAllowMockingProtectedMethods();

		// Mock plugin_basename to avoid WP function call
		WP_Mock::userFunction('plugin_basename')
			->andReturn($this->plugin_basename);

		// Create a plugin_array with RANPluginOption set to an array containing null values
		$plugin_array                    = $this->plugin_data;
		$plugin_array['RANPluginOption'] = 'test_option_with_nulls';

		// Initialize the plugin_array property to avoid the uninitialized property error
		$reflectionClass     = new \ReflectionClass('\Ran\PluginLib\Config\ConfigAbstract');
		$pluginArrayProperty = $reflectionClass->getProperty('plugin_array');
		$pluginArrayProperty->setAccessible(true);
		$pluginArrayProperty->setValue($config, $plugin_array);

		// Mock the get_plugin_config method to return our plugin_array
		$config->shouldReceive('get_plugin_config')
			->andReturn($plugin_array);

		// Create an option value with null values that would be problematic for array_filter
		$option_value = array(
			'key1' => 'value1',
			'key2' => null,
			'key3' => 'value3',
		);

		// Mock get_option to return our option value with nulls
		WP_Mock::userFunction('get_option')
			->with('test_option_with_nulls', false)
			->andReturn($option_value)
			->once();

		// Call get_plugin_options
		$result = $config->get_plugin_options('');

		// Assert that the result includes all keys, including those with null values
		$this->assertSame($option_value, $result, 'get_plugin_options should handle null values in array_filter correctly');
		$this->assertArrayHasKey('key2', $result, 'Result should contain keys with null values');
		$this->assertNull($result['key2'], 'Null values should be preserved');
	}

	/**
	 * Tears down the test environment.
	 */
	public function tearDown(): void {
		\Mockery::close();
		parent::tearDown();
	}
}
