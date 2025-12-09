<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options\Policy;

use WP_Mock;
use Ran\PluginLib\Options\WriteContext;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Options\Policy\AbstractWritePolicy;
use Ran\PluginLib\Options\Policy\WritePolicyInterface;

/**
 * @covers \Ran\PluginLib\Options\Policy\AbstractWritePolicy
 */
final class AbstractWritePolicyTest extends PluginLibTestCase {
	/**
	 * Minimal concrete harness exposing protected helpers.
	 * Guarded to avoid re-declaration under coverage.
	 */
	private function makeHarness(): AbstractWritePolicy {
		if (!class_exists(__NAMESPACE__ . '\\__HarnessPolicy')) {
			eval(<<<'PHP'
namespace Ran\PluginLib\Tests\Unit\Options\Policy;

use Ran\PluginLib\Options\WriteContext;
use Ran\PluginLib\Options\Policy\AbstractWritePolicy;
use Ran\PluginLib\Options\Policy\WritePolicyInterface;

final class __HarnessPolicy extends AbstractWritePolicy implements WritePolicyInterface
{
    // Expose helpers
    public function _canManageNetwork(): bool { return $this->canManageNetwork(); }
    public function _canManageOptions(): bool { return $this->canManageOptions(); }
    public function _canEditUser(int $id): bool { return $this->canEditUser($id); }
    public function _isSameUser(WriteContext $wc): bool { return $this->isSameUser($wc); }
    public function _scopeIs(WriteContext $wc, string $s): bool { return $this->scopeIs($wc, $s); }
    public function _scopeIn(WriteContext $wc, array $ss): bool { return $this->scopeIn($wc, $ss); }
    public function _keysWhitelisted(string $op, WriteContext $wc, array $wl): bool { return $this->keysWhitelisted($op, $wc, $wl); }

    // Required by interface but not used for helper testing
    public function allow(string $op, WriteContext $wc): bool { return false; }
}
PHP);
		}
		/** @var AbstractWritePolicy $inst */
		$inst = new __HarnessPolicy();
		return $inst;
	}

	public function test_can_manage_network_true(): void {
		$h = $this->makeHarness();
		WP_Mock::userFunction('current_user_can')->with('manage_network_options')->andReturn(true);
		self::assertTrue($h->_canManageNetwork());
	}

	public function test_can_manage_network_false(): void {
		$h = $this->makeHarness();
		WP_Mock::userFunction('current_user_can')->with('manage_network_options')->andReturn(false);
		self::assertFalse($h->_canManageNetwork());
	}

	public function test_can_manage_options_true(): void {
		$h = $this->makeHarness();
		WP_Mock::userFunction('current_user_can')->with('manage_options')->andReturn(true);
		self::assertTrue($h->_canManageOptions());
	}

	public function test_can_manage_options_false(): void {
		$h = $this->makeHarness();
		WP_Mock::userFunction('current_user_can')->with('manage_options')->andReturn(false);
		self::assertFalse($h->_canManageOptions());
	}

	public function test_can_edit_user_true(): void {
		$h = $this->makeHarness();
		WP_Mock::userFunction('current_user_can')->with('edit_user', 7)->andReturn(true);
		self::assertTrue($h->_canEditUser(7));
	}

	public function test_can_edit_user_false(): void {
		$h = $this->makeHarness();
		WP_Mock::userFunction('current_user_can')->with('edit_user', 7)->andReturn(false);
		self::assertFalse($h->_canEditUser(7));
	}

	public function test_is_same_user_true_when_ids_match_and_nonzero(): void {
		$h = $this->makeHarness();
		WP_Mock::userFunction('get_current_user_id')->andReturn(101);
		$wc = WriteContext::for_set_option('main', 'user', null, 101, 'meta', false, 'k');
		self::assertTrue($h->_isSameUser($wc));
	}

	public function test_is_same_user_false_when_different_user(): void {
		$h = $this->makeHarness();
		WP_Mock::userFunction('get_current_user_id')->andReturn(101);
		$wc = WriteContext::for_set_option('main', 'user', null, 102, 'meta', false, 'k');
		self::assertFalse($h->_isSameUser($wc));
	}

