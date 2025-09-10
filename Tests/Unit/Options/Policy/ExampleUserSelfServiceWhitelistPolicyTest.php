<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options\Policy;

use Mockery;
use WP_Mock;
use Ran\PluginLib\Options\WriteContext;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Options\Policy\ExampleUserSelfServiceWhitelistPolicy;

/**
 * @covers \Ran\PluginLib\Options\Policy\ExampleUserSelfServiceWhitelistPolicy
 */
final class ExampleUserSelfServiceWhitelistPolicyTest extends PluginLibTestCase {
	private ExampleUserSelfServiceWhitelistPolicy $policy;

	public function setUp(): void {
		parent::setUp();
		$this->policy = new ExampleUserSelfServiceWhitelistPolicy();
	}

	public function tearDown(): void {
		parent::tearDown();
	}

	public function test_allows_set_option_for_same_user_and_whitelisted_key(): void {
		$user_id = 101;
		WP_Mock::userFunction('get_current_user_id')->andReturn($user_id);

		$wc = WriteContext::for_set_option(
			'dummy_main_option',
			'user',
			null,
			$user_id,
			'meta',
			false,
			'preferences'
		);

		self::assertTrue($this->policy->allow('set_option', $wc));
	}

	public function test_denies_set_option_for_different_user(): void {
		WP_Mock::userFunction('get_current_user_id')->andReturn(5);
		$wc = WriteContext::for_set_option('dummy', 'user', null, 7, 'meta', false, 'preferences');
		self::assertFalse($this->policy->allow('set_option', $wc));
	}

	public function test_denies_set_option_for_non_whitelisted_key(): void {
		$user_id = 42;
		WP_Mock::userFunction('get_current_user_id')->andReturn($user_id);
		$wc = WriteContext::for_set_option('dummy', 'user', null, $user_id, 'meta', false, 'admin_only');
		self::assertFalse($this->policy->allow('set_option', $wc));
	}

	public function test_allows_add_options_when_all_keys_whitelisted(): void {
		$user_id = 9;
		WP_Mock::userFunction('get_current_user_id')->andReturn($user_id);
		$wc = WriteContext::for_add_options('dummy', 'user', null, $user_id, 'meta', false, array('profile_bio', 'newsletter_opt_in'));
		self::assertTrue($this->policy->allow('add_options', $wc));
	}

	public function test_denies_add_options_when_any_key_not_whitelisted(): void {
		$user_id = 9;
		WP_Mock::userFunction('get_current_user_id')->andReturn($user_id);
		$wc = WriteContext::for_add_options('dummy', 'user', null, $user_id, 'meta', false, array('preferences', 'unknown_key'));
		self::assertFalse($this->policy->allow('add_options', $wc));
	}

	public function test_allows_save_all_when_only_whitelisted_keys_present(): void {
		$user_id = 12;
		WP_Mock::userFunction('get_current_user_id')->andReturn($user_id);
		$wc = WriteContext::for_save_all(
			'dummy',
			'user',
			null,
			$user_id,
			'meta',
			false,
			array('preferences' => array('theme' => 'dark'), 'profile_bio' => 'hello'),
			false
		);
		self::assertTrue($this->policy->allow('save_all', $wc));
	}

	public function test_denies_save_all_when_non_whitelisted_key_present(): void {
		$user_id = 12;
		WP_Mock::userFunction('get_current_user_id')->andReturn($user_id);
		$wc = WriteContext::for_save_all(
			'dummy',
			'user',
			null,
			$user_id,
			'meta',
			false,
			array('preferences' => array('theme' => 'dark'), 'admin_only' => true),
			false
		);
		self::assertFalse($this->policy->allow('save_all', $wc));
	}

	public function test_denies_non_user_scope(): void {
		// Even if current user id matches, scope must be 'user'.
		WP_Mock::userFunction('get_current_user_id')->andReturn(1);
		$wc = WriteContext::for_set_option('dummy', 'site', null, null, 'meta', false, 'preferences');
		self::assertFalse($this->policy->allow('set_option', $wc));
	}

	public function test_allows_add_option_for_same_user_and_whitelisted_key(): void {
		$user_id = 55;
		WP_Mock::userFunction('get_current_user_id')->andReturn($user_id);
		$wc = WriteContext::for_add_option('dummy_main_option', 'user', null, $user_id, 'meta', false, 'preferences');
		self::assertTrue($this->policy->allow('add_option', $wc));
	}

	public function test_allows_delete_option_for_same_user_and_whitelisted_key(): void {
		$user_id = 56;
		WP_Mock::userFunction('get_current_user_id')->andReturn($user_id);
		$wc = WriteContext::for_delete_option('dummy_main_option', 'user', null, $user_id, 'meta', false, 'profile_bio');
		self::assertTrue($this->policy->allow('delete_option', $wc));
	}

	public function test_denies_management_ops_clear_seed_migrate(): void {
		$user_id = 77;
		WP_Mock::userFunction('get_current_user_id')->andReturn($user_id);

		$wcClear = WriteContext::for_clear('dummy_main_option', 'user', null, $user_id, 'meta', false);
		self::assertFalse($this->policy->allow('clear', $wcClear));

		$wcSeed = WriteContext::for_seed_if_missing('dummy_main_option', 'user', null, $user_id, 'meta', false, array('preferences'));
		self::assertFalse($this->policy->allow('seed_if_missing', $wcSeed));

		$wcMigrate = WriteContext::for_migrate('dummy_main_option', 'user', null, $user_id, 'meta', false, array('profile_bio'));
		self::assertFalse($this->policy->allow('migrate', $wcMigrate));
	}

	public function test_denies_unknown_operation(): void {
		$user_id = 10;
		WP_Mock::userFunction('get_current_user_id')->andReturn($user_id);
		$wc = WriteContext::for_set_option('dummy_main_option', 'user', null, $user_id, 'meta', false, 'preferences');
		self::assertFalse($this->policy->allow('unknown_op', $wc));
	}

	public function test_edge_case_set_option_empty_key_denied(): void {
		$user_id = 42;
		WP_Mock::userFunction('get_current_user_id')->andReturn($user_id);
		// WriteContext disallows empty key; use a non-whitelisted key to emulate the policy denial path.
		$wc = WriteContext::for_set_option('dummy_main_option', 'user', null, $user_id, 'meta', false, 'not_whitelisted');
		self::assertFalse($this->policy->allow('set_option', $wc));
	}

	public function test_edge_case_add_options_empty_array_denied(): void {
		$user_id = 43;
		WP_Mock::userFunction('get_current_user_id')->andReturn($user_id);
		// WriteContext forbids empty keys list; assert that factory enforces this.
		$this->expectException(\InvalidArgumentException::class);
		WriteContext::for_add_options('dummy_main_option', 'user', null, $user_id, 'meta', false, array());
	}

	public function test_save_all_with_empty_options_allowed_by_helper_semantics(): void {
		$user_id = 44;
		WP_Mock::userFunction('get_current_user_id')->andReturn($user_id);
		$wc = WriteContext::for_save_all('dummy_main_option', 'user', null, $user_id, 'meta', false, array(), false);
		self::assertTrue($this->policy->allow('save_all', $wc));
	}
}
