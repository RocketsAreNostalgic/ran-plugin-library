<?php
/**
 * Tests for RegisterOptions class.
 *
 * @package  Ran/PluginLib
 *
 * @uses \Ran\PluginLib\Util\Logger
 */

declare(strict_types = 1);

namespace Ran\PluginLib\Tests\Unit;

use Ran\PluginLib\Tests\Unit\PluginLibTestCase; // Use the new base class
use Ran\PluginLib\Config\ConfigAbstract; // Keep for type hinting if needed elsewhere
use Ran\PluginLib\Util\Logger; // Add use statement for the Logger class
use Ran\PluginLib\RegisterOptions;
use PHPUnit\Framework\MockObject\MockObject; // Add use statement for PHPUnit's MockObject
use WP_Mock;

/**
 * Tests for RegisterOptions class.
 *
 * @covers \Ran\PluginLib\RegisterOptions
 * @uses \Ran\PluginLib\Util\Logger
 * @uses \Ran\PluginLib\Config\ConfigAbstract
 * @uses \Ran\PluginLib\Singleton\SingletonAbstract
 */
final class RegisterOptionsTest extends PluginLibTestCase {
	/**
	 * The plugin data array.
	 *
	 * @var array<string, string>
	 */
	private array $plugin_data = array(
		// The text domain with spaces and dashes for underscores.
		'PluginOption' => 'ran_plugin',
	);

	private MockObject $logger_mock; // This will now correctly refer to PHPUnit\Framework\MockObject\MockObject

	/**
	 * Sets up the test environment before each test.
	 */
	public function setUp(): void {
		parent::setUp(); // This will call PluginLibTestCase::setUp()

		// Ensure the ConfigAbstract system is initialized and a concrete instance is registered.
		// This instance will be used by RegisterOptions when it calls ConfigAbstract::get_instance()
		// or when its get_logger() method tries to get the config instance.
		$this->get_and_register_concrete_config_instance();

		// Common logger mock for all tests in this class, can be overridden in specific tests
		$this->logger_mock = $this->createMock(Logger::class);
		$this->logger_mock->method('is_active')->willReturn(false); // Default to inactive to reduce log noise in tests
	}

	/**
	 * Tears down the test environment.
	 */
	public function tearDown(): void {
		// The parent::tearDown() from PluginLibTestCase will handle singleton cleanup and dummy file removal.
		parent::tearDown();
	}

	/**
	 * Test setting a single option and verify it's saved correctly.
	 *
	 * @covers \Ran\PluginLib\RegisterOptions::set_option
	 * @covers \Ran\PluginLib\RegisterOptions::get_options
	 * @covers \Ran\PluginLib\RegisterOptions::get_option
	 * @covers \Ran\PluginLib\RegisterOptions::__construct
	 * @uses \Ran\PluginLib\Config\Config::get_instance
	 * @uses \Ran\PluginLib\Config\ConfigAbstract::__construct
	 * @uses \Ran\PluginLib\Singleton\SingletonAbstract::get_instance
	 */
	public function test_set_single_option_and_verify_save(): void {
		$main_option_name    = 'test_plugin_settings';
		$option_key_to_set   = 'feature_x_enabled';
		$option_value_to_set = true;

		// Mock for RegisterOptions constructor's call to get_option for the main array.
		// It should be called once when the object is instantiated.
		WP_Mock::userFunction('get_option')
			->once()
			->with($main_option_name, array()) // Expects to load an empty array initially.
			->andReturn(array());

		// Mock for save_all_options' call to update_option.
		// This is called when set_option successfully updates the internal options array.
		$expected_options_array_to_save = array(
			$option_key_to_set => array('value' => $option_value_to_set, 'autoload_hint' => null)
		);
		WP_Mock::userFunction('update_option')
			->once()
			->with($main_option_name, $expected_options_array_to_save, true) // Assuming main_option_autoload defaults to true.
			->andReturnTrue();

		// Mock get_logger and is_active to prevent actual logging during tests if not specifically testing logging.
		// This assumes RegisterOptions extends ConfigAbstract and get_logger is accessible.
		// If direct mocking of get_logger is complex, ensure logger doesn't interfere or mock ConfigAbstract if it's a dependency.
		// For now, we'll assume the logger setup in ConfigAbstract is test-friendly or we can mock it on the instance.
		$mock_logger = $this->getMockBuilder(\Ran\PluginLib\Util\Logger::class)
			->disableOriginalConstructor()
			->getMock();
		$mock_logger->method('is_active')->willReturn(false);
		$mock_logger->method('debug'); // Called by set_option
		// $mock_logger->method('debug'); // if you need to expect debug calls.

		$options_manager = $this->getMockBuilder(RegisterOptions::class)
			->setConstructorArgs(array($main_option_name, array(), true)) // Full constructor args for RegisterOptions
			->onlyMethods(array('get_logger'))
			->addMethods(array('get_plugin_config'))
			->getMock();

		$options_manager->method('get_logger')->willReturn($mock_logger);
		$options_manager->method('get_plugin_config')->willReturn(array(
			'TextDomain'      => 'mock-text-domain',
			'LogConstantName' => 'MOCK_TEST_DEBUG_MODE',
			'LogRequestParam' => 'mock_test_debug_param',
			'PluginPath'      => $this->mock_plugin_file_path, // from PluginLibTestCase::setUp()
		));

		$result = $options_manager->set_option($option_key_to_set, $option_value_to_set);

		$this->assertTrue($result, 'set_option should return true on successful save.');

		// Verify internal state via get_options().
		$this->assertEquals(
			$expected_options_array_to_save,
			$options_manager->get_options(),
			'Internal options array does not match expected after set_option.'
		);

		// Verify retrieval of the specific option via get_option().
		$this->assertEquals(
			$option_value_to_set,
			$options_manager->get_option($option_key_to_set),
			'get_option did not return the correct value for the set option.'
		);
	}

