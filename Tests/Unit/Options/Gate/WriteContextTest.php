<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options;

use PHPUnit\Framework\TestCase;
use Ran\PluginLib\Options\WriteContext;

final class WriteContextTest extends TestCase {
	public function test_for_set_option_sets_fields_and_op(): void {
		$wc = WriteContext::for_set_option('main', 'site', null, null, 'meta', false, 'k');
		$this->assertSame('set_option', $wc->op());
		$this->assertSame('main', $wc->main_option());
		$this->assertSame('site', $wc->scope());
		$this->assertSame('k', $wc->key());
		$this->assertNull($wc->keys());
		$this->assertNull($wc->options());
		$this->assertFalse($wc->merge_from_db());
	}

	public function test_post_scope_requires_post_id(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('WriteContext: post scope requires postId');
		WriteContext::for_clear('main', 'post', null, null, 'meta', false);
	}

	public function test_post_scope_sets_post_id_and_normalizes_other_fields(): void {
		$wc = WriteContext::for_clear('main', 'post', null, null, 'meta', false, 123);
		$this->assertSame('clear', $wc->op());
		$this->assertSame('post', $wc->scope());
		$this->assertSame(123, $wc->post_id());
		$this->assertNull($wc->blog_id());
		$this->assertNull($wc->user_id());
		$this->assertNull($wc->user_storage());
	}

	public function test_ssert_non_empty_throws_on_empty_main_option(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('WriteContext: field main_option must be non-empty');
		// Any factory that validates main_option will do; use for_clear
		WriteContext::for_clear('', 'site', null, null, 'meta', false);
	}

	public function test_ssert_non_emptyArray_throws_on_empty_keys(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('WriteContext: field keys must be a non-empty array');
		WriteContext::for_seed_if_missing('main', 'site', null, null, 'meta', false, array());
	}

	public function test_normalize_scope_throws_on_invalid_scope(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('WriteContext: invalid scope unknown');
		WriteContext::for_clear('main', 'unknown', null, null, 'meta', false);
	}

	public function test_user_scope_requires_user_id(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('WriteContext: user scope requires userId');
		// user scope without userId should fail
		WriteContext::for_clear('main', 'user', null, null, 'meta', false);
	}

	public function test_user_scope_validates_user_storage(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('WriteContext: user_storage must be meta|option');
		// invalid user_storage value for user scope
		WriteContext::for_clear('main', 'user', null, 5, 'prefs', false);
	}

	public function test_blog_scope_requires_blog_id(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('WriteContext: blog scope requires blogId');
		// blog scope without blogId should fail
		WriteContext::for_clear('main', 'blog', null, null, 'meta', false);
	}

	public function test_for_add_option_sets_key_and_op(): void {
		$wc = WriteContext::for_add_option('main', 'user', null, 7, 'meta', false, 'pref');
		$this->assertSame('add_option', $wc->op());
		$this->assertSame('user', $wc->scope());
		$this->assertSame(7, $wc->user_id());
		$this->assertSame('meta', $wc->user_storage());
		$this->assertSame('pref', $wc->key());
	}

	public function test_for_delete_option_sets_key_and_op(): void {
		$wc = WriteContext::for_delete_option('main', 'blog', 99, null, 'meta', false, 'old');
		$this->assertSame('delete_option', $wc->op());
		$this->assertSame('blog', $wc->scope());
		$this->assertSame(99, $wc->blog_id());
		$this->assertSame('old', $wc->key());
	}

	public function test_for_clear_normalizes_scope_triplet(): void {
		$wc = WriteContext::for_clear('main', 'NETWORK', null, null, 'meta', false);
		$this->assertSame('clear', $wc->op());
		$this->assertSame('network', $wc->scope());
		$this->assertNull($wc->blog_id());
		$this->assertNull($wc->user_id());
		$this->assertNull($wc->user_storage());
	}

	public function test_for_seed_if_missing_sets_keys_snapshot(): void {
		$keys = array('a', 'b');
		$wc   = WriteContext::for_seed_if_missing('main', 'site', null, null, 'meta', false, $keys);
		$this->assertSame('seed_if_missing', $wc->op());
		$this->assertSame(array('a', 'b'), $wc->keys());
		$this->assertNull($wc->changed_keys());
	}

	public function test_for_migrate_sets_changed_keys_and_op(): void {
		$changed = array('x', 'y');
		$wc      = WriteContext::for_migrate('main', 'site', null, null, 'meta', false, $changed);
		$this->assertSame('migrate', $wc->op());
		$this->assertSame(array('x', 'y'), $wc->changed_keys());
		$this->assertNull($wc->keys());
	}

	public function test_getters_return_expected_values(): void {
		$wc = WriteContext::for_save_all('main', 'site', null, null, 'meta', true, array('p' => 1), true);
		$this->assertSame('save_all', $wc->op());
		$this->assertSame('main', $wc->main_option());
		$this->assertSame('site', $wc->scope());
		$this->assertNull($wc->blog_id());
		$this->assertNull($wc->user_id());
		// For site scope, user_storage is normalized to null
		$this->assertNull($wc->user_storage());
		$this->assertTrue($wc->user_global());
		$this->assertTrue($wc->merge_from_db());
		$this->assertSame(array('p' => 1), $wc->options());
		$this->assertNull($wc->key());
	}
}
