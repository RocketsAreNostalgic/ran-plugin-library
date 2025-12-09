<?php
/**
 * Radio group component validator.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\RadioGroup;

use Ran\PluginLib\Forms\Component\Validate\ValidatorBase;

final class Validator extends ValidatorBase {
	protected function _validate_component(mixed $value, array $context, callable $emitWarning): bool {
		// Radio groups should not accept arrays
		if (is_array($value)) {
			return false;
		}

		// Check if selection is required when value is null/empty
		if ($value === null || $value === '') {
			$required = $context['required'] ?? false;
			if ($required) {
				$emitWarning($this->_translate('Please select an option.'));
				return false;
			}
			return true;
		}

		$allowedValues = $this->_collect_allowed_values($context['options'] ?? array());
		return $this->_validate_scalar_against_options($value, $allowedValues, $emitWarning);
	}
}
