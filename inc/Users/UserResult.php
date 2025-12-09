<?php
/**
 * Value object for results of User builder create().
 *
 * @internal
 * @package Ran\PluginLib\Users
 */

declare(strict_types=1);

namespace Ran\PluginLib\Users;

final class UserResult {
	/** @param array<int,string> $messages */
	public function __construct(
		public readonly int $id,
		public readonly string $email,
		public readonly string $login,
		public readonly bool $created,
		public readonly array $messages = array()
	) {
	}
}
