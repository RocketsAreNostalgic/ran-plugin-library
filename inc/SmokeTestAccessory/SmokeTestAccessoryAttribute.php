<?php
/**
 * SmokeTestAccessoryAttribute class file.
 *
 * This file contains the SmokeTestAccessoryAttribute class for testing purposes.
 *
 * @package Ran\PluginLib\TestAccessory
 */

declare(strict_types = 1);

namespace Ran\PluginLib\SmokeTestAccessory;
/**
 * Our test attribute
 */
final class SmokeTestAccessoryAttribute {
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
	 * init
	 *
	 * @return void
	 */
	public function init():void {
		echo "<h1>$this->foo</h1>";
		\wp_die();
	}
}
