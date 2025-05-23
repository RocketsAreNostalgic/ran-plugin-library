<?php
/**
 * TestAccessoryAttribute class file.
 *
 * This file contains the TestAccessoryAttribute class for testing purposes.
 *
 * @package Ran\PluginLib\TestAccessory
 */

declare(strict_types = 1);

namespace Ran\PluginLib\TestAccessory;

use Ran\PluginLib\AccessoryAPI\AccessoryBaseInterface;
use Ran\PluginLib\TestAccessory\TestAccessoryInterface;

/**
 * Our test Attribute
 */
final class TestAccessoryAttribute implements TestAccessoryInterface {

	/**
	 * Our constructor.
	 *
	 * @param  string $foo A string.
	 */
	public function __construct(
		public string $foo,
	) {
		echo wp_kses_post( "<h1>{$foo}</h1>" );
		\wp_die();
	}

	/**
	 * Returns test data for the accessory.
	 *
	 * @return array<int|string, mixed> Array of test data.
	 */
	public function test(): array {
		return [
			'test_message' => "Testing {$this->foo}",
			'timestamp' => time(),
		];
	}
}
