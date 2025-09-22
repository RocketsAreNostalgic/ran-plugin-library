<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options;

use WP_Mock;
use Ran\PluginLib\Util\Validate;
use Ran\PluginLib\Util\ExpectLogTrait;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;

final class RegisterOptionsStageOptionsGateSecurityTest extends PluginLibTestCase {
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
		// Capability ok
		WP_Mock::userFunction('current_user_can')->andReturn(true)->byDefault();
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::stage_options
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_apply_write_gate
	 */
	public function test_batch_vetoes_when_any_key_disallowed(): void {
		$opts = RegisterOptions::site('opts_gate_batch', true, $this->logger_mock);
		$opts->with_schema(array(
			'allowed' => array('validate' => Validate::basic()->isString()),
			'denied'  => array('validate' => Validate::basic()->isString()),
		));

		// Policy: deny when 'denied' appears in batch keys
		$policy = new class implements \Ran\PluginLib\Options\Policy\WritePolicyInterface {
			public function allow(string $op, \Ran\PluginLib\Options\WriteContext $wc): bool {
				if ($op === 'stage_options' && is_array($wc->keys()) && in_array('denied', $wc->keys(), true)) {
					return false;
				}
				return true;
			}
		};
		$opts->with_policy($policy);

		$opts->stage_options(array('allowed' => 'A', 'denied' => 'B'));
		// Expect no mutation
		$this->assertFalse($opts->has_option('allowed'));
		$this->assertFalse($opts->has_option('denied'));
		// And no summary log since gate vetoed before mutation
		$this->expectLog('debug', 'stage_options summary', 0);
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::stage_option
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_apply_write_gate
	 */
	public function test_single_key_veto_with_add_option_gate(): void {
		$opts = RegisterOptions::site('opts_gate_single', true, $this->logger_mock);
		$opts->with_schema(array(
			'allow' => array('validate' => Validate::basic()->isString()),
			'deny'  => array('validate' => Validate::basic()->isString()),
		));

		// Policy: veto single key 'deny' on add_option
		$policy = new class implements \Ran\PluginLib\Options\Policy\WritePolicyInterface {
			public function allow(string $op, \Ran\PluginLib\Options\WriteContext $wc): bool {
				if ($op === 'add_option' && $wc->key() === 'deny') {
					return false;
				}
				return true;
			}
		};
		$opts->with_policy($policy);

		// Denied key should not be staged
		$opts->stage_option('deny', 'X');
		$this->assertFalse($opts->has_option('deny'));

		// Allowed key should stage normally
		$opts->stage_option('allow', 'Y');
		$this->assertSame('Y', $opts->get_option('allow'));
	}
}
