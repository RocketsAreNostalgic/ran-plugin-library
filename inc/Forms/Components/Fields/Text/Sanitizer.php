<?php
/**
 * Text component sanitizer.
 *
 * This component delegates sanitization to the Input sanitizer, which handles
 * text input types with WordPress's sanitize_text_field().
 *
 * @see \Ran\PluginLib\Forms\Components\Fields\Input\Sanitizer::_sanitize_text()
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\Text;

use Ran\PluginLib\Forms\Components\Fields\Input\Sanitizer as InputSanitizer;

/**
 * Text field sanitizer.
 *
 * Extends Input sanitizer to leverage its text sanitization logic.
 * The Input sanitizer handles:
 * - HTML tag stripping via sanitize_text_field()
 * - Whitespace normalization
 * - Non-scalar value rejection
 */
final class Sanitizer extends InputSanitizer {
	/**
	 * Sanitize text input.
	 *
	 * Forces input_type to 'text' to ensure text sanitization is applied
	 * regardless of what context is passed.
	 *
	 * @param mixed               $value      The submitted value.
	 * @param array<string,mixed> $context    The field context.
	 * @param callable            $emitNotice Callback to emit sanitization notices.
	 *
	 * @return mixed The sanitized value.
	 */
	protected function _sanitize_component(mixed $value, array $context, callable $emitNotice): mixed {
		$context['input_type'] = 'text';
		return parent::_sanitize_component($value, $context, $emitNotice);
	}
}
