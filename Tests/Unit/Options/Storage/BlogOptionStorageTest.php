<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options\Storage;

use WP_Mock;
use Ran\PluginLib\Options\OptionScope;
use Ran\PluginLib\Options\Storage\BlogOptionStorage;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;

final class BlogOptionStorageTest extends PluginLibTestCase {
	public function test_meta_methods(): void {
		// Make current blog different so supports_autoload() is false deterministically
		WP_Mock::userFunction('get_current_blog_id')->once()->andReturn(999);
		$s = new BlogOptionStorage(123);
		$this->assertSame(OptionScope::Blog, $s->scope());
		$this->assertSame(123, $s->blogId());
		$this->assertFalse($s->supports_autoload());
	}

	public function test_read_and_add_update_delete_flow(): void {
		$blog_id = 5;
		$s       = new BlogOptionStorage($blog_id);

		// add_blog_option(5, 'foo', 'bar')
		WP_Mock::userFunction('add_blog_option')
		    ->once()
		    ->with($blog_id, 'foo', 'bar')
		    ->andReturn(true);
		$this->assertTrue($s->add('foo', 'bar', true)); // autoload ignored

		// get_blog_option(5, 'foo', false)
		WP_Mock::userFunction('get_blog_option')
		    ->once()
		    ->with($blog_id, 'foo', false)
		    ->andReturn('bar');
		$this->assertSame('bar', $s->read('foo'));

		// update_blog_option(5, 'foo', 'baz')
		WP_Mock::userFunction('update_blog_option')
		    ->once()
		    ->with($blog_id, 'foo', 'baz')
		    ->andReturn(true);
		$this->assertTrue($s->update('foo', 'baz', false)); // autoload ignored

		// delete_blog_option(5, 'foo')
		WP_Mock::userFunction('delete_blog_option')
		    ->once()
		    ->with($blog_id, 'foo')
		    ->andReturn(true);
		$this->assertTrue($s->delete('foo'));
	}

	public function test_load_all_autoloaded_is_null(): void {
		// Other blog than current -> null
		WP_Mock::userFunction('get_current_blog_id')->once()->andReturn(8);
		$s = new BlogOptionStorage(7);
		$this->assertNull($s->load_all_autoloaded());
	}

	public function test_supports_autoload_true_for_current_blog(): void {
		$blog_id = 12;
		WP_Mock::userFunction('get_current_blog_id')->once()->andReturn($blog_id);
		$s = new BlogOptionStorage($blog_id);
		$this->assertTrue($s->supports_autoload());
	}

	public function test_load_all_autoloaded_returns_array_for_current_blog(): void {
		$blog_id = 15;
		WP_Mock::userFunction('get_current_blog_id')->once()->andReturn($blog_id);
		WP_Mock::userFunction('wp_load_alloptions')->once()->with(false)->andReturn(array('a' => '1'));
		$s = new BlogOptionStorage($blog_id);
		$this->assertSame(array('a' => '1'), $s->load_all_autoloaded());
	}
}
