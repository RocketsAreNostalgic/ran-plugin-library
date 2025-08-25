<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Config;

use RanTestCase; // Declared in test_bootstrap.php

use Ran\PluginLib\Config\ConfigType;
use Ran\PluginLib\Config\ConfigAbstract;

/**
 * Probe subclass to expose protected utilities for testing.
 */
final class ConfigAbstractProbe extends ConfigAbstract {
	/** @var array<string,mixed> */
	private array $overrideConfig                       = array();
	private ?\Ran\PluginLib\Util\Logger $overrideLogger = null;
	private array $seamConstants                        = array();

	public function __construct(array $cfg = array()) {
		$this->overrideConfig = $cfg;
	}

	public function setOverrideConfig(array $cfg): void {
		$this->overrideConfig = $cfg;
	}

	public function setTestLogger(\Ran\PluginLib\Util\Logger $logger): void {
		$this->overrideLogger = $logger;
	}

	public function get_logger(): \Ran\PluginLib\Util\Logger {
		if ($this->overrideLogger instanceof \Ran\PluginLib\Util\Logger) {
			return $this->overrideLogger;
		}
		return parent::get_logger();
	}

	public function setSeamConstant(string $name, $value): void {
		$this->seamConstants[$name] = $value;
	}

	protected function _defined(string $name): bool {
		return array_key_exists($name, $this->seamConstants);
	}

	protected function _constant(string $name) {
		return $this->seamConstants[$name] ?? null;
	}

	public function callNormalizeHeaderKey(string $name): string {
		return $this->_normalize_header_key($name);
	}

	public function callExtractFirstCommentBlock(string $raw): string {
		return $this->_extract_first_comment_block($raw);
	}

	/** @param array<string,bool> $reserved */
	public function callParseNamespacedHeaders(string $block, array $reserved): array {
		return $this->_parse_namespaced_headers($block, $reserved);
	}


	public function callParseGenericHeaders(string $block): array {
		return $this->_parse_generic_headers($block);
	}

	public function callMergePreserving(array $base, array $extras): array {
		return $this->_merge_preserving($base, $extras);
	}

	public function callReadHeaderContent(string $path): string|false {
		return $this->_read_header_content($path);
	}

	public function callReservedPlugin(): array {
		return $this->_reserved_plugin_headers();
	}

	public function callReservedTheme(): array {
		return $this->_reserved_theme_headers();
	}

	public function callDeriveSlug(string $name, string $td): string {
		return $this->_derive_slug($name, $td);
	}

	/** @param array<string,mixed> $custom */
	public function callDeriveLoggerSettings(array $custom): array {
		return $this->_derive_logger_settings($custom);
	}

	public function callGetStandardPluginHeaders(string $file): array {
		return $this->_get_standard_plugin_headers($file);
	}

	public function callGetStandardThemeHeaders(string $dir): array {
		return $this->_get_standard_theme_headers($dir);
	}

	public function get_config(): array {
		// Provide a minimal valid plugin config by default; allow override per-test
		if (!empty($this->overrideConfig)) {
			return $this->overrideConfig;
		}
		return array(
		    'Name'       => 'Probe',
		    'Version'    => '1.0.0',
		    'TextDomain' => 'probe',
		    'PATH'       => '/path',
		    'URL'        => 'https://example.test',
		    'Slug'       => 'probe',
		    'Type'       => ConfigType::Plugin->value,
		    'Basename'   => 'probe/probe.php',
		    'File'       => __FILE__,
		    'RAN'        => array(
		        'AppOption'       => 'probe_options',
		        'LogConstantName' => 'RAN_LOG',
		        'LogRequestParam' => 'ran_log',
		    ),
		);
	}

	/**
	 * Provide options accessor required by ConfigInterface for this probe.
	 * Mirrors production semantics: no writes; optional schema registration without seed/flush.
	 *
	 * @param array{autoload?: bool, schema?: array<string, mixed>} $args
	 * @return \Ran\PluginLib\Options\RegisterOptions
	 */
	public function options(array $args = array()): \Ran\PluginLib\Options\RegisterOptions {
		$defaults = array('autoload' => true, 'schema' => array());
		$args     = is_array($args) ? array_merge($defaults, $args) : $defaults;

		$autoload = (bool) ($args['autoload'] ?? true);
		$schema   = is_array($args['schema'] ?? null) ? $args['schema'] : array();

		$opts = \Ran\PluginLib\Options\RegisterOptions::from_config(
			$this,
			array(),           // initial (none)
			$autoload,
			$this->get_logger(),
			array()            // schema (none at construction)
		);
		if (!empty($schema)) {
			$opts->register_schema($schema, false, false);
		}
		return $opts;
	}
}

