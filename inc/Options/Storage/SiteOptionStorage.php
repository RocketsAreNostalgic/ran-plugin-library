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
		return (bool) $this->_do_update_option($key, $value);
	}

	/** {@inheritdoc} */
	public function add(string $key, mixed $value, ?bool $autoload = null): bool {
		// Pass nullable autoload through; WP 6.6+ will apply heuristics when null.
		// Autoload is only applicable on creation; updates cannot change it.
		return (bool) $this->_do_add_option($key, $value, '', $autoload);
	}

	/** {@inheritdoc} */
	public function delete(string $key): bool {
		return (bool) $this->_do_delete_option($key);
	}
}

