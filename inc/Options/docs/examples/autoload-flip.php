<?php
/**
 * Example: Safely flipping autoload behavior for performance tuning.
 *
 * This demonstrates how to change autoload behavior for an existing options row
 * without losing data. WordPress requires delete+add to change autoload.
 *
 * WHEN TO USE:
 * - Your options grow large (>50KB serialized data)
 * - Performance monitoring shows wp_options autoload is slow

 * IMPORTANT CAVEATS:
 * - Autoload semantics apply to the SITE scope, and to BLOG scope only when
 *   targeting the current blog (blog_id == get_current_blog_id()).
 * - USER and NETWORK storage do not support autoload; supports_autoload() returns false.
 * - WordPress only applies autoload when an option is CREATED. Changing it
 *   requires delete+add, which is why set_main_autoload() performs that under the hood.
 */

use Ran\PluginLib\Config\Config;
use Ran\PluginLib\Options\RegisterOptions;

// Initialize config and options
$config  = Config::fromPluginFile(__FILE__);
$options = RegisterOptions::from_config($config); // site scope by default

// Guard: ensure we're in a scope that supports autoload.
if (!$options->supports_autoload()) {
	echo "Current scope does not support autoload. Skipping flip.\n";
	// Example: show that user/blog scopes are not eligible
	$userOpts = $config->options(array('scope' => 'user', 'user_id' => 123));
	assert($userOpts->supports_autoload() === false);

	$blogOpts = $config->options(array('scope' => 'blog', 'blog_id' => 2));
	// Will only be true when blog_id == current blog id
	echo 'Blog scope supports autoload? ' . ($blogOpts->supports_autoload() ? 'yes' : 'no') . "\n";
}

// Example: Holiday-specific settings that need quick access during December
if (get_transient('wp_options_autoload_slow')) {
	$options->set_main_autoload(false);
	error_log('Disabled autoload for ' . $config->get_config()['RAN']['AppOption'] . ' due to performance');
}

// Example: Performance monitoring - check current autoload status
echo "Current autoload status: checking via WordPress...\n";

// Example: Flip autoload safely (requires delete+add in WordPress)
if (defined('WP_DEBUG') && WP_DEBUG) {
	echo "Flipping autoload for performance tuning...\n";

	// This will delete the existing row and recreate with new autoload setting
	$success = $options->set_main_autoload(false);

	if ($success) {
		echo "✓ Autoload disabled successfully\n";
		echo "Note: This required a delete+add operation due to WordPress limitations\n";
	} else {
		echo "✗ Failed to change autoload behavior\n";
	}
}

// Example: Check autoload hints for individual keys
$all_options = $options->get_options();
foreach ($all_options as $key => $metadata) {
	$hint = $metadata['autoload_hint'] ?? null;
	if ($hint !== null) {
		echo "Key '{$key}' has autoload hint: " . ($hint ? 'true' : 'false') . "\n";
	}
}

// Example: Re-enable autoload when performance issue is resolved
if (!get_transient('wp_options_autoload_slow')) {
	echo "Performance issue resolved, re-enabling autoload...\n";
	$options->set_main_autoload(true);
	echo "✓ Autoload re-enabled\n";
}
