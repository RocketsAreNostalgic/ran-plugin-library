<?php
/**
 * Simplified Checkbox validator using ValidatorBase.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\Checkbox;

use Ran\PluginLib\Util\Validate;
use Ran\PluginLib\Forms\Component\Validate\ValidatorBase;

final class SimplifiedValidator extends ValidatorBase {
	protected function _validate_component(mixed $value, array $context, callable $emitWarning): bool {
		$checked   = isset($context['checked_value']) ? (string) $context['checked_value'] : 'on';
		$unchecked = array_key_exists('unchecked_value', $context) ? (string) $context['unchecked_value'] : null;

		// Check if checkbox is required when value is null/empty/unchecked
		$isUnchecked = ($value === null || $value === '' || ($unchecked !== null && $value === $unchecked));
		if ($isUnchecked) {
			$required = $context['required'] ?? false;
			if ($required) {
				$emitWarning($this->_translate('This checkbox is required.'));
				return false;
			}
			return true;
		}

		// Handle boolean values with custom logging
		if (Validate::basic()->is_bool()($value)) {
			if ($value === true) {
				$this->logger->warning('Checkbox boolean true coerced to checked string', array(
					'boolean_value'   => $value,
					'checked_value'   => $checked,
					'validator_class' => static::class
				));
			} elseif ($unchecked !== null) {
				$this->logger->warning('Checkbox boolean false coerced to unchecked string', array(
					'boolean_value'   => $value,
					'unchecked_value' => $unchecked,
					'validator_class' => static::class
				));
			}
			return true;
		}

		// Handle string values
		if (Validate::basic()->is_string()($value)) {
			return $value === $checked || ($unchecked !== null && $value === $unchecked);
		}

		return false;
	}
}
