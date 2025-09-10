<?php
/**
 * Scope entity base for Options API.
 *
 * Encapsulates scope-specific identifiers and flags for Options storage.
 *
 * @internal
 * @package Ran\PluginLib\Options\Entity
 */

declare(strict_types=1);

namespace Ran\PluginLib\Options\Entity;

use Ran\PluginLib\Options\OptionScope;

/**
 * Abstract base for scope entities.
 */
abstract class ScopeEntity {
	/**
	 * Return the OptionScope represented by this entity.
	 */
	abstract public function getScope(): OptionScope;

	/**
	 * Convert entity to storage args array consumed by RegisterOptions::_make_storage().
	 *
	 * @return array<string, mixed>
	 */
	abstract public function toStorageArgs(): array;
}
