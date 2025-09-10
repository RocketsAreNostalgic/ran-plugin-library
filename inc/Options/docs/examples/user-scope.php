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
use Ran\PluginLib\Options\Entity\UserEntity;

// Initialize config (adapt as needed for your plugin bootstrap)
$config = Config::fromPluginFile(__FILE__);

$userId = get_current_user_id(); // Or any specific user ID

// Default: user meta storage via typed entity (stores entire options array in usermeta)
$options = $config->options(array(
    'scope'  => 'user',
    'entity' => new UserEntity((int) $userId, false, 'meta'),
));

// Autoload is not supported for user scope
if ($options->supports_autoload() === false) {
	// Expected for user scope
}

// Write some user preferences
$options->set_option('dashboard_prefs', array(
    'layout' => 'compact',
    'cards'  => array('stats', 'news'),
));
$options->set_option('feature_x_enabled', true);

// Read them back with safe defaults
$prefs     = $options->get_option('dashboard_prefs', array());
$isEnabled = $options->get_option('feature_x_enabled', false);

// Values-only view
$values = $options->get_options();

// Batch update pattern (for multiple writes)
$options->add_options(array(
  'theme'     => 'dark',
  'shortcuts' => array('s' => 'search')
));
$options->flush(true); // single DB write

// Optional: Explicit construction via RegisterOptions::from_config() (array args)
$explicit = RegisterOptions::from_config($config, array(
    'autoload' => false, // user scope does not support autoload
    'scope'    => 'user',
    'entity'   => new UserEntity((int) $userId, true, 'meta'), // network-wide user meta
));

// Alternate backend: user option storage (per-user option key)
$optionsOption = $config->options(array(
    'scope'  => 'user',
    'entity' => new UserEntity((int) $userId, false, 'option'), // select WP user option storage
));

// Usage is identical regardless of backend:
$optionsOption->set_option('onboarding_state', array('step' => 2));
$onboarding = $optionsOption->get_option('onboarding_state', array());
