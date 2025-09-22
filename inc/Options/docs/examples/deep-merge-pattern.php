<?php
/**
 * RegisterOptions Example: Deep merge pattern (read–modify–write)
 *
 * PRACTICAL USE CASE: Safely updating nested configuration structures
 *
 * WordPress options are atomic - you can't update just part of an array.
 * This pattern is ESSENTIAL for:
 * - Theme/plugin customization settings (colors, fonts, layouts)
 * - User preference management (dashboard widgets, column visibility)
 * - API configuration with multiple endpoints
 * - Feature flags and A/B testing configurations
 * - Multi-step form data that builds over time
 *
 * WHY THIS MATTERS:
 * Without this pattern, updating one nested value overwrites the entire structure,
 * losing other settings. This is a common source of "my settings disappeared" bugs.
 *
 * CRITICAL CAVEATS:
 * - array_replace_recursive() can behave unexpectedly with numeric arrays
 * - For arrays with numeric keys, consider array_merge_recursive() instead
 * - For complex merging logic, implement custom merge functions
 * - Always validate the current value is an array before merging
 */

declare(strict_types=1);

use Ran\PluginLib\Config\Config;
use Ran\PluginLib\Options\RegisterOptions;

$config  = Config::fromPluginFile(__FILE__);
$options = new RegisterOptions($config->get_options_key());

// EXAMPLE 1: Theme customization - update colors without losing typography
$current_theme = $options->get_option('theme_config', array());
$color_updates = array(
  'colors' => array(
    'primary'   => '#ff0000',    // User changed primary color
    'secondary' => '#00ff00',    // User changed secondary color
    // Note: 'accent' color remains unchanged from existing config
  ),
  // Note: 'typography', 'layout' sections remain unchanged
);

// Safe merge that preserves existing typography and layout settings
$merged_theme = array_replace_recursive(
	is_array($current_theme) ? $current_theme : array(),
	$color_updates
);
$options->stage_option('theme_config', $merged_theme)->commit_merge();

// EXAMPLE 2: User dashboard preferences - add widget without losing others
$current_dashboard = $options->get_option('dashboard_config', array());
$widget_update     = array(
  'widgets' => array(
    'enabled' => array(
      'new_analytics_widget' => true,  // Add new widget
      // Existing enabled widgets remain unchanged
    )
  )
);

$merged_dashboard = array_replace_recursive(
	is_array($current_dashboard) ? $current_dashboard : array(),
	$widget_update
);
$options->stage_option('dashboard_config', $merged_dashboard)->commit_merge();

// EXAMPLE 3: API endpoints - update one endpoint without affecting others
$current_api     = $options->get_option('api_endpoints', array());
$endpoint_update = array(
  'services' => array(
    'payment_gateway' => array(
      'url'     => 'https://new-payment-api.example.com/v2',  // Updated URL
      'timeout' => 45,  // Updated timeout
      // 'api_key' and other settings remain unchanged
    )
    // Other services (email, analytics, etc.) remain unchanged
  )
);

$merged_api = array_replace_recursive(
	is_array($current_api) ? $current_api : array(),
	$endpoint_update
);

// Batch this with other changes for efficiency
$options->stage_option('api_endpoints', $merged_api)->commit_merge();
// If staging more keys before persisting, prefer staged commit
// $options->stage_option('api_endpoints', $merged_api)->commit_replace();

// NUMERIC ARRAY CAVEAT EXAMPLE:
// BE CAREFUL: array_replace_recursive() with numeric keys can be surprising
// $current = ['items' => [0 => 'apple', 1 => 'banana']];
// $patch = ['items' => [0 => 'orange']];
// Result: ['items' => [0 => 'orange', 1 => 'banana']] - banana survives!
//
// If you want to REPLACE the entire numeric array, do this instead:
// $merged = array_replace_recursive($current, $patch);
// $merged['items'] = $patch['items']; // Force complete replacement
