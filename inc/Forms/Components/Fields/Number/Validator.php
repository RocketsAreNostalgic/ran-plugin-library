<?php
/**
 * Number component validator.
 *
 * This component delegates validation to the Input validator, which handles
 * number input types with appropriate validation rules (min, max, step).
 *
 * @see \Ran\PluginLib\Forms\Components\Fields\Input\Validator
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\Number;

use Ran\PluginLib\Forms\Components\Fields\Input\Validator as InputValidator;

/**
 * Number field validator.
 *
 * Extends Input validator to leverage its number validation logic.
 * The Input validator handles:
 * - Required field validation
 * - Numeric value validation
 * - Min/max range validation
 * - Step validation
 */
final class Validator extends InputValidator {
	/**
	 * Validate number input.
	 *
	 * Forces input_type to 'number' to ensure number validation is applied
	 * regardless of what context is passed.
	 *
	 * @param mixed               $value       The submitted value.
	 * @param array<string,mixed> $context     The field context.
	 * @param callable            $emitWarning Callback to emit validation warnings.
	 *
	 * @return bool True if valid, false otherwise.
	 */
	protected function _validate_component(mixed $value, array $context, callable $emitWarning): bool {
		$context['input_type'] = 'number';
		return parent::_validate_component($value, $context, $emitWarning);
	}
}
