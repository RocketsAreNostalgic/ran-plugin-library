<?php
/**
 * Shared validation helpers bridging component normalizers and RegisterOptions staging.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Validation;

use InvalidArgumentException;
use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Util\Sanitize;

/**
 * Centralised helpers that expose the common sanitisation and validation logic used by
 * component normalisers (render path) and RegisterOptions staging (persistence path).
 *
 * Normalisers can call these helpers directly while still performing view-specific work
 * (ID generation, ARIA wiring, etc.). RegisterOptions can consume the same helpers when
 * prepending component defaults so both code paths stay in sync.
 */
final class Helpers {
	/**
	 * Sanitize a scalar value into a trimmed string.
	 *
	 * @param mixed $value Raw value provided by integrators or persisted options.
	 * @param string $fieldName Identifier used for logging/context.
	 * @param Logger $logger Logger instance for diagnostics.
	 * @param callable|null $emitNotice Optional callback: fn(string $code, string $message, array $context = []): void
	 *
	 * @return string Sanitized string value.
	 */
	public static function sanitizeString(mixed $value, string $fieldName, Logger $logger, ?callable $emitNotice = null): string {
		if (!is_scalar($value) && $value !== null) {
			self::logTypeError('string', $value, $fieldName, $logger);
			throw new InvalidArgumentException(self::buildTypeErrorMessage('string', $value, $fieldName));
		}

		$original  = (string) ($value ?? '');
		$sanitized = Sanitize::string()->trim()($original);

		if ($emitNotice !== null && $original !== $sanitized) {
			$emitNotice(
				'forms.validation.string_trimmed',
				"String value for '{$fieldName}' was trimmed during sanitisation.",
				array('field' => $fieldName)
			);
		}

		return $sanitized;
	}

	/**
	 * Sanitize a value into a boolean using the shared Sanitise utility.
	 *
	 * @param mixed $value Incoming value to coerce.
	 * @param string $fieldName Field identifier for logging.
	 * @param Logger $logger Logger instance.
	 * @param callable|null $emitNotice Optional callback for transformation notices.
	 */
	public static function sanitizeBoolean(mixed $value, string $fieldName, Logger $logger, ?callable $emitNotice = null): bool {
		$original = $value;
		$result   = Sanitize::bool()->to_bool($value);

		if (is_bool($result)) {
			if ($emitNotice !== null && $original !== $result) {
				$emitNotice(
					'forms.validation.boolean_coerced',
					"Boolean value for '{$fieldName}' was coerced from " . self::displayValue($original) . ' to ' . ($result ? 'true' : 'false') . '.',
					array('field' => $fieldName)
				);
			}

			return $result;
		}

		self::logTypeError('boolean', $original, $fieldName, $logger);
		throw new InvalidArgumentException(self::buildTypeErrorMessage('boolean', $original, $fieldName));
	}

	/**
	 * Sanitize configuration strings supplied by developers.
	 *
	 * Unlike sanitizeString(), this helper throws when the trimmed result is empty, matching
	 * the fail-fast expectations in NormalizerBase::_validate_config_string().
	 */
	public static function sanitizeConfigString(string $value, string $fieldName, Logger $logger): string {
		$trimmed = Sanitize::string()->trim()($value);
		if ($trimmed === '') {
			$logger->error('forms.validation.empty_config_string', array(
				'field' => $fieldName,
			));
			throw new InvalidArgumentException("Configuration field '{$fieldName}' cannot be empty if provided");
		}

		return $trimmed;
	}

	/**
	 * Normalize an array of extension strings (lowercase + trim) while preserving keys.
	 */
	public static function normalizeExtensions(array $extensions): array {
		return array_map(
			static fn($ext) => Sanitize::string()->trim(strtolower((string) $ext)),
			$extensions
		);
	}

	/**
	 * Compare two structures using canonical order-insensitive shallow comparison.
	 */
	public static function canonicalStructuresMatch(mixed $first, mixed $second): bool {
		return Sanitize::canonical()->order_insensitive_shallow($first)
			=== Sanitize::canonical()->order_insensitive_shallow($second);
	}

