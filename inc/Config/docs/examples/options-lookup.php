<?php
/**
 * Config Example: Retrieve options key via Config and read options
 */

declare(strict_types=1);

use Ran\PluginLib\Config\Config;

$config = Config::fromPluginFile(__FILE__);

// Preferred: resolve the unified option key via helper
// get_options_key() returns the key string to use when storing/retrieving options.
// It prefers RAN.AppOption (if defined in headers) and falls back to the Slug.
$key = $config->get_options_key();

// Access options directly via WordPress or via helper
$current = function_exists('get_option') ? get_option($key, array()) : array();
// Or simply:
// get_options($default) returns the option value under that key, using get_options_key() internally.
$current = $config->get_options(array());

// $current now contains your plugin options array stored under the unified key
// Example: access a specific value (with a default fallback)
$enabled = (bool) ($current['enabled'] ?? false);
