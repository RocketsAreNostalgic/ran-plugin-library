<?php
/**
 * Config Example: Theme options (basic writes)
 *
 * Demonstrates how to add/update theme options using RegisterOptions.
 */

declare(strict_types=1);

use Ran\PluginLib\Config\Config;

// In your theme setup (functions.php or a setup file)
$config = Config::fromThemeDir(get_stylesheet_directory());

// 1) Get a no-write manager for your theme's option key (site scope by default)
$opts = $config->options(array('autoload' => true)); // hint autoload for first creation

// 2) Stage some values (no write yet)
$opts->add_options(array(
    'enabled'      => true,
    'color_scheme' => 'dark',
));

// 3) Persist changes explicitly (single DB write)
$opts->flush();

// Optional: register a schema for validation; seed and persist immediately if desired
$opts->register_schema(array(
    'enabled'      => array('default' => true, 'validate' => 'is_bool'),
    'color_scheme' => array('default' => 'light', 'validate' => fn($v) => in_array($v, array('light', 'dark', 'auto'), true)),
), seed_defaults: true, flush: true);
