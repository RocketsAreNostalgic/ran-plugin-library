<?php
/**
 * @package  RanPluginLib
 */

namespace Ran\PluginLib;

/**
 * This class is meant to be implemented and instantiated via the RegisterServices Class.
 *
 * @package  RanPluginLib
 */
final class EnqueueAdmin extends EnqueueAbstract implements EnqueueInterface {

	/**
	 * A class registration function to add the wp_enqueue_scripts hook to WP.
	 * The hook callback function is $this->enqueue()
	 *
	 * @return void
	 */
	public function load():void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}
}
