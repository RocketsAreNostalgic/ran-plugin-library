<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options\Storage;

use WP_Mock;
use Ran\PluginLib\Options\OptionScope;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Options\Storage\SiteOptionStorage;

final class SiteOptionStorageTest extends PluginLibTestCase {
	/**
	 * @covers \Ran\PluginLib\Options\Storage\SiteOptionStorage::scope
	 * @covers \Ran\PluginLib\Options\Storage\SiteOptionStorage::blogId
	 * @covers \Ran\PluginLib\Options\Storage\SiteOptionStorage::supports_autoload
	 */
	public function test_meta_methods(): void {
		$s = new SiteOptionStorage();
		$this->assertSame(OptionScope::Site, $s->scope());
		$this->assertNull($s->blogId());
		$this->assertTrue($s->supports_autoload());
	}

	/**
	 * @covers \Ran\PluginLib\Options\Storage\SiteOptionStorage::add
	 * @covers \Ran\PluginLib\Options\Storage\SiteOptionStorage::read
	 * @covers \Ran\PluginLib\Options\Storage\SiteOptionStorage::update
	 * @covers \Ran\PluginLib\Options\Storage\SiteOptionStorage::delete
	 */
	public function test_read_and_add_update_delete_flow(): void {
		$s = new SiteOptionStorage();

		// stage_option('foo', 'bar', '', 'yes')
		WP_Mock::userFunction('add_option')
		    ->once()
		    ->with('foo', 'bar', '', 'yes')
		    ->andReturn(true);
		$this->assertTrue($s->add('foo', 'bar', true));

		// get_option('foo', false)
		WP_Mock::userFunction('get_option')
		    ->once()
		    ->with('foo', false)
		    ->andReturn('bar');
		$this->assertSame('bar', $s->read('foo'));

		// update_option('foo', 'baz') - wrapper calls 2-arg variant when no autoload is provided
		WP_Mock::userFunction('update_option')
		    ->once()
		    ->with('foo', 'baz')
		    ->andReturn(true);
		$this->assertTrue($s->update('foo', 'baz', false));

		// delete_option('foo')
		WP_Mock::userFunction('delete_option')
		    ->once()
		    ->with('foo')
		    ->andReturn(true);
		$this->assertTrue($s->delete('foo'));
	}
}
