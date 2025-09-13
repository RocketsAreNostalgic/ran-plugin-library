<?php
/**
 * Example: Wiring a custom WritePolicy (ExampleUserSelfServiceWhitelistPolicy)
 *
 * This example shows how to attach a per‑scope write policy to RegisterOptions
 * so that only safe, self‑service keys can be modified by the current user
 * when operating in user scope.
 */

declare(strict_types=1);

use Ran\PluginLib\Options\Storage\StorageContext;
use Ran\PluginLib\Options\Policy\ExampleUserSelfServiceWhitelistPolicy;

// Assume you already have a Config instance
// $config = ... implements \Ran\PluginLib\Config\ConfigInterface

// 1) Obtain a pre‑wired RegisterOptions manager from Config in user scope
//    Target the current user via typed StorageContext.
$opts = $config->options(
	StorageContext::forUser((int) get_current_user_id(), 'meta', false),
	false
);

// 2) Attach the example policy. The policy will be consulted BEFORE any
//    WordPress filter hooks and can veto the write immediately.
$opts->with_policy(new ExampleUserSelfServiceWhitelistPolicy());

// 3) Safe writes (allowed by ExampleUserSelfServiceWhitelistPolicy):
$opts->set_option('preferences', array('theme' => 'dark'));
$opts->set_option('profile_bio', 'Hi! I love this site.');
$opts->stage_options(array(
    'newsletter_opt_in' => true,
));

// 4) Unsafe writes (blocked by policy):
//    - Non‑whitelisted key
//    - Or writes for a different user
$opts->set_option('admin_only', true); // Will be vetoed by the policy

// Notes
// - See Also: inc/Options/Policy/ExampleUserSelfServiceWhitelistPolicy.php
// - Policy is evaluated using a typed WriteContext (see TFS‑005)
// - You can swap policies per manager instance as needed:
//     $opts->with_policy(new YourCustomPolicy());
