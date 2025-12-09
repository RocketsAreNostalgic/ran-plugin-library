<?php
/**
 * Example: Composing multiple WritePolicies with AND semantics
 *
 * This example shows how to compose a capability-aligned default policy
 * with a stricter, application-level self-service policy. The composite
 * uses AND semantics: if any policy denies, the write is denied.
	*
	* It also demonstrates how to enforce atomic batch behavior for stage_options
	* using the typed WriteContext: if any key would be disallowed, deny the batch.
 */

declare(strict_types=1);

use Ran\PluginLib\Options\WriteContext;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Options\Storage\StorageContext;
use Ran\PluginLib\Options\Policy\WritePolicyInterface;

use Ran\PluginLib\Options\Policy\CompositeWritePolicy; // AND-composite
use Ran\PluginLib\Options\Policy\RestrictedDefaultWritePolicy; // WP caps by scope
use Ran\PluginLib\Options\Policy\ExampleUserSelfServiceWhitelistPolicy; // same-user + whitelist

// Example default capability-aligned policy (sketch)
final class CapabilityAlignedPolicy implements WritePolicyInterface {
	public function allow(string $op, WriteContext $wc): bool {
		$scope = $wc->scope();
		if ($scope === 'network') {
			return function_exists('current_user_can') ? (bool) current_user_can('manage_network_options') : true;
		}
		return function_exists('current_user_can') ? (bool) current_user_can('manage_options') : true;
	}
}

// Example stricter application-level policy (self-service whitelist)
final class SelfServiceWhitelistPolicy implements WritePolicyInterface {
	/** @var array<int,string> */
	private array $whitelist;
	public function __construct(array $whitelist) {
		$this->whitelist = array_values(array_map('strval', $whitelist));
	}
	public function allow(string $op, WriteContext $wc): bool {
		// Single-key ops
		if (in_array($op, array('add_option', 'set_option', 'delete_option'), true) && $wc->key() !== null) {
			return in_array((string) $wc->key(), $this->whitelist, true);
		}
		// Batch (atomic) — deny when any key is not whitelisted
		if ($op === 'stage_options' && is_array($wc->keys())) {
			foreach ($wc->keys() as $k) {
				if (!in_array((string) $k, $this->whitelist, true)) {
					return false;
				}
			}
			return true;
		}
		// Save-all: allow; validator/sanitizer and filters still apply
		return true;
	}
}

// Composite policy with AND semantics
final class CompositePolicy implements WritePolicyInterface {
	/** @var array<int,WritePolicyInterface> */
	private array $policies;
	public function __construct(WritePolicyInterface ...$policies) {
		$this->policies = $policies;
	}
	public function allow(string $op, WriteContext $wc): bool {
		foreach ($this->policies as $p) {
			if ($p->allow($op, $wc) === false) {
				return false;
			}
		}
		return true;
	}
}

// Wiring example (inside a plugin setup)
// $config = ... implements \Ran\PluginLib\Config\ConfigInterface
$opts = $config->options(StorageContext::forSite());
$opts->with_policy(new CompositePolicy(
	new CapabilityAlignedPolicy(),
	new SelfServiceWhitelistPolicy(array('profile_bio', 'preferences', 'newsletter_opt_in'))
));

// Atomic batch example: since 'admin_only' is not whitelisted, entire batch is vetoed
$opts->stage_options(array(
	'profile_bio' => 'Hello',
	'admin_only'  => true,
))->commit_merge();



// Assume you already have a Config instance
// $config = ... implements \Ran\PluginLib\Config\ConfigInterface

// Obtain a RegisterOptions manager in user scope for the current user
$opts = $config->options(
	StorageContext::forUser((int) get_current_user_id(), 'meta', false),
	false
);

// Compose policies with AND semantics
$policy = new CompositeWritePolicy(
	new RestrictedDefaultWritePolicy(),          // capability-driven guard (WP caps per scope)
	new ExampleUserSelfServiceWhitelistPolicy()  // app-level self-service constraints
);

// Attach the composite policy
$opts->with_policy($policy);

// Allowed (must pass BOTH policies):
$opts->stage_option('preferences', array('theme' => 'dark'))->commit_merge();
$opts->stage_options(array('newsletter_opt_in' => true))->commit_merge();

// Denied by whitelist policy (even if caps might allow):
$opts->stage_option('admin_only', true)->commit_merge();

// Denied by default policy if caps are insufficient for scope (e.g., network scope)
// $opts_network = $config->options(StorageContext::forNetwork());
// $opts_network->with_policy($policy);
// $opts_network->stage_option('preferences', array('theme' => 'light'))->commit_merge(); // likely denied by caps

// Notes
// - See Also:
//   - inc/Options/Policy/CompositeWritePolicy.php (AND composite)
//   - inc/Options/Policy/RestrictedDefaultWritePolicy.php (WP caps)
//   - inc/Options/Policy/ExampleUserSelfServiceWhitelistPolicy.php (self-service whitelist)
