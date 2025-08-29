<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options\_helpers;

use Mockery;
use WP_Mock;

trait RegisterOptionsWpMocksTrait {
	protected function init_wp_mocks(string $mainOption): void {
		// Baseline WP stubs
		WP_Mock::userFunction('sanitize_key')
		    ->andReturnUsing(function ($v) {
		    	$s = strtolower(preg_replace('/[^a-z0-9_\-]/i', '', (string) $v));
		    	return trim($s, '_');
		    })
		    ->byDefault();

		// Default reads (specific main option cases)
		WP_Mock::userFunction('get_option')->with($mainOption, array())->andReturn(array())->byDefault();
		// Sentinel default reads (stdClass default pattern)
		WP_Mock::userFunction('get_option')
		    ->with($mainOption, Mockery::type('object'))
		    ->andReturnUsing(fn($n, $d) => $d)
		    ->byDefault();
		// Broad fallback: for any other key or default type, return the provided default (or false if none)
		WP_Mock::userFunction('get_option')
		    ->andReturnUsing(function ($name, ...$args) {
		    	return $args[0] ?? false;
		    })
		    ->byDefault();
		// Storage/SiteOptionStorage may query plugin textdomain option
		WP_Mock::userFunction('get_option')->with('mock-plugin-textdomain', false)->andReturn(false)->byDefault();
		WP_Mock::userFunction('wp_load_alloptions')->andReturn(array())->byDefault();

		// Default apply_filters passthrough
		WP_Mock::userFunction('apply_filters')->andReturnUsing(fn($h, $v) => $v)->byDefault();

		// User meta defaults (in case user scope is used)
		WP_Mock::userFunction('get_user_meta')->andReturn(array())->byDefault();
		WP_Mock::userFunction('update_user_meta')->andReturn(true)->byDefault();
		WP_Mock::userFunction('delete_user_meta')->andReturn(true)->byDefault();

		// Network option functions
		WP_Mock::userFunction('get_site_option')->andReturnUsing(function ($name, ...$args) {
			return $args[0] ?? false;
		})->byDefault();
		WP_Mock::userFunction('update_site_option')->andReturn(true)->byDefault();
		WP_Mock::userFunction('add_site_option')->andReturn(true)->byDefault();
		WP_Mock::userFunction('delete_site_option')->andReturn(true)->byDefault();

		// Blog option functions
		WP_Mock::userFunction('get_blog_option')->andReturnUsing(function ($blog_id, $name, ...$args) {
			return $args[0] ?? false;
		})->byDefault();
		WP_Mock::userFunction('update_blog_option')->andReturn(true)->byDefault();
		WP_Mock::userFunction('add_blog_option')->andReturn(true)->byDefault();
		WP_Mock::userFunction('delete_blog_option')->andReturn(true)->byDefault();

		// Updates default
		WP_Mock::userFunction('update_option')
		    ->with($mainOption, Mockery::type('array'), Mockery::type('string'))
		    ->andReturn(true)
		    ->byDefault();
		WP_Mock::userFunction('add_option')->andReturn(true)->byDefault();
		WP_Mock::userFunction('delete_option')->andReturn(true)->byDefault();

		// Blog helpers
		WP_Mock::userFunction('get_current_blog_id')->andReturn(1)->byDefault();
		WP_Mock::userFunction('is_multisite')->andReturn(true)->byDefault();
	}
}
