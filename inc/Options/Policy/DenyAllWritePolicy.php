<?php
declare(strict_types=1);

namespace Ran\PluginLib\Options\Policy;

use Ran\PluginLib\Options\Policy\WritePolicyInterface;
use Ran\PluginLib\Options\WriteContext;

/**
 * DenyAllWritePolicy
 *
 * Strict baseline policy that denies all write operations.
 * Extend this class and override allow() to selectively enable cases.
 */
final class DenyAllWritePolicy implements WritePolicyInterface {
	public function allow(string $op, WriteContext $wc): bool {
		return false;
	}
}
