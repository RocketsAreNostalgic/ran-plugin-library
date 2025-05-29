<?php
/**
 * AccessoryManagerBaseInterface must be implemented by any Accessory Manager.
 *
 * @package  RanPluginLib
 */

declare(strict_types = 1);

namespace Ran\PluginLib\AccessoryAPI;

use Ran\PluginLib\AccessoryAPI\AccessoryBaseInterface;

interface AccessoryManagerBaseInterface {
	/**
	 * A Feature's AccessoryManager must have an init method to fire when loaded.
	 *
	 * @param AccessoryBaseInterface $feature Any FeatureController being passed, needs to have an Accessory which extends AccessoryBaseInterface.
	 */
	public function init( AccessoryBaseInterface $feature ): void;
}
