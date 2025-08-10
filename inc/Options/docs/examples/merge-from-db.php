<?php
/**
 * RegisterOptions Example: Shallow merge from DB on save
 *
 * PRACTICAL USE CASE: Preventing lost updates in concurrent environments
 *
 * This pattern is CRITICAL for:
 * - Multisite networks with multiple admins
 * - Plugins with background cron jobs that update settings
 * - AJAX-heavy admin interfaces with multiple simultaneous requests
 * - Import/migration scripts running while users modify settings
 * - Multi-step wizards where users can navigate back/forward
 *
 * THE PROBLEM:
 * Process A loads options, modifies 'feature_x'
 * Process B loads options, modifies 'feature_y'
 * Process A saves -> 'feature_y' changes are lost!
 * Process B saves -> 'feature_x' changes are lost!
 *
 * THE SOLUTION:
 * mergeFromDb: true performs a shallow merge at save time:
 * 1. Read current DB state
 * 2. Merge your in-memory changes on top
 * 3. Save the combined result
 *
 * IMPORTANT LIMITATIONS:
 * - Only works for TOP-LEVEL keys (shallow merge)
 * - If two processes modify the SAME key, last writer still wins
 * - For nested structures, use the deep-merge pattern instead
 */

declare(strict_types=1);

use Ran\PluginLib\Config\Config;
use Ran\PluginLib\Options\RegisterOptions;

$config  = Config::get_instance();
$options = RegisterOptions::from_config($config);

// SCENARIO: Plugin activation while admin is changing settings
// Your activation script needs to set version info without losing user settings
$options->add_options(array(
  'plugin_version'       => '2.0.0',    // Your activation script sets this
  'activation_timestamp' => time(),     // Your activation script sets this
  'migration_completed'  => true,       // Your activation script sets this
)); // Stage in memory only, will flush below

// Meanwhile, admin user might be saving these settings:
// 'notification_email' => 'admin@example.com'
// 'enable_features' => ['analytics', 'caching']
// 'api_timeout' => 30

// Persist with mergeFromDb to avoid overwriting concurrent admin changes
// RESULT: Both your activation data AND admin changes are preserved
$options->flush(mergeFromDb: true);

// REAL-WORLD EXAMPLE: Cron job updating cache while user modifies settings
// register_activation_hook(__FILE__, function() {
//     $options = RegisterOptions::from_config(Config::get_instance());
//
//     // Set activation defaults without losing existing user settings
//     $options->add_options([
//         'version' => '1.0.0',
//         'installed_date' => current_time('mysql'),
//         'needs_welcome_screen' => true,
//     ], false);
//
//     $options->flush(mergeFromDb: true); // Safe concurrent save
// });

// CRON JOB EXAMPLE: Update analytics data without losing user settings
// add_action('my_plugin_daily_stats', function() {
//     $options = RegisterOptions::from_config(Config::get_instance());
//
//     $options->add_options([
//         'daily_stats' => calculate_daily_stats(),
//         'last_stats_update' => current_time('mysql'),
//         'cache_status' => 'updated',
//     ], false);
//
//     // Merge with DB in case admin changed settings during cron run
//     $options->flush(mergeFromDb: true);
// });

// AJAX FORM EXAMPLE: Handle overlapping form submissions
// add_action('wp_ajax_save_plugin_settings', function() {
//     $options = RegisterOptions::from_config(Config::get_instance());
//
//     // User submitted form data
//     $options->set_options([
//         'user_email' => sanitize_email($_POST['email']),
//         'notifications_enabled' => !empty($_POST['notifications']),
//         'last_user_update' => current_time('mysql'),
//     ], false);
//
//     // Protect against overlapping AJAX requests
//     if ($options->flush(mergeFromDb: true)) {
//         wp_send_json_success('Settings saved');
//     } else {
//         wp_send_json_error('Save failed');
//     }
// });
