<?php

declare(strict_types=1);

namespace Ran\PluginLib\Options\Storage;

use Ran\PluginLib\Options\OptionScope;

/**
 * Internal, typed context for RegisterOptions storage selection.
 * A strongly-typed value object carrying Options scope details.
 */
final class StorageContext {
	/** @var OptionScope */
	public readonly OptionScope $scope;

	/** @var int|null */
	public readonly ?int $blog_id;

	/** @var int|null */
	public readonly ?int $user_id;

	/** @var int|null */
	public readonly ?int $post_id;

	/** @var string */
	public readonly string $user_storage; // 'meta' | 'option'

	/** @var bool */
	public readonly bool $user_global;

	private function __construct(
        OptionScope $scope,
        ?int $blog_id = null,
        ?int $user_id = null,
		?int $post_id = null,
        string $user_storage = 'meta',
        bool $user_global = false
    ) {
		$this->scope        = $scope;
		$this->blog_id      = $blog_id;
		$this->user_id      = $user_id;
		$this->post_id      = $post_id;
		$this->user_storage = $user_storage;
		$this->user_global  = $user_global;
	}

	public static function forSite(): self {
		return new self(OptionScope::Site);
	}

	public static function forNetwork(): self {
		return new self(OptionScope::Network);
	}

	public static function forBlog(int $blog_id): self {
		if ($blog_id <= 0) {
			throw new \InvalidArgumentException('StorageContext::forBlog requires a positive blog_id.');
		}
		return new self(OptionScope::Blog, blog_id: $blog_id);
	}

	/**
	 * Create a user-scoped context with deferred user_id resolution.
	 *
	 * Use this when registering user settings at plugin load time, before
	 * the target user is known. The actual user_id will be resolved at
	 * render/save time from WordPress profile hooks.
	 *
	 * This is the recommended default for UserSettings registration.
	 *
	 * @param string $user_storage 'meta' (default) or 'option'.
	 * @param bool   $user_global  When storage is 'option', whether to use network-wide storage.
	 * @return self
	 */
	public static function forUser(string $user_storage = 'meta', bool $user_global = false): self {
		$storage_kind = strtolower($user_storage);
		if ($storage_kind !== 'meta' && $storage_kind !== 'option') {
			throw new \InvalidArgumentException("StorageContext::forUser: user_storage must be 'meta' or 'option'.");
		}
		// user_id = null signals deferred resolution
		return new self(OptionScope::User, user_id: null, user_storage: $storage_kind, user_global: $user_global);
	}

	/**
	 * Create a user-scoped context for a specific user ID.
	 *
	 * Use this for programmatic access when you need a specific user's data
	 * outside of the profile page context (REST API, AJAX, WP-CLI, cron, etc.).
	 *
	 * @param int    $user_id      Positive WP user ID.
	 * @param string $user_storage 'meta' (default) or 'option'.
	 * @param bool   $user_global  When storage is 'option', whether to use network-wide (user_settings) storage.
	 * @return self
	 */
	public static function forUserId(int $user_id, string $user_storage = 'meta', bool $user_global = false): self {
		if ($user_id <= 0) {
			throw new \InvalidArgumentException('StorageContext::forUserId requires a positive user_id.');
		}
		$storage_kind = strtolower($user_storage);
		if ($storage_kind !== 'meta' && $storage_kind !== 'option') {
			throw new \InvalidArgumentException("StorageContext::forUserId: user_storage must be 'meta' or 'option'.");
		}
		return new self(OptionScope::User, user_id: $user_id, user_storage: $storage_kind, user_global: $user_global);
	}

	public static function forPost(int $post_id): self {
		if ($post_id <= 0) {
			throw new \InvalidArgumentException('StorageContext::forPost requires a positive post_id.');
		}
		return new self(OptionScope::Post, post_id: $post_id);
	}

	/**
	 * Generate a unique cache key for this storage context.
	 *
	 * @return string A string key uniquely identifying this context.
	 */
	public function get_cache_key(): string {
		$parts = array($this->scope->value);

		if ($this->blog_id !== null) {
			$parts[] = 'blog:' . $this->blog_id;
		}
		if ($this->user_id !== null) {
			$parts[] = 'user:' . $this->user_id;
		}
		if ($this->post_id !== null) {
			$parts[] = 'post:' . $this->post_id;
		}
		if ($this->user_storage !== 'meta') {
			$parts[] = 'storage:' . $this->user_storage;
		}
		if ($this->user_global) {
			$parts[] = 'global';
		}

		return implode('|', $parts);
	}
}
