<?php
/**
 * Example: Admin-only enqueue using conditional helper
 */

declare(strict_types=1);

use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\EnqueueAccessory\StylesHandler;
use Ran\PluginLib\HooksAccessory\HooksManagementTrait;

class AdminAssetsFeature {
	use HooksManagementTrait;

	public function __construct(private StylesHandler $styles) {
	}

	public function register_hooks(): void {
		// Only register for admin context
		$this->_register_admin_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
	}

	public function enqueue_admin_assets(): void {
		$this->styles->enqueue_immediate();
	}
}

/** @var ConfigInterface $config */
// $styles = new StylesHandler($config);
// (new AdminAssetsFeature($styles))->_init_hooks();
