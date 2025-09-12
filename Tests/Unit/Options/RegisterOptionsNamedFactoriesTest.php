<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options;

use WP_Mock;
use Ran\PluginLib\Options\OptionScope;
use Ran\PluginLib\Util\CollectingLogger;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;

/**
 * Coverage for named factories: site(), network(), blog(), user().
 * This file currently focuses on site() lines 189-194.
 */
final class RegisterOptionsNamedFactoriesTest extends PluginLibTestCase {
	public function setUp(): void {
		parent::setUp();
		// Common stubs used by storage adapters
		WP_Mock::userFunction('wp_load_alloptions')->andReturn(array())->byDefault();
		WP_Mock::userFunction('get_option')->andReturn(array())->byDefault();
		WP_Mock::userFunction('sanitize_key')->andReturnUsing(function ($key) {
			$key = strtolower((string) $key);
			$key = preg_replace('/[^a-z0-9_\-]+/i', '_', $key) ?? '';
			return trim($key, '_');
		});
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::site
	 */
	public function test_site_factory_reads_options_and_sets_site_scope(): void {
		$main = 'test_site_option';
		// Simulate existing row for the main option
		WP_Mock::userFunction('get_option')->andReturnUsing(function ($name, $default = null) use ($main) {
			if ($name === $main) {
				return array('a' => 1);
			}
			return array();
		});

		$logger = new CollectingLogger();

		$opts = RegisterOptions::site($main, true, $logger);

		// Instance created and bound to site storage (supports autoload)
		self::assertInstanceOf(RegisterOptions::class, $opts);
		self::assertTrue($opts->supports_autoload());
		// The main options payload should reflect get_option() value
		self::assertSame(array('a' => 1), $opts->get_options());
	}

	public function test_blog_factory_non_current_forces_autoload_false(): void {
		$main = 'test_blog_option';
		// Simulate current blog ID = 123
		WP_Mock::userFunction('get_current_blog_id')->andReturn(123);
		// Simulate in-scope storage read
		WP_Mock::userFunction('get_blog_option')->andReturnUsing(function ($blog_id, $name, $default = null) use ($main) {
			if ($name === $main) {
				return array('b' => 2);
			}
			return array();
		});

		$opts = RegisterOptions::blog($main, 456, true, new CollectingLogger());
		// Non-current blog must force autoload=false internally
		$autoload = $this->_get_protected_property_value($opts, 'main_option_autoload');
		self::assertFalse($autoload);
		// Verify payload read happened
		self::assertSame(array('b' => 2), $opts->get_options());
	}

	public function test_blog_factory_current_respects_autoload_true(): void {
		$main = 'test_blog_option2';
		// Simulate current blog ID = 42
		WP_Mock::userFunction('get_current_blog_id')->andReturn(42);
		// Simulate storage read for current blog
		WP_Mock::userFunction('get_blog_option')->andReturnUsing(function ($blog_id, $name, $default = null) use ($main) {
			if ($name === $main) {
				return array('c' => 3);
			}
			return array();
		});

		$opts     = RegisterOptions::blog($main, 42, true, new CollectingLogger());
		$autoload = $this->_get_protected_property_value($opts, 'main_option_autoload');
		self::assertTrue($autoload);
		self::assertSame(array('c' => 3), $opts->get_options());
	}

	public function test_blog_factory_current_with_null_autoload_sets_false_via_ternary(): void {
		$main = 'test_blog_option3';
		// Simulate current blog ID = 77
		WP_Mock::userFunction('get_current_blog_id')->andReturn(77);
		WP_Mock::userFunction('get_blog_option')->andReturnUsing(function ($blog_id, $name, $default = null) use ($main) {
			if ($name === $main) {
				return array('d' => 4);
			}
			return array();
		});

		// Pass null for autoload_on_create to hit ternary else branch (: false)
		$opts     = RegisterOptions::blog($main, 77, null, new CollectingLogger());
		$autoload = $this->_get_protected_property_value($opts, 'main_option_autoload');
		self::assertFalse($autoload);
		self::assertSame(array('d' => 4), $opts->get_options());
	}
}
