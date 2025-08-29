<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options\Storage;

use WP_Mock;
use Ran\PluginLib\Options\OptionScope;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Options\Storage\SiteOptionStorage;

final class SiteOptionStorageTest extends PluginLibTestCase {
	public function test_meta_methods(): void {
		$s = new SiteOptionStorage();
		$this->assertSame(OptionScope::Site, $s->scope());
		$this->assertNull($s->blogId());
		$this->assertTrue($s->supports_autoload());
	}

	public function test_read_and_add_update_delete_flow(): void {
		$s = new SiteOptionStorage();

		// add_option('foo', 'bar', '', 'yes')
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

		// update_option('foo', 'baz', 'no')
		WP_Mock::userFunction('update_option')
		    ->once()
		    ->with('foo', 'baz', 'no')
		    ->andReturn(true);
		$this->assertTrue($s->update('foo', 'baz', false));

		// delete_option('foo')
		WP_Mock::userFunction('delete_option')
		    ->once()
		    ->with('foo')
		    ->andReturn(true);
		$this->assertTrue($s->delete('foo'));
	}

	public function test_load_all_autoloaded(): void {
		$s = new SiteOptionStorage();

		WP_Mock::userFunction('wp_load_alloptions')
		    ->once()
		    ->with(false)
		    ->andReturn(array('a' => 1, 'b' => 2));

		$all = $s->load_all_autoloaded();
		$this->assertIsArray($all);
		$this->assertSame(array('a' => 1, 'b' => 2), $all);
	}
}
