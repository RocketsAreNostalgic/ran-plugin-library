<?php
/**
 * Checkbox option component validator.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\CheckboxOption;

use Ran\PluginLib\Forms\Component\Validate\ValidatorBase;

final class Validator extends ValidatorBase {
	protected function _validate_component(mixed $value, array $context, callable $emitWarning): bool {
		$checked = isset($context['value']) ? (string) $context['value'] : 'on';

		// Handle boolean values with custom messages
		if (is_bool($value)) {
			if ($value === true) {
				$this->logger->warning('Checkbox option value coerced from boolean true to option string', array(
					'boolean_value'   => $value,
					'option_value'    => $checked,
					'validator_class' => static::class
				));
			} else {
				$this->logger->warning('Checkbox option value coerced from boolean false to null', array(
					'boolean_value'   => $value,
					'validator_class' => static::class
				));
			}
			return true;
		}

		// Handle string values
		if (is_string($value)) {
			return $value === $checked;
		}

		return false;
	}
}
