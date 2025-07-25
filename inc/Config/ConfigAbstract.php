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
use Ran\PluginLib\Util\Logger;

/**
 * Abstract base class for plugin configuration management.
 *
 * This class provides a centralized and consistent way to access plugin metadata
 * and custom configuration values throughout the plugin. It's designed to be
 * instantiated once (as a Singleton) and then passed via dependency injection or
 * accessed via its static `get_instance()` method.
 *
 * It relies on filesystem reads for header parsing, so a single instantiation
 * is recommended for performance.
 *
 * ConfigAbstract parses headers from a plugin's main file includeing:
 *   - Standard WordPress plugin headers (e.g., Name, Version, Author).
 *   - Custom plugin-specific headers, which **must** be prefixed with `@RAN:`
 *     in the plugin file's docblock (e.g., `@RAN: Log Constant Name: MY_LOG_CONST`,
 *     `@RAN: My Custom API Key: some_value`).
 *
 * During instantiation (via the `init()` method, passing the plugin's main file path),
 * it performs the following steps to populate the `public readonly $plugin_array`:
 * 1. Gathers basic plugin path and URL information.
 * 2. Parses custom headers from the plugin file's docblock:
 *    - It looks for lines starting with `@RAN:`.
 *    - The raw header name (e.g., "Log Constant Name" from `@RAN: Log Constant Name: ...`)
 *      is normalized to PascalCase, and then the prefix "RAN" is prepended to form
 *      the final array key (e.g., `RANLogConstantName`).
 *    - **Important**: Attempting to define a custom header using `@RAN:` with a name
 *      that matches a standard WordPress header (e.g., `@RAN: Version: ...` or
 *      `@RAN: Text Domain: ...`) will result in an `Exception`.
 * 3. Retrieves standard WordPress plugin headers using `get_plugin_data()`.
 * 4. Merges all gathered data into the `$plugin_array`.
 *    The merge order is: basic path/URL info, then RAN-prefixed normalized custom
 *    headers, and finally the standard WordPress headers. Standard WordPress headers
 *    will overwrite any preceding keys if a collision occurs (though collisions
 *    between standard headers and RAN-prefixed custom headers are now unlikely).
 * 5. Validates the presence and non-emptiness of essential headers. This includes standard
 *    WordPress headers (`Name`, `Version`, `PluginURI`, `TextDomain`, `DomainPath`,
 *    `RequiresWP`, `RequiresPHP`) and the `RANPluginOption` key. The `RANPluginOption`
 *    key is intended to hold the name of the WordPress options key where the plugin
 *    stores its settings. It is determined as follows:
 *    - Typically, `RANPluginOption` defaults to a sanitized
 *      version of the plugin's `TextDomain` (e.g., if TextDomain is 'my-plugin',
 *      `RANPluginOption` becomes 'my-plugin').
 *    - To overide this default behavior, the custom override `@RAN: Plugin Option: your_plugin_settings_key` can be used.
 *    If any of these required headers are missing or empty, an `Exception` is thrown.
 *
 * @throws \Exception If any of the required headers (e.g., `Name`, `Version`, `TextDomain`, `RANPluginOption`, etc.) are missing or empty.
 * @throws \Exception If an attempt is made to define a standard WordPress header using the `@RAN:` prefix (e.g., `@RAN: Version: ...`).
 */
abstract class ConfigAbstract extends Singleton implements ConfigInterface {
	/**
	 * The path to the plugin's root file.
	 *
	 * @var string Path to the main plugin file.
	 */
	private static string $plugin_file;

	/**
	 * Holds the logger instance.
	 *
	 * @var ?Logger The logger instance.
	 */
	private ?Logger $logger = null;

