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
use Ran\PluginLib\Options\Storage\StorageContext;

$config  = Config::fromPluginFile(__FILE__);
$options = new RegisterOptions($config->get_options_key());

// SCENARIO: Plugin activation while admin is changing settings
// Your activation script needs to set version info without losing user settings
$options->stage_options(array(
  'plugin_version'       => '2.0.0',    // Your activation script sets this
  'activation_timestamp' => time(),     // Your activation script sets this
  'migration_completed'  => true,       // Your activation script sets this
)); // Stage in memory only, will flush below

// Meanwhile, admin user might be saving these settings:
// 'notification_email' => 'admin@example.com'
// 'enable_features' => ['analytics', 'caching']
// 'api_timeout' => 30

// Persist with a shallow merge from DB to avoid overwriting concurrent admin changes
// RESULT: Both your activation data AND admin changes are preserved
$options->commit_merge();

// REAL-WORLD EXAMPLE: Cron job updating cache while user modifies settings
register_activation_hook(__FILE__, function() {
	$options = new RegisterOptions((Config::fromPluginFile(__FILE__))->get_options_key());

	// Set activation defaults without losing existing user settings
	$options->stage_options(array(
	    'version'              => '1.0.0',
	    'installed_date'       => current_time('mysql'),
	    'needs_welcome_screen' => true,
	), false);

	$options->commit_merge(); // Safe concurrent save
});

// CRON JOB EXAMPLE: Update analytics data without losing user settings
add_action('my_plugin_daily_stats', function() {
	$options = new RegisterOptions((Config::fromPluginFile(__FILE__))->get_options_key());
	// Define config in this scope for scoped storage operations below
	$config = Config::fromPluginFile(__FILE__);

	$options->stage_options(array(
	    'daily_stats'       => calculate_daily_stats(),
	    'last_stats_update' => current_time('mysql'),
	    'cache_status'      => 'updated',
	), false);

	// ------------------------------------------------------------
	// Scoped instance (advanced)
	// ------------------------------------------------------------
	// If you need to merge writes in a different scope, obtain a scoped instance:
	$userOptions = $config->options(
		StorageContext::forUser((int) get_current_user_id(), 'meta', false),
		false
	);
	$userOptions->stage_options(array('wizard_step' => 'done'));
	$userOptions->commit_merge();

	$blogOptions = $config->options(
		StorageContext::forBlog(2),
		false // explicit preference (ignored for non-current blog)
	);
	$blogOptions->stage_options(array('feature_flags' => array('beta' => true)));
	$blogOptions->commit_merge();
	// Merge with DB in case admin changed settings during cron run
	$options->commit_merge();
});

// AJAX FORM EXAMPLE: Handle overlapping form submissions
add_action('wp_ajax_save_plugin_settings', function() {
	$options = new RegisterOptions((Config::fromPluginFile(__FILE__))->get_options_key());

	// User submitted form data
	$options->stage_options(array(
	    'user_email'            => sanitize_email($_POST['email']),
	    'notifications_enabled' => !empty($_POST['notifications']),
	    'last_user_update'      => current_time('mysql'),
	));

	// Protect against overlapping AJAX requests
	if ($options->commit_merge()) {
		wp_send_json_success('Settings saved');
	} else {
		wp_send_json_error('Save failed');
	}
});
