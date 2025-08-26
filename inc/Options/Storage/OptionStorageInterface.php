<?php
/**
 * Internal storage interface for options by scope.
 *
 * @internal
 * @package Ran\PluginLib\Options\Storage
 */

declare(strict_types=1);

namespace Ran\PluginLib\Options\Storage;

use Ran\PluginLib\Options\OptionScope;

/**
 * Interface OptionStorageInterface
 *
 * Internal abstraction over WordPress option APIs for different scopes.
 * Implementations MUST be internal to the library and not part of public API.
 *
 * @internal
 */
interface OptionStorageInterface {
	/**
	 * Get the scope handled by this storage.
	 */
	public function scope(): OptionScope;

	/**
	 * Get the blog ID associated with this storage (if applicable).
	 */
	public function blogId(): ?int;

	/**
	 * Whether this storage supports autoload semantics.
	 */
	public function supports_autoload(): bool;

	/**
	 * Read an option value.
	 *
	 * @param string $key Option name.
	 * @return mixed Value or null if not found (implementation-defined consistency with WP APIs).
	 */
	public function read(string $key): mixed;

	/**
	 * Update an option value.
	 *
	 * @param string $key Option name.
	 * @param mixed  $value New value.
	 * @param bool   $autoload Autoload flag (honored only if supports_autoload() is true).
	 * @return bool True on success.
	 */
	public function update(string $key, mixed $value, bool $autoload = false): bool;

	/**
	 * Add a new option value.
	 *
	 * @param string $key Option name.
	 * @param mixed  $value Initial value.
	 * @param bool   $autoload Autoload flag (honored only if supports_autoload() is true).
	 * @return bool True on success.
	 */
	public function add(string $key, mixed $value, bool $autoload = false): bool;

	/**
	 * Delete an option.
	 *
	 * @param string $key Option name.
	 * @return bool True on success.
	 */
	public function delete(string $key): bool;

	/**
	 * Load all autoloaded options for this scope if supported.
	 *
	 * @return array|null Associative array of autoloaded options, or null if unsupported.
	 */
	public function load_all_autoloaded(): ?array;
}
