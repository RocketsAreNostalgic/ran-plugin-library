<?php
/**
 * WritePolicyInterface
 *
 * Non-filterable, immutable policy to gate persistence operations in RegisterOptions.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Options\Policy;
use Ran\PluginLib\Options\WriteContext;

interface WritePolicyInterface {
	/**
	 * Decide whether to allow the given operation.
	 *
	 * @param string $op  Operation name (e.g., 'flush', 'clear', 'delete_option', 'set_option', 'stage_options', 'seed_if_missing', 'migrate')
	 * @param WriteContext $ctx Typed context describing the operation.
	 * @return bool True to allow, false to deny.
	 */
	public function allow(string $op, WriteContext $ctx): bool;
}
