<?php
/**
 * Enum for specifying config types.
 *
 * @package Ran\PluginLib\Config
 */

declare(strict_types=1);

namespace Ran\PluginLib\Config;

/**
 * Enum ConfigType
 *
 * Provides a type-safe way to specify config types throughout the library.
 */
enum ConfigType: string {
	/**
	 * Represents a theme.
	 */
	case Theme = 'theme';

	/**
	 * Represents a plugin.
	 */
	case Plugin = 'plugin';
}
