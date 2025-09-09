<?php
/**
 * Config Example: Plugin options (basic writes)
 *
 * Demonstrates how to add/update plugin options using RegisterOptions.
 */

declare(strict_types=1);

use Ran\PluginLib\Config\Config;

// In your plugin bootstrap or a setup routine
$config = Config::fromPluginFile(__FILE__);

// 1) Get a no-write manager for your plugin's option key (site scope by default)
//    You can hint autoload for future creation; this does NOT write by itself
$opts = $config->options(array('autoload' => true));

// 2) Stage some values (no write yet)
$opts->add_options(array(
    'enabled' => true,
    'timeout' => 30,
));

// 3) Persist changes explicitly (single DB write)
$opts->flush();

// Optional: register a schema, then seed defaults and persist in one step
// (If you only want to register validation with no writes, use seed_defaults: false, flush: false)
$opts->register_schema(array(
    'enabled' => array('default' => true, 'validate' => 'is_bool'),
    'timeout' => array('default' => 30,   'validate' => fn($v) => is_int($v) && $v >= 0),
), seed_defaults: true, flush: true);
