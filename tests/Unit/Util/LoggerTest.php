<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Util;

use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use WP_Mock;
use Psr\Log\LogLevel;

/**
 * Class LoggerTest
 *
 * @package Ran\PluginLib\Tests\Unit\Util
 * @covers \Ran\PluginLib\Util\Logger
 */
class LoggerTest extends PluginLibTestCase {
	private const DEFAULT_CONFIG = array(
	    'custom_debug_constant_name' => 'MY_PLUGIN_DEBUG_MODE',
	    'debug_request_param'        => 'my_plugin_debug',
	);

	/**
	 * Stores messages captured by the mock error log handler.
	 *
	 * @var array<string>
	 */
	private array $logged_messages = array();

	/**
	 * Set up the test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->logged_messages = array();
		$_GET                  = array();

		// Mock WordPress and global functions
		WP_Mock::userFunction('wp_unslash', array(
		    'return' => function($value) {
		    	return $value; // Simple pass-through for testing
		    }
		));
		WP_Mock::userFunction('wp_json_encode', array(
		    'return' => function($value) {
		    	return json_encode($value);
		    }
		));

		// Ensure WP_DEBUG_LOG is defined as true for tests that rely on it.
		$this->define_test_constant('WP_DEBUG_LOG', true);
	}

	/**
	 * Tear down the test environment.
	 */
	/**
	 * Mock error log handler to capture log messages.
	 */
	public function mock_error_log_handler(string $message): void {
		$this->logged_messages[] = $message;
	}

	public function tearDown(): void {
		$_GET = array(); // Reset $_GET superglobal
		WP_Mock::tearDown();
		// Reset any defined constants if possible, or note limitations
		// For now, we rely on unique constant names per test or careful sequencing
		foreach ($this->defined_constants as $const_name) {
			if (defined($const_name)) {
				// This is a known limitation. For robust testing, consider running tests
				// that define constants in separate processes or using a library like uopz.
			}
		}
		$this->defined_constants = array();
		parent::tearDown();
	}

	/**
	 * Helper to define a constant for a test and track it for cleanup.
	 */
	private function define_test_constant(string $name, $value): void {
		if (!defined($name)) {
			define($name, $value);
			$this->defined_constants[] = $name;
		}
	}

	/**
	 * @test
	 */
	public function test_constructor_handles_missing_constant_name_config(): void {
		$logger = new Logger(array());

		$reflection = new \ReflectionClass($logger);
		$property   = $reflection->getProperty('custom_debug_constant_name');
		$property->setAccessible(true);
		$this->assertSame('PLUGIN_LIB_DEBUG_MODE', $property->getValue($logger), 'custom_debug_constant_name should default to PLUGIN_LIB_DEBUG_MODE when config is empty.');

		$this->assertFalse($logger->is_active(), 'Logger should not be active with missing constant name.');
		$this->assertSame(0, $logger->get_log_level(), 'Log level should be 0 for missing constant name.');
	}

	/**
	 * @test
	 */
	public function test_constructor_handles_empty_constant_name_config(): void {
		$logger = new Logger(array('custom_debug_constant_name' => ''));

		$reflection = new \ReflectionClass($logger);
		$property   = $reflection->getProperty('custom_debug_constant_name');
		$property->setAccessible(true);
		$this->assertSame('', $property->getValue($logger), 'custom_debug_constant_name should be empty string when provided empty.');

		$this->assertFalse($logger->is_active(), 'Logger should not be active with empty constant name.');
		$this->assertSame(0, $logger->get_log_level(), 'Log level should be 0 for empty constant name.');
	}

	/**
	 * @test
	 */
	public function test_constructor_sets_default_debug_request_param_if_not_provided(): void {
		$config = array('custom_debug_constant_name' => 'MY_TEST_CONSTANT');
		$logger = new Logger($config);

		$reflection = new \ReflectionClass($logger);
		$property   = $reflection->getProperty('debug_request_param');
		$property->setAccessible(true);

		$this->assertEquals('MY_TEST_CONSTANT', $property->getValue($logger));
	}


