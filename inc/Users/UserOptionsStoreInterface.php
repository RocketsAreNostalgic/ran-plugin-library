<?php
/**
 * Interface for per-user plugin options store.
 *
 * @internal
 * @package Ran\PluginLib\Users
 */

declare(strict_types=1);

namespace Ran\PluginLib\Users;

use Ran\PluginLib\Options\Policy\WritePolicyInterface;

interface UserOptionsStoreInterface {
	public function for_user(int $user_id, bool $global = false, string $storage = 'meta'): self;
	public function with_policy(WritePolicyInterface $policy): self;
	public function register_schema(array $schema, bool $seed_defaults = false, bool $flush = false): bool;
	public function get(string $key, mixed $default = null): mixed;
	public function set(string $key, mixed $value): bool;
	/** @param array<string,mixed> $kv */
	public function set_many(array $kv): bool;
	/** @return array<string,mixed> */
	public function values(): array;
}
