<?php
/**
 * Abstract implementation of Config class.
 *
 * @package  RanConfig
 */

declare(strict_types=1);

namespace Ran\PluginLib\Config;

use Exception;
use Ran\PluginLib\Config\ConfigInterface;

/**
 * Config class collates basic information about the environment using the WordPress docblock in the plugin root.
 * This class must be passed the path to the root file, typically __FILE__  during instantiation.
 * This class relies heavily on the results of get_plugin_data() which involves a file system read, fetch the Config's docblock headers.
 * As such the Config class is best instantiated once and then passed using dependency injection.
 */
abstract class ConfigAbstract implements ConfigInterface {

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
	 * @var array $plugin_array
	 */
	public readonly array $plugin_array;

	/**
	 * Constructor for the plugin for the Config object.
	 *
	 * @param string $plugin_file Is the plugin root file or __FILE__.
	 */
	public function __construct( string $plugin_file ) {

		$plugin_array['PATH'] = plugin_dir_path( dirname( __DIR__, 4 ) );
		$plugin_array['URL'] = plugin_dir_url( dirname( __DIR__, 4 ) );
		$plugin_array['FileName'] = plugin_basename( $plugin_file );
		$plugin_array['File'] = $plugin_file;

		if ( ! function_exists( 'get_plugin_array' ) ) {
			// @codeCoverageIgnoreStart
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			// @codeCoverageIgnoreEnd
		}
		// Get header data from plugin docblock.
		$data = get_plugin_data( $plugin_file );

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
	 * Returns the array of plugin properties.
	 *
	 * @return array plugin array
	 */
	public function get_plugin(): array {
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
	 * @param  array $plugin_array The plugin data array.
	 *
	 * @return array|Exception Returns the validated array provided, or throws an exception.
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
						'Ran Config Header is missing assignment: %s.',
						esc_html( $header )
					)
				);
			}
		}
		return $plugin_array;
	}
}
