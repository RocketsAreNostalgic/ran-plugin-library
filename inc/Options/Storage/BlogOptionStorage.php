<?php
/**
 * Blog scope option storage adapter.
 *
 * @internal
 * @package Ran\PluginLib\Options\Storage
 */

declare(strict_types=1);

namespace Ran\PluginLib\Options\Storage;

use Ran\PluginLib\Options\OptionScope;
use Ran\PluginLib\Util\WPWrappersTrait;

final class BlogOptionStorage implements OptionStorageInterface {
	use WPWrappersTrait;

	/** @var int */
	private int $blog_id;

	public function __construct(int $blog_id) {
		$this->blog_id = $blog_id;
	}

	/**
	 * {@inheritdoc}
	 */
	public function scope(): OptionScope {
		return OptionScope::Blog;
	}

	/**
	 * {@inheritdoc}
	 */
	public function blogId(): ?int {
		return $this->blog_id;
	}

	/**
	 * {@inheritdoc}
	 */
	public function supports_autoload(): bool {
		// Autoload applies only when targeting the current runtime blog.
		return $this->blog_id === $this->_do_get_current_blog_id();
	}

	/**
	 * {@inheritdoc}
	 */
	public function read(string $key): mixed {
		return $this->_do_get_blog_option($this->blog_id, $key);
	}

	/**
	 * {@inheritdoc}
	 */
	public function update(string $key, mixed $value, bool $autoload = false): bool {
		// Blog options do not support autoload; ignore flag.
		return (bool) $this->_do_update_blog_option($this->blog_id, $key, $value);
	}

	/**
	 * {@inheritdoc}
	 */
	public function add(string $key, mixed $value, ?bool $autoload = null): bool {
		// Blog options do not support autoload; ignore flag.
		return (bool) $this->_do_add_blog_option($this->blog_id, $key, $value);
	}

	/**
	 * {@inheritdoc}
	 */
	public function delete(string $key): bool {
		return (bool) $this->_do_delete_blog_option($this->blog_id, $key);
	}

	/**
	 * {@inheritdoc}
	 */
	public function load_all_autoloaded(): ?array {
		// Only meaningful for the current blog; otherwise unsupported.
		if ($this->supports_autoload()) {
			return $this->_do_wp_load_alloptions(false);
		}
		return null;
	}
}
