<?php
/**
 * Text component validator.
 *
 * This component delegates validation to the Input validator, which handles
 * text input types with appropriate validation rules.
 *
 * @see \Ran\PluginLib\Forms\Components\Fields\Input\Validator
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\Text;

use Ran\PluginLib\Forms\Components\Fields\Input\Validator as InputValidator;

/**
 * Text field validator.
 *
 * Extends Input validator to leverage its text validation logic.
 * The Input validator handles:
 * - Required field validation
 * - Max length validation
 * - Pattern matching
 */
final class Validator extends InputValidator {
	/**
	 * Validate text input.
	 *
	 * Forces input_type to 'text' to ensure text validation is applied
	 * regardless of what context is passed.
	 *
	 * @param mixed               $value       The submitted value.
	 * @param array<string,mixed> $context     The field context.
	 * @param callable            $emitWarning Callback to emit validation warnings.
	 *
	 * @return bool True if valid, false otherwise.
	 */
	protected function _validate_component(mixed $value, array $context, callable $emitWarning): bool {
		$context['input_type'] = 'text';
		return parent::_validate_component($value, $context, $emitWarning);
	}
}
