<?php
/**
 * RegisterOptions Example: Post-construction schema (migration)
 *
 * PRACTICAL USE CASE: Plugin updates and feature rollouts
 *
 * This pattern is ESSENTIAL for:
 * - Plugin version upgrades that add new settings
 * - Feature flags for gradual rollouts
 * - A/B testing configurations
 * - Fixing missing defaults from earlier plugin versions
 * - Adding validation to existing options
 *
 * WHY POST-CONSTRUCTION?
 * Sometimes you can't define all schema at construction time:
 * - Schema depends on runtime conditions (user role, site config)
 * - Different features register schema conditionally
 * - Migration scripts that run during plugin updates
 * - Dynamic feature detection (API availability, server capabilities)
 *
 * BENEFITS:
 * - Lazy schema registration reduces memory usage
 * - Conditional schema based on runtime state
 * - Safe defaults for new features without breaking existing installs
 * - Validation can be added to legacy options
 */

declare(strict_types=1);

use Ran\PluginLib\Config\Config;
use Ran\PluginLib\Options\RegisterOptions;

$config  = Config::fromPluginFile(__FILE__);
$options = RegisterOptions::from_config($config);

// EXAMPLE 1: Plugin version upgrade - add new feature with safe defaults
$current_version = $options->get_option('plugin_version', '1.0.0');
if (version_compare($current_version, '2.0.0', '<')) {
	// Version 2.0 introduces analytics feature
	$options->register_schema(array(
	    'analytics_enabled' => array(
	        'default'  => false,  // Default to disabled for safety
	        'validate' => fn($v) => is_bool($v),
	    ),
	    'analytics_tracking_id' => array(
	        'default'  => '',
	        'sanitize' => fn($v) => sanitize_text_field($v),
	        'validate' => fn($v) => is_string($v),
	    ),
'plugin_version' => array(
		'default'  => '2.0.0',
		'validate' => fn($v) => is_string($v) && !empty($v),
	    ),
	));

}

// EXAMPLE 2: Conditional feature registration based on server capabilities
if (function_exists('imagick') || extension_loaded('gd')) {
	$options->register_schema(array(
	    'image_processing_enabled' => array(
	        'default'  => true,  // Enable if server supports it
	        'validate' => fn($v) => is_bool($v),
	    ),
'image_quality' => array(
		'default'  => 85,
		'validate' => fn($v) => is_int($v) && $v >= 1 && $v <= 100,
	    ),
	)); // No implicit flush; will persist later
}

// EXAMPLE 3: User role-based feature availability
$current_user = wp_get_current_user();
if (user_can($current_user, 'manage_options')) {
	$options->register_schema(array(
'admin_debug_mode' => array(
		'default'  => false,
		'validate' => fn($v) => is_bool($v),
	    ),
'show_performance_metrics' => array(
		'default'  => false,
		'validate' => fn($v) => is_bool($v),
	    ),
	));
}

// EXAMPLE 4: A/B testing feature flags
$user_id    = get_current_user_id();
$test_group = $user_id % 2; // Simple A/B split
$options->register_schema(array(
    'new_ui_enabled' => array(
        'default'  => $test_group === 1, // 50% of users get new UI
        'validate' => fn($v) => is_bool($v),
    ),
    'test_group' => array(
        'default'  => $test_group,
        'validate' => fn($v) => is_int($v) && in_array($v, array(0, 1)),
	)

// Commit all schema changes at once for efficiency
$options->commit_replace();

// REAL-WORLD MIGRATION EXAMPLE:
add_action('plugins_loaded', function() {
	$options    = RegisterOptions::from_config(Config::fromPluginFile(__FILE__));
	$db_version = $options->get_option('db_version', '1.0');

	// Migration for version 1.1 - add caching options
	if (version_compare($db_version, '1.1', '<')) {
		$options->register_schema(array(
'cache_enabled'  => array('default' => true, 'validate' => fn($v) => is_bool($v)),
		    'cache_duration' => array('default' => 3600, 'validate' => fn($v) => is_int($v) && $v > 0),
		'db_version'     => array('default' => '1.1', 'validate' => fn($v) => is_string($v) && $v !== ''),
		));
	}

	// Migration for version 1.2 - add API settings
	if (version_compare($db_version, '1.2', '<')) {
		$options->register_schema(array(
'api_endpoint' => array('default' => 'https://api.example.com/v1', 'validate' => fn($v) => is_string($v) && $v !== ''),
		    'api_timeout'  => array('default' => 30, 'validate' => fn($v) => is_int($v) && $v > 0),
		'db_version'   => array('default' => '1.2', 'validate' => fn($v) => is_string($v) && $v !== ''),
		));
	}

	$options->commit_replace(); // Single write for all migrations
});
