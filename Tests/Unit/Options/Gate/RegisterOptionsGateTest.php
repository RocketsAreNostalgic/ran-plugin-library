<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options;

use WP_Mock;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Options\Policy\WritePolicyInterface;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;

/**
 * Tests for RegisterOptions write gate and policy mechanisms.
 */
final class RegisterOptionsGateTest extends PluginLibTestCase {
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
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::set_option
	 */
	public function test_set_option_write_gate_veto_before_mutation(): void {
		// Create a partial mock with constructor disabled
		$mockOpts = $this->getMockBuilder(RegisterOptions::class)
			->disableOriginalConstructor()
			->onlyMethods(array('_do_apply_filter'))
			->getMock();

		// Manually initialize required properties
		$this->_set_protected_property_value($mockOpts, 'main_wp_option_name', 'test_options');
		$this->_set_protected_property_value($mockOpts, 'options', array());
		$this->_set_protected_property_value($mockOpts, 'schema', array());

		// Mock the filter to veto before mutation (covers line 359)
		$mockOpts->method('_do_apply_filter')
			->willReturn(false); // Veto the write

		// Provide minimal schema to satisfy Phase 4
		$this->_set_protected_property_value($mockOpts, 'schema', array(
			'test_key' => array('validate' => function ($v) {
				return is_string($v);
			}),
		));
	
		// Attempt to set option - should be vetoed before mutation
		$result = $mockOpts->set_option('test_key', 'test_value');

		// Should return false due to veto
		$this->assertFalse($result);

		// Verify option was not set (protected in-memory state)
		$this->assertFalse($mockOpts->has_option('test_key'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::set_option
	 */
	public function test_set_option_write_gate_veto_after_mutation(): void {
		// Create a partial mock with constructor disabled
		$mockOpts = $this->getMockBuilder(RegisterOptions::class)
			->disableOriginalConstructor()
			->onlyMethods(array('_do_apply_filter'))
			->getMock();

		// Manually initialize required properties
		$this->_set_protected_property_value($mockOpts, 'main_wp_option_name', 'test_options');
		$this->_set_protected_property_value($mockOpts, 'options', array());
		$this->_set_protected_property_value($mockOpts, 'schema', array());

		// Mock the filter to allow first call, veto second call
		$mockOpts->method('_do_apply_filter')
			->willReturnOnConsecutiveCalls(true, false); // Allow first, veto second

		// Provide minimal schema to satisfy Phase 4
		$this->_set_protected_property_value($mockOpts, 'schema', array(
			'test_key' => array('validate' => function ($v) {
				return is_string($v);
			}),
		));
	
		// Attempt to set option - should be vetoed after mutation
		$result = $mockOpts->set_option('test_key', 'test_value');

		// Should return false due to veto after rollback
		$this->assertFalse($result);

		// Verify option was rolled back (lines 368-369)
		$this->assertFalse($mockOpts->has_option('test_key'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::set_option
	 */
	public function test_set_option_write_gate_veto_after_mutation_with_policy(): void {
		$opts = RegisterOptions::site('test_options');

		// Phase 4: schema required for set_option key
		$opts->with_schema(array('test_key' => array('validate' => function ($v) {
			return is_string($v);
		})));

		// Create a mock write policy that vetoes after mutation
		$mockPolicy = $this->getMockBuilder(WritePolicyInterface::class)
			->getMock();
		$mockPolicy->method('allow')
			->willReturnOnConsecutiveCalls(true, false); // Allow first check, veto second

		// Set the mock policy on the options instance
		$this->_set_protected_property_value($opts, 'write_policy', $mockPolicy);

		// Attempt to set option - should be vetoed after mutation by policy
		$result = $opts->set_option('test_key', 'test_value');

		// Should return false due to veto after rollback
		$this->assertFalse($result);

		// Verify option was rolled back (lines 368-369)
		$this->assertFalse($opts->has_option('test_key'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::set_option
	 */
	public function test_set_option_vetoed_by_persist_gate(): void {
		$opts = RegisterOptions::site('test_options');

		// Phase 4: schema required for set_option key
		$opts->with_schema(array('test_key' => array('validate' => function ($v) {
			return is_string($v);
		})));

		// Mock write guards to allow initial mutation but veto persistence (general + site)
		$gateCounter = 0;
		$gateFn      = function($allowed, $ctx) use (&$gateCounter) {
			$gateCounter++;
			return $gateCounter === 1 ? true : false; // allow first, veto thereafter
		};
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist')
			->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
			->reply($gateFn);
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist/scope/site')
			->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
			->reply($gateFn);

		// Mock storage to return failure
		WP_Mock::userFunction('update_option')->andReturn(false);

		// Set an option - should be vetoed during persistence and rolled back
		$result = $opts->set_option('test_key', 'test_value');

		// Verify the method returned false (persistence failed)
		$this->assertFalse($result);

		// Verify the option was NOT stored in memory (rolled back)
		$this->assertFalse($opts->get_option('test_key'));
	}
}
