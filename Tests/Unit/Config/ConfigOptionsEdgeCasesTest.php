<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Config;

use WP_Mock;
use Ran\PluginLib\Options\OptionScope;
use Ran\PluginLib\Util\ExpectLogTrait;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Tests\Unit\TestClasses\TestableConfig;

/**
 * Tests for Config::options() edge cases and uncovered lines.
 *
 * @covers \Ran\PluginLib\Config\Config::options
 */
final class ConfigOptionsEdgeCasesTest extends PluginLibTestCase {
	use ExpectLogTrait;

	private array $loggedMessages = array();

	public function setUp(): void {
		parent::setUp();
		WP_Mock::userFunction('sanitize_key')
			->andReturnUsing(function ($v) {
				$s = strtolower(preg_replace('/[^a-z0-9_\-]/i', '', (string) $v));
				return trim($s, '_');
			})
			->byDefault();
		WP_Mock::userFunction('sanitize_title')->andReturn('test-plugin');
	}

	/**
	 * @covers \Ran\PluginLib\Config\Config::options
	 * Tests unknown argument handling with warning
	 */
	public function test_options_invalid_policy_triggers_warning_path(): void {
		$cfg = new \Ran\PluginLib\Config\Config();

		// Set up minimal config data using reflection on parent class _unified_cache property
		$reflection    = new \ReflectionClass(\Ran\PluginLib\Config\ConfigAbstract::class);
		$cacheProperty = $reflection->getProperty('_unified_cache');
		$cacheProperty->setAccessible(true);
		$cacheProperty->setValue($cfg, array(
			'RAN' => array('AppOption' => 'test_plugin_options')
		));

		$main = 'test_plugin_options';
		WP_Mock::userFunction('get_option')->with($main, array())->once()->andReturn(array());

		// Create a logger with warning method to satisfy method_exists check
		$logger = $this->createMock(\Ran\PluginLib\Util\Logger::class);
		$logger->expects($this->once())
			->method('warning')
			->with('Config::options(): Ignored args: policy');

		// Set the logger on config to ensure get_logger() returns it
		$cfg->set_logger($logger);

		// Pass policy as unknown argument - this should trigger unknown args warning
		$invalidPolicy = new \stdClass();
		$opts          = $cfg->options(array('policy' => $invalidPolicy));

		$this->assertInstanceOf(\Ran\PluginLib\Options\RegisterOptions::class, $opts);
	}

	/**
	 * @covers \Ran\PluginLib\Config\Config::options
	 * Covers line 115: network scope sets scope to 'network' and ignores entity
	 */
	public function test_options_network_scope_ignores_entity(): void {
		$cfg = new \Ran\PluginLib\Config\Config();

		// Set up config data via reflection
		$reflection    = new \ReflectionClass(\Ran\PluginLib\Config\ConfigAbstract::class);
		$cacheProperty = $reflection->getProperty('_unified_cache');
		$cacheProperty->setAccessible(true);
		$cacheProperty->setValue($cfg, array(
			'RAN' => array('AppOption' => 'test_plugin_options')
		));

		// Guard potential initial site read during construction, and expect network storage path later
		\WP_Mock::userFunction('get_option')->andReturn(array());
		// Expect network storage path: site option is used
		\WP_Mock::userFunction('get_site_option')->once()->andReturn(array());

		$opts = $cfg->options(array(
			'scope'  => 'network',
			'entity' => new \Ran\PluginLib\Options\Entity\BlogEntity(999), // should be ignored
		));

		$this->assertInstanceOf(\Ran\PluginLib\Options\RegisterOptions::class, $opts);
		// NetworkOptionStorage::supports_autoload() is false
		$this->assertFalse($opts->supports_autoload());
	}

