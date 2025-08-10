<?php
/**
 * Manager for the SmokeTest accessory.
 *
 * @package  RanPluginLib
 */

declare(strict_types = 1);

namespace Ran\PluginLib\SmokeTestAccessory;

use Ran\PluginLib\AccessoryAPI\AccessoryBaseInterface;
use Ran\PluginLib\AccessoryAPI\AccessoryManagerBaseInterface;

/**
 * Manages Features Objects by registering them with the Plugin class, and loading them.
 */
final class SmokeTestAccessoryManager implements AccessoryManagerBaseInterface {
	/**
	 * Registers an object with the WordPress Plugin API.
	 *
	 * @param AccessoryBaseInterface $object An object that implements either the ActionHookSubscriberInterface or FilterHookSubscriberInterface.
	 */
	public function init( AccessoryBaseInterface $object ): void {
		if ( $object instanceof SmokeTestAccessory ) {
			$this->callback( $object->test() );
		}
	}

	/**
	 * The callback function for event_listener().
	 *
	 * @param array<int|string, mixed> $test_array A nested array of event listener params.
	 */
	private function callback( array $test_array ): void {
		echo '<pre>';
		foreach ( $test_array as $item ) {
			echo wp_kses_post( $item . '<br>' );
		}
		echo '</pre>';
		\wp_die();
	}
}