	/**
	 * @test
	 */
	public function test_url_param_empty_string_activates_debug_level(): void {
		$_GET['test_log_param'] = '';
		$config                 = array(
		    'custom_debug_constant_name' => 'MY_TEST_CONSTANT',
		    'debug_request_param'        => 'test_log_param'
		);
		$logger = new Logger($config);

		$this->assertTrue($logger->is_active(), 'Logger should be active with empty string URL param.');
		$this->assertSame(Logger::LOG_LEVELS_MAP[LogLevel::DEBUG], $logger->get_log_level(), 'Log level should be DEBUG for empty string URL param.');
	}

	/**
	 * @test
	 */
	public function test_url_param_valid_level_name_activates_correct_level(): void {
		$_GET['test_log_param'] = 'error'; // Test with a lowercase valid level name
		$config                 = array(
		    'custom_debug_constant_name' => 'MY_TEST_CONSTANT',
		    'debug_request_param'        => 'test_log_param'
		);
		$logger = new Logger($config);

		$this->assertTrue($logger->is_active(), 'Logger should be active with URL param set to a valid level name.');
		$this->assertSame(Logger::LOG_LEVELS_MAP[LogLevel::ERROR], $logger->get_log_level(), 'Log level should be ERROR for URL param "error".');
	}

	/**
	 * @test
	 */
	public function test_constant_empty_string_activates_debug_level(): void {
		$constant_name = 'MY_CONSTANT_FOR_EMPTY_STRING_TEST';
		$this->define_constant($constant_name, '');

		$config = array(
		    'custom_debug_constant_name' => $constant_name,
		);
		$logger = new Logger($config);

		$this->assertTrue($logger->is_active(), 'Logger should be active when constant is defined with an empty string.');
		$this->assertSame(Logger::LOG_LEVELS_MAP[LogLevel::DEBUG], $logger->get_log_level(), 'Log level should be DEBUG for empty string constant.');
	}

	/**
	 * Tests that defining a constant with a valid log level name activates logging at the correct level.
	 */
	public function test_constant_valid_level_name_activates_correct_level(): void {
		$constant_name = 'TEST_WARNING_LEVEL_VIA_CONST';
		$this->define_constant($constant_name, 'WARNING');

		$config = array(
		    'custom_debug_constant_name' => $constant_name,
		);
		$logger = new Logger($config);

		$this->assertTrue($logger->is_active(), 'Logger should be active with a valid level string constant.');
		$this->assertSame(Logger::LOG_LEVELS_MAP[LogLevel::WARNING], $logger->get_log_level(), 'Log level should be WARNING for "WARNING" string constant.');
		$this->assertSame('constant', $this->_get_protected_property_value($logger, 'activation_mode'), 'Activation mode should be "constant".');
	}

	/**
	 * Tests that providing a valid log level via URL parameter activates logging at the correct level.
	 */
	public function test_url_parameter_valid_level_name_activates_correct_level(): void {
		$param_name = self::DEFAULT_CONFIG['debug_request_param'];
		$level_name = LogLevel::INFO;

		// Temporarily set the GET parameter
		$_GET[$param_name] = $level_name;

		$logger = new Logger(self::DEFAULT_CONFIG);

		$this->assertTrue($logger->is_active(), 'Logger should be active when URL parameter is set to a valid level.');
		$this->assertSame(Logger::LOG_LEVELS_MAP[$level_name], $logger->get_log_level(), 'Logger effective log level should match the URL parameter.');

		// Assert activation mode
		$activation_mode = $this->_get_protected_property_value($logger, 'activation_mode');
		$this->assertSame('url', $activation_mode, 'Activation mode should be set to "url".');

		// Clean up the GET parameter
		unset($_GET[$param_name]);
	}

