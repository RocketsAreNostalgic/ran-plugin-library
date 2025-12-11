<?php
/**
 * Interface for the fluent User builder.
 *
 * @internal
 * @package Ran\PluginLib\Users
 */

declare(strict_types=1);

namespace Ran\PluginLib\Users;

use Ran\PluginLib\Options\Policy\WritePolicyInterface;

interface UserBuilderInterface {
	public function email(string $email): self;
	public function login(string $login): self;
	public function name(string $first, string $last): self;
	public function role(string $role = 'subscriber'): self;
	public function password(string $password): self;
	public function generate_password(): self;
	public function notify(bool $send = true): self;

	public function user_scope(bool $global = false, string $storage = 'meta'): self;
	/** @param array<string,mixed> $kv */
	public function options(array $kv): self;
	/**
	 * Set the validation/sanitization schema for user options.
	 *
	 * @param array|callable $schema Schema array or callable returning schema array.
	 * @return self
	 */
	public function schema(array|callable $schema): self;
	/**
	 * Enable seeding of default values from schema.
	 *
	 * @param bool $seed Whether to seed defaults.
	 * @return self
	 */
	public function seed_defaults(bool $seed = true): self;
	/**
	 * Enable flushing existing options when schema is registered.
	 *
	 * @param bool $flush Whether to flush on schema registration.
	 * @return self
	 */
	public function flush_on_schema(bool $flush = true): self;
	public function with_policy(WritePolicyInterface $policy): self;
	public function on_exists(string $policy): self;

	public function create(): UserResult;
}
