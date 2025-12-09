<?php
/**
 * Radio option component sanitizer.
 *
 * Sanitizes radio option input by validating against the configured option value.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\RadioOption;

use Ran\PluginLib\Forms\Component\Sanitize\SanitizerBase;
use Ran\PluginLib\Forms\Validation\Helpers;

final class Sanitizer extends SanitizerBase {
	/**
	 * Sanitize radio option value.
	 *
	 * Validates that the submitted value matches the configured option value.
	 * Returns the option value if matched, empty string otherwise.
	 *
	 * @param mixed               $value      The submitted value.
	 * @param array<string,mixed> $context    The field context containing 'value' key.
	 * @param callable            $emitNotice Callback to emit sanitization notices.
	 *
	 * @return string The option value if matched, empty string otherwise.
	 */
	protected function _sanitize_component(mixed $value, array $context, callable $emitNotice): mixed {
		if ($value === '' || $value === null) {
			return '';
		}

		if (!is_scalar($value)) {
			return '';
		}

		$optionValue    = Helpers::sanitizeString($context['value'] ?? '', 'option_value', $this->logger);
		$submittedValue = Helpers::sanitizeString($value, 'radio_option_input', $this->logger);

		// Return the option value if it matches, empty string otherwise
		return $submittedValue === $optionValue ? $optionValue : '';
	}
}
