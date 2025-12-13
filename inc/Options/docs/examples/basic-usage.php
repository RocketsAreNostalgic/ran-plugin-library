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
 * individual stage_option()/get_option() calls for each setting.
 */

declare(strict_types=1);

use Ran\PluginLib\Config\Config;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Options\Storage\StorageContext;

// Acquire config for this plugin file
$config = Config::fromPluginFile(__FILE__);

// Create options group using plugin's primary option name
// RATIONALE: Config::options() uses your plugin's configured option name from Config,
// ensuring consistency across your plugin and avoiding hardcoded strings.
$options = $config->options(StorageContext::forSite(), true);

// Set individual options to memory
$options->stage_option('api_key', 'abc123');
$options->stage_option('enabled', true);

// Commit changes to the DB
$options->commit_replace();

// Read them back with sensible defaults
// BEST PRACTICE: Always provide defaults that won't break your plugin
$enabled = $options->get_option('enabled', false);  // Default to disabled for safety
$apiKey  = $options->get_option('api_key', '');     // Default to empty string, not null

// Values-only view - useful for exports, debugging, or API responses
$values = $options->get_options(); // ['api_key' => 'abc123', 'enabled' => true]

// REAL-WORLD EXAMPLE: Plugin activation
if (!$options->has_option('version')) {
	$options->stage_option('version', '1.0.0');
	$options->stage_option('activation_date', current_time('mysql'));
	$options->stage_option('initial_setup_complete', false);
	$options->commit_replace();
}

// ------------------------------------------------------------
// Scoped storage examples (advanced)
// ------------------------------------------------------------
// You can target different storage scopes without changing call sites.
// Preferred: use Config accessor with explicit scope and arguments.

// User scope (typed context)
// Autoload is not supported for user scope (supports_autoload() => false).
$userOptions = $config->options(
	StorageContext::forUserId((int) get_current_user_id(), 'meta', false),
	false
);
$userOptions->stage_option('dashboard_prefs', array('layout' => 'compact'));
$prefs = $userOptions->get_option('dashboard_prefs', array());
$userOptions->commit_replace();

// Blog scope (multisite)
// Autoload is supported only when blog_id equals the current blog.
$blogOptions = $config->options(
	StorageContext::forBlog(2),
	false // explicit preference (ignored for non-current blog)
);
if ($blogOptions->supports_autoload()) {
	// safe to rely on autoload, we know blog_id matches current blog
}

// Alternate construction via new RegisterOptions() with typed context
$explicit = new RegisterOptions(
	$config->get_options_key(),
	StorageContext::forUserId((int) get_current_user_id(), 'option', true),
	false,
	$config->get_logger()
);
