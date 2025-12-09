<?php
/**
 * Config Example: Plugin options (basic writes)
 *
 * Demonstrates how to add/update plugin options using RegisterOptions.
 */

declare(strict_types=1);

use Ran\PluginLib\Config\Config;
use Ran\PluginLib\Options\Storage\StorageContext;

// In your plugin bootstrap or a setup routine
$config = Config::fromPluginFile(__FILE__);

// 1) Get a no-write manager for your plugin's option key (site scope by default)
//    Use typed StorageContext; pass autoload preference for creation-time only.
$opts = $config->options(StorageContext::forSite(), true);

// 2) Stage some values (no write yet)
$opts->stage_options(array(
    'enabled' => true,
    'timeout' => 30,
));

// 3) Persist changes explicitly (single DB write)
$opts->commit_replace();

// Optional: register a schema, seed/normalize in-memory, then persist explicitly
$opts->with_schema(array(
    'enabled' => array('default' => true, 'validate' => 'is_bool'),
    'timeout' => array('default' => 30,   'validate' => fn($v) => is_int($v) && $v >= 0),
));
$opts->commit_replace();
