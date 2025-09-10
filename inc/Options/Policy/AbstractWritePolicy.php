<?php
declare(strict_types=1);

namespace Ran\PluginLib\Options\Policy;

use Ran\PluginLib\Options\WriteContext;
use Ran\PluginLib\Util\WPWrappersTrait;
use Ran\PluginLib\Options\Policy\WritePolicyInterface;

/**
 * AbstractWritePolicy
 *
 * Convenience base for policy implementers. Provides protected helpers for
 * capability checks, current user resolution, scope checks, and key whitelisting.
 *
 * Note: This class is abstract and does not implement allow(); concrete policies
 * must implement the decision.
 */
abstract class AbstractWritePolicy implements WritePolicyInterface {
	use WPWrappersTrait;

	/** Check if current user can manage network options. */
	protected function canManageNetwork(): bool {
		return $this->_do_current_user_can('manage_network_options');
	}

	/** Check if current user can manage site/blog options. */
	protected function canManageOptions(): bool {
		return $this->_do_current_user_can('manage_options');
	}

	/** Check if current user can edit a specific user. */
	protected function canEditUser(int $userId): bool {
		return $this->_do_current_user_can('edit_user', $userId);
	}

	/** True when WriteContext userId matches the current user. */
	protected function isSameUser(WriteContext $wc): bool {
		$target  = (int) ($wc->userId() ?? 0);
		$current = (int) get_current_user_id();
		return $target > 0 && $current > 0 && $target === $current;
	}

	/** Scope equality helper. */
	protected function scopeIs(WriteContext $wc, string $scope): bool {
		return strtolower($wc->scope()) === strtolower($scope);
	}

	/** Scope membership helper. */
	protected function scopeIn(WriteContext $wc, array $scopes): bool {
		$s = strtolower($wc->scope());
		foreach ($scopes as $cand) {
			if ($s === strtolower((string) $cand)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Ensure all keys referenced by the operation are within a whitelist.
	 * - set/add/delete: checks key()
	 * - add_options: checks keys()
	 * - save_all: checks options() keys
	 */
	protected function keysWhitelisted(string $op, WriteContext $wc, array $whitelist): bool {
		$whitelist = array_map('strval', $whitelist);
		switch ($op) {
			case 'set_option':
			case 'add_option':
			case 'delete_option':
				$k = (string) ($wc->key() ?? '');
				return $k !== '' && in_array($k, $whitelist, true);

			case 'add_options':
				$keys = $wc->keys() ?? array();
				if ($keys === array()) {
					return false;
				}
				foreach ($keys as $k) {
					if (!in_array((string) $k, $whitelist, true)) {
						return false;
					}
				}
				return true;

			case 'save_all':
				$opts = $wc->options() ?? array();
				foreach (array_keys($opts) as $k) {
					if (!in_array((string) $k, $whitelist, true)) {
						return false;
					}
				}
				return true;
		}
		// Other ops not covered by whitelist helper
		return false;
	}
}
