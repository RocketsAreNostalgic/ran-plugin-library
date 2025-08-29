<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options\Storage;

use WP_Mock;
use Ran\PluginLib\Options\OptionScope;
use Ran\PluginLib\Options\Storage\UserMetaStorage;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;

final class UserMetaStorageTest extends PluginLibTestCase {
	public function test_meta_methods(): void {
		$s = new UserMetaStorage(42);
		$this->assertSame(OptionScope::User, $s->scope());
		$this->assertNull($s->blogId());
		$this->assertFalse($s->supports_autoload());
		$this->assertNull($s->load_all_autoloaded());
	}

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
}