	/**
	 * Tests that providing a numeric log level activates the corresponding severity.
	 */
	public function test_url_parameter_numeric_level_activates_correct_level(): void {
		$param_name        = self::DEFAULT_CONFIG['debug_request_param'];
		$numeric_level     = (string) Logger::LOG_LEVELS_MAP[LogLevel::WARNING];
		$_GET[$param_name] = $numeric_level;

		$logger = new Logger(self::DEFAULT_CONFIG);

		$this->assertTrue($logger->is_active(), 'Logger should activate for numeric level input.');
		$this->assertSame(Logger::LOG_LEVELS_MAP[LogLevel::WARNING], $logger->get_log_level(), 'Numeric level should resolve to corresponding severity.');

		unset($_GET[$param_name]);
	}

	/**
	 * Tests that providing a boolean true value activates DEBUG level.
	 */
	public function test_url_parameter_true_value_activates_debug_level(): void {
		$param_name        = self::DEFAULT_CONFIG['debug_request_param'];
		$_GET[$param_name] = 'true';

		$logger = new Logger(self::DEFAULT_CONFIG);

		$this->assertTrue($logger->is_active(), 'Logger should activate for true-valued input.');
		$this->assertSame(Logger::LOG_LEVELS_MAP[LogLevel::DEBUG], $logger->get_log_level(), 'True value should normalize to DEBUG severity.');

		unset($_GET[$param_name]);
	}

	/**
	 * Tests that providing an invalid log level via URL parameter does not activate logging.
	 */
	public function test_url_parameter_invalid_level_name_does_not_activate_logger(): void {
		$param_name         = self::DEFAULT_CONFIG['debug_request_param'];
		$invalid_level_name = 'BOGUS_LEVEL';

		// Temporarily set the GET parameter
		$_GET[$param_name] = $invalid_level_name;

		$logger = new Logger(self::DEFAULT_CONFIG);

		$this->assertFalse($logger->is_active(), 'Logger should not be active when URL parameter is set to an invalid level.');
		$this->assertSame(0, $logger->get_log_level(), 'Logger effective log level should be 0 for an invalid URL parameter.');

		// Assert activation mode is an empty string
		$activation_mode = $this->_get_protected_property_value($logger, 'activation_mode');
		$this->assertSame('', $activation_mode, 'Activation mode should be an empty string for an invalid URL parameter.');

		// Clean up the GET parameter
		unset($_GET[$param_name]);
	}

	/**
	 * Tests that the debug() method logs a message when the logger is active and the level allows it.
	 */
	public function test_debug_method_logs_when_active_and_level_allows(): void {
		$constant_name = 'TEST_DEBUG_LOG_METHOD';
		$this->define_test_constant($constant_name, LogLevel::DEBUG);

		$config = array(
		    'custom_debug_constant_name' => $constant_name,
		    'error_log_handler'          => array($this, 'mock_error_log_handler'),
		);
		$logger = new Logger($config);

		$test_message         = 'This is a debug test message.';
		$expected_log_message = '[DEBUG] ' . $test_message;

		$logger->debug($test_message);

		$this->assertCount(1, $this->logged_messages, 'Expected one message to be logged.');
		$this->assertSame($expected_log_message, $this->logged_messages[0], 'Logged message content does not match.');
	}

	/**
	 * Tests that the info() method logs a message when the logger is active and the level allows it.
	 */
	public function test_info_method_logs_when_active_and_level_allows(): void {
		$constant_name = 'TEST_INFO_LOG_METHOD';
		$this->define_test_constant($constant_name, LogLevel::INFO);

		$config = array(
		    'custom_debug_constant_name' => $constant_name,
		    'error_log_handler'          => array($this, 'mock_error_log_handler'),
		);
		$logger = new Logger($config);

		$test_message         = 'This is an info test message.';
		$expected_log_message = '[INFO] ' . $test_message;

		$logger->info($test_message);

		$this->assertCount(1, $this->logged_messages, 'Expected one message to be logged.');
		$this->assertSame($expected_log_message, $this->logged_messages[0], 'Logged message content does not match.');
	}

