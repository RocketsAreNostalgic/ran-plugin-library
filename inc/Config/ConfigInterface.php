<?php
/**
 * Unified Config interface for plugins and themes.
 *
 * @package  RanPluginLib
 */

declare(strict_types=1);

namespace Ran\PluginLib\Config;

use Ran\PluginLib\Util\Logger;

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
	 * Whether the current environment should be treated as development.
	 */
	public function is_dev_environment(): bool;

	/**
	 * The environment type backing this configuration (plugin or theme).
	 */
	public function get_type(): ConfigType;
}
