<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit;

use Mockery;
use WP_Mock;
use RanTestCase;
use Mockery\MockInterface;
use Ran\PluginLib\Config\ConfigAbstract;
use Ran\PluginLib\Util\CollectingLogger;
use PHPUnit\Framework\MockObject\MockObject;
use Ran\PluginLib\EnqueueAccessory\AssetType;
use Ran\PluginLib\Singleton\SingletonAbstract;

/**
 * Minimal concrete class for testing ConfigAbstract initialization.
 *
 * This class is used internally by PluginLibTestCase to create a tangible instance
 * of ConfigAbstract for testing purposes, as ConfigAbstract itself is abstract.
 */
class ConcreteConfigForTesting extends ConfigAbstract {
	// This method is explicitly defined to resolve a PHPUnit mocking ambiguity
	// with inherited methods. Its body is irrelevant as it will be mocked.
	public function get_plugin_data(): array {
		return array();
	}

	/**
	 * Provide options accessor required by ConfigInterface for this test concrete.
	 * Mirrors production semantics: no writes; optional schema registration without seed/flush.
	 *
	 * @param array{autoload?: bool, schema?: array<string, mixed>} $args
	 * @return \Ran\PluginLib\Options\RegisterOptions
	 */
	public function options(array $args = array()): \Ran\PluginLib\Options\RegisterOptions {
		$defaults = array('autoload' => true, 'schema' => array());
		$args     = is_array($args) ? array_merge($defaults, $args) : $defaults;

		$autoload = (bool) ($args['autoload'] ?? true);
		$schema   = is_array($args['schema'] ?? null) ? $args['schema'] : array();

		$opts = \Ran\PluginLib\Options\RegisterOptions::from_config(
			$this,
			array(),           // initial (none)
			$autoload,
			$this->get_logger(),
			array()            // schema (none at construction)
		);
		if (!empty($schema)) {
			$opts->register_schema($schema, false, false);
		}
		return $opts;
	}
}

/**
 * Base test case for plugin-lib unit tests requiring a ConfigAbstract environment.
 *
 * This abstract class provides a standardized setup for tests that depend on
 * a properly initialized ConfigAbstract instance (or its derivatives).
 * It handles the mocking of necessary WordPress functions, creation of a dummy plugin file,
 * and the initialization and registration of a `ConcreteConfigForTesting` instance
 * within the SingletonAbstract manager. This ensures that classes under test which
 * rely on `ConfigAbstract::get_instance()` or `ConfigAbstract::init()` can function
 * correctly in a controlled test environment.
 */
abstract class PluginLibTestCase extends RanTestCase {
	/**
	 * @var bool Set to true within a test to enable console logging for that test.
	 */
	protected bool $enable_console_logging = false;

	/**
	 * Mocked instance of the concrete configuration class.
	 * @var ConcreteConfigForTesting|MockObject
	 */
	protected $config_mock;

	/**
	 * @var CollectingLogger|null The logger instance for collecting log messages.
	 */
	protected ?CollectingLogger $logger_mock = null;

	/**
	 * Path to the mock plugin file created during setup.
	 * Example: `/path/to/tests/Unit/mock-plugin-file.php`
	 * @var string
	 */
	protected string $mock_plugin_file_path;

	/**
	 * Path to the directory containing the mock plugin file.
	 * Example: `/path/to/tests/Unit/mock-plugin-dir/`
	 * @var string
	 */
	protected string $mock_plugin_dir_path;

	/**
	 * URL of the directory containing the mock plugin file.
	 * Example: `http://example.com/wp-content/plugins/mock-plugin-dir/`
	 * @var string
	 */
	protected string $mock_plugin_dir_url;

	/**
	 * Basename of the mock plugin file.
	 * Example: `mock-plugin-dir/mock-plugin-file.php`
	 * @var string
	 */
	protected string $mock_plugin_basename;

