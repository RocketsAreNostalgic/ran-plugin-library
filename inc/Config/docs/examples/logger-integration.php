<?php
/**
 * Config Example: Logger integration
 */

declare(strict_types=1);

use Ran\PluginLib\Config\Config;
use Ran\PluginLib\Util\Logger;

$config = Config::fromPluginFile(__FILE__);

// Built-in logger honors RAN.LogConstantName and RAN.LogRequestParam
$logger = $config->get_logger();
$logger->debug('Example debug message');

// Swap to a compatible custom logger (implements same ctor signature/options)
$custom = new Logger(array('custom_debug_constant_name' => 'MY_DEBUG', 'debug_request_param' => 'debug'));
// If you need to globally use your custom logger, inject it where your classes accept a Logger
