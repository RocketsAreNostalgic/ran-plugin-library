<?php
/**
 * EnqueueAdmin class file.
 *
 * This file contains the EnqueueAdmin class for handling admin script and style enqueuing.
 *
 * @package  Ran\PluginLib\EnqueueAccessory
 */

declare(strict_types = 1);

namespace Ran\PluginLib\EnqueueAccessory;

/**
 * Class for handling admin script and style enqueuing.
 *
 * This class is meant to be implemented and instantiated via the RegisterServices Class.
 *
 * @since 1.0.0
 * @package Ran\PluginLib\EnqueueAccessory
 */
final class EnqueueAdmin extends EnqueueAbstract implements EnqueueInterface {


	/**
	 * A class registration function to add the admin_enqueue_scripts hook to WP.
	 * The hook callback function is $this->enqueue().
	 *
	 * @since 1.0.0
	 * @link https://developer.wordpress.org/reference/hooks/admin_enqueue_scripts/
	 */
	public function load(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}
}
