<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options;

use WP_Mock;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Options\Storage\StorageContext;
use Ran\PluginLib\Config\ConfigInterface;

final class RegisterOptionsSanitizerIdempotenceTest extends PluginLibTestCase {
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

	private function buildOpts(string $key = 'opts_san_idem'): RegisterOptions {
		return new RegisterOptions($key, StorageContext::forSite(), true, $this->logger_mock);
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_sanitize_and_validate_option
	 * Covers lines 1146-1151: idempotence error details (stringify first/second, describe callable)
	 */
	public function test_sanitizer_idempotence_violation_with_array_callable_reports_details(): void {
		$opts = $this->buildOpts();

		// Array callable sanitizer that is non-idempotent: appends 'x' each time
		$sanitizerClass = new class {
			public static function appendX($v) {
				return (string)$v . 'x';
			}
		};
		$sanitizerFqcn = get_class($sanitizerClass);

		$opts->register_schema(array(
			'name' => array(
				'sanitize' => array(array($sanitizerClass, 'appendX')),
				'validate' => function ($v) {
					return is_string($v);
				},
			),
		));

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessageMatches(
			'/Sanitizer for option \'name\' at index 0 must be idempotent.*Sanitizer ' . preg_quote($sanitizerFqcn, '/') . '::appendX\./'
		);

		$opts->stage_option('name', 'A');
	}
}
