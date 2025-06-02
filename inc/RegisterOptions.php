<?php
/**
 * WordPress Options Registration and Management.
 *
 * This class manages plugin options by storing them as a single array
 * under a specified main option name in the WordPress options table.
 * This approach enhances encapsulation and keeps the wp_options table cleaner.
 *
 * @package  RanPluginLib
 */

declare(strict_types=1);

namespace Ran\PluginLib;

use Ran\PluginLib\Config\ConfigAbstract;
use Ran\PluginLib\Util\Logger;

/**
 * Manages plugin options by storing them as a single array in the wp_options table.
 *
 * This class provides methods for registering, retrieving, and updating plugin settings,
 * all grouped under one main WordPress option. This improves organization and reduces
 * the number of individual rows added to the wp_options table.
 */
class RegisterOptions {
	/**
	 * The in-memory store for all plugin options.
	 * Structure: ['option_key' => ['value' => mixed, 'autoload_hint' => bool|null]]
	 *
	 * @var array<string, array{"value": mixed, "autoload_hint": bool|null}>
	 */
	private array $options = array();

	/**
	 * The main WordPress option name under which all plugin settings are stored as an array.
	 *
	 * @var string
	 */
	private string $main_wp_option_name;

	/**
	 * Whether the main WordPress option group should be autoloaded by WordPress.
	 *
	 * @var bool
	 */
	private bool $main_option_autoload;

	/**
	 * Logger instance.
	 *
	 * @var Logger|null
	 */
	private ?Logger $logger = null;

	/**
	 * Creates a new RegisterOptions instance.
	 *
	 * Initializes options by loading them from the database under `$main_wp_option_name`.
	 * If initial `$default_options` are provided, they are merged with existing options
	 * and the entire set is saved back to the database.
	 *
	 * @param string $main_wp_option_name The primary key in wp_options where all settings for this instance are stored.
	 * @param array<string, array{"value": mixed, "autoload_hint": bool|null}|mixed> $initial_options Optional. An array of default options to set if not already present or to merge.
	 *                                   Each key is the option name. Value can be the direct value or an array ['value' => mixed, 'autoload_hint' => bool].
	 * @param bool $main_option_autoload  Whether the entire group of options should be autoloaded by WordPress. Defaults to true.
	 */
	public function __construct(string $main_wp_option_name, array $initial_options = array(), bool $main_option_autoload = true) {
		$this->main_wp_option_name  = $main_wp_option_name;
		$this->main_option_autoload = $main_option_autoload;

		// Load all existing options from the single database entry.
		$this->options = \get_option($this->main_wp_option_name, array());
		// @codeCoverageIgnoreStart
		if ($this->get_logger()->is_active()) {
			$this->get_logger()->debug("RegisterOptions: Initialized with main option '{$this->main_wp_option_name}'. Loaded " . count($this->options) . ' existing sub-options.');
		}
		// @codeCoverageIgnoreEnd

		// If initial/default options are provided, merge and save them.
		if (!empty($initial_options)) {
			$options_changed = false;
			foreach ($initial_options as $option_name => $definition) {
				$option_name_clean    = \str_replace(' ', '_', $option_name);
				$current_value_exists = isset($this->options[$option_name_clean]);

				$value_to_set  = is_array($definition) && isset($definition['value']) ? $definition['value'] : $definition;
				$autoload_hint = is_array($definition) && isset($definition['autoload_hint']) ? $definition['autoload_hint'] : ($this->options[$option_name_clean]['autoload_hint'] ?? null);

				// Set if new, or if existing value is different (for complex types, this is a simple check).
				if (!$current_value_exists || ($current_value_exists && $this->options[$option_name_clean]['value'] !== $value_to_set)) {
					$this->options[$option_name_clean] = array('value' => $value_to_set, 'autoload_hint' => $autoload_hint);
					$options_changed                   = true;
					// @codeCoverageIgnoreStart
					if ($this->get_logger()->is_active()) {
						$this->get_logger()->debug("RegisterOptions: Initial option '{$option_name_clean}' set/updated.");
					}
					// @codeCoverageIgnoreEnd
				}
			}

			if ($options_changed) {
				$this->save_all_options();
				// @codeCoverageIgnoreStart
				if ($this->get_logger()->is_active()) {
					$this->get_logger()->debug("RegisterOptions: Initial options processed and saved to '{$this->main_wp_option_name}'.");
				}
				// @codeCoverageIgnoreEnd
			}
		}
	}

	/**
	 * Saves all currently held options to the database under the main option name.
	 *
	 * @return bool True if the option was successfully updated or added, false otherwise.
	 */
	private function save_all_options(): bool {
		// @codeCoverageIgnoreStart
		if ($this->get_logger()->is_active()) {
			$this->get_logger()->debug("RegisterOptions: Saving all options to '{$this->main_wp_option_name}'. Total sub-options: " . count($this->options) . '. Autoload: ' . ($this->main_option_autoload ? 'true' : 'false'));
		}
		// @codeCoverageIgnoreEnd
		return \update_option($this->main_wp_option_name, $this->options, $this->main_option_autoload);
	}