	/**
	 * Holds useful plugin details, including paths, URLs, filenames, and data from the WordPress plugin header.
	 * Standard WordPress headers are included, along with any custom headers defined in the plugin's main file.
	 * Custom headers are parsed, their names normalized to PascalCase (e.g., "My Custom Value" becomes `MyCustomValue`),
	 * and then merged into this array. Standard WordPress headers take precedence in case of naming conflicts after normalization.
	 *
	 * The array keys are typically PascalCase or CamelCase. Some keys for standard WordPress headers might differ
	 * from their raw header names (e.g., 'Plugin URI' header becomes 'PluginURI' key, 'Name' header becomes 'Name' key but is often referred to as 'Plugin Name').
	 * Values marked '(derived)' are generated by WordPress or this class, not directly from headers.
	 *
	 * See: https://developer.wordpress.org/plugins/plugin-basics/header-requirements/
	 *
	 * Standard and Derived Properties:
	 * ['Name'] string The official name of the plugin (from "Name" header).
	 * ['Version'] string The plugin version (from "Version" header).
	 * ['Description'] string Plugin description (from "Description" header).
	 * ['PluginURI'] string The plugin's homepage URI (from "Plugin URI" header).
	 * ['UpdatesURI'] string The plugin updates URI (from "Updates URI" header, optional).
	 * ['Title'] string (derived) HTML link for the plugin title.
	 * ['Basename'] string (derived) The base filename of the plugin.
	 * ['URL'] string (derived) The base URL to the plugin directory.
	 * ['PATH'] string (derived) The absolute server path to the plugin directory.
	 * ['PluginOption'] string (derived) The auto-generated WordPress option name for the plugin (based on TextDomain).
	 * ['Author'] string The plugin author's name (from "Author" header).
	 * ['AuthorURI'] string The author's website URI (from "Author URI" header).
	 * ['AuthorName'] string (derived) HTML link for the author's name.
	 * ['TextDomain'] string The plugin's text domain for localization (from "Text Domain" header).
	 * ['DomainPath'] string Path to translation files relative to plugin root (from "Domain Path" header).
	 * ['RequiresPHP'] string Minimum required PHP version (from "Requires PHP" header).
	 * ['RequiresWP'] string Minimum required WordPress version (from "Requires WP" header).
	 *
	 * The `$plugin_array` (accessible via `get_plugin_config()` or by direct access
     * to the public readonly `$plugin_array` property) will contain keys derived from
     * custom headers (those prefixed with `@RAN:` in the plugin file's docblock),
     * in addition to standard WordPress headers. These custom keys will be normalized
     * to PascalCase and prefixed with `RAN`.
     *
     * Examples of how custom headers translate to keys in `$plugin_array`:
     *
     * Logging Headers (defined using `@RAN:`):
     * If plugin file has `@RAN: Log Constant Name: MY_PLUGIN_DEBUG`:
     *   `$plugin_array['RANLogConstantName']` will be 'MY_PLUGIN_DEBUG'.
     * If plugin file has `@RAN: Log Request Param: my_debug_trigger`:
     *   `$plugin_array['RANLogRequestParam']` will be 'my_debug_trigger'.
     *
     * Plugin Option Header (defined using `@RAN:` or defaulted):
     * If plugin file has `@RAN: Plugin Option: my_plugin_settings`:
     *   `$plugin_array['RANPluginOption']` will be 'my_plugin_settings'.
     * If not set, `RANPluginOption` defaults (e.g., from 'TextDomain').
     *
     * Other Custom Header Example (defined using `@RAN:`):
     * If plugin file has `@RAN: My Custom API Key: xyz123`:
     *   `$plugin_array['RANMyCustomAPIKey']` will be 'xyz123'.
	 *
	 * @var array<string, mixed> $plugin_array
	 */
	public readonly array $plugin_array;


