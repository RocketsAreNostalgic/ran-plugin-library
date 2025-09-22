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
 * - Persist all missing defaults efficiently with a single commit_replace() call
 * - Environment-aware defaults using Config context
 * - Validation ensures defaults are always valid
 * - Simple activation flow: seed in-memory at construction, then commit once
 *
 * DYNAMIC DEFAULTS:
 * Use callable defaults when values depend on runtime conditions:
 * - Server environment (dev/staging/production)
 * - User capabilities or roles
 * - Site configuration or multisite context
 * - Available PHP extensions or server features
 */

declare(strict_types=1);

use Ran\PluginLib\Config\Config;
use Ran\PluginLib\Util\Validate;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Options\Storage\StorageContext;

$config = Config::fromPluginFile(__FILE__);

// COMPREHENSIVE SCHEMA WITH ENVIRONMENT-AWARE DEFAULTS:
$schema = array(
    // Simple boolean default - same for all environments
    'analytics_enabled' => array(
        'default'  => false, // Default to disabled for privacy
        'validate' => Validate::compose()->all(array(
            Validate::basic()->isBool(),
        )),
    ),

    // Environment-specific timeout - shorter for development
    'api_timeout' => array(
        'default'  => fn($cfg) => $cfg && $cfg->is_dev_environment() ? 5 : 30,
        'validate' => Validate::compose()->all(array(
            Validate::basic()->isInt(),
            Validate::number()->between(0, 300),
        )),
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
        'validate' => Validate::compose()->all(array(
            Validate::basic()->isInt(),
			Validate::number()->min(0),
        )),
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
        'validate' => Validate::compose()->all(array(
            Validate::basic()->isString(),
			Validate::enums()->enum(array('debug', 'info', 'warning', 'error')),
        )),
    ),

    // Feature availability based on server capabilities
    'image_processing_enabled' => array(
        'default'  => fn($cfg) => extension_loaded('gd') || extension_loaded('imagick'),
        'validate' => Validate::basic()->isBool(),
    ),

    // Upload directory with site-specific path
    'upload_directory' => array(
        'default' => function($cfg) {
        	$upload_dir  = wp_upload_dir();
        	$plugin_name = $cfg ? $cfg->get_plugin_config()['TextDomain'] : 'my-plugin';
        	return $upload_dir['basedir'] . '/' . $plugin_name;
        },
        'validate' => Validate::compose()->all(array(
            Validate::basic()->isString(),
			Validate::basic()->isNotEmpty(),
        )),
    ),

    // User role-based default permissions
    'admin_features_enabled' => array(
        'default'  => fn($cfg) => current_user_can('manage_options'),
        'validate' => Validate::basic()->isBool(),
    ),

    // Multisite-aware defaults
    'network_mode' => array(
        'default'  => fn($cfg) => is_multisite(),
        'validate' => Validate::basic()->isBool(),
    ),
);

// Create RegisterOptions with schema - defaults will be seeded in-memory automatically
// Construct with autoload preference; bind schema via fluent
$options = $config->options(StorageContext::forSite(), true)
    ->with_schema($schema);

// Persist seeded defaults explicitly (single write)
$options->commit_replace();

// At this point, all missing options have been:
// 1. Resolved from their default values (including callable defaults)
// 2. Sanitized (if sanitizers were defined)
// 3. Validated (ensuring they meet requirements)
// 4. Persisted to the database via a single commit_replace() call

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
register_activation_hook(__FILE__, function() {
	$config = Config::fromPluginFile(__FILE__);

	$activation_schema = array(
	    'version' => array(
	        'default'  => '1.0.0',
	        'validate' => Validate::compose()->all(array(
	            Validate::basic()->isString(),
	            Validate::basic()->isNotEmpty(),
	        )),
	    ),
	    'installed_date' => array(
	        'default'  => fn($cfg) => current_time('mysql'),
	        'validate' => Validate::compose()->all(array(
	            Validate::basic()->isString(),
	            Validate::basic()->isNotEmpty(),
	        )),
	    ),
	    'needs_welcome_screen' => array(
	        'default'  => true,
	        'validate' => Validate::basic()->isBool(),
	    ),
	    'performance_mode' => array(
	        'default'  => fn($cfg) => wp_get_environment_type() === 'production' ? 'optimized' : 'standard',
	        'validate' => Validate::compose()->all(array(
	            Validate::basic()->isString(),
	            Validate::enums()->enum(array('standard', 'optimized')),
	        )),
	    ),
	);

	// Seed defaults in-memory via fluent, then persist in one write
	$config->options(StorageContext::forSite(), true)
		->with_schema($activation_schema)
		->commit_replace();
	// Plugin is now properly initialized with environment-appropriate defaults
});

// ------------------------------------------------------------
// Scoped usage with schema (advanced)
// ------------------------------------------------------------
// When you need to seed defaults for a different storage scope, prefer Config::options()
// Example: per-user defaults
$userOptions = $config->options(
	StorageContext::forUser((int) get_current_user_id(), 'meta', false),
	false
)->with_schema($schema);

// Example: blog-scoped defaults (multisite)
$blogOptions = $config->options(
	StorageContext::forBlog(2),
	false // explicit preference (ignored for non-current blog)
)->with_schema($schema);
