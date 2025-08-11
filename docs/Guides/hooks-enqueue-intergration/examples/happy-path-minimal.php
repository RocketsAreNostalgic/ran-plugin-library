<?php
/**
 * Hooks × Enqueue minimal integration (happy path)
 */

declare(strict_types = 1);

use Ran\PluginLib\EnqueueAccessory\ScriptsHandler;
use Ran\PluginLib\HooksAccessory\HooksManagementTrait;

class MinimalEnqueueFeature {
	use HooksManagementTrait;

	public function __construct( private ScriptsHandler $scripts ) {
	}

	public function register_hooks(): void {
		// Minimal: register one action to enqueue scripts
		$this->_register_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function enqueue(): void {
		$this->scripts->enqueue_immediate();
	}
}