	/**
	 * Tests that the notice() method logs a message when the logger is active and the level allows it.
	 */
	public function test_notice_method_logs_when_active_and_level_allows(): void {
		$constant_name = 'TEST_NOTICE_LOG_METHOD';
		$this->define_test_constant($constant_name, LogLevel::NOTICE);

		$config = array(
		    'custom_debug_constant_name' => $constant_name,
		    'error_log_handler'          => array($this, 'mock_error_log_handler'),
		);
		$logger = new Logger($config);

		$test_message         = 'This is a notice test message.';
		$expected_log_message = '[NOTICE] ' . $test_message;

		$logger->notice($test_message);

		$this->assertCount(1, $this->logged_messages, 'Expected one message to be logged.');
		$this->assertSame($expected_log_message, $this->logged_messages[0], 'Logged message content does not match.');
	}

	/**
	 * Tests that the warning() method logs a message when the logger is active and the level allows it.
	 */
	public function test_warning_method_logs_when_active_and_level_allows(): void {
		$constant_name = 'TEST_WARNING_LOG_METHOD';
		$this->define_test_constant($constant_name, LogLevel::WARNING);

		$config = array(
		    'custom_debug_constant_name' => $constant_name,
		    'error_log_handler'          => array($this, 'mock_error_log_handler'),
		);
		$logger = new Logger($config);

		$test_message         = 'This is a warning test message.';
		$expected_log_message = '[WARNING] ' . $test_message;

		$logger->warning($test_message);

		$this->assertCount(1, $this->logged_messages, 'Expected one message to be logged.');
		$this->assertSame($expected_log_message, $this->logged_messages[0], 'Logged message content does not match.');
	}

	/**
	 * Tests that the error() method logs a message when the logger is active and the level allows it.
	 */
	public function test_error_method_logs_when_active_and_level_allows(): void {
		$constant_name = 'TEST_ERROR_LOG_METHOD';
		$this->define_test_constant($constant_name, LogLevel::ERROR);

		$config = array(
		    'custom_debug_constant_name' => $constant_name,
		    'error_log_handler'          => array($this, 'mock_error_log_handler'),
		);
		$logger = new Logger($config);

		$test_message         = 'This is an error test message.';
		$expected_log_message = '[ERROR] ' . $test_message;

		$logger->error($test_message);

		$this->assertCount(1, $this->logged_messages, 'Expected one message to be logged.');
		$this->assertSame($expected_log_message, $this->logged_messages[0], 'Logged message content does not match.');
	}

	/**
	 * Tests that the critical() method logs when the logger is active and the level allows it.
	 */
	public function test_critical_method_logs_when_active_and_level_allows(): void {
		$constant_name = 'TEST_CRITICAL_LOG_METHOD';
		$this->define_test_constant($constant_name, LogLevel::CRITICAL);

		$config = array(
		    'custom_debug_constant_name' => $constant_name,
		    'error_log_handler'          => array($this, 'mock_error_log_handler'),
		);
		$logger = new Logger($config);

		$test_message         = 'This is a critical test message.';
		$expected_log_message = '[CRITICAL] ' . $test_message;

		$logger->critical($test_message);

		$this->assertCount(1, $this->logged_messages, 'Expected one message to be logged.');
		$this->assertSame($expected_log_message, $this->logged_messages[0], 'Logged message content does not match.');
	}

	/**
	 * Tests that the alert() method logs when the logger is active and the level allows it.
	 */
	public function test_alert_method_logs_when_active_and_level_allows(): void {
		$constant_name = 'TEST_ALERT_LOG_METHOD';
		$this->define_test_constant($constant_name, LogLevel::ALERT);

		$config = array(
		    'custom_debug_constant_name' => $constant_name,
		    'error_log_handler'          => array($this, 'mock_error_log_handler'),
		);
		$logger = new Logger($config);

		$test_message         = 'This is an alert test message.';
		$expected_log_message = '[ALERT] ' . $test_message;

		$logger->alert($test_message);

		$this->assertCount(1, $this->logged_messages, 'Expected one message to be logged.');
		$this->assertSame($expected_log_message, $this->logged_messages[0], 'Logged message content does not match.');
	}

