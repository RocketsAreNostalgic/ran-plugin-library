<?php
/**
 * HooksAccessory Happy Path: single action
 */

declare(strict_types=1);

use Ran\PluginLib\HooksAccessory\HooksManagementTrait;

class HappyPathActionExample {
	use HooksManagementTrait;

	public function register_hooks(): void {
		$this->_register_action('wp_init', array($this, 'boot'));
	}

	public function boot(): void {
	}
}
