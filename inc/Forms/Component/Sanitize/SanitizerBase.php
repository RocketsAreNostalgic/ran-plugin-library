<?php
/**
 * Base sanitizer providing common functionality for all component sanitizers.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Component\Sanitize;

use Ran\PluginLib\Util\Validate;
use Ran\PluginLib\Util\TranslationService;
use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Forms\Validation\Helpers;
use InvalidArgumentException;

abstract class SanitizerBase implements SanitizerInterface {
	protected Logger $logger;
	protected ?TranslationService $translator = null;

	/**
	 * Provide default manifest defaults for sanitizers that don't override them.
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
	 * Sanitize the provided value.
	 * This method handles common patterns and delegates to component-specific logic.
	 *
	 * @param mixed $value The value to sanitize.
	 * @param array<string,mixed> $context The context array.
	 * @param callable $emitNotice A function to emit notices.
	 *
	 * @return mixed The sanitized value.
	 */
	public function sanitize(mixed $value, array $context, callable $emitNotice): mixed {
		// Handle null values (most components allow null)
		if ($this->_allow_null() && Validate::basic()->is_null()($value)) {
			return null;
		}

		// Delegate to component-specific sanitization
		return $this->_sanitize_component($value, $context, $emitNotice);
	}

	/**
	 * Component-specific sanitization logic.
	 * Override this method in child classes.
	 *
	 * @param mixed $value The value to sanitize.
	 * @param array<string,mixed> $context The context array.
	 * @param callable $emitNotice A function to emit notices.
	 *
	 * @return mixed The sanitized value.
	 */
	abstract protected function _sanitize_component(mixed $value, array $context, callable $emitNotice): mixed;

	/**
	 * Whether this sanitizer allows null values.
	 * Override in child classes if null should not be allowed.
	 *
	 * @return bool True if null values are allowed.
	 */
	protected function _allow_null(): bool {
		return true;
	}

	/**
	 * Collect allowed values from options array.
	 * Common pattern used by Select, MultiSelect, RadioGroup, CheckboxGroup.
	 *
	 * @param mixed $options The options array.
	 *
	 * @return array<int,string> The allowed values.
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
				$this->logger->warning('Skipping non-scalar option value during sanitization', array(
					'option_keys' => array_keys($option),
					'value_type'  => gettype($rawValue),
					'sanitizer'   => static::class,
				));
			}
		}

		return $allowed;
	}

	/**
	 * Filter array values to only allowed options.
	 *
	 * @param array<int,mixed> $values The values to filter.
	 * @param array<int,string> $allowedValues The allowed values.
	 * @param callable $emitNotice A callback to emit a notice.
	 *
	 * @return array<int,string> Filtered values.
	 */
	protected function _filter_array_to_allowed(array $values, array $allowedValues, callable $emitNotice): array {
		if (empty($allowedValues)) {
			// No allowed values defined; sanitize each entry as string
			$sanitized = array();
			foreach ($values as $entry) {
				if (!Validate::basic()->is_scalar()($entry)) {
					continue;
				}
				$sanitized[] = Helpers::sanitizeString($entry, 'submitted_option_value', $this->logger);
			}
			return $sanitized;
		}

		$filtered = array();
		$removed  = array();

		foreach ($values as $entry) {
			if (!Validate::basic()->is_scalar()($entry)) {
				continue;
			}
			$entryString = Helpers::sanitizeString($entry, 'submitted_option_value', $this->logger);
			if (in_array($entryString, $allowedValues, true)) {
				$filtered[] = $entryString;
			} else {
				$removed[] = $entryString;
			}
		}

		if (!empty($removed)) {
			$emitNotice($this->_translate('Some invalid selections were removed.'));
			$this->logger->debug('SanitizerBase: Removed invalid selections', array(
				'removed'       => $removed,
				'allowed_count' => count($allowedValues),
				'sanitizer'     => static::class,
			));
		}

		return $filtered;
	}

	/**
	 * Filter scalar value to allowed options.
	 *
	 * @param mixed $value The value to filter.
	 * @param array<int,string> $allowedValues The allowed values.
	 * @param callable $emitNotice A callback to emit a notice.
	 *
	 * @return string|null Filtered value or null if not allowed.
	 */
	protected function _filter_scalar_to_allowed(mixed $value, array $allowedValues, callable $emitNotice): ?string {
		if (!Validate::basic()->is_scalar()($value)) {
			return null;
		}

		$stringValue = Helpers::sanitizeString($value, 'submitted_option_value', $this->logger);

		if (empty($allowedValues)) {
			return $stringValue;
		}

		if (!in_array($stringValue, $allowedValues, true)) {
			$emitNotice($this->_translate('Invalid selection was cleared.'));
			$this->logger->debug('SanitizerBase: Cleared invalid selection', array(
				'value'         => $stringValue,
				'allowed_count' => count($allowedValues),
				'sanitizer'     => static::class,
			));
			return null;
		}

		return $stringValue;
	}

	/**
	 * Coerce value to array.
	 * Useful for multi-select and checkbox-group components.
	 *
	 * @param mixed $value The value to coerce.
	 *
	 * @return array<int,mixed> The coerced array.
	 */
	protected function _coerce_to_array(mixed $value): array {
		if (is_array($value)) {
			return array_values($value);
		}

		if ($value === null || $value === '' || $value === false) {
			return array();
		}

		// Single scalar value becomes single-element array
		if (Validate::basic()->is_scalar()($value)) {
			return array($value);
		}

		return array();
	}

	/**
	 * Coerce value to boolean.
	 * Useful for checkbox components.
	 *
	 * @param mixed $value The value to coerce.
	 *
	 * @return bool The coerced boolean.
	 */
	protected function _coerce_to_bool(mixed $value): bool {
		if (is_bool($value)) {
			return $value;
		}

		if ($value === null || $value === '' || $value === '0' || $value === 0) {
			return false;
		}

		if ($value === '1' || $value === 1 || $value === 'on' || $value === 'yes' || $value === 'true') {
			return true;
		}

		return (bool) $value;
	}

	/**
	 * Create a translation service for sanitizers.
	 *
	 * @param string $textDomain The WordPress text domain to use.
	 *
	 * @return TranslationService
	 */
	public static function create_translation_service(string $textDomain = 'ran-plugin-lib'): TranslationService {
		return TranslationService::for_domain('forms/sanitizer', $textDomain);
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
