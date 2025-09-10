<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options\Policy;

use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Options\Policy\DenyAllWritePolicy;
use Ran\PluginLib\Options\WriteContext;

/**
 * @covers \Ran\PluginLib\Options\Policy\DenyAllWritePolicy
 */
final class DenyAllWritePolicyTest extends PluginLibTestCase {
	public function test_deny_all_ops(): void {
		$policy = new DenyAllWritePolicy();
		$wc     = WriteContext::for_clear('dummy', 'site', null, null, 'meta', false);

		$ops = array('set_option', 'add_option', 'delete_option', 'add_options', 'clear', 'save_all', 'seed_if_missing', 'migrate');
		foreach ($ops as $op) {
			self::assertFalse($policy->allow($op, $wc), "$op should be denied by DenyAllWritePolicy");
		}
	}
}
