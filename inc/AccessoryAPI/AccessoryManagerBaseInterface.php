<?php

declare(strict_types=1);
/**
 * AccessoryManagerBaseInterface must be implemented by any Accessory Manager.
 *
 * @package  RanPluginLib
 */

namespace Ran\PluginLib\AccessoryAPI;

interface AccessoryManagerBaseInterface
{
	/**
	 * A Feature's AccessoryManager must have an init method to fire when loaded.
	 *
	 * @param AccessoryBaseInterface $feature Any FeatureController being passed, needs to have an Accessory which extends AccessoryBaseInterface.
	 *
	 * @return void
	 */
	public function init(AccessoryBaseInterface $feature): void;
}
