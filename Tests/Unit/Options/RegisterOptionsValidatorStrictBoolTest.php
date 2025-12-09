<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options;

use WP_Mock;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Options\Storage\StorageContext;

/**
 * Focused coverage for _sanitize_and_validate_option lines 1159–1164
 * (validator must return strict boolean).
 */
final class RegisterOptionsValidatorStrictBoolTest extends PluginLibTestCase {
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
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_sanitize_and_validate_option
	 */
	public function test_validator_returning_non_bool_throws_strict_bool_error(): void {
		$opts = new RegisterOptions('opts_validator_strict_bool', StorageContext::forSite(), true, $this->logger_mock);

		$opts->register_schema(array(
		    'y' => array(
		        // Return a string (non-bool) to trigger the strict bool guard
		        'validate' => function ($v) {
		        	return 'yes';
		        },
		    ),
		));

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessageMatches('/Validator for option \'y\' at index 0 must return strict bool; got string\./');

		// Triggers _sanitize_and_validate_option() and non-bool validator path
		$opts->stage_option('y', 'anything');
	}
}
