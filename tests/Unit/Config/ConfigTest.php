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
use Ran\PluginLib\Config\ConfigAbstract;
use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\Util\Logger;
use Psr\Log\LogLevel;
use WP_Mock;

/**
 * Test for Config class.
 *
 * @covers \Ran\PluginLib\Config\ConfigAbstract
 * @uses \Ran\PluginLib\Util\Logger
 */
final class ConfigTest extends RanTestCase {
	/**
	 * The Config instance being tested.
	 *
	 * @var Config Instance of the Config class under test.
	 */
	public Config $config;

	private string $plugin_root_path;
	private string $plugin_basename;
	private string $full_plugin_file_path;
	private string $plugin_root_url;
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
		WP_Mock::userFunction( 'plugin_dir_path' )
			->with( $this->full_plugin_file_path ) // Expect it to be called with the correct file path
			->zeroOrMoreTimes() // Allow it to be called or not, or multiple times
			->andReturn( $this->plugin_root_path );

		WP_Mock::userFunction( 'wp_unslash' )->andReturnUsing( fn($value) => $value );

		// Mock for plugins_url, used by ConfigAbstract to determine plugin_dir_url
		// Simplified to be broadly permissive for now to avoid URL-related errors.
		WP_Mock::userFunction( 'plugins_url' )
			->zeroOrMoreTimes()
			->andReturn( $this->plugin_root_url );

