<?php
/**
 * Abstract implementation of Config class.
 *
 * @package  RanConfig
 */

declare(strict_types = 1);

namespace Ran\PluginLib\Config;

use Ran\PluginLib\Util\WPWrappersTrait;
use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Config\ConfigInterface;
use Exception;

/**
 * Abstract base class for plugin/theme configuration management.
 *
 * This class provides a centralized and consistent way to access plugin/theme metadata
 * and custom configuration values throughout the plugin or theme. It is intended to be
 * instantiated via concrete factory methods and then passed via dependency injection
 * to collaborating components.
 *
 * It relies on filesystem reads for header parsing, so a single instantiation is
 * recommended for performance.
 *
 * Header parsing model:
 *   - Standard WordPress headers (e.g., Name, Version, Author) are collected via
 *     core APIs (plugins: get_plugin_data; themes: wp_get_theme) and added to the
 *     normalized array.
 *   - Namespaced custom headers of the form `@<Namespace>: Name: Value` are parsed
 *     from the first doc/comment block of the source file and exposed as top-level
 *     arrays keyed by the namespace. For example, `@RAN: Log Constant Name: FOO`
 *     becomes `$normalized['RAN']['LogConstantName'] = 'FOO'`.
 *
 * A concrete `Config` should be created using factory methods on the final class:
 *   - `Config::fromPluginFile(string $pluginFile): self`
 *   - `Config::fromThemeDir(?string $stylesheetDir): self`
 *
 * During instantiation, it performs the following steps to populate the normalized
 * configuration array held in an internal cache:
 * 1. Gathers basic plugin/theme path and URL information.
 * 2. Parses namespaced custom headers from the first doc/comment block:
 *    - It looks for lines starting with `@<Namespace>:`.
 *    - The display name is normalized to PascalCase (e.g., "Log Constant Name" → "LogConstantName").
 *    - The result is stored under `$normalized[<Namespace>][<PascalCaseName>] = <Value>`.
 *    - Attempting to define a namespaced header whose display name collides with a
 *      standard WordPress header (by raw or normalized comparison) throws an Exception.
 * 3. Retrieves standard WordPress plugin headers using `get_plugin_data()`.
 * 4. Merges all gathered data into the normalized cache.
 *    The merge order is: base path/URL and environment keys, then standard WordPress
 *    headers, then any extra non-reserved pairs under `ExtraHeaders`, and finally each
 *    namespace array (e.g., `RAN`, `Acme`).
 * 5. Validates presence of common required keys across environments (e.g., Name,
 *    Version, TextDomain, PATH/URL/Slug/Type) plus environment-specific sanity checks
 *    (plugin Basename/File or theme StylesheetDir/StylesheetURL). The options key is no
 *    longer required at the top level; consumers may use `$normalized['RAN']['AppOption']`
 *    when provided, otherwise fall back to `Slug`.
 *
 * @throws \Exception If any of the required headers are missing or empty.
 * @throws \Exception If an attempt is made to define a standard WordPress header using the `@RAN:` prefix (e.g., `@RAN: Version: ...`).
 */
abstract class ConfigAbstract implements ConfigInterface {
	use WPWrappersTrait;

	/**
	 * Holds the logger instance.
	 *
	 * @var ?Logger The logger instance.
	 */
	private ?Logger $_logger = null;

	/**
	 * Flag to track if hydration is in progress (prevents Logger chicken-and-egg).
	 *
	 * @var bool
	 */
	private bool $_hydrating = false;

	/**
	 * Unified normalized config cache (theme or plugin).
	 *
	 * @var array<string,mixed>|null
	 */
	private ?array $_unified_cache = null;

	/**
	 * Memoized dev-environment decision per request.
	 *
	 * @var bool|null
	 */
	private ?bool $_is_dev_cache = null;

	/**
	 * Optional developer-provided callback to determine dev environment.
	 * If set, this takes precedence over all other detection mechanisms.
	 *
	 * @example
	 * ```php
	 * $config->set_is_dev_callback(function() {
	 *     return true;
	 * });
	 * ```
	 *
	 * @example using a filter:
	 * ```php
	 * add_filter('ran/plugin_lib/config/is_dev_environment', function() {
	 *     return true;
	 * });
	 * ```
	 *
	 * @var callable|null
	 */
	protected $_dev_callback = null;