	/**
	 * Mock data for the plugin header.
	 * @var array<string, mixed>
	 */
	protected array $mock_plugin_data;

	/**
	 * Tracks constants defined during a test to undefine them in tearDown.
	 * @var string[]
	 */
	protected array $defined_constants = array();

	/**
	 * Sets up the test environment before each test.
	 *
	 * Initializes mock plugin file paths, data, and creates the mock plugin file.
	 * This method should be called via `parent::setUp()` in child test classes.
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		$this->defined_constants = array(); // Reset for each test

		$this->enable_console_logging = false;
		$this->mock_plugin_file_path  = __DIR__ . '/mock-plugin-file.php';
		$this->mock_plugin_dir_path   = __DIR__ . '/mock-plugin-dir/';
		$this->mock_plugin_dir_url    = 'http://example.com/wp-content/plugins/mock-plugin-dir/';
		$this->mock_plugin_basename   = 'mock-plugin-dir/mock-plugin-file.php';

		$this->mock_plugin_data = array(
		    'Name'            => 'Mock Plugin for Testing',
		    'Version'         => '1.0.0',
		    'PluginURI'       => 'http://example.com',
		    'TextDomain'      => 'mock-plugin-textdomain',
		    'DomainPath'      => '/languages',
		    'RequiresPHP'     => '7.4',
		    'RequiresWP'      => '5.0',
		    'LogConstantName' => 'MOCK_PLUGIN_DEBUG_MODE',
		    'LogRequestParam' => 'mock_debug_mode',
		);

		// Ensure the mock plugin directory exists
		if (!file_exists($this->mock_plugin_dir_path)) {
			mkdir($this->mock_plugin_dir_path, 0777, true);
		}

		// Ensure the dummy plugin file exists for tests that need it.
		if (!file_exists($this->mock_plugin_file_path)) {
			touch($this->mock_plugin_file_path);
		}

		// Define the debug constant to make the real is_active() method return true.
		$this->define_constant($this->mock_plugin_data['LogConstantName'], true);

		// Initialize and register the concrete config instance.
		$this->config_mock = $this->get_and_register_concrete_config_instance();

		// Instantiate the real CollectingLogger. This allows tests to inspect all
		// logs after execution without Mockery interfering.
		$this->logger_mock = new CollectingLogger($this->config_mock->get_plugin_data());

		// Configure the config mock to always return our specific logger instance.
		$this->config_mock->method('get_logger')->willReturn($this->logger_mock);
	}

	/**
	 * Cleans up the test environment after each test.
	 *
	 * Removes the mock plugin file and cleans up singleton instances managed by
	 * SingletonAbstract to prevent test pollution. This method should be called
	 * via `parent::tearDown()` in child test classes.
	 * @return void
	 */
	public function tearDown(): void {
		// Conditionally print logs if enabled for the test
		if ($this->enable_console_logging && $this->logger_mock instanceof CollectingLogger) {
			$logs = $this->logger_mock->collected_logs;
			if (!empty($logs)) {
				fwrite(STDERR, "\n--- CONSOLE LOGS FOR TEST: " . $this->getName() . " ---\n");
				fwrite(STDERR, print_r($logs, true));
				fwrite(STDERR, '--- END CONSOLE LOGS FOR TEST: ' . $this->getName() . " ---\n\n");
			}
		}

		// Clean up defined constants
		foreach ($this->defined_constants as $constant_name) {
			// This is tricky as PHP doesn't have a native 'undefine'
			// so we can't truly clean up. This is a known limitation.
			// For now, we just clear our tracking array.
		}
		$this->defined_constants = array();

		// Remove the mock plugin file.
		if (file_exists($this->mock_plugin_file_path)) {
			unlink($this->mock_plugin_file_path);
		}

		Mockery::close();
		WP_Mock::tearDown();

		// Clean up singleton instances
		$this->_removeSingletonInstance(ConcreteConfigForTesting::class);
		$this->_removeSingletonInstance(ConfigAbstract::class);

		parent::tearDown();
	}

