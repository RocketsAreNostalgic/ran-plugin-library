<?php
/**
 * HooksAccessory Example: Method-based action and filter via HooksManagementTrait
 */

declare(strict_types=1);

use Ran\PluginLib\HooksAccessory\HooksManagementTrait;

class TraitMethodExample {
	use HooksManagementTrait;

	public function register_hooks(): void {
		// Action: run on wp_init
		$this->_register_action_method('wp_init', 'on_wp_init', 10, 0, array('example' => 'trait_method_action'));

		// Filter: modify the_content
		$this->_register_filter_method('the_content', 'filter_content', 10, 1, array('example' => 'trait_method_filter'));
	}

	public function on_wp_init(): void {
	}

	public function filter_content(string $content): string {
		return $content . "\n<!-- filtered by TraitMethodExample -->";
	}

	public function debug_report(): array {
		// Public getters provided by HooksManagementTrait
		return array(
		    'stats' => $this->get_hook_stats(),
		    'keys'  => $this->get_registered_hooks(),
		);
	}
}

// Usage:
// $ex = new TraitMethodExample();
// $ex->_init_hooks(); // will call register_hooks() and initialize declarative hooks (if any)
