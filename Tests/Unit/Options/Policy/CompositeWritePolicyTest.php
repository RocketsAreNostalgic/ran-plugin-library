<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options\Policy;

use Ran\PluginLib\Options\WriteContext;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Options\Policy\DenyAllWritePolicy;
use Ran\PluginLib\Options\Policy\CompositeWritePolicy;
use Ran\PluginLib\Tests\Unit\Options\Stubs\AllowAllPolicy;

/**
 * @covers \Ran\PluginLib\Options\Policy\CompositeWritePolicy
 */
final class CompositeWritePolicyTest extends PluginLibTestCase {
	public function test_and_semantics_denies_if_any_denies(): void {
		$wc        = WriteContext::for_clear('dummy', 'site', null, null, 'meta', false);
		$composite = new CompositeWritePolicy(new AllowAllPolicy(), new DenyAllWritePolicy());
		self::assertFalse($composite->allow('clear', $wc));
	}

	public function test_and_semantics_allows_when_all_allow(): void {
		$wc        = WriteContext::for_clear('dummy', 'site', null, null, 'meta', false);
		$composite = new CompositeWritePolicy(new AllowAllPolicy(), new AllowAllPolicy());
		self::assertTrue($composite->allow('clear', $wc));
	}
}
