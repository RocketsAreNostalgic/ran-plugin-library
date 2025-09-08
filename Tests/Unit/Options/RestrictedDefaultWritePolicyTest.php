<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options;

use Mockery;
use WP_Mock;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Options\RestrictedDefaultWritePolicy;

/**
 * Test coverage for RestrictedDefaultWritePolicy
 *
 * @covers \Ran\PluginLib\Options\RestrictedDefaultWritePolicy
 */
class RestrictedDefaultWritePolicyTest extends PluginLibTestCase {
	private RestrictedDefaultWritePolicy $policy;

	public function setUp(): void {
		parent::setUp();
		$this->policy = new RestrictedDefaultWritePolicy();
	}

	public function tearDown(): void {
		Mockery::close();
		parent::tearDown();
	}

	/**
	 * Test network scope allows when user has manage_network_options capability
	 * @covers \Ran\PluginLib\Options\RestrictedDefaultWritePolicy::allow (lines 30-31)
	 */
	public function test_network_scope_allows_with_manage_network_options_capability(): void {
		WP_Mock::userFunction('current_user_can')
			->with('manage_network_options')
			->andReturn(true);

		$this->assertTrue($this->policy->allow('flush', array('scope' => 'network')));
	}

	/**
	 * Test network scope denies when user lacks manage_network_options capability
	 * @covers \Ran\PluginLib\Options\RestrictedDefaultWritePolicy::allow (lines 30-31)
	 */
	public function test_network_scope_denies_without_manage_network_options_capability(): void {
		WP_Mock::userFunction('current_user_can')
			->with('manage_network_options')
			->andReturn(false);

		$this->assertFalse($this->policy->allow('flush', array('scope' => 'network')));
	}

	/**
	 * Test user scope allows when user has edit_user capability for the specified user
	 * @covers \Ran\PluginLib\Options\RestrictedDefaultWritePolicy::allow (lines 32-34)
	 */
	public function test_user_scope_allows_with_edit_user_capability(): void {
		$userId = 42;

		WP_Mock::userFunction('current_user_can')
			->with('edit_user', $userId)
			->andReturn(true);

		$this->assertTrue($this->policy->allow('set_option', array('scope' => 'user', 'user_id' => $userId)));
	}

	/**
	 * Test user scope denies when user lacks edit_user capability
	 * @covers \Ran\PluginLib\Options\RestrictedDefaultWritePolicy::allow (lines 32-34)
	 */
	public function test_user_scope_denies_without_edit_user_capability(): void {
		$userId = 42;

		WP_Mock::userFunction('current_user_can')
			->with('edit_user', $userId)
			->andReturn(false);

		$this->assertFalse($this->policy->allow('set_option', array('scope' => 'user', 'user_id' => $userId)));
	}

	/**
	 * Test user scope uses 0 as default user_id when not provided
	 * @covers \Ran\PluginLib\Options\RestrictedDefaultWritePolicy::allow (lines 32-34)
	 */
	public function test_user_scope_uses_zero_default_user_id(): void {
		WP_Mock::userFunction('current_user_can')
			->with('edit_user', 0)
			->andReturn(true);

		$this->assertTrue($this->policy->allow('delete_option', array('scope' => 'user')));
	}

	/**
	 * Test blog scope allows when user has manage_options capability
	 * @covers \Ran\PluginLib\Options\RestrictedDefaultWritePolicy::allow (line 35)
	 */
	public function test_blog_scope_allows_with_manage_options_capability(): void {
		WP_Mock::userFunction('current_user_can')
			->with('manage_options')
			->andReturn(true);

		$this->assertTrue($this->policy->allow('clear', array('scope' => 'blog')));
	}

	/**
	 * Test site scope specifically to ensure line 37 is covered
	 * @covers \Ran\PluginLib\Options\RestrictedDefaultWritePolicy::allow (line 37)
	 */
	public function test_site_scope_specific_coverage(): void {
		WP_Mock::userFunction('current_user_can')
			->with('manage_options')
			->andReturn(true);

		$this->assertTrue($this->policy->allow('flush', array('scope' => 'site')));
	}

	/**
	 * Test blog scope denies when user lacks manage_options capability
	 * @covers \Ran\PluginLib\Options\RestrictedDefaultWritePolicy::allow (line 35)
	 */
	public function test_blog_scope_denies_without_manage_options_capability(): void {
		WP_Mock::userFunction('current_user_can')
			->with('manage_options')
			->andReturn(false);

		$this->assertFalse($this->policy->allow('clear', array('scope' => 'blog')));
	}

