<?php
/**
 * Example: Method-based public assets enqueue using HooksManagementTrait
 */

declare(strict_types = 1);

use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\EnqueueAccessory\ScriptsHandler;
use Ran\PluginLib\HooksAccessory\HooksManagementTrait;

class PublicAssetsFeature {
	use HooksManagementTrait;

	public function __construct( private ScriptsHandler $scripts ) {
	}

	public function register_hooks(): void {
		// Register our own method to run on wp_enqueue_scripts
		$this->_register_action_method( 'wp_enqueue_scripts', 'enqueue_public_assets', 10, 0 );
	}

	public function enqueue_public_assets(): void {
		// Enqueue immediately using the handler's APIs
		$this->scripts->enqueue_immediate();
	}
}

/** @var ConfigInterface $config */
// $scripts = new ScriptsHandler($config);
// (new PublicAssetsFeature($scripts))->_init_hooks();