	/**
	 * Request-level memoization buffer for header file reads.
	 *
	 * This avoids duplicate 8KB reads of the same file path within a single
	 * request (e.g., multiple Config instances pointing to the same source).
	 * It does NOT persist across requests and intentionally avoids disk I/O
	 * or invalidation complexity. The cache stores only successful reads.
	 *
	 * @var array<string,string>
	 */
	private static array $_header_buffer_cache = array();

	/**
		* Returns an instance of the Logger.
	 * Lazily instantiates the logger if it hasn't been already.
	 *
	 * @return Logger The logger instance.
	 */
	public function get_logger(): Logger {
		if ( $this->_logger instanceof Logger ) {
			return $this->_logger;
		}
		// During hydration, return a temporary no-op logger to avoid chicken-and-egg.
		// The real logger will be created after hydration completes.
		if ( $this->_hydrating ) {
			return new Logger( array() );
		}
		$cfg           = $this->get_config();
		$const         = (string)(($cfg['RAN']['LogConstantName'] ?? null) ?: 'RAN_LOG');
		$param         = (string)(($cfg['RAN']['LogRequestParam'] ?? null) ?: 'ran_log');
		$this->_logger = new Logger( array(
		    'custom_debug_constant_name' => $const,
		    'debug_request_param'        => $param,
		) );
		return $this->_logger;
	}

	/**
	 * Override the logger instance to use for all logging.
	 * Useful for testing and advanced integration scenarios.
	 *
	 * @param Logger $logger
	 * @return self
	 */
	public function set_logger(Logger $logger): self {
		$this->_logger = $logger;
		return $this;
	}

	/**
	 * Checks if the current environment is considered a 'development' environment.
	 *
	 * @return bool True if it's a development environment, false otherwise.
	 */
	public function is_dev_environment(): bool {
		$logger  = $this->get_logger();
		$context = get_class($this) . '::is_dev_environment';
		$cfg     = $this->get_config();
		$type    = $cfg['Type'] ?? '';
		$slug    = $cfg['Slug'] ?? '';
		if ( null !== $this->_is_dev_cache ) {
			return $this->_is_dev_cache;
		}
		$callback = $this->get_is_dev_callback();
		if ( is_callable( $callback ) ) {
			$result = (bool) $callback();
			if ( $logger->is_active() ) {
				$logger->debug("{$context} - Decision via callback.", array('type' => $type, 'slug' => $slug, 'result' => $result));
			}
			return $this->_is_dev_cache = $result;
		}
		$const = (string)(($cfg['RAN']['LogConstantName'] ?? null) ?: 'RAN_LOG');
		if ( $const && $this->_defined( $const ) && (bool) $this->_constant( $const ) ) {
			if ( $logger->is_active() ) {
				$logger->debug("{$context} - Decision via const.", array('type' => $type, 'slug' => $slug, 'const' => $const, 'result' => true));
			}
			return $this->_is_dev_cache = true;
		}
		if ( $this->_defined( 'SCRIPT_DEBUG' ) && (bool) $this->_constant('SCRIPT_DEBUG') ) {
			if ( $logger->is_active() ) {
				$logger->debug("{$context} - Decision via SCRIPT_DEBUG.", array('type' => $type, 'slug' => $slug, 'result' => true));
			}
			return $this->_is_dev_cache = true;
		}
		if ( $this->_defined( 'WP_DEBUG' ) && (bool) $this->_constant('WP_DEBUG') ) {
			if ( $logger->is_active() ) {
				$logger->debug("{$context} - Decision via WP_DEBUG.", array('type' => $type, 'slug' => $slug, 'result' => true));
			}
			return $this->_is_dev_cache = true;
		}
		if ( $logger->is_active() ) {
			$logger->debug("{$context} - Decision default.", array('type' => $type, 'slug' => $slug, 'result' => false));
		}
		return $this->_is_dev_cache = false;
	}

	/**
	 * Programmatically set the developer callback for dev environment detection.
	 *
	 * @param callable $callback
	 * @return self
	 */
	public function set_is_dev_callback(callable $callback): self {
		$this->_dev_callback = $callback;
		$this->_is_dev_cache = null; // reset memoized decision
		return $this;
	}

	/**
	 * Returns the developer-defined callback for checking if the environment is 'dev'.
	 *
	 * @return callable|null The callback function, or null if not set.
	 */
	public function get_is_dev_callback(): ?callable {
		// Explicitly set programmatic callback takes precedence
		if (is_callable($this->_dev_callback)) {
			return $this->_dev_callback;
		}

		$callback = $this->_unified_cache['is_dev_callback'] ?? null;

		if (is_callable($callback)) {
			return $callback;
		}

		return null;
	}