	/**
	 * Defines a constant if it's not already defined and tracks its name.
	 *
	 * @param string $name The name of the constant.
	 * @param mixed $value The value of the constant.
	 * @return void
	 */
	protected function define_constant(string $name, $value): void {
		if (!defined($name)) {
			define($name, $value);
			$this->defined_constants[] = $name;
		}
	}

	/**
	 * Sets up WP_Mock expectations for a full asset lifecycle.
	 *
	 * @param AssetType $asset_type      The type of asset.
	 * @param string    $register_function  The name of the WordPress registration function.
	 * @param string    $enqueue_function   The name of the WordPress enqueue function.
	 * @param ?string   $is_function        The name of the WordPress status check function (e.g., 'wp_script_is'). Null for script modules.
	 * @param array     $asset_to_add       The asset definition array.
	 * @param bool      $is_registered      Whether the asset is already registered.
	 * @param bool      $is_enqueued        Whether the asset is already enqueued.
	 */
	protected function _mock_asset_lifecycle_functions(
		AssetType $asset_type,
		string $register_function,
		string $enqueue_function,
		?string $is_function,
		array $asset_to_add,
		bool $is_registered = false,
		bool $is_enqueued = false
	): void {
		$asset_type_string = $asset_type->value;
		$handle            = $asset_to_add['handle'];

		// Only mock the status function if it's provided (script modules don't have one)
		if ($is_function !== null) {
			WP_Mock::userFunction($is_function)->with($handle, 'registered')->andReturn(false, true, true);
		}
		// WP_Mock::userFunction($is_function)->with($handle, 'enqueued')->andReturn(false);

		if ($asset_type_string === 'script') {
			WP_Mock::userFunction($register_function)->with($handle, $asset_to_add['src'], $asset_to_add['deps'], $asset_to_add['version'], false)->andReturn(true);
		} elseif ($asset_type_string === 'script_module') {
			// Script modules use a different registration signature
			WP_Mock::userFunction($register_function)->with($handle, $asset_to_add['src'], $asset_to_add['deps'], $asset_to_add['version'])->andReturn(true);
		} else {
			// Styles have a media parameter
			WP_Mock::userFunction($register_function)->with($handle, $asset_to_add['src'], $asset_to_add['deps'], $asset_to_add['version'], $asset_to_add['media'])->andReturn(true);
		}
		WP_Mock::userFunction($enqueue_function)->with($handle);
	}

	/**
	 * Sets the value of a protected or private property on an object.
	 *
	 * @param object $object The object on which to set the property.
	 * @param string $property_name The name of the property to set.
	 * @param mixed $value The value to set the property to.
	 * @return void
	 * @throws \ReflectionException If the property does not exist in the object or any of its parents.
	 */
	protected function _set_protected_property_value(object &$object, string $property_name, $value): void {
		$reflection = new \ReflectionObject($object);

		// Walk up the inheritance tree to find the property
		while ($reflection) {
			if ($reflection->hasProperty($property_name)) {
				$property = $reflection->getProperty($property_name);
				$property->setAccessible(true);
				$property->setValue($object, $value);
				return; // Property found and set
			}
			$reflection = $reflection->getParentClass();
		}

		// If we get here, the property was not found in the entire hierarchy.
		throw new \ReflectionException(
			sprintf(
				'Property "%s" does not exist in class "%s" or any of its parents.',
				$property_name,
				get_class($object)
			)
		);
	}

	/**
	 * Gets the value of a protected or private property from an object.
	 *
	 * This is a helper method for testing, allowing access to non-public properties
	 * to verify internal state.
	 *
	 * @param object $object The object from which to get the property.
	 * @param string $property_name The name of the property to get.
	 * @return mixed The value of the property.
	 * @throws \ReflectionException If the property does not exist on the object.
	 */
	protected function _get_protected_property_value(object $object, string $property_name) {
		$reflection = new \ReflectionObject($object);
		$property   = $reflection->getProperty($property_name);
		$property->setAccessible(true);

		return $property->getValue($object);
	}

