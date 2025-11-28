<?php
/**
 * Select component sanitizer.
 *
 * Sanitizes select input by filtering to allowed options defined in context.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\Select;

use Ran\PluginLib\Forms\Component\Sanitize\SanitizerBase;

final class Sanitizer extends SanitizerBase {
	/**
	 * Sanitize select value.
	 *
	 * Filters the submitted value to only allow values defined in the options array.
	 * Invalid selections are cleared and a notice is emitted.
	 *
	 * @param mixed               $value      The submitted value.
	 * @param array<string,mixed> $context    The field context containing options array.
	 * @param callable            $emitNotice Callback to emit sanitization notices.
	 *
	 * @return string The sanitized value (empty string if invalid).
	 */
	protected function _sanitize_component(mixed $value, array $context, callable $emitNotice): mixed {
		// Empty selection is valid for non-required selects
		if ($value === '' || $value === null) {
			return '';
		}

		$allowedValues = $this->_collect_allowed_values($context['options'] ?? array());

		$filtered = $this->_filter_scalar_to_allowed($value, $allowedValues, $emitNotice);

		return $filtered ?? '';
	}
}
