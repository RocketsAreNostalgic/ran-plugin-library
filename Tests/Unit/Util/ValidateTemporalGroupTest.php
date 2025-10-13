<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Util;

use InvalidArgumentException;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Util\Validate;

/**
 * @covers \Ran\PluginLib\Util\Validate::temporal
 * @covers \Ran\PluginLib\Util\Validate\ValidateTemporalGroup
 */
final class ValidateTemporalGroupTest extends PluginLibTestCase {
	/**
	 * @covers \Ran\PluginLib\Util\Validate\ValidateTemporalGroup::date
	 */
	public function test_date_default_preset(): void {
		$validator = Validate::temporal()->date();
		$this->assertTrue($validator('2024-01-31'));
		$this->assertFalse($validator('31/01/2024'));
		$this->assertFalse($validator('invalid'));
		$this->assertFalse($validator(123));
	}

	/**
	 * @covers \Ran\PluginLib\Util\Validate\ValidateTemporalGroup::date
	 */
	public function test_date_custom_preset_dual_mode(): void {
		$this->assertTrue(Validate::temporal()->date('d/m/Y', '31/01/2024'));
		$this->assertFalse(Validate::temporal()->date('d/m/Y', '2024-01-31'));
	}

	/**
	 * @covers \Ran\PluginLib\Util\Validate\ValidateTemporalGroup::time
	 */
	public function test_time_presets(): void {
		$time24 = Validate::temporal()->time();
		$this->assertTrue($time24('23:59:59'));
		$this->assertFalse($time24('23:59'));

		$this->assertTrue(Validate::temporal()->time('h:i A', '11:15 PM'));
		$this->assertFalse(Validate::temporal()->time('h:i A', '23:15'));
	}

	/**
	 * @covers \Ran\PluginLib\Util\Validate\ValidateTemporalGroup::datetime
	 */
	public function test_datetime_iso_requires_timezone(): void {
		$dt = Validate::temporal()->datetime();
		$this->assertTrue($dt('2024-01-31T10:30:00+00:00'));
		$this->assertFalse($dt('2024-01-31T10:30:00'));
		$this->assertFalse($dt('invalid'));
	}

	/**
	 * @covers \Ran\PluginLib\Util\Validate\ValidateTemporalGroup::datetime
	 */
	public function test_datetime_mysql_without_timezone(): void {
		$this->assertTrue(Validate::temporal()->datetime('mysql', false, '2024-01-31 10:30:00'));
		$this->assertFalse(Validate::temporal()->datetime('mysql', false, '2024-01-31T10:30:00Z'));
	}

	/**
	 * @covers \Ran\PluginLib\Util\Validate\ValidateTemporalGroup::datetime
	 */
	public function test_datetime_unknown_preset_throws(): void {
		$this->expectException(InvalidArgumentException::class);
		Validate::temporal()->datetime('unknown');
	}

	/**
	 * @covers \Ran\PluginLib\Util\Validate\ValidateTemporalGroup::custom_datetime
	 */
	public function test_custom_datetime_with_allowed_timezones(): void {
		$allowed   = array('UTC', 'America/New_York', '-05:00');
		$validator = Validate::temporal()->custom_datetime('Y-m-d\TH:i:sP', true, $allowed);
		$this->assertTrue($validator('2024-01-31T10:30:00+00:00'));
		$this->assertTrue($validator('2024-01-31T05:30:00-05:00'));
		$this->assertFalse($validator('2024-01-31T10:30:00+09:00'));
	}

	/**
	 * @covers \Ran\PluginLib\Util\Validate\ValidateTemporalGroup::custom_datetime
	 */
	public function test_custom_datetime_requires_timezone_missing(): void {
		$this->expectException(InvalidArgumentException::class);
		Validate::temporal()->custom_datetime('Y-m-d H:i:s', true);
	}

	/**
	 * @covers \Ran\PluginLib\Util\Validate\ValidateTemporalGroup::custom_datetime
	 */
	public function test_custom_datetime_dual_mode_immediate_call(): void {
		$format = 'Y-m-d H:i:s';
		$this->assertTrue(Validate::temporal()->custom_datetime($format, false, null, '2024-01-31 10:30:00'));
		$this->assertFalse(Validate::temporal()->custom_datetime($format, false, null, '2024-01-31T10:30:00Z'));
	}
}