	/**
	 * Invokes a protected method on an object.
	 *
	 * @param object $object The object to call the method on.
	 * @param string $method_name The name of the method to call.
	 * @param array $parameters An array of parameters to pass to the method.
	 *
	 * @return mixed The return value of the method.
	 * @throws \ReflectionException If the method does not exist.
	 */
	protected function _invoke_protected_method(object $object, string $method_name, array $parameters = array()) {
		$reflection = new \ReflectionClass(get_class($object));
		$method     = $reflection->getMethod($method_name);
		$method->setAccessible(true);
		return $method->invokeArgs($object, $parameters);
	}

	/**
	 * Removes a specific singleton instance from SingletonAbstract::$instances.
	 *
	 * Uses reflection to access and modify the private static $instances property
	 * of SingletonAbstract. This is primarily used in tearDown() to ensure a clean
	 * state between tests.
	 *
	 * @param string $className The fully qualified class name of the singleton to remove.
	 * @return void
	 */
	protected function _removeSingletonInstance(string $className): void {
		try {
			$reflectionSingleton = new \ReflectionClass(SingletonAbstract::class);
			$instancesProperty   = $reflectionSingleton->getProperty('instances');
			$instancesProperty->setAccessible(true);
			$currentInstances = $instancesProperty->getValue();

			if (isset($currentInstances[$className])) {
				unset($currentInstances[$className]);
				$instancesProperty->setValue(null, $currentInstances);
			}
		} catch (\ReflectionException $e) {
			// Fail silently if the property doesn't exist, as it means no cleanup is needed.
		}
	}


	/**
	 * Sets up the logger mock for the test.
	 *
	 * This method creates a mock of the CollectingLogger and sets it on the test case.
	 * It also sets an expectation for the `is_active` method to return true, which is
	 * necessary for the logger to be used in the code under test.
	 *
	 * @return MockInterface The mocked logger instance.
	 */
	protected function set_logger_mock(): MockInterface {
		$this->logger_mock = Mockery::mock(CollectingLogger::class)->makePartial();
		$this->logger_mock->shouldReceive('is_active')->andReturn(true);

		return $this->logger_mock;
	}


