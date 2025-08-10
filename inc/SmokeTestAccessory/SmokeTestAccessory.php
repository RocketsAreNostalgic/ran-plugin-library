<?php
/**
 * SmokeTestAccessory is implemented by any FeatureController instance.
 * See: https://carlalexander.ca/designing-classes-wordpress-plugin-api/
 *
 * @package  RanPluginLib
 */

declare(strict_types = 1);

namespace Ran\PluginLib\SmokeTestAccessory;

use Ran\PluginLib\AccessoryAPI\AccessoryBaseInterface;

/**
 * SmokeTestAccessory is used by an object that needs to subscribe to WordPress filter hooks.
 */
interface SmokeTestAccessory extends AccessoryBaseInterface {
	/**
	 * Returns lines to output for the smoke test.
	 *
	 * @return array<int|string, mixed>
	 */
	public function test(): array;
}
