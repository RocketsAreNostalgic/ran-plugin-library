<?php
/**
 * Logger class for WordPress plugins.
 *
 * @package Ran\PluginLib\Util
 */

declare(strict_types = 1);

namespace Ran\PluginLib\Util;

/**
 * A lightweight, configurable logger for WordPress plugins.
 *
 * This logger allows recording messages at various severity levels. Activation
 * and the active log level are controlled by a PHP constant or a URL parameter,
 * whose names are specified during instantiation. All log messages are directed
 * to PHP's standard `error_log()` function. To ensure these logs are written to the
 * WordPress `wp-content/debug.log` file, both `WP_DEBUG` and `WP_DEBUG_LOG` must be
 * set to `true` in your `wp-config.php` file.
 *
 * @link https://deliciousbrains.com/why-use-wp-debug-log-wordpress-development/
 *
 * --- Configuration ---
 * The logger is configured via an associative array passed to its constructor.
 * Key parameters:
 * - 'custom_debug_constant_name': (string, required) The name of the PHP constant
 *   that will be checked to activate logging and set the log level.
 *   Example: 'MY_PLUGIN_DEBUG_MODE'.
 * - 'debug_request_param': (string, optional) The name of the URL query parameter
 *   that can also activate logging and set the log level. If not provided, it
 *   defaults to the value of 'custom_debug_constant_name'.
 *   Example: 'my_plugin_debug'.
 *
 * --- Activation and Log Levels ---
 * Logging is activated if either the specified PHP constant is defined or the
 * specified URL parameter is present in the request. The value of the constant
 * or URL parameter determines the *minimum* severity level that will be logged.
 * URL parameters take precedence over PHP constants if both are present and valid.
 *
 * Accepted values for the constant or URL parameter:
 * - Textual levels (case-insensitive): 'DEBUG', 'INFO', 'WARNING', 'ERROR'.
 * - Numeric levels (corresponding to textual levels): 100 (DEBUG), 200 (INFO), 300 (WARNING), 400 (ERROR).
 * - For URL parameters:
 *   - `?param` (naked, value is empty string `''`): Activates 'DEBUG' level.
 *   - `?param=true` (or case-insensitive "1", "yes", "on"): Activates 'DEBUG' level.
 *   - Arbitrary strings (e.g., `?param=sometext`) not matching a level or explicit true: Do NOT activate logging via URL.
 * - For PHP constants:
 *   - `define('CONST', true);`: Activates 'DEBUG' level.
 *   The method checks sources in order: URL parameter, then PHP constant. The first active source found determines the level.
 * - Recognized log level strings (e.g., "DEBUG", "INFO", "WARNING", "ERROR") or their corresponding numeric values
 *   (100, 200, 300, 400) will activate logging at that specific level, case-insensitively for strings.
 * - For both URL parameters and PHP constants, the following fallback rules apply if no explicit level is matched:
 *   - An empty string value (e.g., `?your_log_param` for URLs, or `define('MY_CONST', '');` for constants) activates `DEBUG`.
 *   - An explicit true value (`true`, `1`, `yes`, `on` for strings, or boolean `true` for constants) activates `DEBUG`.
 *   - Arbitrary strings (e.g., `?your_log_param=sometext` or `define('MY_CONST', 'sometext');`), explicit false values
 *     (e.g., "false", "0", "no", "off", or boolean `false` for constants), or any other value not meeting the above
 *     criteria will NOT activate logging from that source.
 * - If a URL parameter like `?your_log_param=false` (or "0", "no", "off") is used, it explicitly deactivates logging
 *   from the URL source. If a constant is also set, the constant would then be checked against the same rules.
 * - If neither a URL parameter nor a constant activates logging according to these rules, the logger remains inactive.
 *
 * Examples:
 * (Assuming `debug_request_param` is 'log_my_plugin' and `custom_debug_constant_name` is 'LOG_MY_PLUGIN_LEVEL')
 *
 * /// 1. No logging active:
 * /// (No relevant URL parameter or constant set)
 *
 * /// 2. Constant activates INFO, URL param is not set:
 * /// define('LOG_MY_PLUGIN_LEVEL', 'INFO');
 * /// Result: Logs INFO, WARNING, ERROR
 *
 * /// 3. Activating logging via a URL parameter:
 * /// ?log_my_plugin         /// (Naked parameter, value is '') Logs DEBUG and above
 * /// ?log_my_plugin=WARNING  /// Logs WARNING, ERROR (case-insensitive)
 * /// ?log_my_plugin=300      /// Equivalent to 'WARNING'
 * /// ?log_my_plugin=true     /// Logs DEBUG and above
 * /// ?log_my_plugin=sometext /// (Inactive, arbitrary strings no longer default to DEBUG)
 *
 * /// 4. Activating logging via a PHP constant (now follows same rules as URL params):
 * /// define('LOG_MY_PLUGIN_LEVEL', true);       // Logs DEBUG and above (boolean true)
 * /// define('LOG_MY_PLUGIN_LEVEL', '');         // Logs DEBUG and above (empty string)
 * /// define('LOG_MY_PLUGIN_LEVEL', 'ERROR');    // Logs ERROR only (case-insensitive)
 * /// define('LOG_MY_PLUGIN_LEVEL', 400);        // Equivalent to 'ERROR'
 * /// define('LOG_MY_PLUGIN_LEVEL', 'anytext');  // (Inactive, arbitrary strings no longer default to DEBUG)
 * /// define('LOG_MY_PLUGIN_LEVEL', false);      // (Inactive, boolean false)
 *
 * /// 5. Logging messages:
 * if ($logger->is_active()) { // Optional: check if logger is active
 *     $logger->debug('This is a detailed debug message for developers.');
 *     $logger->info('Informational message about an event.', ['user_id' => 123]);
 *     $logger->warning('Something might be wrong, but not critical yet.');
 *     $logger->error('A critical error occurred!', ['error_code' => 500]);
 * }
 *
 * /// Logging methods accept a message string and an optional context array.
 * /// The context array is serialized and appended to the log message.
 */
