<?php
/**
 * Example: Per-user options (user scope)
 *
 * Demonstrates storing options per individual user. Useful for user preferences
 * like UI layout, feature toggles per user, onboarding state, etc.
 *
 * NOTES:
 * - You must provide 'user_id' when using user scope.
 * - 'user_global' controls whether the option is global (network-wide) for the user.
 *   Default is false (per-site for that user). Set true for network-wide.
 * - Storage backend selection (user scope):
 *   - Default: User meta (no extra arg)
 *   - Alternate: WordPress user options by passing 'user_storage' => 'option'
 *   User meta is preferred in most cases (e.g., multisite nuances, separation from options table).
 * - Autoload is not supported for user scope. Use supports_autoload() to check.
 */

declare(strict_types=1);

use Ran\PluginLib\Config\Config;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Options\Storage\StorageContext;

// Initialize config (adapt as needed for your plugin bootstrap)
$config = Config::fromPluginFile(__FILE__);

$userId = get_current_user_id(); // Or any specific user ID

// Default: user meta storage via typed context (stores entire options array in usermeta)
$options = $config->options(
	StorageContext::forUserId((int) $userId, 'meta', false),
	false // autoload not applicable for user scope
);

// Autoload is not supported for user scope
if ($options->supports_autoload() === false) {
	// Expected for user scope
}

// Write some user preferences
$options->stage_option('dashboard_prefs', array(
    'layout' => 'compact',
    'cards'  => array('stats', 'news'),
))->commit_merge();
$options->stage_option('feature_x_enabled', true)->commit_merge();

// Read them back with safe defaults
$prefs     = $options->get_option('dashboard_prefs', array());
$isEnabled = $options->get_option('feature_x_enabled', false);

// Values-only view
$values = $options->get_options();

// Batch update pattern (for multiple writes)
$options->stage_options(array(
  'theme'     => 'dark',
  'shortcuts' => array('s' => 'search')
));
$options->commit_merge(); // single DB write (shallow merge with DB)

// Optional: Explicit construction via RegisterOptions (typed context)
$explicit = new RegisterOptions(
	$config->get_options_key(),
	StorageContext::forUserId((int) $userId, 'meta', true), // network-wide user meta
	false, // autoload not applicable for user scope
	$config->get_logger()
);

// Alternate backend: user option storage (per-user option key)
$optionsOption = $config->options(
	StorageContext::forUserId((int) $userId, 'option', false), // select WP user option storage
	false // autoload not applicable for user scope
);

// Usage is identical regardless of backend:
$optionsOption->stage_option('onboarding_state', array('step' => 2))->commit_merge();
$onboarding = $optionsOption->get_option('onboarding_state', array());
