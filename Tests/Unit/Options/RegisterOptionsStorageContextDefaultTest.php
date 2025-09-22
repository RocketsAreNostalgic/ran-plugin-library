<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options;

use WP_Mock;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Options\Storage\StorageContext;

/**
 * Focused coverage for _get_storage_context() defaulting and memoization
 * hitting lines 894–895 in RegisterOptions.php.
 */
final class RegisterOptionsStorageContextDefaultTest extends PluginLibTestCase {
	public function setUp(): void {
		parent::setUp();
		// Common WP stubs to avoid calling real WP functions during constructor
		WP_Mock::userFunction('get_option')->andReturn(array())->byDefault();
		WP_Mock::userFunction('get_site_option')->andReturn(array())->byDefault();
		WP_Mock::userFunction('get_blog_option')->andReturn(array())->byDefault();
		WP_Mock::userFunction('get_user_option')->andReturn(array())->byDefault();
		WP_Mock::userFunction('get_user_meta')->andReturn(array())->byDefault();
		WP_Mock::userFunction('wp_load_alloptions')->andReturn(array())->byDefault();
		WP_Mock::userFunction('sanitize_key')->andReturnUsing(function($k) {
			$k = strtolower((string)$k);
			$k = preg_replace('/[^a-z0-9_\-]+/i', '_', $k) ?? '';
			return trim($k, '_');
		});
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_get_storage_context
	 */
	public function test_get_storage_context_defaults_to_site_and_memoizes(): void {
		$opts = new RegisterOptions('opts_ctx_default', StorageContext::forSite(), true, $this->logger_mock);

		// Force the internal property to null to exercise default path
		$ref  = new \ReflectionClass($opts);
		$prop = $ref->getProperty('storage_context');
		$prop->setAccessible(true);
		$prop->setValue($opts, null);

		// Invoke the private method directly to target the lines precisely
		$meth = $ref->getMethod('_get_storage_context');
		$meth->setAccessible(true);
		$ctx1 = $meth->invoke($opts);

		$this->assertInstanceOf(StorageContext::class, $ctx1);
		$this->assertSame('site', $ctx1->scope->value);

		// Second invocation should return the exact same instance (memoized)
		$ctx2 = $meth->invoke($opts);
		$this->assertSame($ctx1, $ctx2);
	}
}