	/**
	 * Validate that a value exists (non-empty) when required. Emits a warning on failure.
	 *
	 * @param mixed $value Value to inspect.
	 * @param string $fieldName Identifier used for logging and message payloads.
	 * @param Logger $logger Logger instance.
	 * @param callable|null $emitWarning Optional callback for warnings (fn(string $code, string $message, array $context = []): void).
	 *
	 * @return bool True when the field satisfies the required constraint.
	 */
	public static function validateRequired(mixed $value, string $fieldName, Logger $logger, ?callable $emitWarning = null): bool {
		$isEmpty = $value === null
			|| $value        === ''
			|| (is_array($value) && count($value) === 0);

		if ($isEmpty) {
			$logger->warning('forms.validation.required_missing', array('field' => $fieldName));
			if ($emitWarning !== null) {
				$emitWarning(
					'forms.validation.required_missing',
					"Value for '{$fieldName}' is required.",
					array('field' => $fieldName)
				);
			}

			return false;
		}

		return true;
	}

	/**
	 * Validate that a value matches one of the allowed choices.
	 *
	 * @template T
	 * @param T|null $value Value to validate.
	 * @param array<int,T> $allowedValues Allowed value set.
	 * @param string $fieldName Field identifier.
	 * @param Logger $logger Logger instance.
	 * @param callable|null $emitWarning Optional warning emitter.
	 * @param T|null $defaultValue Default to use when value not provided.
	 *
	 * @return T Validated choice (original, default, or first allowed value).
	 */
	public static function validateChoice(mixed $value, array $allowedValues, string $fieldName, Logger $logger, ?callable $emitWarning = null, mixed $defaultValue = null): mixed {
		if ($value === null || $value === '') {
			return $defaultValue !== null ? $defaultValue : reset($allowedValues);
		}

		if (!in_array($value, $allowedValues, true)) {
			$message = "Value for '{$fieldName}' must be one of: " . implode(', ', array_map('strval', $allowedValues)) . '. Got: ' . self::displayValue($value) . '.';
			$logger->error('forms.validation.invalid_choice', array(
				'field'          => $fieldName,
				'allowed_values' => $allowedValues,
				'provided_value' => $value,
			));

			throw new InvalidArgumentException($message);
		}

		return $value;
	}

	/**
	 * Validate string length constraints and emit warnings when the value is out of range.
	 *
	 * @param string $value Normalised string value.
	 * @param string $fieldName Field identifier for logging.
	 * @param Logger $logger Logger instance.
	 * @param int|null $min Minimum length (inclusive) when set.
	 * @param int|null $max Maximum length (inclusive) when set.
	 * @param callable|null $emitWarning Optional warning emitter.
	 *
	 * @return bool True when the value satisfies the constraints.
	 */
	public static function validateLength(string $value, string $fieldName, Logger $logger, ?int $min = null, ?int $max = null, ?callable $emitWarning = null): bool {
		$length = \strlen($value);

		if ($min !== null && $length < $min) {
			self::emitLengthWarning($fieldName, 'min', $min, $length, $logger, $emitWarning);
			return false;
		}

		if ($max !== null && $length > $max) {
			self::emitLengthWarning($fieldName, 'max', $max, $length, $logger, $emitWarning);
			return false;
		}

		return true;
	}

	/**
	 * Convert a value to a readable form for logging/messages.
	 */
	private static function displayValue(mixed $value): string {
		if (is_scalar($value) || $value === null) {
			return var_export($value, true);
		}

		return is_array($value) ? 'array' : gettype($value);
	}

	private static function logTypeError(string $expectedType, mixed $value, string $fieldName, Logger $logger): void {
		$logger->error('forms.validation.invalid_type', array(
			'expected_type' => $expectedType,
			'actual_type'   => gettype($value),
			'field'         => $fieldName,
		));
	}

	private static function buildTypeErrorMessage(string $expectedType, mixed $value, string $fieldName): string {
		return "Expected {$expectedType} for '{$fieldName}', got " . gettype($value) . '.';
	}

	private static function emitLengthWarning(string $fieldName, string $boundType, int $bound, int $length, Logger $logger, ?callable $emitWarning): void {
		$code    = $boundType === 'min' ? 'forms.validation.string_too_short' : 'forms.validation.string_too_long';
		$message = $boundType === 'min'
			? "Value for '{$fieldName}' must be at least {$bound} characters (got {$length})."
			: "Value for '{$fieldName}' must be at most {$bound} characters (got {$length}).";

		$logger->warning($code, array(
			'field'  => $fieldName,
			'bounds' => array('type' => $boundType, 'value' => $bound),
			'length' => $length,
		));

		if ($emitWarning !== null) {
			$emitWarning($code, $message, array(
				'field'  => $fieldName,
				'bounds' => array('type' => $boundType, 'value' => $bound),
				'length' => $length,
			));
		}
	}
}
