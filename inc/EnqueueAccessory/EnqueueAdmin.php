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
class EnqueueAdmin extends EnqueueAbstract implements EnqueueInterface {
	/**
	 * A class registration function to add the admin_enqueue_scripts hook to WP.
	 * The hook callback function is $this->enqueue().
	 * Also registers any deferred script hooks.
	 *
	 * @since 1.0.0
	 * @link https://developer.wordpress.org/reference/hooks/admin_enqueue_scripts/
	 */
	public function load(): void {
		$logger = $this->get_logger();
		if ( $logger->is_active() ) {
			$logger->debug( 'EnqueueAdmin::load() - Method entered.' );
		}

		if ( ! is_admin() ) {
			if ( $logger->is_active() ) {
				$logger->debug( 'EnqueueAdmin::load() - Not an admin request. Bailing.' );
			}
			return;
		}

		// Handle main asset enqueueing.
		if ( did_action( 'admin_enqueue_scripts' ) ) {
			if ( $logger->is_active() ) {
				$logger->debug( 'EnqueueAdmin::load() - admin_enqueue_scripts already fired. Calling enqueue() directly.' );
			}
			$this->enqueue();
		} else {
			if ( $logger->is_active() ) {
				$logger->debug( 'EnqueueAdmin::load() - Hooking enqueue() to admin_enqueue_scripts.' );
			}
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		}

		// Register head callbacks if any exist.
		if ( ! empty( $this->head_callbacks ) ) {
			if ( $logger->is_active() ) {
				$logger->debug( 'EnqueueAdmin::load() - Checking for head callbacks. Count: ' . count( $this->head_callbacks ) );
			}
			if ( did_action( 'admin_head' ) ) {
				if ( $logger->is_active() ) {
					$logger->debug( 'EnqueueAdmin::load() - admin_head already fired. Calling render_head() directly.' );
				}
				$this->render_head();
			} else {
				if ( $logger->is_active() ) {
					$logger->debug( 'EnqueueAdmin::load() - Hooking render_head() to admin_head.' );
				}
				add_action( 'admin_head', array( $this, 'render_head' ) );
			}
		}

		// Register footer callbacks if any exist.
		if ( ! empty( $this->footer_callbacks ) ) {
			if ( $logger->is_active() ) {
				$logger->debug( 'EnqueueAdmin::load() - Checking for footer callbacks. Count: ' . count( $this->footer_callbacks ) );
			}
			if ( did_action( 'admin_footer' ) ) {
				if ( $logger->is_active() ) {
					$logger->debug( 'EnqueueAdmin::load() - admin_footer already fired. Calling render_footer() directly.' );
				}
				$this->render_footer();
			} else {
				if ( $logger->is_active() ) {
					$logger->debug( 'EnqueueAdmin::load() - Hooking render_footer() to admin_footer.' );
				}
				add_action( 'admin_footer', array( $this, 'render_footer' ) );
			}
		}

		if ( $logger->is_active() ) {
			$logger->debug( 'EnqueueAdmin::load() - Method exited.' );
		}
	}
}
