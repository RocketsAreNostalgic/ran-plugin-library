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
	 * Semantics:
	 * - Returns a RegisterOptions instance bound to `get_options_key()` and this Config's logger.
	 * - If a schema is provided, it will be registered on the instance only.
	 * - This method should not perform any DB writes, seeding, or flushing.
	 * - Any persistent changes should be made through the RegisterOptions instance eg `$opts->register_schema($schema, true, true);`.
	 *
	 * @param array{autoload?: bool, schema?: array<string, mixed>} $args
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
