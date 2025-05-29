<?php
/**
 * Filter Hooks Manager for WordPress plugins.
 *
 * This file contains the FilterHooksManager class which manages objects that implement
 * the FilterHooksInterface for registering WordPress filter hooks.
 *
 * @package  RanPluginLib
 * @author   https://carlalexander.ca/polymorphism-wordpress-interfaces/
 */

declare(strict_types = 1);

/**
 * Manage the implementation of Filter and Action Hooks.
 *
 * @author https://carlalexander.ca/polymorphism-wordpress-interfaces/
 *
 * @package  RanPluginLib
 */

namespace Ran\PluginLib\HooksAccessory;

use Ran\PluginLib\AccessoryAPI\AccessoryBaseInterface;

/**
 * FilterHooksAttributeManager manages object that implement objects that would like to register Wordpress hooks
 * by implementing FilterHooksAttributeInterface.
 */
class FilterHooksManager implements AccessoryBaseInterface {
	/**
	 * Registers an object with the WordPress Plugin API.
	 *
	 * @param mixed $object An object that implements the FilterHooksInterface.
	 */
	public function init( mixed $object ): void {
		if ( $object instanceof FilterHooksInterface ) {
			$this->register_filters( $object );
		}
	}

	/**
	 * Register an object with a specific filter hook.
	 *
	 * @param FilterHooksInterface $object Any object the implements the FilterHooksInterface.
	 * @param string               $name The name of the filter hook.
	 * @param mixed                $parameters the hook parameters.
	 */
	private function register_filter( FilterHooksInterface $object, string $name, mixed $parameters ): void {
		if ( is_string( $parameters ) ) {
			add_filter( $name, array( $object, $parameters ) );
		} elseif ( is_array( $parameters ) && isset( $parameters[0] ) ) {
			add_filter( $name, array( $object, $parameters[0] ), isset( $parameters[1] ) ? $parameters[1] : 10, isset( $parameters[2] ) ? $parameters[2] : 1 );
		}
	}

	/**
	 * Registers an object with all its filter hooks.
	 *
	 * @param FilterHooksInterface $object Any object the implements the FilterHooksInterface.
	 */
	private function register_filters( FilterHooksInterface $object ): void {
		foreach ( $object->get_filter() as $name => $parameters ) {
			$this->register_filter( $object, $name, $parameters );
		}
	}
}
