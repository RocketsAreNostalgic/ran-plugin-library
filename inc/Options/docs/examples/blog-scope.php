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
 *   - When blog_id == get_current_blog_id(): supports_autoload() may be true for purposes of
 *     wp_load_alloptions() only; creation-time autoload control is not available via Blog APIs.
 *   - Otherwise, supports_autoload() is false.
 */

declare(strict_types=1);

use Ran\PluginLib\Config\Config;
use Ran\PluginLib\Options\Storage\StorageContext;

$config = Config::fromPluginFile(__FILE__);

// Target blog #2 explicitly
$blogId  = 2;
$options = $config->options(
	StorageContext::forBlog($blogId),
	false // autoload preference for blog scope (ignored for non-current blog)
);

// Check autoload support; only meaningful for the current blog
if ($options->supports_autoload()) {
	// Autoload relevance here is limited to wp_load_alloptions() for the current blog.
	// Blog add/update APIs do not accept an autoload parameter.
}

// Example writes
$options->stage_option('maintenance_mode', false);
$options->stage_option('homepage_layout', 'magazine');

// Commit changes to the DB
$options->commit_merge();

// Reads with defaults
$mm   = $options->get_option('maintenance_mode', false);
$home = $options->get_option('homepage_layout', 'standard');

// Values-only view
$values = $options->get_options();

// Batch update pattern
$options->stage_options(array(
  'feature_flags' => array('new_nav' => true),
  'theme_options' => array('color' => 'blue')
));
$options->commit_merge();