	/**
	 * Test site scope allows when user has manage_options capability
	 * @covers \Ran\PluginLib\Options\RestrictedDefaultWritePolicy::allow (line 37)
	 */
	public function test_site_scope_allows_with_manage_options_capability(): void {
		WP_Mock::userFunction('current_user_can')
			->with('manage_options')
			->andReturn(true);

		$this->assertTrue($this->policy->allow('flush', array('scope' => 'site')));
	}

	/**
	 * Test site scope denies when user lacks manage_options capability
	 * @covers \Ran\PluginLib\Options\RestrictedDefaultWritePolicy::allow (line 37)
	 */
	public function test_site_scope_denies_without_manage_options_capability(): void {
		WP_Mock::userFunction('current_user_can')
			->with('manage_options')
			->andReturn(false);

		$this->assertFalse($this->policy->allow('flush', array('scope' => 'site')));
	}

	/**
	 * Test default scope (empty or invalid) allows when user has manage_options capability
	 * @covers \Ran\PluginLib\Options\RestrictedDefaultWritePolicy::allow (line 39)
	 */
	public function test_default_scope_allows_with_manage_options_capability(): void {
		WP_Mock::userFunction('current_user_can')
			->with('manage_options')
			->andReturn(true);

		$this->assertTrue($this->policy->allow('add_options', array('scope' => ''))); // Empty scope
		$this->assertTrue($this->policy->allow('add_options', array('scope' => 'invalid'))); // Invalid scope
		$this->assertTrue($this->policy->allow('add_options', array())); // No scope provided
	}

	/**
	 * Test default scope (empty or invalid) denies when user lacks manage_options capability
	 * @covers \Ran\PluginLib\Options\RestrictedDefaultWritePolicy::allow (line 39)
	 */
	public function test_default_scope_denies_without_manage_options_capability(): void {
		WP_Mock::userFunction('current_user_can')
			->with('manage_options')
			->andReturn(false);

		$this->assertFalse($this->policy->allow('add_options', array('scope' => ''))); // Empty scope
		$this->assertFalse($this->policy->allow('add_options', array('scope' => 'invalid'))); // Invalid scope
		$this->assertFalse($this->policy->allow('add_options', array())); // No scope provided
	}

	/**
	 * Test that scope is case-insensitive
	 * @covers \Ran\PluginLib\Options\RestrictedDefaultWritePolicy::allow (lines 27-28)
	 */
	public function test_scope_is_case_insensitive(): void {
		// Set up mocks for all capability checks
		WP_Mock::userFunction('current_user_can')
			->andReturnUsing(function ($capability) {
				return match ($capability) {
					'manage_options', 'manage_network_options' => true,
					default => false,
				};
			});

		$this->assertTrue($this->policy->allow('flush', array('scope' => 'BLOG')));
		$this->assertTrue($this->policy->allow('flush', array('scope' => 'Site')));
		$this->assertTrue($this->policy->allow('flush', array('scope' => 'NETWORK')));
	}

	/**
	 * Test various operation types are handled consistently
	 * @covers \Ran\PluginLib\Options\RestrictedDefaultWritePolicy::allow
	 */
	public function test_various_operations_with_site_scope(): void {
		WP_Mock::userFunction('current_user_can')
			->with('manage_options')
			->andReturn(true);

		$operations = array('flush', 'clear', 'set_option', 'add_options', 'delete_option', 'seed_if_missing', 'migrate');

		foreach ($operations as $operation) {
			$this->assertTrue(
				$this->policy->allow($operation, array('scope' => 'site')),
				"Operation '$operation' should be allowed for site scope with manage_options capability"
			);
		}
	}

	/**
	 * Verify that all scope cases are actually being executed
	 * This test serves as documentation that we've tested all paths
	 * @covers \Ran\PluginLib\Options\RestrictedDefaultWritePolicy::allow
	 */
	public function test_all_scope_cases_are_covered(): void {
		// Test network scope path (lines 31-32)
		WP_Mock::userFunction('current_user_can')
			->with('manage_network_options')
			->andReturn(true);
		$this->assertTrue($this->policy->allow('test', array('scope' => 'network')));

		// Test user scope path (lines 34-36)
		WP_Mock::userFunction('current_user_can')
			->with('edit_user', 123)
			->andReturn(true);
		$this->assertTrue($this->policy->allow('test', array('scope' => 'user', 'user_id' => 123)));

		// Test blog scope path (line 38)
		WP_Mock::userFunction('current_user_can')
			->with('manage_options')
			->andReturn(true);
		$this->assertTrue($this->policy->allow('test', array('scope' => 'blog')));

		// Test site scope path (line 39)
		$this->assertTrue($this->policy->allow('test', array('scope' => 'site')));

		// Test default scope path (line 40)
		$this->assertTrue($this->policy->allow('test', array('scope' => 'unknown')));
	}
}
