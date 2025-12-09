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
$opts->stage_option('preferences', array('theme' => 'dark'))->commit_merge();
$opts->stage_option('profile_bio', 'Hi! I love this site.')->commit_merge();
$opts->stage_options(array(
    'newsletter_opt_in' => true,
))->commit_merge();

// 4) Unsafe writes (blocked by policy):
//    - Non‑whitelisted key
//    - Or writes for a different user
$opts->stage_option('admin_only', true)->commit_merge(); // Will be vetoed by the policy

// Notes
// - See Also: inc/Options/Policy/ExampleUserSelfServiceWhitelistPolicy.php
// - Policy is evaluated using a typed WriteContext (see TFS‑005)
// - For atomic batch enforcement with stage_options, a policy can inspect WriteContext->keys()
//   and veto the entire batch if any key is disallowed. For single-key ops, inspect WriteContext->key().
//   Example policy skeleton:
//   final class UserSelfServiceWhitelistPolicy implements \Ran\PluginLib\Options\Policy\WritePolicyInterface {
//       /** @var array<int,string> */
//       private array $whitelist;
//       public function __construct(array $whitelist) { $this->whitelist = array_values(array_map('strval', $whitelist)); }
//       public function allow(string $op, \Ran\PluginLib\Options\WriteContext $wc): bool {
//           if ($op === 'add_option' && $wc->key() !== null) {
//               return in_array((string) $wc->key(), $this->whitelist, true);
//           }
//           if ($op === 'stage_options' && is_array($wc->keys())) {
//               foreach ($wc->keys() as $k) {
//                   if (!in_array((string) $k, $this->whitelist, true)) {
//                       return false; // atomic veto
//                   }
//               }
//               return true;
//           }
//           return true;
//       }
//   }
// - You can swap policies per manager instance as needed:
//     $opts->with_policy(new YourCustomPolicy());
