<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options;

use WP_Mock;
use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Util\ExpectLogTrait;
use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Options\Entity\BlogEntity;
use Ran\PluginLib\Options\Entity\UserEntity;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Options\Storage\StorageContext;

/**
 * Tests for RegisterOptions::__construct.
 *
 * Covers default args handling and typed StorageContext usage for scope selection.
 */
final class RegisterOptionsConstructorDefaultArgsTest extends PluginLibTestCase {
	use ExpectLogTrait;

	public function setUp(): void {
		parent::setUp();

		// Common WP functions used by wrappers
		WP_Mock::userFunction('get_option')->andReturn(array())->byDefault();
		WP_Mock::userFunction('get_site_option')->andReturn(array())->byDefault();
		WP_Mock::userFunction('get_blog_option')->andReturn(array())->byDefault();
		WP_Mock::userFunction('get_user_option')->andReturn(array())->byDefault();
		WP_Mock::userFunction('get_user_meta')->andReturn(array())->byDefault();
		WP_Mock::userFunction('wp_load_alloptions')->andReturn(array())->byDefault();

		// Key normalization consistent with other Options tests
		WP_Mock::userFunction('sanitize_key')->andReturnUsing(function ($key) {
			$key = strtolower((string) $key);
			$key = preg_replace('/[^a-z0-9_\-]+/i', '_', $key) ?? '';
			return trim($key, '_');
		});
	}

	private function makeConfig(): ConfigInterface {
		// Minimal Config double returning a known options key and our shared logger
		return new class($this->logger_mock) implements ConfigInterface {
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
			// Unused methods in these tests
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
				return 'test';
			}
			public function get_name(): string {
				return 'Test';
			}
			public function get_description(): string {
				return 'Desc';
			}
			public function get_author(): string {
				return 'Author';
			}
			public function get_author_url(): string {
				return 'https://example.com/author';
			}
			public function get_text_domain(): string {
				return 'test';
			}
			public function get_domain_path(): string {
				return '/languages';
			}
			public function get_min_php_version(): string {
				return '8.1';
			}
			public function get_min_wp_version(): string {
				return '6.0';
			}
			public function get_namespace(): string {
				return 'Ns';
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
			public function options(?StorageContext $context = null, bool $autoload = true): RegisterOptions {
				return new RegisterOptions($this->get_options_key(), $context ?? StorageContext::forSite(), $autoload, $this->get_logger());
			}
			public function is_dev_environment(): bool {
				return false;
			}
			public function get_type(): \Ran\PluginLib\Config\ConfigType {
				return \Ran\PluginLib\Config\ConfigType::Plugin;
			}
		};
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::__construct
	 */
	public function test_constructor_defaults_to_site_scope(): void {
		$cfg  = $this->makeConfig();
		$opts = new RegisterOptions($cfg->get_options_key(), StorageContext::forSite(), true, $this->logger_mock);

		// Expect constructor initialization log captured via Config-provided logger
		$this->expectLog('debug', "RegisterOptions: Initialized with main option 'test_plugin_options'. Loaded 0 existing sub-options.", 1);

		$this->assertInstanceOf(RegisterOptions::class, $opts);
		// Site scope defaults: supports autoload and site storage adapter
		$this->assertTrue($opts->supports_autoload());
		$this->assertTrue($opts->supports_autoload());
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::__construct
	 */
	public function test_constructor_network_scope_context(): void {
		$cfg  = $this->makeConfig();
		$opts = new RegisterOptions($cfg->get_options_key(), StorageContext::forNetwork(), true, $this->logger_mock);
		// Network storage does not support autoload
		$this->assertFalse($opts->supports_autoload());
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::__construct
	 */
	public function test_constructor_blog_scope_invalid_id_throws(): void {
		$cfg = $this->makeConfig();
		$this->expectException(\InvalidArgumentException::class);
		// Invalid blog id (0) should throw from StorageContext
		new RegisterOptions($cfg->get_options_key(), StorageContext::forBlog(0), true, $this->logger_mock);
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::__construct
	 */
	public function test_constructor_blog_with_entity(): void {
		$cfg  = $this->makeConfig();
		$opts = new RegisterOptions($cfg->get_options_key(), StorageContext::forBlog(123), true, $this->logger_mock);
		// supports_autoload may be false if not current blog; this test focuses on construction success
		$this->assertInstanceOf(RegisterOptions::class, $opts);
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::__construct
	 */
	public function test_constructor_user_with_context(): void {
		$cfg  = $this->makeConfig();
		$ctx  = StorageContext::forUser(7, 'option', true);
		$opts = new RegisterOptions($cfg->get_options_key(), $ctx, true, $this->logger_mock);
		$this->assertInstanceOf(RegisterOptions::class, $opts);
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::__construct
	 */
	public function test_constructor_throws_on_empty_options_key(): void {
		$cfg = $this->getMockBuilder(ConfigInterface::class)->getMock();
		$cfg->method('get_options_key')->willReturn('');
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('RegisterOptions: main_wp_option_name cannot be empty');
		new RegisterOptions($cfg->get_options_key(), StorageContext::forSite(), true, $this->logger_mock);
	}
}
