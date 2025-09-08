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

final class BlogEntity extends ScopeEntity {
	/**
	 * @param int|null $id Optional explicit blog ID. Null means current blog.
	 */
	public function __construct(public readonly ?int $id = null) {
	}

	public function getScope(): OptionScope {
		return OptionScope::Blog;
	}

	/**
	 * @return array{blog_id?: int|null}
	 */
	public function toStorageArgs(): array {
		// When null, storage factory will use current blog ID.
		return array('blog_id' => $this->id);
	}
}
