<?php
/**
 * Textarea component sanitizer.
 *
 * Sanitizes textarea input by normalizing line endings and applying
 * WordPress sanitize_textarea_field() which preserves newlines.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\Textarea;

use Ran\PluginLib\Forms\Component\Sanitize\SanitizerBase;

final class Sanitizer extends SanitizerBase {
	/**
	 * Sanitize textarea value.
	 *
	 * Normalizes line endings to \n and applies WordPress sanitization
	 * that preserves newlines while removing potentially harmful content.
	 *
	 * @param mixed               $value      The submitted value.
	 * @param array<string,mixed> $context    The field context.
	 * @param callable            $emitNotice Callback to emit sanitization notices.
	 *
	 * @return string The sanitized value.
	 */
	protected function _sanitize_component(mixed $value, array $context, callable $emitNotice): mixed {
		if (!is_scalar($value)) {
			return '';
		}

		$stringValue = (string) $value;

		if ($stringValue === '') {
			return '';
		}

		// Normalize line endings to \n (handles \r\n from Windows, \r from old Mac)
		$stringValue = str_replace(array("\r\n", "\r"), "\n", $stringValue);

		// Use WordPress sanitize_textarea_field which preserves newlines
		// but removes tags and encodes special characters
		return sanitize_textarea_field($stringValue);
	}
}
