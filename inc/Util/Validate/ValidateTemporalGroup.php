<?php
declare(strict_types=1);

namespace Ran\PluginLib\Util\Validate;

use DateTimeZone;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Temporal validators for date, time, and datetime strings.
 *
 * Dual-mode methods:
 * - With no value, methods return callable(mixed): bool
 */
final class ValidateTemporalGroup {
	/**
	 * Validate calendar dates using common presets.
	 *
	 * Presets include `Y-m-d` (default), `d/m/Y`, `m/d/Y`, and `Ymd`.
	 *
	 * @param string $preset
	 * @param mixed $value
	 * @return callable(mixed):bool|bool
	 */
	public function date(string $preset = 'Y-m-d', mixed $value = null): callable|bool {
		$format    = self::_preset_format($preset, self::_date_preset_map(), 'date');
		$validator = static function (mixed $v) use ($format): bool {
			return self::_validate_format($v, $format);
		};
		return \func_num_args() < 2 ? $validator : $validator($value);
	}

	/**
	 * Validate clock times using common presets.
	 *
	 * Presets include `H:i:s` (default), `H:i`, `h:i:s A`, `h:i A`, and `H:i:s.u`.
	 *
	 * @param string $preset
	 * @param mixed $value
	 * @return callable(mixed):bool|bool
	 */
	public function time(string $preset = 'H:i:s', mixed $value = null): callable|bool {
		$format    = self::_preset_format($preset, self::_time_preset_map(), 'time');
		$validator = static function (mixed $v) use ($format): bool {
			return self::_validate_format($v, $format);
		};
		return \func_num_args() < 2 ? $validator : $validator($value);
	}

	/**
	 * Validate datetime values using named presets.
	 *
	 * @param string $preset
	 * @param bool $requireTimezone
	 * @param mixed $value
	 * @return callable(mixed):bool|bool
	 */
	public function datetime(string $preset = 'iso8601', bool $requireTimezone = true, mixed $value = null): callable|bool {
		$map = self::_datetime_preset_map();
		if (!isset($map[$preset])) {
			throw new InvalidArgumentException(\sprintf('Unknown datetime preset "%s"', $preset));
		}
		$format = $map[$preset];
		if ($requireTimezone && !self::_format_has_timezone($format)) {
			throw new InvalidArgumentException(\sprintf('Preset "%s" does not include timezone information', $preset));
		}
		$validator = static function (mixed $v) use ($format, $requireTimezone): bool {
			return self::_validate_format($v, $format, $requireTimezone);
		};
		return \func_num_args() < 3 ? $validator : $validator($value);
	}

	/**
	 * Validate using a custom format via DateTimeImmutable::createFromFormat.
	 *
	 * @param string $format
	 * @param bool $requireTimezone
	 * @param array<string|DateTimeZone>|string|DateTimeZone|null $allowedTimezones
	 *        Mix identifiers (e.g. "UTC"), offsets (e.g. "+02:00"), or DateTimeZone objects.
	 * @param mixed $value
	 * @return callable(mixed):bool|bool
	 */
	public function custom_datetime(string $format, bool $requireTimezone = false, array|string|DateTimeZone|null $allowedTimezones = null, mixed $value = null): callable|bool {
		if ($requireTimezone && !self::_format_has_timezone($format)) {
			throw new InvalidArgumentException('The provided format does not include timezone information');
		}
		$allowed   = self::_normalize_allowed_timezones($allowedTimezones);
		$validator = static function (mixed $v) use ($format, $requireTimezone, $allowed): bool {
			return self::_validate_format($v, $format, $requireTimezone, $allowed);
		};
		return \func_num_args() < 4 ? $validator : $validator($value);
	}

