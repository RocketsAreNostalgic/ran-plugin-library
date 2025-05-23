<?php
/**
 * Abstract implementation of Config class.
 *
 * @package  RanConfig
 */

declare(strict_types = 1);

namespace Ran\PluginLib\Config;

use Exception;
use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\Singleton\Singleton;

/**
 * Config class collates basic information about the environment using the WordPress docblock in the plugin root.
 * This class must be passed the path to the root file, typically __FILE__  during instantiation.
 * This class relies heavily on the results of get_plugin_data() which involves a file system read, fetch the Config's docblock headers.
 * As such the Config class is best instantiated once and then passed using dependency injection.
 */
abstract class ConfigAbstract extends Singleton implements ConfigInterface {

	/**
	 * The path to the plugin's root file.
	 *
	 * @var string Path to the main plugin file.
	 */
	private static string $plugin_file;

	/**
	 * Holds useful plugin details, including paths, URL's and filenames, plus details pulled from WordPress plugin header.
	 * This list may change depending on what is included in the plugin header. Note that this EXTENDS the WordPress minimum header requirements.
	 * * Array keys are in CamelCase, where many have representational space separated DockBlock names within the plugin header.
	 * *  with Exception of "Name" which is identified as "Config Name". Other marked 'derived' are either created by WordPress itself or in our constructor.
	 * * https://developer.wordpress.org/plugins/plugin-basics/header-requirements/
	 *
	 * ['Name'] The name of the plugin (declared as 'Config Name' in headers).
	 * ['Version'] The version of the plugin.
	 * ['Description'] The description of the plugin.
	 * ['ConfigURI'] The project URI for the plugin.
	 * ['UpdatesURI] The plugin updates URI, not required if hosted on the WP Config Directory.
	 * ['Title'] (derived) The title of the plugin rendered as a link pointing to the plugin URI.
	 * ['FileName'] (derived) The file name of the root plugin file.
	 * ['URL'] (derived) URL to the plugin's containing directory within WordPress.
	 * ['PATH'] (derived) Full file path to the plugin's containing directory.
	 * ['PluginOption'] (derived) The name of the plugin in the WP options table, which is auto generated from the text domain in lower_snake_case.
	 * ['Author'] The author of the plugin.
	 * ['AuthorURI'] The author's webpage URI.
	 * ['AuthorName'] (derived) The author of the plugin rendered as a link pointing to the author URI.
	 * ['TextDomain'] (optional|derived) The text domain of the plugin, used as the plugin slug in urls.
	 * ['DomainPath'] The path to the plugin translation files.
	 * ['RequiresPHP'] The required PHP version of the plugin.
	 * ['RequiresWP'] The required WordPress version of the plugin.
	 *
	 * @var array<string, mixed> $plugin_array
	 */
	public readonly array $plugin_array;


	/**
	 * Constructor for the plugin for the Config object.
	 *
	 * @throws Exception If no plugin file is provided.
	 */
	protected function __construct() {

		if ( empty( self::$plugin_file ) ) {
			// @codeCoverageIgnoreStart
			throw new Exception( 'Ran PluginLib: No plugin file provided. First call ::init(path/to/entrance/plugin-name.php)' );
			// @codeCoverageIgnoreEnd
		}

		$plugin_array['PATH'] = plugin_dir_path( dirname( __DIR__, 4 ) );
		$plugin_array['URL'] = plugin_dir_url( dirname( __DIR__, 4 ) );
		$plugin_array['FileName'] = plugin_basename( self::$plugin_file );
		$plugin_array['File'] = self::$plugin_file;

		if ( ! function_exists( 'get_plugin_array' ) ) {
			// @codeCoverageIgnoreStart
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			// @codeCoverageIgnoreEnd
		}
		// Get header data from plugin docblock.
		$data = get_plugin_data( self::$plugin_file, false, false );

		// if debug is enabled, log the data.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Ran PluginLib: Config Data from ' . self::$plugin_file . ': ' . print_r( $data, true ) );
		}

		// Merge in the plugin docblock data.
		$plugin_array = array_merge( $plugin_array, $data );

		// Add plugin option name with snake case.
		$text_domain = array( 'PluginOption' => str_replace( '-', '_', sanitize_title( $plugin_array['TextDomain'] ) ) );
		$plugin_array = array_merge( array_slice( $plugin_array, 0, 9 ), $text_domain, array_slice( $plugin_array, 9 ) );

		// Validate that we have all the headers required for our plugin.
		$this->validate_plugin_array( $plugin_array );
		$this->plugin_array = $plugin_array;
	}

	/**
	 * Initializes and returns an instance of the Config object.
	 *
	 * @param string $plugin_file The path to the plugin's root file.
	 * @return Singleton The current instance of the Config object.
	 */
	public static function init( string $plugin_file ): Singleton {
		self::$plugin_file = $plugin_file;
		return self::get_instance();
	}


	/**
	 * Returns the array of plugin properties.
	 *
	 * @return array<string, mixed> Plugin configuration array with various properties.
	 */
	public function get_plugin_config(): array {
		return $this->plugin_array;
	}

	/**
	 * Returns the value of the current plugins primary WordPress option, or false if none has been set.
	 * * You can optionally pass in any string value to see if an option by that name has been set,
	 * * or a Option and Key to see if a specific key has been set.
	 *
	 * @param  string $option The option name, defaults to the text domain of the current plugin.
	 *
	 * @param  mixed  $default Optional. Default value to return if the option does not exist.
	 *
	 * @return mixed the value of the option key (string or array), or false
	 */
	public function get_plugin_options( string $option, mixed $default = false ): mixed {
		if ( ! function_exists( 'get_option' ) ) {
			return -1;
		}

		$option = $this->plugin_array['PluginOption'];
		if ( empty( $option ) ) {
			$option = null;
		}

		$option = get_option( $option, $default );

		return $option;
	}

	/**
	 * Validate that the root plugin array has the required fields set.
	 * These are gathered from the plugin docblock using the WP get_plugin_array().
	 *
	 * @param  array<string, mixed> $plugin_array The plugin data array.
	 *
	 * @return array<string, mixed>|Exception Returns the validated array provided, or throws an exception.
	 * @throws \Exception Throws if the minimum headers have not been set.
	 */
	public function validate_plugin_array( array $plugin_array ): array|Exception {
		/**
		 * The minimum headers to be set in the plugin docblock.
		 */
		$required_headers = array(
			'Name',
			'Version',
			'PluginURI',
			'TextDomain',
			'PluginOption',
			'DomainPath',
			'RequiresPHP',
			'RequiresWP',
		);
		foreach ( $required_headers as $header ) {
			if ( ! array_key_exists( $header, $plugin_array ) || empty( $plugin_array[ $header ] ) ) {
				throw new \Exception(
					\sprintf(
						'RanPluginLib: Config Header is missing assignment: "%s".',
						esc_html( $header )
					)
				);
			}
		}
		return $plugin_array;
	}
}
