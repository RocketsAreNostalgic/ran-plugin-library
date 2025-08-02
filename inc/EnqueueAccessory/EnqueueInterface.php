<?php
/**
 * EnqueueInterface file.
 *
 * This file contains the interface for enqueueing scripts and styles in WordPress.
 *
 * @package  Ran\PluginLib\EnqueueAccessory
 */

declare(strict_types = 1);

namespace Ran\PluginLib\EnqueueAccessory;

/**
 * An interface for enqueueing scripts and styles, which can be instantiated via the RegisterServices Class.
 *
 * @since 0.1.0
 */
interface EnqueueInterface {
	/**
	 * A class registration function to add scripts and styles via wp_enqueue_* hooks to WP.
	 * The hook callback function is $this->enqueue()
	 *
	 * @since 0.1.0
	 */
	public function load(): void;


	/**
	 * Returns the ScriptsHandler instance.
	 *
	 * @since 0.1.0
	 *
	 * @return ScriptsHandler
	 */
	public function scripts(): ScriptsHandler;

	/**
	 * Returns the ScriptModulesHandler instance.
	 *
	 * @since 0.1.0
	 *
	 * @return ScriptModulesHandler
	 */
	public function script_modules(): ScriptModulesHandler;

	/**
	 * Returns the StylesHandler instance.
	 *
	 * @since 0.1.0
	 *
	 * @return StylesHandler
	 */
	public function styles(): StylesHandler;

	/**
	 * Returns the MediaHandler instance.
	 *
	 * @since 0.1.0
	 */
	// public function media(): MediaHandler;
}
