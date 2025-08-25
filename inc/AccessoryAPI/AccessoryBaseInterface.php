<?php
/**
 * Base marker interface for Accessory interfaces.
 *
 * Accessory interfaces should extend this interface. FeatureControllers implement those
 * accessory interfaces (not this base) to opt into specific capabilities.
 *
 * @package  RanPluginLib
 */

declare(strict_types = 1);

namespace Ran\PluginLib\AccessoryAPI;

/**
 * Marker interface used to confirm that an Accessory interface is constructed correctly and to
 * serve as a common ancestor for future additions to the API. Not intended to be implemented by
 * FeatureControllers directly—implement an accessory interface that extends this instead.
 */
interface AccessoryBaseInterface {
}