/**
 * @coversDefaultClass \Ran\PluginLib\Config\ConfigAbstract
 */
final class ConfigAbstractProtectedTest extends RanTestCase {
	/**
	 * @covers ::_normalize_header_key
	 */
	public function test_normalize_header_key_variants(): void {
		$p = new ConfigAbstractProbe();
		$this->assertSame('LogConstantName', $p->callNormalizeHeaderKey('Log Constant Name'));
		$this->assertSame('PluginUri', $p->callNormalizeHeaderKey('Plugin URI'));
		$this->assertSame('XMyHeader', $p->callNormalizeHeaderKey('X-My-Header'));
		$this->assertSame('RequiresPhp', $p->callNormalizeHeaderKey('requires_php'));
	}

	/**
	 * @covers ::_extract_first_comment_block
	 */
	public function test_extract_first_comment_block_php_docblock(): void {
		$raw   = "<?php\n/**\n * Line one\n * @RAN: App Option: foo\n */\n echo 'x';";
		$p     = new ConfigAbstractProbe();
		$block = $p->callExtractFirstCommentBlock($raw);
		$this->assertStringContainsString('@RAN: App Option: foo', $block);
	}

	/**
	 * @covers ::_extract_first_comment_block
	 */
	public function test_extract_first_comment_block_css_block(): void {
		$raw   = "/*\n Theme Name: T\n @RAN: Log Constant Name: MY_LOG\n*/\nbody{}";
		$p     = new ConfigAbstractProbe();
		$block = $p->callExtractFirstCommentBlock($raw);
		$this->assertStringContainsString('Theme Name: T', $block);
		$this->assertStringContainsString('Log Constant Name: MY_LOG', $block);
	}

	/**
	 * @covers ::_extract_first_comment_block
	 */
	public function test_extract_first_comment_block_php_docblock_not_at_start(): void {
		$raw   = "prefix\n/**\n * Middle block\n */\n<?php echo 'x';";
		$p     = new ConfigAbstractProbe();
		$block = $p->callExtractFirstCommentBlock($raw);
		$this->assertStringContainsString('Middle block', $block);
	}

	/**
	 * @covers ::_extract_first_comment_block
	 */
	public function test_extract_first_comment_block_empty_and_no_match(): void {
		$p = new ConfigAbstractProbe();
		$this->assertSame('', $p->callExtractFirstCommentBlock(''));
		$this->assertSame('', $p->callExtractFirstCommentBlock('no comments here'));
	}

	/**
	 * @covers ::_parse_namespaced_headers
	 */
	public function test_parse_namespaced_headers_success_and_collision(): void {
		$p        = new ConfigAbstractProbe();
		$reserved = $p->callReservedPlugin();
		$block    = "@RAN:   App Option: my_app\n@Acme: Feature Flag: on\n";
		$parsed   = $p->callParseNamespacedHeaders($block, $reserved);
		$this->assertSame('my_app', $parsed['RAN']['AppOption']);
		$this->assertSame('on', $parsed['Acme']['FeatureFlag']);

		$this->expectException(\Exception::class);
		$collision         = "@RAN: Version: 2.0\n"; // reserved header name
		$logger            = new \Ran\PluginLib\Util\CollectingLogger();
		$this->logger_mock = $logger;
		$p->setTestLogger($logger);
		try {
			$p->callParseNamespacedHeaders($collision, $reserved);
		} finally {
			$this->expectLog('warning', array('::_parse_namespaced_headers', 'Reserved header collision'), 1);
		}
	}

	/**
	 * @covers ::_parse_namespaced_headers
	 */
	public function test_parse_namespaced_headers_empty_block_returns_empty(): void {
		$p = new ConfigAbstractProbe();
		$this->assertSame(array(), $p->callParseNamespacedHeaders('', $p->callReservedPlugin()));
	}

	/**
	 * @covers ::_parse_namespaced_headers
	 */
	public function test_parse_namespaced_headers_skips_incomplete_entries(): void {
		$p        = new ConfigAbstractProbe();
		$reserved = $p->callReservedPlugin();
		$block    = "@Acme: Feature: on\n@Acme: EmptyVal:    \n@Foo:   : value\n@Bar: NameOnly:\n"; // lines with empty value/name should be skipped
		$parsed   = $p->callParseNamespacedHeaders($block, $reserved);
		$this->assertSame('on', $parsed['Acme']['Feature'] ?? null);
		$this->assertTrue(!isset($parsed['Acme']['EmptyVal']));
		$this->assertTrue(!isset($parsed['Foo']));
		$this->assertTrue(!isset($parsed['Bar']['NameOnly']));
	}

