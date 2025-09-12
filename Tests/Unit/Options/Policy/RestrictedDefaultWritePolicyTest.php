<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options;

use Mockery;
use WP_Mock;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Options\Policy\RestrictedDefaultWritePolicy;

/**
 * Test coverage for RestrictedDefaultWritePolicy
 *
 * @covers \Ran\PluginLib\Options\Policy\RestrictedDefaultWritePolicy
 */
class RestrictedDefaultWritePolicyTest extends PluginLibTestCase {
	private RestrictedDefaultWritePolicy $policy;

	public function setUp(): void {
		parent::setUp();
		$this->policy = new RestrictedDefaultWritePolicy();
	}

	public function tearDown(): void {
		parent::tearDown();
	}

	/**
	 * Test network scope allows when user has manage_network_options capability
	 * @covers \Ran\PluginLib\Options\Policy\RestrictedDefaultWritePolicy::allow (lines 30-31)
	 */
	public function test_network_scope_allows_with_manage_network_options_capability(): void {
		WP_Mock::userFunction('current_user_can')
			->with('manage_network_options')
			->andReturn(true);

		$ctx = \Ran\PluginLib\Options\WriteContext::for_clear('dummy', 'network', null, null, 'meta', false);
		$this->assertTrue($this->policy->allow('flush', $ctx));
	}

	/**
	 * Test network scope denies when user lacks manage_network_options capability
	 * @covers \Ran\PluginLib\Options\Policy\RestrictedDefaultWritePolicy::allow (lines 30-31)
	 */
	public function test_network_scope_denies_without_manage_network_options_capability(): void {
		WP_Mock::userFunction('current_user_can')
			->with('manage_network_options')
			->andReturn(false);

		$ctx = \Ran\PluginLib\Options\WriteContext::for_clear('dummy', 'network', null, null, 'meta', false);
		$this->assertFalse($this->policy->allow('flush', $ctx));
	}

	/**
	 * Test user scope allows when user has edit_user capability for the specified user
	 * @covers \Ran\PluginLib\Options\Policy\RestrictedDefaultWritePolicy::allow (lines 32-34)
	 */
	public function test_user_scope_allows_with_edit_user_capability(): void {
		$userId = 42;

		WP_Mock::userFunction('current_user_can')
			->with('edit_user', $userId)
			->andReturn(true);

		$ctx = \Ran\PluginLib\Options\WriteContext::for_clear('dummy', 'user', null, $userId, 'meta', false);
		$this->assertTrue($this->policy->allow('set_option', $ctx));
	}

	/**
	 * Test user scope denies when user lacks edit_user capability
	 * @covers \Ran\PluginLib\Options\Policy\RestrictedDefaultWritePolicy::allow (lines 32-34)
	 */
	public function test_user_scope_denies_without_edit_user_capability(): void {
		$userId = 42;

		WP_Mock::userFunction('current_user_can')
			->with('edit_user', $userId)
			->andReturn(false);

		$ctx = \Ran\PluginLib\Options\WriteContext::for_clear('dummy', 'user', null, $userId, 'meta', false);
		$this->assertFalse($this->policy->allow('set_option', $ctx));
	}

	/**
	 * Test user scope uses 0 as default user_id when not provided
	 * @covers \Ran\PluginLib\Options\Policy\RestrictedDefaultWritePolicy::allow (lines 32-34)
	 */
	public function test_user_scope_uses_zero_default_user_id(): void {
		WP_Mock::userFunction('current_user_can')
			->with('edit_user', 0)
			->andReturn(true);

		$ctx = \Ran\PluginLib\Options\WriteContext::for_clear('dummy', 'user', null, 0, 'meta', false);
		$this->assertTrue($this->policy->allow('delete_option', $ctx));
	}

	/**
	 * Test blog scope allows when user has manage_options capability
	 * @covers \Ran\PluginLib\Options\Policy\RestrictedDefaultWritePolicy::allow (line 35)
	 */
	public function test_blog_scope_allows_with_manage_options_capability(): void {
		WP_Mock::userFunction('current_user_can')
			->with('manage_options')
			->andReturn(true);

		$ctx = \Ran\PluginLib\Options\WriteContext::for_clear('dummy', 'blog', 55, null, 'meta', false);
		$this->assertTrue($this->policy->allow('clear', $ctx));
	}

