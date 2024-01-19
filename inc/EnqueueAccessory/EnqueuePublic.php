<?php
/**
 * EnqueuePublic class adds scripts with the wp_enqueue_scripts WP hook.
 *
 * @package  RanPluginLib
 */

declare(strict_types=1);

namespace Ran\PluginLib\EnqueueAccessory;

/**
 * This class is meant to be implemented and instantiated via the RegisterServices Class.
 *
 * @package  RanPluginLib
 */
final class EnqueuePublic extends EnqueueAbstract implements EnqueueInterface {


	/**
	 * A class registration function to add the wp_enqueue_scripts hook to WP.
	 * The hook callback function is $this->enqueue()
	 *
	 */
	public function load(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}
}
