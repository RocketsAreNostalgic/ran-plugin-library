<?php
/**
 * @package  RanPluginLib
 */

namespace Ran\PluginLib;

/**
 * Activation class that establishes the
 *
 * @package  RanPlugin
 */
final class RegisterOptions {

	/**
	 * A stored array of WordPress options.
	 *
	 * @var array an array of WordPress options.
	 */
	private array $options = array();

	/**
	 * Creates new RegisterOptions object, with a provided array of options.
	 *
	 * @param  array $options an array of WordPress options.
	 */
	public function __construct( array $options ) {
		$this->options = $options;
	}

	/**
	 * Registers all plugin options with values present in the options array with WordPress.
	 * Should be run before the plugin exits.
	 *
	 * @return void
	 */
	public function register_all_options(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		foreach ( $this->options as $option_name => $value ) {
			// if it already exists, don't overwrite it
			if ( ! get_option( $option_name ) ) {
				update_option( $option_name, $value );
			}
		}
	}

	/**
	 * Add an option to the plugin options array. Will later be set to WordPress add_option using this classes register_options() method.
	 * https://developer.wordpress.org/reference/functions/add_option/
	 *
	 * @param  string $option_name The name of the option to be updated.
	 * @param  mixed  $value The (serializable) value of the option being set. Expected to not be SQL-escaped.
	 *
	 * @return void
	 */
	public function add_option( string $option_name, mixed $value ) {

		$this->options[ $option_name ] = $value;
	}
}
