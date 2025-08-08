<?php
/**
 * HooksAccessory Example: Conditional and grouped registrations
 */

declare(strict_types=1);

use Ran\PluginLib\HooksAccessory\HooksManagementTrait;

class ConditionalGroupedExample {
	use HooksManagementTrait;

	public function register_hooks(): void {
		// Conditional admin-only
		$this->_register_admin_action('admin_init', array($this, 'on_admin_init'));

		// Frontend-only
		$this->_register_frontend_action('wp', array($this, 'on_frontend_wp'));

		// Grouped deferred callbacks
		$this->_register_deferred_hooks(array(
		    'wp_head' => array(
		        'priority' => 10,
		        'callback' => array($this, 'render_head'),
		        'context'  => array('group' => 'deferred')
		    ),
		    'wp_footer' => array(
		        'priority' => 10,
		        'callback' => array($this, 'render_footer'),
		        'context'  => array('group' => 'deferred')
		    ),
		));
	}

	public function on_admin_init(): void {
	}
	public function on_frontend_wp(): void {
	}
	public function render_head(): void {
	}
	public function render_footer(): void {
	}
}

// $ex = new ConditionalGroupedExample();
// $ex->_init_hooks();
