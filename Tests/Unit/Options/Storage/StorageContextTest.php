<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options\Storage;

use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Options\Storage\StorageContext;
use Ran\PluginLib\Options\OptionScope;

final class StorageContextTest extends PluginLibTestCase {
	public function test_for_user_happy_path_defaults_meta(): void {
		$ctx = StorageContext::forUserId(123);
		$this->assertSame(OptionScope::User, $ctx->scope);
		$this->assertNull($ctx->blog_id);
		$this->assertSame(123, $ctx->user_id);
		$this->assertSame('meta', $ctx->user_storage);
		$this->assertFalse($ctx->user_global);
	}

	public function test_for_user_happy_path_option_global_true(): void {
		$ctx = StorageContext::forUserId(5, 'OPTION', true);
		$this->assertSame(OptionScope::User, $ctx->scope);
		$this->assertSame(5, $ctx->user_id);
		$this->assertSame('option', $ctx->user_storage, 'user_storage should be normalized to lowercase');
		$this->assertTrue($ctx->user_global);
	}

	public function test_for_user_invalid_id_throws(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('StorageContext::forUserId requires a positive user_id.');
		StorageContext::forUserId(0);
	}

	public function test_for_user_invalid_storage_throws(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage("StorageContext::forUserId: user_storage must be 'meta' or 'option'.");
		StorageContext::forUserId(10, 'prefs');
	}

	// =========================================================================
	// forUser() - Deferred user_id resolution tests
	// =========================================================================

	public function test_for_user_deferred_creates_null_user_id(): void {
		$ctx = StorageContext::forUser();
		$this->assertSame(OptionScope::User, $ctx->scope);
		$this->assertNull($ctx->blog_id);
		$this->assertNull($ctx->user_id, 'Deferred context should have null user_id');
		$this->assertSame('meta', $ctx->user_storage);
		$this->assertFalse($ctx->user_global);
	}

	public function test_for_user_deferred_accepts_option_storage(): void {
		$ctx = StorageContext::forUser('OPTION');
		$this->assertNull($ctx->user_id);
		$this->assertSame('option', $ctx->user_storage, 'user_storage should be normalized to lowercase');
		$this->assertFalse($ctx->user_global);
	}

	public function test_for_user_deferred_accepts_global_flag(): void {
		$ctx = StorageContext::forUser('option', true);
		$this->assertNull($ctx->user_id);
		$this->assertSame('option', $ctx->user_storage);
		$this->assertTrue($ctx->user_global);
	}

	public function test_for_user_deferred_invalid_storage_throws(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage("StorageContext::forUser: user_storage must be 'meta' or 'option'.");
		StorageContext::forUser('prefs');
	}

	public function test_for_user_deferred_cache_key_excludes_user_id(): void {
		$ctx = StorageContext::forUser();
		$key = $ctx->get_cache_key();
		$this->assertSame('user', $key, 'Deferred context cache key should not include user_id');
	}

	public function test_for_user_id_cache_key_includes_user_id(): void {
		$ctx = StorageContext::forUserId(123);
		$key = $ctx->get_cache_key();
		$this->assertSame('user|user:123', $key, 'Explicit context cache key should include user_id');
	}
}