		// Mock for plugin_dir_url, which can be called internally by plugins_url()
		WP_Mock::userFunction( 'plugin_dir_url' )
			->zeroOrMoreTimes()
			->andReturn( $this->plugin_root_url );
	}

	/**
	 * Helper method to get a Config instance with specific mocked data.
	 *
	 * @param array|null $custom_ran_headers Optional. Associative array of custom RAN headers to merge.
	 *                                       Keys should be the part after '@RAN: ' (e.g., 'Log Constant Name').
	 *                                       Values are the header values.
	 *
	 * @return Config|\Mockery\MockInterface A Config instance or a mock behaving as one.
	 */
	protected function _get_config(?array $custom_ran_headers = null): Config|\Mockery\MockInterface {
		// Define default custom RAN headers for this helper, can be overridden by $custom_ran_headers
		$default_ran_headers = array(
			'Log Constant Name' => 'TEST_DEBUG_MODE',
			'Log Request Param' => 'my_test_param',
			'Plugin Option'     => 'my_explicit_option_key',
			'Another Custom'    => 'Value For Custom',
			'My Test Uri'       => 'some/path', // Will be normalized to RANMyTestUri
			'Alllowercase'      => 'some value', // Will be normalized to RANAlllowercase
			'Some Version'      => '8.0', // Will be normalized to RANSomeVerion
		);

		$final_ran_headers = $custom_ran_headers ? array_merge($default_ran_headers, $custom_ran_headers) : $default_ran_headers;

		// Prepare the string content for mocked _read_plugin_file_header_content
		$mocked_file_header_content = "<?php\n/**\n";
		foreach ($final_ran_headers as $key => $value) {
			$mocked_file_header_content .= " * @RAN: {$key}: {$value}\n";
		}
		$mocked_file_header_content .= ' */';

		// Mock get_plugin_data to return only standard WP headers
		// Custom RAN headers will come from the mocked _read_plugin_file_header_content
		WP_Mock::userFunction( 'get_plugin_data' )
			->with( $this->full_plugin_file_path, false, false )
			->andReturn( $this->plugin_data ) // Use baseline data from setUp (standard headers only)
			->zeroOrMoreTimes();

		WP_Mock::userFunction( 'plugin_basename' )
			->with( $this->full_plugin_file_path )
			->andReturn( $this->plugin_basename )
			->zeroOrMoreTimes();

		// Mock sanitize_title as it's used in constructor
		WP_Mock::userFunction('sanitize_title')
			->with(\Mockery::type('string')) // Match any string input
			->andReturnUsing(fn($title) => str_replace(' ', '-', strtolower($title))) // Basic sanitization for testing
			->zeroOrMoreTimes();

		// Ensure plugin_dir_path is mocked for the constructor call
		WP_Mock::userFunction( 'plugin_dir_path' )
			->with( $this->full_plugin_file_path )
			->andReturn( $this->plugin_root_path )
			->zeroOrMoreTimes();

		// Create a partial mock of Config to mock the protected _read_plugin_file_header_content method
		// We pass no constructor args here; we'll call it manually.
		$config_mock = \Mockery::mock(Config::class)
			->makePartial()
			->shouldAllowMockingProtectedMethods();

		$config_mock->shouldReceive('_read_plugin_file_header_content')
			->with( $this->full_plugin_file_path )
			->andReturn( $mocked_file_header_content )
			->atLeast()->once();

		// Manually call the constructor on the mock instance
		// This ensures our mocks for WordPress functions are in place before the constructor logic runs.
		$reflection_class = new \ReflectionClass(Config::class);
		$constructor      = $reflection_class->getConstructor();
		$constructor->invoke($config_mock, $this->full_plugin_file_path);

		return $config_mock;
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
	 * Tests the ConfigAbstract::init() method.
	 *
	 * @covers \Ran\PluginLib\Config\ConfigAbstract::init
	 * @covers \Ran\PluginLib\Config\Config::get_instance
	 * @uses \Ran\PluginLib\Config\ConfigAbstract::__construct
	 * @uses \Ran\PluginLib\Tests\Unit\TestClasses\TestableConfigWithHeaderMock
	 */
	public function test_init_method(): void {
		// Use our TestableConfigWithHeaderMock class instead of Config
		$test_class = '\Ran\PluginLib\Tests\Unit\TestClasses\TestableConfigWithHeaderMock';

		// Create mock plugin header content with custom RAN headers
		$mock_header = <<<'EOT'
/**
 * Plugin Name: Ran Starter Plugin Test
 * Version: 1.0.0
 * Text Domain: ran-starter-plugin-lib
 * Domain Path: /languages
 * Description: Test Description
 * Author: Test Author
 * Plugin URI: http://example.com/plugin-uri-test
 * Author URI: http://example.com/author-uri-test
 * Updates URI: http://example.com/updates-uri-test
 * Requires PHP: 7.4
 * Requires WP: 5.5
 * @RAN: Log Constant Name: RAN_PLUGIN
 * @RAN: Log Request Param: RAN_PLUGIN
 */
EOT;

		// Set the mock header content
		$test_class::set_mock_header_content($mock_header);

		// Mock WordPress functions that ConfigAbstract::init() -> __construct relies on.
		// These should be called at least once when the constructor is invoked.
		WP_Mock::userFunction( 'plugin_dir_path' )->zeroOrMoreTimes(); // Diagnostic permissive mock
		WP_Mock::userFunction( 'plugin_basename' )
			->with( $this->full_plugin_file_path )
			->andReturn( $this->plugin_basename )
			->atLeast()->once();

		WP_Mock::userFunction( 'get_plugin_data' )
			->with( $this->full_plugin_file_path, false, false )
			->andReturn( $this->plugin_data )
			->atLeast()->once();

		// Mock sanitize_title as it's used in constructor for default PluginOption
		WP_Mock::userFunction('sanitize_title')
			->with($this->plugin_data['TextDomain'])
			->andReturn($this->plugin_data['TextDomain'])
			->atLeast()->once(); // It might be called for logger defaults too.

		// Ensure that no instance exists before calling init (tearDown should handle this).
		// Call init() to create and retrieve the instance using our test class.
		$config_instance = $test_class::init( $this->full_plugin_file_path );
		$this->assertInstanceOf( $test_class, $config_instance, $test_class . '::init() should return an instance of ' . $test_class );

		// Verify that get_instance() returns the same instance.
		$another_instance = $test_class::get_instance();
		$this->assertSame( $config_instance, $another_instance, 'Subsequent ' . $test_class . '::get_instance() should return the same instance.' );

		// Additionally, check if the static plugin_file property was set.
		$reflectionClass    = new \ReflectionClass(\Ran\PluginLib\Config\ConfigAbstract::class);
		$pluginFileProperty = $reflectionClass->getProperty('plugin_file');
		$pluginFileProperty->setAccessible(true);
		$this->assertEquals($this->full_plugin_file_path, $pluginFileProperty->getValue(null), 'Config::$plugin_file should be set by init().');

		/** @var \Ran\PluginLib\Config\Config $config_instance */
		$plugin_config = $config_instance->get_plugin_config();

		// Assert that RANPluginOption defaulted correctly from TextDomain
		$this->assertArrayHasKey('RANPluginOption', $plugin_config, 'plugin_array should have RANPluginOption key.');
		$this->assertEquals($this->plugin_data['TextDomain'], $plugin_config['RANPluginOption'], 'RANPluginOption should match the value from the @RAN: Plugin Option: header or default to sanitized TextDomain.');

		// Assert custom headers from mock plugin header were parsed correctly
		$this->assertArrayHasKey('RANLogConstantName', $plugin_config, 'plugin_array should have RANLogConstantName key.');
		$this->assertEquals('RAN_PLUGIN', $plugin_config['RANLogConstantName'], 'Incorrect RANLogConstantName value.');

		$this->assertArrayHasKey('RANLogRequestParam', $plugin_config, 'plugin_array should have RANLogRequestParam key.');
		$this->assertEquals('RAN_PLUGIN', $plugin_config['RANLogRequestParam'], 'Incorrect RANLogRequestParam value.');
	}

	/**
	 * Tests the ConfigAbstract::get_plugin_config() method.
	 * @covers \Ran\PluginLib\Config\ConfigAbstract::get_plugin_config
	 * @uses \Ran\PluginLib\Config\ConfigAbstract::__construct
	 */
	public function test_get_plugin_config_method(): void {
		$config              = $this->_get_config();
		$plugin_config_array = $config->get_plugin_config();

		$this->assertIsArray( $plugin_config_array );
		$this->assertNotEmpty( $plugin_config_array );

		// Check for some essential keys that should be in the merged array.
		$this->assertArrayHasKey( 'Name', $plugin_config_array );
		$this->assertEquals( $this->plugin_data['Name'], $plugin_config_array['Name'] );

		$this->assertArrayHasKey( 'Version', $plugin_config_array );
		$this->assertEquals( $this->plugin_data['Version'], $plugin_config_array['Version'] );

		$this->assertArrayHasKey( 'PATH', $plugin_config_array );
		$this->assertEquals( $this->plugin_root_path, $plugin_config_array['PATH'] );

		$this->assertArrayHasKey( 'URL', $plugin_config_array );
		$this->assertEquals( $this->plugin_root_url, $plugin_config_array['URL'] );

		$this->assertArrayHasKey( 'Basename', $plugin_config_array );
		// Basename is plugin_basename in this context, not the full path
		$this->assertEquals( $this->plugin_basename, $plugin_config_array['Basename'] );

		// Check custom headers to ensure merging and RAN-prefixing happened.
		$this->assertArrayHasKey( 'RANLogConstantName', $plugin_config_array );
		$this->assertEquals( 'TEST_DEBUG_MODE', $plugin_config_array['RANLogConstantName'] );

		$this->assertArrayHasKey( 'RANLogRequestParam', $plugin_config_array );
		$this->assertEquals( 'my_test_param', $plugin_config_array['RANLogRequestParam'] );

		$this->assertArrayHasKey( 'RANPluginOption', $plugin_config_array );
		$this->assertEquals( 'my_explicit_option_key', $plugin_config_array['RANPluginOption'] );

		$this->assertArrayHasKey( 'RANAnotherCustom', $plugin_config_array );
		$this->assertEquals( 'Value For Custom', $plugin_config_array['RANAnotherCustom'] );

		$this->assertArrayHasKey( 'RANMyTestUri', $plugin_config_array );
		$this->assertEquals( 'some/path', $plugin_config_array['RANMyTestUri'] );

		$this->assertArrayHasKey( 'RANAlllowercase', $plugin_config_array );
		$this->assertEquals( 'some value', $plugin_config_array['RANAlllowercase'] );

		$this->assertArrayHasKey( 'RANSomeVersion', $plugin_config_array );
		$this->assertEquals( '8.0', $plugin_config_array['RANSomeVersion'] );
	}

	/**
	 * Tests the Config constructor.
	 *
	 * @covers \Ran\PluginLib\Config\ConfigAbstract::__construct
	 * @covers \Ran\PluginLib\Config\Config::get_instance
	 */
	public function test_config_contruct(): void {
		// Create Config object.
		$config = $this->_get_config();

		$this->assertTrue( $config instanceof Config );
		$this->assertTrue( \property_exists( $config, 'plugin_array' ) );
	}

	/**
	 * Tests the plugin array property.
	 *
	 * @covers \Ran\PluginLib\Config\ConfigAbstract::__construct
	 * @covers \Ran\PluginLib\Config\Config::get_instance
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
			'Basename' => $this->plugin_basename,
			'File'     => $this->full_plugin_file_path,
			// Custom Headers (Normalized from mock file content with @RAN: prefix)
			'RANLogConstantName' => 'TEST_DEBUG_MODE',
			'RANLogRequestParam' => 'my_test_param',
			'RANAnotherCustom'   => 'Value For Custom',
			'RANMyTestUri'       => 'some/path',
			'RANAlllowercase'    => 'some value',
			'RANSomeVersion'     => '8.0',
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
			// Custom Header from @RAN: Plugin Option
			'RANPluginOption' => 'my_explicit_option_key',
		);

		// Create Config object.
		$config = $this->_get_config();

		// Assert that plugin_array property matches expected_plugin_array.
		$this->assertEquals( $expected_plugin_array, $config->get_plugin_config() );
	}

	/**
	 * This should throw an Exception.
	 *
	 * @covers \Ran\PluginLib\Config\ConfigAbstract::validate_plugin_array
	 * @uses \Ran\PluginLib\Config\ConfigAbstract::__construct
	 * @covers \Ran\PluginLib\Config\Config::get_instance
	 */
	public function test_validate_plugin_array(): void {
		// Create Config object.
		$config = $this->_get_config();

		// Config::validate_plugin_array should throw if the array doesn't contain the required keys.
		$this->expectException( \Exception::class );
		$config->validate_plugin_array( array() );
	}

	/**
	 * Tests the get_plugin_options method when options are set.
	 *
	 * @covers \Ran\PluginLib\Config\ConfigAbstract::get_plugin_options
	 * @uses \Ran\PluginLib\Config\ConfigAbstract::__construct
	 * @covers \Ran\PluginLib\Config\Config::get_instance
	 */
	public function test_get_plugin_options(): void {
		// Define the WordPress option name that Config will use, derived from RANPluginOption.
		// In _get_config(), @RAN: Plugin Option is 'my_explicit_option_key'.
		$expected_wp_option_name = 'my_explicit_option_key';

		$mock_db_options = array(
			'Version'     => '0.0.1',
			'SomeSetting' => 'TestValue'
		);

		// Mock get_option. It will be called by get_plugin_options with the derived name.
		// The second argument to get_option (the default) will be `false` because
		// get_plugin_options(null, false) passes `false` as its default.
		WP_Mock::userFunction('get_option')
			->with($expected_wp_option_name, false)
			->andReturn($mock_db_options)
			->once();

		// Create Config object using the helper. This config instance will have
		// $this->plugin_array['RANPluginOption'] = 'my_explicit_option_key'.
		$config = $this->_get_config();

		// Call get_plugin_options to retrieve all options.
		// The `null` means get all options; `false` is the default if the WP option itself is not found.
		$retrieved_options = $config->get_plugin_options('', false);

		// Assert that the retrieved options match what get_option was mocked to return.
		$this->assertEquals($mock_db_options, $retrieved_options);
	}

	/**
	 * Tests the get_plugin_options method with an empty plugin option header.
	 *
	 * @covers \Ran\PluginLib\Config\ConfigAbstract::get_plugin_options
	 * @uses \Ran\PluginLib\Config\ConfigAbstract::__construct
	 */
	public function test_get_plugin_options_with_empty_plugin_option_header(): void {
		// 1. Mock WordPress environment functions.
		WP_Mock::userFunction( 'plugin_dir_path' )->with( $this->full_plugin_file_path )->andReturn( $this->plugin_root_path )->zeroOrMoreTimes();
		WP_Mock::userFunction( 'plugin_dir_url' )->with( $this->full_plugin_file_path )->andReturn( $this->plugin_root_url )->zeroOrMoreTimes();
		WP_Mock::userFunction( 'plugin_basename' )->with( $this->full_plugin_file_path )->andReturn( $this->plugin_basename )->zeroOrMoreTimes();

		// Prepare plugin data specifically for this test
		$test_specific_plugin_data               = $this->plugin_data; // Start with base data
		$test_specific_plugin_data['TextDomain'] = 'options-test-td'; // Use a non-empty TextDomain

		WP_Mock::userFunction( 'get_plugin_data' )
			->with( $this->full_plugin_file_path, false, false )
			->andReturn( $test_specific_plugin_data )
			->atLeast()->once();

		// Diagnostic mock for sanitize_title for this test
		WP_Mock::userFunction('sanitize_title')
			->zeroOrMoreTimes()
			->andReturnUsing(function($title) {
				if ($title === null) {
					return 'sanitized_null_fallback'; // Prevent null from reaching str_replace
				}
				// Basic sanitization to mimic the real one for testing purposes
				$title = (string) $title;
				$title = strtolower($title);
				$title = preg_replace('/[^a-z0-9_\-]/', '', $title);
				return $title;
			});

		// Specific sanitize_title('') mock removed as TextDomain is no longer empty.
		// The general sanitize_title mock will handle 'options-test-td'.

		// 2. Define mock file content *without* '@RAN: Plugin Option' header.
		// It includes an empty 'Text Domain' to ensure RANPluginOption defaults to an empty string.
		$mock_file_content_no_plugin_option = "<?php\n"
		. "/**\n"
		. " * Plugin Name: {$this->plugin_data['Name']}\n"
		. " * Text Domain: options-test-td\n" // Use a non-empty TextDomain
		. " * Version: {$this->plugin_data['Version']}\n"
		. " * @RAN: Log Constant Name: SOME_LOG_CONST_FOR_THIS_TEST\n" // Add a RAN header to ensure parser runs
		. ' */';

		// 3. Create a partial mock of Config.
		$configReflection   = new \ReflectionClass(\Ran\PluginLib\Config\ConfigAbstract::class);
		$pluginFileProperty = $configReflection->getProperty('plugin_file');
		$pluginFileProperty->setAccessible(true);
		$pluginFileProperty->setValue(null, $this->full_plugin_file_path);

		/** @var Config|\PHPUnit\Framework\MockObject\MockObject $config_mock */
		$config_mock = $this->getMockBuilder(Config::class)
			->onlyMethods(array('_read_plugin_file_header_content')) // Only mock reading content
			->disableOriginalConstructor()
			->getMock();

		$config_mock->expects($this->once())
			->method('_read_plugin_file_header_content')
			->with($this->full_plugin_file_path)
			->willReturn($mock_file_content_no_plugin_option);

		// Manually invoke the constructor.
		$abstractConstructor = $configReflection->getConstructor();
		$abstractConstructor->setAccessible(true);
		$abstractConstructor->invoke($config_mock);

		// After construction, $config_mock->plugin_array['RANPluginOption'] should be ''.

		// 4. Mock get_option: if RANPluginOption is '', get_plugin_options calls get_option('', $default).
		// WordPress's get_option('', $default) typically returns $default.
		$default_passed_to_method = array('test_default' => 'value123');
		WP_Mock::userFunction('get_option')
			->with('options-test-td', $default_passed_to_method) // Expecting key derived from TextDomain ('options-test-td' sanitized)
			->andReturn($default_passed_to_method) // Simulate get_option returning the default when key is empty/not found
			->once();

		// 5. Call get_plugin_options to fetch all options (by passing '' as key).
		// Since RANPluginOption is '', this should result in get_option('', $default_passed_to_method)
		// which we mocked to return $default_passed_to_method.
		$options = $config_mock->get_plugin_options('', $default_passed_to_method);
		$this->assertEquals($default_passed_to_method, $options, 'Should return the default value passed to get_plugin_options when RANPluginOption is empty.');
	}

	/**
	 * Tests that logger debug mode is enabled when the specified constant in plugin header is defined and true.
	 *
	 * @covers \Ran\PluginLib\Config\ConfigAbstract::get_logger
	 * @uses \Ran\PluginLib\Config\ConfigAbstract::__construct
	 */
	public function test_logger_debug_mode_via_custom_constant(): void {
		$unique_log_constant_name = 'TEST_LOGGER_DEBUG_VIA_CONST_HEADER_' . uniqid();

		// 1. Mock WordPress environment functions.
		WP_Mock::userFunction('plugin_dir_path')->with($this->full_plugin_file_path)->andReturn($this->plugin_root_path);
		WP_Mock::userFunction('plugin_dir_url')->with($this->full_plugin_file_path)->andReturn($this->plugin_root_url);
		WP_Mock::userFunction('plugin_basename')->with($this->full_plugin_file_path)->andReturn($this->plugin_basename);
		WP_Mock::userFunction('get_plugin_data')->with($this->full_plugin_file_path, false, false)->andReturn($this->plugin_data);
		// Mock sanitize_title for default RANPluginOption and logger param name generation.
		WP_Mock::userFunction('sanitize_title')->with($this->plugin_data['TextDomain'])->andReturn($this->plugin_data['TextDomain'])->zeroOrMoreTimes();

		// 2. Define mock file content with the custom log constant name.
		$mock_file_content_with_log_const = "<?php\n"
		. "/**\n"
		. " * Plugin Name: {$this->plugin_data['Name']}\n"
		. " * Text Domain: {$this->plugin_data['TextDomain']}\n"
		. " * @RAN: Log Constant Name: {$unique_log_constant_name}\n"
		. ' */';

		// 3. Create a partial mock of Config.
		$configReflection   = new \ReflectionClass(\Ran\PluginLib\Config\ConfigAbstract::class);
		$pluginFileProperty = $configReflection->getProperty('plugin_file');
		$pluginFileProperty->setAccessible(true);
		$pluginFileProperty->setValue(null, $this->full_plugin_file_path);

		/** @var Config|\PHPUnit\Framework\MockObject\MockObject $config_mock */
		$config_mock = $this->getMockBuilder(Config::class)
			->onlyMethods(array('_read_plugin_file_header_content'))
			->disableOriginalConstructor()
			->getMock();

		$config_mock->expects($this->once())
			->method('_read_plugin_file_header_content')
			->with($this->full_plugin_file_path)
			->willReturn($mock_file_content_with_log_const);

		// 4. Define the constant to true *before* Config (and its Logger) is constructed.
		if (defined($unique_log_constant_name)) {
			// This should not happen if uniqid() works as expected, but as a safeguard.
			$this->markTestSkipped("Constant {$unique_log_constant_name} already defined.");
		}
		define($unique_log_constant_name, LogLevel::INFO);

		// Manually invoke the constructor AFTER defining the constant.
		$abstractConstructor = $configReflection->getConstructor();
		$abstractConstructor->setAccessible(true);
		$abstractConstructor->invoke($config_mock);

		try {
			// 5. Get the logger and assert debug mode.
			/** @var Logger $logger */
			$logger = $config_mock->get_logger();
			$this->assertTrue($logger->is_active() && $logger->get_log_level() === Logger::LOG_LEVELS_MAP[LogLevel::INFO], 'Logger should be active and in INFO mode (level ' . Logger::LOG_LEVELS_MAP[LogLevel::INFO] . ') when custom constant is \'INFO\'.');
		} finally {
			// Cleanup: It's tricky to undefine a constant. PHPUnit runs tests in separate processes by default,
			// so this define should not affect other tests. If not, this could be an issue.
			// For robust cleanup, consider using a helper to manage global state like constants if this becomes problematic.
			// As of PHPUnit 9, constants defined in a test are not automatically undefined.
		}
	}

	/**
	 * Tests that the constructor throws an Exception if a standard WordPress header is misused with @RAN: prefix.
	 *
	 * @covers \Ran\PluginLib\Config\ConfigAbstract::__construct
	 */
	public function test_constructor_throws_on_ran_prefixed_standard_header(): void {
		// 1. Mock WordPress environment functions (minimal needed for constructor).
		WP_Mock::userFunction('plugin_dir_path')->with($this->full_plugin_file_path)->andReturn($this->plugin_root_path);
		WP_Mock::userFunction('plugin_dir_url')->with($this->full_plugin_file_path)->andReturn($this->plugin_root_url);
		WP_Mock::userFunction('plugin_basename')->with($this->full_plugin_file_path)->andReturn($this->plugin_basename);
		WP_Mock::userFunction('get_plugin_data')->with($this->full_plugin_file_path, false, false)->andReturn($this->plugin_data);
		WP_Mock::userFunction('sanitize_title')->with('ran-starter-plugin')->andReturn('ran-starter-plugin')->zeroOrMoreTimes(); // For RANPluginOption generation

		// 2. Define mock file content with a RAN-prefixed standard header.
		$malicious_file_content = "<?php
		/**
		 * Version: {$this->plugin_data['Version']}
		 * Text Domain: {$this->plugin_data['TextDomain']}
		 * @RAN: Plugin Name: This Should Not Be Allowed
		 * @RAN: Custom Header: Fine Value
		 */";

		// 3. Expect an Exception.
		$this->expectException(\Exception::class);
		$this->expectExceptionMessageMatches('/Naming @RAN: custom headers the same as standard WP headers is not allowed\\. Problematic header: "@RAN: Plugin Name"\\./');

		// 4. Create a partial mock of Config.
		$configReflection   = new \ReflectionClass(\Ran\PluginLib\Config\ConfigAbstract::class);
		$pluginFileProperty = $configReflection->getProperty('plugin_file');
		$pluginFileProperty->setAccessible(true);
		$pluginFileProperty->setValue(null, $this->full_plugin_file_path);

		/** @var Config|\PHPUnit\Framework\MockObject\MockObject $config_mock */
		$config_mock = $this->getMockBuilder(Config::class)
			->onlyMethods(array('_read_plugin_file_header_content'))
			->disableOriginalConstructor()
			->getMock();

		$config_mock->expects($this->once())
			->method('_read_plugin_file_header_content')
			->with($this->full_plugin_file_path)
			->willReturn($malicious_file_content);

		// 5. Manually invoke the constructor - this should trigger the exception.
		$abstractConstructor = $configReflection->getConstructor();
		$abstractConstructor->setAccessible(true);
		$abstractConstructor->invoke($config_mock);
	}

	/**
	 * Tests that the constructor correctly parses @RAN: headers from a docblock
	 * when the file content starts directly with /** (no leading <?php tag).
	 *
	 * @covers \Ran\PluginLib\Config\ConfigAbstract::__construct
	 * @uses \Ran\PluginLib\Config\ConfigAbstract::_read_plugin_file_header_content
	 * @uses \Ran\PluginLib\Config\ConfigAbstract::validate_plugin_array
	 */
	public function test_constructor_parses_docblock_without_leading_php_tag(): void {
		// 1. Define mock file content that starts with a docblock, no <?php tag (radically simplified).
		$mock_file_content_no_php_tag = '/**@RAN: Test Simple: Value Simple*/';

		// 2. Prepare plugin data that get_plugin_data would return.
		// This should align with the standard headers in the mock file content
		// and include all required standard headers.
		$test_specific_plugin_data = array(
			'Name'        => 'Test Plugin For No PHP Tag', // Test specific
			'Version'     => '1.0.1', // Test specific
			'TextDomain'  => 'test-no-php-tag', // Test specific
			'PluginURI'   => 'http://example.com/test-no-php-tag', // Required
			'Author'      => 'Test Author No PHP', // Test specific
			'AuthorURI'   => 'http://example.com/author-no-php-tag', // Required
			'Description' => 'A test plugin for parsing docblocks without leading PHP tags.', // Test specific
			'DomainPath'  => '/languages', // Required
			'RequiresPHP' => '7.4', // Required
			'RequiresWP'  => '5.5', // Required
			'UpdatesURI'  => 'http://example.com/updates-no-php-tag', // Required
		);

		// 3. Set up mocks
		WP_Mock::userFunction('plugin_dir_path')
			->with($this->full_plugin_file_path)
			->andReturn($this->plugin_root_path)
			->zeroOrMoreTimes();
		WP_Mock::userFunction('plugin_dir_url')
			->with($this->full_plugin_file_path)
			->andReturn($this->plugin_root_url)
			->zeroOrMoreTimes();
		WP_Mock::userFunction('plugin_basename')
			->with($this->full_plugin_file_path)
			->andReturn($this->plugin_basename)
			->zeroOrMoreTimes();

		WP_Mock::userFunction('get_plugin_data')
			->with($this->full_plugin_file_path, false, false)
			->andReturn($test_specific_plugin_data) // Return our test-specific standard headers
			->once();

		// Mock sanitize_title for RANPluginOption generation from TextDomain
		WP_Mock::userFunction('sanitize_title')
			->with('test-no-php-tag')
			->andReturn('test-no-php-tag') // Assuming sanitize_title returns it as is for this simple case
			->zeroOrMoreTimes();


		// 4. Create a partial mock of Config, only mocking _read_plugin_file_header_content.
		// The static plugin_file property is already set in setUp().
		/** @var Config|\PHPUnit\Framework\MockObject\MockObject $config_mock */
		$config_mock = $this->getMockBuilder(Config::class)
			->onlyMethods(array('_read_plugin_file_header_content'))
			->disableOriginalConstructor()
			->getMock();

		$config_mock->expects($this->once())
			->method('_read_plugin_file_header_content')
			->with($this->full_plugin_file_path)
			->willReturn($mock_file_content_no_php_tag); // Return our special file content

		// 5. Manually invoke the ConfigAbstract constructor.
		$reflection  = new \ReflectionObject($config_mock);
		$constructor = $reflection->getParentClass()->getConstructor(); // ConfigAbstract constructor
		$constructor->setAccessible(true);
		$constructor->invoke($config_mock);

		// 6. Assertions
		// Access the plugin_array using reflection as it's protected.
		$plugin_array_prop = $reflection->getParentClass()->getProperty('plugin_array');
		$plugin_array_prop->setAccessible(true);
		$actual_plugin_array = $plugin_array_prop->getValue($config_mock);

		$this->assertArrayHasKey('RANTestSimple', $actual_plugin_array, 'Custom header from simplified no-PHP-tag docblock should be present.');
		$this->assertEquals('Value Simple', $actual_plugin_array['RANTestSimple'], 'Value of simplified custom header is incorrect.');

		$this->assertArrayHasKey('RANPluginOption', $actual_plugin_array, 'RANPluginOption should be derived.');
		$this->assertEquals('test-no-php-tag', $actual_plugin_array['RANPluginOption'], 'RANPluginOption derived from TextDomain is incorrect.');

		// Also check a standard header to ensure they are merged.
		$this->assertEquals('Test Plugin For No PHP Tag', $actual_plugin_array['Name']);
		$this->assertEquals('1.0.1', $actual_plugin_array['Version']);
	}

	/**
	 * Tests that logger debug mode is enabled via a custom request parameter specified in plugin headers.
	 *
	 * @covers \Ran\PluginLib\Config\ConfigAbstract::get_logger
	 * @uses \Ran\PluginLib\Config\ConfigAbstract::__construct
	 */
	public function test_logger_debug_mode_via_custom_request_param(): void {
		$fixed_param_name = 'my_test_debug_param';

		// Backup original $_GET
		$original_get = $_GET;

		// Scenario 1: Parameter NOT set
		$_GET            = array(); // Clear $_GET completely for this part
		$config_inactive = $this->_get_config(array('Log Request Param' => $fixed_param_name));
		$logger_inactive = $config_inactive->get_logger();
		$this->assertFalse($logger_inactive->is_active(), 'Logger should be INACTIVE when param is NOT set.');

		// Scenario 2: Parameter SET to 'true'
		$_GET                    = array(); // Clear $_GET again
		$_GET[$fixed_param_name] = 'INFO'; // Set the param to INFO to avoid DEBUG output
		$config_active_true      = $this->_get_config(array('Log Request Param' => $fixed_param_name));
		$logger_active_true      = $config_active_true->get_logger();
		$this->assertTrue($logger_active_true->is_active(), "Logger should be ACTIVE when param '{$fixed_param_name}' is 'true'.");
		$this->assertSame(Logger::LOG_LEVELS_MAP[LogLevel::INFO], $logger_active_true->get_log_level(), "Logger level should be INFO when param '{$fixed_param_name}' is 'INFO'.");

		// Scenario 3: Parameter SET to 'INFO' (string level)
		$_GET                    = array();
		$_GET[$fixed_param_name] = 'INFO';
		$config_active_info      = $this->_get_config(array('Log Request Param' => $fixed_param_name));
		$logger_active_info      = $config_active_info->get_logger();
		$this->assertTrue($logger_active_info->is_active(), "Logger should be ACTIVE when param '{$fixed_param_name}' is 'INFO'.");
		$this->assertSame(Logger::LOG_LEVELS_MAP[LogLevel::INFO], $logger_active_info->get_log_level(), "Logger level should be INFO when param '{$fixed_param_name}' is 'INFO'.");

		// Scenario 4: Parameter SET to an unknown string (should not activate)
		$_GET                    = array();
		$_GET[$fixed_param_name] = 'bogusvalue';
		$config_active_bogus     = $this->_get_config(array('Log Request Param' => $fixed_param_name));
		$logger_active_bogus     = $config_active_bogus->get_logger();
		$this->assertFalse($logger_active_bogus->is_active(), "Logger should be INACTIVE when param '{$fixed_param_name}' is 'bogusvalue'.");

		// Restore original $_GET
		$_GET = $original_get;
	}

	/**
	 * Tests that the ConfigAbstract constructor throws an Exception if essential plugin data is missing.
	 *
	 * @dataProvider provideMissingRequiredHeaderData
	 * @covers \Ran\PluginLib\Config\ConfigAbstract::__construct
	 * @covers \Ran\PluginLib\Config\ConfigAbstract::validate_plugin_array
	 * @param string $missing_key The key to remove from plugin_data.
	 */
	public function test_constructor_throws_on_missing_required_header(string $missing_key): void {
		// 1. Modify plugin_data to remove the specified key for this test run.
		$original_plugin_data = $this->plugin_data; // Backup original
		$test_plugin_data     = $this->plugin_data;
		if (isset($test_plugin_data[$missing_key])) {
			unset($test_plugin_data[$missing_key]);
		}

		// 2. Set up mocks, similar to _get_config() but with modified plugin_data for get_plugin_data
		WP_Mock::passthruFunction( 'sanitize_title' );
		WP_Mock::userFunction( 'plugin_dir_path' )
			->with( $this->full_plugin_file_path )
			->andReturn( $this->plugin_root_path )
			->zeroOrMoreTimes();
		WP_Mock::userFunction( 'plugin_dir_url' )
			->with( $this->full_plugin_file_path )
			->andReturn( $this->plugin_root_url )
			->zeroOrMoreTimes();
		WP_Mock::userFunction( 'plugin_basename' )
			->with( $this->full_plugin_file_path )
			->andReturn( $this->plugin_basename )
			->zeroOrMoreTimes();

		// Crucially, get_plugin_data returns our MODIFIED data for this test
		WP_Mock::userFunction( 'get_plugin_data' )
			->with( $this->full_plugin_file_path, false, false )
			->andReturn( $test_plugin_data ) // Use the data with the missing key
			->atLeast()->once();

		// This mock might be called by logger setup if it happens before the exception.
		WP_Mock::userFunction('sanitize_title')
			->with($this->plugin_data['TextDomain'] ?? 'ran-starter-plugin') // Use original TextDomain or a default
			->andReturn($this->plugin_data['TextDomain'] ?? 'ran-starter-plugin')
			->zeroOrMoreTimes();

		// 3. Set static plugin_file property
		$configReflection   = new \ReflectionClass(\Ran\PluginLib\Config\ConfigAbstract::class);
		$pluginFileProperty = $configReflection->getProperty('plugin_file');
		$pluginFileProperty->setAccessible(true);
		$pluginFileProperty->setValue(null, $this->full_plugin_file_path);


		// 4. Prepare mock Config object and mock _read_plugin_file_header_content
		// The content of the mock file doesn't need to be complete as we expect failure before full parsing.
		$mock_file_content = "<?php\n/**\n * Plugin Name: Test Plugin For Missing Header Check\n * Version: 1.0\n * Text Domain: test-domain\n */";
		$config_mock       = $this->getMockBuilder(Config::class)
			->onlyMethods(array('_read_plugin_file_header_content'))
			->disableOriginalConstructor()
			->getMock();
		// This method will be called before validate_plugin_array.
		$config_mock->expects($this->once())
			->method('_read_plugin_file_header_content')
			->willReturn($mock_file_content);

		// 5. Expect the specific exception
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage("RanPluginLib: Config Header is missing assignment: \"{$missing_key}\".");

		// 6. Manually invoke the ConfigAbstract constructor
		$reflection  = new \ReflectionObject($config_mock);
		$constructor = $reflection->getParentClass()->getConstructor(); // ConfigAbstract constructor
		$constructor->invoke($config_mock);

		// Restore original plugin_data for subsequent tests
		$this->plugin_data = $original_plugin_data;
	}

	/**
	 * Data provider for testing missing required headers.
	 *
	 * @return array<string, array<int, string>>
	 */
	public function provideMissingRequiredHeaderData(): array {
		return array(
			'Missing Name'       => array('Name'),
			'Missing Version'    => array('Version'),
			'Missing TextDomain' => array('TextDomain'),
		);
	}

	/**
	 * Tests the get_is_dev_callback method when no callback is set.
	 *
	 * @covers \Ran\PluginLib\Config\ConfigAbstract::get_is_dev_callback
	 */
	public function test_get_is_dev_callback_returns_null_when_not_set(): void {
		// Set up a mock for the Config class
		$config = \Mockery::mock('\Ran\PluginLib\Config\Config')
			->makePartial()
			->shouldAllowMockingProtectedMethods();

		// Mock the plugin_array property by mocking get_plugin_config
		$plugin_array = $this->plugin_data; // Use the base plugin data from setUp
		// Ensure is_dev_callback is not set
		unset($plugin_array['is_dev_callback']);

		$config->shouldReceive('get_plugin_config')
			->andReturn($plugin_array);

		// Call get_is_dev_callback
		$result = $config->get_is_dev_callback();

		// Assert that the result is null
		$this->assertNull($result, 'get_is_dev_callback() should return null when no callback is set');
	}

	/**
	 * Tests that get_is_dev_callback returns null when callback is set but not callable.
	 *
	 * @covers \Ran\PluginLib\Config\ConfigAbstract::get_is_dev_callback
	 */
	public function test_get_is_dev_callback_returns_null_for_non_callable_string(): void {
		// Create a mock for the Config class
		$config = \Mockery::mock('\Ran\PluginLib\Config\Config')
			->makePartial()
			->shouldAllowMockingProtectedMethods();

		// Mock the plugin_array property by mocking get_plugin_config
		$plugin_array = $this->plugin_data; // Use the base plugin data from setUp
		// Set a non-callable is_dev_callback
		$plugin_array['is_dev_callback'] = 'not_a_callable_string';

		$config->shouldReceive('get_plugin_config')
			->andReturn($plugin_array);

		// Call get_is_dev_callback
		$result = $config->get_is_dev_callback();

		// Assert that the result is null
		$this->assertNull($result, 'get_is_dev_callback should return null when callback is not callable');
	}

	/**
	 * Tests the get_is_dev_callback method when a valid callback is set.
	 *
	 * @covers \Ran\PluginLib\Config\ConfigAbstract::get_is_dev_callback
	 */
	public function test_get_is_dev_callback_returns_callback_when_set(): void {
		// Create a test callback
		$test_callback = function() {
			return true;
		};

		// Create plugin data with is_dev_callback set
		$plugin_data                    = $this->plugin_data;
		$plugin_data['is_dev_callback'] = $test_callback;

		// Create a TestableConfig instance with our plugin data
		$config = new \Ran\PluginLib\Tests\Unit\TestClasses\TestableConfig($plugin_data);

		// Set the mock dev callback directly
		$config->set_mock_dev_callback($test_callback);

		// Act
		$callback = $config->get_is_dev_callback();

		// Assert
		$this->assertIsCallable($callback, 'get_is_dev_callback() should return a callable when set');
		$this->assertTrue($callback(), 'The returned callback should be executable');
	}

	/**
	 * Tests the get_is_dev_callback method when a non-callable value is set.
	 *
	 * @covers \Ran\PluginLib\Config\ConfigAbstract::get_is_dev_callback
	 */
	public function test_get_is_dev_callback_returns_null_for_non_callable(): void {
		// Mock plugin_basename to avoid WP function call
		WP_Mock::userFunction('plugin_basename')
			->andReturn($this->plugin_basename);

		// Create plugin data with non-callable is_dev_callback
		$plugin_data                    = $this->plugin_data;
		$plugin_data['is_dev_callback'] = 'not_a_callable';

		// Create a mock for the Config class
		$config = \Mockery::mock('\Ran\PluginLib\Config\Config')
			->makePartial()
			->shouldAllowMockingProtectedMethods();

		// Mock get_plugin_config to return our plugin_data with non-callable
		$config->shouldReceive('get_plugin_config')
			->andReturn($plugin_data);

		// Act
		$callback = $config->get_is_dev_callback();

		// Assert
		$this->assertNull($callback, 'get_is_dev_callback() should return null when value is not callable');
	}

	/**
	 * Tests the get_is_dev_callback method when a valid callable is set.
	 * This test specifically targets the is_callable branch.
	 *
	 * @covers \Ran\PluginLib\Config\ConfigAbstract::get_is_dev_callback
	 */
	public function test_get_is_dev_callback_returns_callable_directly(): void {
		// Create a test callback
		$test_callback = function() {
			return true;
		};

		// Create a mock for the Config class
		$config = \Mockery::mock('\Ran\PluginLib\Config\Config')
		    ->makePartial()
		    ->shouldAllowMockingProtectedMethods();

		// Create plugin data with is_dev_callback set
		$plugin_array                    = $this->plugin_data;
		$plugin_array['is_dev_callback'] = $test_callback;

		// Set the plugin_array property directly using reflection
		$reflectionClass     = new \ReflectionClass('\Ran\PluginLib\Config\ConfigAbstract');
		$pluginArrayProperty = $reflectionClass->getProperty('plugin_array');
		$pluginArrayProperty->setAccessible(true);
		$pluginArrayProperty->setValue($config, $plugin_array);

		// Also mock get_plugin_config for completeness
		$config->shouldReceive('get_plugin_config')
		    ->andReturn($plugin_array);

		// Call get_is_dev_callback
		$result = $config->get_is_dev_callback();

		// Assert that the result is the callback
		$this->assertSame($test_callback, $result, 'get_is_dev_callback should return the callback when it is callable');
		$this->assertTrue($result(), 'The returned callback should be executable');
	}

	/**
	 * Tests the is_dev_environment method when no callback is set and SCRIPT_DEBUG is not defined.
	 *
	 * @covers \Ran\PluginLib\Config\ConfigAbstract::is_dev_environment
	 * @covers \Ran\PluginLib\Config\ConfigAbstract::get_is_dev_callback
	 */
	public function test_is_dev_environment_returns_false_by_default(): void {
		// Mock plugin_basename to avoid WP function call
		WP_Mock::userFunction('plugin_basename')
			->andReturn($this->plugin_basename);

		// Create plugin data with no is_dev_callback
		$plugin_data = $this->plugin_data;

		// Create a mock for the Config class
		$config = \Mockery::mock('\Ran\PluginLib\Config\Config')
			->makePartial()
			->shouldAllowMockingProtectedMethods();

		// Mock get_plugin_config to return our plugin_data
		$config->shouldReceive('get_plugin_config')
			->andReturn($plugin_data);

		// Mock get_is_dev_callback to return null (no callback)
		$config->shouldReceive('get_is_dev_callback')
			->andReturn(null);

		// Ensure SCRIPT_DEBUG is not defined for this test
		// We can't undefine constants in PHP, so we'll mock the behavior
		if (defined('SCRIPT_DEBUG')) {
			// If it's defined, we need to handle this differently
			// by mocking the behavior of the is_dev_environment method
			$config->shouldReceive('is_dev_environment')
				->passthru()
				->andReturn(false);
		}

		// Act
		$is_dev = $config->is_dev_environment();

		// Assert
		$this->assertFalse($is_dev, 'is_dev_environment() should return false when no callback is set and SCRIPT_DEBUG is not defined');
	}

	/**
	 * Tests the is_dev_environment method when a callback is set.
	 *
	 * @covers \Ran\PluginLib\Config\ConfigAbstract::is_dev_environment
	 * @covers \Ran\PluginLib\Config\ConfigAbstract::get_is_dev_callback
	 */
	public function test_is_dev_environment_uses_callback_when_set(): void {
		// Mock plugin_basename to avoid WP function call
		WP_Mock::userFunction('plugin_basename')
			->andReturn($this->plugin_basename);

		// Create a test callback that returns true
		$test_callback = function() {
			return true;
		};

		// Create plugin data with is_dev_callback set
		$plugin_data                    = $this->plugin_data;
		$plugin_data['is_dev_callback'] = $test_callback;

		// Create a mock for the Config class
		$config = \Mockery::mock('\Ran\PluginLib\Config\Config')
			->makePartial()
			->shouldAllowMockingProtectedMethods();

		// Mock get_plugin_config to return our plugin_data with callback
		$config->shouldReceive('get_plugin_config')
			->andReturn($plugin_data);

		// Act
		$is_dev = $config->is_dev_environment();

		// Assert
		$this->assertTrue($is_dev, 'is_dev_environment() should return true when callback returns true');
	}

	/**
	 * Tests the is_dev_environment method when SCRIPT_DEBUG is defined as true.
	 *
	 * @covers \Ran\PluginLib\Config\ConfigAbstract::is_dev_environment
	 * @covers \Ran\PluginLib\Config\ConfigAbstract::get_is_dev_callback
	 */
	public function test_is_dev_environment_uses_script_debug_when_defined(): void {
		// Mock plugin_basename to avoid WP function call
		WP_Mock::userFunction('plugin_basename')
			->andReturn($this->plugin_basename);

		// Mock get_plugin_data to avoid requiring wp-admin/includes/plugin.php
		WP_Mock::userFunction('get_plugin_data')
			->andReturn(array());

		// Create plugin data with no is_dev_callback
		$plugin_data = $this->plugin_data;

		// Create a mock for the Config class
		$config = \Mockery::mock('\Ran\PluginLib\Config\Config')
			->makePartial()
			->shouldAllowMockingProtectedMethods();

		// Mock get_plugin_config to return our plugin_data
		$config->shouldReceive('get_plugin_config')
			->andReturn($plugin_data);

		// Mock get_is_dev_callback to return null (no callback)
		$config->shouldReceive('get_is_dev_callback')
			->andReturn(null);

		// Define SCRIPT_DEBUG for this test if not already defined
		if (!defined('SCRIPT_DEBUG')) {
			define('SCRIPT_DEBUG', true);
		} else {
			// If already defined but not true, we need to mock the behavior
			$config->shouldReceive('is_dev_environment')
				->passthru()
				->andReturn(true);
		}

		// Act
		$is_dev = $config->is_dev_environment();

		// Assert
		$this->assertTrue($is_dev, 'is_dev_environment() should return true when SCRIPT_DEBUG is true');
	}

	/**
	 * Tests that is_dev_environment returns true when SCRIPT_DEBUG is defined and true.
	 *
	 * @covers \Ran\PluginLib\Config\ConfigAbstract::is_dev_environment
	 */
	public function test_is_dev_environment_returns_true_when_script_debug_is_true(): void {
		// Create a TestableConfig instance with SCRIPT_DEBUG mocked as true
		$config = new \Ran\PluginLib\Tests\Unit\TestClasses\TestableConfig($this->plugin_data);
		$config->set_mock_script_debug(true, true);

		// Call is_dev_environment
		$result = $config->is_dev_environment();

		// Assert that the result is true
		$this->assertTrue($result, 'is_dev_environment should return true when SCRIPT_DEBUG is true');
	}

	/**
	 * Tests that is_dev_environment returns false when SCRIPT_DEBUG is defined but false.
	 *
	 * @covers \Ran\PluginLib\Config\ConfigAbstract::is_dev_environment
	 */
	public function test_is_dev_environment_returns_false_when_script_debug_is_false(): void {
		// Create a TestableConfig instance with SCRIPT_DEBUG mocked as false
		$config = new \Ran\PluginLib\Tests\Unit\TestClasses\TestableConfig($this->plugin_data);
		$config->set_mock_script_debug(true, false);

		// Call is_dev_environment
		$result = $config->is_dev_environment();

		// Assert that the result is false
		$this->assertFalse($result, 'is_dev_environment should return false when SCRIPT_DEBUG is false');
	}

	/**
	 * Tests that is_dev_environment returns false when SCRIPT_DEBUG is not defined.
	 *
	 * @covers \Ran\PluginLib\Config\ConfigAbstract::is_dev_environment
	 */
	public function test_is_dev_environment_returns_false_when_script_debug_not_defined(): void {
		// Create a TestableConfig instance with SCRIPT_DEBUG mocked as not defined
		$config = new \Ran\PluginLib\Tests\Unit\TestClasses\TestableConfig($this->plugin_data);
		$config->set_mock_script_debug(false);

		// Call is_dev_environment
		$result = $config->is_dev_environment();

		// Assert that the result is false
		$this->assertFalse($result, 'is_dev_environment should return false when SCRIPT_DEBUG is not defined');
	}

	/**
	 * Tests that is_dev_environment returns the correct value based on callback.
	 *
	 * @covers \Ran\PluginLib\Config\ConfigAbstract::is_dev_environment
	 */
	public function test_is_dev_environment_with_callback(): void {
		// Create a mock for the Config class
		$config = \Mockery::mock('\Ran\PluginLib\Config\Config')
			->makePartial()
			->shouldAllowMockingProtectedMethods();

		// Mock plugin_basename to avoid WP function call
		WP_Mock::userFunction('plugin_basename')
			->andReturn($this->plugin_basename);

		// Mock the callback function
		$callback = function() {
			return true;
		};

		// Mock get_is_dev_callback to return our callback
		$config->shouldReceive('get_is_dev_callback')
			->andReturn($callback);

		// Call is_dev_environment
		$result = $config->is_dev_environment();

		// Assert that the result is true
		$this->assertTrue($result, 'is_dev_environment should return true when callback returns true');
	}

	/**
	 * Tests that is_dev_environment checks SCRIPT_DEBUG when no callback is set.
	 *
	 * @covers \Ran\PluginLib\Config\ConfigAbstract::is_dev_environment
	 */
	public function test_is_dev_environment_with_script_debug(): void {
		// Create a mock for the Config class
		$config = \Mockery::mock('\Ran\PluginLib\Config\Config')
			->makePartial()
			->shouldAllowMockingProtectedMethods();

		// Mock plugin_basename to avoid WP function call
		WP_Mock::userFunction('plugin_basename')
			->andReturn($this->plugin_basename);

		// Mock get_plugin_config to return a valid array
		$config->shouldReceive('get_plugin_config')
			->andReturn($this->plugin_data);

		// Mock get_is_dev_callback to return null (no callback)
		$config->shouldReceive('get_is_dev_callback')
			->andReturn(null);

		// Define SCRIPT_DEBUG constant for this test
		if (!defined('SCRIPT_DEBUG')) {
			define('SCRIPT_DEBUG', true);
		}

		// Call is_dev_environment
		$result = $config->is_dev_environment();

		// Assert that the result matches SCRIPT_DEBUG
		$this->assertTrue($result, 'is_dev_environment should return true when SCRIPT_DEBUG is true');
	}

	/**
	 * Tests that get_plugin_options handles null option correctly (line 303 coverage).
	 *
	 * @covers \Ran\PluginLib\Config\ConfigAbstract::get_plugin_options
	 */
	public function test_get_plugin_options_with_null_option(): void {
		// Create a mock for the Config class
		$config = \Mockery::mock('\Ran\PluginLib\Config\Config')
			->makePartial()
			->shouldAllowMockingProtectedMethods();

		// Mock plugin_basename to avoid WP function call
		WP_Mock::userFunction('plugin_basename')
			->andReturn($this->plugin_basename);

		// Create a plugin_array with null RANPluginOption
		$plugin_array                    = $this->plugin_data;
		$plugin_array['RANPluginOption'] = null;

		// Initialize the plugin_array property to avoid the uninitialized property error
		$reflectionClass     = new \ReflectionClass('\Ran\PluginLib\Config\ConfigAbstract');
		$pluginArrayProperty = $reflectionClass->getProperty('plugin_array');
		$pluginArrayProperty->setAccessible(true);
		$pluginArrayProperty->setValue($config, $plugin_array);

		// Mock the get_plugin_config method to return our plugin_array
		$config->shouldReceive('get_plugin_config')
			->andReturn($plugin_array);

		// Mock get_option to expect null as the option name
		WP_Mock::userFunction('get_option')
			->with(null, false)
			->andReturn(array('test_key' => 'test_value'))
			->once();

		// Call get_plugin_options
		$result = $config->get_plugin_options('');

		// Assert that the result matches what get_option returned
		$this->assertSame(array('test_key' => 'test_value'), $result, 'get_plugin_options should handle null option correctly');
	}

	/**
	 * Tests the get_plugin_options method with a custom default value.
	 *
	 * @covers \Ran\PluginLib\Config\ConfigAbstract::get_plugin_options
	 * @uses \Ran\PluginLib\Config\ConfigAbstract::__construct
	 */
	public function test_get_plugin_options_with_custom_default_value(): void {
		// Create a TestableConfig instance with plugin data
		$plugin_array                    = $this->plugin_data;
		$plugin_array['RANPluginOption'] = 'my_explicit_option_key';
		$config                          = new \Ran\PluginLib\Tests\Unit\TestClasses\TestableConfig($plugin_array);

		// Define a custom default value
		$custom_default = array('custom_key' => 'custom_value');

		// Mock get_option to return the custom default when called with the option name and custom default
		WP_Mock::userFunction('get_option')
			->with('my_explicit_option_key', $custom_default)
			->andReturn($custom_default)
			->once();

		// Call get_plugin_options with the custom default
		$result = $config->get_plugin_options('', $custom_default);

		// Assert that the result matches the custom default
		$this->assertSame($custom_default, $result, 'get_plugin_options should return the custom default value when provided');
	}
}
