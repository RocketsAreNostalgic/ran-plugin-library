<?php
/**
 * RegisterOptions Example: Force autoload flip for main option
 *
 * PRACTICAL USE CASE: Dynamic performance optimization
 *
 * WordPress autoloads all options on every page load. This is great for small,
 * frequently-used options but terrible for large data sets.
 *
 * WHEN TO USE:
 * - Your options grow large (>50KB serialized data)
 * - You have seasonal/conditional data (e.g., holiday settings)
 * - Performance monitoring shows wp_options autoload is slow
 * - You want to optimize based on usage patterns
 *
 * REAL-WORLD SCENARIOS:
 * 1. E-commerce: Disable autoload for large product catalogs during low-traffic periods
 * 2. Analytics: Cache data that's only needed in admin, not frontend
 * 3. Seasonal plugins: Disable autoload when features are inactive
 *
 * IMPORTANT CAVEAT:
 * WordPress only applies autoload setting when an option is CREATED.
 * This method works by deleting and recreating the option, which is why
 * it's the only way to reliably change autoload behavior.
 */

declare(strict_types=1);

use Ran\PluginLib\Config\Config;
use Ran\PluginLib\Options\RegisterOptions;

$config  = Config::get_instance();
$options = RegisterOptions::from_config($config);

// PERFORMANCE OPTIMIZATION: Disable autoload for large datasets
// Example: Your plugin stores user analytics that grew to 200KB
$current_size = strlen(serialize($options->get_values()));
if ($current_size > 100000) { // 100KB threshold
	$options->set_main_autoload(false);
	// Now this data won't slow down every page load
}

// SEASONAL EXAMPLE: Re-enable autoload when feature becomes active
// Example: Holiday-specific settings that need quick access during December
$current_month = (int) date('n');
if ($current_month === 12 && !$options->get_option('autoload_enabled', false)) {
	$options->set_main_autoload(true);
	$options->set_option('autoload_enabled', true);
}

// MONITORING EXAMPLE: Disable autoload based on performance metrics
// if (get_transient('wp_options_autoload_slow')) {
//     $options->set_main_autoload(false);
//     error_log('Disabled autoload for ' . $config->get_plugin_config()['RANPluginOption'] . ' due to performance');
// }
