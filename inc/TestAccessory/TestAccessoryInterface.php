<?php
/**
 * Interface for the TestAccessory.
 *
 * @package  RanPluginLib
 */

declare(strict_types = 1);

namespace Ran\PluginLib\TestAccessory;

use Ran\PluginLib\AccessoryAPI\AccessoryBaseInterface;

/**
 * Interface for Test Accessory functionality.
 * 
 * This interface defines the contract for classes that implement test functionality.
 */
interface TestAccessoryInterface extends AccessoryBaseInterface {

	/**
	 * Returns test data for the accessory.
	 *
	 * @return array<int|string, mixed> Array of test data.
	 */
	public function test(): array;
}
