<?php
/**
 * Radio group component sanitizer.
 *
 * Sanitizes radio group input by filtering to allowed options defined in context.
 * Functionally identical to Select sanitizer.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\RadioGroup;

use Ran\PluginLib\Forms\Component\Sanitize\SanitizerBase;

final class Sanitizer extends SanitizerBase {
	/**
	 * Sanitize radio group value.
	 *
	 * Filters the submitted value to only allow values defined in the options array.
	 * Invalid selections are cleared and a notice is emitted. Arrays are rejected
	 * since radio groups only allow single selection.
	 *
	 * @param mixed               $value      The submitted value.
	 * @param array<string,mixed> $context    The field context containing options array.
	 * @param callable            $emitNotice Callback to emit sanitization notices.
	 *
	 * @return string The sanitized value (empty string if invalid).
	 */
	protected function _sanitize_component(mixed $value, array $context, callable $emitNotice): mixed {
		// Radio groups should not accept arrays
		if (is_array($value)) {
			$emitNotice($this->_translate('Multiple selections not allowed.'));
			return '';
		}

		// Empty selection is valid for non-required radio groups
		if ($value === '' || $value === null) {
			return '';
		}

		$allowedValues = $this->_collect_allowed_values($context['options'] ?? array());

		$filtered = $this->_filter_scalar_to_allowed($value, $allowedValues, $emitNotice);

		return $filtered ?? '';
	}
}