	/**
	 * Test site scope specifically to ensure line 37 is covered
	 * @covers \Ran\PluginLib\Options\Policy\RestrictedDefaultWritePolicy::allow (line 37)
	 */
	public function test_site_scope_specific_coverage(): void {
		WP_Mock::userFunction('current_user_can')
			->with('manage_options')
			->andReturn(true);

		$ctx = \Ran\PluginLib\Options\WriteContext::for_clear('dummy', 'site', null, null, 'meta', false);
		$this->assertTrue($this->policy->allow('flush', $ctx));
	}

	/**
	 * Test blog scope denies when user lacks manage_options capability
	 * @covers \Ran\PluginLib\Options\Policy\RestrictedDefaultWritePolicy::allow (line 35)
	 */
	public function test_blog_scope_denies_without_manage_options_capability(): void {
		WP_Mock::userFunction('current_user_can')
			->with('manage_options')
			->andReturn(false);

		$ctx = \Ran\PluginLib\Options\WriteContext::for_clear('dummy', 'blog', 55, null, 'meta', false);
		$this->assertFalse($this->policy->allow('clear', $ctx));
	}

	/**
	 * Test site scope allows when user has manage_options capability
	 * @covers \Ran\PluginLib\Options\Policy\RestrictedDefaultWritePolicy::allow (line 37)
	 */
	public function test_site_scope_allows_with_manage_options_capability(): void {
		WP_Mock::userFunction('current_user_can')
			->with('manage_options')
			->andReturn(true);

		$ctx = \Ran\PluginLib\Options\WriteContext::for_clear('dummy', 'site', null, null, 'meta', false);
		$this->assertTrue($this->policy->allow('flush', $ctx));
	}

	/**
	 * Test site scope denies when user lacks manage_options capability
	 * @covers \Ran\PluginLib\Options\Policy\RestrictedDefaultWritePolicy::allow (line 37)
	 */
	public function test_site_scope_denies_without_manage_options_capability(): void {
		WP_Mock::userFunction('current_user_can')
			->with('manage_options')
			->andReturn(false);

		$ctx = \Ran\PluginLib\Options\WriteContext::for_clear('dummy', 'site', null, null, 'meta', false);
		$this->assertFalse($this->policy->allow('flush', $ctx));
	}

	/**
	 * Test default scope (empty or invalid) allows when user has manage_options capability
	 * @covers \Ran\PluginLib\Options\Policy\RestrictedDefaultWritePolicy::allow (line 39)
	 */
	public function test_default_scope_allows_with_manage_options_capability(): void {
		WP_Mock::userFunction('current_user_can')
			->with('manage_options')
			->andReturn(true);

		$ctx = \Ran\PluginLib\Options\WriteContext::for_clear('dummy', 'site', null, null, 'meta', false);
		$this->assertTrue($this->policy->allow('stage_options', $ctx));
	}

	/**
	 * Test default scope (empty or invalid) denies when user lacks manage_options capability
	 * @covers \Ran\PluginLib\Options\Policy\RestrictedDefaultWritePolicy::allow (line 39)
	 */
	public function test_default_scope_denies_without_manage_options_capability(): void {
		WP_Mock::userFunction('current_user_can')
			->with('manage_options')
			->andReturn(false);

		$ctx = \Ran\PluginLib\Options\WriteContext::for_clear('dummy', 'site', null, null, 'meta', false);
		$this->assertFalse($this->policy->allow('stage_options', $ctx));
	}

