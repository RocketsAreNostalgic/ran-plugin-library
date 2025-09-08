<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options\Storage;

use WP_Mock;
use Ran\PluginLib\Options\OptionScope;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Options\Storage\UserMetaStorage;

final class UserMetaStorageTest extends PluginLibTestCase {
	/**
	 * @covers \Ran\PluginLib\Options\Storage\UserMetaStorage::scope
	 * @covers \Ran\PluginLib\Options\Storage\UserMetaStorage::blogId
	 * @covers \Ran\PluginLib\Options\Storage\UserMetaStorage::supports_autoload
	 */
	public function test_meta_methods(): void {
		$s = new UserMetaStorage(42);
		$this->assertSame(OptionScope::User, $s->scope());
		$this->assertNull($s->blogId());
		$this->assertFalse($s->supports_autoload());
	}

	/**
	 * @covers \Ran\PluginLib\Options\Storage\UserMetaStorage::add
	 * @covers \Ran\PluginLib\Options\Storage\UserMetaStorage::read
	 * @covers \Ran\PluginLib\Options\Storage\UserMetaStorage::update
	 * @covers \Ran\PluginLib\Options\Storage\UserMetaStorage::delete
	 */
	public function test_read_add_update_delete_user_meta(): void {
		$user_id = 7;
		$s       = new UserMetaStorage($user_id);

		// add() delegates to update()
		WP_Mock::userFunction('update_user_meta')
		    ->once()
		    ->with($user_id, 'foo', 'bar', '')
		    ->andReturn(true);
		$this->assertTrue($s->add('foo', 'bar'));

		// read
		WP_Mock::userFunction('get_user_meta')
		    ->once()
		    ->with($user_id, 'foo', true)
		    ->andReturn('bar');
		$this->assertSame('bar', $s->read('foo'));

		// update
		WP_Mock::userFunction('update_user_meta')
		    ->once()
		    ->with($user_id, 'foo', 'baz', '')
		    ->andReturn(true);
		$this->assertTrue($s->update('foo', 'baz'));

		// delete
		WP_Mock::userFunction('delete_user_meta')
		    ->once()
		    ->with($user_id, 'foo')
		    ->andReturn(true);
		$this->assertTrue($s->delete('foo'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\Storage\UserMetaStorage::__construct
	 */
	public function test_constructor_for_coverage(): void {
		// Simple instantiation to execute constructor assignment
		$s = new UserMetaStorage(99);
		// Verify via public API while restricting coverage to __construct
		$this->assertSame(OptionScope::User, $s->scope());
		$this->assertNull($s->blogId());
	}
}