	// _parse_ran_headers removed with namespaced headers refactor. No tests needed.

	/**
	 * @covers ::_parse_generic_headers
	 */
	public function test_parse_generic_headers_normalizes_text_domain(): void {
		$p     = new ConfigAbstractProbe();
		$block = "Random: val\nText Domain: my-text-domain\nEmpty: \n";
		$g     = $p->callParseGenericHeaders($block);
		$this->assertSame('my-text-domain', $g['TextDomain']);
		$this->assertSame('val', $g['Random']);
		$this->assertArrayNotHasKey('Empty', $g);
	}

	/**
	 * @covers ::_parse_generic_headers
	 */
	public function test_parse_generic_headers_returns_empty_on_empty_or_no_pairs(): void {
		$p = new ConfigAbstractProbe();
		$this->assertSame(array(), $p->callParseGenericHeaders(''));
		$this->assertSame(array(), $p->callParseGenericHeaders("@RAN: Key: Val\n@Acme: Other: X"));
	}

	/**
	 * @covers ::_merge_preserving
	 */
	public function test_merge_preserving_does_not_override(): void {
		$p      = new ConfigAbstractProbe();
		$base   = array('A' => '1', 'B' => '2');
		$extras = array('B' => 'X', 'C' => '3');
		$m      = $p->callMergePreserving($base, $extras);
		$this->assertSame('2', $m['B']);
		$this->assertSame('3', $m['C']);
	}

	/**
	 * @covers ::_read_header_content
	 */
	public function test_read_header_content_memoizes(): void {
		$p                 = new ConfigAbstractProbe();
		$logger            = new \Ran\PluginLib\Util\CollectingLogger();
		$this->logger_mock = $logger;
		$p->setTestLogger($logger);
		$tmp = tempnam(sys_get_temp_dir(), 'hdr_');
		file_put_contents($tmp, str_repeat('A', 100));
		$c1 = $p->callReadHeaderContent($tmp);
		$c2 = $p->callReadHeaderContent($tmp);
		$this->assertIsString($c1);
		$this->assertSame($c1, $c2);
		$this->expectLog('debug', array('::_read_header_content', 'Cache hit'), 1);
	}

	/**
	 * @covers ::_reserved_plugin_headers
	 * @covers ::_reserved_theme_headers
	 */
	public function test_reserved_sets_are_non_empty(): void {
		$p = new ConfigAbstractProbe();
		$this->assertNotEmpty($p->callReservedPlugin());
		$this->assertNotEmpty($p->callReservedTheme());
	}

	/**
	 * @covers ::_derive_slug
	 */
	public function test_derive_slug_fallbacks(): void {
		$p = new ConfigAbstractProbe();
		$this->assertSame('my_plugin', $p->callDeriveSlug('My Plugin', ''));
		$this->assertSame('my-domain', $p->callDeriveSlug('My Plugin', 'my-domain'));
	}

	/**
	 * @covers ::_derive_logger_settings
	 */
	public function test_derive_logger_settings_from_flattened_keys(): void {
		$p   = new ConfigAbstractProbe();
		$out = $p->callDeriveLoggerSettings(array('RANLogConstantName' => 'CUSTOM_LOG', 'RANLogRequestParam' => 'custom'));
		$this->assertSame(array('const' => 'CUSTOM_LOG', 'param' => 'custom'), $out);
	}

	/**
	 * @covers ::_get_standard_plugin_headers
	 * @covers ::_get_standard_theme_headers
	 */
	public function test_get_standard_headers_return_empty_without_wp(): void {
		$p = new ConfigAbstractProbe();
		$this->assertSame(array(), $p->callGetStandardPluginHeaders(__FILE__));
		$this->assertSame(array(), $p->callGetStandardThemeHeaders(sys_get_temp_dir()));
	}

	/**
		* Explicitly covers early return when get_plugin_data() is absent.
		*
		* @covers ::_get_standard_plugin_headers
		*/
	public function test_get_standard_plugin_headers_returns_empty_when_function_absent(): void {
		$p = new ConfigAbstractProbe();
		// No WP_Mock::userFunction('get_plugin_data') defined here
		$this->assertSame(array(), $p->callGetStandardPluginHeaders(__FILE__));
	}

	/**
		* Explicitly covers early return when wp_get_theme() is absent.
		*
		* @covers ::_get_standard_theme_headers
		*/
	public function test_get_standard_theme_headers_returns_empty_when_function_absent(): void {
		$p = new ConfigAbstractProbe();
		// No WP_Mock::userFunction('wp_get_theme') defined here
		$this->assertSame(array(), $p->callGetStandardThemeHeaders(sys_get_temp_dir()));
	}

