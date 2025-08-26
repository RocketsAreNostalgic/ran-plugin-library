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

// Create a logger (swap with your PSR-3 logger if desired)
$logger = new CollectingLogger();

// Initialize config with a logger sourced from this plugin file
$config = Config::fromPluginFileWithLogger(__FILE__, $logger);

// Default site-scoped options using the injected logger
$siteOptions = $config->options();
$siteOptions->set_option('feature_enabled', true);

// User-scoped options using the same logger
$userOptions = $config->options(array(
    'scope'       => 'user',
    'user_id'     => get_current_user_id(),
    'user_global' => false,
));
$userOptions->set_option('dashboard_prefs', array('layout' => 'compact'));

// Later (e.g., in tests), inspect logs
var_dump($logger->collected_logs);

// Alternate: construct explicitly passing logger and storage args
use Ran\PluginLib\Options\RegisterOptions;
$explicit = RegisterOptions::from_config(
	$config,
	/* initial */ array(),
	/* autoload */ true,
	/* logger */ $config->get_logger(),
	/* schema */ array(),
	/* scope */ 'user',
	/* storage args */ array('user_id' => get_current_user_id(), 'user_global' => true)
);
