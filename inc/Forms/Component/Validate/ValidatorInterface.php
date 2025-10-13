<?php
/**
 * ValidatorInterface: contract for component-level schema validators.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Component\Validate;

/**
 * Component validators validate a staged option value and may emit warnings.
 */
interface ValidatorInterface {
	/**
	 * Validate the provided value.
	 *
	 * @param mixed $value   Current value slated for persistence.
	 * @param array<string,mixed> $context  Component context metadata.
	 * @param callable(string):void $emitWarning Callback to append warning message.
	 *
	 * @return bool True when value is acceptable. False indicates validation failure.
	 */
	public function validate(mixed $value, array $context, callable $emitWarning): bool;
}