	/**
		* @covers ::_get_standard_plugin_headers
		*/
	public function test_get_standard_plugin_headers_filters_empty(): void {
		$p = new ConfigAbstractProbe();
		\WP_Mock::setUp();
		\WP_Mock::userFunction('get_plugin_data')->with(__FILE__, false, false)->andReturn(array(
		    'Name' => 'X', 'Version' => '1', 'TextDomain' => '', 'Empty' => ''
		));
		$out = $p->callGetStandardPluginHeaders(__FILE__);
		$this->assertSame(array('Name' => 'X', 'Version' => '1'), $out);
		\WP_Mock::tearDown();
	}

	/**
	 * @covers ::_get_standard_theme_headers
	 */
	public function test_get_standard_theme_headers_collects_and_normalizes(): void {
		$p = new ConfigAbstractProbe();
		\WP_Mock::setUp();
		$theme = new class {
			public function get($k) {
				return match ($k) {
					'Name'   => 'T', 'Version' => '2', 'Text Domain' => 'td', 'ThemeURI' => 'u',
					'Author' => 'a', 'AuthorURI' => 'au', 'Description' => 'd',
					default  => ''
				};
			}
		};
		\WP_Mock::userFunction('wp_get_theme')->andReturn($theme);
		$out = $p->callGetStandardThemeHeaders(sys_get_temp_dir());
		$this->assertSame('td', $out['TextDomain']);
		$this->assertSame('T', $out['Name']);
		$this->assertSame('2', $out['Version']);
		$this->assertSame('u', $out['ThemeURI']);
		\WP_Mock::tearDown();
	}

	/**
	 * @covers ::get_options_key
	 */
	public function test_get_options_key_uses_ran_app_option_when_present(): void {
		$p = new ConfigAbstractProbe();
		$this->assertSame('probe_options', $p->get_options_key());
	}

	/**
	 * @covers ::is_dev_environment
	 */
	public function test_is_dev_environment_via_custom_constant(): void {
		$p     = new ConfigAbstractProbe();
		$const = 'UNIT_DEV_' . strtoupper(substr(md5((string) microtime(true)), 0, 8));
		$p->setSeamConstant($const, true);
		$cfg                           = $p->get_config();
		$cfg['RAN']['LogConstantName'] = $const;
		$p->setOverrideConfig($cfg);
		$logger            = new \Ran\PluginLib\Util\CollectingLogger();
		$this->logger_mock = $logger;
		$p->setTestLogger($logger);
		$this->assertTrue($p->is_dev_environment());
		$this->expectLog('debug', array('::is_dev_environment', 'Decision via const'), 1);
	}

	/**
	 * @covers ::_read_header_content
	 */
	public function test_read_header_content_failure_returns_false(): void {
		$p                 = new ConfigAbstractProbe();
		$logger            = new \Ran\PluginLib\Util\CollectingLogger();
		$this->logger_mock = $logger;
		$p->setTestLogger($logger);
		$nonexistent = sys_get_temp_dir() . '/does_not_exist_' . uniqid() . '.php';
		$prev        = set_error_handler(static function () {
			return true;
		});
		try {
			$this->assertFalse($p->callReadHeaderContent($nonexistent));
		} finally {
			if ($prev !== null) {
				set_error_handler($prev);
			} else {
				restore_error_handler();
			}
			$this->expectLog('warning', array('::_read_header_content', 'Failed to read header content'), 1);
		}
	}

	/**
	 * @covers ::is_dev_environment
	 */
	public function test_is_dev_environment_logs_callback_decision(): void {
		$p                 = new ConfigAbstractProbe();
		$logger            = new \Ran\PluginLib\Util\CollectingLogger();
		$this->logger_mock = $logger;
		$p->setTestLogger($logger);
		$p->set_is_dev_callback(function () {
			return true;
		});
		$p->is_dev_environment();
		$this->expectLog('debug', array('::is_dev_environment', 'Decision via callback'), 1);
	}

	/**
		* @covers ::is_dev_environment
		*/
	public function test_is_dev_environment_uses_script_debug_constant(): void {
		$p                 = new ConfigAbstractProbe();
		$logger            = new \Ran\PluginLib\Util\CollectingLogger();
		$this->logger_mock = $logger;
		$p->setTestLogger($logger);
		// Make sure SCRIPT_DEBUG drives decision in this test
		if (\defined('SCRIPT_DEBUG')) {
			$this->markTestSkipped('SCRIPT_DEBUG already defined in this process.');
		}
		// Use seam instead of global define to avoid cross-test contamination
		$p->setSeamConstant('SCRIPT_DEBUG', true);
		if (false) {
			// no-op (covered by seam)
		}
		$this->assertTrue($p->is_dev_environment());
		$this->expectLog('debug', array('::is_dev_environment', 'Decision via SCRIPT_DEBUG'), 1);
	}

