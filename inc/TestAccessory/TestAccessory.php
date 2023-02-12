<?php
/**
 * TestsAccessory is implemented by any FeatureController instance.
 *
 * See: https://carlalexander.ca/designing-classes-wordpress-plugin-api/
 *
 * @package  RanPluginLib
 */

namespace Ran\PluginLib\TestAccessory;

use Ran\PluginLib\AccessoryAPI\AccessoryBaseInterface;

/**
 * TestsAccessory is used by an object that needs to subscribe to WordPress filter hooks.
 */
interface TestAccessory extends AccessoryBaseInterface {

	/**
	 * Test so far...
	 *
	 * @return array
	 */
	public function test():string;
}
