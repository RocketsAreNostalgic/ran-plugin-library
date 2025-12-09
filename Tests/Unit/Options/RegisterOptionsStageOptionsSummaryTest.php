<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options;

use WP_Mock;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Options\Storage\StorageContext;
use Ran\PluginLib\Util\ExpectLogTrait;

/**
 * Covers stage_options summary logging (lines 571–579).
 */
final class RegisterOptionsStageOptionsSummaryTest extends PluginLibTestCase {
	use ExpectLogTrait;

	public function setUp(): void {
		parent::setUp();
		// Ensure logger is active so summary logging executes
		if (method_exists($this->logger_mock, 'shouldReceive')) {
			$this->logger_mock->shouldReceive('is_active')->byDefault()->andReturn(true);
		}
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
		// Allow capability and write gating to pass
		WP_Mock::userFunction('current_user_can')->andReturn(true)->byDefault();
		// Allow write gating to pass via specific filters (avoid brittle global apply_filters)
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist')
			->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
			->reply(true);
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist/scope/site')
			->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
			->reply(true);
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::stage_options
	 */
	public function test_stage_options_emits_summary_logging_with_changed_count_and_keys(): void {
		$opts = new RegisterOptions('opts_stage_summary', StorageContext::forSite(), true, $this->logger_mock);

		// Provide schema for keys
		$opts->with_schema(array(
		    'k1' => array('validate' => function($v) {
		    	return is_string($v);
		    }),
		    'k2' => array('validate' => function($v) {
		    	return is_string($v);
		    }),
		    'k3' => array('validate' => function($v) {
		    	return is_string($v);
		    }),
		));

		// Pre-populate to create a mix of changed and unchanged
		$ref  = new \ReflectionClass($opts);
		$prop = $ref->getProperty('options');
		$prop->setAccessible(true);
		$prop->setValue($opts, array(
		    'k1' => 'old',   // will change
		    'k3' => 'same',  // will remain same (no change)
		));

		// Stage multiple options: k1 changes, k2 new (changes), k3 unchanged (no change)
		$opts->stage_options(array(
		    'k1' => 'new',
		    'k2' => 'val',
		    'k3' => 'same',
		));

		// Expect the summary log to be emitted
		$this->expectLog('debug', 'stage_options summary');

		// Sanity: values updated in memory as expected
		$this->assertSame('new', $opts->get_option('k1'));
		$this->assertSame('val', $opts->get_option('k2'));
		$this->assertSame('same', $opts->get_option('k3'));
	}
}
