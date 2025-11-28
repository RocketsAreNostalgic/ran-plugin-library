<?php
/**
 * Number component sanitizer.
 *
 * This component delegates sanitization to the Input sanitizer, which handles
 * numeric input types (number, range) with coercion and range clamping.
 *
 * @see \Ran\PluginLib\Forms\Components\Fields\Input\Sanitizer::_sanitize_number()
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\Number;

use Ran\PluginLib\Forms\Components\Fields\Input\Sanitizer as InputSanitizer;

/**
 * Number field sanitizer.
 *
 * Extends Input sanitizer to leverage its numeric sanitization logic.
 * The Input sanitizer handles:
 * - Numeric coercion (string to int/float)
 * - Range clamping (min/max enforcement)
 * - Empty/null value handling
 * - Non-numeric value rejection with notice
 */
final class Sanitizer extends InputSanitizer {
	/**
	 * Sanitize number input.
	 *
	 * Forces input_type to 'number' to ensure numeric sanitization is applied
	 * regardless of what context is passed.
	 *
	 * @param mixed               $value      The submitted value.
	 * @param array<string,mixed> $context    The field context.
	 * @param callable            $emitNotice Callback to emit sanitization notices.
	 *
	 * @return mixed The sanitized value.
	 */
	protected function _sanitize_component(mixed $value, array $context, callable $emitNotice): mixed {
		$context['input_type'] = 'number';
		return parent::_sanitize_component($value, $context, $emitNotice);
	}
}
