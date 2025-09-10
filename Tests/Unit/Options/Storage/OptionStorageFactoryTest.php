<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options\Storage;

use Ran\PluginLib\Options\OptionScope;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Options\Storage\UserMetaStorage;
use Ran\PluginLib\Options\Storage\BlogOptionStorage;
use Ran\PluginLib\Options\Storage\SiteOptionStorage;
use Ran\PluginLib\Options\Storage\UserOptionStorage;
use Ran\PluginLib\Options\Storage\NetworkOptionStorage;

/**
 * @covers \Ran\PluginLib\Options\RegisterOptions::_make_storage
 */
final class RegisterOptionsMakeStorageTest extends PluginLibTestCase {
	public function test_site_scope_default(): void {
		$ro  = self::makeRO(null, array());
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
		$ro  = self::makeRO('network', array());
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
		$ro  = self::makeRO(OptionScope::Network, array());
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
		$ro  = self::makeRO('blog', array('blog_id' => 123));
		$ref = new \ReflectionClass($ro);
		$m   = $ref->getMethod('_make_storage');
		$m->setAccessible(true);
		$storage = $m->invoke($ro);
		$this->assertInstanceOf(BlogOptionStorage::class, $storage);
		$this->assertSame(123, $storage->blogId());
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_make_storage
	 */
	public function test_blog_scope_via_string(): void {
		$ro  = self::makeRO('blog', array('blog_id' => 123));
		$ref = new \ReflectionClass($ro);
		$m   = $ref->getMethod('_make_storage');
		$m->setAccessible(true);
		$storage = $m->invoke($ro);
		$this->assertInstanceOf(BlogOptionStorage::class, $storage);
		$this->assertSame(123, $storage->blogId());
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_make_storage
	 */
	public function test_user_meta_default(): void {
		$ro  = self::makeRO('user', array('user_id' => 7));
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
		$ro  = self::makeRO('user', array('user_id' => 7, 'user_storage' => 'option', 'user_global' => true));
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
		$ro  = self::makeRO('user', array('user_id' => 7, 'user_storage' => 'OpTiOn', 'user_global' => true));
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
		$ro = self::makeRO('blog', array());
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
		$ro = self::makeRO('user', array());
		$this->expectException(\InvalidArgumentException::class);
		$ref = new \ReflectionClass($ro);
		$m   = $ref->getMethod('_make_storage');
		$m->setAccessible(true);
		$m->invoke($ro);
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_make_storage
	 */
	private static function makeRO(string|OptionScope|null $scope, array $args): RegisterOptions {
		// Use named factory then override scope/args reflectively
		$ro  = RegisterOptions::site('ro_test', true);
		$ref = new \ReflectionClass($ro);
		$ps  = $ref->getProperty('storage_scope');
		$pa  = $ref->getProperty('storage_args');
		$ps->setAccessible(true);
		$pa->setAccessible(true);
		if ($scope !== null) {
			$ps->setValue($ro, $scope);
		}
		$pa->setValue($ro, $args);
		return $ro;
	}
}
