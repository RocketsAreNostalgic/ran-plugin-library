<?php
/**
 * Interface for the Bootstrap class.
 *
 * @package  RanPluginLib
 */

declare(strict_types = 1);

namespace Ran\PluginLib;

use Ran\PluginLib\Config\ConfigInterface;

/**
 * Interface for the Bootstrap class.
 * The Bootstrap's init class init method is called on plugin activation.
 */
interface BootstrapInterface {

	/**
	 * The initializing function which should be called by the WordPress register_activation_hook when the plugin is activated.
	 * The init method is ideally called from the the plugin root file, and passed a reference to __FILE__.
	 * It returns a Plugin object which contains all the Plugin's attributes.
	 *
	 * @return PluginInterface
	 */
	public function init(): ConfigInterface;
}
