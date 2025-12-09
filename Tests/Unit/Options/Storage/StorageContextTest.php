<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options\Storage;

use Ran\PluginLib\Options\OptionScope;
use Ran\PluginLib\Options\Storage\StorageContext;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;

final class StorageContextTest extends PluginLibTestCase {
	public function test_for_user_happy_path_defaults_meta(): void {
		$ctx = StorageContext::forUser(123);
		$this->assertSame(OptionScope::User, $ctx->scope);
		$this->assertNull($ctx->blog_id);
		$this->assertSame(123, $ctx->user_id);
		$this->assertSame('meta', $ctx->user_storage);
		$this->assertFalse($ctx->user_global);
	}

	public function test_for_user_happy_path_option_global_true(): void {
		$ctx = StorageContext::forUser(5, 'OPTION', true);
		$this->assertSame(OptionScope::User, $ctx->scope);
		$this->assertSame(5, $ctx->user_id);
		$this->assertSame('option', $ctx->user_storage, 'user_storage should be normalized to lowercase');
		$this->assertTrue($ctx->user_global);
	}

	public function test_for_user_invalid_id_throws(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('StorageContext::forUser requires a positive user_id.');
		StorageContext::forUser(0);
	}

	public function test_for_user_invalid_storage_throws(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage("StorageContext::forUser: user_storage must be 'meta' or 'option'.");
		StorageContext::forUser(10, 'prefs');
	}
}
