<?php
/**
 * WritePolicyInterface
 *
 * Non-filterable, immutable policy to gate persistence operations in RegisterOptions.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Options;

interface WritePolicyInterface {
	/**
	 * Decide whether to allow the given operation.
	 *
	 * @param string $op  Operation name (e.g., 'flush', 'clear', 'delete_option', 'set_option', 'add_options', 'seed_if_missing', 'migrate')
	 * @param array  $ctx Context map describing the operation (scope, main_option, keys, user_id, blog_id, etc.)
	 * @return bool True to allow, false to deny.
	 */
	public function allow(string $op, array $ctx): bool;
}