	/**
		* @covers ::is_dev_environment
		*/
	public function test_is_dev_environment_uses_wp_debug_constant(): void {
		$p                 = new ConfigAbstractProbe();
		$logger            = new \Ran\PluginLib\Util\CollectingLogger();
		$this->logger_mock = $logger;
		$p->setTestLogger($logger);
		// Ensure constants state allows exercising WP_DEBUG branch only
		if (\defined('SCRIPT_DEBUG')) {
			$this->markTestSkipped('SCRIPT_DEBUG defined; cannot reach WP_DEBUG branch.');
		}
		if (\defined('WP_DEBUG')) {
			$this->markTestSkipped('WP_DEBUG already defined in this process.');
		}
		// Use seam instead of global define to avoid cross-test contamination
		$p->setSeamConstant('WP_DEBUG', true);
		if (false) {
			// no-op (covered by seam)
		}
		$this->assertTrue($p->is_dev_environment());
		$this->expectLog('debug', array('::is_dev_environment', 'Decision via WP_DEBUG'), 1);
	}

	/**
		* @covers ::is_dev_environment
		*/
	public function test_is_dev_environment_uses_cached_value_on_second_call(): void {
		$p                 = new ConfigAbstractProbe();
		$logger            = new \Ran\PluginLib\Util\CollectingLogger();
		$this->logger_mock = $logger;
		$p->setTestLogger($logger);
		// First call computes and logs default decision (false)
		$first = $p->is_dev_environment();
		// Ensure neither SCRIPT_DEBUG nor WP_DEBUG are defined so default path triggers
		if (\defined('SCRIPT_DEBUG') || \defined('WP_DEBUG')) {
			$this->markTestSkipped('Debug constants present; cannot assert default path.');
		}
		// Second call should hit early return without logging again
		$second = $p->is_dev_environment();
		$this->assertSame($first, $second);
		// Only one default log should exist regardless of how many calls
		$this->expectLog('debug', array('::is_dev_environment', 'Decision default'), 1);
	}

	/**
	 * @covers ::get_type
	 */
	public function test_get_type_overrides(): void {
		$p = new ConfigAbstractProbe();
		$this->assertTrue($p->get_type()->value === ConfigType::Plugin->value);
		$p->setOverrideConfig(array(
		    'Name'          => 'T', 'Version' => '1', 'TextDomain' => 't',
		    'PATH'          => '/p', 'URL' => 'https://x', 'Slug' => 't', 'Type' => ConfigType::Theme->value,
		    'StylesheetDir' => '/dir', 'StylesheetURL' => 'https://x/t'
		));
		$this->assertTrue($p->get_type()->value === ConfigType::Theme->value);
	}

	/**
	 * @covers ::get_is_dev_callback
	 * @covers ::set_is_dev_callback
	 */
	public function test_get_is_dev_callback_defaults_and_setter(): void {
		$p = new ConfigAbstractProbe();
		$this->assertNull($p->get_is_dev_callback());
		$cb = static function (): bool {
			return true;
		};
		$p->set_is_dev_callback($cb);
		$this->assertIsCallable($p->get_is_dev_callback());
	}

	/**
	 * @covers ::validate_config
	 */
	public function test_validate_config_plugin_and_theme_failures(): void {
		$p = new ConfigAbstractProbe();
		// Missing Basename for plugin
		$pluginCfg = array(
		    'Name' => 'P', 'Version' => '1', 'TextDomain' => 'p', 'PATH' => '/p', 'URL' => 'https://x',
		    'Slug' => 'p', 'Type' => ConfigType::Plugin->value, 'File' => __FILE__
		);
		$this->expectException(\Exception::class);
		$p->validate_config($pluginCfg);
	}

	/**
	 * @covers ::validate_config
	 */
	public function test_validate_config_theme_missing_key_failure(): void {
		$p = new ConfigAbstractProbe();
		// Missing StylesheetURL for theme
		$themeCfg = array(
		    'Name' => 'T', 'Version' => '1', 'TextDomain' => 't', 'PATH' => '/p', 'URL' => 'https://x',
		    'Slug' => 't', 'Type' => ConfigType::Theme->value, 'StylesheetDir' => '/dir'
		);
		$this->expectException(\Exception::class);
		$p->validate_config($themeCfg);
	}
}


