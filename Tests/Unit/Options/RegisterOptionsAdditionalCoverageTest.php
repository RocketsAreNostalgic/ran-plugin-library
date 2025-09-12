<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options;

use WP_Mock;
use Ran\PluginLib\Options\OptionScope;
use Ran\PluginLib\Util\ExpectLogTrait;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Options\Storage\StorageContext;

/**
 * Additional targeted coverage for RegisterOptions.
 *
 * Focus areas:
 * - Scope/adapter correctness (site/network/blog/user)
 * - Schema normalization and default seeding
 * - Callable validator success path
 * - Refresh behavior with empty array from storage
 */
final class RegisterOptionsAdditionalCoverageTest extends PluginLibTestCase {
	// Expose protected constructor for targeted constructor-path tests
	private static function makeExposed(
        string $name,
        array $initial = array(),
        bool $autoload = true,
        ?\Ran\PluginLib\Util\Logger $logger = null,
        ?\Ran\PluginLib\Config\ConfigInterface $config = null,
        array $schema = array(),
        ?\Ran\PluginLib\Options\Policy\WritePolicyInterface $policy = null
    ): \Ran\PluginLib\Options\RegisterOptions {
		return new class($name, $initial, $autoload, $logger, $config, $schema, $policy) extends \Ran\PluginLib\Options\RegisterOptions {
			public function __construct(
                string $main_wp_option_name,
                array $initial_options = array(),
                bool $main_option_autoload = true,
                ?\Ran\PluginLib\Util\Logger $logger = null,
                ?\Ran\PluginLib\Config\ConfigInterface $config = null,
                array $schema = array(),
                ?\Ran\PluginLib\Options\Policy\WritePolicyInterface $policy = null
            ) {
				parent::__construct($main_wp_option_name, $initial_options, $main_option_autoload, $logger, $config, $schema, $policy);
			}
		};
	}
	use ExpectLogTrait;

	public function setUp(): void {
		parent::setUp();

		// Common WP wrappers used by SUT
		WP_Mock::userFunction('get_option')->andReturn(array())->byDefault();
		WP_Mock::userFunction('get_site_option')->andReturn(array())->byDefault();
		WP_Mock::userFunction('get_blog_option')->andReturn(array())->byDefault();
		WP_Mock::userFunction('get_user_option')->andReturn(array())->byDefault();
		WP_Mock::userFunction('get_user_meta')->andReturn(array())->byDefault();
		WP_Mock::userFunction('wp_load_alloptions')->andReturn(array())->byDefault();

		// Key normalization
		WP_Mock::userFunction('sanitize_key')->andReturnUsing(function ($key) {
			$key = strtolower((string) $key);
			$key = preg_replace('/[^a-z0-9_\-]+/i', '_', $key) ?? '';
			return trim($key, '_');
		});

		// Writes default to success unless overridden per test
		WP_Mock::userFunction('add_option')->andReturn(true);
		WP_Mock::userFunction('update_option')->andReturn(true);
		WP_Mock::userFunction('delete_option')->andReturn(true);

		// Default allow for write gate (tests override when needed)
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist')
			->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
			->reply(true);
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist/scope/site')
			->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
			->reply(true);
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist/scope/network')
			->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
			->reply(true);
		// Ensure apply_filters exists and defaults to pass-through so that
		// policy + onFilter hooks control allow/deny regardless of file order.
		WP_Mock::userFunction('apply_filters')->andReturnUsing(function ($hook, $value) {
			return $value;
		});
	}

