<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Settings;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Ran\PluginLib\Options\OptionScope;
use Ran\PluginLib\Settings\SettingsScopeHelper;

/**
 * @covers \Ran\PluginLib\Settings\SettingsScopeHelper
 */
final class SettingsScopeHelperTest extends TestCase {
	public function test_parse_scope_accepts_enum_instance(): void {
		$result = SettingsScopeHelper::parseScope(array('scope' => OptionScope::Network));
		$this->assertSame(OptionScope::Network, $result);
	}

	public function test_parse_scope_accepts_string_value(): void {
		$result = SettingsScopeHelper::parseScope(array('scope' => 'user'));
		$this->assertSame(OptionScope::User, $result);
	}

	public function test_parse_scope_returns_null_for_missing_scope(): void {
		$this->assertNull(SettingsScopeHelper::parseScope(null));
		$this->assertNull(SettingsScopeHelper::parseScope(array()));
	}

	public function test_parse_scope_returns_null_for_invalid_value(): void {
		$this->assertNull(SettingsScopeHelper::parseScope(array('scope' => 'invalid')));
		$this->assertNull(SettingsScopeHelper::parseScope(array('scope' => null)));
	}

	public function test_require_allowed_returns_scope_when_allowed(): void {
		$result = SettingsScopeHelper::requireAllowed(OptionScope::Site, OptionScope::Site, OptionScope::Network);
		$this->assertSame(OptionScope::Site, $result);
	}

	public function test_require_allowed_throws_when_scope_not_allowed(): void {
		$this->expectException(InvalidArgumentException::class);
		SettingsScopeHelper::requireAllowed(OptionScope::Blog, OptionScope::Site, OptionScope::Network);
	}
}
