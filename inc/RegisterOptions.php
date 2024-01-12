<?php

/**
 * @package  RanPluginLib
 */

declare(strict_types=1);

namespace Ran\PluginLib;

/**
 * Activation class that establishes the
 *
 * @package  RanPlugin
 */
final class RegisterOptions
{
	/**
	 * An object store of options to be saved to the WP Options table.
	 *
	 * @var array an array of WordPress options.
	 */
	private array $options = array();

	/**
	 * Creates new RegisterOptions object, with a provided array of options.
	 *
	 * @param  array $options an array options to be set to WordPress options table.
	 */
	public function __construct(array $options = array())
	{
		foreach ($options as $key => $value) {
			if (\is_array($value)) {
				$option_key = array_key_first($value);
				if (!is_array($value[$option_key])) {
					$value[$option_key] = array($value[$option_key]);
				}
				$autoload = $value[$option_key]['autoload'] ?? null;
				$this->set_option($option_key, $value[$option_key], $autoload);
			} else {
				throw new \Exception("RegisterOptions: invalid options array provided on instantionation.");
			}
		}
	}

	/**
	 * Registers all plugin options with values present in the options array in wp_options table.
	 *
	 * @return void
	 */
	public function register_options(): void
	{
		/**
		 * Registers all options with the array key becoming the option name. If the value is an array, then we look for 'autoload' key and set it if present. Defaults to true as does WordPress.
		 *
		 */
		foreach ($this->options as $key => $value) {
			$option_key = array_key_first($value);
			$this->register_option($option_key, $value[$option_key], $value['autoload'] ?? true);
		}
	}

	/**
	 * Add an option to the plugin options array.
	 * Will later be set to WordPress add_option using this classes register_options() method.
	 * https://developer.wordpress.org/reference/functions/add_option/
	 *
	 * @param  string $option_name The name of the option to be set. Spaces will be replaced by underscores.
	 * @param  mixed  $value The (serializable) value of the option being set. Expected to not be SQL-escaped.
	 * @param mixed $autoload
	 * @return int|bool  Returns true or false if option is updated successfully, -1 is returned if user does not have permissions.
	 */
	public function set_option(string $option_name, mixed $value, $autoload = null): mixed
	{
		$value = array($value);
		$option_name = \str_replace(' ', '_', $option_name); // Spaces to underscores.
		$registered = $this->register_option($option_name, $value[0], $autoload = null);
		$value['autoload'] = $autoload;
		if ($registered || $registered !== -1) {
			$this->options[$option_name] = $value;
		}
		return $registered;
	}

	public function get_option($option_name): mixed
	{
	}

	/**
	 *
	 * @param string $option_name $option_name The name of the option to be set. Spaces will be replaced by underscores.
	 * @param array $value An array containing the (serializable) value of the option being set. Expected to not be SQL-escaped.
	 * @param mixed $autoload null|true|false|'yes'|'no'
	 * @return bool|int Returns true or false if option is updated successfully, -1 is returned if user does not have permissions.
	 */
	public function update_option(string $option_name, array $value, $autoload = null): bool|int
	{
		return $this->set_option($option_name,  $value, $autoload);
	}

	/**
	 * Register the incoming value in the wp_options table.
	 * update_option() creates the option if it doesn't exist.
	 * User must be able to 'activate_plugins'.
	 *
	 * @param mixed $option_name The name of the option to be updated. Spaces will be replaced by underscores.
	 * @param mixed $value The (serializable) value of the option being set. Expected to not be SQL-escaped.
	 * @param mixed $autoload null|true|false|'yes'|'no'
	 * @return bool|int Returns true or false if option is updated successfully, -1 is returned if user does not have permissions.
	 */
	private function register_option($option_name, $value, $autoload = null): bool|int
	{
		if (!current_user_can('activate_plugins')) {
			return -1;
		}
		// Either update the option or create it.
		return update_option($option_name, $value, $autoload);
	}

	/**
	 * Returns the the array of options set on RegisterOptions.
	 *
	 * @return array
	 */
	public function get_options(): array
	{
		$this->refresh_options();
		return $this->options;
	}

	private function refresh_options(): void
	{
		foreach ($this->options as $option => $value) {
			$this->refresh_option($option);
		}
	}

	private function refresh_option(string $option)
	{
		// echo '<pre>';
		// $wp_opt_table_val = \get_option($option);
		// echo ('option:</br>');
		// print_r($option);
		// print_r($this->options[$option]);
		// echo '</br>';
		// print_r($wp_opt_table_val);
		// echo '</pre>';

		// 	// If we have a value that the database doesn't (shouldn't ever happen)
		// 	if (!$wp_opt_table_val && $this->options[$option]) {
		// 		// Try to set any options to the table
		// 		$this->register_option($option, $value[$option_key], $value['autoload'] ?? true);
		// 	}
		// 	if ($wp_opt_table_val === $this->options[$option][0]) {
		// 		return $this->options[$option][0];
		// 	} else {
		// 		$this->options[$option][0] = $wp_opt_table_val;
		// 	}
	}
}
