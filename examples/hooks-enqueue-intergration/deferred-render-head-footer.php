<?php
/**
 * Example: Deferred head/footer rendering using a grouped registration
 */

declare(strict_types=1);

use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\EnqueueAccessory\ScriptsHandler;
use Ran\PluginLib\HooksAccessory\HooksManagementTrait;

class DeferredHeadFooterFeature {
	use HooksManagementTrait;

	public function __construct(private ScriptsHandler $scripts) {
	}

	public function register_hooks(): void {
		$this->_register_deferred_hooks(array(
		    'wp_head' => array(
		        'priority' => 10,
		        'callback' => array($this, 'render_head'),
		        'context'  => array('deferred' => true)
		    ),
		    'wp_footer' => array(
		        'priority' => 10,
		        'callback' => array($this, 'render_footer'),
		        'context'  => array('deferred' => true)
		    ),
		));
	}

	public function render_head(): void {
		$this->scripts->render_head();
	}

	public function render_footer(): void {
		$this->scripts->render_footer();
	}
}

/** @var ConfigInterface $config */
// $scripts = new ScriptsHandler($config);
// (new DeferredHeadFooterFeature($scripts))->_init_hooks();