	/**
	 * @param mixed $value
	 * @param string $format
	 * @param bool $requireTimezone
	 * @param array{names:array<string>, offsets:array<string>}|null $allowedTimezones
	 * @return bool
	 */
	private static function _validate_format(mixed $value, string $format, bool $requireTimezone = false, ?array $allowedTimezones = null): bool {
		if (!\is_string($value) || $value === '') {
			return false;
		}
		$dt = DateTimeImmutable::createFromFormat($format, $value);
		if ($dt === false) {
			return false;
		}
		$errors = DateTimeImmutable::getLastErrors();
		if ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
			return false;
		}
		$tz = $dt->getTimezone();
		if ($requireTimezone && $tz === false) {
			return false;
		}
		if ($allowedTimezones !== null) {
			if ($tz === false) {
				return false;
			}
			$tzName = self::_normalize_timezone_name($tz);
			$offset = $dt->format('P');
			if (!\in_array($tzName, $allowedTimezones['names'], true) && !\in_array($offset, $allowedTimezones['offsets'], true)) {
				return false;
			}
		}
		return $dt->format($format) === $value;
	}

	/**
	 * @return array<string, string>
	 */
	private static function _date_preset_map(): array {
		return array(
			'Y-m-d' => 'Y-m-d',
			'd/m/Y' => 'd/m/Y',
			'm/d/Y' => 'm/d/Y',
			'Ymd'   => 'Ymd',
		);
	}

	/**
	 * @return array<string, string>
	 */
	private static function _time_preset_map(): array {
		return array(
			'H:i:s'   => 'H:i:s',
			'H:i'     => 'H:i',
			'h:i:s A' => 'h:i:s A',
			'h:i A'   => 'h:i A',
			'H:i:s.u' => 'H:i:s.u',
		);
	}

	/**
	 * @return array<string, string>
	 */
	private static function _datetime_preset_map(): array {
		return array(
			'iso8601'          => DateTimeInterface::ATOM,
			'rfc3339'          => DateTimeInterface::RFC3339,
			'rfc3339_extended' => DateTimeInterface::RFC3339_EXTENDED,
			'w3c'              => DateTimeInterface::W3C,
			'mysql'            => 'Y-m-d H:i:s',
			'cookie'           => DateTimeInterface::COOKIE,
			'rfc2822'          => DateTimeInterface::RFC2822,
		);
	}

	/**
	 * @param string $preset
	 * @param array<string, string> $map
	 * @param string $type
	 */
	private static function _preset_format(string $preset, array $map, string $type): string {
		if (!isset($map[$preset])) {
			throw new InvalidArgumentException(\sprintf('Unknown %s preset "%s"', $type, $preset));
		}
		return $map[$preset];
	}

	private static function _format_has_timezone(string $format): bool {
		return (bool) \preg_match('/[eOPTZ]/', $format);
	}

	/**
	 * @param array|string|DateTimeZone|null $allowed
	 * @return array{names:array<string>, offsets:array<string>}|null
	 */
	private static function _normalize_allowed_timezones(array|string|DateTimeZone|null $allowed): ?array {
		if ($allowed === null) {
			return null;
		}
		if ($allowed instanceof DateTimeZone) {
			return self::_allowed_timezone_structure(array($allowed));
		}
		if (\is_string($allowed)) {
			return self::_allowed_timezone_structure(array(new DateTimeZone($allowed)));
		}
		$zones = array();
		foreach ($allowed as $tz) {
			if ($tz instanceof DateTimeZone) {
				$zones[] = $tz;
				continue;
			}
			if (!\is_string($tz)) {
				throw new InvalidArgumentException('Allowed timezones must be strings or DateTimeZone instances');
			}
			$zones[] = new DateTimeZone($tz);
		}
		return self::_allowed_timezone_structure($zones);
	}

	private static function _normalize_timezone_name(DateTimeZone $tz): string {
		return $tz->getName();
	}

	/**
	 * @param array<int, DateTimeZone> $zones
	 * @return array{names:array<string>, offsets:array<string>}
	 */
	private static function _allowed_timezone_structure(array $zones): array {
		$names   = array();
		$offsets = array();
		foreach ($zones as $tz) {
			$names[]   = self::_normalize_timezone_name($tz);
			$offsets[] = self::_timezone_offset_string($tz);
		}
		return array(
			'names'   => array_values(array_unique($names)),
			'offsets' => array_values(array_unique($offsets)),
		);
	}

	private static function _timezone_offset_string(DateTimeZone $tz): string {
		$now = new DateTimeImmutable('now', $tz);
		return $now->format('P');
	}
}
