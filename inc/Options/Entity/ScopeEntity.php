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
	abstract public function get_scope(): OptionScope;
}
