<?php
/**
 * Example: Blog-scoped options (Multisite)
 *
 * Demonstrates targeting a specific blog (site) in a multisite network.
 * Useful for network tools that manage settings for blogs other than the current one.
 *
 * NOTES:
 * - Provide 'blog_id' to target a specific blog.
 * - Autoload support depends on whether blog_id equals the current blog:
 *   - When blog_id == get_current_blog_id(): supports_autoload() may be true.
 *   - Otherwise, supports_autoload() is false and load_all_autoloaded() returns null.
 */

declare(strict_types=1);

use Ran\PluginLib\Config\Config;

$config = Config::fromPluginFile(__FILE__);

// Target blog #2 explicitly
$blogId  = 2;
$options = $config->options(array(
    'scope'   => 'blog',
    'blog_id' => $blogId,
));

// Check autoload support; only meaningful for the current blog
if ($options->supports_autoload()) {
	// Autoload hints can have effect when working with the current blog
}

// Example writes
$options->set_option('maintenance_mode', false);
$options->set_option('homepage_layout', 'magazine');

// Reads with defaults
$mm   = $options->get_option('maintenance_mode', false);
$home = $options->get_option('homepage_layout', 'standard');

// Values-only view (no autoload_hint)
$values = $options->get_values();

// Attempt to load all autoloaded options
$autoloaded = $options->load_all_autoloaded(); // may be array or null depending on blog_id
if ($autoloaded === null) {
	// Not supported when targeting a different blog
}

// Batch update pattern
$options->add_options(array(
  'feature_flags' => array('new_nav' => true),
  'theme_options' => array('color' => 'blue')
));
$options->flush(true);