	public function test_is_same_user_false_when_current_zero(): void {
		$h = $this->makeHarness();
		WP_Mock::userFunction('get_current_user_id')->andReturn(0);
		$wc = WriteContext::for_set_option('main', 'user', null, 101, 'meta', false, 'k');
		self::assertFalse($h->_isSameUser($wc));
	}

	public function test_is_same_user_false_when_target_null(): void {
		$h = $this->makeHarness();
		WP_Mock::userFunction('get_current_user_id')->andReturn(101);
		// Use non-user scope to avoid WriteContext validation requiring userId
		$wc = WriteContext::for_set_option('main', 'site', null, null, 'meta', false, 'k');
		self::assertFalse($h->_isSameUser($wc));
	}

	public function test_scope_helpers_are_case_insensitive(): void {
		$h  = $this->makeHarness();
		$wc = WriteContext::for_set_option('main', 'User', null, 1, 'meta', false, 'k');
		self::assertTrue($h->_scopeIs($wc, 'user'));
		self::assertTrue($h->_scopeIs($wc, 'USER'));
		self::assertFalse($h->_scopeIs($wc, 'site'));

		self::assertTrue($h->_scopeIn($wc, array('site', 'USER')));
		self::assertTrue($h->_scopeIn($wc, array('user')));
		self::assertFalse($h->_scopeIn($wc, array('site', 'network')));
	}

	public function test_keys_whitelisted_for_single_key_ops(): void {
		$h  = $this->makeHarness();
		$wc = WriteContext::for_set_option('main', 'user', null, 1, 'meta', false, 'good_key');
		self::assertTrue($h->_keysWhitelisted('set_option', $wc, array('good_key', 'other')));

		$wc2 = WriteContext::for_set_option('main', 'user', null, 1, 'meta', false, 'bad');
		self::assertFalse($h->_keysWhitelisted('set_option', $wc2, array('good_key')));
	}

	public function test_keys_whitelisted_for_stage_options(): void {
		$h  = $this->makeHarness();
		$wc = WriteContext::for_stage_options('main', 'user', null, 1, 'meta', false, array('a', 'b'));
		self::assertTrue($h->_keysWhitelisted('stage_options', $wc, array('a', 'b', 'c')));

		$wc2 = WriteContext::for_stage_options('main', 'user', null, 1, 'meta', false, array('a', 'x'));
		self::assertFalse($h->_keysWhitelisted('stage_options', $wc2, array('a', 'b', 'c')));
	}

	public function test_keys_whitelisted_for_save_all(): void {
		$h  = $this->makeHarness();
		$wc = WriteContext::for_save_all('main', 'user', null, 1, 'meta', false, array('a' => 1, 'b' => 2), false);
		self::assertTrue($h->_keysWhitelisted('save_all', $wc, array('a', 'b', 'c')));

		$wc2 = WriteContext::for_save_all('main', 'user', null, 1, 'meta', false, array('a' => 1, 'x' => 2), false);
		self::assertFalse($h->_keysWhitelisted('save_all', $wc2, array('a', 'b', 'c')));

		// Empty options yields true per current implementation
		$wc3 = WriteContext::for_save_all('main', 'user', null, 1, 'meta', false, array(), false);
		self::assertTrue($h->_keysWhitelisted('save_all', $wc3, array('a')));
	}

	public function test_keys_whitelisted_stage_options_empty_keys_path(): void {
		$h = $this->makeHarness();
		// Create a valid context first (factory rejects empty arrays)
		$wc = WriteContext::for_stage_options('main', 'user', null, 1, 'meta', false, array('placeholder'));
		// Force internal keys to empty via reflection to hit the guard branch
		$ref  = new \ReflectionClass($wc);
		$prop = $ref->getProperty('keys');
		$prop->setAccessible(true);
		$prop->setValue($wc, array());

		self::assertFalse($h->_keysWhitelisted('stage_options', $wc, array('a')));
	}

	public function test_keys_whitelisted_default_branch_for_unknown_op(): void {
		$h  = $this->makeHarness();
		$wc = WriteContext::for_set_option('main', 'user', null, 1, 'meta', false, 'a');
		self::assertFalse($h->_keysWhitelisted('unknown_op', $wc, array('a')));
	}
}
