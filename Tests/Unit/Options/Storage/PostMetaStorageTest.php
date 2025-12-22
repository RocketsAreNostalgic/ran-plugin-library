<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options\Storage;

use WP_Mock;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Options\Storage\PostMetaStorage;
use Ran\PluginLib\Options\OptionScope;

final class PostMetaStorageTest extends PluginLibTestCase {
	/**
	 * @covers \Ran\PluginLib\Options\Storage\PostMetaStorage::scope
	 * @covers \Ran\PluginLib\Options\Storage\PostMetaStorage::blog_id
	 * @covers \Ran\PluginLib\Options\Storage\PostMetaStorage::supports_autoload
	 */
	public function test_meta_methods(): void {
		$s = new PostMetaStorage(42);
		$this->assertSame(OptionScope::Post, $s->scope());
		$this->assertNull($s->blog_id());
		$this->assertFalse($s->supports_autoload());
	}

	/**
	 * @covers \Ran\PluginLib\Options\Storage\PostMetaStorage::add
	 * @covers \Ran\PluginLib\Options\Storage\PostMetaStorage::read
	 * @covers \Ran\PluginLib\Options\Storage\PostMetaStorage::update
	 * @covers \Ran\PluginLib\Options\Storage\PostMetaStorage::delete
	 */
	public function test_read_add_update_delete_post_meta(): void {
		$post_id = 7;
		$s       = new PostMetaStorage($post_id);

		// add() delegates to update()
		WP_Mock::userFunction('update_post_meta')
			->once()
			->with($post_id, 'foo', 'bar', '')
			->andReturn(true);
		$this->assertTrue($s->add('foo', 'bar'));

		// read
		WP_Mock::userFunction('get_post_meta')
			->once()
			->with($post_id, 'foo', true)
			->andReturn('bar');
		$this->assertSame('bar', $s->read('foo'));

		// update
		WP_Mock::userFunction('update_post_meta')
			->once()
			->with($post_id, 'foo', 'baz', '')
			->andReturn(true);
		$this->assertTrue($s->update('foo', 'baz'));

		// delete
		WP_Mock::userFunction('delete_post_meta')
			->once()
			->with($post_id, 'foo')
			->andReturn(true);
		$this->assertTrue($s->delete('foo'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\Storage\PostMetaStorage::__construct
	 */
	public function test_constructor_for_coverage(): void {
		// Simple instantiation to execute constructor assignment
		$s = new PostMetaStorage(99);
		// Verify via public API while restricting coverage to __construct
		$this->assertSame(OptionScope::Post, $s->scope());
		$this->assertNull($s->blog_id());
	}
}
