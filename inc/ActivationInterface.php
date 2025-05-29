<?php
/**
 * Interface for the Activation class
 *
 * @package  RanPluginLib
 */

declare(strict_types = 1);

namespace Ran\PluginLib;

/**
 * Interface for the Activation class
 *
 * @param  Config $config the current plugin config instance.
 * @param  mixed ...$args mixed array of arguments.
 *
 * @package  RanPluginLib
 */

use Ran\PluginLib\Config\ConfigInterface;

interface ActivationInterface {
	/**
	 * Static activation method called by WordPress register_activation_hook when the plugin is activated.
	 * This must be called as a static method, ideally in the plugin root file.
	 *
	 * @param  ConfigInterface $config An instance of the Plugin class.
	 * @param  mixed           ...$args Any required arguments.
	 */
	public static function activate( ConfigInterface $config, mixed ...$args ): void;
}
