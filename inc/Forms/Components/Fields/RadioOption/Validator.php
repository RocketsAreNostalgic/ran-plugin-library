<?php
/**
 * Radio option component validator.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\RadioOption;

use Ran\PluginLib\Forms\Component\Validate\ValidatorBase;
use Ran\PluginLib\Forms\Validation\Helpers;

final class Validator extends ValidatorBase {
	protected function _validate_component(mixed $value, array $context, callable $emitWarning): bool {
		$optionValue = Helpers::sanitizeString($context['value'] ?? '', 'option_value', $this->logger);
		return $this->_validate_value_in_set($value, array($optionValue));
	}
}
