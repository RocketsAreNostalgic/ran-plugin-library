<?php
/**
 * Radio option component validator.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\RadioOption;

use Ran\PluginLib\Forms\Component\Validate\ValidatorBase;

final class Validator extends ValidatorBase {
	protected function _validate_component(mixed $value, array $context, callable $emitWarning): bool {
		$optionValue = isset($context['value']) ? (string) $context['value'] : '';
		return $this->_validate_value_in_set($value, array($optionValue));
	}
}
