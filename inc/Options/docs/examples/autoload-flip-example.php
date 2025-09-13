<?php
/**
 * Manual autoload flipping for WordPress options.
 *
 * This demonstrates how to manually change autoload behavior for an existing options row
 * without losing data. WordPress requires delete+add to change autoload.
 *
 * WHEN TO USE:
 * - Your options grow large (>50KB serialized data)
 * - Performance monitoring shows wp_options autoload is slow
 * - You need to optimize memory usage
 *
 * IMPORTANT CAVEATS:
 * - Autoload semantics apply to SITE scope, and to BLOG scope only when
 *   targeting the current blog (blog_id == get_current_blog_id()).
 * - USER and NETWORK storage do not support autoload.
 * - WordPress only applies autoload when an option is CREATED. Changing it
 *   requires delete+add.
 */

use Ran\PluginLib\Config\Config;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Options\Entity\BlogEntity;
use Ran\PluginLib\Options\Entity\UserEntity;
use Ran\PluginLib\Options\Storage\StorageContext;

// Initialize config and options
$config  = Config::fromPluginFile(__FILE__);
$options = RegisterOptions::from_config($config); // site scope by default

// Guard: ensure we're in a scope that supports autoload.
if (!$options->supports_autoload()) {
	echo "Current scope does not support autoload. Skipping flip.\n";
	// Example: show that user/blog scopes are not eligible
	$userOpts = $config->options(
		StorageContext::forUser(123, 'meta', false),
		false
	);
	assert($userOpts->supports_autoload() === false);

	$blogOpts = $config->options(
		StorageContext::forBlog(2),
		false // explicit preference (ignored for non-current blog)
	);
	// Will only be true when blog_id == current blog id
	echo 'Blog scope supports autoload? ' . ($blogOpts->supports_autoload() ? 'yes' : 'no') . "\n";
	exit;
}

// Get the option name and current values
$option_name    = $config->get_options_key();
$current_values = $options->get_options();

// Example: Holiday-specific settings that need quick access during December
if (get_transient('wp_options_autoload_slow')) {
	// Manual autoload flip: delete and re-add with autoload=false
	delete_option($option_name);
	add_option($option_name, $current_values, '', 'no'); // 'no' = not autoloaded
	error_log('Disabled autoload for ' . $option_name . ' due to performance');

	// Refresh in-memory cache
	$options->refresh_options();
}

// Example: Performance monitoring - check current autoload status
echo "Current autoload status: checking via WordPress...\n";
// Note: Without the removed methods, you would need to check wp_load_alloptions()
// or use a custom query to check the autoload column in wp_options

// Example: Re-enable autoload when performance issue is resolved
if (!get_transient('wp_options_autoload_slow')) {
	echo "Performance issue resolved, re-enabling autoload...\n";

	// Manual autoload flip: delete and re-add with autoload=true
	delete_option($option_name);
	add_option($option_name, $current_values, '', 'yes'); // 'yes' = autoloaded

	// Refresh in-memory cache
	$options->refresh_options();

	echo "✓ Autoload re-enabled\n";
}
