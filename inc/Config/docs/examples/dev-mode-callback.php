<?php
/**
 * Config Example: Dev-mode detection with programmatic callback
 */

declare(strict_types=1);

use Ran\PluginLib\Config\Config;

$config = Config::fromPluginFile(__FILE__);

// Preferred: programmatic callback (overrides all other checks)
$config->set_is_dev_callback(function(): bool {
	return defined('WP_DEBUG') && WP_DEBUG; // customize as needed
});

if ($config->is_dev_environment()) {
	$config->get_logger()->debug('Development mode active');
}