	private function allow_all_persist_filters_for_site(): void {
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist')
		    ->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
		    ->reply(true);
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist/scope/site')
		    ->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
		    ->reply(true);
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::__construct
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_read_main_option
	 */
	public function test_constructor_logs_initialized_message(): void {
		// Ensure get_option returns empty array (already default in setUp)
		$config = $this->getMockBuilder(\Ran\PluginLib\Config\ConfigInterface::class)->getMock();
		$config->method('get_options_key')->willReturn('ctor_log');
		// Provide logger via Config so constructor-time logs are captured
		if (method_exists($config, 'get_logger')) {
			$config->method('get_logger')->willReturn($this->logger_mock);
		}
		$sut = RegisterOptions::from_config($config, StorageContext::forSite(), true);
		// After construction, the constructor logs an initialization message (exact string)
		$this->expectLog('debug', "RegisterOptions: Initialized with main option 'ctor_log'. Loaded 0 existing sub-options.", 1);
		// Sanity: no options initially
		$this->assertFalse($sut->has_option('any'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::__construct
	 * @covers \Ran\PluginLib\Options\RegisterOptions::network
	 * @covers \Ran\PluginLib\Options\RegisterOptions::set_option
	 * @covers \Ran\PluginLib\Options\RegisterOptions::supports_autoload
	 */
	public function test_network_scope_set_option_and_autoload_false(): void {
		$opts = RegisterOptions::network('net_opts');
		// Allow all writes for this test
		$policy = $this->getMockBuilder(\Ran\PluginLib\Options\Policy\WritePolicyInterface::class)->getMock();
		$policy->method('allow')->willReturn(true);
		$opts->with_policy($policy);
		$this->assertFalse($opts->supports_autoload());

		// Allow writes
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist')
		    ->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
		    ->reply(true);
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist/scope/network')
		    ->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
		    ->reply(true);
		WP_Mock::userFunction('update_site_option')->andReturn(true);

		// Even though update_site_option is mocked, SUT uses storage adapters; ensure set_option returns true
		$this->assertTrue($opts->set_option('k', 'v'));
		$this->assertSame('v', $opts->get_option('k'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::__construct
	 * @covers \Ran\PluginLib\Options\RegisterOptions::blog
	 * @covers \Ran\PluginLib\Options\RegisterOptions::refresh_options
	 * @covers \Ran\PluginLib\Options\RegisterOptions::supports_autoload
	 */
	public function test_blog_scope_reads_and_autoload_semantics(): void {
		// Use non-current blog ID to ensure effective autoload = false
		$opts = RegisterOptions::blog('blog_opts', 9999, true);
		$this->assertFalse($opts->supports_autoload());

		// Storage snapshot returns some values
		$mockStorage = $this->createMock(\Ran\PluginLib\Options\Storage\OptionStorageInterface::class);
		$mockStorage->method('read')->willReturn(array('a' => 1));
		$mockStorage->method('scope')->willReturn(OptionScope::Blog);
		$this->_set_protected_property_value($opts, 'storage', $mockStorage);

		$opts->refresh_options();
		$this->assertSame(1, $opts->get_option('a'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::__construct
	 * @covers \Ran\PluginLib\Options\RegisterOptions::user
	 * @covers \Ran\PluginLib\Options\RegisterOptions::supports_autoload
	 */
	public function test_user_scope_supports_autoload_false(): void {
		$opts = RegisterOptions::user('user_opts', 1234, false);
		$this->assertFalse($opts->supports_autoload());
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::__construct
	 * @covers \Ran\PluginLib\Options\RegisterOptions::register_schema
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_normalize_schema_keys
	 */
	public function test_register_schema_normalizes_keys_and_seeds(): void {
		$opts = RegisterOptions::site('norm_opts');

		$schema = array(
		    'MiXeD-Case Key' => array(
		        'default' => 'x',
		    ),
		    'weird key!!' => array(
		        'default' => 42,
		    ),
		);

		$changed = $opts->register_schema($schema, seed_defaults: true, flush: false);
		$this->assertTrue($changed);

		// Normalized: 'mixed-case_key' and 'weird_key'
		$this->assertSame('x', $opts->get_option('mixed-case_key'));
		$this->assertSame(42, $opts->get_option('weird_key'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::__construct
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_normalize_defaults
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_sanitize_and_validate_option
	 */
	public function test_constructor_merges_initial_options_in_memory(): void {
		$initial = array(
			'MiXeD-Case Key' => 'val1',
			'arr-key'        => array('value' => 'val2'),
		);
		$config = $this->getMockBuilder(\Ran\PluginLib\Config\ConfigInterface::class)->getMock();
		$config->method('get_options_key')->willReturn('ctor_initial');
		$config->method('get_logger')->willReturn($this->logger_mock);
		$sut = RegisterOptions::from_config($config, StorageContext::forSite(), true)->with_defaults($initial);
		// Keys are normalized and values set in-memory only
		$this->assertSame('val1', $sut->get_option('mixed-case_key'));
		// Without schema, complex array remains as provided
		$this->assertSame(array('value' => 'val2'), $sut->get_option('arr-key'));
		// Defaults were applied via fluent method; constructor-specific initialization logs are not expected here.
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::__construct
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_normalize_defaults
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_sanitize_and_validate_option
	 */
	public function test_constructor_seeds_schema_defaults(): void {
		$schema = array(
		    'MiXeD Key' => array(
		        'default' => function () {
		        	return 'abc';
		        },
		        'sanitize' => function ($v) {
		        	return strtoupper((string) $v);
		        },
		        'validate' => function ($v) {
		        	return is_string($v);
		        },
		    ),
		);
		$config = $this->getMockBuilder(\Ran\PluginLib\Config\ConfigInterface::class)->getMock();
		$config->method('get_options_key')->willReturn('ctor_schema');
		$config->method('get_logger')->willReturn($this->logger_mock);
		$sut = RegisterOptions::from_config($config, StorageContext::forSite(), true)->with_schema($schema, true, false);
		// Normalized key should be present with sanitized/validated default value
		$this->assertSame('ABC', $sut->get_option('mixed_key'));
		$this->expectLog('debug', '_resolve_default_value');
		$this->expectLog('debug', '_sanitize_and_validate_option completed');
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::__construct
	 */
	public function test_string_callable_validate_success_does_not_throw(): void {
		$opts = RegisterOptions::site('string_valid');
		// Allow all writes for this test
		$policy = $this->getMockBuilder(\Ran\PluginLib\Options\Policy\WritePolicyInterface::class)->getMock();
		$policy->method('allow')->willReturn(true);
		$opts->with_policy($policy);
		WP_Mock::userFunction('apply_filters')->andReturn(true);
		$opts->register_schema(array(
		    'num' => array(
		        'validate' => 'is_numeric',
		    ),
		), seed_defaults: false, flush: false);

		// Should not throw
		$this->assertTrue($opts->set_option('num', '123'));
		$this->assertSame('123', $opts->get_option('num'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::__construct
	 * @covers \Ran\PluginLib\Options\RegisterOptions::refresh_options
	 */
	public function test_refresh_options_with_empty_array_resets_cache(): void {
		$opts = RegisterOptions::site('empty_refresh');
		// Seed in-memory different from storage
		$this->_set_protected_property_value($opts, 'options', array('foo' => 'bar'));

		// Storage returns empty array
		$mockStorage = $this->createMock(\Ran\PluginLib\Options\Storage\OptionStorageInterface::class);
		$mockStorage->method('read')->willReturn(array());
		$mockStorage->method('scope')->willReturn(OptionScope::Site);
		$this->_set_protected_property_value($opts, 'storage', $mockStorage);

		$opts->refresh_options();
		$this->assertFalse($opts->has_option('foo'));
		$this->assertFalse($opts->get_option('foo'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::__construct
	 * @covers \Ran\PluginLib\Options\RegisterOptions::network
	 * @covers \Ran\PluginLib\Options\RegisterOptions::flush
	 */
	public function test_network_scope_flush_merge_from_db(): void {
		$opts = RegisterOptions::network('net_merge');
		// Allow all writes for this test
		$policy = $this->getMockBuilder(\Ran\PluginLib\Options\Policy\WritePolicyInterface::class)->getMock();
		$policy->method('allow')->willReturn(true);
		$opts->with_policy($policy);

		// Allow persistence filters
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist')
		    ->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
		    ->reply(true);
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist/scope/network')
		    ->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
		    ->reply(true);

		// Stage memory options
		$opts->stage_options(array('mk' => 'mv'));

		// Storage returns DB snapshot
		$mockStorage = $this->createMock(\Ran\PluginLib\Options\Storage\OptionStorageInterface::class);
		$mockStorage->method('read')->willReturn(array('dbk' => 'dbv'));
		$mockStorage->method('scope')->willReturn(OptionScope::Network);
		$mockStorage->method('update')->willReturn(true);
		$this->_set_protected_property_value($opts, 'storage', $mockStorage);

		// Simulate network option update path
		WP_Mock::userFunction('update_site_option')->andReturn(true);

		$this->assertTrue($opts->flush(true));
		$this->assertSame('mv', $opts->get_option('mk'));
		$this->assertSame('dbv', $opts->get_option('dbk'));
	}
}
