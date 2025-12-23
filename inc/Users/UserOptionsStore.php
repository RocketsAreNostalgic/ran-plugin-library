<?php
/**
 * Store for per-user plugin options backed by RegisterOptions.
 *
 * @internal
 * @package Ran\PluginLib\Users
 */

declare(strict_types=1);

namespace Ran\PluginLib\Users;

use Ran\PluginLib\Options\Storage\StorageContext;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Options\Policy\WritePolicyInterface;
use Ran\PluginLib\Options\Entity\UserEntity;
use Ran\PluginLib\Config\ConfigInterface;

final class UserOptionsStore implements UserOptionsStoreInterface {
	private ConfigInterface $config;
	private ?RegisterOptions $opts = null;
	private ?UserEntity $entity    = null;

	public function __construct(ConfigInterface $config) {
		$this->config = $config;
	}

	public function for_user(int $userId, bool $global = false, string $storage = 'meta'): self {
		$this->entity = new UserEntity($userId, $global, $storage);
		$this->opts   = null; // re-init lazily
		return $this;
	}

	public function with_policy(WritePolicyInterface $policy): self {
		$this->ensure_opts();
		$this->opts = $this->opts->with_policy($policy);
		return $this;
	}

	public function register_schema(array $schema, bool $seedDefaults = false, bool $flush = false): bool {
		$this->ensure_opts();
		return (bool) $this->opts->register_schema($schema, $seedDefaults, $flush);
	}

	public function get(string $key, mixed $default = null): mixed {
		$this->ensure_opts();
		return $this->opts->get_option($key, $default);
	}

	public function set(string $key, mixed $value): bool {
		$this->ensure_opts();
		return (bool) $this->opts->stage_option($key, $value)->commit_merge();
	}

	/**
	 * Batch set and flush in a single DB write.
	 * @param array<string,mixed> $kv
	 */
	public function set_many(array $kv): bool {
		$this->ensure_opts();
		$this->opts->stage_options($kv);
		return (bool) $this->opts->commit_merge();
	}

	/**
	 * @return array<string,mixed>
	 */
	public function values(): array {
		$this->ensure_opts();
		return $this->opts->get_options();
	}

	private function ensure_opts(): void {
		if ($this->opts instanceof RegisterOptions) {
			return;
		}
		if (!($this->entity instanceof UserEntity)) {
			throw new \InvalidArgumentException('UserOptionsStore: call for_user() before use.');
		}
		// Config::options() already injects the logger via constructor DI
		$this->opts = $this->config->options(
			StorageContext::forUserId($this->entity->id, $this->entity->storage, $this->entity->global)
		);
	}
}