	/**
	 * @covers \Ran\PluginLib\Config\Config::options
	 * Also cover line 115 via OptionScope enum input.
	 */
	public function test_options_network_scope_enum_ignores_entity(): void {
		$cfg = new \Ran\PluginLib\Config\Config();

		$reflection    = new \ReflectionClass(\Ran\PluginLib\Config\ConfigAbstract::class);
		$cacheProperty = $reflection->getProperty('_unified_cache');
		$cacheProperty->setAccessible(true);
		$cacheProperty->setValue($cfg, array(
			'RAN' => array('AppOption' => 'test_plugin_options')
		));

		\WP_Mock::userFunction('get_option')->andReturn(array());
		\WP_Mock::userFunction('get_site_option')->andReturn(array());

		$opts = $cfg->options(array(
			'scope'  => \Ran\PluginLib\Options\OptionScope::Network,
			'entity' => new \Ran\PluginLib\Options\Entity\BlogEntity(999), // should be ignored
		));

		$this->assertInstanceOf(\Ran\PluginLib\Options\RegisterOptions::class, $opts);
		$this->assertFalse($opts->supports_autoload());
	}

	/**
	 * @covers \Ran\PluginLib\Config\Config::options
	 * Covers line 120: blog scope without entity throws
	 */
	public function test_options_blog_scope_without_entity_throws(): void {
		$cfg = new \Ran\PluginLib\Config\Config();

		// Set up config data via reflection
		$reflection    = new \ReflectionClass(\Ran\PluginLib\Config\ConfigAbstract::class);
		$cacheProperty = $reflection->getProperty('_unified_cache');
		$cacheProperty->setAccessible(true);
		$cacheProperty->setValue($cfg, array(
			'RAN' => array('AppOption' => 'test_plugin_options')
		));

		$this->expectException(\InvalidArgumentException::class);
		$cfg->options(array('scope' => 'blog'));
	}

	/**
	 * @covers \Ran\PluginLib\Config\Config::options
	 * Covers line 126: user scope without entity throws
	 */
	public function test_options_user_scope_without_entity_throws(): void {
		$cfg = new \Ran\PluginLib\Config\Config();

		// Set up config data via reflection
		$reflection    = new \ReflectionClass(\Ran\PluginLib\Config\ConfigAbstract::class);
		$cacheProperty = $reflection->getProperty('_unified_cache');
		$cacheProperty->setAccessible(true);
		$cacheProperty->setValue($cfg, array(
			'RAN' => array('AppOption' => 'test_plugin_options')
		));

		$this->expectException(\InvalidArgumentException::class);
		$cfg->options(array('scope' => 'user'));
	}

	/**
	 * @covers \Ran\PluginLib\Config\Config::options
	 * Covers line 131: fallback to site on unknown scope (entity ignored)
	 */
	public function test_options_unknown_scope_falls_back_to_site_and_ignores_entity(): void {
		$cfg = new \Ran\PluginLib\Config\Config();

		// Set up config data via reflection
		$reflection    = new \ReflectionClass(\Ran\PluginLib\Config\ConfigAbstract::class);
		$cacheProperty = $reflection->getProperty('_unified_cache');
		$cacheProperty->setAccessible(true);
		$cacheProperty->setValue($cfg, array(
			'RAN' => array('AppOption' => 'test_plugin_options')
		));

		// Site storage path: uses get_option
		\WP_Mock::userFunction('get_option')->once()->andReturn(array());

		$opts = $cfg->options(array(
			'scope'  => 'unknown-scope',
			'entity' => new \Ran\PluginLib\Options\Entity\BlogEntity(123), // ignored
		));

		$this->assertInstanceOf(\Ran\PluginLib\Options\RegisterOptions::class, $opts);
		$this->assertTrue($opts->supports_autoload()); // site storage supports autoload
	}

