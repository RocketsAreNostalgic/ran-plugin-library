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
	 *   - `scope` ('site'|'network'|'blog'|'user' or OptionScope enum), default: 'site'.
	 *   - `entity` (ScopeEntity|null) — used when relevant for `blog` and `user` scopes.
	 * - This method performs no DB writes, seeding, or flushing.
	 * - Unknown args are ignored and a warning is emitted via the configured logger.
	 * - Persistent changes are performed on the returned RegisterOptions instance using its fluent API,
	 *   e.g. `$opts->add_options([...])->flush();`, `$opts->with_schema($schema, true, true);`, `$opts->with_policy($policy);`.
	 *
	 * @param array{autoload?: bool, scope?: string|\Ran\PluginLib\Options\OptionScope, entity?: \Ran\PluginLib\Options\Entity\ScopeEntity|null} $args
	 *        Recognized args only; unknown keys are ignored with a warning.
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