	/**
	 * Return the current configuration type. For now this implementation
	 * represents plugin-backed configuration. Theme support is added by
	 * dedicated factory methods in the concrete class.
	 */
	public function get_type(): ConfigType {
		$cfgType = $this->get_config()['Type'] ?? ConfigType::Plugin->value;
		return ($cfgType === ConfigType::Theme->value) ? ConfigType::Theme : ConfigType::Plugin;
	}

	/**
	 * Returns normalized configuration; derives from plugin_array when needed.
	 *
	 * @return array<string,mixed>
	 */
	public function get_config(): array {
		if ( null !== $this->_unified_cache ) {
			return $this->_unified_cache;
		}
		// Derive normalized structure from whatever minimal state is available (defensive fallback)
		$p                    = $this->_unified_cache ?? array();
		$text_domain          = (string) ( $p['TextDomain'] ?? '' );
		$name                 = (string) ( $p['Name'] ?? '' );
		$slug_src             = $text_domain !== '' ? $text_domain : $name;
		$slug                 = $this->_do_sanitize_key( $slug_src );
		$logger_default_name  = 'RAN_LOG';
		$logger_default_req   = 'ran_log';
		$this->_unified_cache = array(
		    'Name'       => $p['Name']    ?? '',
		    'Version'    => $p['Version'] ?? '',
		    'TextDomain' => $text_domain,
		    'PATH'       => $p['PATH'] ?? '',
		    'URL'        => $p['URL']  ?? '',
		    'Slug'       => $slug,
		    'Type'       => ConfigType::Plugin->value,
		    'RAN'        => array(
		        'AppOption'       => $p['RAN']['AppOption']       ?? $slug,
		        'LogConstantName' => $p['RAN']['LogConstantName'] ?? $logger_default_name,
		        'LogRequestParam' => $p['RAN']['LogRequestParam'] ?? $logger_default_req,
		    ),
		    'Basename' => $p['Basename'] ?? '',
		    'File'     => $p['File']     ?? '',
		);
		return $this->_unified_cache;
	}

	/**
	 * Validate that the root config array has the required fields set.
	 * These are gathered from the plugin docblock using the WP get_plugin_array().
	 *
	 * @param  array<string, mixed> $config The plugin data array.
	 *
	 * @return array<string, mixed>|Exception Returns the validated array provided, or throws an exception.
	 * @throws \Exception Throws if the minimum headers have not been set.
	 */
	public function validate_config( array $unified_cache ): array|Exception {
		$logger  = $this->get_logger();
		$context = get_class($this) . '::validate_config';
		// Common normalized keys required across both environments
		$common_required = array(
		    'Name',
		    'Version',
		    'TextDomain',
		    'PATH',
		    'URL',
		    'Slug',
		    'Type',
		);

		foreach ( $common_required as $key ) {
			if ( ! array_key_exists( $key, $unified_cache ) || $unified_cache[$key] === '' ) {
				throw new \Exception( sprintf('RanPluginLib: Missing required config key: "%s".', $key) );
			}
		}

		// Environment-specific sanity checks
		if ( ($unified_cache['Type'] ?? '') === ConfigType::Plugin->value ) {
			foreach ( array('Basename', 'File') as $key ) {
				if ( ! isset($unified_cache[$key]) || $unified_cache[$key] === '' ) {
					throw new \Exception( sprintf('RanPluginLib: Missing required plugin config key: "%s".', $key) );
				}
			}
		} elseif ( ($unified_cache['Type'] ?? '') === ConfigType::Theme->value ) {
			foreach ( array('StylesheetDir', 'StylesheetURL') as $key ) {
				if ( ! isset($unified_cache[$key]) || $unified_cache[$key] === '' ) {
					throw new \Exception( sprintf('RanPluginLib: Missing required theme config key: "%s".', $key) );
				}
			}
		}
		if ( $logger->is_active() ) {
			$logger->debug("{$context} - Validation OK.", array('type' => ($unified_cache['Type'] ?? 'unknown'), 'slug' => ($unified_cache['Slug'] ?? '')));
		}

		return $unified_cache;
	}

