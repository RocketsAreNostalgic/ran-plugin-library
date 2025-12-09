<?php
/**
 * SanitizerInterface: contract for component-level schema sanitizers.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Component\Sanitize;

/**
 * Component sanitizers transform/clean submitted values before storage.
 */
interface SanitizerInterface {
	/**
	 * Sanitize the provided value.
	 *
	 * @param mixed $value Raw submitted value.
	 * @param array<string,mixed> $context Component context metadata.
	 * @param callable(string):void $emitNotice Callback to append notice message.
	 *
	 * @return mixed Sanitized value ready for storage.
	 */
	public function sanitize(mixed $value, array $context, callable $emitNotice): mixed;
}