	/**
	 * Test constructor with initial options when no pre-existing options are in the DB.
	 *
	 * @covers \Ran\PluginLib\RegisterOptions::__construct
	 * @covers \Ran\PluginLib\RegisterOptions::get_options
	 * @covers \Ran\PluginLib\RegisterOptions::get_option
	 * @uses \Ran\PluginLib\Config\ConfigAbstract::__construct
	 * @uses \Ran\PluginLib\Config\Config::get_instance
	 * @uses \Ran\PluginLib\Singleton\SingletonAbstract::get_instance
	 */
	public function test_constructor_with_initial_options_no_existing_in_db(): void {
		$main_option_name       = 'test_plugin_settings_constructor';
		$initial_options_to_set = array(
			'api_key'             => 'test_api_key_123',
			'feature_enabled'     => true,
			'item_limit'          => array('value' => 25, 'autoload_hint' => false), // Explicit structure
			'setting with spaces' => 'will be normalized'
		);

		// Expected structure after constructor processes initial_options and normalizes keys
		$expected_saved_options = array(
			'api_key'             => array('value' => 'test_api_key_123', 'autoload_hint' => null),
			'feature_enabled'     => array('value' => true, 'autoload_hint' => null),
			'item_limit'          => array('value' => 25, 'autoload_hint' => false),
			'setting_with_spaces' => array('value' => 'will be normalized', 'autoload_hint' => null)
		);

		// 1. Mock get_option for the constructor's initial load.
		//    Simulates no options existing in the database for this main_option_name.
		WP_Mock::userFunction('get_option')
			->once()
			->with($main_option_name, array())
			->andReturn(array());

		// 2. Mock update_option for the constructor's save operation.
		//    This should be called because initial_options are provided and they are all new.
		WP_Mock::userFunction('update_option')
			->once()
			->with($main_option_name, $expected_saved_options, true) // true for $main_option_autoload default
			->andReturnTrue();

		// Mock logger
		$mock_logger = $this->getMockBuilder(\Ran\PluginLib\Util\Logger::class)
			->disableOriginalConstructor()->getMock();
		$mock_logger->method('is_active')->willReturn(false);

		$options_manager = $this->getMockBuilder(RegisterOptions::class)
			// Pass main_option_name, initial_options, and main_option_autoload (true by default)
			->setConstructorArgs(array($main_option_name, $initial_options_to_set, true))
			->onlyMethods(array('get_logger'))      // For method in RegisterOptions
			->addMethods(array('get_plugin_config')) // For inherited method
			->getMock();
		$options_manager->method('get_logger')->willReturn($mock_logger);
		$options_manager->method('get_plugin_config')->willReturn(array(
			'TextDomain'      => 'mock-text-domain',
			'LogConstantName' => 'MOCK_TEST_DEBUG_MODE',
			'LogRequestParam' => 'mock_test_debug_param',
			// Ensure $this->plugin_file is set up in the test class if used here
			// 'PluginPath'      => $this->plugin_file,
		));

		// Assertions
		$this->assertEquals(
			$expected_saved_options,
			$options_manager->get_options(),
			'get_options() did not return the expected initial options array.'
		);

		// Verify individual options can be retrieved correctly
		$this->assertEquals('test_api_key_123', $options_manager->get_option('api_key'));
		$this->assertTrue($options_manager->get_option('feature_enabled'));
		$this->assertEquals(25, $options_manager->get_option('item_limit'));
		$this->assertEquals('will be normalized', $options_manager->get_option('setting_with_spaces'));
		$this->assertEquals('will be normalized', $options_manager->get_option('setting with spaces'), 'Should retrieve with original key with spaces too.');
		$this->assertFalse($options_manager->get_option('non_existent_key', false), 'Should return default for non-existent key.');
	}

