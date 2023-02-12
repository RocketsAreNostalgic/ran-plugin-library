<?php
/**
 * Abstract implementation of Plugin class.
 *
 * @package  RanPlugin
 */

namespace Ran\PluginLib\Plugin;

use Ran\PluginLib\Plugin\PluginInterface;

/**
 * Plugin class collates basic information about the environment using the WordPress docblock in the plugin root.
 * This class must be passed the path to the root file __FILE__ during instantiation.
 * As this process involves a file system read, this class is best instantiated once and then passed using dependency injection.
 */
abstract class PluginAbstract implements PluginInterface {

	/**
	 * Holds useful plugin details, including paths, URL's and filenames, plus details pulled from WordPress plugin header.
	 * This list may change depending on what is included in the plugin header.
	 * https://developer.wordpress.org/plugins/plugin-basics/header-requirements/
	 *
	 * ['PATH'] Full path to the plugin directory.
	 * ['URL'] URL to the plugin directory.
	 * ['FileName'] The file name of the root plugin file.
	 * ['Name'] The name of the plugin.
	 * ['Title'] The title of the plugin rendered as a link pointing to the plugin URI.
	 * ['Description'] The description of the plugin.
	 * ['PluginURI'] The project URI for the plugin
	 * ['UpdatesURI] The plugin updates URI.
	 * ['Version'] The version of the plugin. // REQUIRED
	 * ['Author'] The author of the plugin.
	 * ['AuthorURI'] The author homepage.
	 * ['AuthorName'] The author of the plugin rendered as a link pointing to the author URI.
	 * ['PluginOption'] The name of the plugin base option, which is generated from the text domain in snake_case.
	 * ['TextDomain'] The text domain of the plugin.
	 * ['DomainPath'] The path to the plugin translation files.
	 * ['RequiresPHP'] The required PHP version of the plugin.
	 * ['RequiresWP'] The required Wordpress version of the plugin.
	 *
	 * @var array $plugin
	 */
	private array $plugin_data = array();

	/**
	 * Constructor for the plugin for the Plugin object.
	 *
	 * @param string $plugin_file Is the plugin root file or __FILE__.
	 */
	public function __construct( $plugin_file ) {

		$this->plugin_data['PATH'] = plugin_dir_path( dirname( __FILE__, 5 ) );
		$this->plugin_data['URL'] = plugin_dir_url( dirname( __FILE__, 5 ) );
		$this->plugin_data['FileName'] = plugin_basename( $plugin_file );
		$this->plugin_data['File'] = $plugin_file;

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}
		// Get header data from plugin docblock.
		$data = \get_plugin_data( $plugin_file );

		// Merge in the plugin docblock data.
		$this->plugin_data = array_merge( $this->plugin_data, $data );

		// Add plugin option name with snake case.
		$text_domain = array( 'PluginOption' => str_replace( '-', '_', sanitize_title( $this->plugin_data['TextDomain'] ) ) );
		$this->plugin_data = array_merge( array_slice( $this->plugin_data, 0, 9 ), $text_domain, array_slice( $this->plugin_data, 9 ) );

		// Validate that we have all the headers required for our plugin.
		$this->validate_plugin_headers( $this->plugin_data );
	}

	/**
	 * Returns the array of plugin properties.
	 *
	 * @return array plugin array
	 */
	public function get_plugin(): array {
		return $this->plugin_data;
	}

	/**
	 * Returns the value of the current plugins primary option or false if its not set.
	 * You can optionally pass in any string value to see if an option of that name has been set, or a Option and Key to see if a specific key has been set.
	 *
	 * @param  string $option The option name, defaults to the text domain of the current plugin.
	 *
	 * @param  mixed  $default Optional. Default value to return if the option does not exist.
	 *
	 * @return mixed the value of the option key (string or array), or false
	 */
	public function get_plugin_options( string $option, mixed $default = false ): mixed {
		if ( ! function_exists( 'get_option' ) ) {
			return -1; }

		$option = empty( $option ) ?: $this->plugin_data['TextDomain'];

		$option = get_option( $option, $default );

		return $option;
	}

	/**
	 * Validate the root plugin data array file has the required fields set.
	 * These gathered from the plugin docblock using the WP get_plugin_data() function.
	 *
	 * @param  array $plugin_data The plugin data array.
	 *
	 * @return void
	 * @throws \Exception Throws if the minimum headers have not been set.
	 */
	private function validate_plugin_headers( array $plugin_data ): void {

		$text_domain = $this->plugin_data['TextDomain'];
		$required_headers = array(
			'Name',
			'PluginURI',
			'Version',
			'Description',
			'Author',
			'PluginOption',
			'TextDomain',
			'RequiresPHP',
			'UpdateURI',
			'Title',
			'AuthorName',
		);
		foreach ( $required_headers as $header ) {
			if ( ! array_key_exists( $header, $plugin_data ) || empty( $plugin_data[ $header ] ) ) {
				throw new \Exception( \sprintf( 'Ran Plugin Header must contain %s assignment.', $header ) );
				wp_die();
			}
		}
	}
}