	/**
	 * Returns the generic WordPress option key for the plugin or theme.
	 *
	 * Preference order:
	 * 1) Namespaced RAN.AppOption
	 * 2) Slug
	 *
	 * @return string
	 */
	public function get_options_key(): string {
		$logger  = $this->get_logger();
		$context = get_class($this) . '::get_options_key';
		$cfg     = $this->get_config();
		$ran     = (array)($cfg['RAN'] ?? array());
		$used    = 'slug';
		$key     = (string) ($cfg['Slug'] ?? '');
		if (!empty($ran['AppOption'])) {
			$key  = (string) $ran['AppOption'];
			$used = 'RAN.AppOption';
		}
		if ( $logger->is_active() ) {
			$logger->debug("{$context} - Resolved options key.", array(
			    'type' => $cfg['Type'] ?? '',
			    'slug' => $cfg['Slug'] ?? '',
			    'via'  => $used,
			    'key'  => $key,
			));
		}
		return $key;
	}

	/**
	 * Returns the PSR-4 root namespace for this plugin/theme.
	 *
	 * Resolution order:
	 * 1. Explicit `@RAN: Namespace` header value
	 * 2. PascalCase conversion of the plugin/theme Name
	 *
	 * @return string The namespace (e.g., "MyPlugin" or "Acme\MyPlugin")
	 */
	public function get_namespace(): string {
		$cfg = $this->get_config();

		// Check for explicit override via @RAN: Namespace header
		if (isset($cfg['RAN']['Namespace']) && $cfg['RAN']['Namespace'] !== '') {
			return (string) $cfg['RAN']['Namespace'];
		}

		// Fallback: PascalCase of plugin/theme Name
		$name = (string) ($cfg['Name'] ?? '');
		return $this->_to_pascal_case($name);
	}

	/**
	 * Converts a string to PascalCase for namespace derivation.
	 *
	 * Examples:
	 * - "My Plugin Name" -> "MyPluginName"
	 * - "ran-starter-plugin" -> "RanStarterPlugin"
	 * - "Acme's Cool Plugin!" -> "AcmesCoolPlugin"
	 *
	 * @param string $name The input string.
	 * @return string The PascalCase result.
	 */
	protected function _to_pascal_case(string $name): string {
		// Remove non-alphanumeric characters except spaces, hyphens, underscores
		$name = preg_replace('/[^a-zA-Z0-9\s\-_]/', '', $name);

		// Replace hyphens and underscores with spaces for word splitting
		$name = str_replace(array('-', '_'), ' ', $name);

		// Split on whitespace, capitalize each word, join
		$words = preg_split('/\s+/', trim($name));
		if ($words === false || $words === array('')) {
			return '';
		}

		return implode('', array_map('ucfirst', array_map('strtolower', $words)));
	}

	/**
	 * Explicitly hydrate plugin metadata from a plugin root file.
	 *
	 * @param string $plugin_file Path to the plugin's root file.
	 * @return void
	 */
	protected function _hydrateFromPlugin( string $plugin_file ): void {
		$this->_hydrating = true;
		try {
			$logger  = $this->get_logger();
			$context = get_class($this) . '::_hydrateFromPlugin';
			if ( $logger->is_active() ) {
				$logger->debug("{$context} - Entered.", array(
					'type' => 'plugin',
					'file' => $plugin_file,
				));
			}
			// Fail fast with a clear error if the provided plugin file is not usable
			if ($plugin_file === '' || !is_file($plugin_file) || !is_readable($plugin_file)) {
				if ( $logger->is_active() ) {
					$logger->warning("{$context} - Invalid or unreadable plugin file.", array(
						'type' => 'plugin',
						'file' => $plugin_file,
					));
				}
				throw new \RuntimeException('Config::fromPlugin requires a valid, readable plugin root file.');
			}
			$provider = new PluginHeaderProvider($plugin_file, $this);
			$this->_hydrate_generic($provider);
		} finally {
			$this->_hydrating = false;
		}
	}

	/**
	 * Hydrate normalized config for a theme stylesheet directory.
	 *
	 * @param string $stylesheet_dir Absolute path to the theme stylesheet directory.
	 * @return void
	 */
	protected function _hydrateFromTheme( string $stylesheet_dir ): void {
		$this->_hydrating = true;
		try {
			$logger  = $this->get_logger();
			$context = get_class($this) . '::_hydrateFromTheme';
			$dir     = $stylesheet_dir ?: ($this->_do_get_stylesheet_directory());
			if ($dir === '') {
				if ( $logger->is_active() ) {
					$logger->warning("{$context} - Missing stylesheet directory or unavailable WordPress runtime.", array(
						'type' => 'theme',
						'dir'  => $stylesheet_dir,
					));
				}
				throw new \RuntimeException('Config::fromThemeDir requires a stylesheet directory or WordPress runtime.');
			}
			$provider = new ThemeHeaderProvider($dir, $this);
			$this->_hydrate_generic($provider);
		} finally {
			$this->_hydrating = false;
		}
	}

