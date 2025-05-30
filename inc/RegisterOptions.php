<?php
/**
 * WordPress Options Registration and Management.
 *
 * @package  RanPluginLib
 */

declare(strict_types = 1);

namespace Ran\PluginLib;

/**
 * Activation class that establishes and manages WordPress options.
 *
 * This class provides methods for registering, retrieving, and updating WordPress options.
 */
final class RegisterOptions {
	/**
	 * An object store of options to be saved to the WP Options table.
	 *
	 * @var array<string, array<int|string, mixed>> An array of WordPress options.
	 */
	private array $options = array();

	/**
	 * The main WordPress option name that stores all plugin settings.
	 *
	 * @var string
	 */
	private string $main_wp_option_name;

	/**
	 * Creates new RegisterOptions object, with a provided array of options.
	 *
	 * @param  array<string, array<string, mixed>> $options An array options to be set to WordPress options table.
	 * @throws \Exception If invalid options array is provided.
	 */
	public function __construct( string $main_wp_option_name, array $options = array() ) {
		$this->main_wp_option_name = $main_wp_option_name;
		$this->options             = get_option( $this->main_wp_option_name, array() ); // Fetch existing options.

		// If options are provided at instantiation, add/update them.
		if ( ! empty( $options ) ) {
			foreach ( $options as $option_name => $definition ) {
				if ( is_array( $definition ) ) {
					// If $definition is an array, check if it's a structured definition
					// with 'value' and/or 'autoload' keys, or if it's the value itself.
					$actual_value     = $definition['value'] ?? $definition;
					$autoload_setting = $definition['autoload'] ?? null; // Default to null if not specified.
					$this->set_option( $option_name, $actual_value, $autoload_setting );
				} else {
					// If $definition is not an array, treat it as the direct value.
					$this->set_option( $option_name, $definition, null ); // Autoload defaults to null.
				}
			}
			// After processing all initial options, save them.
			$this->register_options();
		}
	}

	/**
	 * Registers all plugin options with values present in the options array in wp_options table.
	 */
	public function register_options(): void {
		/**
		 * Registers all options with the array key becoming the option name. If the value is an array, then we look for 'autoload' key and set it if present. Defaults to true as does WordPress.
		 */
		foreach ( $this->options as $key => $value ) {
			$option_key = array_key_first( $value );
			$this->register_option( $option_key, $value[ $option_key ], $value['autoload'] ?? true );
		}
	}

	/**
	 * Add an option to the plugin options array.
	 * Will later be set to WordPress add_option using this classes register_options() method.
	 * https://developer.wordpress.org/reference/functions/add_option/
	 *
	 * @param  string $option_name The name of the option to be set. Spaces will be replaced by underscores.
	 * @param  mixed  $value The (serializable) value of the option being set. Expected to not be SQL-escaped.
	 * @param  mixed  $autoload Optional. Whether to load the option when WordPress starts up. Default null.
	 * @return int|bool  Returns true or false if option is updated successfully, -1 is returned if user does not have permissions.
	 */
	public function set_option( string $option_name, mixed $value, mixed $autoload = null ): mixed {
		$value             = array( $value );
		$option_name       = \str_replace( ' ', '_', $option_name ); // Spaces to underscores.
		$registered        = $this->register_option( $option_name, $value[0], $autoload );
		$value['autoload'] = $autoload;
		if ( true === $registered || -1 !== $registered ) {
			$this->options[ $option_name ] = $value;
		}
		return $registered;
	}

	/**
	 * Retrieves an option value from the WordPress options table.
	 *
	 * @param  string $option_name The name of the option to retrieve. Spaces will be replaced by underscores.
	 * @param  mixed  $default     Optional. Default value to return if the option does not exist.
	 *
	 * @return mixed The value of the option, or the default value if option not found.
	 */
	public function get_option( string $option_name, mixed $default = false ): mixed {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return -1;
		}

		$option_name = \str_replace( ' ', '_', $option_name ); // Spaces to underscores.

		// Check if we have it in our local cache first.
		if ( isset( $this->options[ $option_name ] ) && isset( $this->options[ $option_name ][0] ) ) {
			return $this->options[ $option_name ][0];
		}

		// Otherwise get it directly from WordPress.
		return \get_option( $option_name, $default );
	}

	/**
	 * Updates an existing WordPress option.
	 *
	 * @param string               $option_name The name of the option to be set. Spaces will be replaced by underscores.
	 * @param array<string, mixed> $value An array containing the (serializable) value of the option being set. Expected to not be SQL-escaped.
	 * @param mixed                $autoload null|true|false|'yes'|'no'.
	 * @return bool|int Returns true or false if option is updated successfully, -1 is returned if user does not have permissions.
	 */
	public function update_option( string $option_name, array $value, mixed $autoload = null ): bool|int {
		return $this->set_option( $option_name, $value, $autoload );
	}

	/**
	 * Register the incoming value in the wp_options table.
	 * update_option() creates the option if it doesn't exist.
	 * User must be able to 'activate_plugins'.
	 *
	 * @param mixed $option_name The name of the option to be updated. Spaces will be replaced by underscores.
	 * @param mixed $value The (serializable) value of the option being set. Expected to not be SQL-escaped.
	 * @param mixed $autoload null|true|false|'yes'|'no'.
	 * @return bool|int Returns true or false if option is updated successfully, -1 is returned if user does not have permissions.
	 */
	private function register_option( mixed $option_name, mixed $value, mixed $autoload = null ): bool|int {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return -1;
		}
		// Either update the option or create it.
		return update_option( $option_name, $value, $autoload );
	}

	/**
	 * Returns the array of options set on RegisterOptions.
	 *
	 * @return array<string, array<int|string, mixed>> The array of registered options.
	 */
	public function get_options(): array {
		$this->refresh_options();
		return $this->options;
	}

	/**
	 * Refreshes all options by synchronizing the local cache with the WordPress options table.
	 */
	private function refresh_options(): void {
		foreach ( $this->options as $option => $value ) {
			$this->refresh_option( $option );
		}
	}

	/**
	 * Refreshes a single option by synchronizing the local cache with the WordPress options table.
	 *
	 * @param string $option The option name to refresh.
	 *
	 * @return mixed|null The refreshed option value or null if not found.
	 */
	private function refresh_option( string $option ): mixed {
		// Get the current value from WordPress options table.
		$wp_opt_table_val = \get_option( $option, null );

		// If the option doesn't exist in our local cache but exists in the database.
		if ( ! isset( $this->options[ $option ] ) && null !== $wp_opt_table_val ) {
			// Add it to our local cache.
			$this->options[ $option ] = array( $wp_opt_table_val );
			return $wp_opt_table_val;
		}

		// If we have a value in our cache that the database doesn't have.
		if ( isset( $this->options[ $option ] ) && null === $wp_opt_table_val ) {
			// Try to set the option in the database.
			$autoload = $this->options[ $option ]['autoload'] ?? null;
			$this->register_option( $option, $this->options[ $option ][0], $autoload );
			return $this->options[ $option ][0];
		}

		// If both exist but values are different, update our local cache.
		if ( isset( $this->options[ $option ] ) && null !== $wp_opt_table_val && $wp_opt_table_val !== $this->options[ $option ][0] ) {
			$this->options[ $option ][0] = $wp_opt_table_val;
		}

		return isset( $this->options[ $option ] ) ? $this->options[ $option ][0] : null;
	}
}
