<?php
declare(strict_types=1);

namespace Ran\PluginLib\Options\Policy;

use Ran\PluginLib\Options\WriteContext;
use Ran\PluginLib\Options\Policy\AbstractWritePolicy;
use Ran\PluginLib\Options\Policy\WritePolicyInterface;

/**
 * ExampleUserSelfServiceWhitelistPolicy
 *
 * Minimal example policy for a "subscriber"-like role:
 * - Allows writes only in user scope
 * - Only for the current user (same userId, eg 'self-service')
 * - Only for a small whitelist of keys (safe self-managed fields)
 * - Denies all other scopes/keys/operations
 *
 * This class is intended as an example/template and should be adapted
 * to real product requirements (keys, scope rules, etc.).
 */
final class ExampleUserSelfServiceWhitelistPolicy extends AbstractWritePolicy implements WritePolicyInterface {
	/**
	 * Whitelisted keys that a subscriber may self-manage.
	 * Adjust to your product's needs.
	 *
	 * @var array<int,string>
	 */
	private array $whitelist_keys = array(
	    'profile_bio',
	    'preferences',
	    'newsletter_opt_in',
	);

	public function allow(string $op, WriteContext $wc): bool {
		// Only user scope is permitted for a subscriber.
		if (!$this->scopeIs($wc, 'user')) {
			return false;
		}

		// Must match current user (self-service only).
		if (!$this->isSameUser($wc)) {
			return false;
		}

		// Operation-specific checks (nested conditionals)
		if ($op === 'set_option' || $op === 'add_option' || $op === 'delete_option') {
			return $this->keysWhitelisted($op, $wc, $this->whitelist_keys);
		}

		if ($op === 'stage_options') {
			return $this->keysWhitelisted($op, $wc, $this->whitelist_keys);
		}

		if ($op === 'save_all') {
			// Ensure save_all payload only contains whitelisted keys.
			return $this->keysWhitelisted($op, $wc, $this->whitelist_keys);
		}

		if ($op === 'clear' || $op === 'seed_if_missing' || $op === 'migrate') {
			// Out of scope for subscriber self-service in this example.
			return false;
		}

		// Unknown operation → deny
		return false;
	}
}
