<?php
/**
 * Network scope option storage adapter.
 *
 * @internal
 * @package Ran\PluginLib\Options\Storage
 */

declare(strict_types=1);

namespace Ran\PluginLib\Options\Storage;

use Ran\PluginLib\Options\OptionScope;
use Ran\PluginLib\Util\WPWrappersTrait;

final class NetworkOptionStorage implements OptionStorageInterface {
	use WPWrappersTrait;

	/**
	 * {@inheritdoc}
	 */
	public function scope(): OptionScope {
		return OptionScope::Network;
	}

	/**
	 * {@inheritdoc}
	 */
	public function blogId(): ?int {
		return null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function supports_autoload(): bool {
		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function read(string $key): mixed {
		return $this->_do_get_site_option($key);
	}

	/**
	 * {@inheritdoc}
	 */
	public function update(string $key, mixed $value, ?bool $autoload = null): bool {
		// Network options do not support autoload; ignore flag.
		return (bool) $this->_do_update_site_option($key, $value);
	}

	/**
	 * {@inheritdoc}
	 */
	public function add(string $key, mixed $value, ?bool $autoload = null): bool {
		// Network options do not support autoload; ignore flag.
		return (bool) $this->_do_add_site_option($key, $value);
	}

	/**
	 * {@inheritdoc}
	 */
	public function delete(string $key): bool {
		return (bool) $this->_do_delete_site_option($key);
	}

	/**
	 * {@inheritdoc}
	 */
	public function load_all_autoloaded(): ?array {
		// No network-wide autoload map in WP core.
		return null;
	}
}
