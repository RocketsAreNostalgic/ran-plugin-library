<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options\Storage;

use WP_Mock;
use Ran\PluginLib\Options\OptionScope;
use Ran\PluginLib\Options\Storage\NetworkOptionStorage;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;

final class NetworkOptionStorageTest extends PluginLibTestCase {
	public function test_meta_methods(): void {
		$s = new NetworkOptionStorage();
		$this->assertSame(OptionScope::Network, $s->scope());
		$this->assertNull($s->blogId());
		$this->assertFalse($s->supports_autoload());
	}

	public function test_read_and_add_update_delete_flow(): void {
		$s = new NetworkOptionStorage();

		// add_site_option('foo', 'bar')
		WP_Mock::userFunction('add_site_option')
		    ->once()
		    ->with('foo', 'bar')
		    ->andReturn(true);
		$this->assertTrue($s->add('foo', 'bar', true)); // autoload ignored

		// get_site_option('foo', false)
		WP_Mock::userFunction('get_site_option')
		    ->once()
		    ->with('foo', false)
		    ->andReturn('bar');
		$this->assertSame('bar', $s->read('foo'));

		// update_site_option('foo', 'baz')
		WP_Mock::userFunction('update_site_option')
		    ->once()
		    ->with('foo', 'baz')
		    ->andReturn(true);
		$this->assertTrue($s->update('foo', 'baz', false)); // autoload ignored

		// delete_site_option('foo')
		WP_Mock::userFunction('delete_site_option')
		    ->once()
		    ->with('foo')
		    ->andReturn(true);
		$this->assertTrue($s->delete('foo'));
	}

	public function test_load_all_autoloaded_is_null(): void {
		$s = new NetworkOptionStorage();
		$this->assertNull($s->load_all_autoloaded());
	}
}
