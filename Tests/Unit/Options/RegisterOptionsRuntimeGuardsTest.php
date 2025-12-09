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
		$meth->invoke($opts, 'bad1', 'value');
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_sanitize_and_validate_option
	 */
	public function test_runtime_allows_missing_validate_definition(): void {
		$opts = new RegisterOptions('opts_runtime_guard2', StorageContext::forSite(), true, $this->logger_mock);

		// Inject schema without validate to confirm relaxed requirement
		$ref  = new \ReflectionClass($opts);
		$prop = $ref->getProperty('schema');
		$prop->setAccessible(true);
		$prop->setValue($opts, array(
		    'no_validate' => array(
		        'sanitize' => null,
		        'validate' => array(
		            'component' => array(),
		            'schema'    => array(),
		        ),
		    ),
		));

		$meth = $ref->getMethod('_sanitize_and_validate_option');
		$meth->setAccessible(true);

		self::assertSame('value', $meth->invoke($opts, 'no_validate', 'value'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::stage_option
	 * @covers \Ran\PluginLib\Options\RegisterOptions::commit_merge
	 */
	public function test_commit_merge_succeeds_when_validator_buckets_empty(): void {
		$opts = new RegisterOptions('opts_runtime_guard3', StorageContext::forSite(), true, $this->logger_mock);
		WP_Mock::userFunction('apply_filters')->withAnyArgs()->andReturn(true);
		WP_Mock::userFunction('update_option')->andReturn(true);
		WP_Mock::userFunction('current_user_can')->withAnyArgs()->andReturn(true);

		$ref  = new \ReflectionClass($opts);
		$prop = $ref->getProperty('schema');
		$prop->setAccessible(true);
		$prop->setValue($opts, array(
		    'empty_validate' => array(
		        'sanitize' => array(
		        	'component' => array(),
		        	'schema'    => array(),
		        ),
		        'validate' => array(
		        	'component' => array(),
		        	'schema'    => array(),
		        ),
		    ),
		));

		$opts->stage_option('empty_validate', 'example-value');

		self::assertSame('example-value', $opts->get_option('empty_validate'));
		self::assertTrue($opts->commit_merge());
	}
}
