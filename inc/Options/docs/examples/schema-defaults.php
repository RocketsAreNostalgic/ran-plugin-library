<?php
/**
 * RegisterOptions Example: Constructor schema with default seeding
 *
 * PRACTICAL USE CASE: Environment-aware plugin initialization
 *
 * This pattern is IDEAL for:
 * - Fresh plugin installations with sensible defaults
 * - Environment-specific configurations (dev vs production)
 * - Feature flags that depend on server capabilities
 * - User role-based default permissions
 * - Site-specific optimizations (multisite, performance)
 *
 * BENEFITS OF CONSTRUCTOR SCHEMA:
 * - Defaults are resolved immediately when RegisterOptions is created (in-memory)
 * - Persist all missing defaults efficiently with a single flush() call
 * - Environment-aware defaults using Config context
 * - Validation ensures defaults are always valid
 * - Simple activation flow: seed in-memory at construction, then flush once
 *
 * DYNAMIC DEFAULTS:
 * Use callable defaults when values depend on runtime conditions:
 * - Server environment (dev/staging/production)
 * - User capabilities or roles
 * - Site configuration or multisite context
 * - Available PHP extensions or server features
 */

declare(strict_types=1);

use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Config\Config;

$config = Config::get_instance();

// COMPREHENSIVE SCHEMA WITH ENVIRONMENT-AWARE DEFAULTS:
$schema = array(
    // Simple boolean default - same for all environments
    'analytics_enabled' => array(
        'default'  => false, // Default to disabled for privacy
        'validate' => fn($v) => is_bool($v),
    ),
    
    // Environment-specific timeout - shorter for development
    'api_timeout' => array(
        'default'  => fn($cfg) => $cfg && $cfg->is_dev_environment() ? 5 : 30,
        'validate' => fn($v) => is_int($v) && $v > 0 && $v <= 300,
    ),
    
    // Cache duration based on environment
    'cache_duration' => array(
        'default' => function($cfg) {
        	if (!$cfg) {
        		return 3600;
        	} // 1 hour fallback
        	if ($cfg->is_dev_environment()) {
        		return 0;
        	}     // No cache in dev
        	if ($cfg->is_staging_environment()) {
        		return 300;
        	} // 5 min in staging
        	return 3600; // 1 hour in production
        },
        'validate' => fn($v) => is_int($v) && $v >= 0,
    ),
    
    // Debug level based on environment
    'debug_level' => array(
        'default' => function($cfg) {
        	if (!$cfg) {
        		return 'error';
        	}
        	if ($cfg->is_dev_environment()) {
        		return 'debug';
        	}
        	if ($cfg->is_staging_environment()) {
        		return 'warning';
        	}
        	return 'error'; // Production: errors only
        },
        'validate' => fn($v) => in_array($v, array('debug', 'info', 'warning', 'error')),
    ),
    
    // Feature availability based on server capabilities
    'image_processing_enabled' => array(
        'default'  => fn($cfg) => extension_loaded('gd') || extension_loaded('imagick'),
        'validate' => fn($v) => is_bool($v),
    ),
    
    // Upload directory with site-specific path
    'upload_directory' => array(
        'default' => function($cfg) {
        	$upload_dir  = wp_upload_dir();
        	$plugin_name = $cfg ? $cfg->get_plugin_config()['TextDomain'] : 'my-plugin';
        	return $upload_dir['basedir'] . '/' . $plugin_name;
        },
        'validate' => fn($v) => is_string($v) && !empty($v),
    ),
    
    // User role-based default permissions
    'admin_features_enabled' => array(
        'default'  => fn($cfg) => current_user_can('manage_options'),
        'validate' => fn($v) => is_bool($v),
    ),
    
    // Multisite-aware defaults
    'network_mode' => array(
        'default'  => fn($cfg) => is_multisite(),
        'validate' => fn($v) => is_bool($v),
    ),
);

// Create RegisterOptions with schema - defaults will be seeded in-memory automatically
$options = RegisterOptions::from_config($config, /* initial */ array(), /* autoload */ true, /* logger */ null, $schema);

// Persist seeded defaults explicitly (single write)
$options->flush();

// At this point, all missing options have been:
// 1. Resolved from their default values (including callable defaults)
// 2. Sanitized (if sanitizers were defined)
// 3. Validated (ensuring they meet requirements)
// 4. Persisted to the database via a single flush() call

// VERIFY THE SEEDED VALUES:
echo "Seeded values:\n";
echo '- Analytics enabled: ' . ($options->get_option('analytics_enabled') ? 'Yes' : 'No') . "\n";
echo '- API timeout: ' . $options->get_option('api_timeout') . " seconds\n";
echo '- Cache duration: ' . $options->get_option('cache_duration') . " seconds\n";
echo '- Debug level: ' . $options->get_option('debug_level') . "\n";
echo '- Image processing: ' . ($options->get_option('image_processing_enabled') ? 'Available' : 'Disabled') . "\n";
echo '- Upload directory: ' . $options->get_option('upload_directory') . "\n";
echo '- Admin features: ' . ($options->get_option('admin_features_enabled') ? 'Enabled' : 'Disabled') . "\n";
echo '- Network mode: ' . ($options->get_option('network_mode') ? 'Multisite' : 'Single site') . "\n";

// REAL-WORLD PLUGIN ACTIVATION EXAMPLE:
// register_activation_hook(__FILE__, function() {
//     $config = Config::get_instance();
//
//     $activation_schema = [
//         'version' => [
//             'default' => '1.0.0',
//             'validate' => fn($v) => is_string($v) && !empty($v),
//         ],
//         'installed_date' => [
//             'default' => fn($cfg) => current_time('mysql'),
//             'validate' => fn($v) => is_string($v),
//         ],
//         'needs_welcome_screen' => [
//             'default' => true,
//             'validate' => fn($v) => is_bool($v),
//         ],
//         'performance_mode' => [
//             'default' => fn($cfg) => wp_get_environment_type() === 'production' ? 'optimized' : 'standard',
//             'validate' => fn($v) => in_array($v, ['standard', 'optimized']),
//         ],
//     ];
//
//     // This will seed all defaults in-memory
//     $options = RegisterOptions::from_config($config, [], true, null, $activation_schema);
//     // Persist in one database write
//     $options->flush();
//
//     // Plugin is now properly initialized with environment-appropriate defaults
// });