	/**
	 * Test setting multiple options sequentially and verify the combined state and saves.
	 *
	 * @covers \Ran\PluginLib\RegisterOptions::set_option
	 * @covers \Ran\PluginLib\RegisterOptions::get_options
	 * @covers \Ran\PluginLib\RegisterOptions::get_option
	 * @covers \Ran\PluginLib\RegisterOptions::__construct
	 * @uses \Ran\PluginLib\Config\Config::get_instance
	 * @uses \Ran\PluginLib\Config\ConfigAbstract::__construct
	 * @uses \Ran\PluginLib\Singleton\SingletonAbstract::get_instance
	 */
	public function test_set_multiple_options_sequentially(): void {
		$main_option_name = $this->plugin_data['PluginOption']; // Using 'ran_plugin' from test class property

		$option1_key = 'first_setting';
		$option1_val = 'initial_value';

		$option2_key      = 'second setting with spaces'; // Will be normalized
		$option2_val      = array('detail1' => 'info1', 'detail2' => true);
		$option2_autoload = true;

		$option3_key      = 'third_setting';
		$option3_val      = 12345;
		$option3_autoload = false;

		// Expected states for update_option calls
		$expected_options_after_set1 = array(
			$option1_key => array('value' => $option1_val, 'autoload_hint' => null),
		);
		$expected_options_after_set2 = array(
			$option1_key                 => array('value' => $option1_val, 'autoload_hint' => null),
			'second_setting_with_spaces' => array('value' => $option2_val, 'autoload_hint' => $option2_autoload),
		);
		$expected_options_after_set3 = array(
			$option1_key                 => array('value' => $option1_val, 'autoload_hint' => null),
			'second_setting_with_spaces' => array('value' => $option2_val, 'autoload_hint' => $option2_autoload),
			$option3_key                 => array('value' => $option3_val, 'autoload_hint' => $option3_autoload),
		);

		// Mock for constructor's get_option
		WP_Mock::userFunction('get_option')
			->once()
			->with($main_option_name, array())
			->andReturn(array());

		// Mock update_option to be called sequentially with the evolving options array
		WP_Mock::userFunction('update_option')
			->once()
			->with($main_option_name, $expected_options_after_set1, true)
			->andReturnTrue();
		WP_Mock::userFunction('update_option')
			->once()
			->with($main_option_name, $expected_options_after_set2, true)
			->andReturnTrue();
		WP_Mock::userFunction('update_option')
			->once()
			->with($main_option_name, $expected_options_after_set3, true)
			->andReturnTrue();

		// Mock logger
		$mock_logger = $this->getMockBuilder(\Ran\PluginLib\Util\Logger::class)
			->disableOriginalConstructor()->getMock();
		$mock_logger->method('is_active')->willReturn(false);
		$mock_logger->method('debug'); // Called by set_option

		$options_manager = $this->getMockBuilder(RegisterOptions::class)
			->setConstructorArgs(array($main_option_name, array(), true)) // Full constructor args
			->onlyMethods(array('get_logger'))
			->addMethods(array('get_plugin_config'))
			->getMock();

		$options_manager->method('get_logger')->willReturn($mock_logger);
		$options_manager->method('get_plugin_config')->willReturn(array(
			'TextDomain'      => 'mock-text-domain',
			'LogConstantName' => 'MOCK_TEST_DEBUG_MODE',
			'LogRequestParam' => 'mock_test_debug_param',
			'PluginPath'      => $this->mock_plugin_file_path,
		));

		// Set options sequentially
		$options_manager->set_option($option1_key, $option1_val);
		$options_manager->set_option($option2_key, $option2_val, $option2_autoload);
		$options_manager->set_option($option3_key, $option3_val, $option3_autoload);

		// Assert final state of all options
		$this->assertEquals(
			$expected_options_after_set3,
			$options_manager->get_options(),
			'Final options array does not match expected after multiple set_option calls.'
		);

		// Assert individual option retrieval
		$this->assertEquals($option1_val, $options_manager->get_option($option1_key));
		$this->assertEquals($option2_val, $options_manager->get_option($option2_key)); // Test with original key with spaces
		$this->assertEquals($option2_val, $options_manager->get_option('second_setting_with_spaces')); // Test with normalized key
		$this->assertEquals($option3_val, $options_manager->get_option($option3_key));
	}

