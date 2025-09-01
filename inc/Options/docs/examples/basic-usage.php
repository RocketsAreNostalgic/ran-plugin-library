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

// Acquire config for this plugin file
$config = Config::fromPluginFile(__FILE__);

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
$values = $options->get_values(); // ['api_key' => 'abc123', 'enabled' => true]

// REAL-WORLD EXAMPLE: Plugin activation
if (!$options->has_option('version')) {
	$options->set_option('version', '1.0.0');
	$options->set_option('activation_date', current_time('mysql'));
	$options->set_option('initial_setup_complete', false);
}

// ------------------------------------------------------------
// Scoped storage examples (advanced)
// ------------------------------------------------------------
// You can target different storage scopes without changing call sites.
// Preferred: use Config accessor with explicit scope and arguments.

// User scope
// Note: user_id is required. user_global is optional (defaults false).
// Autoload is not supported for user scope (supports_autoload() => false).
$userOptions = $config->options(array(
    'scope'       => 'user',
    'user_id'     => get_current_user_id(),
    'user_global' => false,
));
$userOptions->set_option('dashboard_prefs', array('layout' => 'compact'));
$prefs = $userOptions->get_option('dashboard_prefs', array());

// Blog scope (multisite)
// Autoload is supported only when blog_id equals the current blog.
$blogOptions = $config->options(array(
    'scope'   => 'blog',
    'blog_id' => 2,
));
if ($blogOptions->supports_autoload()) {
	// safe to rely on autoload
}

// Alternate construction via RegisterOptions::from_config() (explicit logger/args)
// This is useful when you need to pass a specific logger or storage args directly.
$explicit = RegisterOptions::from_config(
	$config,
	/* initial */ array(),
	/* autoload */ true,
	/* logger */ $config->get_logger(),
	/* schema */ array(),
	/* scope */ 'user',
	/* storage args */ array('user_id' => get_current_user_id(), 'user_global' => true)
);
