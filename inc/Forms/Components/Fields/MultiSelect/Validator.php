<?php
/**
 * MultiSelect validator using ValidatorBase.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\MultiSelect;

use Ran\PluginLib\Forms\Component\Validate\ValidatorBase;

final class Validator extends ValidatorBase {
	protected function _validate_component(mixed $value, array $context, callable $emitWarning): bool {
		// Check if selection is required when value is null/empty/no selections
		if ($value === null || $value === '' || (is_array($value) && empty($value))) {
			$required = $context['required'] ?? false;
			if ($required) {
				$emitWarning($this->_translate('Please select at least one option.'));
				return false;
			}
			return true;
		}

		$allowedValues = $this->_collect_allowed_values($context['options'] ?? array());
		return $this->_validate_array_against_options($value, $allowedValues, $emitWarning);
	}

	protected function _log_boolean_coercion(mixed $value, array $context): void {
		$this->logger->warning('Boolean value coerced in multi-select validation', array(
			'boolean_value'   => $value,
			'validator_class' => static::class,
			'context_keys'    => array_keys($context)
		));
	}
}
