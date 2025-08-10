<?php
/**
 * RegisterOptions Example: Basic usage
 *
 * PRACTICAL USE CASE: Simple plugin settings management
 *
 * This pattern is ideal for:
 * - Small to medium plugins with straightforward settings
 * - When you want to keep all plugin options in a single wp_options row
 * - Reducing wp_options table bloat (WordPress best practice)
 *
 * BENEFITS:
 * - Cleaner wp_options table (one row instead of many)
 * - Better encapsulation of plugin data
 * - Automatic key sanitization prevents naming conflicts
 * - Built-in logging for debugging option changes
 *
 * WHEN TO USE: Most WordPress plugins should use this pattern instead of
 * individual add_option()/get_option() calls for each setting.
 */

declare(strict_types=1);

use Ran\PluginLib\Config\Config;
use Ran\PluginLib\Options\RegisterOptions;

// Acquire config (assumes Config has been initialized elsewhere)
$config = Config::get_instance();

// Create options group using plugin's primary option name
// RATIONALE: from_config() uses your plugin's configured option name from Config,
// ensuring consistency across your plugin and avoiding hardcoded strings.
// The 'true' parameter enables autoloading for frequently accessed options.
$options = RegisterOptions::from_config($config, /* initial */ array(), /* autoload */ true);

// Set individual options - each call writes to database immediately
// PERFORMANCE NOTE: Each set_option() call triggers a database write.
// For multiple options, prefer add_option(s) + flush() (see batch-and-flush.php)
$options->set_option('api_key', 'abc123');
$options->set_option('enabled', true);

// Read them back with sensible defaults
// BEST PRACTICE: Always provide defaults that won't break your plugin
$enabled = $options->get_option('enabled', false);  // Default to disabled for safety
$apiKey  = $options->get_option('api_key', '');     // Default to empty string, not null

// Values-only view - useful for exports, debugging, or API responses
// RATIONALE: Strips internal metadata (autoload_hint) for clean data access
$values = $options->get_values(); // ['api_key' => 'abc123', 'enabled' => true]

// REAL-WORLD EXAMPLE: Plugin activation
// if (!$options->has_option('version')) {
//     $options->set_option('version', '1.0.0');
//     $options->set_option('activation_date', current_time('mysql'));
//     $options->set_option('initial_setup_complete', false);
// }
