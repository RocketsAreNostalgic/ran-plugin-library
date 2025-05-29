<?php
/**
 * DeactivationInterface is used by an object that needs to perform actions when the plugin is deactivated.

 * @package  RanPluginLib
 */

declare(strict_types = 1);

namespace Ran\PluginLib;

use Ran\PluginLib\Config\ConfigInterface;

/**
 * Interface for the Deactivation class
 *
 * @param  Config $config the current plugin config instance.
 * @param  mixed  ...$args mixed array of arguments.
 *
 * @package  RanPluginLib
 */
interface DeactivationInterface {
	/**
	 * Deactivation function called by WordPress register_deactivation_hook when the plugin is deactivated.
	 * This must be called as a static method, ideally in the plugin root file or Bootstrap.php
	 *
	 * @param  ConfigInterface $config the config instance.
	 * @param  mixed           ...$args mixed array of arguments.
	 */
	public static function deactivate( ConfigInterface $config, mixed ...$args ): void;
}
