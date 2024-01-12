<?php

declare(strict_types=1);
/**
 * Manage the implementation of Filter and Action Hooks.
 *
 * @author https://carlalexander.ca/polymorphism-wordpress-interfaces/
 *
 *  @package  RanPluginLib
 */

namespace Ran\PluginLib\HooksAccessory;

use Ran\PluginLib\AccessoryAPI\AccessoryBaseInterface;
use Ran\PluginLib\HooksAccessory\ActionHooksInterface;

/**
 * HooksAPIManager manages object that implement objects that would like to register Wordpress hooks
 * by implementing ActionHookAssetInterface.
 */
class ActionHooksManager implements AccessoryBaseInterface
{
	/**
	 * Registers an object with the WordPress Plugin API.
	 *
	 * @param AssetBaseInterface $object An object that implements either the AssetBaseInterface or FilterHookAssetInterface.
	 */
	public function init(ActionHooksInterface $object): void
	{
		if ($object instanceof ActionHooksInterface) {
			$this->register_actions($object);
		}
	}

	/**
	 * Register an object with a specific action hook.
	 *
	 * @param ActionHooksInterface $object Any object the implements the ActionHookAssetInterface.
	 * @param string               $name The name of the action hook.
	 * @param mixed                $parameters The hook parameters.
	 */
	private function register_action(ActionHooksInterface $object, $name, $parameters)
	{
		if (is_string($parameters)) {
			add_action($name, array($object, $parameters));
		} elseif (is_array($parameters) && isset($parameters[0])) {
			add_action($name, array($object, $parameters[0]), isset($parameters[1]) ? $parameters[1] : 10, isset($parameters[2]) ? $parameters[2] : 1);
		}
	}

	/**
	 * Registers an object with all its action hooks.
	 *
	 * @param ActionHooksInterface $object Any object the implements the ActionHookAssetInterface.
	 */
	private function register_actions(ActionHooksInterface $object)
	{
		foreach ($object->get_actions() as $name => $parameters) {
			$this->register_action($object, $name, $parameters);
		}
	}
}
