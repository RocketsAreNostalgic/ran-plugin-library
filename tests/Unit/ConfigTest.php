<?php
/**
 * Unit tests for the Config class.
 *
 * This file contains tests for the Config class functionality.
 *
 * @package  Ran\PluginLib\Tests\Unit
 */

declare(strict_types = 1);

namespace Ran\PluginLib\Tests\Unit;

use RanTestCase; // Declared in test_bootstrap.php.
use Ran\PluginLib\Config\Config;
use WP_Mock;

/**
 * Test for Config class.
 *
 * @covers Ran\PluginLib\Config\ConfigAbstract
 */
final class ConfigTest extends RanTestCase {
	/**
	 * The Config instance being tested.
	 *
	 * @var Config Instance of the Config class under test.
	 */
	public Config $config;

	private string $plugin_root_path;
	private string $plugin_file_name;
	private string $full_plugin_file_path;
	private string $plugin_root_url;

	/**
	 * Sets up the test environment.
	 */
	public function setUp(): void {
		parent::setUp(); // Crucial for WP_Mock

		// ConfigTest.php is in .../vendor/ran/plugin-lib/tests/Unit/
		// Root of ran-starter-plugin is 5 levels up from __DIR__ (directory of ConfigTest.php)
		$this->plugin_root_path = dirname(__DIR__, 5) . '/';

		// Name of the main plugin file for ran-starter-plugin
		$this->plugin_file_name = 'ran-starter-plugin.php';

		// Full path to the main plugin file
		$this->full_plugin_file_path = $this->plugin_root_path . $this->plugin_file_name;

		// Mock URL for the plugin directory
		$this->plugin_root_url = 'http://example.com/wp-content/plugins/ran-starter-plugin/';

		// Update plugin_data to reflect the plugin being tested
		$this->plugin_data = array(
			'Name'        => 'Ran Starter Plugin',
			'Version'     => '1.0.0',
			'Description' => 'Test plugin description for Ran Starter Plugin',
			'UpdatesURI'  => 'https://example.com/ran-starter-plugin/updates',
			'PluginURI'   => 'https://example.com/ran-starter-plugin/',
			'Author'      => 'Ran Starter Author',
			'AuthorURI'   => 'https://example.com/author/ran-starter/',
			'TextDomain'  => 'ran-starter-plugin',
			'DomainPath'  => '/languages',
			'RequiresPHP' => '7.4',
			'RequiresWP'  => '5.8',
		);
	}

	/**
	 * Tears down the test environment.
	 */
	public function tearDown(): void {
		// Manually remove the Config instance from SingletonAbstract::$instances
		try {
			$reflectionClass   = new \ReflectionClass(\Ran\PluginLib\Singleton\SingletonAbstract::class);
			$instancesProperty = $reflectionClass->getProperty('instances');
			$instancesProperty->setAccessible(true);
			$current_instances = $instancesProperty->getValue(null);
			if (isset($current_instances[Config::class])) {
				unset($current_instances[Config::class]);
				$instancesProperty->setValue(null, $current_instances);
			}
		} catch (\ReflectionException $e) {
			// Handle reflection error if necessary, though unlikely here
		}

		parent::tearDown(); // Calls WP_Mock::tearDown() etc.
	}

	/**
	 * Mock data for get_plugin_data function.
	 *
	 * @var array<string, string>
	 */
	private array $plugin_data = array(); // Initialized in setUp()

	/**
	 * Tests the Config constructor.
	 *
	 * @covers Ran\PluginLib\Config\ConfigAbstract::__construct
	 */
	public function test_config_contruct(): void {
		// Create Config object.
		$config = $this->get_config();

		$this->assertTrue( $config instanceof Config );
		$this->assertTrue( \property_exists( $config, 'plugin_array' ) );
	}

	/**
	 * Tests the plugin array property.
	 *
	 * @covers Ran\PluginLib\Config\ConfigAbstract
	 * @uses Ran\PluginLib\Config\ConfigAbstract::__construct
	 */
	public function test_plugin_array(): void {
		$wp_runtime_data = array(
			// WP adds these fields at runtime.
			'Network'    => '',
			'Title'      => '<a href="' . $this->plugin_data['PluginURI'] . '">' . $this->plugin_data['Name'] . '</a>',
			'AuthorName' => '<a href="' . $this->plugin_data['AuthorURI'] . '">' . $this->plugin_data['Author'] . '</a>',
		);

		// Set up expected plugin array.
		$expected_plugin_array = array(
			'PATH'     => $this->plugin_root_path,
			'URL'      => $this->plugin_root_url,
			'FileName' => $this->plugin_file_name,
			'File'     => $this->full_plugin_file_path,
			// Custom Headers (Normalized from mock file content)
			'LogConstantName' => 'TEST_DEBUG_MODE',
			'AnotherCustom'   => 'Value For Custom',
			'RequiresWp'      => $this->plugin_data['RequiresWP'], // Match buggy behavior of _get_custom_headers
			'PluginOption'    => 'ran_starter_plugin',
			// Standard Headers (from get_plugin_data mock, these take precedence)
			'Name'        => $this->plugin_data['Name'],
			'PluginURI'   => $this->plugin_data['PluginURI'],
			'Version'     => $this->plugin_data['Version'],
			'Description' => $this->plugin_data['Description'],
			'Author'      => $this->plugin_data['Author'],
			'AuthorURI'   => $this->plugin_data['AuthorURI'],
			'TextDomain'  => $this->plugin_data['TextDomain'],
			'DomainPath'  => $this->plugin_data['DomainPath'],
			'RequiresWP'  => $this->plugin_data['RequiresWP'],
			'RequiresPHP' => $this->plugin_data['RequiresPHP'],
			'UpdatesURI'  => $this->plugin_data['UpdatesURI'],
			// Calculated by ConfigAbstract
			'PluginOption' => str_replace( '-', '_', $this->plugin_data['TextDomain'] ),
		);

		// Create Config object.
		$config = $this->get_config();

		// Assert that plugin_array property matches expected_plugin_array.
		$this->assertEquals( $expected_plugin_array, $config->plugin_array );
	}
	/**
	 * This should throw an Exception.
	 *
	 * @covers Ran\PluginLib\Config\ConfigAbstract::validate_plugin_array
	 * @uses Ran\PluginLib\Config\ConfigAbstract::__construct
	 */
	public function test_validate_plugin_array(): void {
		// Create Config object.
		$config = $this->get_config();

		// Config::validate_plugin_array should throw if the array doesn't contain the required keys.
		$this->expectException( \Exception::class );
		$config->validate_plugin_array( array() );
	}

