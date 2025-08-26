<?php
/**
 * Site scope option storage adapter.
 *
 * @internal
 * @package Ran\PluginLib\Options\Storage
 */

declare(strict_types=1);

namespace Ran\PluginLib\Options\Storage;

use Ran\PluginLib\Options\OptionScope;
use Ran\PluginLib\Util\WPWrappersTrait;

final class SiteOptionStorage implements OptionStorageInterface {
	use WPWrappersTrait;
	/** {@inheritdoc} */
	public function scope(): OptionScope {
		return OptionScope::Site;
	}

	/** {@inheritdoc} */
	public function blogId(): ?int {
		return null;
	}

	/** {@inheritdoc} */
	public function supports_autoload(): bool {
		return true;
	}

	/** {@inheritdoc} */
	public function read(string $key): mixed {
		return $this->_do_get_option($key);
	}

	/** {@inheritdoc} */
	public function update(string $key, mixed $value, bool $autoload = false): bool {
		// WordPress accepts 'yes'/'no' for autoload when creating; updates typically don't change autoload.
		// We pass the flag through for consistency; implementations may ignore it.
		return (bool) $this->_do_update_option($key, $value, $autoload ? 'yes' : 'no');
	}

	/** {@inheritdoc} */
	public function add(string $key, mixed $value, bool $autoload = false): bool {
		return (bool) $this->_do_add_option($key, $value, '', $autoload ? 'yes' : 'no');
	}

	/** {@inheritdoc} */
	public function delete(string $key): bool {
		return (bool) $this->_do_delete_option($key);
	}

	/** {@inheritdoc} */
	public function load_all_autoloaded(): ?array {
		return $this->_do_wp_load_alloptions();
	}
}
