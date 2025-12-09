<?php
/**
 * Base validator providing common functionality for all component validators.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Component\Validate;

use Ran\PluginLib\Util\Validate;
use Ran\PluginLib\Util\TranslationService;
use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Forms\Validation\Helpers;
use InvalidArgumentException;

abstract class ValidatorBase implements ValidatorInterface {
	protected Logger $logger;
	protected ?TranslationService $translator = null;

	/**
	 * Provide default manifest defaults for validators that don't override them.
	 *
	 * @return array<string,mixed>
	 */
	public static function manifest_defaults(): array {
		return array();
	}

	public function __construct(?Logger $logger = null, ?TranslationService $translator = null) {
		$this->logger     = $logger ?? new Logger();
		$this->translator = $translator;
	}

	/**
	 * Validate the provided value.
	 * This method handles common validation patterns and delegates to component-specific logic.
	 *
	 * @param mixed $value The value to validate.
	 * @param array $context The context array.
	 * @param callable $emitWarning A function to emit warnings.
	 *
	 * @return bool True if the value is valid, false otherwise.
	 * @throws \Exception If an error occurs during validation.
	 */
	public function validate(mixed $value, array $context, callable $emitWarning): bool {
		// Handle null values (most components allow null)
		if ($this->_allow_null() && Validate::basic()->is_null()($value)) {
			return true;
		}

		// Log boolean coercion as developer warning (not UI warning)
		if (Validate::basic()->is_bool()($value)) {
			$this->_log_boolean_coercion($value, $context);
		}

		// Delegate to component-specific validation
		return $this->_validate_component($value, $context, $emitWarning);
	}

	/**
	 * Component-specific validation logic.
	 * Override this method in child classes.
	 *
	 * @param mixed $value The value to validate.
	 * @param array $context The context array.
	 * @param callable $emitWarning A function to emit warnings.
	 *
	 * @return bool True if the value is valid, false otherwise.
	 */
	abstract protected function _validate_component(mixed $value, array $context, callable $emitWarning): bool;

	/**
	 * Whether this validator allows null values.
	 * Override in child classes if null should not be allowed.
	 *
	 * @return bool True if null values are allowed, false otherwise.
	 */
	protected function _allow_null(): bool {
		return true;
	}

	/**
	 * Log boolean coercion as developer warning.
	 *
	 * @param mixed $value The boolean value that was coerced.
	 * @param array $context The context array.
	 *
	 * @return void
	 */
	protected function _log_boolean_coercion(mixed $value, array $context): void {
		$this->logger->warning('Boolean value coerced during validation', array(
			'boolean_value'   => $value,
			'validator_class' => static::class,
			'context_keys'    => array_keys($context)
		));
	}

	/**
	 * Validate that a value is scalar (string, int, float, bool) or null.
	 *
	 * @param mixed $value The value to validate.
	 *
	 * @return bool True if the value is scalar or null, false otherwise.
	 */
	protected function _validate_scalar_or_null(mixed $value): bool {
		return Validate::basic()->is_scalar()($value) || Validate::basic()->is_null()($value);
	}

	/**
	 * Validate that a value is an array or null.
	 *
	 * @param mixed $value The value to validate.
	 *
	 * @return bool True if the value is an array or null, false otherwise.
	 */
	protected function _validate_array_or_null(mixed $value): bool {
		return Validate::basic()->is_array()($value) || Validate::basic()->is_null()($value);
	}

	/**
	 * Collect allowed values from options array.
	 * Common pattern used by Select, MultiSelect, RadioGroup, CheckboxGroup.
	 *
	 * @param mixed $options The options array.
	 *
	 * @return array The allowed values.
	 */
	protected function _collect_allowed_values(mixed $options): array {
		if (!is_array($options)) {
			return array();
		}

		$allowed = array();
		foreach ($options as $option) {
			if (!is_array($option)) {
				continue;
			}
			$rawValue = $option['value'] ?? '';
			try {
				$allowed[] = Helpers::sanitizeString($rawValue, 'allowed_option_value', $this->logger);
			} catch (InvalidArgumentException $exception) {
				$this->logger->warning('Skipping non-scalar option value during validation', array(
					'option_keys' => array_keys($option),
					'value_type'  => gettype($rawValue),
					'validator'   => static::class,
				));
			}
		}

		return $allowed;
	}

	/**
	 * Validate a single scalar value against allowed options.
	 *
	 * @param mixed $value The value to validate.
	 * @param array $allowedValues The allowed values.
	 * @param callable $emitWarning A callback to emit a warning.
	 *
	 * @return bool True if the value is valid, false otherwise.
	 */
	protected function _validate_scalar_against_options(mixed $value, array $allowedValues, callable $emitWarning): bool {
		if (!Validate::basic()->is_scalar()($value)) {
			return false;
		}

		if (Validate::basic()->is_bool()($value)) {
			$this->logger->warning('Boolean value coerced to string in scalar validation', array(
				'boolean_value'   => $value,
				'validator_class' => static::class,
				'allowed_values'  => $allowedValues
			));
		}

		$stringValue = Helpers::sanitizeString($value, 'submitted_option_value', $this->logger);

		if (empty($allowedValues)) {
			return true;
		}

		if (!in_array($stringValue, $allowedValues, true)) {
			$emitWarning($this->_translate('Please select a valid option.'));
			return false;
		}

		return true;
	}

	/**
	 * Validate an array of values against allowed options.
	 *
	 * @param mixed $value The value to validate.
	 * @param array $allowedValues The allowed values.
	 * @param callable $emitWarning A callback to emit a warning.
	 *
	 * @return bool True if the value is valid, false otherwise.
	 */
	protected function _validate_array_against_options(mixed $value, array $allowedValues, callable $emitWarning): bool {
		if (!Validate::basic()->is_array()($value)) {
			return false;
		}

		foreach ($value as $entry) {
			if (!Validate::basic()->is_scalar()($entry)) {
				return false;
			}

			if (Validate::basic()->is_bool()($entry)) {
				$this->logger->warning('Boolean value coerced to string in array validation', array(
					'boolean_value'   => $entry,
					'validator_class' => static::class,
					'allowed_values'  => $allowedValues
				));
			}

			$entryString = Helpers::sanitizeString($entry, 'submitted_option_value', $this->logger);
			if (!empty($allowedValues) && !in_array($entryString, $allowedValues, true)) {
				$emitWarning($this->_translate('Please select only valid options.'));
				return false;
			}
		}

		return true;
	}

	/**
	 * Validate that a value matches one of the expected values.
	 *
	 * @param mixed $value The value to validate.
	 * @param array $expectedValues The expected values.
	 *
	 * @return bool True if the value matches one of the expected values, false otherwise.
	 */
	protected function _validate_value_in_set(mixed $value, array $expectedValues): bool {
		if (!Validate::basic()->is_scalar()($value)) {
			return false;
		}

		if (Validate::basic()->is_bool()($value)) {
			$this->logger->warning('Boolean value coerced to string when checking expected set', array(
				'boolean_value'   => $value,
				'validator_class' => static::class
			));
		}

		$normalizedExpected = array();
		foreach ($expectedValues as $expected) {
			try {
				$normalizedExpected[] = Helpers::sanitizeString($expected, 'expected_value', $this->logger);
			} catch (InvalidArgumentException $exception) {
				$this->logger->warning('Skipping non-scalar expected value', array(
					'value_type' => gettype($expected),
					'validator'  => static::class,
				));
			}
		}

		if ($normalizedExpected === array()) {
			return false;
		}

		$normalizedValue = Helpers::sanitizeString($value, 'expected_value', $this->logger);
		return in_array($normalizedValue, $normalizedExpected, true);
	}

	/**
	 * Create a translation service for validators.
	 *
	 * @param string $textDomain The WordPress text domain to use.
	 *
	 * @return TranslationService
	 */
	public static function create_translation_service(string $textDomain = 'ran-plugin-lib'): TranslationService {
		return TranslationService::for_domain('forms/validator', $textDomain);
	}

	protected function _get_translator(): TranslationService {
		if ($this->translator === null) {
			$this->translator = self::create_translation_service();
		}
		return $this->translator;
	}

	/**
	 * Translate a message using the translation service.
	 *
	 * @param string $message The message to translate.
	 * @param string $context Optional context for the translation.
	 *
	 * @return string The translated message.
	 */
	protected function _translate(string $message, string $context = ''): string {
		return $this->_get_translator()->translate($message, $context);
	}
}
