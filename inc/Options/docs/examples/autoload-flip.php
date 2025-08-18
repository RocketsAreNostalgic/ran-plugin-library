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

 * IMPORTANT CAVEAT:
 * WordPress only applies autoload setting when an option is CREATED.
 * This method works by deleting and recreating the option, which is why
 * it's the only way to reliably change autoload behavior.
 */

use Ran\PluginLib\Config\Config;
use Ran\PluginLib\Options\RegisterOptions;

// Initialize config and options
$config  = Config::fromPluginFile(__FILE__);
$options = RegisterOptions::from_config($config);

// Example: Holiday-specific settings that need quick access during December
// if (get_transient('wp_options_autoload_slow')) {
//     $options->set_main_autoload(false);
//     error_log('Disabled autoload for ' . $config->get_config()['RAN']['AppOption'] . ' due to performance');
// }

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