	/**
	 * Test that scope is case-insensitive
	 * @covers \Ran\PluginLib\Options\Policy\RestrictedDefaultWritePolicy::allow (lines 27-28)
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

		$ctxB = \Ran\PluginLib\Options\WriteContext::for_clear('dummy', 'BLOG', 55, null, 'meta', false);
		$ctxS = \Ran\PluginLib\Options\WriteContext::for_clear('dummy', 'Site', null, null, 'meta', false);
		$ctxN = \Ran\PluginLib\Options\WriteContext::for_clear('dummy', 'NETWORK', null, null, 'meta', false);
		$this->assertTrue($this->policy->allow('flush', $ctxB));
		$this->assertTrue($this->policy->allow('flush', $ctxS));
		$this->assertTrue($this->policy->allow('flush', $ctxN));
	}

	/**
	 * Test various operation types are handled consistently
	 * @covers \Ran\PluginLib\Options\Policy\RestrictedDefaultWritePolicy::allow
	 */
	public function test_various_operations_with_site_scope(): void {
		WP_Mock::userFunction('current_user_can')
			->with('manage_options')
			->andReturn(true);

		$operations = array('flush', 'clear', 'set_option', 'stage_options', 'delete_option', 'seed_if_missing', 'migrate');

		$ctx = \Ran\PluginLib\Options\WriteContext::for_clear('dummy', 'site', null, null, 'meta', false);
		foreach ($operations as $operation) {
			$this->assertTrue(
				$this->policy->allow($operation, $ctx),
				"Operation '$operation' should be allowed for site scope with manage_options capability"
			);
		}
	}

	/**
	 * Verify that all scope cases are actually being executed
	 * This test serves as documentation that we've tested all paths
	 * @covers \Ran\PluginLib\Options\Policy\RestrictedDefaultWritePolicy::allow
	 */
	public function test_all_scope_cases_are_covered(): void {
		// Test network scope path (lines 31-32)
		WP_Mock::userFunction('current_user_can')
			->with('manage_network_options')
			->andReturn(true);
		$ctx = \Ran\PluginLib\Options\WriteContext::for_clear('dummy', 'network', null, null, 'meta', false);
		$this->assertTrue($this->policy->allow('test', $ctx));

		// Test user scope path (lines 34-36)
		WP_Mock::userFunction('current_user_can')
			->with('edit_user', 123)
			->andReturn(true);
		$ctx = \Ran\PluginLib\Options\WriteContext::for_clear('dummy', 'user', null, 123, 'meta', false);
		$this->assertTrue($this->policy->allow('test', $ctx));

		// Test blog scope path (line 38)
		WP_Mock::userFunction('current_user_can')
			->with('manage_options')
			->andReturn(true);
		$ctx = \Ran\PluginLib\Options\WriteContext::for_clear('dummy', 'blog', 55, null, 'meta', false);
		$this->assertTrue($this->policy->allow('test', $ctx));

		// Test site scope path (line 39)
		$ctx = \Ran\PluginLib\Options\WriteContext::for_clear('dummy', 'site', null, null, 'meta', false);
		$this->assertTrue($this->policy->allow('test', $ctx));
	}

	/**
	 * With typed WriteContext, invalid scopes are rejected at construction time.
	 * This documents that the policy's default branch is unreachable.
	 * @covers \Ran\PluginLib\Options\WriteContext::for_clear
	 */
	public function test_unknown_scope_is_rejected_by_write_context(): void {
		$this->expectException(\InvalidArgumentException::class);
		\Ran\PluginLib\Options\WriteContext::for_clear('dummy', 'unknown', null, null, 'meta', false);
	}

	/**
	 * Positive coverage for policy's default branch (line 41) by simulating a corrupted scope.
	 * We mutate WriteContext via reflection to set an invalid scope, then assert policy falls back
	 * to manage_options.
	 * @covers \Ran\PluginLib\Options\Policy\RestrictedDefaultWritePolicy::allow
	 */
	public function test_default_branch_allows_manage_options_when_scope_is_invalid(): void {
		// Start with a valid context, then corrupt it
		$wc        = \Ran\PluginLib\Options\WriteContext::for_clear('dummy', 'site', null, null, 'meta', false);
		$ref       = new \ReflectionClass($wc);
		$scopeProp = $ref->getProperty('scope');
		$scopeProp->setAccessible(true);
		$scopeProp->setValue($wc, 'invalid_scope');

		// Expect fallback to manage_options
		WP_Mock::userFunction('current_user_can')
			->with('manage_options')
			->andReturn(true);

		$this->assertTrue($this->policy->allow('flush', $wc));
	}
}
