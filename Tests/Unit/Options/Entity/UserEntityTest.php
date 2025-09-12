<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options\Entity;

use InvalidArgumentException;
use Ran\PluginLib\Options\Entity\UserEntity;
use Ran\PluginLib\Options\OptionScope;
use Ran\PluginLib\Options\Storage\StorageContext;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;

final class UserEntityTest extends PluginLibTestCase {
	public function test_getScope_returns_user(): void {
		$e = new UserEntity(42);
		$this->assertSame(OptionScope::User, $e->getScope());
	}

	public function test_toStorageContext_defaults_meta_and_false_global(): void {
		$e   = new UserEntity(42);
		$ctx = $e->toStorageContext();
		$this->assertSame(OptionScope::User, $ctx->scope);
		$this->assertSame(42, $ctx->user_id);
		$this->assertSame('meta', $ctx->user_storage);
		$this->assertFalse($ctx->user_global);
	}

	public function test_toStorageContext_with_option_and_global_true(): void {
		$e   = new UserEntity(7, true, 'option');
		$ctx = $e->toStorageContext();
		$this->assertSame(OptionScope::User, $ctx->scope);
		$this->assertSame(7, $ctx->user_id);
		$this->assertSame('option', $ctx->user_storage);
		$this->assertTrue($ctx->user_global);
	}

	public function test_constructor_rejects_non_positive_id(): void {
		$this->expectException(InvalidArgumentException::class);
		new UserEntity(0);
	}

	public function test_constructor_rejects_invalid_storage(): void {
		$this->expectException(InvalidArgumentException::class);
		new UserEntity(1, false, 'redis');
	}
}
