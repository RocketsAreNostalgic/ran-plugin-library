<?php
/**
 * ActionHooksInterface is used by an object that needs to subscribe to
 * WordPress action hooks.
 *
 * @author https://carlalexander.ca/polymorphism-wordpress-interfaces/
 *
 *  @package  RanPluginLib
 */

namespace Ran\PluginLib\HooksAsset;

use Ran\PluginLib\AssetsAPI\AssetBaseInterface;

/**
 * ActionHooksInterface is used by an object that needs to subscribe to WordPress action hooks.
 */
interface ActionHooksInterface extends AssetBaseInterface {

	/**
	 * Returns an array of actions that the object needs to be subscribed to.
	 *
	 * The array key is the name of the action hook. The value can be:
	 *
	 *  * The method name
	 *  * An array with the method name and priority
	 *  * An array with the method name, priority and number of accepted arguments
	 *
	 * For instance, in the context of your FeatureController:
	 *
	 *  * array('action_name' => array($this => 'method_name')
	 *  * array('action_name' => array(array($this => 'method_name'), $priority))
	 *  * array('action_name' => array(array($this => 'method_name'), $priority, $accepted_args))
	 *
	 *  Here 'method_name' is the name of your public callback method found your FeatureController.
	 *
	 * @return array
	 */
	public static function get_actions():array;

}
