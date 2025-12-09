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
	 * A Feature's AccessoryManager must have an init() method to run when the accessory is enabled for a feature.
	 * Called by the FeaturesManager during accessory enablement, before the feature's own init() is invoked.
	 *
	 * @param AccessoryBaseInterface $feature FeatureController instance implementing the specific Accessory interface
	 *                                       (which extends AccessoryBaseInterface).
	 */
	public function init( AccessoryBaseInterface $feature ): void;
}
