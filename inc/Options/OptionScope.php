<?php
/**
 * Enum for specifying option scope types.
 *
 * @internal
 * @package Ran\PluginLib\Options
 */

declare(strict_types=1);

namespace Ran\PluginLib\Options;

/**
 * Enum OptionScope
 *
 * Provides a type-safe way to specify option scope types.
 */
enum OptionScope: string {
	/**
	 * Represents a site-level option.
	 */
	case Site = 'site';

	/**
	 * Represents a blog-level option.
	 */
	case Blog = 'blog';

	/**
	 * Represents a network-level option.
	 */
	case Network = 'network';

	/**
	 * Represents a user-level option.
	 */
	case User = 'user';

	/**
	 * Represents a post-level option.
	 */
	case Post = 'post';
}
