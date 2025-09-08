<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options\Storage;

use WP_Mock;
use Ran\PluginLib\Options\OptionScope;
use Ran\PluginLib\Util\ExpectLogTrait;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Options\Storage\BlogOptionStorage;
use Ran\PluginLib\Options\Storage\SiteOptionStorage;
use Ran\PluginLib\Options\Storage\UserOptionStorage;
use Ran\PluginLib\Options\Storage\NetworkOptionStorage;
use Ran\PluginLib\Options\Storage\OptionStorageFactory;
use Ran\PluginLib\Options\Storage\OptionStorageInterface;

/**
 * @covers \Ran\PluginLib\Options\Storage\OptionStorageFactory::__construct
 * @covers \Ran\PluginLib\Options\Storage\OptionStorageFactory::get_logger
 * @covers \Ran\PluginLib\Options\Storage\OptionStorageFactory::normalize_scope
 * @covers \Ran\PluginLib\Options\Storage\OptionStorageFactory::require_int
 */
final class OptionStorageFactoryTest extends PluginLibTestCase {
	use ExpectLogTrait;
	/**
	 * @covers \Ran\PluginLib\Options\Storage\OptionStorageFactory::make
	 */
	public function test_make_with_enum_scope_types(): void {
		$f = new OptionStorageFactory();

		$this->assertInstanceOf(SiteOptionStorage::class, $f->make(OptionScope::Site));
		$this->assertInstanceOf(NetworkOptionStorage::class, $f->make(OptionScope::Network));

		// Blog with explicit id
		$this->assertInstanceOf(BlogOptionStorage::class, $f->make(OptionScope::Blog, array('blog_id' => 123)));

		// User with id/global defaults to UserMetaStorage now
		$this->assertInstanceOf(\Ran\PluginLib\Options\Storage\UserMetaStorage::class, $f->make(OptionScope::User, array('user_id' => 7, 'user_global' => true)));
	}

	/**
	 * @covers \Ran\PluginLib\Options\Storage\OptionStorageFactory::make
	 */
	public function test_make_with_string_scope_and_blog_current_when_null(): void {
		$f = new OptionStorageFactory();

		// When blog_id omitted, use current blog id
		WP_Mock::userFunction('get_current_blog_id')->once()->andReturn(55);
		$storage = $f->make('blog');
		$this->assertInstanceOf(BlogOptionStorage::class, $storage);
		$this->assertSame(55, $storage->blogId());
	}

	/**
	 * @covers \Ran\PluginLib\Options\Storage\OptionStorageFactory::make
	 */
	public function test_make_throws_when_user_missing_user_id(): void {
		$f = new OptionStorageFactory();
		$this->expectException(\InvalidArgumentException::class);
		$f->make('user');
	}

	/**
	 * @covers \Ran\PluginLib\Options\Storage\OptionStorageFactory::make
	 */
	public function test_interface_return_type(): void {
		$f = new OptionStorageFactory();
		$this->assertInstanceOf(OptionStorageInterface::class, $f->make('site'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\Storage\OptionStorageFactory::make
	 */
	public function test_make_logs_with_collecting_logger_covers_all_branches(): void {
		// Inject CollectingLogger via constructor DI
		$f = new OptionStorageFactory($this->logger_mock);

		// 1) Site scope as string to exercise normalized-scope debug logging.
		$this->assertInstanceOf(SiteOptionStorage::class, $f->make('site'));

		// 2) Blog scope with explicit blog_id to exercise blog debug logging.
		$this->assertInstanceOf(BlogOptionStorage::class, $f->make('blog', array('blog_id' => 42)));

		// 3) User scope with required int user_id to exercise user debug logging (defaults to meta).
		$this->assertInstanceOf(\Ran\PluginLib\Options\Storage\UserMetaStorage::class, $f->make('user', array('user_id' => 9)));

		// Assertions against collected logs (no terminal output expected).
		$this->expectLog('debug', 'OptionStorageFactory::make - normalized scope.', 3);
		$this->expectLog('debug', 'OptionStorageFactory::make - constructing BlogOptionStorage.', 1);
		$this->expectLog('debug', 'OptionStorageFactory::make - constructing UserMetaStorage.', 1);
	}

	/**
	 * @covers \Ran\PluginLib\Options\Storage\OptionStorageFactory::make
	 */
	public function test_make_user_throws_when_user_id_not_int(): void {
		$f = new OptionStorageFactory();
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage("Argument 'user_id' must be int");
		// user_id present but not an int should trigger require_int() failure path
		$f->make('user', array('user_id' => '9'));
	}
}