	/**
	 * Tests that the emergency() method logs when the logger is active and the level allows it.
	 */
	public function test_emergency_method_logs_when_active_and_level_allows(): void {
		$constant_name = 'TEST_EMERGENCY_LOG_METHOD';
		$this->define_test_constant($constant_name, LogLevel::EMERGENCY);

		$config = array(
		    'custom_debug_constant_name' => $constant_name,
		    'error_log_handler'          => array($this, 'mock_error_log_handler'),
		);
		$logger = new Logger($config);

		$test_message         = 'This is an emergency test message.';
		$expected_log_message = '[EMERGENCY] ' . $test_message;

		$logger->emergency($test_message);

		$this->assertCount(1, $this->logged_messages, 'Expected one message to be logged.');
		$this->assertSame($expected_log_message, $this->logged_messages[0], 'Logged message content does not match.');
	}

	/**
	 * Tests that messages are not logged if their level is below the effective log level.
	 */
	public function test_log_method_does_not_log_when_level_below_effective_level(): void {
		$constant_name = 'TEST_LOG_LEVEL_SUPPRESSION';
		// Activate logger at WARNING level
		$this->define_test_constant($constant_name, LogLevel::WARNING);

		$config = array(
		    'custom_debug_constant_name' => $constant_name,
		    'error_log_handler'          => array($this, 'mock_error_log_handler'),
		);
		$logger = new Logger($config);

		// Attempt to log an INFO message (which is below WARNING)
		$logger->info('This info message should not be logged.');
		$logger->debug('This debug message should not be logged.');

		$this->assertCount(0, $this->logged_messages, 'No messages should be logged if their level is below effective level.');
	}

	/**
	 * Tests that messages are not logged if the logger is inactive.
	 */
	public function test_log_method_does_not_log_when_logger_inactive(): void {
		// Ensure no activating constant or URL parameter is set
		$config = array(
		    'custom_debug_constant_name' => 'TEST_INACTIVE_LOGGER',
		    // No 'debug_request_param' or constant defined for TEST_INACTIVE_LOGGER
		    'error_log_handler' => array($this, 'mock_error_log_handler'), // To prevent actual error_log
		);
		$logger = new Logger($config);

		$this->assertFalse($logger->is_active(), 'Logger should be inactive.');

		$logger->debug('This message should not be logged as logger is inactive.');
		$logger->info('This message should not be logged as logger is inactive.');
		$logger->warning('This message should not be logged as logger is inactive.');
		$logger->error('This message should not be logged as logger is inactive.');

		$this->assertCount(0, $this->logged_messages, 'No messages should be logged if the logger is inactive.');
	}

	/**
	 * Tests that context is correctly appended to the log message.
	 */
	public function test_log_method_appends_context_correctly(): void {
		$constant_name = 'TEST_LOG_CONTEXT';
		$this->define_test_constant($constant_name, LogLevel::DEBUG);

		$config = array(
		    'custom_debug_constant_name' => $constant_name,
		    'error_log_handler'          => array($this, 'mock_error_log_handler'),
		);
		$logger = new Logger($config);

		$test_message = 'Debug message with context.';
		$context      = array('user_id' => 123, 'action' => 'test_action');
		// wp_json_encode is mocked to use standard json_encode
		$expected_log_message = '[DEBUG] ' . $test_message . ' Context: ' . json_encode($context);

		$logger->debug($test_message, $context);

		$this->assertCount(1, $this->logged_messages, 'Expected one message to be logged.');
		$this->assertSame($expected_log_message, $this->logged_messages[0], 'Logged message with context does not match.');
	}

