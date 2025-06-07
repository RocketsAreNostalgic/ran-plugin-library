<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit;

use RanTestCase; // Assumes RanTestCase is available via autoloader or bootstrap
use Ran\PluginLib\Config\ConfigAbstract;
use Ran\PluginLib\Singleton\SingletonAbstract;
use WP_Mock;

/**
 * Minimal concrete class for testing ConfigAbstract initialization.
 *
 * This class is used internally by PluginLibTestCase to create a tangible instance
 * of ConfigAbstract for testing purposes, as ConfigAbstract itself is abstract.
 */
if (!class_exists(\Ran\PluginLib\Tests\Unit\ConcreteConfigForTesting::class)) {
	class ConcreteConfigForTesting extends ConfigAbstract {
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
 *
 * ## Usage Example:
 *
 * ```php
 * <?php
 * declare(strict_types=1);
 *
 * namespace Ran\PluginLib\Tests\Unit\MyPluginFeature;
 *
 * use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
 * use Ran\PluginLib\MyFeature\MyClassThatUsesConfig;
 * use Ran\PluginLib\Util\Logger; // If MyClassThatUsesConfig uses the logger
 * use WP_Mock;
 *
 * final class MyClassThatUsesConfigTest extends PluginLibTestCase {
 *     private MyClassThatUsesConfig $my_class_instance;
 *     private MockObject $logger_mock; // Example if MyClassThatUsesConfig needs a logger mock
 *
 *     public function setUp(): void {
 *         parent::setUp(); // Sets up mock plugin file, data, and WP mocks via PluginLibTestCase
 *
 *         // This ensures ConfigAbstract is initialized and a concrete instance is available.
 *         $this->get_and_register_concrete_config_instance();
 *
 *         // Mock the logger if MyClassThatUsesConfig uses it via get_logger()
 *         $this->logger_mock = $this->createMock(Logger::class);
 *         $this->logger_mock->method('is_active')->willReturn(false);
 *
 *         // Now, instantiate your class under test.
 *         // If it extends ConfigAbstract or calls ConfigAbstract::get_instance(),
 *         // it will receive the configured ConcreteConfigForTesting instance.
 *         $this->my_class_instance = $this->getMockBuilder(MyClassThatUsesConfig::class)
 *             ->onlyMethods(['get_logger']) // Example: mock get_logger if it's protected
 *             ->setConstructorArgs([$this->plugin_data['PluginOption']]) // Example constructor args
 *             ->getMock();
 *
 *         $this->my_class_instance->method('get_logger')->willReturn($this->logger_mock);
 *     }
 *
 *     public function tearDown(): void {
 *         parent::tearDown(); // Cleans up singleton instances and mock plugin file
 *     }
 *
 *     public function test_my_feature_method_that_relies_on_config(): void {
 *         // Mock WordPress functions specific to this test, if any
 *         WP_Mock::userFunction('get_option')
 *             ->with('my_plugin_option_key', false)
 *             ->andReturn('some_value');
 *
 *         $result = $this->my_class_instance->doSomethingThatUsesConfig();
 *
 *         $this->assertEquals('expected_result_based_on_config_and_option', $result);
 *         // Add more assertions as needed
 *     }
 * }
 * ?>
 * ```
 */


if (!class_exists(\Ran\PluginLib\Tests\Unit\PluginLibTestCase::class)) {
	abstract class PluginLibTestCase extends RanTestCase {
		/**
		 * Path to the mock plugin file created during setup.
		 * Example: `/path/to/tests/Unit/mock-plugin-file.php`
		 * @var string
		 */
		protected string $mock_plugin_file_path;
		/**
		 * Mock plugin header data used for testing.
		 * This array is returned by the mocked `get_plugin_data()` WordPress function.
		 * @var array<string, string|array<string,string>>
		 */
		protected array $mock_plugin_data = array();
		/**
		 * Path to the directory containing the mock plugin file.
		 * Example: `/path/to/tests/Unit/mock-plugin-dir/`
		 * @var string
		 */
		protected string $mock_plugin_dir_path;
		/**
		 * URL for the mock plugin directory.
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
		 * Sets up the test environment before each test.
		 *
		 * Initializes mock plugin file paths, data, and creates the mock plugin file.
		 * This method should be called via `parent::setUp()` in child test classes.
		 * @return void
		 */
		public function setUp(): void {
			parent::setUp();

			$this->mock_plugin_file_path = __DIR__ . '/mock-plugin-file.php';
			$this->mock_plugin_dir_path  = __DIR__ . '/mock-plugin-dir/';
			$this->mock_plugin_dir_url   = 'http://example.com/wp-content/plugins/mock-plugin-dir/';
			$this->mock_plugin_basename  = 'mock-plugin-dir/mock-plugin-file.php';

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

			// Ensure the dummy plugin file exists for tests that need it.
			// Individual test setup methods can remove it if they test file-not-found scenarios.
			if (!file_exists($this->mock_plugin_file_path)) {
				touch($this->mock_plugin_file_path);
			}
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
			// Clean up the singleton instance for ConcreteConfigForTesting
			$this->removeSingletonInstance(ConcreteConfigForTesting::class);
			// Also clean up the alias we might have created for ConfigAbstract
			$this->removeSingletonInstance(ConfigAbstract::class);

			if (file_exists($this->mock_plugin_file_path)) {
				unlink($this->mock_plugin_file_path);
			}
			parent::tearDown();
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
		protected function removeSingletonInstance(string $className): void {
			try {
				$reflectionClass   = new \ReflectionClass(SingletonAbstract::class);
				$instancesProperty = $reflectionClass->getProperty('instances');
				$instancesProperty->setAccessible(true);
				$current_instances = $instancesProperty->getValue(null);
				if (isset($current_instances[$className])) {
					unset($current_instances[$className]);
					$instancesProperty->setValue(null, $current_instances);
				}
			} catch (\ReflectionException $e) {
				// Suppress reflection errors during cleanup
			}
		}

		/**
		 * Creates, configures, and registers a `ConcreteConfigForTesting` instance.
		 *
		 * This is the core helper method provided by `PluginLibTestCase`. It performs the following steps:
		 * 1. Mocks essential WordPress functions (`plugin_dir_path`, `plugin_dir_url`, `plugin_basename`, `get_plugin_data`, `sanitize_title`).
		 * 2. Calls `ConcreteConfigForTesting::init()` with the mock plugin file path. This initializes the static `$plugin_file` property in `ConfigAbstract`.
		 * 3. Mocks the `_read_plugin_file_header_content()` method on a `ConcreteConfigForTesting` instance to return controlled header data.
		 * 4. Manually invokes the `ConfigAbstract` constructor on this mock instance.
		 * 5. Registers the fully constructed and configured `ConcreteConfigForTesting` instance in `SingletonAbstract::$instances` under two keys:
		 *    - `ConcreteConfigForTesting::class`: For direct access if needed.
		 *    - `ConfigAbstract::class`: Crucially, this allows classes under test that call `ConfigAbstract::get_instance()` (e.g., indirectly via `RegisterOptions::get_logger()`) to receive this test-specific, fully configured instance.
		 *
		 * Child test classes should call this method in their `setUp()` (after `parent::setUp()`) to ensure the
		 * configuration system is ready before instantiating the class under test.
		 *
		 * @return ConcreteConfigForTesting The fully initialized and registered concrete config instance.
		 */
		protected function get_and_register_concrete_config_instance(): ConcreteConfigForTesting {
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

			// Initialize the concrete config class (which calls ConfigAbstract::init indirectly)
			ConcreteConfigForTesting::init($this->mock_plugin_file_path);

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
				->onlyMethods(array('_read_plugin_file_header_content'))
				->disableOriginalConstructor()
				->getMock();

			$concreteInstance->expects($this->any()) // Use any() if called multiple times or once() if strictly once
				->method('_read_plugin_file_header_content')
				->with($this->mock_plugin_file_path)
				->willReturn($mock_file_header_content);

			// Manually invoke the ConfigAbstract constructor
			$reflection  = new \ReflectionObject($concreteInstance);
			$constructor = $reflection->getParentClass()->getConstructor(); // ConfigAbstract constructor
			$constructor->invoke($concreteInstance);

			// Register this fully constructed instance in SingletonAbstract
			$reflectionSingleton = new \ReflectionClass(SingletonAbstract::class);
			$instancesProperty   = $reflectionSingleton->getProperty('instances');
			$instancesProperty->setAccessible(true);
			$currentInstances = $instancesProperty->getValue();

			// Store under its own class name
			$currentInstances[ConcreteConfigForTesting::class] = $concreteInstance;
			// AND under ConfigAbstract::class for compatibility with RegisterOptions::get_logger()
			$currentInstances[ConfigAbstract::class] = $concreteInstance;

			$instancesProperty->setValue(null, $currentInstances);

			return $concreteInstance;
			// Note: Callers might expect ConfigAbstract::get_instance() to work,
			// or ConcreteConfigForTesting::get_instance(). The reflection ensures both do.
		}
	}
}
