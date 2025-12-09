<?php
/**
 * Example: Field-Restricted Write Policy (single key and batch)
 *
 * Demonstrates a write policy that restricts persistence to a specific set of
 * fields (option keys). Shows how this interacts with single-key updates
 * (stage_option → commit_*) and batch updates (stage_options/save-all).
 *
 * Notes
 * - RegisterOptions emits detailed gating context via WriteContext, including
 *   op name and the exact keys being written. This policy uses those fields to
 *   allow/deny writes deterministically.
 */

declare(strict_types=1);

use Ran\PluginLib\Options\WriteContext;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Options\Storage\StorageContext;
use Ran\PluginLib\Options\Policy\AbstractWritePolicy;
use Ran\PluginLib\Options\Policy\WritePolicyInterface;

/**
 * FieldWhitelistPolicy – allow writes only for keys in the whitelist.
 *
 * Covers these ops via WriteContext:
 * - set_option / add_option / delete_option → validates the single key
 * - stage_options (batch staging)          → validates all keys (atomic)
 * - save_all (commit_* persistence)        → validates all top-level keys in payload
 */
final class FieldWhitelistPolicy extends AbstractWritePolicy implements WritePolicyInterface {
	/** @var array<int,string> */
	private array $whitelist;

	public function __construct(array $whitelist) {
		$this->whitelist = array_values(array_map('strval', $whitelist));
	}

	public function allow(string $op, WriteContext $wc): bool {
		// Use AbstractWritePolicy::keysWhitelisted helper for all relevant ops
		return $this->keysWhitelisted($op, $wc, $this->whitelist);
	}
}

// ----------------------------------------------------------------------------
// Wiring examples
// ----------------------------------------------------------------------------

// Site-scope manager for a grouped option
$opts = RegisterOptions::site('example_field_gated');

// Attach policy that only allows writing these keys
$opts->with_policy(new FieldWhitelistPolicy(array(
	'profile_bio',
	'preferences',
)));

// --- Single-key update (akin to update_option for a subkey) ---
// Allowed: key is whitelisted
$opts->stage_option('profile_bio', 'Hello world!')->commit_merge();

// Denied: key not whitelisted (gated by FieldWhitelistPolicy)
$opts->stage_option('admin_only', true)->commit_merge();

// --- Batch update (akin to update_options) ---
// Atomic behavior: entire batch is denied if any key is not allowed
$opts->stage_options(array(
	'profile_bio' => 'Updated',   // allowed
	'admin_only'  => true,        // not allowed → batch veto
))->commit_merge();

// --- Save-all (e.g., replace) ---
// Even when persisting the full map, policy validates all top-level keys in the payload
$opts->stage_options(array(
	'profile_bio' => 'Final',     // allowed
	'preferences' => array('theme' => 'dark'), // allowed
))->commit_replace();
