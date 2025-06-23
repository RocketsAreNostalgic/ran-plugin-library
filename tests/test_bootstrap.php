<?php
/**
 * Test bootstrap file for Ran Plugin Lib.

 * @package Ran/PluginLib
 */

declare(strict_types = 1);

// First we need to load the composer autoloader, so we can use WP Mock.
require_once dirname(__DIR__) . '/vendor/autoload.php';

use WP_Mock\Tools\TestCase;

// Bootstrap WP_Mock to initialize built-in features.
WP_Mock::bootstrap();

// Diagnostic: Check if add_action is shimmed
if (function_exists('add_action')) {
	$reflector = new \ReflectionFunction('add_action');
	if ($reflector->isUserDefined()) {
		fwrite(STDERR, "DIAGNOSTIC: add_action() is user-defined (shimmed by Patchwork).\n");
	} else {
		fwrite(STDERR, "DIAGNOSTIC: add_action() is NOT user-defined (NOT shimmed by Patchwork. Likely pre-defined by WordPress/wp-phpunit or other).\n");
	}
} else {
	fwrite(STDERR, "DIAGNOSTIC: add_action() does NOT exist after WP_Mock::bootstrap().\n");
}

/**
 * Base test case class for Ran Plugin Lib.

 * @package Ran/PluginLib
 */
abstract class RanTestCase extends TestCase {
	private static array $skippedFilesReported = array();
	/**
	 * Scaffold WP_Mock setUp method.
	 *
	 * @group skip
	 *
	 * @throws \Exception If setUp fails.
	 */
	public function setUp(): void {
		parent::setUp();

		// Following vitest convention of skipping test suites starting with 'skip.'
		$reflection   = new \ReflectionClass($this);
		$filename     = $reflection->getFileName();
		$baseFilename = basename($filename);

		if (strpos($baseFilename, 'skip.') === 0) {
			if (!in_array($baseFilename, self::$skippedFilesReported)) {
				fwrite(STDERR, PHP_EOL . 'Skipping ' . $baseFilename . PHP_EOL);
				fflush(STDERR);
				self::$skippedFilesReported[] = $baseFilename;
			}
			$this->markTestSkipped('File skipped due to skip. prefix');
		}

		$currentTestMethodName = $this->getName(); // Gets the name of the current test method

		if (strpos($currentTestMethodName, 'skip_') === 0) {
			$this->markTestSkipped('Method skipped due to skip_ prefix: ' . $currentTestMethodName);
		}
	}

	/**
	 * Scaffold WP_Mock tearDown method.
	 *
	 * @throws \Exception If tearDown fails.
	 */
	public function tearDown(): void {
		\WP_Mock::tearDown();
	}

	/**
	 * Sets a Mockery expectation for a log message, checking for substrings.
	 *
	 * This provides a less brittle way to test logging than matching exact strings.
	 * It requires the test class to have a `logger_mock` property.
	 *
	 * @param string       $level            The log level to expect (e.g., 'debug', 'warning').
	 * @param string|array $message_contains A substring or array of substrings the log message must contain.
	 * @param int          $times            The number of times the log is expected to be called.
	 * @param string|null  $order_group      Optional ordering group name for Mockery's `ordered()` method.
	 * @return void
	 */
	protected function expectLog(string $level, string|array $message_contains, int $times = 1, ?string $order_group = null, bool $logs = false): void {
		if (!is_array($message_contains)) {
			$message_contains = array($message_contains);
		}

		// This assumes the test class using this helper has a ->logger_mock property.
		if (!property_exists($this, 'logger_mock') || !$this->logger_mock instanceof \Mockery\MockInterface) {
			$this->fail('The expectLog() helper requires a Mockery\MockInterface property named "logger_mock" to be set on the test class.');
		}

		$expectation = $this->logger_mock->shouldReceive($level)
			->withArgs(function (...$args) use ($message_contains, $level, $logs) {
				// $args is an array of all arguments passed to the log method,
				// e.g., ['message', ['context' => 'value']]
				if (empty($args) || !is_string($args[0])) {
					return false;
				}

				$log_message = $args[0];

				foreach ($message_contains as $substring) {
					if (strpos($log_message, $substring) === false) {
						if ($logs) {
							fprintf(STDERR, "Log expectation failed for level '%s'.\n", $level);
							fprintf(STDERR, "Expected substring: '%s'\n", $substring);
							fprintf(STDERR, "Actual log message: '%s'\n", $log_message);
						}
						return false; // A required substring was not found.
					}
				}

				// All substrings were found, the match succeeds.
				return true;
			})
			->times($times);

		if ($order_group !== null) {
			$expectation->ordered($order_group);
		}
	}

	/**
	 * Gets the value of a protected/private property from an object.
	 *
	 * @param object $object The object to get the property from.
	 * @param string $property_name The name of the property.
	 * @return mixed The value of the property.
	 * @throws \ReflectionException If the property does not exist.
	 */
	protected function get_protected_property_value(object $object, string $property_name) {
		$reflector = new \ReflectionClass($object);

		// If the object is a Mockery mock, get the reflection of the mocked class
		if ($object instanceof \Mockery\MockInterface) {
			// Get the parent class of the mock object, which should be the actual class being mocked
			$parentClass = get_parent_class($object);
			if ($parentClass) {
				$reflector = new \ReflectionClass($parentClass);
			}
		}

		// Traverse up the class hierarchy to find the property
		while ($reflector) {
			if ($reflector->hasProperty($property_name)) {
				$property = $reflector->getProperty($property_name);
				// For private properties, ensure we are in the declaring class or make it accessible if protected.
				// The main check is that getProperty found it in $reflector.
				// If it's private, it must be from $reflector. If protected, it's fine from $reflector or parents.
				$property->setAccessible(true);
				return $property->getValue($object);
			}
			$reflector = $reflector->getParentClass();
		}

		// If property not found after traversing, throw an exception
		throw new \ReflectionException(sprintf(
			'Property "%s" not found in object of class "%s" or its parents.',
			$property_name,
			get_class($object)
		));
	}

	/**
	 * Invokes a protected/private method on an object.
	 *
	 * @param object $object The object to invoke the method on.
	 * @param string $method_name The name of the method.
	 * @param array  $args The arguments to pass to the method.
	 * @return mixed The return value of the method.
	 * @throws \ReflectionException If the method does not exist.
	 */
	protected function invoke_protected_method(object $object, string $method_name, array $args = array()) {
		$reflector = new \ReflectionClass($object);
		$method    = $reflector->getMethod($method_name);
		$method->setAccessible(true);
		return $method->invokeArgs($object, $args);
	}
}
