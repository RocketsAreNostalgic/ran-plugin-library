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
 * merge_from_db: true performs a shallow merge at save time:
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

$config  = Config::fromPluginFile(__FILE__);
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

// Persist with merge_from_db to avoid overwriting concurrent admin changes
// RESULT: Both your activation data AND admin changes are preserved
$options->flush(merge_from_db: true);

// REAL-WORLD EXAMPLE: Cron job updating cache while user modifies settings
register_activation_hook(__FILE__, function() {
	$options = RegisterOptions::from_config(Config::fromPluginFile(__FILE__));

	// Set activation defaults without losing existing user settings
	$options->add_options(array(
	    'version'              => '1.0.0',
	    'installed_date'       => current_time('mysql'),
	    'needs_welcome_screen' => true,
	), false);

	$options->flush(merge_from_db: true); // Safe concurrent save
});

// CRON JOB EXAMPLE: Update analytics data without losing user settings
add_action('my_plugin_daily_stats', function() {
	$options = RegisterOptions::from_config(Config::fromPluginFile(__FILE__));
	// Define config in this scope for scoped storage operations below
	$config = Config::fromPluginFile(__FILE__);

	$options->add_options(array(
	    'daily_stats'       => calculate_daily_stats(),
	    'last_stats_update' => current_time('mysql'),
	    'cache_status'      => 'updated',
	), false);

	// ------------------------------------------------------------
	// Scoped instance (advanced)
	// ------------------------------------------------------------
	// If you need to merge writes in a different scope, obtain a scoped instance:
	$userOptions = $config->options(array(
	  'scope'       => 'user',
	  'user_id'     => get_current_user_id(),
	  'user_global' => false,
	));
	$userOptions->add_options(array('wizard_step' => 'done'));
	$userOptions->flush(merge_from_db: true);

	$blogOptions = $config->options(array(
	  'scope'   => 'blog',
	  'blog_id' => 2,
	));
	$blogOptions->add_options(array('feature_flags' => array('beta' => true)));
	$blogOptions->flush(merge_from_db: true);
	// Merge with DB in case admin changed settings during cron run
	$options->flush(merge_from_db: true);
});

// AJAX FORM EXAMPLE: Handle overlapping form submissions
add_action('wp_ajax_save_plugin_settings', function() {
	$options = RegisterOptions::from_config(Config::fromPluginFile(__FILE__));

	// User submitted form data
	$options->add_options(array(
	    'user_email'            => sanitize_email($_POST['email']),
	    'notifications_enabled' => !empty($_POST['notifications']),
	    'last_user_update'      => current_time('mysql'),
	));

	// Protect against overlapping AJAX requests
	if ($options->flush(merge_from_db: true)) {
		wp_send_json_success('Settings saved');
	} else {
		wp_send_json_error('Save failed');
	}
});
