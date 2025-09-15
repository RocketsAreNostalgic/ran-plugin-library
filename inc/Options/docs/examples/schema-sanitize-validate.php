<?php
/**
 * RegisterOptions Example: Sanitization and Validation
 *
 * PRACTICAL USE CASE: Data integrity and security
 *
 * This pattern is CRITICAL for:
 * - API keys and sensitive credentials (format validation, trimming)
 * - User input from forms (XSS prevention, data cleaning)
 * - Configuration values with strict requirements (URLs, email addresses)
 * - Numeric settings with valid ranges (timeouts, limits, percentages)
 * - File paths and directory names (security, path traversal prevention)
 *
 * SECURITY BENEFITS:
 * - Prevents malformed data from breaking your plugin
 * - Stops XSS attacks through option values
 * - Validates API credentials before they're used
 * - Ensures numeric values are within safe ranges
 * - Catches configuration errors early
 *
 * EXECUTION ORDER:
 * 1. Sanitize: Clean/transform the input value
 * 2. Validate: Check if the sanitized value is acceptable
 * 3. If validation fails, throw InvalidArgumentException
 *
 * IMPORTANT: Validation should return true or throw/return false
 */

declare(strict_types=1);

use Ran\PluginLib\Config\Config;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Options\Storage\StorageContext;

$config = Config::fromPluginFile(__FILE__);

// COMPREHENSIVE SCHEMA EXAMPLES:
$schema = array(
    // API Key validation - common for third-party integrations
    'stripe_api_key' => array(
        'default'  => '',
        'sanitize' => fn($v) => is_string($v) ? trim($v) : '',
        'validate' => fn($v) => is_string($v) && preg_match('/^sk_(test_|live_)[a-zA-Z0-9]{24,}$/', $v),
    ),

    // Email validation - for notifications, admin contacts (hypothetical methods)
    'notification_email' => array(
        'default'  => get_option('admin_email', ''),
        'sanitize' => fn($v) => sanitize_email($v),
        'validate' => fn($v) => is_email($v),
    ),

    // URL validation - for webhooks, API endpoints
    'webhook_url' => array(
        'default'  => '',
        'sanitize' => fn($v) => esc_url_raw($v),
        'validate' => fn($v) => filter_var($v, FILTER_VALIDATE_URL) !== false,
    ),

    // Numeric range validation - for timeouts, limits
    'api_timeout' => array(
        'default'  => 30,
        'sanitize' => fn($v) => (int) $v,
        'validate' => fn($v) => is_int($v) && $v >= 5 && $v <= 300, // 5-300 seconds
    ),

    // Percentage validation - for quality settings, rates
    'image_quality' => array(
        'default'  => 85,
        'sanitize' => fn($v) => max(1, min(100, (int) $v)), // Clamp to 1-100
        'validate' => fn($v) => is_int($v) && $v >= 1 && $v <= 100,
    ),

    // Array validation - for feature lists, selected options
    'enabled_features' => array(
        'default'  => array('basic'),
        'sanitize' => fn($v) => is_array($v) ? array_map('sanitize_key', $v) : array('basic'),
        'validate' => fn($v) => is_array($v) && !empty($v) && array_intersect($v, array('basic', 'advanced', 'premium')) === $v,
    ),

    // File path validation - for upload directories, log files
    'upload_directory' => array(
        'default'  => wp_upload_dir()['basedir'] . '/my-plugin',
        'sanitize' => fn($v) => sanitize_text_field($v),
        'validate' => fn($v) => is_string($v) && strpos($v, '..')     === false && // Prevent path traversal
                               strpos($v, wp_upload_dir()['basedir']) === 0, // Must be in uploads
    ),
);

// Construct with autoload preference and attach schema via fluent
$options = RegisterOptions::from_config($config, StorageContext::forSite(), true)
    ->with_schema($schema);

// EXAMPLES OF SANITIZATION AND VALIDATION IN ACTION:

try {
	// This will be trimmed and validated
	$options->set_option('stripe_api_key', '  sk_test_redacted  ');
	echo "✓ API key saved successfully\n";
} catch (InvalidArgumentException $e) {
	echo '✗ API key validation failed: ' . $e->getMessage() . "\n";
}

try {
	// This will be sanitized to a valid email
	$options->set_option('notification_email', ' admin@example.com ');
	echo "✓ Email saved successfully\n";
} catch (InvalidArgumentException $e) {
	echo '✗ Email validation failed: ' . $e->getMessage() . "\n";
}

try {
	// This will fail validation (invalid URL)
	$options->set_option('webhook_url', 'not-a-valid-url');
	echo "✓ Webhook URL saved successfully\n";
} catch (InvalidArgumentException $e) {
	echo '✗ Webhook URL validation failed: ' . $e->getMessage() . "\n";
}

try {
    // This will be clamped to valid range by sanitizer
	$options->set_option('image_quality', 150); // Will become 100
	echo '✓ Image quality saved as: ' . $options->get_option('image_quality') . "\n";
} catch (InvalidArgumentException $e) {
	echo '✗ Image quality validation failed: ' . $e->getMessage() . "\n";
}

// REAL-WORLD FORM PROCESSING EXAMPLE:
if ($_POST['save_settings']) {
	try {
		$options->stage_options(array(
		    'stripe_api_key'     => $_POST['api_key'],
		    'notification_email' => $_POST['admin_email'],
		    'webhook_url'        => $_POST['webhook'],
		    'api_timeout'        => $_POST['timeout'],
		    'enabled_features'   => $_POST['features'] ?? array(),
		        ))->commit_replace();

		wp_redirect(add_query_arg('updated', '1', wp_get_referer()));
	} catch (InvalidArgumentException $e) {
		wp_redirect(add_query_arg('error', urlencode($e->getMessage()), wp_get_referer()));
	}
}
