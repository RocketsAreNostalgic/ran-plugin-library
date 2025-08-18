<?php
/**
 * Config Example: Theme quick start
 *
 * Goal: Initialize Config for a theme and read normalized fields.
 */

declare(strict_types=1);

use Ran\PluginLib\Config\Config;

// Initialize using factory (e.g., in functions.php)
$config = Config::fromThemeDir(get_stylesheet_directory());

// Optional: initialize with a custom logger during hydration
// $logger = new \Ran\PluginLib\Util\Logger(array('custom_debug_constant_name' => 'MY_DEBUG', 'debug_request_param' => 'debug'));
// $config = Config::fromThemeDirWithLogger(get_stylesheet_directory(), $logger);

$cfg = $config->get_config();

$themeName = $cfg['Name'];
$version   = $cfg['Version'];
$slug      = $cfg['Slug'];
$dir       = $cfg['StylesheetDir'];
$url       = $cfg['StylesheetURL'];

$logger = $config->get_logger();
$logger->debug('Theme Config ready', array('name' => $themeName, 'version' => $version));
