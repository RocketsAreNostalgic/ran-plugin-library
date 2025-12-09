<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options;

use WP_Mock;
use Ran\PluginLib\Util\ExpectLogTrait;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Options\Storage\StorageContext;

/**
 * Covers RegisterOptions::_apply_write_gate final decision logging (lines ~1008-1016).
 * We force the allow_persist filter to return false and assert the final decision log.
 */
final class RegisterOptionsApplyWriteGateFinalDecisionTest extends PluginLibTestCase {
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
		// Capability check
		WP_Mock::userFunction('current_user_can')->andReturn(true)->byDefault();
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_apply_write_gate
	 * @uses   \Ran\PluginLib\Options\WriteContext
	 */
	public function test_final_decision_log_is_emitted_when_gate_vetoes(): void {
		// Anonymous subclass to deterministically veto allow_persist hooks only
		$opts = new class('opts_gate_final', StorageContext::forSite(), true, $this->logger_mock) extends RegisterOptions {
			public function _do_apply_filter(string $hook_name, $value, ...$args) {
				if ($hook_name === 'ran/plugin_lib/options/allow_persist' || $hook_name === 'ran/plugin_lib/options/allow_persist/scope/site') {
					return false; // force veto for this test
				}
				return $value;
			}
		};

		// Public call to exercise the gate path
		$opts->with_schema(array('k' => array('validate' => fn($v) => is_string($v))));
		$opts->stage_option('k', 'v');

		// Assert: not staged and logs emitted (notice + final decision)
		$this->assertFalse($opts->has_option('k'));
		$this->expectLog('notice', 'Write vetoed by allow_persist filter.');
		$this->expectLog('debug', '_apply_write_gate final decision');
	}
}