	/**
	 * Tests that the logger activates via constant if a URL parameter is present but invalid.
	 */
	public function test_constant_activates_logger_when_url_param_is_invalid_and_present(): void {
		$url_param_name = 'TEST_INVALID_URL_VALID_CONST_PARAM';
		$constant_name  = 'TEST_INVALID_URL_VALID_CONST_CONST';

		$_GET[$url_param_name] = 'INVALID_LEVEL'; // Set an invalid URL param value
		$this->define_test_constant($constant_name, LogLevel::INFO); // Define a valid constant

		$config = array(
		    'debug_request_param'        => $url_param_name,
		    'custom_debug_constant_name' => $constant_name,
		    'error_log_handler'          => array($this, 'mock_error_log_handler'),
		);
		$logger = new Logger($config);

		$this->assertTrue($logger->is_active(), 'Logger should be active via constant.');
		$this->assertSame(Logger::LOG_LEVELS_MAP[LogLevel::INFO], $logger->get_log_level(), 'Logger effective log level should be INFO.');
		$activation_mode = $this->_get_protected_property_value($logger, 'activation_mode');
		$this->assertSame('constant', $activation_mode, 'Activation mode should be constant.');

		// Log a message to ensure it works
		$logger->info('Test message for constant activation with invalid URL param.');
		$this->assertCount(1, $this->logged_messages, 'Expected one message to be logged.');

		unset($_GET[$url_param_name]);
	}

	/**
	 * Tests that the constructor correctly defaults custom_debug_constant_name from TextDomain.
	 */
	public function test_constructor_defaults_constant_name_from_textdomain(): void {
		$config = array(
		    'TextDomain' => 'MY_AWESOME_PLUGIN',
            // No custom_debug_constant_name
		    'error_log_handler' => array($this, 'mock_error_log_handler'), // To prevent actual error_log
		);
		$logger               = new Logger($config);
		$actual_constant_name = $this->_get_protected_property_value($logger, 'custom_debug_constant_name');
		$this->assertSame('MY_AWESOME_PLUGIN_DEBUG_MODE', $actual_constant_name, 'custom_debug_constant_name should default based on TextDomain.');
	}

	/**
	 * Tests that the constructor correctly defaults custom_debug_constant_name to a generic value.
	 */
	public function test_constructor_defaults_constant_name_to_generic(): void {
		$config = array(
		    // No TextDomain
		    // No custom_debug_constant_name
		    'error_log_handler' => array($this, 'mock_error_log_handler'),
		);
		$logger               = new Logger($config);
		$actual_constant_name = $this->_get_protected_property_value($logger, 'custom_debug_constant_name');
		$this->assertSame('', $actual_constant_name, 'custom_debug_constant_name should default to empty string when config is non-empty but lacks specific keys.');
	}

	/**
	 * Tests that debug_request_param defaults to the final custom_debug_constant_name.
	 */
	public function test_constructor_defaults_request_param_to_final_constant_name(): void {
		// Case 1: custom_debug_constant_name is explicitly set
		$config1 = array(
		    'custom_debug_constant_name' => 'EXPLICIT_CONST_NAME',
		    // No debug_request_param
		    'error_log_handler' => array($this, 'mock_error_log_handler'),
		);
		$logger1               = new Logger($config1);
		$actual_request_param1 = $this->_get_protected_property_value($logger1, 'debug_request_param');
		$this->assertSame('EXPLICIT_CONST_NAME', $actual_request_param1, 'debug_request_param should default to explicit custom_debug_constant_name.');

		// Case 2: custom_debug_constant_name is defaulted from TextDomain
		$config2 = array(
		    'TextDomain' => 'TD_FOR_PARAM_DEFAULT',
		    // No custom_debug_constant_name
		    // No debug_request_param
		    'error_log_handler' => array($this, 'mock_error_log_handler'),
		);
		$logger2               = new Logger($config2);
		$actual_request_param2 = $this->_get_protected_property_value($logger2, 'debug_request_param');
		$this->assertSame('TD_FOR_PARAM_DEFAULT_DEBUG_MODE', $actual_request_param2, 'debug_request_param should default to TextDomain-derived custom_debug_constant_name.');
	}

	/**
	 * Tests that a non-callable error_log_handler is ignored and the internal handler remains null.
	 */
	public function test_constructor_ignores_non_callable_error_log_handler(): void {
		$config = array(
		    'error_log_handler' => 'this_is_not_a_function_or_callable_array',
		);
		$logger         = new Logger($config);
		$actual_handler = $this->_get_protected_property_value($logger, 'error_log_handler');
		$this->assertNull($actual_handler, 'error_log_handler should be null if a non-callable one is provided.');
	}
}
