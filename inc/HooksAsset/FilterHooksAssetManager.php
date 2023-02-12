<?php
/**
 * Manage the implementation of Filter and Action Hooks.
 *
 * @author https://carlalexander.ca/polymorphism-wordpress-interfaces/
 *
 *  @package  RanPluginLib
 */

namespace Ran\PluginLib\HooksAttribute;

use Ran\PluginLib\AttributesAPI\AttributeManagerInterface;

/**
 * FilterHooksAttributeManager manages object that implement objects that would like to register Wordpress hooks
 * by implementing FilterHooksAttributeInterface.
 */
class FilterHooksAttributeManager implements AttributeManagerInterface {

	/**
	 * Registers an object with the WordPress Plugin API.
	 *
	 * @param mixed $object An object that implements either the ActionHookAttributeInterface or FilterHookAttributeInterface.
	 */
	public function init( $object ) {
		if ( $object instanceof FilterHookAttributeInterface ) {
			$this->register_filters( $object );
		}
	}

	/**
	 * Register an object with a specific filter hook.
	 *
	 * @param FilterHooksAttributeInterface $object Any object the implements the FilterHookAttributeInterface.
	 * @param string                        $name The name of the filter hook.
	 * @param mixed                         $parameters the hook parameters.
	 */
	private function register_filter( FilterHooksAttributeInterface $object, $name, $parameters ) {
		if ( is_string( $parameters ) ) {
			add_filter( $name, array( $object, $parameters ) );
		} elseif ( is_array( $parameters ) && isset( $parameters[0] ) ) {
			add_filter( $name, array( $object, $parameters[0] ), isset( $parameters[1] ) ? $parameters[1] : 10, isset( $parameters[2] ) ? $parameters[2] : 1 );
		}
	}

	/**
	 * Registers an object with all its filter hooks.
	 *
	 * @param FilterHooksAttributeInterface $object Any object the implements the FilterHookAttributeInterface.
	 */
	private function register_filters( FilterHooksAttributeInterface $object ) {
		foreach ( $object->get_filter() as $name => $parameters ) {
			$this->register_filter( $object, $name, $parameters );
		}
	}
}
