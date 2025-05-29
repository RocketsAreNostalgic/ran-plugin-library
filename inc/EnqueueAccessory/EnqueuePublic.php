<?php
/**
 * EnqueuePublic class adds scripts with the wp_enqueue_scripts WP hook.
 *
 * @package  RanPluginLib
 */

declare(strict_types = 1);

namespace Ran\PluginLib\EnqueueAccessory;

/**
 * This class is meant to be implemented and instantiated via the RegisterServices Class.
 *
 * @package  RanPluginLib
 */
final class EnqueuePublic extends EnqueueAbstract implements EnqueueInterface {
	/**
	 * A class registration function to add the wp_enqueue_scripts hook to WP.
	 * The hook callback function is $this->enqueue().
	 * Also registers any deferred script hooks.
	 */
	public function load(): void {
		// Register the main enqueue action.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );

		// Register head callbacks if any exist.
		if ( ! empty( $this->head_callbacks ) ) {
			add_action( 'wp_head', array( $this, 'render_head' ) );
		}

		// Register footer callbacks if any exist.
		if ( ! empty( $this->footer_callbacks ) ) {
			add_action( 'wp_footer', array( $this, 'render_footer' ) );
		}

		// Register deferred script hooks.
		foreach ( array_keys( $this->deferred_scripts ) as $hook ) {
			// Use a closure to capture the current hook.
			add_action(
				$hook,
				function () use ( $hook ): void {
					$this->enqueue_deferred_scripts( $hook );
				}
			);
		}
	}
}
