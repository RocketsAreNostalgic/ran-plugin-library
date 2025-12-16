<?php
/**
 * Logger class for WordPress plugins.
 *
 * @package Ran\PluginLib\Util
 * @author  Ran Plugin Lib <bnjmnrsh@gmail.com>
 * @license GPL-2.0+ <http://www.gnu.org/licenses/gpl-2.0.txt>
 * @link    https://github.com/RocketsAreNostalgic
 * @since   0.1.0
 */

declare(strict_types = 1);

namespace Ran\PluginLib\Util;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

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
 * - 'error_log_handler': (callable, optional) A custom callable function
 *   to handle log messages. If provided, this function will be called with
 *   the formatted log string instead of PHP's `error_log()`. Useful for
 *   testing or custom log routing.
 *   Example: `function(string $message) { echo $message; }`.
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
class Logger implements LoggerInterface {
	public const LOG_LEVELS_MAP = array(
		LogLevel::DEBUG     => 100,
		LogLevel::INFO      => 200,
		LogLevel::NOTICE    => 250,
		LogLevel::WARNING   => 300,
		LogLevel::ERROR     => 400,
		LogLevel::CRITICAL  => 500,
		LogLevel::ALERT     => 550,
		LogLevel::EMERGENCY => 600,
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
	 * Optional custom error log handler.
	 *
	 * @var callable|null
	 */
	private $error_log_handler = null;

	private static ?string $request_id = null;

	/**
	 * Logger constructor.
	 *
	 * @param array<string, mixed> $config Configuration array. Must include 'custom_debug_constant_name'.
	 *                      'debug_request_param' is optional and defaults to 'custom_debug_constant_name'.
	 */
	public function __construct( array $config = array() ) {
		// Determine custom_debug_constant_name
		if ( array_key_exists( 'custom_debug_constant_name', $config ) ) {
			$this->custom_debug_constant_name = $config['custom_debug_constant_name'];
		} elseif ( isset( $config['TextDomain'] ) && ! empty( $config['TextDomain'] ) ) {
			$this->custom_debug_constant_name = strtoupper( $config['TextDomain'] ) . '_DEBUG_MODE';
		} else {
        // Neither 'custom_debug_constant_name' was explicitly passed, nor 'TextDomain' was validly passed.
			if ( empty( $config ) ) { // True if new Logger() or new Logger([]) was called.
            // For test_constructor_defaults_constant_name_to_generic.
				$this->custom_debug_constant_name = 'PLUGIN_LIB_DEBUG_MODE';
			} else {
				// For test_constructor_handles_missing_constant_name_config.
				// Config was passed but didn't include the above keys (e.g., new Logger(['foo' => 'bar'])).
				$this->custom_debug_constant_name = '';
			}
		}

		// Determine debug_request_param, defaulting to the now-set custom_debug_constant_name
		$this->debug_request_param = $config['debug_request_param'] ?? $this->custom_debug_constant_name;

		if (isset($config['error_log_handler']) && is_callable($config['error_log_handler'])) {
			$this->error_log_handler = $config['error_log_handler'];
		}

		$this->config                       = $config;
		$this->effective_log_level_severity = self::LOG_LEVELS_MAP[ LogLevel::WARNING ]; // Default.

		$this->_determine_effective_log_level();
	}

	/**
	 * Determines if logging should be active and at what level based on URL params or PHP constants.
	 *
	 * @since 0.1.0
	 */
	private function _determine_effective_log_level(): void {
		$this->is_active                    = false;
		$this->effective_log_level_severity = self::LOG_LEVELS_MAP[LogLevel::EMERGENCY] + 100; // Default to higher than any level.

		$sources_values = array();
		// phpcs:disable Squiz.Commenting.InlineComment.InvalidEndChar -- Reading a debug param, not processing form data.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Reading a debug param, not processing form data.
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Input is checked against known values or used as a boolean flag.
		if (!empty($this->debug_request_param) && array_key_exists($this->debug_request_param, $_GET)) {
			$sources_values['url'] = \wp_unslash($_GET[$this->debug_request_param]);
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		// phpcs:enable Squiz.Commenting.InlineComment.InvalidEndChar

		if (!empty($this->custom_debug_constant_name) && defined($this->custom_debug_constant_name)) {
			$sources_values['constant'] = constant($this->custom_debug_constant_name);
		}

		$determined_level_text = null;

		foreach (array('url', 'constant') as $source_type) {
			if (!isset($sources_values[$source_type])) {
				continue; // Skip if this source is not set.
			}

			$raw_value    = $sources_values[$source_type];
			$parsed_level = $this->_parse_log_level_value($raw_value);

			if (null !== $parsed_level) {
				$determined_level_text = $parsed_level;
				break;
			}
		} // end foreach source_type

		if (null !== $determined_level_text && isset(self::LOG_LEVELS_MAP[$determined_level_text])) {
			$this->is_active                    = true;
			$this->effective_log_level_severity = self::LOG_LEVELS_MAP[$determined_level_text];
		}
	}

	/**
	 * Parses a raw value from a debug source (URL param or constant) to determine a log level.
	 *
	 * @param mixed $raw_value The raw value to parse.
	 * @return string|null The textual log level (e.g., 'DEBUG') or null if no valid level is determined.
	 */
	private function _parse_log_level_value($raw_value): ?string {
		$numeric_to_text_level = array_flip(self::LOG_LEVELS_MAP); // e.g., [100 => 'debug', ...].
		$level_value_str       = is_string($raw_value) ? strtolower(trim($raw_value)) : null;

		// 1. Check for direct textual level match (debug, info, etc.)
		if (null !== $level_value_str && isset(self::LOG_LEVELS_MAP[$level_value_str])) {
			return $level_value_str;
		}

		// 2. Check for numeric level match (100, 200, etc.)
		if (is_numeric($raw_value) && isset($numeric_to_text_level[(int) $raw_value])) {
			return $numeric_to_text_level[(int) $raw_value];
		}

		// 3. Unified defaulting logic for both URL parameters and Constants:
		// Activates DEBUG if the value is an empty string (naked URL param or empty constant)
		if ('' === $raw_value) {
			return LogLevel::DEBUG;
		}

		// Check for explicit true values (e.g., "true", "1", "yes", "on").
		// For constants, this also handles actual boolean `true`.
		$filter_val_bool = filter_var($raw_value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
		if (true === $filter_val_bool) {
			return LogLevel::DEBUG;
		}

		// If none of the above conditions were met, this value does not activate logging.
		return null;
	}

	/**
	 * Gets the integer severity for a given log level string.
	 *
	 * @param string $level_string The log level string (e.g., 'DEBUG').
	 * @return int The integer severity.
	 */
	private function _get_level_severity( string $level_string ): int {
		return self::LOG_LEVELS_MAP[ $level_string ] ?? self::LOG_LEVELS_MAP[ LogLevel::DEBUG ];
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
		if (! $this->is_active) {
			return 0;
		}
		return $this->effective_log_level_severity;
	}

	private function _is_request_id_enabled(): bool {
		$param = '';
		if (!empty($this->debug_request_param)) {
			$param = $this->debug_request_param . '_request_id';
		}
		if ($param !== '' && array_key_exists($param, $_GET)) {
			$raw = $_GET[$param];
			if ('' === $raw) {
				return true;
			}
			$bool = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
			return true === $bool;
		}

		$const = '';
		if (!empty($this->custom_debug_constant_name)) {
			$const = $this->custom_debug_constant_name . '_REQUEST_ID';
		}
		if ($const !== '' && defined($const)) {
			$raw = constant($const);
			if ('' === $raw) {
				return true;
			}
			$bool = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
			return true === $bool;
		}

		return false;
	}

	protected function get_request_id(): string {
		if (null !== self::$request_id) {
			return self::$request_id;
		}

		try {
			if (function_exists('random_bytes')) {
				self::$request_id = bin2hex(random_bytes(8));
				return self::$request_id;
			}
		} catch (\Throwable $e) {
			self::$request_id = null;
		}

		self::$request_id = str_replace('.', '', uniqid('req', true));
		return self::$request_id;
	}

	protected function enrich_context(array $context): array {
		if (!array_key_exists('request_id', $context)) {
			$context['request_id'] = $this->get_request_id();
		}

		return $context;
	}

	/**
	 * System is unusable.
	 *
	 * @param string|\Stringable $message
	 * @param array<mixed>       $context
	 * @return void
	 */
	public function emergency(string|\Stringable $message, array $context = array()): void {
		$this->log(LogLevel::EMERGENCY, $message, $context);
	}

	/**
	 * Action must be taken immediately.
	 *
	 * Example: Entire website down, database unavailable, etc. This should
	 * trigger the SMS alerts and wake you up.
	 *
	 * @param string|\Stringable $message
	 * @param array<mixed>       $context
	 * @return void
	 */
	public function alert(string|\Stringable $message, array $context = array()): void {
		$this->log(LogLevel::ALERT, $message, $context);
	}

	/**
	 * Critical conditions.
	 *
	 * Example: Application component unavailable, unexpected exception.
	 *
	 * @param string|\Stringable $message
	 * @param array<mixed>       $context
	 * @return void
	 */
	public function critical(string|\Stringable $message, array $context = array()): void {
		$this->log(LogLevel::CRITICAL, $message, $context);
	}

	/**
	 * Runtime errors that do not require immediate action but should typically
	 * be logged and monitored.
	 *
	 * @param string|\Stringable $message
	 * @param array<mixed>       $context
	 * @return void
	 */
	public function error(string|\Stringable $message, array $context = array()): void {
		$this->log(LogLevel::ERROR, $message, $context);
	}

	/**
	 * Exceptional occurrences that are not errors.
	 *
	 * Example: Use of deprecated APIs, poor use of an API, undesirable things
	 * that are not necessarily wrong.
	 *
	 * @param string|\Stringable $message
	 * @param array<mixed>       $context
	 * @return void
	 */
	public function warning(string|\Stringable $message, array $context = array()): void {
		$this->log(LogLevel::WARNING, $message, $context);
	}

	/**
	 * Normal but significant events.
	 *
	 * @param string|\Stringable $message
	 * @param array<mixed>       $context
	 * @return void
	 */
	public function notice(string|\Stringable $message, array $context = array()): void {
		$this->log(LogLevel::NOTICE, $message, $context);
	}

	/**
	 * Interesting events.
	 *
	 * Example: User logs in, SQL logs.
	 *
	 * @param string|\Stringable $message
	 * @param array<mixed>       $context
	 * @return void
	 */
	public function info(string|\Stringable $message, array $context = array()): void {
		$this->log(LogLevel::INFO, $message, $context);
	}

	/**
	 * Detailed debug information.
	 *
	 * @param string|\Stringable $message
	 * @param array<mixed>       $context
	 * @return void
	 */
	public function debug(string|\Stringable $message, array $context = array()): void {
		$this->log(LogLevel::DEBUG, $message, $context);
	}

	/**
	 * Logs with an arbitrary level.
	 *
	 * @param mixed              $level
	 * @param string|\Stringable $message
	 * @param array<mixed>       $context
	 * @return void
	 */
	public function log($level, string|\Stringable $message, array $context = array()): void {
		if ( ! $this->is_active ) {
			return;
		}

		$current_level_severity = $this->_get_level_severity( (string) $level );
		if ( $current_level_severity < $this->effective_log_level_severity ) {
			return;
		}

		$context = $this->enrich_context($context);

		$formatted_message = sprintf(
			'[%s] %s',
			strtoupper( (string) $level ),
			(string) $message
		);

		if ( ! empty( $context ) ) {
			$sanitized_context = $this->sanitize_context( $context );
			$json              = function_exists('wp_json_encode') ? \wp_json_encode( $sanitized_context ) : \json_encode( $sanitized_context );
			if ($json === false) {
				$json = '"[context encoding failed]"';
			}
			$formatted_message .= ' Context: ' . $json;
		}

		if (null !== $this->error_log_handler) {
			call_user_func($this->error_log_handler, $formatted_message);
		} else {
			// @codeCoverageIgnoreStart
			error_log($formatted_message);
			// @codeCoverageIgnoreEnd
		}
	}

	/**
	 * Recursively sanitize context values to avoid retaining unsupported types.
	 *
	 * @param array<string|int,mixed> $context
	 * @return array<string|int,mixed>
	 */
	protected function sanitize_context(array $context, int $depth = 0): array {
		if ($depth > 8) {
			return array('[depth_limit]' => true);
		}

		$sanitized = array();
		foreach ($context as $key => $value) {
			$sanitized[$key] = $this->sanitize_value($value, $depth + 1, (string) $key);
		}

		return $sanitized;
	}

	/**
	 * @param mixed $value
	 * @return mixed
	 */
	protected function sanitize_value(mixed $value, int $depth, string $key = '') {
		if ($depth > 8) {
			return '[depth_limit]';
		}

		if (is_array($value)) {
			return $this->sanitize_summary_if_applicable($key, $this->sanitize_context($value, $depth + 1));
		}

		if ($value instanceof \Closure) {
			return $this->describe_closure($value);
		}

		if (is_object($value)) {
			return 'object(' . get_class($value) . ')';
		}

		if (is_resource($value)) {
			return 'resource(' . get_resource_type($value) . ')';
		}

		if (is_scalar($value) || $value === null) {
			return $value;
		}

		return '[unsupported]';
	}

	/**
	 * @param array<string|int,mixed> $value
	 * @return array<string|int,mixed>
	 */
	protected function sanitize_summary_if_applicable(string $key, array $value): array {
		if ($key === 'sanitize_summary' || $key === 'validate_summary') {
			return $this->summarize_bucket_map($value);
		}

		if ($key === 'default_summary') {
			return $this->summarize_default($value);
		}

		return $value;
	}

	/**
	 * @param array<string|int,mixed> $value
	 * @return array<string|int,mixed>
	 */
	protected function summarize_bucket_map(array $value): array {
		$summary = array(
			'component'  => array('count' => 0, 'descriptors' => array()),
			'schema'     => array('count' => 0, 'descriptors' => array()),
			'other_keys' => array(),
		);

		foreach ($value as $bucket => $data) {
			if ($bucket === 'component' || $bucket === 'schema') {
				$descriptors = array();
				if (is_array($data)) {
					$descriptors = $this->normalize_descriptors_array($data['descriptors'] ?? array());
					$count       = isset($data['count']) ? (int) $data['count'] : count($descriptors);
				} else {
					$count = 0;
				}
				$summary[$bucket] = array(
					'count'       => $count,
					'descriptors' => $descriptors,
				);
				continue;
			}

			$summary['other_keys'][$bucket] = $data;
		}

		return $summary;
	}

	/**
	 * @param array<string|int,mixed> $value
	 * @return array<string|int,mixed>
	 */
	protected function summarize_default(array $value): array {
		return array(
			'has_default' => (bool) ($value['has_default'] ?? false),
			'type'        => $value['type'] ?? null,
			'value'       => $this->summarize_default_value($value['value'] ?? null),
		);
	}

	protected function summarize_default_value(mixed $value): mixed {
		if (is_scalar($value) || $value === null) {
			return $value;
		}

		if (is_array($value)) {
			return array('count' => count($value));
		}

		return is_object($value) ? 'object(' . get_class($value) . ')' : '[unsupported]';
	}

	/**
	 * @param mixed $descriptors
	 * @return array<int,string>
	 */
	protected function normalize_descriptors_array(mixed $descriptors): array {
		if (!is_array($descriptors)) {
			return array();
		}

		return array_values(array_map(static fn($item): string => is_string($item) ? $item : '[invalid]', $descriptors));
	}

	protected function describe_closure(\Closure $closure): string {
		if (function_exists('spl_object_id')) {
			return 'Closure#' . spl_object_id($closure);
		}

		if (function_exists('spl_object_hash')) {
			return 'Closure#' . spl_object_hash($closure);
		}

		return 'Closure';
	}
}