	/**
	 * Sets or updates a specific option's value within the main options array and saves all options.
	 *
	 * @param string     $option_name The name of the sub-option to set. Spaces will be replaced by underscores.
	 * @param mixed      $value       The value for the sub-option.
	 * @param bool|null  $autoload_hint Optional. A hint for whether this specific sub-option might have been intended for autoloading (for metadata purposes only).
	 * @return bool True if the options were successfully saved, false otherwise.
	 */
	public function set_option(string $option_name, mixed $value, ?bool $autoload_hint = null): bool {
		$option_name_clean = \str_replace(' ', '_', $option_name);

		// @codeCoverageIgnoreStart
		if ($this->get_logger()->is_active()) {
			$this->get_logger()->debug("RegisterOptions: Setting option '{$option_name_clean}' in '{$this->main_wp_option_name}'.");
		}
		// @codeCoverageIgnoreEnd

		$this->options[$option_name_clean] = array(
			'value'         => $value,
			'autoload_hint' => $autoload_hint ?? ($this->options[$option_name_clean]['autoload_hint'] ?? null)
		);
		return $this->save_all_options();
	}

	/**
	 * Retrieves a specific option's value from the main options array.
	 *
	 * @param string $option_name The name of the sub-option to retrieve. Spaces will be replaced by underscores.
	 * @param mixed  $default     Optional. Default value to return if the sub-option does not exist.
	 * @return mixed The value of the sub-option, or the default value if not found.
	 */
	public function get_option(string $option_name, mixed $default = false): mixed {
		$option_name_clean = \str_replace(' ', '_', $option_name);
		$value             = $this->options[$option_name_clean]['value'] ?? $default;

		// @codeCoverageIgnoreStart
		if ($this->get_logger()->is_active()) {
			$log_value = is_scalar($value) ? (string) $value : (is_array($value) ? 'Array' : 'Object');
			if (strlen($log_value) > 100) {
				$log_value = substr($log_value, 0, 97) . '...';
			}
			$found_status = isset($this->options[$option_name_clean]['value']) ? 'Found' : 'Not found, using default';
			$this->get_logger()->debug("RegisterOptions: Getting option '{$option_name_clean}' from '{$this->main_wp_option_name}'. Status: {$found_status}. Value: {$log_value}");
		}
		// @codeCoverageIgnoreEnd
		return $value;
	}

	/**
	 * Updates a specific option's value. Alias for set_option.
	 *
	 * @param string     $option_name The name of the sub-option to update.
	 * @param mixed      $value       The new value for the sub-option.
	 * @param bool|null  $autoload_hint Optional. Autoload hint for the sub-option.
	 * @return bool True if options were saved successfully, false otherwise.
	 */
	public function update_option(string $option_name, mixed $value, ?bool $autoload_hint = null): bool {
		return $this->set_option($option_name, $value, $autoload_hint);
	}

	/**
	 * Returns the entire array of options currently held by this instance.
	 *
	 * @return array<string, array{"value": mixed, "autoload_hint": bool|null}> The array of all sub-options.
	 */
	public function get_options(): array {
		// @codeCoverageIgnoreStart
		if ($this->get_logger()->is_active()) {
			$this->get_logger()->debug("RegisterOptions: Getting all options from '{$this->main_wp_option_name}'. Count: " . count($this->options));
		}
		// @codeCoverageIgnoreEnd
		return $this->options;
	}

	/**
	 * Refreshes the local options cache by reloading them from the database.
	 */
	public function refresh_options(): void {
		// @codeCoverageIgnoreStart
		if ($this->get_logger()->is_active()) {
			$this->get_logger()->debug("RegisterOptions: Refreshing options from database for '{$this->main_wp_option_name}'.");
		}
		// @codeCoverageIgnoreEnd
		$this->options = \get_option($this->main_wp_option_name, array());
	}

	/**
	 * Retrieves the logger instance.
	 *
	 * @return Logger The logger instance.
	 */
	protected function get_logger(): Logger {
		// @codeCoverageIgnoreStart
		if (null === $this->logger) {
			// Attempt to get the logger from the concrete Config class instance.
			// This enforces that the Config system must be initialized and provide the logger.
			if (!class_exists(\Ran\PluginLib\Config\Config::class) || !method_exists(\Ran\PluginLib\Config\Config::class, 'get_instance')) {
				throw new \LogicException(static::class . ': \Ran\PluginLib\Config\Config class or its get_instance method is not available to retrieve the logger.');
			}

			$config_instance = \Ran\PluginLib\Config\Config::get_instance();

			if (!method_exists($config_instance, 'get_logger')) {
				throw new \LogicException(static::class . ': The Config instance (retrieved from \Ran\PluginLib\Config\Config::get_instance()) does not have a get_logger method.');
			}

			$logger_from_config = $config_instance->get_logger(); // This itself should throw if logger not initialized in Config

			if (null === $logger_from_config) {
				// This case should ideally be prevented by Config::get_logger() throwing an exception if it cannot provide a logger.
				throw new \LogicException(static::class . ': Failed to retrieve a valid logger instance from Config. Config::get_logger() returned null.');
			}
			$this->logger = $logger_from_config;
		}
		// @codeCoverageIgnoreEnd
		return $this->logger;
	}
}