	/**
	 * Test constructor merges initial options with existing DB options.
	 *
	 * @covers \Ran\PluginLib\RegisterOptions::__construct
	 * @covers \Ran\PluginLib\RegisterOptions::get_options
	 * @uses \Ran\PluginLib\Config\Config::get_instance
	 * @uses \Ran\PluginLib\Singleton\SingletonAbstract::get_instance
	 */
	public function test_constructor_merges_initial_options_with_existing_db_options(): void {
		$main_option_name    = 'test_plugin_settings_merge';
		$existing_db_options = array(
			'existing_key' => array('value' => 'db_value', 'autoload_hint' => true),
			'overlap_key'  => array('value' => 'db_overlap_value', 'autoload_hint' => null),
		);
		$initial_constructor_options = array(
			'new_key'     => array('value' => 'new_value', 'autoload_hint' => false),
			'overlap_key' => array('value' => 'initial_overlap_value', 'autoload_hint' => true), // This should overwrite DB
		);

		$expected_merged_options = array(
			'existing_key' => array('value' => 'db_value', 'autoload_hint' => true),
			'overlap_key'  => array('value' => 'initial_overlap_value', 'autoload_hint' => true),
			'new_key'      => array('value' => 'new_value', 'autoload_hint' => false),
		);

		// Mock for constructor's get_option to return existing DB options.
		WP_Mock::userFunction('get_option')
			->once()
			->with($main_option_name, array())
			->andReturn($existing_db_options);

		// Mock for update_option, expecting the merged set.
		WP_Mock::userFunction('update_option')
			->once()
			->with($main_option_name, $expected_merged_options, true) // Assuming main_option_autoload defaults to true.
			->andReturnTrue();

		$options_manager = $this->getMockBuilder(RegisterOptions::class)
			->setConstructorArgs(array($main_option_name, $initial_constructor_options, true))
			->onlyMethods(array('get_logger'))
			->getMock();
		$options_manager->method('get_logger')->willReturn($this->logger_mock);

		$this->assertEquals($expected_merged_options, $options_manager->get_options(), 'Options after constructor do not match expected merged options.');
	}

	/**
	 * Test refresh_options reloads options from the database.
	 *
	 * @covers \Ran\PluginLib\RegisterOptions::refresh_options
	 * @covers \Ran\PluginLib\RegisterOptions::get_options
	 * @uses \Ran\PluginLib\Config\Config::get_instance
	 * @uses \Ran\PluginLib\Singleton\SingletonAbstract::get_instance
	 */
	public function test_refresh_options_reloads_from_database(): void {
		$main_option_name     = 'test_plugin_settings_refresh';
		$initial_db_options   = array('initial_key' => array('value' => 'initial_value', 'autoload_hint' => null));
		$refreshed_db_options = array('refreshed_key' => array('value' => 'refreshed_value', 'autoload_hint' => true));

		// Mock for constructor's get_option.
		WP_Mock::userFunction('get_option')
			->once()
			->with($main_option_name, array())
			->andReturn($initial_db_options);

		$options_manager = $this->getMockBuilder(RegisterOptions::class)
			->setConstructorArgs(array($main_option_name, array(), true)) // No initial options for this test, rely on DB.
			->onlyMethods(array('get_logger'))
			->getMock();
		$options_manager->method('get_logger')->willReturn($this->logger_mock);

		// Verify initial state.
		$this->assertEquals($initial_db_options, $options_manager->get_options(), 'Initial options do not match.');

		// Mock for refresh_options' call to get_option.
		WP_Mock::userFunction('get_option')
			->once()
			->with($main_option_name, array())
			->andReturn($refreshed_db_options);

		$options_manager->refresh_options();

		$this->assertEquals($refreshed_db_options, $options_manager->get_options(), 'Options after refresh do not match expected refreshed options.');
	}

