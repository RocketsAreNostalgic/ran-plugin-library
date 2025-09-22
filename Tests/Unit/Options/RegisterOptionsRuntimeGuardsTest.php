<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options;

use WP_Mock;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Options\Storage\StorageContext;
use Ran\PluginLib\Util\ExpectLogTrait;

final class RegisterOptionsRuntimeGuardsTest extends PluginLibTestCase {
	use ExpectLogTrait;

	public function setUp(): void {
		parent::setUp();
		// Common WP stubs
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
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_sanitize_and_validate_option
	 */
	public function test_runtime_throws_when_sanitize_present_and_non_callable(): void {
		$opts = new RegisterOptions('opts_runtime_guard', StorageContext::forSite(), true, $this->logger_mock);

		// Inject malformed schema directly (bypassing register_schema)
		$ref  = new \ReflectionClass($opts);
		$prop = $ref->getProperty('schema');
		$prop->setAccessible(true);
		$prop->setValue($opts, array(
		    'bad1' => array(
		        'sanitize' => 123, // non-callable and not null
		        'validate' => function($v) {
		        	return is_string($v);
		        },
		    ),
		));

		$meth = $ref->getMethod('_sanitize_and_validate_option');
		$meth->setAccessible(true);

		$this->expectException(\InvalidArgumentException::class);
		try {
			$meth->invoke($opts, 'bad1', 'value');
		} finally {
			$this->expectLog('warning', 'runtime non-callable sanitize');
		}
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_sanitize_and_validate_option
	 */
	public function test_runtime_throws_when_validate_missing_or_non_callable(): void {
		$opts = new RegisterOptions('opts_runtime_guard2', StorageContext::forSite(), true, $this->logger_mock);

		// Inject malformed schema without validate
		$ref  = new \ReflectionClass($opts);
		$prop = $ref->getProperty('schema');
		$prop->setAccessible(true);
		$prop->setValue($opts, array(
		    'bad2' => array(
		        'sanitize' => null,
		        // missing validate
		    ),
		));

		$meth = $ref->getMethod('_sanitize_and_validate_option');
		$meth->setAccessible(true);

		$this->expectException(\InvalidArgumentException::class);
		try {
			$meth->invoke($opts, 'bad2', 'value');
		} finally {
			$this->expectLog('warning', 'runtime missing/non-callable validate');
		}
	}
}
