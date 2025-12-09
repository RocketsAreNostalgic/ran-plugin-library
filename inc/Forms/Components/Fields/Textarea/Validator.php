<?php
/**
 * Textarea component validator.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\Textarea;

use Ran\PluginLib\Util\Validate;
use Ran\PluginLib\Forms\Validation\Helpers;
use Ran\PluginLib\Forms\Component\Validate\ValidatorBase;

final class Validator extends ValidatorBase {
	/**
	 * Validate a component-specific value.
	 *
	 * @param mixed $value
	 * @param array $context
	 * @param callable $emitWarning
	 * @return bool
	 */
	protected function _validate_component(mixed $value, array $context, callable $emitWarning): bool {
		if (!is_string($value)) {
			return false;
		}

		Helpers::sanitizeString($value, 'textarea_value', $this->logger);

		// Check if textarea is required when value is empty
		if ($value === '') {
			$required = $context['required'] ?? false;
			if ($required) {
				$emitWarning($this->_translate('This field is required.'));
				return false;
			}
			return true;
		}

		// For textareas, most content is valid - just ensure it's a string
		// Length constraints and other validation would be handled by the normalizer
		// based on maxlength, minlength attributes
		return true;
	}
}