	/**
	 * Test get_option returns default value when key does not exist.
	 *
	 * @covers \Ran\PluginLib\RegisterOptions::get_option
	 * @uses \Ran\PluginLib\Config\Config::get_instance
	 * @uses \Ran\PluginLib\Singleton\SingletonAbstract::get_instance
	 */
	public function test_get_option_returns_default_when_key_not_exists(): void {
		$main_option_name = 'test_plugin_settings_default';
		$default_value    = 'this_is_a_default';

		// Mock for constructor's get_option to return an empty array.
		WP_Mock::userFunction('get_option')
			->once()
			->with($main_option_name, array())
			->andReturn(array());

		$options_manager = $this->getMockBuilder(RegisterOptions::class)
			->setConstructorArgs(array($main_option_name, array(), true))
			->onlyMethods(array('get_logger'))
			->getMock();
		$options_manager->method('get_logger')->willReturn($this->logger_mock);

		$retrieved_value = $options_manager->get_option('non_existent_key', $default_value);
		$this->assertSame($default_value, $retrieved_value, 'get_option did not return the default value for a non-existent key.');
	}

	/**
	 * Test set_option correctly handles explicit autoload hints.
	 *
	 * @covers \Ran\PluginLib\RegisterOptions::set_option
	 * @covers \Ran\PluginLib\RegisterOptions::get_options
	 * @uses \Ran\PluginLib\Config\Config::get_instance
	 * @uses \Ran\PluginLib\Singleton\SingletonAbstract::get_instance
	 */
	public function test_set_option_with_explicit_autoload_hints(): void {
		$main_option_name = 'test_plugin_settings_autoload';

		// Mock for constructor's get_option.
		WP_Mock::userFunction('get_option')
			->once()
			->with($main_option_name, array())
			->andReturn(array());

		// Mock update_option to check autoload hints.
		WP_Mock::userFunction('update_option')
			->times(3) // Expect it to be called for each set_option call.
			->withArgs(function ($option_name, $options_array, $autoload_main) use ($main_option_name) {
				$this->assertSame($main_option_name, $option_name);
				$this->assertTrue($autoload_main); // Main option group autoload
				if (isset($options_array['key_autoload_true'])) {
					$this->assertTrue($options_array['key_autoload_true']['autoload_hint']);
				} elseif (isset($options_array['key_autoload_false'])) {
					$this->assertFalse($options_array['key_autoload_false']['autoload_hint']);
				} elseif (isset($options_array['key_autoload_null'])) {
					$this->assertNull($options_array['key_autoload_null']['autoload_hint']);
				}
				return true; // Important for withArgs to return true if args match.
			})
			->andReturnTrue();

		$options_manager = $this->getMockBuilder(RegisterOptions::class)
			->setConstructorArgs(array($main_option_name, array(), true))
			->onlyMethods(array('get_logger'))
			->getMock();
		$options_manager->method('get_logger')->willReturn($this->logger_mock);

		$options_manager->set_option('key_autoload_true', 'value1', true);
		$options_manager->set_option('key_autoload_false', 'value2', false);
		$options_manager->set_option('key_autoload_null', 'value3', null);

		$final_options = $options_manager->get_options();
		$this->assertTrue($final_options['key_autoload_true']['autoload_hint']);
		$this->assertFalse($final_options['key_autoload_false']['autoload_hint']);
		$this->assertNull($final_options['key_autoload_null']['autoload_hint']);
	}

