<?php
/**
 * Example: Composing multiple WritePolicies with AND semantics
 *
 * This example shows how to compose a capability-aligned default policy
 * with a stricter, application-level self-service policy. The composite
 * uses AND semantics: if any policy denies, the write is denied.
 */

declare(strict_types=1);

use Ran\PluginLib\Options\Storage\StorageContext;
use Ran\PluginLib\Options\Policy\CompositeWritePolicy; // AND-composite
use Ran\PluginLib\Options\Policy\RestrictedDefaultWritePolicy; // WP caps by scope
use Ran\PluginLib\Options\Policy\ExampleUserSelfServiceWhitelistPolicy; // same-user + whitelist

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
$opts->set_option('preferences', array('theme' => 'dark'));
$opts->stage_options(array('newsletter_opt_in' => true));

// Denied by whitelist policy (even if caps might allow):
$opts->set_option('admin_only', true);

// Denied by default policy if caps are insufficient for scope (e.g., network scope)
// $opts_network = $config->options(StorageContext::forNetwork());
// $opts_network->with_policy($policy);
// $opts_network->set_option('preferences', array('theme' => 'light')); // likely denied by caps

// Notes
// - See Also:
//   - inc/Options/Policy/CompositeWritePolicy.php (AND composite)
//   - inc/Options/Policy/RestrictedDefaultWritePolicy.php (WP caps)
//   - inc/Options/Policy/ExampleUserSelfServiceWhitelistPolicy.php (self-service whitelist)
