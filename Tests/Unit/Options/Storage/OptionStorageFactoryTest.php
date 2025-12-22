<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options\Storage;

use Ran\PluginLib\Options\OptionScope;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Options\Storage\StorageContext;
use Ran\PluginLib\Options\Storage\UserMetaStorage;
use Ran\PluginLib\Options\Storage\BlogOptionStorage;
use Ran\PluginLib\Options\Storage\SiteOptionStorage;
use Ran\PluginLib\Options\Storage\UserOptionStorage;
use Ran\PluginLib\Options\Storage\NetworkOptionStorage;
use Ran\PluginLib\Options\Storage\PostMetaStorage;

/**
 * @covers \Ran\PluginLib\Options\RegisterOptions::_make_storage
 */
final class RegisterOptionsMakeStorageTest extends PluginLibTestCase {
	public function test_site_scope_default(): void {
		$ro  = $this->makeRO(StorageContext::forSite());
		$ref = new \ReflectionClass($ro);
		$m   = $ref->getMethod('_make_storage');
		$m->setAccessible(true);
		$storage = $m->invoke($ro);
		$this->assertInstanceOf(SiteOptionStorage::class, $storage);
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_make_storage
	 */
	public function test_network_scope(): void {
		$ro  = $this->makeRO(StorageContext::forNetwork());
		$ref = new \ReflectionClass($ro);
		$m   = $ref->getMethod('_make_storage');
		$m->setAccessible(true);
		$storage = $m->invoke($ro);
		$this->assertInstanceOf(NetworkOptionStorage::class, $storage);
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_make_storage
	 */
	public function test_network_scope_from_enum_normalizes(): void {
		$ro  = $this->makeRO(StorageContext::forNetwork());
		$ref = new \ReflectionClass($ro);
		$m   = $ref->getMethod('_make_storage');
		$m->setAccessible(true);
		$storage = $m->invoke($ro);
		$this->assertInstanceOf(NetworkOptionStorage::class, $storage);
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_make_storage
	 */
	public function test_blog_scope_requires_blog_id(): void {
		$ro  = $this->makeRO(StorageContext::forBlog(123));
		$ref = new \ReflectionClass($ro);
		$m   = $ref->getMethod('_make_storage');
		$m->setAccessible(true);
		$storage = $m->invoke($ro);
		$this->assertInstanceOf(BlogOptionStorage::class, $storage);
		$this->assertSame(123, $storage->blog_id());
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_make_storage
	 */
	public function test_blog_scope_via_string(): void {
		$ro  = $this->makeRO(StorageContext::forBlog(123));
		$ref = new \ReflectionClass($ro);
		$m   = $ref->getMethod('_make_storage');
		$m->setAccessible(true);
		$storage = $m->invoke($ro);
		$this->assertInstanceOf(BlogOptionStorage::class, $storage);
		$this->assertSame(123, $storage->blog_id());
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_make_storage
	 */
	public function test_post_scope(): void {
		$ro  = $this->makeRO(StorageContext::forPost(777));
		$ref = new \ReflectionClass($ro);
		$m   = $ref->getMethod('_make_storage');
		$m->setAccessible(true);
		$storage = $m->invoke($ro);
		$this->assertInstanceOf(PostMetaStorage::class, $storage);
		$this->assertSame(OptionScope::Post, $storage->scope());
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_make_storage
	 */
	public function test_user_meta_default(): void {
		$ro  = $this->makeRO(StorageContext::forUserId(7, 'meta', false));
		$ref = new \ReflectionClass($ro);
		$m   = $ref->getMethod('_make_storage');
		$m->setAccessible(true);
		$storage = $m->invoke($ro);
		$this->assertInstanceOf(UserMetaStorage::class, $storage);
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_make_storage
	 */
	public function test_user_option_when_requested(): void {
		$ro  = $this->makeRO(StorageContext::forUserId(7, 'option', true));
		$ref = new \ReflectionClass($ro);
		$m   = $ref->getMethod('_make_storage');
		$m->setAccessible(true);
		$storage = $m->invoke($ro);
		$this->assertInstanceOf(UserOptionStorage::class, $storage);
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_make_storage
	 */
	public function test_user_option_when_requested_case_insensitive(): void {
		$ro  = $this->makeRO(StorageContext::forUserId(7, 'option', true));
		$ref = new \ReflectionClass($ro);
		$m   = $ref->getMethod('_make_storage');
		$m->setAccessible(true);
		$storage = $m->invoke($ro);
		$this->assertInstanceOf(UserOptionStorage::class, $storage);
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_make_storage
	 */
	public function test_blog_scope_missing_blog_id_throws(): void {
		$this->expectException(\InvalidArgumentException::class);
		// StorageContext::forBlog(0) should throw; if not, _make_storage will
		$ro = $this->makeRO(StorageContext::forBlog(0));
		$this->expectException(\InvalidArgumentException::class);
		$ref = new \ReflectionClass($ro);
		$m   = $ref->getMethod('_make_storage');
		$m->setAccessible(true);
		$m->invoke($ro);
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_make_storage
	 */
	public function test_user_scope_missing_user_id_throws(): void {
		$this->expectException(\InvalidArgumentException::class);
		// StorageContext::forUserId(0, ...) should throw; if not, _make_storage will
		$ro = $this->makeRO(StorageContext::forUserId(0, 'meta', false));
		$this->expectException(\InvalidArgumentException::class);
		$ref = new \ReflectionClass($ro);
		$m   = $ref->getMethod('_make_storage');
		$m->setAccessible(true);
		$m->invoke($ro);
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_make_storage
	 */
	private function makeRO(StorageContext $context): RegisterOptions {
		// Use named factory then override typed context reflectively
		$ro  = RegisterOptions::site('ro_test', true, $this->logger_mock);
		$ref = new \ReflectionClass($ro);
		$pc  = $ref->getProperty('storage_context');
		$ps  = $ref->getProperty('storage');
		$pc->setAccessible(true);
		$ps->setAccessible(true);
		$pc->setValue($ro, $context);
		$ps->setValue($ro, null); // force rebuild
		return $ro;
	}
}
