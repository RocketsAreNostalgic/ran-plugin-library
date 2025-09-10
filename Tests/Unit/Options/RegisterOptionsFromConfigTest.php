<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options;

use WP_Mock;
use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Options\Entity\BlogEntity;
use Ran\PluginLib\Options\Entity\UserEntity;
use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Util\ExpectLogTrait;

/**
 * Tests for RegisterOptions::from_config factory (lines 339-356).
 *
 * Covers default args handling, scope resolution via ScopeResolver, and forwarding
 * to _from_config with derived (scope, storage_args).
 */
final class RegisterOptionsFromConfigTest extends PluginLibTestCase {
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
			public function options(array $args = array()): RegisterOptions {
				return RegisterOptions::site('unused');
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
	 * @covers \Ran\PluginLib\Options\RegisterOptions::from_config
	 */
	public function test_from_config_defaults_to_site_scope(): void {
		$cfg  = $this->makeConfig();
		$opts = RegisterOptions::from_config($cfg);

		// Expect constructor initialization log captured via Config-provided logger
		$this->expectLog('debug', "RegisterOptions: Initialized with main option 'test_plugin_options'. Loaded 0 existing sub-options.", 1);

		$this->assertInstanceOf(RegisterOptions::class, $opts);
		// Site scope defaults: null storage_scope and empty storage_args
		$scope = $this->_get_protected_property_value($opts, 'storage_scope');
		$args  = $this->_get_protected_property_value($opts, 'storage_args');
		$this->assertSame(null, $scope);
		$this->assertSame(array(), $args);
		$this->assertTrue($opts->supports_autoload());
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::from_config
	 */
	public function test_from_config_network_scope_string(): void {
		$cfg  = $this->makeConfig();
		$opts = RegisterOptions::from_config($cfg, array('scope' => 'network'));

		$scope = $this->_get_protected_property_value($opts, 'storage_scope');
		$args  = $this->_get_protected_property_value($opts, 'storage_args');
		$this->assertSame('network', $scope);
		$this->assertSame(array(), $args);
		// Network storage does not support autoload
		$this->assertFalse($opts->supports_autoload());
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::from_config
	 */
	public function test_from_config_blog_scope_requires_entity_throws(): void {
		$cfg = $this->makeConfig();
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('requires an entity');
		RegisterOptions::from_config($cfg, array('scope' => 'blog'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::from_config
	 */
	public function test_from_config_blog_with_entity(): void {
		$cfg    = $this->makeConfig();
		$entity = new BlogEntity(123);

		$opts  = RegisterOptions::from_config($cfg, array('scope' => 'blog', 'entity' => $entity));
		$scope = $this->_get_protected_property_value($opts, 'storage_scope');
		$args  = $this->_get_protected_property_value($opts, 'storage_args');

		$this->assertSame('blog', $scope);
		$this->assertSame(array('blog_id' => 123), $args);
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::from_config
	 */
	public function test_from_config_user_with_entity(): void {
		$cfg    = $this->makeConfig();
		$entity = new UserEntity(5, true, 'option');

		$opts  = RegisterOptions::from_config($cfg, array('scope' => 'user', 'entity' => $entity));
		$scope = $this->_get_protected_property_value($opts, 'storage_scope');
		$args  = $this->_get_protected_property_value($opts, 'storage_args');

		$this->assertSame('user', $scope);
		$this->assertSame(array(
		    'user_id'      => 5,
		    'user_global'  => true,
		    'user_storage' => 'option',
		), $args);
	}
}
