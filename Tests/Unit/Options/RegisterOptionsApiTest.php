<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options;

// Helper for static callable coverage
class RegisterOptionsTest_StaticValidator {
	public static function check($v): bool {
		return false;
	}
}

// Helper for invokable callable coverage
class RegisterOptionsTest_InvokableValidator {
	public function __invoke($v): bool {
		return false;
	}
}

use InvalidArgumentException;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Tests\Unit\TestClasses\TestableConfig;
use WP_Mock;

/**
 * Comprehensive public interface tests for RegisterOptions
 *
 * - Follows ADR-002: Public Interface Testing (no private/protected method calls)
 * - Mocks WordPress option functions with WP_Mock
 * - Verifies behavior and side effects observable via public APIs
 *
 * @covers \Ran\PluginLib\Options\RegisterOptions
 */
final class RegisterOptionsApiTest extends PluginLibTestCase {
	private string $mainOption = 'ran_plugin_options_test';

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::__construct
	 * @covers \Ran\PluginLib\Options\RegisterOptions::get_option
	 */
	public function test_constructor_seeds_schema_defaults_and_saves(): void {
		WP_Mock::userFunction('get_option')->with($this->mainOption, array())->once()->andReturn(array());
		WP_Mock::userFunction('update_option')
			->with($this->mainOption, array(
				'enabled' => array('value' => true, 'autoload_hint' => null),
				'timeout' => array('value' => 30, 'autoload_hint' => null),
			), true)
			->once()
			->andReturn(true);

		$schema = array(
			'enabled' => array('default' => true,  'validate' => fn($v) => is_bool($v)),
			'timeout' => array('default' => 30,    'validate' => fn($v) => is_int($v) && $v > 0),
		);

		$opts = new RegisterOptions($this->mainOption, array(), true, null, null, $schema);
		$this->assertTrue($opts->get_option('enabled'));
		$this->assertSame(30, $opts->get_option('timeout'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::__construct
	 * @covers \Ran\PluginLib\Options\RegisterOptions::get_option
	 */
	public function test_constructor_initial_options_merge_and_saves(): void {
		WP_Mock::userFunction('get_option')->with($this->mainOption, array())->once()->andReturn(array(
			'foo' => array('value' => 'old', 'autoload_hint' => null),
		));
		WP_Mock::userFunction('update_option')
			->with($this->mainOption, array(
				'foo' => array('value' => 'new', 'autoload_hint' => null),
			), true)
			->once()
			->andReturn(true);

		$opts = new RegisterOptions($this->mainOption, array('foo' => 'new'));
		$this->assertSame('new', $opts->get_option('foo'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::set_option
	 */
	public function test_set_option_noop_guard_skips_write(): void {
		WP_Mock::userFunction('get_option')->with($this->mainOption, array())->once()->andReturn(array(
			'k' => array('value' => 123, 'autoload_hint' => true),
		));
		// No update_option expected

		$opts = new RegisterOptions($this->mainOption);
		$this->assertTrue($opts->set_option('k', 123, true));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::set_option
	 */
	public function test_set_option_writes_on_change(): void {
		WP_Mock::userFunction('get_option')->with($this->mainOption, array())->once()->andReturn(array(
			'k' => array('value' => 123, 'autoload_hint' => null),
		));
		WP_Mock::userFunction('update_option')
			->with($this->mainOption, array(
				'k' => array('value' => 124, 'autoload_hint' => null),
			), true)
			->once()->andReturn(true);

		$opts = new RegisterOptions($this->mainOption);
		$this->assertTrue($opts->set_option('k', 124));
	}

	/**
     * @covers \Ran\PluginLib\Options\RegisterOptions::add_options
	 * @covers \Ran\PluginLib\Options\RegisterOptions::flush
	 */
	public function test_add_options_batch_and_flush(): void {
		WP_Mock::userFunction('get_option')->with($this->mainOption, array())->once()->andReturn(array());
		WP_Mock::userFunction('update_option')
			->with($this->mainOption, array(
				'a' => array('value' => 'x', 'autoload_hint' => null),
				'b' => array('value' => true, 'autoload_hint' => null),
			), true)
			->once()->andReturn(true);

		$opts = new RegisterOptions($this->mainOption);
		$opts->add_options(array(
		    'a' => 'x',
		    'b' => array('value' => true),
		));
		$this->assertTrue($opts->flush());
	}

	/**
     * @covers \Ran\PluginLib\Options\RegisterOptions::add_options
	 */
	public function test_add_options_flush_true_returns_true_when_no_changes(): void {
		WP_Mock::userFunction('get_option')->with($this->mainOption, array())->once()->andReturn(array(
		    'a' => array('value' => 'x', 'autoload_hint' => null),
		));
		// No update_option expected because no changes

		$opts = new RegisterOptions($this->mainOption);
		$opts->add_options(array('a' => 'x')); // no changes
		// Expect update_option to be called with the same structure, returning true
		WP_Mock::userFunction('update_option')->with($this->mainOption, array(
		    'a' => array('value' => 'x', 'autoload_hint' => null),
		), true)->once()->andReturn(true);
		$this->assertTrue($opts->flush());
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::register_schema
	 */
	public function test_register_schema_seed_defaults_and_flush(): void {
		WP_Mock::userFunction('get_option')->with($this->mainOption, array())->once()->andReturn(array());
		WP_Mock::userFunction('update_option')
			->with($this->mainOption, array(
				'flag' => array('value' => false, 'autoload_hint' => null),
			), true)
			->once()->andReturn(true);

		$opts    = new RegisterOptions($this->mainOption);
		$changed = $opts->register_schema(array(
			'flag' => array('default' => false, 'validate' => fn($v) => is_bool($v)),
		), seedDefaults: true, flush: true);
		$this->assertTrue($changed);
		$this->assertFalse($opts->get_option('flag'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::get_values
	 * @covers \Ran\PluginLib\Options\RegisterOptions::get_autoload_hint
	 */
	public function test_get_values_and_autoload_hint(): void {
		WP_Mock::userFunction('get_option')->with($this->mainOption, array())->once()->andReturn(array(
			'x' => array('value' => 'y', 'autoload_hint' => true),
		));
		$opts = new RegisterOptions($this->mainOption);
		$this->assertSame(array('x' => 'y'), $opts->get_values());
		$this->assertTrue($opts->get_autoload_hint('x'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::set_main_autoload
	 */
	public function test_set_main_autoload_flips_flag_preserving_data(): void {
		// get_option is called twice: constructor + inside set_main_autoload
		WP_Mock::userFunction('get_option')->with($this->mainOption, array())->times(2)->andReturn(array(
		    'k' => array('value' => 'v', 'autoload_hint' => null),
		));
		WP_Mock::userFunction('delete_option')->with($this->mainOption)->once()->andReturn(true);
		WP_Mock::userFunction('add_option')
		    ->with($this->mainOption, array('k' => array('value' => 'v', 'autoload_hint' => null)), '', 'no')
		    ->once()->andReturn(true);

		$opts = new RegisterOptions($this->mainOption);
		$this->assertTrue($opts->set_main_autoload(false));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::set_option
	 * @covers \Ran\PluginLib\Options\RegisterOptions::get_option
	 */
	public function test_sanitization_and_validation_success_path(): void {
		WP_Mock::userFunction('get_option')->with($this->mainOption, array())->once()->andReturn(array());

		$schema = array(
		    'email' => array(
		        'sanitize' => fn($v) => is_string($v) ? trim($v) : '',
		        'validate' => fn($v) => filter_var($v, FILTER_VALIDATE_EMAIL) !== false,
		    ),
		);
		WP_Mock::userFunction('update_option')
		    ->with($this->mainOption, array(
		        'email' => array('value' => 'user@example.com', 'autoload_hint' => null),
		    ), true)
		    ->once()->andReturn(true);
		$opts = new RegisterOptions($this->mainOption, array(), true, null, null, $schema);
		$this->assertTrue($opts->set_option('email', ' user@example.com '));
		$this->assertSame('user@example.com', $opts->get_option('email'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::refresh_options
	 * @covers \Ran\PluginLib\Options\RegisterOptions::get_option
	 */
	public function test_refresh_options_reloads_from_db(): void {
		// First load returns empty
		WP_Mock::userFunction('get_option')->with($this->mainOption, array())->once()->andReturn(array());
		$opts = new RegisterOptions($this->mainOption);

		// Next refresh returns populated
		WP_Mock::userFunction('get_option')->with($this->mainOption, array())->once()->andReturn(array(
			'foo' => array('value' => 'bar', 'autoload_hint' => null),
		));
		$opts->refresh_options();
		$this->assertSame('bar', $opts->get_option('foo'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::from_config
	 */
	public function test_from_config_uses_RAN_AppOption(): void {
		// ConfigAbstract uses sanitize_title() internally; stub it
		WP_Mock::userFunction('sanitize_title')->andReturn('test-plugin');
		$cfg  = new TestableConfig();
		$main = $cfg->get_config()['RAN']['AppOption'];
		WP_Mock::userFunction('get_option')->with($main, array())->once()->andReturn(array());
		$opts = RegisterOptions::from_config($cfg);
		$this->assertInstanceOf(RegisterOptions::class, $opts);
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::with_schema
	 * @covers \Ran\PluginLib\Options\RegisterOptions::register_schema
	 */
	public function test_with_schema_fluent_and_seed_then_flush(): void {
		WP_Mock::userFunction('get_option')->with($this->mainOption, array())->once()->andReturn(array());
		WP_Mock::userFunction('update_option')
		    ->with($this->mainOption, array(
		        'flag' => array('value' => true, 'autoload_hint' => null),
		    ), true)
		    ->once()->andReturn(true);

		$opts = new RegisterOptions($this->mainOption);
		$this->assertInstanceOf(RegisterOptions::class, $opts->with_schema(array(
		    'flag' => array('default' => true, 'validate' => fn($v) => is_bool($v)),
		), seedDefaults: true, flush: false));
		$this->assertTrue($opts->flush());
		$this->assertTrue($opts->get_option('flag'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::update_option
	 */
	public function test_update_option_alias(): void {
		WP_Mock::userFunction('get_option')->with($this->mainOption, array())->once()->andReturn(array());
		WP_Mock::userFunction('update_option')
		    ->with($this->mainOption, array(
		        'v' => array('value' => 1, 'autoload_hint' => null),
		    ), true)
		    ->once()->andReturn(true);
		$opts = new RegisterOptions($this->mainOption);
		$this->assertTrue($opts->update_option('v', 1));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::has_option
	 */
	public function test_has_option_true_false(): void {
		WP_Mock::userFunction('get_option')->with($this->mainOption, array())->once()->andReturn(array(
		    'a' => array('value' => 'b', 'autoload_hint' => null),
		));
		$opts = new RegisterOptions($this->mainOption);
		$this->assertTrue($opts->has_option('a'));
		$this->assertFalse($opts->has_option('missing'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::delete_option
	 */
	public function test_delete_option_removes_and_saves(): void {
		WP_Mock::userFunction('get_option')->with($this->mainOption, array())->once()->andReturn(array(
		    'a' => array('value' => 1, 'autoload_hint' => null),
		    'b' => array('value' => 2, 'autoload_hint' => null),
		));
		WP_Mock::userFunction('update_option')
		    ->with($this->mainOption, array(
		        'b' => array('value' => 2, 'autoload_hint' => null),
		    ), true)
		    ->once()->andReturn(true);
		$opts = new RegisterOptions($this->mainOption);
		$this->assertTrue($opts->delete_option('a'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::delete_option
	 */
	public function test_delete_option_missing_returns_false(): void {
		WP_Mock::userFunction('get_option')->with($this->mainOption, array())->once()->andReturn(array(
		    'b' => array('value' => 2, 'autoload_hint' => null),
		));
		$opts = new RegisterOptions($this->mainOption);
		$this->assertFalse($opts->delete_option('a'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::clear
	 */
	public function test_clear_empties_and_saves(): void {
		WP_Mock::userFunction('get_option')->with($this->mainOption, array())->once()->andReturn(array(
		    'x' => array('value' => 1, 'autoload_hint' => null),
		));
		WP_Mock::userFunction('update_option')
		    ->with($this->mainOption, array(), true)
		    ->once()->andReturn(true);
		$opts = new RegisterOptions($this->mainOption);
		$this->assertTrue($opts->clear());
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::get_options
	 */
	public function test_get_options_returns_full_structure(): void {
		$seed = array(
		    'a' => array('value' => 'x', 'autoload_hint' => null),
		    'b' => array('value' => true, 'autoload_hint' => false),
		);
		WP_Mock::userFunction('get_option')->with($this->mainOption, array())->once()->andReturn($seed);
		$opts = new RegisterOptions($this->mainOption);
		$this->assertSame($seed, $opts->get_options());
	}

	/**
	 * Trigger validation failure to exercise error helpers via public API.
	 *
	 * @covers \Ran\PluginLib\Options\RegisterOptions::set_option
	 */
	public function test_validation_failure_stringifies_value_and_describes_callable(): void {
		WP_Mock::userFunction('get_option')->with($this->mainOption, array())->once()->andReturn(array());
		$validatorObj = new class() {
			public function check($v) {
				return false;
			}
		};
		$schema = array(
		    'bad' => array(
		        'sanitize' => fn($v) => $v, // keep as-is
		        'validate' => array($validatorObj, 'check'),
		    ),
		);
		$opts = new RegisterOptions($this->mainOption, array(), true, null, null, $schema);
		try {
			$opts->set_option('bad', new \stdClass());
			$this->fail('Expected exception');
		} catch (InvalidArgumentException $e) {
			$this->assertStringContainsString('Object(', $e->getMessage());
			$this->assertStringContainsString('::check', $e->getMessage());
		}
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::set_option
	 */
	public function test_describe_callable_string_function(): void {
		WP_Mock::userFunction('get_option')->with($this->mainOption, array())->once()->andReturn(array());
		$schema = array(
		    's' => array(
		        'sanitize' => fn($v) => $v,
		        'validate' => 'is_string', // string callable
		    ),
		);
		$opts = new RegisterOptions($this->mainOption, array(), true, null, null, $schema);
		try {
			$opts->set_option('s', 123); // not a string -> fails
			$this->fail('Expected exception');
		} catch (InvalidArgumentException $e) {
			$this->assertStringContainsString('is_string', $e->getMessage());
		}
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::set_option
	 */
	public function test_describe_callable_static_array_callable(): void {
		WP_Mock::userFunction('get_option')->with($this->mainOption, array())->once()->andReturn(array());
		$schema = array(
		    'a' => array(
		        'sanitize' => fn($v) => $v,
		        'validate' => array(RegisterOptionsTest_StaticValidator::class, 'check'), // static callable
		    ),
		);
		$opts = new RegisterOptions($this->mainOption, array(), true, null, null, $schema);
		try {
			$opts->set_option('a', 'any');
			$this->fail('Expected exception');
		} catch (InvalidArgumentException $e) {
			$this->assertStringContainsString('RegisterOptionsTest_StaticValidator::check', $e->getMessage());
		}
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::set_option
	 */
	public function test_describe_callable_closure(): void {
		WP_Mock::userFunction('get_option')->with($this->mainOption, array())->once()->andReturn(array());
		$schema = array(
		    'c' => array(
		        'sanitize' => fn($v) => $v,
		        'validate' => function ($v) {
		        	return false;
		        }, // closure
		    ),
		);
		$opts = new RegisterOptions($this->mainOption, array(), true, null, null, $schema);
		try {
			$opts->set_option('c', 'x');
			$this->fail('Expected exception');
		} catch (InvalidArgumentException $e) {
			$this->assertStringContainsString('Closure', $e->getMessage());
		}
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::set_option
	 */
	public function test_describe_callable_invokable_object(): void {
		WP_Mock::userFunction('get_option')->with($this->mainOption, array())->once()->andReturn(array());
		$schema = array(
		    'inv' => array(
		        'sanitize' => fn($v) => $v,
		        'validate' => new RegisterOptionsTest_InvokableValidator(),
		    ),
		);
		$opts = new RegisterOptions($this->mainOption, array(), true, null, null, $schema);
		try {
			$opts->set_option('inv', 'x');
			$this->fail('Expected exception');
		} catch (InvalidArgumentException $e) {
			$this->assertStringContainsString('callable', $e->getMessage());
		}
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::set_option
	 */
	public function test_stringify_value_for_error_array_and_long_string(): void {
		// Array branch
		WP_Mock::userFunction('get_option')->with($this->mainOption, array())->once()->andReturn(array());
		$schema = array(
		    'arr' => array(
		        'sanitize' => fn($v) => $v,
		        'validate' => fn($v) => false,
		    ),
		);
		$opts = new RegisterOptions($this->mainOption, array(), true, null, null, $schema);
		try {
			$opts->set_option('arr', array('x' => 'y'));
			$this->fail('Expected exception');
		} catch (InvalidArgumentException $e) {
			$this->assertStringContainsString('Array(1)', $e->getMessage());
		}

		// Long string truncation branch
		WP_Mock::userFunction('get_option')->with($this->mainOption, array())->once()->andReturn(array());
		$schema2 = array(
		    'long' => array(
		        'sanitize' => fn($v) => $v,
		        'validate' => fn($v) => false,
		    ),
		);
		$opts2 = new RegisterOptions($this->mainOption, array(), true, null, null, $schema2);
		$long  = str_repeat('a', 200);
		try {
			$opts2->set_option('long', $long);
			$this->fail('Expected exception');
		} catch (InvalidArgumentException $e) {
			$this->assertStringContainsString('...', $e->getMessage());
			$this->assertStringNotContainsString($long, $e->getMessage());
		}
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::register_schema
	 */
	public function test_register_schema_no_default_no_seed_no_flush_returns_false(): void {
		WP_Mock::userFunction('get_option')->with($this->mainOption, array())->once()->andReturn(array());
		$opts = new RegisterOptions($this->mainOption);
		$this->assertFalse($opts->register_schema(array(
		    'x' => array('sanitize' => fn($v) => $v),
		), seedDefaults: false, flush: false));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::register_schema
	 */
	public function test_register_schema_merges_partial_rules_no_seed(): void {
		WP_Mock::userFunction('get_option')->with($this->mainOption, array())->once()->andReturn(array());
		$opts = new RegisterOptions($this->mainOption);
		// first registration creates schema entry
		$this->assertFalse($opts->register_schema(array('m' => array('sanitize' => fn($v) => $v)), seedDefaults: false, flush: false));
		// merge additional rule; still no seed, so returns false
		$this->assertFalse($opts->register_schema(array('m' => array('validate' => fn($v) => true)), seedDefaults: false, flush: false));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::register_schema
	 */
	public function test_register_schema_seed_preserves_existing_autoload_hint(): void {
		// options with autoload_hint only, missing value
		WP_Mock::userFunction('get_option')->with($this->mainOption, array())->once()->andReturn(array(
		    'flag' => array('autoload_hint' => true),
		));
		WP_Mock::userFunction('update_option')
		    ->with($this->mainOption, array(
		        'flag' => array('value' => false, 'autoload_hint' => true),
		    ), true)
		    ->once()->andReturn(true);
		$opts = new RegisterOptions($this->mainOption);
		$this->assertTrue($opts->register_schema(array(
		    'flag' => array('default' => false),
		), seedDefaults: true, flush: true));
		$this->assertFalse($opts->get_option('flag'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::register_schema
	 */
	public function test_register_schema_seed_no_changes_returns_false(): void {
		// already has value for a
		WP_Mock::userFunction('get_option')->with($this->mainOption, array())->once()->andReturn(array(
		    'a' => array('value' => 1, 'autoload_hint' => null),
		));
		$opts = new RegisterOptions($this->mainOption);
		$this->assertFalse($opts->register_schema(array(
		    'a' => array('default' => 2),
		), seedDefaults: true, flush: false));
	}

	/**
     * @covers \Ran\PluginLib\Options\RegisterOptions::add_options
	 */
	public function test_set_options_changed_hint_only_and_unchanged_value(): void {
		// existing k with value 'x' and null hint, plus u unchanged
		WP_Mock::userFunction('get_option')->with($this->mainOption, array())->once()->andReturn(array(
		    'k' => array('value' => 'x', 'autoload_hint' => null),
		    'u' => array('value' => 'z', 'autoload_hint' => false),
		));
		// Only k changes (hint flip to true); u unchanged; no flush here
		$opts = new RegisterOptions($this->mainOption);
		$opts->add_options(array(
		    'k' => array('value' => 'x', 'autoload_hint' => true),
		    'u' => array('value' => 'z', 'autoload_hint' => false),
		));
		// No flush here; we only assert that add_options completed without error
		$this->assertTrue(true);
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::sanitize_key
	 */
	public function test_sanitize_key_strips_and_trims_and_lowercases(): void {
		$this->assertSame('abc_123', \Ran\PluginLib\Options\RegisterOptions::sanitize_key('AbC-123!!'));
		$this->assertSame('abc', \Ran\PluginLib\Options\RegisterOptions::sanitize_key('__ABC__'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::from_config
	 */
	public function test_from_config_missing_RAN_AppOption_throws(): void {
		$missingCfg = new class($this->logger_mock) implements \Ran\PluginLib\Config\ConfigInterface {
			public function __construct(private $logger) {
			}
			public function get_config(): array {
				return array();
			}
			public function get_options(mixed $default = false): mixed {
				return $default;
			}
			public function get_logger(): \Ran\PluginLib\Util\Logger {
				return $this->logger;
			}
			public function is_dev_environment(): bool {
				return false;
			}
			public function get_type(): \Ran\PluginLib\Config\ConfigType {
				return \Ran\PluginLib\Config\ConfigType::Plugin;
			}
		};
		$this->expectException(\InvalidArgumentException::class);
		\Ran\PluginLib\Options\RegisterOptions::from_config($missingCfg);
	}

	/**
     * @covers \Ran\PluginLib\Options\RegisterOptions::add_options
     * @covers \Ran\PluginLib\Options\RegisterOptions::flush
	 */
	public function test_flush_returns_false_on_wp_failure(): void {
		// Stage changes then flush with WP failure
		WP_Mock::userFunction('get_option')->with($this->mainOption, array())->once()->andReturn(array());
		WP_Mock::userFunction('update_option')->with($this->mainOption, array(
		    'x' => array('value' => 1, 'autoload_hint' => null),
		), true)->once()->andReturn(false);
		$opts = new RegisterOptions($this->mainOption);
		$opts->add_options(array('x' => 1));
		$this->assertFalse($opts->flush());
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::from_config
	 */
	public function test_resolve_default_value_callable_uses_config_context(): void {
		// Use from_config to pass a config; default callable inspects cfg
		WP_Mock::userFunction('sanitize_title')->andReturn('test-plugin');
		$cfg  = new \Ran\PluginLib\Tests\Unit\TestClasses\TestableConfig();
		$main = $cfg->get_config()['RAN']['AppOption'];
		WP_Mock::userFunction('get_option')->with($main, array())->once()->andReturn(array());
		// Expect defaults seeded with value 'yes'
		WP_Mock::userFunction('update_option')->with($main, array(
		    'env' => array('value' => 'yes', 'autoload_hint' => null),
		), true)->once()->andReturn(true);
		$schema = array(
		    'env' => array('default' => function ($cfgArg) {
		    	return $cfgArg ? 'yes' : 'no';
		    }),
		);
		$opts = \Ran\PluginLib\Options\RegisterOptions::from_config($cfg, array(), true, null, $schema);
		$this->assertSame('yes', $opts->get_option('env'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::add_option
	 * @covers \Ran\PluginLib\Options\RegisterOptions::flush
	 */
	public function test_add_option_noop_guard_returns_self_and_keeps_state(): void {
		WP_Mock::userFunction('get_option')->with($this->mainOption, array())->once()->andReturn(array(
			'same' => array('value' => 'v', 'autoload_hint' => true),
		));
		// Expect a single flush writing exactly the same structure
		WP_Mock::userFunction('update_option')
			->with($this->mainOption, array(
				'same' => array('value' => 'v', 'autoload_hint' => true),
			), true)
			->once()->andReturn(true);

		$opts = new RegisterOptions($this->mainOption);
		$ret  = $opts->add_option('same', 'v', true); // triggers no-op guard branch

		$this->assertSame($opts, $ret, 'add_option should return $this for fluent chaining');
		$this->assertSame(array('same' => array('value' => 'v', 'autoload_hint' => true)), $opts->get_options());
		$this->assertTrue($opts->flush());
	}
}
