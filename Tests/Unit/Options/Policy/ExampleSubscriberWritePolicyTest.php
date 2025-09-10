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
}