	/**
	 * Generic hydration pipeline using a provider (template method pattern).
	 *
	 *
	 */
	private function _hydrate_generic(HeaderProviderInterface $provider): void {
		$logger  = $this->get_logger();
		$context = get_class($this) . '::_hydrate_generic';
		// 1) Ensure WP is loaded
		$provider->ensure_wp_loaded();
		if ( $logger->is_active() ) {
			$logger->debug("{$context} - ensure_wp_loaded() completed.", array(
				'type' => $provider->get_type()->value,
			));
		}

		// 2) Establish Standard Headers
		$standard_headers = $provider->get_standard_headers();
		if ( $logger->is_active() ) {
			$logger->debug("{$context} - Collected standard headers.", array(
				'type'  => $provider->get_type()->value,
				'count' => count($standard_headers),
			));
		}
		$name        = (string) ($standard_headers['Name'] ?? '');
		$version     = (string) ($standard_headers['Version'] ?? '');
		$text_domain = (string) ($standard_headers['TextDomain'] ?? '');

		// 3) Gather path/url/ID
		[$base_path, $base_url, $base_name] = $provider->get_base_identifiers();
		if ( $logger->is_active() ) {
			$logger->debug("{$context} - Base identifiers.", array(
				'type' => $provider->get_type()->value,
				'path' => $base_path,
				'url'  => $base_url,
				'name' => $base_name,
			));
		}

		// 4) Parse headers from comment source
		$comment_source_path = $provider->get_comment_source_path();
		$raw_header_content  = $this->_read_header_content($comment_source_path) ?: '';
		$first_comment       = $this->_extract_first_comment_block($raw_header_content);
		$reserved            = $provider->get_type() === ConfigType::Plugin ? $this->_reserved_plugin_headers() : $this->_reserved_theme_headers();

		// Parse all namespaced @<Namespace>: Key: Value headers into nested structure
		$namespaced_headers = $this->_parse_namespaced_headers($first_comment, $reserved);
		if ( $logger->is_active() ) {
			$logger->debug("{$context} - Parsed namespaces.", array(
				'type'       => $provider->get_type()->value,
				'namespaces' => array_keys($namespaced_headers),
			));
		}

		$extra_header_pairs = $this->_parse_generic_headers($first_comment);

		// Filter extra headers against reserved keys and normalize collisions
		$filtered_extra = array();
		if (!empty($extra_header_pairs)) {
			$reserved_normalized = array();
			foreach (array_keys($reserved) as $rk) {
				$reserved_normalized[$this->_normalize_header_key($rk)] = true;
			}
			foreach ($extra_header_pairs as $ek => $ev) {
				if ($ev === '') {
					continue;
				}
				$norm = $this->_normalize_header_key($ek);
				if (isset($reserved[$ek]) || isset($reserved_normalized[$norm])) {
					continue; // skip keys that collide with reserved headers
				}
				$filtered_extra[$ek] = $ev;
			}
		}

		// 5) Build normalized config array
		$slug = $this->_derive_slug($name, $text_domain);

		$normalized = array(
		    'Name'       => $name,
		    'Version'    => $version,
		    'TextDomain' => $text_domain,
		    'PATH'       => $base_path,
		    'URL'        => $base_url,
		    'Slug'       => $slug,
		    'Type'       => $provider->get_type()->value,
		);

		// Add environment-specific normalized keys
		$normalized = $this->_merge_preserving($normalized, $provider->get_env_specific_normalized_keys(array(
		    'base_path' => $base_path,
		    'base_url'  => $base_url,
		    'base_name' => $base_name,
		)));

		// Merge additive headers
		$normalized = $this->_merge_preserving($normalized, $standard_headers);
		// Namespace remaining generic extras to avoid polluting top-level
		if (!empty($filtered_extra)) {
			$normalized['ExtraHeaders'] = $filtered_extra;
			// if ( $logger->is_active() ) {
			$logger->debug("{$context} - Extra headers kept.", array(
				'type'  => $provider->get_type()->value,
				'count' => count($filtered_extra),
			));
			// }
		}

		// Add each namespace directly at the top level
		foreach ($namespaced_headers as $ns => $pairs) {
			$normalized[$ns] = $pairs;
		}

		// Allow final adjustments via WordPress filter
		if ( $logger->is_active() ) {
			$logger->debug("{$context} - Applying filter 'ran/plugin_lib/config'.", array(
				'type' => $provider->get_type()->value,
			));
		}
		$filter_context = array(
			'environment'      => $provider->get_type()->value,
			'standard_headers' => $standard_headers,
			'namespaces'       => $namespaced_headers,
			'extra_headers'    => $filtered_extra,
			'base_path'        => $base_path,
			'base_url'         => $base_url,
			'base_name'        => $base_name,
			'comment_source'   => $comment_source_path,
		);

		/**
		 * Filter: ran/plugin_lib/config
		 *
		 * Gives theme/plugin authors a final opportunity to adjust the
		 * normalized configuration. Return the full normalized array.
		 *
		 * @param array $normalized Final normalized config array
		 * @param array $context    Context details (environment, sources)
		 */
		$normalized = $this->_do_apply_filter('ran/plugin_lib/config', $normalized, $filter_context);
		if (!is_array($normalized)) {
			if ( $logger->is_active() ) {
				$logger->debug("{$context} - Filter returned non-array, casting.", array(
					'type' => $provider->get_type()->value,
				));
			}
			$normalized = (array) $normalized;
		}

		// 6) Validate & set to unified_cache
		$this->validate_config($normalized);
		if ( $logger->is_active() ) {
			$logger->debug("{$context} - Hydration complete.", array(
				'type' => $provider->get_type()->value,
				'slug' => $normalized['Slug'] ?? '',
			));
		}
		$this->_unified_cache = $normalized;
	}

