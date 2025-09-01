<?php
/**
 * User scope option storage adapter.
 *
 * @internal
 * @package Ran\PluginLib\Options\Storage
 */

declare(strict_types=1);

namespace Ran\PluginLib\Options\Storage;

use Ran\PluginLib\Options\OptionScope;
use Ran\PluginLib\Util\WPWrappersTrait;

final class UserOptionStorage implements OptionStorageInterface {
	use WPWrappersTrait;

	/** @var int */
	private int $user_id;
	/** @var bool */
	private bool $global;

	public function __construct(int $user_id, bool $global = false) {
		$this->user_id = $user_id;
		$this->global  = $global;
	}

	/** {@inheritdoc} */
	public function scope(): OptionScope {
		return OptionScope::User;
	}

	/** {@inheritdoc} */
	public function blogId(): ?int {
		return null;
	}

	/** {@inheritdoc} */
	public function supports_autoload(): bool {
		return false;
	}

	/** {@inheritdoc} */
	public function read(string $key): mixed {
		return $this->_do_get_user_option($this->user_id, $key);
	}

	/** {@inheritdoc} */
	public function update(string $key, mixed $value, ?bool $autoload = null): bool {
		// Autoload not supported. $this->global controls global vs site-specific user option.
		return (bool) $this->_do_update_user_option($this->user_id, $key, $value, $this->global);
	}

	/** {@inheritdoc} */
	public function add(string $key, mixed $value, ?bool $autoload = null): bool {
		// No native add API; act as upsert via update. Autoload is not supported.
		return $this->update($key, $value, false);
	}

	/** {@inheritdoc} */
	public function delete(string $key): bool {
		return (bool) $this->_do_delete_user_option($this->user_id, $key, $this->global);
	}
}
