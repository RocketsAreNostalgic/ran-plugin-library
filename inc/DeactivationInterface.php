<?php
/**
 * @package  RanPluginLib
 */

namespace Ran\PluginLib;

/**
 * Interface for the Deactivation class
 *
 * @param  Plugin $plugin the current plugin instance.
 * @param  mixed  ...$args mixed array of arguments.
 *
 * @package  RanPluginLib
 */
interface DeactivationInterface {

	/**
	 * Deactivation function called by WordPress register_deactivation_hook when the plugin is deactivated.
	 * This must be called as a static method, ideally in the plugin root file or Bootstrap.php
	 *
	 * @return void
	 */
	public static function deactivate( $plugin, ...$args): void;
}