	/**
	 * Normalizes a raw header name into a camelCase or PascalCase string suitable for an array key.
	 *
	 * Examples:
	 * - "Log Constant Name" -> "LogConstantName"
	 * - "Plugin URI"        -> "PluginURI"
	 * - "X-My-Header"       -> "XMyHeader"
	 * - "requires_php"      -> "RequiresPhp"
	 *
	 * @param string $header_name The raw header name.
	 * @return string The normalized header key.
	 */
	protected function _normalize_header_key( string $header_name ): string {
		$normalized = trim( $header_name ); 								// Remove whitespace
		$normalized = strtolower( $normalized ); 							// Convert to lowercase
		$normalized = str_replace( array( '-', '_' ), ' ', $normalized ); 	// Replace hyphens and underscores with spaces
		$normalized = ucwords( $normalized ); 								// Capitalize each word
		$normalized = str_replace( ' ', '', $normalized ); 					// Remove spaces
		return $normalized;
	}

	/**
	 * Extracts the first doc/comment block from raw file content.
	 * Supports PHP docblocks and generic CSS-style comment blocks.
	 *
	 * @param string $raw The raw file content.
	 * @return string The first comment block.
	 */
	protected function _extract_first_comment_block(string $raw): string {
		if ($raw === '') {
			return '';
		}
		if (preg_match('/^\s*<\?php\s*\/\*\*(.*?)\*\//s', $raw, $m)) {
			return $m[1];
		}
		if (preg_match('/\/\*\*(.*?)\*\//s', $raw, $m)) {
			return $m[1];
		}
		if (preg_match('/\/\*(.*?)\*\//s', $raw, $m)) { // generic block comment (e.g., style.css)
			return $m[1];
		}
		return '';
	}

	/**
	 * Parses all @<Namespace>: Name: Value headers into a nested map under NamespacedHeaders.
	 * Enforces reserved-name protection against standard WP headers.
	 *
	 * Example lines:
	 *   @RAN:   Log Constant Name: MY_LOG
	 *   @Acme:  Feature Flag: on
	 *
	 * Produces:
	 *   [
	 *     'RAN'  => ['LogConstantName' => 'MY_LOG'],
	 *     'Acme' => ['FeatureFlag' => 'on']
	 *   ]
	 *
	 * @param string               $comment_block
	 * @param array<string, bool>  $reserved_names
	 * @return array<string, array<string, string>>
	 */
	protected function _parse_namespaced_headers(string $comment_block, array $reserved_names): array {
		$result = array();
		if ($comment_block === '') {
			return $result;
		}
		// Pattern captures: namespace, name, value. Namespace allows alnum, underscore, hyphen
		preg_match_all('/^[ \t\*]*@(?<ns>[A-Za-z0-9_\-]+):\s*(?<name>[A-Za-z0-9\s\-]+):\s*(?<value>.+)$/m', $comment_block, $matches, PREG_SET_ORDER);
		foreach ($matches as $m) {
			$ns       = trim($m['ns']);
			$raw_name = trim($m['name']);
			$raw_val  = trim($m['value']);

			if ($ns === '' || $raw_name === '' || $raw_val === '') {
				// Skip invalid/incomplete entries
				continue;
			}

			// Enforce reserved name protection against standard WP headers
			$normalized_display  = $this->_normalize_header_key($raw_name);
			$reserved_normalized = array();
			foreach (array_keys($reserved_names) as $rk) {
				$reserved_normalized[$this->_normalize_header_key($rk)] = true;
			}
			if (isset($reserved_names[$raw_name]) || isset($reserved_normalized[$normalized_display])) {
				$logger  = $this->get_logger();
				$context = get_class($this) . '::_parse_namespaced_headers';
				if ( $logger->is_active() ) {
					$logger->warning("{$context} - Reserved header collision.", array(
						'namespace' => $ns,
						'name'      => $raw_name,
					));
				}
				throw new \Exception(sprintf(
					'Naming @%s: custom headers the same as standard WP headers is not allowed. Problematic header: "@%s: %s".',
					$ns,
					$ns,
					$raw_name
				));
			}

			$result[$ns][$normalized_display] = $raw_val;
		}
		return $result;
	}

