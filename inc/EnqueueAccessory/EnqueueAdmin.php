<?php
/**
 * This class is meant to be implemented and instantiated via the RegisterServices Class.
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
final class EnqueueAdmin extends EnqueueAbstract implements EnqueueInterface {


	/**
	 * A class registration function to add the wp_enqueue_scripts hook to WP.
	 * The hook callback function is $this->enqueue()
	 *
	 * https://developer.wordpress.org/reference/hooks/admin_enqueue_scripts/
	 *
	 * @return void
	 */
	public function load(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}
}
