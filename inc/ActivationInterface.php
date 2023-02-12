<?php
/**
 * @package  RanPluginLib
 */

namespace Ran\PluginLib;

/**
 * Interface for the Activation class
 *
 * @param  Plugin $plugin the current plugin instance.
 * @param  mixed ...$args mixed array of arguments.
 *
 * @package  RanPluginLib
 */

use Ran\PluginLib\Plugin\PluginInterface;

interface ActivationInterface {

	/**
	 * Static activation method called by WordPress register_activation_hook when the plugin is activated.
	 * This must be called as a static method, ideally in the plugin root file.
	 *
	 * @param  PluginInterface $plugin An instance of the Plugin class.
	 * @param  mixed           ...$args Any required arguments.
	 *
	 * @return void
	 */
	public static function activate( PluginInterface $plugin, mixed ...$args ): void;
}
