<?php
/**
 * Post meta-backed option storage adapter.
 *
 * Stores the single main options array as a post meta value.
 * Autoload is not supported for post meta.
 *
 * @internal
 * @package Ran\PluginLib\Options\Storage
 */

declare(strict_types=1);

namespace Ran\PluginLib\Options\Storage;

use Ran\PluginLib\Util\WPWrappersTrait;
use Ran\PluginLib\Options\OptionScope;

final class PostMetaStorage implements OptionStorageInterface {
	use WPWrappersTrait;

	/** @var int */
	private int $post_id;

	public function __construct(int $post_id) {
		$this->post_id = $post_id;
	}

	/** {@inheritdoc} */
	public function scope(): OptionScope {
		return OptionScope::Post;
	}

	/** {@inheritdoc} */
	public function blog_id(): ?int {
		return null;
	}

	/** {@inheritdoc} */
	public function supports_autoload(): bool {
		return false;
	}

	/** {@inheritdoc} */
	public function read(string $key): mixed {
		return $this->_do_get_post_meta($this->post_id, $key, true);
	}

	/** {@inheritdoc} */
	public function update(string $key, mixed $value, bool $autoload = false): bool {
		// Autoload not supported for post meta. update_* will add if missing (acts as upsert).
		return (bool) $this->_do_update_post_meta($this->post_id, $key, $value);
	}

	/** {@inheritdoc} */
	public function add(string $key, mixed $value, ?bool $autoload = null): bool {
		// Keep semantics consistent with UserMetaStorage: upsert via update.
		return $this->update($key, $value, false);
	}

	/** {@inheritdoc} */
	public function delete(string $key): bool {
		return (bool) $this->_do_delete_post_meta($this->post_id, $key);
	}
}
