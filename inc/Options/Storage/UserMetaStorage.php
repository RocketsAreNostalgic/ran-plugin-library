<?php
/**
 * User meta-backed option storage adapter.
 *
 * Stores the single main options array as a user meta value.
 * Autoload is not supported for user meta.
 *
 * @internal
 * @package Ran\PluginLib\Options\Storage
 */

declare(strict_types=1);

namespace Ran\PluginLib\Options\Storage;

use Ran\PluginLib\Options\OptionScope;
use Ran\PluginLib\Util\WPWrappersTrait;

final class UserMetaStorage implements OptionStorageInterface {
	use WPWrappersTrait;

	/** @var int */
	private int $user_id;

	public function __construct(int $user_id) {
		$this->user_id = $user_id;
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
		return $this->_do_get_user_meta($this->user_id, $key, true);
	}

	/** {@inheritdoc} */
	public function update(string $key, mixed $value, bool $autoload = false): bool {
		// Autoload not supported for user meta. update_* will add if missing (acts as upsert).
		return (bool) $this->_do_update_user_meta($this->user_id, $key, $value);
	}

	/** {@inheritdoc} */
	public function add(string $key, mixed $value, ?bool $autoload = null): bool {
		// Keep semantics consistent with UserOptionStorage: upsert via update.
		return $this->update($key, $value, false);
	}

	/** {@inheritdoc} */
	public function delete(string $key): bool {
		return (bool) $this->_do_delete_user_meta($this->user_id, $key);
	}

	/** {@inheritdoc} */
	public function load_all_autoloaded(): ?array {
		return null;
	}
}