	/**
	 * Test update_option works as an alias for set_option.
	 *
	 * @covers \Ran\PluginLib\RegisterOptions::update_option
	 * @covers \Ran\PluginLib\RegisterOptions::set_option
	 * @covers \Ran\PluginLib\RegisterOptions::save_all_options
	 * @uses \Ran\PluginLib\Config\Config::get_instance
	 * @uses \Ran\PluginLib\Singleton\SingletonAbstract::get_instance
	 */
	public function test_update_option_works_as_alias_for_set_option(): void {
		$main_option_name = 'test_plugin_settings_update_alias';
		$option_key       = 'alias_test_key';
		$option_value     = 'alias_test_value';
		$option_autoload  = true;

		$expected_options_to_save = array(
			$option_key => array('value' => $option_value, 'autoload_hint' => $option_autoload),
		);

		// Mock for constructor's get_option.
		WP_Mock::userFunction('get_option')
			->once()
			->with($main_option_name, array())
			->andReturn(array());

		// Mock for update_option (WordPress function) called by save_all_options.
		WP_Mock::userFunction('update_option')
			->once()
			->with($main_option_name, $expected_options_to_save, true) // Assuming main_option_autoload is true.
			->andReturnTrue();

		$options_manager = $this->getMockBuilder(RegisterOptions::class)
			->setConstructorArgs(array($main_option_name, array(), true))
			->onlyMethods(array('get_logger'))
			->getMock();
		$options_manager->method('get_logger')->willReturn($this->logger_mock);

		// Call the update_option method.
		$result = $options_manager->update_option($option_key, $option_value, $option_autoload);

		$this->assertTrue($result, 'update_option should return true on success.');
		$this->assertEquals($expected_options_to_save, $options_manager->get_options(), 'Options array after update_option does not match expected.');
	}

	/**
	 * Test get_logger returns an already initialized logger instance.
	 *
	 * @covers \Ran\PluginLib\RegisterOptions::get_logger
	 * @uses \Ran\PluginLib\Config\Config::get_instance
	 * @uses \Ran\PluginLib\Singleton\SingletonAbstract::get_instance
	 */
	public function test_get_logger_returns_existing_logger(): void {
		$main_option_name = 'test_plugin_settings_existing_logger';

		// Mock for constructor's get_option.
		WP_Mock::userFunction('get_option')
			->once()
			->with($main_option_name, array())
			->andReturn(array());

		$options_manager = new RegisterOptions($main_option_name);

		// Manually set a logger instance using reflection to simulate it being pre-initialized.
		$reflection      = new \ReflectionClass($options_manager);
		$logger_property = $reflection->getProperty('logger');
		$logger_property->setAccessible(true);
		$logger_property->setValue($options_manager, $this->logger_mock);

		// Access the protected get_logger method using reflection.
		$method = $reflection->getMethod('get_logger');
		$method->setAccessible(true);
		$retrieved_logger = $method->invoke($options_manager);

		$this->assertSame($this->logger_mock, $retrieved_logger, 'get_logger did not return the pre-existing logger instance.');
	}

	/**
	 * Test get_logger retrieves logger from Config if not already set and caches it.
	 *
	 * @covers \Ran\PluginLib\RegisterOptions::get_logger
	 * @uses \Ran\PluginLib\Config\Config
	 * @uses \Ran\PluginLib\Singleton\SingletonAbstract
	 */
	public function test_get_logger_retrieves_from_config_if_not_set(): void {
		$main_option_name = 'test_plugin_settings_config_logger';

		// Config is primed with $this->logger_mock from setUp().

		WP_Mock::userFunction('get_option') // For the constructor
			->once()
			->with($main_option_name, array())
			->andReturn(array());

		$options_manager = new RegisterOptions($main_option_name); // Constructor runs

		// Verify that the internal logger property is now set to the logger from Config,
		// implying the constructor called get_logger().
		$reflection      = new \ReflectionClass($options_manager);
		$logger_property = $reflection->getProperty('logger');
		$logger_property->setAccessible(true);
		$internal_logger_after_construction = $logger_property->getValue($options_manager);

		$this->assertInstanceOf(\Ran\PluginLib\Util\Logger::class, $internal_logger_after_construction, 'Internal logger should be a Logger instance after construction.');

		// Now, explicitly call get_logger() again and verify it returns the same (cached) instance.
		$method = $reflection->getMethod('get_logger');
		$method->setAccessible(true);
		$retrieved_logger_explicit_call = $method->invoke($options_manager);

		$this->assertSame($internal_logger_after_construction, $retrieved_logger_explicit_call, 'Explicit call to get_logger() did not return the cached instance, which should have been set by constructor.');
	}
}
