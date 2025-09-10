<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options;

use WP_Mock;
use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Config\ConfigType;
use Ran\PluginLib\Options\OptionScope;
use Ran\PluginLib\Util\ExpectLogTrait;
use Ran\PluginLib\Config\ConfigAbstract;
use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;

/**
 * Minimal subclass to control WP behavior via WPWrappersTrait overrides.
 */
class TestableRegisterOptions extends RegisterOptions {
	public static int $currentBlogId = 123;

	public function _do_get_option(string $option, mixed $default = false): mixed {
		return array();
	}

	public function _do_get_site_option(string $option, mixed $default = false): mixed {
		return array();
	}

	public function _do_get_blog_option(int $blog_id, string $option, mixed $default = false): mixed {
		return array();
	}

	public function _do_get_user_option(int $user_id, string $option, mixed $deprecated = ''): mixed {
		return array();
	}

	public function _do_get_user_meta(int $user_id, string $key, bool $single = true): mixed {
		return array();
	}

	public function _do_get_current_blog_id(): int {
		return self::$currentBlogId;
	}

	// Write guards – throw to fail tests if any write happens during construction
	public function _do_add_option(string $option, mixed $value = '', string $deprecated = '', mixed $autoload = null): bool {
		throw new \LogicException('Unexpected site write');
	}

	public function _do_update_option(string $option, mixed $value, mixed $autoload = null): bool {
		throw new \LogicException('Unexpected site write');
	}

	public function _do_delete_option(string $option): bool {
		throw new \LogicException('Unexpected site write');
	}

	public function _do_add_site_option(string $option, mixed $value = ''): bool {
		throw new \LogicException('Unexpected network write');
	}

	public function _do_update_site_option(string $option, mixed $value): bool {
		throw new \LogicException('Unexpected network write');
	}

	public function _do_delete_site_option(string $option): bool {
		throw new \LogicException('Unexpected network write');
	}

	public function _do_add_blog_option(int $blog_id, string $option, mixed $value = ''): bool {
		throw new \LogicException('Unexpected blog write');
	}

	public function _do_update_blog_option(int $blog_id, string $option, mixed $value): bool {
		throw new \LogicException('Unexpected blog write');
	}

	public function _do_delete_blog_option(int $blog_id, string $option): bool {
		throw new \LogicException('Unexpected blog write');
	}

	public function _do_update_user_option(int $user_id, string $option, mixed $value, bool $global = false): bool {
		throw new \LogicException('Unexpected user option write');
	}

	public function _do_delete_user_option(int $user_id, string $option_name, bool $is_global = false): bool {
		throw new \LogicException('Unexpected user option write');
	}

	public function _do_update_user_meta(int $user_id, string $key, mixed $value, string $prev_value = ''): int|bool {
		throw new \LogicException('Unexpected user meta write');
	}

	public function _do_delete_user_meta(int $user_id, string $key): bool {
		throw new \LogicException('Unexpected user meta write');
	}
}

/**
 * Tests for RegisterOptions static constructors.
 *
 * Phase 0: Verifies constructors create correctly scoped instances
 * without performing any writes during construction.
 *
 * @uses \Ran\PluginLib\Config\ConfigInterface
 */
final class RegisterOptionsConstructorTest extends PluginLibTestCase {
	use ExpectLogTrait;