	/**
	 * @covers \Ran\PluginLib\Config\Config::options
	 * Explicit site scope should ignore entity and use site storage.
	 */
	public function test_options_site_scope_ignores_entity(): void {
		$cfg = new \Ran\PluginLib\Config\Config();

		$reflection    = new \ReflectionClass(\Ran\PluginLib\Config\ConfigAbstract::class);
		$cacheProperty = $reflection->getProperty('_unified_cache');
		$cacheProperty->setAccessible(true);
		$cacheProperty->setValue($cfg, array(
			'RAN' => array('AppOption' => 'test_plugin_options')
		));

		// Site storage path: uses get_option
		\WP_Mock::userFunction('get_option')->once()->andReturn(array());

		$opts = $cfg->options(array(
			'scope'  => 'site',
			'entity' => new \Ran\PluginLib\Options\Entity\BlogEntity(999), // ignored
		));

		$this->assertInstanceOf(\Ran\PluginLib\Options\RegisterOptions::class, $opts);
		$this->assertTrue($opts->supports_autoload());
	}

	/**
	 * @covers \Ran\PluginLib\Config\Config::options
	 * Tests line 130: user_id assignment for user scope
	 */
	public function test_options_user_scope_with_user_id(): void {
		$cfg = new \Ran\PluginLib\Config\Config();

		// Set up config data via reflection
		$reflection    = new \ReflectionClass(\Ran\PluginLib\Config\ConfigAbstract::class);
		$cacheProperty = $reflection->getProperty('_unified_cache');
		$cacheProperty->setAccessible(true);
		$cacheProperty->setValue($cfg, array(
			'RAN' => array('AppOption' => 'test_plugin_options')
		));

		$main = 'test_plugin_options';
		WP_Mock::userFunction('get_option')->andReturn(array());
		WP_Mock::userFunction('get_user_meta')->andReturn(array());

		$opts = $cfg->options(array(
			'scope'  => 'user',
			'entity' => new \Ran\PluginLib\Options\Entity\UserEntity(42)
		));

		$this->assertInstanceOf(\Ran\PluginLib\Options\RegisterOptions::class, $opts);
	}

	/**
	 * @covers \Ran\PluginLib\Config\Config::options
	 * Tests line 136: user_storage assignment for user scope
	 */
	public function test_options_user_scope_with_user_storage(): void {
		$cfg = new \Ran\PluginLib\Config\Config();

		// Set up config data via reflection
		$reflection    = new \ReflectionClass(\Ran\PluginLib\Config\ConfigAbstract::class);
		$cacheProperty = $reflection->getProperty('_unified_cache');
		$cacheProperty->setAccessible(true);
		$cacheProperty->setValue($cfg, array(
			'RAN' => array('AppOption' => 'test_plugin_options')
		));

		$main = 'test_plugin_options';
		WP_Mock::userFunction('get_option')->andReturn(array());
		WP_Mock::userFunction('get_user_option')->andReturn(array());

		$opts = $cfg->options(array(
			'scope'  => 'user',
			'entity' => new \Ran\PluginLib\Options\Entity\UserEntity(42, false, 'option')
		));

		$this->assertInstanceOf(\Ran\PluginLib\Options\RegisterOptions::class, $opts);
	}

	/**
	 * @covers \Ran\PluginLib\Config\Config::options
	 * Tests line 139: default user_storage to 'meta' when null
	 */
	public function test_options_user_scope_defaults_to_meta_storage(): void {
		$cfg = new \Ran\PluginLib\Config\Config();

		// Set up config data via reflection
		$reflection    = new \ReflectionClass(\Ran\PluginLib\Config\ConfigAbstract::class);
		$cacheProperty = $reflection->getProperty('_unified_cache');
		$cacheProperty->setAccessible(true);
		$cacheProperty->setValue($cfg, array(
			'RAN' => array('AppOption' => 'test_plugin_options')
		));

		$main = 'test_plugin_options';
		WP_Mock::userFunction('get_option')->andReturn(array());
		// Mock user meta function for user scope with meta storage (default when null)
		WP_Mock::userFunction('get_user_meta')->andReturn(array());

		$opts = $cfg->options(array(
			'scope'  => 'user',
			'entity' => new \Ran\PluginLib\Options\Entity\UserEntity(42)
		));

		$this->assertInstanceOf(\Ran\PluginLib\Options\RegisterOptions::class, $opts);
	}