	/**
	 * Reserved standard header display names for plugins.
	 * Used to prevent @RAN collisions.
	 *
	 * @return array<string,bool>
	 */
	protected function _reserved_plugin_headers(): array {
		return array(
		    'Name'        => true, 'Plugin Name' => true, 'Version' => true, 'Plugin URI' => true,
		    'Author'      => true, 'Author URI' => true, 'Description' => true, 'Text Domain' => true,
		    'Domain Path' => true, 'Network' => true, 'Requires WP' => true, 'Requires PHP' => true,
		    'Update URI'  => true,
		);
	}

	/**
	 * Reserved standard header display names for themes.
	 * Used to prevent @RAN collisions.
	 *
	 * @return array<string,bool>
	 */
	protected function _reserved_theme_headers(): array {
		return array(
		    'Name'              => true, 'Theme Name' => true, 'Version' => true, 'Theme URI' => true,
		    'Author'            => true, 'Author URI' => true, 'Description' => true, 'Text Domain' => true,
		    'Domain Path'       => true, 'Requires WP' => true, 'Requires PHP' => true, 'Update URI' => true,
		    'Requires at least' => true, 'Tested up to' => true
		);
	}

	/**
	 * Best-effort parser for generic header-like pairs in a comment block.
	 * Accepts lines like "Key: Value" without the @RAN prefix.
	 * Useful to sweep any fields get_plugin_data/wp_get_theme may not expose.
	 *
	 * @param string $comment_block
	 * @return array<string,string>
	 */
	protected function _parse_generic_headers(string $comment_block): array {
		$result = array();
		if ($comment_block === '') {
			return $result;
		}
		if (!preg_match_all('/^[ \t\*]*([A-Za-z0-9 _\-]+):\s*(.+)$/m', $comment_block, $matches, PREG_SET_ORDER)) {
			return $result;
		}
		foreach ($matches as $m) {
			$raw_name  = trim($m[1]);
			$raw_value = trim($m[2]);
			if ($raw_name === '' || $raw_value === '') {
				continue;
			}
			// Normalize spaces/variants for Text Domain
			if (strcasecmp($raw_name, 'Text Domain') === 0) {
				$result['TextDomain'] = $raw_value;
				continue;
			}
			// Preserve original header casing except spaces -> no change; callers treat additively only
			$result[$raw_name] = $raw_value;
		}
		return $result;
	}

	/**
	 * Derives a slug from text-domain or name.
	 *
	 * @param string $name The name of the plugin or theme.
	 * @param string $text_domain The text domain of the plugin or theme.
	 * @return string The slug.
	 */
	protected function _derive_slug(string $name, string $text_domain): string {
		$src = $text_domain !== '' ? $text_domain : $name;
		return $this->_do_sanitize_key($src);
	}

	/**
	 * Returns logger constant and request param, allowing overrides via custom headers.
	 *
	 * @param array<string,mixed> $custom_headers
	 * @return array{const:string,param:string}
	 */
	protected function _derive_logger_settings(array $custom_headers): array {
		$const = (string) ($custom_headers['RANLogConstantName'] ?? 'RAN_LOG');
		$param = (string) ($custom_headers['RANLogRequestParam'] ?? 'ran_log');
		return array('const' => $const, 'param' => $param);
	}

