<?php
/**
 * Button link component validator.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Elements\ButtonLink;

use Ran\PluginLib\Forms\Component\Validate\ValidatorInterface;

final class Validator implements ValidatorInterface {
	public function validate(mixed $value, array $context, callable $emitWarning): bool {
		// Buttons don't typically have user-submitted values to validate
		// They're action elements, not input elements
		// Accept any value (usually null or empty)
		return true;
	}
}
