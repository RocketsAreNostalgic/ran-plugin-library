<?php
/**
 * Enum for specifying asset types.
 *
 * @package Ran\PluginLib\EnqueueAccessory
 */

declare(strict_types=1);

namespace Ran\PluginLib\EnqueueAccessory;

/**
 * Enum AssetType
 *
 * Provides a type-safe way to specify asset types throughout the enqueue system.
 */
enum AssetType: string {
	/**
	 * Represents a JavaScript asset.
	 */
	case Script = 'script';

	/**
	 * Represents a CSS stylesheet asset.
	 */
	case Style = 'style';
}
