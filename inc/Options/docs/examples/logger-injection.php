<?php
/**
 * Example: Logger injection for options
 *
 * Shows how to initialize Config with a logger and have that logger used by
 * options instances created via Config::options() or RegisterOptions::from_config().
 * Useful for debugging, testing, or capturing operational telemetry.
 */

declare(strict_types=1);

use Ran\PluginLib\Config\Config;
use Ran\PluginLib\Util\CollectingLogger;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Options\Storage\StorageContext;

// Create a logger (swap with your PSR-3 logger if desired)
$logger = new CollectingLogger();

// Initialize config with a logger sourced from this plugin file
$config = Config::fromPluginFileWithLogger(__FILE__, $logger);

// Default site-scoped options using the injected logger
$siteOptions = $config->options(StorageContext::forSite(), true);
$siteOptions->set_option('feature_enabled', true);

// User-scoped options using the same logger
$userOptions = $config->options(
	StorageContext::forUser((int) get_current_user_id(), 'meta', false),
	false
);
$userOptions->set_option('dashboard_prefs', array('layout' => 'compact'));

// Later (e.g., in tests), inspect logs
var_dump($logger->collected_logs);

// Alternate: construct explicitly with typed context (logger comes from Config)
$explicit = RegisterOptions::from_config(
	$config,
	StorageContext::forUser((int) get_current_user_id(), 'meta', true),
	false
);
