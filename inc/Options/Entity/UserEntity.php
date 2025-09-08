<?php
/**
 * User entity for Options API.
 *
 * Represents a user-scoped target with optional network-global flag and
 * selection of storage backend.
 *
 * @internal
 * @package Ran\PluginLib\Options\Entity
 */

declare(strict_types=1);

namespace Ran\PluginLib\Options\Entity;

use InvalidArgumentException;
use Ran\PluginLib\Options\OptionScope;

final class UserEntity extends ScopeEntity {
	/**
	 * @param int         $id       User ID (required)
	 * @param bool        $global   Network-wide (multisite) flag. Default false (site-specific)
	 * @param 'meta'|'option' $storage  Storage backend selector. Default 'meta'
	 */
	public function __construct(
		public readonly int $id,
		public readonly bool $global = false,
		public readonly string $storage = 'meta'
	) {
		if ($this->id <= 0) {
			throw new InvalidArgumentException('UserEntity: id must be a positive integer');
		}
		$storageNorm = strtolower($this->storage);
		if ($storageNorm !== 'meta' && $storageNorm !== 'option') {
			throw new InvalidArgumentException("UserEntity: storage must be 'meta' or 'option'");
		}
	}

	public function getScope(): OptionScope {
		return OptionScope::User;
	}

	/**
	 * @return array{user_id:int,user_global:bool,user_storage:'meta'|'option'}
	 */
	public function toStorageArgs(): array {
		return array(
			'user_id'      => $this->id,
			'user_global'  => $this->global,
			'user_storage' => strtolower($this->storage),
		);
	}
}
