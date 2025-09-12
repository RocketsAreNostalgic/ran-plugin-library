<?php

declare(strict_types=1);

namespace Ran\PluginLib\Options\Storage;

use Ran\PluginLib\Options\OptionScope;

/**
 * Internal, typed context for RegisterOptions storage selection.
 *
 * This replaces the fragile, stringly-typed $storage_args array with
 * an immutable value object carrying strongly-typed scope details.
 *
 * NOTE: This class is internal to the options subsystem. It is not part of the
 * public API and may change without notice.
 */
final class StorageContext {
	/** @var OptionScope */
	public readonly OptionScope $scope;

	/** @var int|null */
	public readonly ?int $blog_id;

	/** @var int|null */
	public readonly ?int $user_id;

	/** @var string */
	public readonly string $user_storage; // 'meta' | 'option'

	/** @var bool */
	public readonly bool $user_global;

	private function __construct(
        OptionScope $scope,
        ?int $blog_id = null,
        ?int $user_id = null,
        string $user_storage = 'meta',
        bool $user_global = false
    ) {
		$this->scope        = $scope;
		$this->blog_id      = $blog_id;
		$this->user_id      = $user_id;
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
	 * @param int    $user_id      Positive WP user ID.
	 * @param string $user_storage 'meta' (default) or 'option'.
	 * @param bool   $user_global  When storage is 'option', whether to use network-wide (user_settings) storage.
	 */
	public static function forUser(int $user_id, string $user_storage = 'meta', bool $user_global = false): self {
		if ($user_id <= 0) {
			throw new \InvalidArgumentException('StorageContext::forUser requires a positive user_id.');
		}
		$storage_kind = strtolower($user_storage);
		if ($storage_kind !== 'meta' && $storage_kind !== 'option') {
			throw new \InvalidArgumentException("StorageContext::forUser: user_storage must be 'meta' or 'option'.");
		}
		return new self(OptionScope::User, user_id: $user_id, user_storage: $storage_kind, user_global: $user_global);
	}
}
