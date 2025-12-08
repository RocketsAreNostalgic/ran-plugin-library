<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Settings;

use Ran\PluginLib\Settings\SettingsScopeHelper;
use Ran\PluginLib\Options\OptionScope;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

/**
 * @covers \Ran\PluginLib\Settings\SettingsScopeHelper
 */
final class SettingsScopeHelperTest extends TestCase {
	public function test_parse_scope_accepts_enum_instance(): void {
		$result = SettingsScopeHelper::parse_scope(array('scope' => OptionScope::Network));
		$this->assertSame(OptionScope::Network, $result);
	}

	public function test_parse_scope_accepts_string_value(): void {
		$result = SettingsScopeHelper::parse_scope(array('scope' => 'user'));
		$this->assertSame(OptionScope::User, $result);
	}

	public function test_parse_scope_returns_null_for_missing_scope(): void {
		$this->assertNull(SettingsScopeHelper::parse_scope(null));
		$this->assertNull(SettingsScopeHelper::parse_scope(array()));
	}

	public function test_parse_scope_returns_null_for_invalid_value(): void {
		$this->assertNull(SettingsScopeHelper::parse_scope(array('scope' => 'invalid')));
		$this->assertNull(SettingsScopeHelper::parse_scope(array('scope' => null)));
	}

	public function test_require_allowed_returns_scope_when_allowed(): void {
		$result = SettingsScopeHelper::require_allowed(OptionScope::Site, OptionScope::Site, OptionScope::Network);
		$this->assertSame(OptionScope::Site, $result);
	}

	public function test_require_allowed_throws_when_scope_not_allowed(): void {
		$this->expectException(InvalidArgumentException::class);
		SettingsScopeHelper::require_allowed(OptionScope::Blog, OptionScope::Site, OptionScope::Network);
	}
}
