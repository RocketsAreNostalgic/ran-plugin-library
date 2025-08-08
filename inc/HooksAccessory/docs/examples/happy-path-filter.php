<?php
/**
 * HooksAccessory Happy Path: single filter
 */

declare(strict_types=1);

use Ran\PluginLib\HooksAccessory\HooksManagementTrait;

class HappyPathFilterExample {
	use HooksManagementTrait;

	public function register_hooks(): void {
		$this->_register_filter('the_content', array($this, 'filter_content'));
	}

	public function filter_content(string $content): string {
		return $content . "\n<!-- happy path filter -->";
	}
}