	/**
	 * Adds keys from extras into base only when the key does not already exist in base.
	 *
	 * @param array<string,mixed> $base
	 * @param array<string,mixed> $extras
	 * @return array<string,mixed>
	 */
	protected function _merge_preserving(array $base, array $extras): array {
		foreach ($extras as $k => $v) {
			if (!array_key_exists($k, $base) && $v !== '') {
				$base[$k] = $v;
			}
		}
		return $base;
	}

	/**
	 * Reads the first 8KB of a specified plugin file to extract raw header content.
	 *
	 * This method is a wrapper around file_get_contents to allow for easier testing by mocking.
	 *
	 * @param string $file_path The full path to the plugin file.
	 * @return string|false The raw content of the plugin file's header (first 8KB), or false on failure.
	 */
	protected function _read_header_content( string $file_path ): string|false {
		// Return from lightweight per-request cache if available
		if ( isset( self::$_header_buffer_cache[ $file_path ] ) ) {
			$logger  = $this->get_logger();
			$context = get_class($this) . '::_read_header_content';
			if ( $logger->is_active() ) {
				$logger->debug("{$context} - Cache hit.", array('file' => $file_path));
			}
			return self::$_header_buffer_cache[ $file_path ];
		}

		// Read first 8KB of the file for header parsing
		$buf = file_get_contents( $file_path, false, null, 0, 8 * 1024 );
		if ( $buf === false ) {
			$logger  = $this->get_logger();
			$context = get_class($this) . '::_read_header_content';
			if ( $logger->is_active() ) {
				$logger->warning("{$context} - Failed to read header content.", array('file' => $file_path));
			}
		}

		// Cache only successful reads to avoid storing false values
		if ( $buf !== false ) {
			self::$_header_buffer_cache[ $file_path ] = $buf;
		}

		return $buf;
	}

	/**
	 * Return standard plugin headers as an associative array using get_plugin_data.
	 * Filters out empty values. Safe to call without WP fully loaded (will return empty array).
	 *
	 * @param string $plugin_file
	 * @return array<string,mixed>
	 */
	public function __get_standard_plugin_headers(string $plugin_file): array {
		$data = (array) $this->_do_get_plugin_data($plugin_file, false, false);
		return array_filter($data, static fn($v) => $v !== '');
	}

	/**
	 * Return standard theme headers as an associative array using wp_get_theme.
	 * Attempts to include all common fields and any available headers exposed by WP_Theme.
	 * Filters out empty values. If no theme context is available, returns empty array.
	 *
	 * @param string $stylesheet_dir
	 * @return array<string,mixed>
	 */
	public function __get_standard_theme_headers(string $stylesheet_dir): array {
		if (!$this->_function_exists('wp_get_theme')) {
			//@codeCoverageIgnoreStart
			return array();
			//@codeCoverageIgnoreEnd
		}
		$slug_guess   = basename($stylesheet_dir);
		$theme_object = $slug_guess ? $this->_do_wp_get_theme($slug_guess) : $this->_do_wp_get_theme();
		if (!$theme_object) {
			return array();
		}
		// WP_Theme exposes a finite set via ->get(); collect typical keys but keep logic resilient
		$keys = array(
		    'Name', 'ThemeURI', 'Description', 'Author', 'AuthorURI', 'Version', 'Template', 'Status', 'Tags',
			'TextDomain', 'Text Domain', 'DomainPath', 'Domain Path', 'RequiresWP', 'Requires at least', 'RequiresPHP', 'Requires PHP', 'UpdateURI', 'Update URI',
		);
		$headers = array();
		foreach ($keys as $k) {
			$v = (string) $theme_object->get($k);
			if ($v !== '') {
				// Normalize Text Domain variations into TextDomain key
				if ($k === 'Text Domain') {
					$headers['TextDomain'] = $v;
				} else {
					$headers[$k] = $v;
				}
			}
		}
		return array_filter($headers, static fn($v) => $v !== '');
	}

	// Code coverage seams are covered by tests in ConfigAbstractTest
	// but PHPUnit does not acknowledge this use as coverage.
	//@codeCoverageIgnoreStart
	/**
	* Test seam to simulate missing global functions in unit tests.
	*/
	protected function _function_exists(string $fn): bool {
		return \function_exists($fn);
	}

	/**
	* Test seam for defined().
	*/
	protected function _defined(string $name): bool {
		return \defined($name);
	}

	/**
	* Test seam for constant().
	* @return mixed
	*/
	protected function _constant(string $name) {
		return \constant($name);
	}
	//@codeCoverageIgnoreEnd
}