	/**
	 * Normalizes a raw header name into a camelCase or PascalCase string suitable for an array key.
	 *
	 * Examples:
	 * - "Log Constant Name" -> "LogConstantName"
	 * - "Plugin URI"        -> "PluginURI"
	 * - "X-My-Header"       -> "XMyHeader"
	 * - "requires_php"      -> "RequiresPHP"
	 *
	 * @param string $header_name The raw header name.
	 * @return string The normalized header key.
	 */
	private static function _normalize_header_key( string $header_name ): string {
		$normalized = trim( $header_name );
		$normalized = strtolower( $normalized );
		$normalized = str_replace( array( '-', '_' ), ' ', $normalized );
		$normalized = ucwords( $normalized );
		$normalized = str_replace( ' ', '', $normalized );
		return $normalized;
	}

	/**
	 * Constructor for the plugin for the Config object.
	 *
	 * @throws Exception If no plugin file is provided.
	 */
	public function __construct() {
		if ( empty( self::$plugin_file ) ) {
			// @codeCoverageIgnoreStart
			throw new Exception( 'Plugin file not set. Call init() first.' );
			// @codeCoverageIgnoreEnd
		}

		// Initialize a local array to accumulate all plugin data.
		$local_plugin_array = array();

		// Populate with basic path and URL info (available before any header parsing).
		$local_plugin_array['URL']      = \plugin_dir_url( self::$plugin_file );
		$local_plugin_array['PATH']     = \plugin_dir_path( self::$plugin_file );
		$local_plugin_array['File']     = self::$plugin_file;
		$local_plugin_array['Basename'] = \plugin_basename( self::$plugin_file );

		// 1. Fetch standard WordPress headers.
		if ( ! \function_exists( 'get_plugin_data' ) ) {
			// @codeCoverageIgnoreStart
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			// @codeCoverageIgnoreEnd
		}
		$standard_headers_data = \get_plugin_data( self::$plugin_file, false, false );
		$standard_headers_data = array_filter(
			$standard_headers_data,
			function ( $value ) {
				return '' !== $value;
			}
		);

		// 2. Read and parse custom headers from the plugin file's docblock.
		$custom_headers_data = array();
		$raw_file_content    = $this->_read_plugin_file_header_content( self::$plugin_file );
		$doc_comment_block   = '';
		if ( $raw_file_content ) {
			if ( preg_match( '/^\s*<\?php\s*\/\*\*(.*?)\*\//s', $raw_file_content, $matches ) ) {
				$doc_comment_block = $matches[1];
			} elseif ( preg_match( '/\/\*\*(.*?)\*\//s', $raw_file_content, $matches ) ) {
				$doc_comment_block = $matches[1];
			}
			if ( ! empty( $doc_comment_block ) ) {
				// Naming @RAN custom headers the same as standard WP headers is not allowed.
				$wp_standard_display_names = array(
					'Name'        => true, 'Plugin Name' => true, 'Version' => true, 'Plugin URI' => true,
					'Author'      => true, 'Author URI' => true, 'Description' => true, 'Text Domain' => true,
					'Domain Path' => true, 'Network' => true, 'Requires WP' => true, 'Requires PHP' => true,
					'Update URI'  => true,
				);
				preg_match_all( '/^[ \t\*]*@RAN:\s*(?<name>[A-Za-z0-9\s\-]+):\s*(?<value>.+)/m', $doc_comment_block, $custom_matches, PREG_SET_ORDER );
				foreach ( $custom_matches as $match ) {
					$header_name  = trim( $match['name'] );
					$header_value = trim( $match['value'] );
					if ( ! isset( $wp_standard_display_names[ $header_name ] ) ) {
						$custom_headers_data[ $header_name ] = $header_value;
					} else {
						throw new \Exception(
							sprintf(
								'Naming @RAN: custom headers the same as standard WP headers is not allowed. Problematic header: "@RAN: %s".',
								esc_html( $header_name )
							)
						);
					}
				}
			}
		}

		// 3. Merge all headers: Start with basic path/URL, add normalized custom, then overlay standard (standard takes precedence).
		$normalized_custom_headers = array();
		foreach ( $custom_headers_data as $key => $value ) {
			$normalized_custom_headers[ 'RAN' . $this->_normalize_header_key( $key ) ] = $value;
		}
		$local_plugin_array = array_merge( $local_plugin_array, $normalized_custom_headers, $standard_headers_data );

		// Generate a default plugin option name if not set, based on TextDomain.
		if ( empty( $local_plugin_array['RANPluginOption'] ) && ! empty( $local_plugin_array['TextDomain'] ) ) {
			$local_plugin_array['RANPluginOption'] = sanitize_title( $local_plugin_array['TextDomain'] );
		}

		// 4. Validate and set the final plugin_array property.
		$this->validate_plugin_array( $local_plugin_array );
		$this->plugin_array = $local_plugin_array;
	}