class Logger {
	public const LEVEL_DEBUG   = 'DEBUG';   // 100
	public const LEVEL_INFO    = 'INFO';    // 200
	public const LEVEL_WARNING = 'WARNING'; // 300
	public const LEVEL_ERROR   = 'ERROR';   // 400

	public const LOG_LEVELS_MAP = array(
		self::LEVEL_DEBUG   => 100,
		self::LEVEL_INFO    => 200,
		self::LEVEL_WARNING => 300,
		self::LEVEL_ERROR   => 400,
	);

	/**
	 * Logger configuration array.
	 *
	 * Holds settings like custom debug constant name and request parameter.
	 * Example: ['custom_debug_constant_name' => 'MY_PLUGIN_DEBUG_MODE', 'debug_request_param' => 'my_plugin_debug']
	 *
	 * @var array<string, mixed> $config Configuration settings.
	 */
	private array $config;

	/**
	 * Flag indicating if logging is currently active.
	 *
	 * @var bool $is_active True if logging is active, false otherwise.
	 */
	private bool $is_active = false;

	/**
	 * The determined minimum severity level for messages to be logged.
	 *
	 * @var int $effective_log_level_severity Integer representation of the log level.
	 */
	private int $effective_log_level_severity;

	/**
	 * The name of the PHP constant used to activate logging and set the log level.
	 *
	 * @var string $custom_debug_constant_name Name of the PHP constant.
	 */
	private string $custom_debug_constant_name;

	/**
	 * The name of the URL query parameter used to activate logging and set the log level.
	 *
	 * @var string $debug_request_param Name of the URL query parameter.
	 */
	private string $debug_request_param;

	/**
	 * Logger constructor.
	 *
	 * @param array<string, mixed> $config Configuration array. Must include 'custom_debug_constant_name'.
	 *                      'debug_request_param' is optional and defaults to 'custom_debug_constant_name'.
	 */
	public function __construct( array $config ) {
		if ( empty( $config['custom_debug_constant_name'] ) ) {
			// Optionally, trigger a warning or throw an exception if this crucial config is missing.
			// For now, we'll let it proceed, but logging won't activate via constant.
			$this->custom_debug_constant_name = '';
		} else {
			$this->custom_debug_constant_name = $config['custom_debug_constant_name'];
		}

		$this->debug_request_param          = $config['debug_request_param'] ?? $this->custom_debug_constant_name;
		$this->config                       = $config;
		$this->effective_log_level_severity = self::LOG_LEVELS_MAP[ self::LEVEL_WARNING ]; // Default.

		$this->determine_effective_log_level();
	}

