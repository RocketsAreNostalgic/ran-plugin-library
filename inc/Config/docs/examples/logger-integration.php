<?php
/**
 * Config Example: Logger integration
 */

declare(strict_types=1);

use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Config\Config;

$config = Config::fromPluginFile(__FILE__);

// Built-in logger honors RAN.LogConstantName and RAN.LogRequestParam
$logger = $config->get_logger();
$logger->debug('Example debug message');

// Use a custom-configured Logger; to use it during hydration, prefer the WithLogger factory:
$custom           = new Logger(array('custom_debug_constant_name' => 'MY_DEBUG', 'debug_request_param' => 'debug'));
$configWithLogger = Config::fromPluginFileWithLogger(__FILE__, $custom);
$configWithLogger->get_logger()->info('Custom logger attached via factory');
// If you need to globally use your custom logger, inject it where your classes accept a Logger
