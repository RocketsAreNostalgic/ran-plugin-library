<?php
/**
 * RegisterOptions Example: Batch updates with flush
 *
 * PRACTICAL USE CASE: Performance optimization for bulk operations
 *
 * This pattern is useful for:
 * - Plugin activation/deactivation (setting multiple defaults)
 * - Settings import/export operations
 * - Migration scripts that update many options
 * - AJAX endpoints that process form data with multiple fields
 * - Cron jobs that update multiple cached values
 *
 * PERFORMANCE IMPACT:
 * - Without batching: 10 options = 10 database UPDATE queries
 * - With batching: 10 options = 1 database UPDATE query
 * - Can improve performance by 5-10x for bulk operations
 *
 * WHEN TO USE:
 * - Any time you're setting more than 2-3 options at once
 * - During plugin setup/teardown
 * - When processing user form submissions
 * - In background tasks or cron jobs
 */

declare(strict_types=1);

use Ran\PluginLib\Config\Config;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Options\Entity\UserEntity;
use Ran\PluginLib\Options\Entity\BlogEntity;

$config  = Config::fromPluginFile(__FILE__);
$options = RegisterOptions::from_config($config);

// BATCH PATTERN: Stage multiple changes in memory first, then flush once
// Note: You can mix simple values and structured definitions
$options->add_options(array(
  'api_key'        => 'abc',                 // Simple value
  'enabled'        => array('value' => true), // With metadata structure
  'timeout'        => 30,                    // Simple value
  'cache_duration' => array('value' => 3600) // With metadata structure
))->flush(); // Single DB write for all changes

// REAL-WORLD EXAMPLE: Plugin activation with many defaults
$activation_defaults = array(
    'version'          => '1.0.0',
    'activation_date'  => current_time('mysql'),
    'features_enabled' => array('analytics', 'caching'),
    'api_endpoints'    => array(
        'primary'  => 'https://api.example.com/v1',
        'fallback' => 'https://backup-api.example.com/v1'
    ),
    'performance_settings' => array(
        'cache_timeout' => 3600,
        'max_retries'   => 3,
        'batch_size'    => 100
    )
);

$options->add_options($activation_defaults)->flush(); // Single write for all

// FORM PROCESSING EXAMPLE: Handle admin settings form
if ($_POST['save_settings']) {
	$form_data = array(
	    'notification_email'     => sanitize_email($_POST['email']),
	    'enable_notifications'   => !empty($_POST['notifications']),
	    'notification_frequency' => sanitize_text_field($_POST['frequency']),
	    'last_updated'           => current_time('mysql')
	);

	$options->add_options($form_data)->flush();
	wp_redirect(add_query_arg('updated', '1', wp_get_referer()));
}

// ------------------------------------------------------------
// Scoped instance (advanced): obtain $options for a specific scope
// ------------------------------------------------------------
// Example: per-user batch update
$userOptions = $config->options(array(
  'scope'  => 'user',
  'entity' => new UserEntity((int) get_current_user_id(), false, 'meta'),
));
$userOptions->add_options(array(
  'shortcuts' => array('s' => 'search', 'n' => 'new'),
  'theme'     => 'dark'
))->flush();

// Example: specific blog in multisite
$blogOptions = $config->options(array(
  'scope'  => 'blog',
  'entity' => new BlogEntity(2),
));
$blogOptions->add_options(array('feature_flags' => array('beta_ui' => true)))->flush();