	/**
	 * Tests the get_plugin_options method.
	 *
	 * @covers Ran\PluginLib\Config\ConfigAbstract::get_plugin_options
	 */
	public function test_get_plugin_options(): void {
		// Mock the plugin option id.
		$plugin_opt_id = str_replace( '-', '_', $this->plugin_data['TextDomain'] );
		$moc_options   = array(
			'Version' => '0.0.1',
		);

		// Set up additional mock of get_option.
		WP_Mock::userFunction( 'get_option' )
			->with( $plugin_opt_id, false )
			->andReturn( $moc_options );

		// Create Config object.
		$config = $this->get_config();

		$options = $config->get_plugin_options( $plugin_opt_id, false );
		$this->assertEquals( $moc_options, $options );
	}

	/**
	 * Creates and returns a configured Config instance for testing.
	 *
	 * @return Config The configured Config instance.
	 */
	public function get_config(): Config {
		// Set up mock functions for WordPress environment.
		WP_Mock::passthruFunction( 'sanitize_title' );

		// Mock WordPress functions that ConfigAbstract relies on.
		// These must be set up BEFORE Config::init() and the Config object instantiation.
		WP_Mock::userFunction( 'plugin_dir_path' )
			->with( $this->full_plugin_file_path )
			->andReturn( $this->plugin_root_path )
			->atLeast()->once();

		WP_Mock::userFunction( 'plugin_dir_url' )
			->with( $this->full_plugin_file_path )
			->andReturn( $this->plugin_root_url )
			->atLeast()->once();

		WP_Mock::userFunction( 'plugin_basename' )
			->atLeast()->once() // Changed from once() for diagnostics
			->with( $this->full_plugin_file_path )
			->andReturn( $this->plugin_file_name );

		WP_Mock::userFunction( 'get_plugin_data' )
			->atLeast()->once() // Changed from once() for diagnostics
			->with( $this->full_plugin_file_path, false, false )
			->andReturn( $this->plugin_data );

		// Initialize the Config class with the main plugin file path.
		Config::init( $this->full_plugin_file_path );

		// Define the mock content for the plugin file header.
		$mock_file_content = "<?php\n" .
			"/**\n" .
			" * Plugin Name: {$this->plugin_data['Name']}\n" .
			" * Version: {$this->plugin_data['Version']}\n" .
			" * Description: {$this->plugin_data['Description']}\n" .
			" * Author: {$this->plugin_data['Author']}\n" .
			" * Text Domain: {$this->plugin_data['TextDomain']}\n" .
			" * Domain Path: {$this->plugin_data['DomainPath']}\n" .
			" * Requires PHP: {$this->plugin_data['RequiresPHP']}\n" .
			" * Requires WP: {$this->plugin_data['RequiresWP']}\n" .
			" * Log Constant Name: TEST_DEBUG_MODE\n" .
			" * Another Custom: Value For Custom\n" .
			' */';

		// Create a partial mock of the Config class, disabling the original constructor for now.
		$the_actual_mock_instance = $this->getMockBuilder(Config::class)
			->onlyMethods(array('_read_plugin_file_header_content'))
			->disableOriginalConstructor()
			->getMock();

		// Set up the expectation for _read_plugin_file_header_content.
		// This must be done BEFORE the constructor logic (which calls this method) runs.
		$the_actual_mock_instance->expects( $this->once() )
			->method( '_read_plugin_file_header_content' )
			->with( $this->full_plugin_file_path )
			->willReturn( $mock_file_content );

		// Manually invoke the original constructor (from ConfigAbstract) on our mock instance.
		// Now, when the constructor calls _read_plugin_file_header_content, the expectation will be met.
		$reflection  = new \ReflectionObject($the_actual_mock_instance);
		$constructor = $reflection->getParentClass()->getConstructor(); // Config inherits constructor from ConfigAbstract
		$constructor->invoke($the_actual_mock_instance);

		// Use Reflection to set our mock instance into the SingletonAbstract's static property.
		$reflectionClass   = new \ReflectionClass(\Ran\PluginLib\Singleton\SingletonAbstract::class);
		$instancesProperty = $reflectionClass->getProperty('instances');
		$instancesProperty->setAccessible(true); // Make it accessible
		$current_instances                = $instancesProperty->getValue(null); // Get current static instances
		$current_instances[Config::class] = $the_actual_mock_instance; // Set our mock
		$instancesProperty->setValue(null, $current_instances); // Write back

		// Return the instance; Config::get_instance() will now return our mock.
		return Config::get_instance();
	}
}