	/**
	 * Reads the first 8KB of a specified plugin file to extract raw header content.
	 *
	 * This method is a wrapper around file_get_contents to allow for easier testing by mocking.
	 *
	 * @param string $file_path The full path to the plugin file.
	 * @return string|false The raw content of the plugin file's header (first 8KB), or false on failure.
	 */
	// @codeCoverageIgnoreStart
	protected function _read_plugin_file_header_content( string $file_path ): string|false {
		return file_get_contents( $file_path, false, null, 0, 8 * 1024 ); // Read first 8KB.
	}
	// @codeCoverageIgnoreEnd

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
	 * @return mixed the value of the option key (string or array), or false.
	 */
	public function get_plugin_options( string $option, mixed $default = false ): mixed {
		// @codeCoverageIgnoreStart
		if ( ! function_exists( 'get_option' ) ) {
			return -1;
		}
		// @codeCoverageIgnoreEnd

		$option = $this->plugin_array['RANPluginOption'];
		if ( empty( $option ) ) {
			$option = null;
		}

		$option = get_option( $option, $default );

		return $option;
	}

	/**
	 * Returns an instance of the Logger.
	 * Lazily instantiates the logger if it hasn't been already.
	 *
	 * @return Logger The logger instance.
	 */
	public function get_logger(): Logger {
		if ( null === $this->logger ) {
			$logger_text_domain      = $this->plugin_array['TextDomain'];
			$logger_default_constant = strtoupper( str_replace( '-', '_', sanitize_title( $logger_text_domain ) ) ) . '_DEBUG_MODE';
			$logger_constant_name    = $this->plugin_array['RANLogConstantName'] ?? $logger_default_constant;
			$logger_request_param    = $this->plugin_array['RANLogRequestParam'] ?? $logger_constant_name;
			$logger_config           = array(
				'custom_debug_constant_name' => $logger_constant_name,
				'debug_request_param'        => $logger_request_param,
			);
			$this->logger = new Logger( $logger_config );

			// @codeCoverageIgnoreStart
			if ($this->logger->is_active()) {
				$this->logger->debug('ConfigAbstract: Final plugin_array: ' . print_r($this->plugin_array, true));
			}
			// @codeCoverageIgnoreEnd
		}
		return $this->logger;
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
			'DomainPath',
			'RequiresWP',
			'RequiresPHP',
			'RANPluginOption', // Set by class, if not specified by user.
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

	/**
	 * Returns the developer-defined callback for checking if the environment is 'dev'.
	 *
	 * @return callable|null The callback function, or null if not set.
	 */
	public function get_is_dev_callback(): ?callable {
		$callback = $this->plugin_array['is_dev_callback'] ?? null;

		if (is_callable($callback)) {
			return $callback;
		}

		return null;
	}

	/**
	 * Checks if the current environment is considered a 'development' environment.
	 *
	 * @return bool True if it's a development environment, false otherwise.
	 */
	public function is_dev_environment(): bool {
		$callback = $this->get_is_dev_callback();

		if (is_callable($callback)) {
			return (bool) $callback();
		}

		return defined('SCRIPT_DEBUG') && SCRIPT_DEBUG;
	}
}
