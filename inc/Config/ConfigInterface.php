<?php
/**
 * Unified Config interface for plugins and themes.
 *
 * @package  RanPluginLib
 */

declare(strict_types=1);

namespace Ran\PluginLib\Config;

use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Options\RegisterOptions;

/**
 * Public contract for configuration objects exposed to library consumers.
 *
 * The config object MUST return a normalized array that uses neutral keys
 * across environments (plugin or theme). See PRD for normalized keys.
 */
interface ConfigInterface {
	/**
	 * Get the normalized configuration array.
	 *
	 * @return array<string,mixed>
	 */
	public function get_config(): array;

	/**
	 * Get the generic WordPress options key for this app.
	 * Prefer the namespaced RAN.AppOption header if present; otherwise, fallback to the normalized Slug.
	 */
	public function get_options_key(): string;

	/**
	 * Get a logger instance configured for this app.
	 */
	public function get_logger(): Logger;

	/**
	 * Accessor: get a pre-wired RegisterOptions instance for this app's options key.
	 *
	 * Semantics (no-write accessor):
	 * - Returns a RegisterOptions instance bound to `get_options_key()` and this Config's logger.
	 * - Recognized args (all optional):
	 *   - `autoload` (bool, default: true) — default autoload policy hint for future writes.
	 *   - `initial` (array<string,mixed>, default: []) — values merged in-memory on the instance.
	 *   - `schema` (array<string,mixed>, default: []) — schema merged in-memory on the instance.
	 * - This method performs no DB writes, seeding, or flushing.
	 * - Unknown args are ignored and a warning is emitted via the configured logger.
	 * - Persistent changes are performed on the returned RegisterOptions instance, e.g. `$opts->add_options([...]); $opts->flush();` or `$opts->register_schema($schema, true, true);`.
	 *
	 * @param array{autoload?: bool, initial?: array<string, mixed>, schema?: array<string, mixed>} $args Recognized args only; unknown keys are ignored with a warning.
	 * @return \Ran\PluginLib\Options\RegisterOptions
	 */
	public function options(array $args = array()): RegisterOptions;

	/**
	 * Whether the current environment should be treated as development.
	 */
	public function is_dev_environment(): bool;

	/**
	 * The environment type backing this configuration (plugin or theme).
	 */
	public function get_type(): ConfigType;
}
