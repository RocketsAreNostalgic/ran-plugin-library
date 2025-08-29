<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options;

use PHPUnit\Framework\TestCase;
use Ran\PluginLib\Options\OptionScope;

final class OptionScopeTest extends TestCase {
	public function test_enum_exists_and_is_backed(): void {
		$this->assertTrue(enum_exists(OptionScope::class), 'OptionScope enum should exist');
		// Ensure cases() method exists and returns array (backed enum behavior)
		$cases = OptionScope::cases();
		$this->assertIsArray($cases);
		$this->assertNotEmpty($cases);
	}

	public function test_enum_includes_required_scopes(): void {
		$values = array_map(static fn(OptionScope $c) => $c->value, OptionScope::cases());
		$this->assertContains('site', $values, 'Enum must include site scope');
		$this->assertContains('network', $values, 'Enum must include network scope');
		$this->assertContains('blog', $values, 'Enum must include blog scope');
		// note: user scope is optional for now; do not assert
	}
}