	/**
	 * @covers \Ran\PluginLib\Config\Config::options
	 * Tests lines 142-150: unknown args handling and potential warning
	 */
	public function test_options_unknown_args_handling(): void {
		$cfg = new \Ran\PluginLib\Config\Config();

		// Set up config data via reflection
		$reflection    = new \ReflectionClass(\Ran\PluginLib\Config\ConfigAbstract::class);
		$cacheProperty = $reflection->getProperty('_unified_cache');
		$cacheProperty->setAccessible(true);
		$cacheProperty->setValue($cfg, array(
			'RAN' => array('AppOption' => 'test_plugin_options')
		));

		$main = 'test_plugin_options';
		WP_Mock::userFunction('get_option')->with($main, array())->once()->andReturn(array());

		$opts = $cfg->options(array(
			'unknown_arg1' => 'value1',
			'unknown_arg2' => 'value2'
		));

		// Unknown args should be ignored and the method should return a valid RegisterOptions instance
		$this->assertInstanceOf(\Ran\PluginLib\Options\RegisterOptions::class, $opts);
	}

	/**
	 * @covers \Ran\PluginLib\Config\Config::options
	 * Tests initial values parameter handling
	 */
	public function test_options_with_initial_values_calls_with_defaults(): void {
		$cfg = new \Ran\PluginLib\Config\Config();

		// Set up config data via reflection
		$reflection    = new \ReflectionClass(\Ran\PluginLib\Config\ConfigAbstract::class);
		$cacheProperty = $reflection->getProperty('_unified_cache');
		$cacheProperty->setAccessible(true);
		$cacheProperty->setValue($cfg, array(
			'RAN' => array('AppOption' => 'test_plugin_options')
		));

		$main           = 'test_plugin_options';
		$initial_values = new \stdClass();
		// Use flexible mock that accepts any parameters
		WP_Mock::userFunction('get_option')->andReturn(array());

		$opts = $cfg->options(array(
			'initial_values' => $initial_values
		));

		$this->assertInstanceOf(\Ran\PluginLib\Options\RegisterOptions::class, $opts);
	}

	/**
	 * @covers \Ran\PluginLib\Config\Config::options
	 * Tests line 160: with_defaults fluent call when initial is not empty
	 */
	public function test_options_with_initial_values_calls_with_defaults_2(): void {
		$cfg = new \Ran\PluginLib\Config\Config();

		// Set up config data via reflection
		$reflection    = new \ReflectionClass(\Ran\PluginLib\Config\ConfigAbstract::class);
		$cacheProperty = $reflection->getProperty('_unified_cache');
		$cacheProperty->setAccessible(true);
		$cacheProperty->setValue($cfg, array(
			'RAN' => array('AppOption' => 'test_plugin_options')
		));

		$main           = 'test_plugin_options';
		$initial_values = array('key1' => 'value1', 'key2' => 'value2');
		// Use flexible mock that accepts any parameters
		WP_Mock::userFunction('get_option')->andReturn(array());

		$opts = $cfg->options(array(
			'initial' => $initial_values
		));

		$this->assertInstanceOf(\Ran\PluginLib\Options\RegisterOptions::class, $opts);
	}