	public function setUp(): void {
		parent::setUp();

		// Mock basic WordPress functions that WPWrappersTrait calls
		WP_Mock::userFunction('get_option')->andReturn(array())->byDefault();
		WP_Mock::userFunction('get_site_option')->andReturn(array())->byDefault();
		WP_Mock::userFunction('get_blog_option')->andReturn(array())->byDefault();
		WP_Mock::userFunction('get_user_option')->andReturn(array())->byDefault();
		WP_Mock::userFunction('get_user_meta')->andReturn(array())->byDefault();
		WP_Mock::userFunction('wp_load_alloptions')->andReturn(array())->byDefault();

		// Mock sanitize_key to properly handle key normalization
		WP_Mock::userFunction('sanitize_key')->andReturnUsing(function($key) {
			$key = strtolower($key);
			// Replace any run of non [a-z0-9_\-] with a single underscore (preserve hyphens)
			$key = preg_replace('/[^a-z0-9_\-]+/i', '_', $key) ?? '';
			// Trim underscores at edges (preserve leading/trailing hyphens if present)
			return trim($key, '_');
		});

		// Mock write functions to ensure they're never called
		WP_Mock::userFunction('add_option')->never();
		WP_Mock::userFunction('add_site_option')->never();
		WP_Mock::userFunction('update_site_option')->never();
		WP_Mock::userFunction('delete_site_option')->never();
		WP_Mock::userFunction('add_blog_option')->never();
		WP_Mock::userFunction('update_blog_option')->never();
		WP_Mock::userFunction('delete_blog_option')->never();
		WP_Mock::userFunction('update_user_option')->never();
		WP_Mock::userFunction('delete_user_option')->never();
		WP_Mock::userFunction('update_user_meta')->never();
		WP_Mock::userFunction('delete_user_meta')->never();
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::site
	 */
	public function test_site_constructor_creates_instance_and_supports_autoload(): void {
		$opts = TestableRegisterOptions::site('my_option');
		$this->assertInstanceOf(RegisterOptions::class, $opts);
		$this->assertTrue($opts->supports_autoload());
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::blog
	 */
	public function test_blog_constructor_non_current_blog_no_autoload(): void {
		TestableRegisterOptions::$currentBlogId = 1;
		WP_Mock::userFunction('get_current_blog_id')->andReturn(1);
		$opts = TestableRegisterOptions::blog('my_option', 999);
		$this->assertInstanceOf(RegisterOptions::class, $opts);
		$this->assertFalse($opts->supports_autoload());
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::blog
	 */
	public function test_blog_constructor_current_blog_supports_autoload(): void {
		TestableRegisterOptions::$currentBlogId = 999;
		WP_Mock::userFunction('get_current_blog_id')->andReturn(999);
		$opts = TestableRegisterOptions::blog('my_option', 999);
		$this->assertInstanceOf(RegisterOptions::class, $opts);
		$this->assertTrue($opts->supports_autoload());
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::blog
	 */
	public function test_blog_constructor_explicit_autoload_true_for_current_blog(): void {
		TestableRegisterOptions::$currentBlogId = 999;
		WP_Mock::userFunction('get_current_blog_id')->andReturn(999);
		$opts = TestableRegisterOptions::blog('my_option', 999, true);
		$this->assertInstanceOf(RegisterOptions::class, $opts);
		$this->assertTrue($opts->supports_autoload());
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::blog
	 */
	public function test_blog_constructor_explicit_autoload_false_for_current_blog(): void {
		TestableRegisterOptions::$currentBlogId = 999;
		WP_Mock::userFunction('get_current_blog_id')->andReturn(999);
		$opts = TestableRegisterOptions::blog('my_option', 999, false);
		$this->assertInstanceOf(RegisterOptions::class, $opts);
		// Current blog always supports autoload, regardless of autoload_on_create setting
		$this->assertTrue($opts->supports_autoload());
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::user
	 */
	public function test_user_constructor_creates_instance_and_autoload_false(): void {
		$opts = TestableRegisterOptions::user('my_option', 42);
		$this->assertInstanceOf(RegisterOptions::class, $opts);
		$this->assertFalse($opts->supports_autoload());
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::network
	 */
	public function test_network_constructor_creates_instance_and_autoload_false(): void {
		$opts = TestableRegisterOptions::network('my_option');
		$this->assertInstanceOf(RegisterOptions::class, $opts);
		$this->assertFalse($opts->supports_autoload()); // Network options don't support autoload
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::from_config
	 */
	public function test_from_config_constructor_with_minimal_config(): void {
		// Create a minimal Config double with collecting logger supplied via constructor
		$config = new class($this->logger_mock) implements ConfigInterface {
			private Logger $logger;
			public function __construct(Logger $logger) {
				$this->logger = $logger;
			}
			public function get_options_key(): string {
				return 'test_plugin_options';
			}

			public function get_logger(): Logger {
				return $this->logger;
			}

			public function get_base_path(): string {
				return '/test/path';
			}

			public function get_base_url(): string {
				return 'https://example.com';
			}

			public function get_version(): string {
				return '1.0.0';
			}

			public function get_slug(): string {
				return 'test-plugin';
			}

			public function get_name(): string {
				return 'Test Plugin';
			}

			public function get_description(): string {
				return 'Test Plugin Description';
			}

			public function get_author(): string {
				return 'Test Author';
			}

			public function get_author_url(): string {
				return 'https://example.com/author';
			}

			public function get_text_domain(): string {
				return 'test-plugin';
			}

			public function get_domain_path(): string {
				return '/languages';
			}

			public function get_min_php_version(): string {
				return '7.4';
			}

			public function get_min_wp_version(): string {
				return '5.0';
			}

			public function get_namespace(): string {
				return 'TestPlugin';
			}

			public function is_development(): bool {
				return false;
			}

			public function is_production(): bool {
				return true;
			}

			public function get_config(): array {
				return array();
			}

			public function options(array $args = array()): RegisterOptions {
				return RegisterOptions::site('test_options');
			}

			public function is_dev_environment(): bool {
				return false;
			}

			public function get_type(): ConfigType {
				return ConfigType::Plugin;
			}
		};

		$opts = TestableRegisterOptions::from_config($config);
		// Expect constructor initialization log captured via Config-provided logger
		$this->expectLog('debug', "RegisterOptions: Initialized with main option 'test_plugin_options'. Loaded 0 existing sub-options.", 1);
		$this->assertInstanceOf(RegisterOptions::class, $opts);
		$this->assertTrue($opts->supports_autoload()); // Site scope by default
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::from_config
	 */
	public function test_from_config_with_explicit_scope_blog_current(): void {
		$config = new class($this->logger_mock) implements ConfigInterface {
			private Logger $logger;
			public function __construct(Logger $logger) {
				$this->logger = $logger;
			}
			public function get_options_key(): string {
				return 'test_plugin_options';
			}

			public function get_logger(): Logger {
				return $this->logger;
			}

			public function get_base_path(): string {
				return '/test/path';
			}

			public function get_base_url(): string {
				return 'https://example.com';
			}

			public function get_version(): string {
				return '1.0.0';
			}

			public function get_slug(): string {
				return 'test-plugin';
			}

			public function get_name(): string {
				return 'Test Plugin';
			}

			public function get_description(): string {
				return 'Test Plugin Description';
			}

			public function get_author(): string {
				return 'Test Author';
			}

			public function get_author_url(): string {
				return 'https://example.com/author';
			}

			public function get_text_domain(): string {
				return 'test-plugin';
			}

			public function get_domain_path(): string {
				return '/languages';
			}

			public function get_min_php_version(): string {
				return '7.4';
			}

			public function get_min_wp_version(): string {
				return '5.0';
			}

			public function get_namespace(): string {
				return 'TestPlugin';
			}

			public function is_development(): bool {
				return false;
			}

			public function is_production(): bool {
				return true;
			}

			public function get_config(): array {
				return array();
			}

			public function options(array $args = array()): RegisterOptions {
				return RegisterOptions::site('test_options');
			}

			public function is_dev_environment(): bool {
				return false;
			}

			public function get_type(): ConfigType {
				return ConfigType::Plugin;
			}
		};

		// Set current blog ID to match the blog scope
		TestableRegisterOptions::$currentBlogId = 456;
		WP_Mock::userFunction('get_current_blog_id')->andReturn(456);

		$opts = TestableRegisterOptions::from_config(
			$config,
			scope: OptionScope::Blog,
			storage_args: array('blog_id' => 456)
		);
		// Expect constructor initialization log captured via Config-provided logger
		$this->expectLog('debug', "RegisterOptions: Initialized with main option 'test_plugin_options'. Loaded 0 existing sub-options.", 1);
		$this->assertInstanceOf(RegisterOptions::class, $opts);
		$this->assertTrue($opts->supports_autoload()); // Current blog should support autoload
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::from_config
	 */
	public function test_from_config_with_explicit_scope_blog_non_current(): void {
		$config = new class($this->logger_mock) implements ConfigInterface {
			private Logger $logger;
			public function __construct(Logger $logger) {
				$this->logger = $logger;
			}
			public function get_options_key(): string {
				return 'test_plugin_options';
			}

			public function get_logger(): Logger {
				return $this->logger;
			}

			public function get_base_path(): string {
				return '/test/path';
			}

			public function get_base_url(): string {
				return 'https://example.com';
			}

			public function get_version(): string {
				return '1.0.0';
			}

			public function get_slug(): string {
				return 'test-plugin';
			}

			public function get_name(): string {
				return 'Test Plugin';
			}

			public function get_description(): string {
				return 'Test Plugin Description';
			}

			public function get_author(): string {
				return 'Test Author';
			}

			public function get_author_url(): string {
				return 'https://example.com/author';
			}

			public function get_text_domain(): string {
				return 'test-plugin';
			}

			public function get_domain_path(): string {
				return '/languages';
			}

			public function get_min_php_version(): string {
				return '7.4';
			}

			public function get_min_wp_version(): string {
				return '5.0';
			}

			public function get_namespace(): string {
				return 'TestPlugin';
			}

			public function is_development(): bool {
				return false;
			}

			public function is_production(): bool {
				return true;
			}

			public function get_config(): array {
				return array();
			}

			public function options(array $args = array()): RegisterOptions {
				return RegisterOptions::site('test_options');
			}

			public function is_dev_environment(): bool {
				return false;
			}

			public function get_type(): ConfigType {
				return ConfigType::Plugin;
			}
		};

		// Set current blog ID to be different from the blog scope
		TestableRegisterOptions::$currentBlogId = 123;

		$opts = TestableRegisterOptions::from_config(
			$config,
			scope: OptionScope::Blog,
			storage_args: array('blog_id' => 456)
		);
		// Expect constructor initialization log captured via Config-provided logger
		$this->expectLog('debug', "RegisterOptions: Initialized with main option 'test_plugin_options'. Loaded 0 existing sub-options.", 1);
		$this->assertInstanceOf(RegisterOptions::class, $opts);
		$this->assertFalse($opts->supports_autoload()); // Non-current blog should not support autoload
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::from_config
	 */
	public function test_from_config_throws_exception_for_empty_options_key(): void {
		$config = new class($this->logger_mock) implements ConfigInterface {
			private Logger $logger;
			public function __construct(Logger $logger) {
				$this->logger = $logger;
			}
			public function get_options_key(): string {
				return ''; // Empty options key should trigger exception
			}

			public function get_logger(): Logger {
				return $this->logger;
			}

			public function get_base_path(): string {
				return '/test/path';
			}

			public function get_base_url(): string {
				return 'https://example.com';
			}

			public function get_version(): string {
				return '1.0.0';
			}

			public function get_slug(): string {
				return 'test-plugin';
			}

			public function get_name(): string {
				return 'Test Plugin';
			}

			public function get_description(): string {
				return 'Test Plugin Description';
			}

			public function get_author(): string {
				return 'Test Author';
			}

			public function get_author_url(): string {
				return 'https://example.com/author';
			}

			public function get_text_domain(): string {
				return 'test-plugin';
			}

			public function get_domain_path(): string {
				return '/languages';
			}

			public function get_min_php_version(): string {
				return '7.4';
			}

			public function get_min_wp_version(): string {
				return '5.0';
			}

			public function get_namespace(): string {
				return 'TestPlugin';
			}

			public function is_development(): bool {
				return false;
			}

			public function is_production(): bool {
				return true;
			}

			public function get_config(): array {
				return array();
			}

			public function options(array $args = array()): RegisterOptions {
				return RegisterOptions::site('test_options');
			}

			public function is_dev_environment(): bool {
				return false;
			}

			public function get_type(): ConfigType {
				return ConfigType::Plugin;
			}
		};

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Missing or invalid options key from Config');
		TestableRegisterOptions::from_config($config);
	}
}
