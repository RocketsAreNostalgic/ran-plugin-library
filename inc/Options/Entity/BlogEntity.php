<?php
/**
 * Blog entity for Options API.
 *
 * Represents a blog/site scoped target, with optional explicit blog ID.
 *
 * @internal
 * @package Ran\PluginLib\Options\Entity
 */

declare(strict_types=1);

namespace Ran\PluginLib\Options\Entity;

use Ran\PluginLib\Options\OptionScope;
use Ran\PluginLib\Options\Storage\StorageContext;

final class BlogEntity extends ScopeEntity {
	/**
	 * @param int|null $id Optional explicit blog ID. Null means current blog.
	 */
	public function __construct(public readonly ?int $id = null) {
	}

	public function get_scope(): OptionScope {
		return OptionScope::Blog;
	}

	/**
	 * Typed StorageContext helper (preferred over array args).
	 */
	public function to_storage_context(): StorageContext {
		$blogId = (int) ($this->id ?? 0);
		if ($blogId <= 0) {
			throw new \InvalidArgumentException('BlogEntity: blog_id must be a positive integer for typed context.');
		}
		return StorageContext::forBlog($blogId);
	}
}
