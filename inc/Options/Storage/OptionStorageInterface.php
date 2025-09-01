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
     * @param bool   $autoload Autoload flag (ignored on updates; WP does not change autoload on update).
     * @return bool True on success.
     */
    public function update(string $key, mixed $value, bool $autoload = false): bool;

    /**
     * Add a new option value.
     *
     * @param string   $key      Option name.
     * @param mixed    $value    Initial value.
     * @param bool|null $autoload Autoload flag. When null, defer to WordPress heuristics (WP 6.6+) where supported.
     *                            Where the underlying API supports creation-time autoload (e.g., Site options), a
     *                            boolean true/false will be honored on creation. In other storages (Blog/Network/User),
     *                            autoload is ignored.
     * @return bool True on success.
     */
    public function add(string $key, mixed $value, ?bool $autoload = null): bool;

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
     * Performance note:
     * - Implementations typically delegate to WordPress `wp_load_alloptions()` which returns
     *   all autoloaded options. On large sites this may produce a large array and be relatively heavy.
     * - This API is exposed for callers who explicitly need it; the library does not invoke it implicitly
     *   during normal operations.
     * - Prefer targeted reads via `read()` when specific keys are known; if you use this method, consider
     *   caching at the call site and using it sparingly.
     *
     * @return array|null Associative array of autoloaded options, or null if unsupported.
     */
    public function load_all_autoloaded(): ?array;
}
