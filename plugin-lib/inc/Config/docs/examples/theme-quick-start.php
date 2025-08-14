<?php
/**
 * Config Example: Theme quick start
 *
 * Goal: Initialize Config for a theme and read normalized fields.
 */

declare(strict_types=1);

use Ran\PluginLib\Config\Config;

// Initialize once early (e.g., functions.php)
Config::init_theme(get_stylesheet_directory());

$config = Config::get_instance();
$cfg    = $config->get_config();

$themeName = $cfg['Name'];
$version   = $cfg['Version'];
$slug      = $cfg['Slug'];
$dir       = $cfg['StylesheetDir'];
$url       = $cfg['StylesheetURL'];

$logger = $config->get_logger();
$logger->debug('Theme Config ready', array('name' => $themeName, 'version' => $version));
