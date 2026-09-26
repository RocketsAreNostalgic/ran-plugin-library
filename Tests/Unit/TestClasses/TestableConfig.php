<?php
/**
	* TestableConfig class for testing ConfigAbstract methods.
	*
	* @package Ran\PluginLib\Tests\Unit\TestClasses
	*/

declare(strict_types = 1);

namespace Ran\PluginLib\Tests\Unit\TestClasses;

use Ran\PluginLib\Config\ConfigAbstract;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Options\Storage\StorageContext;
use Ran\PluginLib\Util\CollectingLogger;

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
	private bool $mock_script_debug_defined = false;

	/**
		* Mock value for SCRIPT_DEBUG constant.
		*
		* @var bool
		*/
	private bool $mock_script_debug_value = false;

	/**
		* Mock plugin data for testing.
		*
		* @var array<string,mixed>
		*/
	private array $mock_plugin_data = array();

	/**
		* Constructor that sets up mock data.
		*
		* @param array<string,mixed>|null $plugin_data Optional plugin data array to initialize with.
		*/
	public function __construct(?array $plugin_data = null) {
		// Initialize with provided plugin data or default values
		$this->mock_plugin_data = $plugin_data ?? array(
			'Name'       => 'Test Plugin',
			'Version'    => '1.0.0',
			'TextDomain' => 'test-plugin',
			'RAN'        => array('AppOption' => 'test_plugin_options'),
			'URL'        => 'https://example.com/plugins/test-plugin/',
			'PATH'       => '/path/to/plugins/test-plugin/',
			'File'       => 'dummy-file-path.php',
			'Basename'   => 'test-plugin/dummy-file-path.php'
		);

		// Mock plugin_basename to avoid WP function call
		if (!function_exists('plugin_basename')) {
			if (!function_exists('Patchwork\\redefine')) {
				require_once dirname(__DIR__, 3) . '/vendor/antecedent/patchwork/Patchwork.php';
			}
			\Patchwork\redefine('plugin_basename', function($file) {
				return 'test-plugin/dummy-file-path.php';
			});
		}

		// Mock plugin_dir_url to avoid WP function call
		if (!function_exists('plugin_dir_url')) {
			if (!function_exists('Patchwork\\redefine')) {
				require_once dirname(__DIR__, 3) . '/vendor/antecedent/patchwork/Patchwork.php';
			}
			\Patchwork\redefine('plugin_dir_url', function($file) {
				return 'https://example.com/plugins/test-plugin/';
			});
		}

		// Mock plugin_dir_path to avoid WP function call
		if (!function_exists('plugin_dir_path')) {
			if (!function_exists('Patchwork\\redefine')) {
				require_once dirname(__DIR__, 3) . '/vendor/antecedent/patchwork/Patchwork.php';
			}
			\Patchwork\redefine('plugin_dir_path', function($file) {
				return '/path/to/plugins/test-plugin/';
			});
		}

		$this->set_logger(new CollectingLogger($this->mock_plugin_data));
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
		* @return array<string,mixed> The mock plugin data.
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
		* Override validate_config_array to always return the mock plugin data.
		*
		* @param array<string, mixed> $config_array The plugin data array.
		* @return array<string, mixed> Returns the mock plugin data.
		*/
	public function validate_config_array(array $config_array): array {
		return $this->mock_plugin_data;
	}

	/**
		* Override header content reader to avoid file system operations.
		*/
	protected function _read_header_content(string $file_path): string|false {
		return '';
	}

	/**
		* Provide a minimal normalized config for consumers of get_config().
		*
		* @return array<string,mixed>
		*/
	public function get_config(): array {
		$ran = (array)($this->mock_plugin_data['RAN'] ?? array());
		return array(
			'Name'       => $this->mock_plugin_data['Name'],
			'Version'    => $this->mock_plugin_data['Version'],
			'TextDomain' => $this->mock_plugin_data['TextDomain'],
			'PATH'       => $this->mock_plugin_data['PATH'],
			'URL'        => $this->mock_plugin_data['URL'],
			'Basename'   => $this->mock_plugin_data['Basename'],
			'File'       => $this->mock_plugin_data['File'],
			'Slug'       => 'test-plugin',
			'Type'       => 'plugin',
			'RAN'        => array(
				'AppOption'       => $ran['AppOption']       ?? 'test_plugin_options',
				'LogConstantName' => $ran['LogConstantName'] ?? 'RAN_LOG',
				'LogRequestParam' => $ran['LogRequestParam'] ?? 'ran_log',
			),
		);
	}

	/**
		* (Removed) Legacy static init() method was deleted as part of eliminating
		* singleton-style access in tests. Construct instances directly or use
		* factory methods on concrete Config implementations where applicable.
		*/

	/**
    	 * Provide options accessor required by ConfigInterface for this test class (typed-first).
    	 */
	public function options(?StorageContext $context = null, bool $autoload = true): RegisterOptions {
		$opts = new RegisterOptions($this->get_options_key(), $context, $autoload, $this->get_logger());
		// Align with tests expecting two reads during initial access
		$opts->refresh_options();
		return $opts;
	}
}
