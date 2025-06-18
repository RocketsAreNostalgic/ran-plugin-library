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
class EnqueuePublic extends AssetEnqueueBaseAbstract implements EnqueueInterface {
	use ScriptsEnqueueTrait, StylesEnqueueTrait, MediaEnqueueTrait;

	/**
	 * A class registration function to add the wp_enqueue_scripts hook to WP.
	 * The hook callback function is $this->enqueue().
	 * Also registers any deferred script hooks.
	 */
	public function load(): void {
		$logger = $this->get_logger();
		if ( $logger->is_active() ) {
			$logger->debug( 'EnqueuePublic::load() - Method entered.' );
		}

		// This should not run in the admin area.
		if ( is_admin() ) {
			if ( $logger->is_active() ) {
				$logger->debug( 'EnqueuePublic::load() - In admin area. Bailing.' );
			}
			return;
		}

		// Register the main enqueue action.
		if ( $logger->is_active() ) {
			$logger->debug( 'EnqueuePublic::load() - Hooking enqueue() to wp_enqueue_scripts.' );
		}
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );

		// Register head callbacks if any exist.
		if ( $logger->is_active() ) {
			$logger->debug( 'EnqueuePublic::load() - Checking for head callbacks. Count: ' . count( $this->head_callbacks ) );
		}
		if ( ! empty( $this->head_callbacks ) ) {
			if ( $logger->is_active() ) {
				$logger->debug( 'EnqueuePublic::load() - Hooking render_head() to wp_head.' );
			}
			add_action( 'wp_head', array( $this, 'render_head' ) );
		}

		// Register footer callbacks if any exist.
		if ( $logger->is_active() ) {
			$logger->debug( 'EnqueuePublic::load() - Checking for footer callbacks. Count: ' . count( $this->footer_callbacks ) );
		}
		if ( ! empty( $this->footer_callbacks ) ) {
			if ( $logger->is_active() ) {
				$logger->debug( 'EnqueuePublic::load() - Hooking render_footer() to wp_footer.' );
			}
			add_action( 'wp_footer', array( $this, 'render_footer' ) );
		}

		// Register deferred script hooks.
		if ( $logger->is_active() ) {
			$logger->debug( 'EnqueuePublic::load() - Checking for deferred script hooks. Count: ' . count( $this->deferred_scripts ) );
		}
		foreach ( array_keys( $this->deferred_scripts ) as $hook ) {
			// Use a closure to capture the current hook.
			if ( $logger->is_active() ) {
				$logger->debug( "EnqueuePublic::load() - Hooking enqueue_deferred_scripts() to action '{$hook}'." );
			}
			add_action(
				$hook,
				function () use ( $hook ): void {
					$this->enqueue_deferred_scripts( $hook );
				}
			);
		}

		if ( $logger->is_active() ) {
			$logger->debug( 'EnqueuePublic::load() - Method exited.' );
		}
	}
}