	/**
	 * @covers \Ran\PluginLib\Config\Config::options
	 * Tests line 163: with_schema fluent call when schema is not empty
	 */
	public function test_options_with_schema_calls_with_schema(): void {
		$cfg = new \Ran\PluginLib\Config\Config();

		// Set up config data via reflection
		$reflection    = new \ReflectionClass(\Ran\PluginLib\Config\ConfigAbstract::class);
		$cacheProperty = $reflection->getProperty('_unified_cache');
		$cacheProperty->setAccessible(true);
		$cacheProperty->setValue($cfg, array(
			'RAN' => array('AppOption' => 'test_plugin_options')
		));

		$main = 'test_plugin_options';
		WP_Mock::userFunction('get_option')->with($main, array())->once()->andReturn(array());

		$schema = array(
			'test_option' => array(
				'default'  => 'default_value',
				'validate' => function($v) {
					return is_string($v);
				}
			)
		);

		$opts = $cfg->options(array());
		// Apply schema using fluent API; ensure no writes occur via options() itself
		$opts->with_schema($schema, true, false);

		$this->assertInstanceOf(\Ran\PluginLib\Options\RegisterOptions::class, $opts);
	}

	/**
	 * @covers \Ran\PluginLib\Config\Config::options
	 * Tests line 166: with_policy fluent call when policy is not null
	 */
	public function test_options_with_valid_policy_calls_with_policy(): void {
		$cfg = new \Ran\PluginLib\Config\Config();

		// Set up config data via reflection
		$reflection    = new \ReflectionClass(\Ran\PluginLib\Config\ConfigAbstract::class);
		$cacheProperty = $reflection->getProperty('_unified_cache');
		$cacheProperty->setAccessible(true);
		$cacheProperty->setValue($cfg, array(
			'RAN' => array('AppOption' => 'test_plugin_options')
		));

		$main = 'test_plugin_options';
		WP_Mock::userFunction('get_option')->with($main, array())->once()->andReturn(array());

		$policy = $this->createMock(\Ran\PluginLib\Options\Policy\WritePolicyInterface::class);
		$opts   = $cfg->options(array('policy' => $policy));

		$this->assertInstanceOf(\Ran\PluginLib\Options\RegisterOptions::class, $opts);
	}

	/**
	 * @covers \Ran\PluginLib\Config\Config::options
	 * Tests user scope with OptionScope enum instead of string
	 */
	public function test_options_user_scope_with_enum(): void {
		$cfg = new \Ran\PluginLib\Config\Config();

		// Set up config data via reflection
		$reflection    = new \ReflectionClass(\Ran\PluginLib\Config\ConfigAbstract::class);
		$cacheProperty = $reflection->getProperty('_unified_cache');
		$cacheProperty->setAccessible(true);
		$cacheProperty->setValue($cfg, array(
			'RAN' => array('AppOption' => 'test_plugin_options')
		));

		$main = 'test_plugin_options';
		WP_Mock::userFunction('get_option')->andReturn(array());
		// Mock user meta function for user scope with enum
		WP_Mock::userFunction('get_user_meta')->andReturn(array());

		$opts = $cfg->options(array(
			'scope'  => OptionScope::User,
			'entity' => new \Ran\PluginLib\Options\Entity\UserEntity(42, true)
		));

		$this->assertInstanceOf(\Ran\PluginLib\Options\RegisterOptions::class, $opts);
	}

	/**
	 * @covers \Ran\PluginLib\Config\Config::options
	 * Tests blog scope with blog_id
	 */
	public function test_options_blog_scope_with_blog_id(): void {
		$cfg = new \Ran\PluginLib\Config\Config();

		// Set up config data via reflection
		$reflection    = new \ReflectionClass(\Ran\PluginLib\Config\ConfigAbstract::class);
		$cacheProperty = $reflection->getProperty('_unified_cache');
		$cacheProperty->setAccessible(true);
		$cacheProperty->setValue($cfg, array(
			'RAN' => array('AppOption' => 'test_plugin_options')
		));

		$main = 'test_plugin_options';
		WP_Mock::userFunction('get_option')->andReturn(array());
		// Mock blog option function for blog scope
		WP_Mock::userFunction('get_blog_option')->andReturn(array());

		$opts = $cfg->options(array(
			'scope'  => 'blog',
			'entity' => new \Ran\PluginLib\Options\Entity\BlogEntity(5)
		));

		$this->assertInstanceOf(\Ran\PluginLib\Options\RegisterOptions::class, $opts);
	}
}
