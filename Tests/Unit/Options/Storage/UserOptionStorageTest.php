<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options\Storage;

use WP_Mock;
use Ran\PluginLib\Options\OptionScope;
use Ran\PluginLib\Options\Storage\UserOptionStorage;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;

final class UserOptionStorageTest extends PluginLibTestCase {
	public function test_meta_methods(): void {
		$s = new UserOptionStorage(42, true);
		$this->assertSame(OptionScope::User, $s->scope());
		$this->assertNull($s->blogId());
		$this->assertFalse($s->supports_autoload());
		$this->assertNull($s->load_all_autoloaded());
	}

	public function test_read_add_update_delete_site_specific(): void {
		$user_id = 7;
		$global  = false;
		$s       = new UserOptionStorage($user_id, $global);

		// add() delegates to update()
		WP_Mock::userFunction('update_user_option')
		    ->once()
		    ->with($user_id, 'foo', 'bar', $global)
		    ->andReturn(true);
		$this->assertTrue($s->add('foo', 'bar'));

		// read
		WP_Mock::userFunction('get_user_option')
		    ->once()
		    ->with('foo', $user_id, '')
		    ->andReturn('bar');
		$this->assertSame('bar', $s->read('foo'));

		// update
		WP_Mock::userFunction('update_user_option')
		    ->once()
		    ->with($user_id, 'foo', 'baz', $global)
		    ->andReturn(true);
		$this->assertTrue($s->update('foo', 'baz'));

		// delete
		WP_Mock::userFunction('delete_user_option')
		    ->once()
		    ->with($user_id, 'foo', $global)
		    ->andReturn(true);
		$this->assertTrue($s->delete('foo'));
	}

	public function test_update_delete_global_user_option(): void {
		$user_id = 3;
		$global  = true;
		$s       = new UserOptionStorage($user_id, $global);

		WP_Mock::userFunction('update_user_option')
		    ->once()
		    ->with($user_id, 'alpha', 'beta', $global)
		    ->andReturn(true);
		$this->assertTrue($s->update('alpha', 'beta'));

		WP_Mock::userFunction('delete_user_option')
		    ->once()
		    ->with($user_id, 'alpha', $global)
		    ->andReturn(true);
		$this->assertTrue($s->delete('alpha'));
	}
}
