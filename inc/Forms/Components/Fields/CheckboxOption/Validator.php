<?php
/**
 * Checkbox option component validator.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\CheckboxOption;

use Ran\PluginLib\Forms\Component\Validate\ValidatorBase;
use Ran\PluginLib\Forms\Validation\Helpers;

final class Validator extends ValidatorBase {
	protected function _validate_component(mixed $value, array $context, callable $emitWarning): bool {
		$checked = Helpers::sanitizeString($context['value'] ?? 'on', 'option_value', $this->logger);

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
			return Helpers::sanitizeString($value, 'checkbox_option_input', $this->logger) === $checked;
		}

		return false;
	}
}
