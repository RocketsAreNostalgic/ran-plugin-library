<?php
/**
 * HooksAccessory Example: Bulk registrations via register_hooks_bulk
 */

declare(strict_types=1);

use Ran\PluginLib\HooksAccessory\HooksManagementTrait;

class BulkRegistrationExample {
	use HooksManagementTrait;

	public function register_hooks(): void {
		$defs = array(
		    array(
		        'type'          => 'action',
		        'hook'          => 'init',
		        'callback'      => array($this, 'on_init'),
		        'priority'      => 10,
		        'accepted_args' => 0,
		        'context'       => array('bulk' => true),
		    ),
		    array(
		        'type'          => 'filter',
		        'hook'          => 'the_title',
		        'callback'      => array($this, 'on_the_title'),
		        'priority'      => 10,
		        'accepted_args' => 1,
		        'context'       => array('bulk' => true),
		    ),
		);

		$this->_register_hooks_bulk($defs);
	}

	public function on_init(): void {
	}

	public function on_the_title(string $title): string {
		return $title . ' · enhanced';
	}
}

// $ex = new BulkRegistrationExample();
// $ex->_init_hooks();
