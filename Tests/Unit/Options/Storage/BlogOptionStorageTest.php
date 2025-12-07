<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options\Storage;

use WP_Mock;
use Ran\PluginLib\Options\OptionScope;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Options\Storage\BlogOptionStorage;

final class BlogOptionStorageTest extends PluginLibTestCase {
	/**
	 * @covers \Ran\PluginLib\Options\Storage\BlogOptionStorage::scope
	 * @covers \Ran\PluginLib\Options\Storage\BlogOptionStorage::blogId
	 * @covers \Ran\PluginLib\Options\Storage\BlogOptionStorage::supports_autoload
	 */
	public function test_meta_methods(): void {
		// Make current blog different so supports_autoload() is false deterministically
		WP_Mock::userFunction('get_current_blog_id')->once()->andReturn(999);
		$s = new BlogOptionStorage(123);
		$this->assertSame(OptionScope::Blog, $s->scope());
		$this->assertSame(123, $s->blog_id());
		$this->assertFalse($s->supports_autoload());
	}

	/**
	 * @covers \Ran\PluginLib\Options\Storage\BlogOptionStorage::add
	 * @covers \Ran\PluginLib\Options\Storage\BlogOptionStorage::read
	 * @covers \Ran\PluginLib\Options\Storage\BlogOptionStorage::update
	 * @covers \Ran\PluginLib\Options\Storage\BlogOptionStorage::delete
	 */
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

	/**
	 * @covers \Ran\PluginLib\Options\Storage\BlogOptionStorage::supports_autoload
	 */
	public function test_supports_autoload_true_for_current_blog(): void {
		$blog_id = 12;
		WP_Mock::userFunction('get_current_blog_id')->once()->andReturn($blog_id);
		$s = new BlogOptionStorage($blog_id);
		$this->assertTrue($s->supports_autoload());
	}

	/**
	 * @covers \Ran\PluginLib\Options\Storage\BlogOptionStorage::__construct
	 */
	public function test_constructor_sets_blog_id_for_coverage(): void {
		$blog_id = 321;
		$s       = new BlogOptionStorage($blog_id);
		// Intentionally only asserting via public API while restricting coverage to __construct
		$this->assertSame($blog_id, $s->blog_id());
	}
}
