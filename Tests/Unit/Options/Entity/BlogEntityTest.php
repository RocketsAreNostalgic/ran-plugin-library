<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options\Entity;

use Ran\PluginLib\Options\OptionScope;
use Ran\PluginLib\Options\Entity\BlogEntity;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Options\Storage\StorageContext;

final class BlogEntityTest extends PluginLibTestCase {
	public function test_getScope_returns_blog(): void {
		$e = new BlogEntity(123);
		$this->assertSame(OptionScope::Blog, $e->getScope());
	}

	public function test_toStorageContext_with_id(): void {
		$e   = new BlogEntity(5);
		$ctx = $e->toStorageContext();
		$this->assertSame(OptionScope::Blog, $ctx->scope);
		$this->assertSame(5, $ctx->blog_id);
	}

	public function test_toStorageContext_with_null_id_throws(): void {
		$e = new BlogEntity(null);
		$this->expectException(\InvalidArgumentException::class);
		// Typed conversion should fail for null/invalid id
		$e->toStorageContext();
	}
}
