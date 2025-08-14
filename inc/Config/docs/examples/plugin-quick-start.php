<?php
/**
 * Config Example: Plugin quick start
 *
 * Goal: Initialize Config for a plugin and read normalized fields.
 */

declare(strict_types=1);

use Ran\PluginLib\Config\Config;

// Initialize using factory (e.g., in your main plugin file)
$config = Config::fromPluginFile(__FILE__);

// Core normalized values
$name       = $config->get_config()['Name'];
$version    = $config->get_config()['Version'];
$textDomain = $config->get_config()['TextDomain'];
$slug       = $config->get_config()['Slug'];
$path       = $config->get_config()['PATH'];
$url        = $config->get_config()['URL'];

// Namespaced custom headers (RAN)
$ran          = $config->get_config()['RAN'] ?? array();
$appOptionKey = $ran['AppOption']            ?? $slug; // Fallback to slug

// Use logger that honors RAN.LogConstantName/LogRequestParam
$logger = $config->get_logger();
if ($logger->is_active()) {
	$logger->debug('Config initialized', array('name' => $name, 'version' => $version));
}
