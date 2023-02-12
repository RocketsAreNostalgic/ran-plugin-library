<?php
/**
 * @package  RanPluginLib
 */

namespace Ran\PluginLib;

use Ran\PluginLib\Plugin\PluginInterface;

/**
 * Interface for the Bootstrap class.
 * The Bootstrap class should ideally have only one method called init where all the configuration takes place.
 *
 * @package  RanPluginLib
 */
interface BootstrapInterface {

	/**
	 * The initializing function which should be called by the WordPress register_activation_hook when the plugin is activated.
	 * The init method is ideally called from the the plugin root file, and passed a reference to __FILE__.
	 * It returns a Plugin object which contains all the Plugin's attributes.
	 *
	 * @return PluginInterface
	 */
	public function init():PluginInterface;
}
