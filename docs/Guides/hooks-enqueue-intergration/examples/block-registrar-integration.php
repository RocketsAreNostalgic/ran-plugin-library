<?php
/**
 * Example: BlockFactory + HooksManagementTrait integration
 */

declare(strict_types = 1);

use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\EnqueueAccessory\BlockFactory;
use Ran\PluginLib\HooksAccessory\HooksManagementTrait;

class BlocksFeature {
	use HooksManagementTrait;

	public function __construct( private BlockFactory $blocks ) {
	}

	public function register_hooks(): void {
		// Ensure block registration occurs at init (or earlier if desired)
		$this->_register_action_method( 'init', 'register_blocks', 10, 0 );
	}

	public function register_blocks(): void {
		// Define one block and register all
		$this->blocks->add_block(
			'my-plugin/example-block',
			array(
				'render_callback' => 'my_plugin_render_example_block',
			)
		);
		$this->blocks->register();
	}
}

function my_plugin_render_example_block( array $attributes, string $content ): string {
	return '<div class="example-block">Hello from example block</div>';
}

/** @var ConfigInterface $config */
// $blocks = new BlockFactory($config);
// (new BlocksFeature($blocks))->_init_hooks();
