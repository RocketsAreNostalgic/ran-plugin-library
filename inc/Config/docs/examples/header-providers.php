<?php
/**
 * Config Example: Header providers (advanced)
 *
 * Shows how Config gets its data from providers for plugin or theme environments.
 */

declare(strict_types=1);

use Ran\PluginLib\Config\Config;

// Plugin mode (default)
$cfgPlugin = Config::fromPluginFile(__FILE__)->get_config();

// Theme mode
$cfgTheme = Config::fromThemeDir(get_stylesheet_directory())->get_config();

// Both provide normalized arrays but with environment-specific keys
// - Plugin: Basename, File, PATH, URL
// - Theme: StylesheetDir, StylesheetURL
