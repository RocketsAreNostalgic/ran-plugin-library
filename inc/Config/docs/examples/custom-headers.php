<?php
/**
 * Config Example: Namespaced custom headers (@<Namespace>: Name: Value)
 */

declare(strict_types=1);

use Ran\PluginLib\Config\Config;

$config = Config::fromPluginFile(__FILE__);
$cfg    = $config->get_config();

// All @RAN: headers appear under $cfg['RAN'] with normalized keys
$ran      = (array)($cfg['RAN'] ?? array());
$logConst = $ran['LogConstantName'] ?? 'RAN_LOG';
$logParam = $ran['LogRequestParam'] ?? 'ran_log';
$appKey   = $ran['AppOption']       ?? $cfg['Slug'];

// Other namespaces (e.g., @Acme:) appear as top-level arrays too
$acme        = (array)($cfg['Acme'] ?? array());
$featureFlag = $acme['FeatureFlag'] ?? 'off';

// Example consumption
$logger = $config->get_logger();
$logger->debug('Headers', compact('logConst', 'logParam', 'appKey', 'featureFlag'));
