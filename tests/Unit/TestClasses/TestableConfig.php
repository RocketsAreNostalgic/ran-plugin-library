<?php
/**
 * TestableConfig class for testing ConfigAbstract methods.
 *
 * @package Ran\PluginLib\Tests\Unit\TestClasses
 */

declare(strict_types = 1);

namespace Ran\PluginLib\Tests\Unit\TestClasses;

use Ran\PluginLib\Config\ConfigAbstract;

/**
 * TestableConfig class for testing ConfigAbstract methods.
 *
 * This class extends ConfigAbstract and provides methods to control
 * the behavior of get_is_dev_callback and is_dev_environment for testing.
 */
class TestableConfig extends ConfigAbstract {
	/**
	 * Mock callback for dev environment detection.
	 *
	 * @var callable|null
	 */
	private $mock_dev_callback = null;

	/**
	 * Mock value for SCRIPT_DEBUG constant.
	 *
	 * @var bool
	 */
	private $mock_script_debug_defined = false;

	/**
	 * Mock value for SCRIPT_DEBUG constant.
	 *
	 * @var bool
	 */
	private $mock_script_debug_value = false;
    
	/**
	 * Mock plugin data for testing.
	 *
	 * @var array
	 */
	private array $mock_plugin_data = array();

	/**
	 * Constructor that sets up mock data.
	 *
	 * @param array|null $plugin_data Optional plugin data array to initialize with.
	 */
	public function __construct(?array $plugin_data = null) {
		// Set the static plugin_file property
		$reflectionClass    = new \ReflectionClass('\Ran\PluginLib\Config\ConfigAbstract');
		$pluginFileProperty = $reflectionClass->getProperty('plugin_file');
		$pluginFileProperty->setAccessible(true);
		$pluginFileProperty->setValue(null, 'dummy-file-path.php');
        
		// Initialize with provided plugin data or default values
		$this->mock_plugin_data = $plugin_data ?? array(
		    'Name'            => 'Test Plugin',
		    'Version'         => '1.0.0',
		    'TextDomain'      => 'test-plugin',
		    'RANPluginOption' => 'test_plugin_options',
		    'URL'             => 'https://example.com/plugins/test-plugin/',
		    'PATH'            => '/path/to/plugins/test-plugin/',
		    'File'            => 'dummy-file-path.php',
		    'Basename'        => 'test-plugin/dummy-file-path.php'
		);
        
		// Mock plugin_basename to avoid WP function call
		if (!function_exists('plugin_basename')) {
			if (!function_exists('Patchwork\redefine')) {
				require_once dirname(__DIR__, 3) . '/vendor/antecedent/patchwork/Patchwork.php';
			}
			\Patchwork\redefine('plugin_basename', function($file) {
				return 'test-plugin/dummy-file-path.php';
			});
		}
        
		// Mock plugin_dir_url to avoid WP function call
		if (!function_exists('plugin_dir_url')) {
			if (!function_exists('Patchwork\redefine')) {
				require_once dirname(__DIR__, 3) . '/vendor/antecedent/patchwork/Patchwork.php';
			}
			\Patchwork\redefine('plugin_dir_url', function($file) {
				return 'https://example.com/plugins/test-plugin/';
			});
		}
        
		// Mock plugin_dir_path to avoid WP function call
		if (!function_exists('plugin_dir_path')) {
			if (!function_exists('Patchwork\redefine')) {
				require_once dirname(__DIR__, 3) . '/vendor/antecedent/patchwork/Patchwork.php';
			}
			\Patchwork\redefine('plugin_dir_path', function($file) {
				return '/path/to/plugins/test-plugin/';
			});
		}
        
		// Initialize the plugin_array property directly
		$pluginArrayProperty = $reflectionClass->getProperty('plugin_array');
		$pluginArrayProperty->setAccessible(true);
		$pluginArrayProperty->setValue($this, $this->mock_plugin_data);
	}

	/**
	 * Set the mock callback for dev environment detection.
	 *
	 * @param callable|mixed $callback Callback to use for testing.
	 * @return void
	 */
	public function set_mock_dev_callback($callback): void {
		$this->mock_dev_callback = $callback;
	}

	/**
	 * Set the mock value for SCRIPT_DEBUG constant.
	 *
	 * @param bool $defined Whether SCRIPT_DEBUG is defined.
	 * @param bool $value   Value of SCRIPT_DEBUG if defined.
	 * @return void
	 */
	public function set_mock_script_debug(bool $defined, bool $value = false): void {
		$this->mock_script_debug_defined = $defined;
		$this->mock_script_debug_value   = $value;
	}
    

    
	/**
	 * Override _get_plugin_config to return mock plugin data directly.
	 * This avoids calling get_plugin_data which requires WordPress core files.
	 *
	 * @return array The mock plugin data.
	 */
	protected function _get_plugin_config(): array {
		return $this->mock_plugin_data;
	}

	/**
	 * Override get_is_dev_callback to use the mock callback.
	 *
	 * @return callable|null The mock callback or null.
	 */
	public function get_is_dev_callback(): ?callable {
		if (isset($this->mock_dev_callback)) {
			return is_callable($this->mock_dev_callback) ? $this->mock_dev_callback : null;
		}
        
		return null;
	}

	/**
	 * Override is_dev_environment to use the mock SCRIPT_DEBUG state.
	 *
	 * @return bool Whether the environment is a dev environment.
	 */
	public function is_dev_environment(): bool {
		// First check if we have a callback
		$callback = $this->get_is_dev_callback();
		if (null !== $callback) {
			return (bool) $callback();
		}

		// Then check for SCRIPT_DEBUG
		if ($this->mock_script_debug_defined) {
			return $this->mock_script_debug_value;
		}

		// Default to false
		return false;
	}

	/**
	 * Override get_plugin_config to return the mock plugin data.
	 *
	 * @return array<string, mixed> Mock plugin configuration array.
	 */
	public function get_plugin_config(): array {
		return $this->mock_plugin_data;
	}
    
	/**
	 * Override validate_plugin_array to always return the mock plugin data.
	 *
	 * @param array<string, mixed> $plugin_array The plugin data array.
	 * @return array<string, mixed> Returns the mock plugin data.
	 */
	public function validate_plugin_array(array $plugin_array): array {
		return $this->mock_plugin_data;
	}

	/**
	 * Override _read_plugin_file_header_content to avoid file system operations.
	 *
	 * @param string $file_path The file path (unused in this test implementation)
	 * @return string|false Empty string for testing.
	 */
	protected function _read_plugin_file_header_content(string $file_path): string|false {
		return '';
	}
    
	/**
	 * Override init to return the current instance without requiring a plugin file.
	 *
	 * @param string $plugin_file Ignored in this implementation.
	 * @return self The current instance.
	 */
	public static function init(string $plugin_file = ''): self {
		return self::get_instance();
	}
}
