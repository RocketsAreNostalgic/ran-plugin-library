<?php
/**
 * Config Example: Retrieve options key via Config and read options
 */

declare(strict_types=1);

use Ran\PluginLib\Config\Config;

$config = Config::fromPluginFile(__FILE__);
$cfg    = $config->get_config();

$ran     = (array)($cfg['RAN'] ?? array());
$appKey  = $ran['AppOption'] ?? $cfg['Slug'];
$current = function_exists('get_option') ? get_option($appKey, array()) : array();

// $current now contains your plugin options array stored under the unified key
