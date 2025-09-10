<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options\Entity;

use Ran\PluginLib\Options\Entity\BlogEntity;
use Ran\PluginLib\Options\OptionScope;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;

final class BlogEntityTest extends PluginLibTestCase {
	public function test_getScope_returns_blog(): void {
		$e = new BlogEntity(123);
		$this->assertSame(OptionScope::Blog, $e->getScope());
	}

	public function test_toStorageArgs_with_id(): void {
		$e = new BlogEntity(5);
		$this->assertSame(array('blog_id' => 5), $e->toStorageArgs());
	}

	public function test_toStorageArgs_with_null_id_uses_null(): void {
		$e = new BlogEntity(null);
		$this->assertSame(array('blog_id' => null), $e->toStorageArgs());
	}
}
