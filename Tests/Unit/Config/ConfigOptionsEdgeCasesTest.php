<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Config;

use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Tests\Unit\TestClasses\TestableConfig;
use Ran\PluginLib\Util\ExpectLogTrait;
use Ran\PluginLib\Options\OptionScope;
use WP_Mock;

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
	 * Tests lines 114-121: invalid policy handling - forces execution of lines 116-119
	 */
	public function test_options_invalid_policy_triggers_warning_path(): void {
		// Create Config instance using reflection to access parent class property
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
			->with('Config::options(): Ignored policy (must implement WritePolicyInterface).');
		
		// Set the logger on config to ensure get_logger() returns it
		$cfg->set_logger($logger);

		// Pass invalid policy (not implementing WritePolicyInterface) - this should trigger lines 114-121
		$invalidPolicy = new \stdClass();
		$opts          = $cfg->options(array('policy' => $invalidPolicy));

		$this->assertInstanceOf(\Ran\PluginLib\Options\RegisterOptions::class, $opts);
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
			'scope'   => 'user',
			'user_id' => 42
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
			'scope'        => 'user',
			'user_id'      => 42,
			'user_storage' => 'option'
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
			'scope'        => 'user',
			'user_id'      => 42,
			'user_storage' => null
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

		$opts = $cfg->options(array('initial_values' => $initial_values));

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
		WP_Mock::userFunction('update_option')->andReturn(true);

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

		$opts = $cfg->options(array(
			'schema'        => $schema,
			'seed_defaults' => true,
			'flush'         => false
		));

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

		$policy = $this->createMock(\Ran\PluginLib\Options\WritePolicyInterface::class);
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
			'scope'       => OptionScope::User,
			'user_id'     => 42,
			'user_global' => true
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
			'scope'   => 'blog',
			'blog_id' => 5
		));

		$this->assertInstanceOf(\Ran\PluginLib\Options\RegisterOptions::class, $opts);
	}
}
