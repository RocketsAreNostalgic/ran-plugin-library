<?php
/**
 * Inline link component validator.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Elements\InlineLink;

use Ran\PluginLib\Forms\Component\Validate\ValidatorBase;
use Ran\PluginLib\Util\Validate;

final class Validator extends ValidatorBase {
	protected function _validate_component(mixed $value, array $context, callable $emitWarning): bool {
		// Inline links don't typically have user-submitted values to validate
		// They're display/navigation elements, not input elements
		// Accept any value (usually null or empty)
		return true;
	}

	protected function _allow_null(): bool {
		// Inline links typically don't have values
		return true;
	}
}
