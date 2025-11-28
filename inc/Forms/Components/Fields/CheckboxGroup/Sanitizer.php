<?php
/**
 * Checkbox group component sanitizer.
 *
 * Sanitizes checkbox group input by coercing to array and filtering
 * to allowed options defined in context.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\CheckboxGroup;

use Ran\PluginLib\Forms\Component\Sanitize\SanitizerBase;

final class Sanitizer extends SanitizerBase {
	/**
	 * Sanitize checkbox group value.
	 *
	 * Coerces the submitted value to an array and filters to only
	 * values defined in the options array. Invalid selections are
	 * removed and a notice is emitted.
	 *
	 * @param mixed               $value      The submitted value (array or scalar).
	 * @param array<string,mixed> $context    The field context containing options array.
	 * @param callable            $emitNotice Callback to emit sanitization notices.
	 *
	 * @return array<int,string> The sanitized array of selected values.
	 */
	protected function _sanitize_component(mixed $value, array $context, callable $emitNotice): mixed {
		$values = $this->_coerce_to_array($value);

		if (empty($values)) {
			return array();
		}

		$allowedValues = $this->_collect_allowed_values($context['options'] ?? array());

		return $this->_filter_array_to_allowed($values, $allowedValues, $emitNotice);
	}
}