	/**
	 * Determines if logging should be active and at what level based on URL params or PHP constants.
	 *
	 * @since 1.0.0
	 */
	private function determine_effective_log_level(): void {
		$this->is_active                    = false;
		$this->effective_log_level_severity = self::LOG_LEVELS_MAP[ self::LEVEL_ERROR ] + 100; // Default to higher than any level.

		$numeric_to_text_level = array_flip( self::LOG_LEVELS_MAP ); // e.g., [100 => 'DEBUG', ...].

		$sources_values = array();
		// phpcs:disable Squiz.Commenting.InlineComment.InvalidEndChar -- Reading a debug param, not processing form data.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Reading a debug param, not processing form data.
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Input is checked against known values or used as a boolean flag.
		if ( ! empty( $this->debug_request_param ) && array_key_exists( $this->debug_request_param, $_GET ) ) {
			$sources_values['url'] = $this->_unslash( $_GET[ $this->debug_request_param ] );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		// phpcs:enable Squiz.Commenting.InlineComment.InvalidEndChar

		if ( ! empty( $this->custom_debug_constant_name ) && defined( $this->custom_debug_constant_name ) ) {
			$sources_values['constant'] = constant( $this->custom_debug_constant_name );
		}

		$determined_level_text = null;

		foreach ( array( 'url', 'constant' ) as $source_type ) {
			if ( ! isset( $sources_values[ $source_type ] ) ) {
				continue; // Skip if this source is not set.
			}

			$raw_value             = $sources_values[ $source_type ];
			$level_value_str_upper = is_string( $raw_value ) ? strtoupper( trim( $raw_value ) ) : null;

			// 1. Check for direct textual level match (DEBUG, INFO, etc.)
			if ( null !== $level_value_str_upper && isset( self::LOG_LEVELS_MAP[ $level_value_str_upper ] ) ) {
				$determined_level_text = $level_value_str_upper;
				break;
			}
			// 2. Check for numeric level match (100, 200, etc.)
			if ( is_numeric( $raw_value ) && isset( $numeric_to_text_level[ (int) $raw_value ] ) ) {
				$determined_level_text = $numeric_to_text_level[ (int) $raw_value ];
				break;
			}

			// 3. Unified defaulting logic for both URL parameters and Constants:
			// Activates DEBUG if the value is an empty string (naked URL param or empty constant)
			// OR if the value explicitly evaluates to boolean true (e.g., "true", "1", "yes", "on", or boolean true for constants).
			// Any other value (including arbitrary strings like "sometext", or explicit false values) will not activate logging from this source.
			if ( '' === $raw_value ) { // Naked param (URL) or empty string constant.
				$determined_level_text = self::LEVEL_DEBUG;
				break;
			}

			// Check for explicit true values (e.g., "true", "1", "yes", "on").
			// For constants, this also handles actual boolean `true`.
			$filter_val_bool = filter_var( $raw_value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
			if ( true === $filter_val_bool ) {
				$determined_level_text = self::LEVEL_DEBUG;
				break;
			}
			// If none of the above conditions were met (explicit level, empty string, or explicit true),
			// this source does not activate logging. The loop continues to check the next source, or finishes if this was the last source.
		} // end foreach source_type

		if ( null !== $determined_level_text && isset( self::LOG_LEVELS_MAP[ $determined_level_text ] ) ) {
			$this->is_active                    = true;
			$this->effective_log_level_severity = self::LOG_LEVELS_MAP[ $determined_level_text ];
		}
	}

	/**
	 * Gets the integer severity for a given log level string.
	 *
	 * @param string $level_string The log level string (e.g., 'DEBUG').
	 * @return int The integer severity.
	 */
	private function _get_level_severity( string $level_string ): int {
		return self::LOG_LEVELS_MAP[ strtoupper( $level_string ) ] ?? self::LOG_LEVELS_MAP[ self::LEVEL_DEBUG ];
	}

	/**
	 * Unslashes a string using `wp_unslash()`, if available.
	 *
	 * @param  string $value
	 *
	 * @return string
	 */
	private function _unslash( string $value ): string {
		return wp_unslash( $value );
	}

	/**
	 * Checks if the logger is currently active based on the configuration.
	 *
	 * @return bool True if logging is active, false otherwise.
	 */
	public function is_active(): bool {
		return $this->is_active;
	}

	/**
	 * Gets the current effective log level severity.
	 *
	 * Returns the integer severity value (e.g., 100 for DEBUG, 200 for INFO).
	 * Note: The logger also has an `is_active()` method. This method returns the
	 * configured severity level, which is only acted upon if `is_active()` is true.
	 *
	 * @return int The integer value of the effective log level. Returns 0 if not active or no level set.
	 */
	public function get_log_level(): int {
		return $this->effective_log_level_severity;
	}

	/**
	 * Logs a message with the DEBUG level.
	 *
	 * @param string       $message The message to log.
	 * @param array<mixed> $context Optional context data.
	 */
	public function debug( string $message, array $context = array() ): void {
		$this->log( $message, self::LEVEL_DEBUG, $context );
	}

	/**
	 * Logs a message with the INFO level.
	 *
	 * @param string       $message The message to log.
	 * @param array<mixed> $context Optional context data.
	 */
	public function info( string $message, array $context = array() ): void {
		$this->log( $message, self::LEVEL_INFO, $context );
	}

	/**
	 * Logs a message with the WARNING level.
	 *
	 * @param string       $message The message to log.
	 * @param array<mixed> $context Optional context data.
	 */
	public function warning( string $message, array $context = array() ): void {
		$this->log( $message, self::LEVEL_WARNING, $context );
	}

	/**
	 * Logs a message with the ERROR level.
	 *
	 * @param string       $message The message to log.
	 * @param array<mixed> $context Optional context data.
	 */
	public function error( string $message, array $context = array() ): void {
		$this->log( $message, self::LEVEL_ERROR, $context );
	}

	/**
	 * The core logging method.
	 *
	 * @param string       $message The message to log.
	 * @param string       $level   The log level (e.g., 'DEBUG').
	 * @param array<mixed> $context Optional context data.
	 */
	private function log( string $message, string $level, array $context = array() ): void {
		if ( ! $this->is_active ) {
			return;
		}

		$current_level_severity = $this->_get_level_severity( $level );
		if ( $current_level_severity < $this->effective_log_level_severity ) {
			return;
		}

		$formatted_message = sprintf(
			'[%s] %s', // Timestamp removed, context will append if present.
			strtoupper( $level ),
			$message
		);

		if ( ! empty( $context ) ) {
			$formatted_message .= ' Context: ' . wp_json_encode( $context );
		}

		error_log( $formatted_message );
	}
}