	/**
	 * Creates, configures, and registers a `ConcreteConfigForTesting` instance.
	 *
	 * This is the core helper method provided by `PluginLibTestCase`. It performs the following steps:
	 * 1. Mocks essential WordPress functions (`plugin_dir_path`, `get_plugin_data`, etc.)
	 *    to provide a controlled environment.
	 * 2. Initializes the `ConcreteConfigForTesting` class via `::init()`.
	 * 3. Mocks the `_read_plugin_file_header_content()` method on a `ConcreteConfigForTesting` instance to return controlled header data.
	 * 4. Manually invokes the `ConfigAbstract` constructor on this mock instance.
	 * 5. Registers the fully constructed and configured `ConcreteConfigForTesting` instance in `SingletonAbstract::$instances` under two keys:
	 *    - `ConcreteConfigForTesting::class`: For direct access if needed.
	 *    - `ConfigAbstract::class`: Crucially, this allows classes under test which
	 *      call `ConfigAbstract::get_instance()` (e.g., indirectly via `RegisterOptions::get_logger()`) to receive this test-specific, fully configured instance.
	 *
	 * Child test classes should call this method in their `setUp()` (after `parent::setUp()`) to ensure the
	 * configuration system is ready before instantiating the class under test.
	 *
	 * @return \PHPUnit\Framework\MockObject\MockObject|ConcreteConfigForTesting The fully initialized and registered concrete config instance.
	 */
	protected function get_and_register_concrete_config_instance() {
		WP_Mock::userFunction('plugin_dir_path')
		    ->with($this->mock_plugin_file_path)
		    ->andReturn($this->mock_plugin_dir_path);
		WP_Mock::userFunction('plugin_dir_url')
		    ->with($this->mock_plugin_file_path)
		    ->andReturn($this->mock_plugin_dir_url);
		WP_Mock::userFunction('plugin_basename')
		    ->with($this->mock_plugin_file_path)
		    ->andReturn($this->mock_plugin_basename);
		WP_Mock::userFunction('get_plugin_data')
		    ->with($this->mock_plugin_file_path, false, false)
		    ->andReturn($this->mock_plugin_data);
		WP_Mock::userFunction('sanitize_title')
		    ->with($this->mock_plugin_data['TextDomain'])
		    ->andReturn($this->mock_plugin_data['TextDomain']); // Simple mock

		// NOTE: Legacy ::init() no longer exists on Config; we create a mock instance directly.

		// Define mock header content for _read_plugin_file_header_content
		$mock_file_header_content = "<?php\n/**\n";
		foreach ($this->mock_plugin_data as $key => $value) {
			// Convert camelCase/PascalCase to spaced words for header keys if necessary
			// For simplicity, we'll assume keys in mock_plugin_data are already header-like or direct.
			// Example: 'LogConstantName' -> 'Log Constant Name'
			$header_key = preg_replace('/(?<!^)([A-Z])/', ' $1', $key);
			$mock_file_header_content .= " * {$header_key}: {$value}\n";
		}
		$mock_file_header_content .= ' */';

		$concreteInstance = $this->getMockBuilder(ConcreteConfigForTesting::class)
			->onlyMethods(array('_read_header_content', 'get_logger', 'get_plugin_data', 'get_config'))
			->addMethods(array('get_enqueue_public_config'))
			->disableOriginalConstructor()
			->getMock();

		$concreteInstance->expects($this->any())
		    ->method('_read_header_content')
		    ->with($this->mock_plugin_file_path)
		    ->willReturn($mock_file_header_content);

		$concreteInstance->expects($this->any())
		    ->method('get_logger')
		    ->willReturn($this->set_logger_mock());

		$concreteInstance->expects($this->any())
			->method('get_plugin_data')
			->willReturn($this->mock_plugin_data);

		// Provide a minimal normalized config payload expected by tests
		$slug = strtolower(preg_replace('/[^a-z0-9_\-]/i', '_', (string)($this->mock_plugin_data['TextDomain'] ?? 'mock-plugin')));
		$concreteInstance->method('get_config')->willReturn(array(
			'Name'       => $this->mock_plugin_data['Name']       ?? 'Mock Plugin',
			'Version'    => $this->mock_plugin_data['Version']    ?? '0.0.0',
			'TextDomain' => $this->mock_plugin_data['TextDomain'] ?? 'mock-plugin',
			'PATH'       => $this->mock_plugin_dir_path,
			'URL'        => $this->mock_plugin_dir_url,
			'Basename'   => $this->mock_plugin_basename,
			'File'       => $this->mock_plugin_file_path,
			'Slug'       => $slug,
			'Type'       => 'plugin',
		));

		// Register in SingletonAbstract for codepaths that call ConfigAbstract::get_instance()
		try {
			$reflectionSingleton = new \ReflectionClass(SingletonAbstract::class);
			$instancesProperty   = $reflectionSingleton->getProperty('instances');
			$instancesProperty->setAccessible(true);
			$currentInstances                                  = $instancesProperty->getValue();
			$currentInstances[ConcreteConfigForTesting::class] = $concreteInstance;
			$currentInstances[ConfigAbstract::class]           = $concreteInstance;
			$instancesProperty->setValue(null, $currentInstances);
		} catch (\ReflectionException $e) {
			// If singleton scaffolding changes, tests that depend on it will surface failures.
		}

		return $concreteInstance;
	}
}
