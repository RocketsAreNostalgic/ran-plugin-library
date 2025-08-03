<?php
/**
 * BlockFactory Factory/Coordinator Usage Example
 *
 * This example demonstrates how to use the BlockFactory as a factory and coordinator
 * for managing block registration and lifecycle. BlockFactory serves as the central
 * point for creating and coordinating Block objects.
 *
 * @package Ran\PluginLib\Examples
 * @author  Ran Plugin Lib
 * @license GPL-2.0+ <http://www.gnu.org/licenses/gpl-2.0.txt>
 * @link    https://github.com/RocketsAreNostalgic
 * @since   0.1.0
 */

declare(strict_types=1);

use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\EnqueueAccessory\BlockFactory;

// Assuming you have a config instance
/** @var ConfigInterface $config */

// Create BlockFactory instance with natural syntax
$blockManager = new BlockFactory($config);

// Example 1: Simple block registration with Block-first approach
$blockManager->add_block('my-plugin/hero-block', array(
    'render_callback' => 'my_plugin_render_hero_block',
    'attributes'      => array(
        'title'   => array('type' => 'string', 'default' => ''),
        'content' => array('type' => 'string', 'default' => '')
    )
));

$heroBlock = $blockManager->block('my-plugin/hero-block');
$heroBlock
    ->add_script(array(
        'handle' => 'hero-block-script',
        'src'    => 'assets/js/hero-block.js',
        'deps'   => array('wp-blocks', 'wp-element')
    ))
    ->add_style(array(
        'handle' => 'hero-block-style',
        'src'    => 'assets/css/hero-block.css'
    ))
    ->condition('is_admin')
    ->preload(true);

// Example 2: Block with complex asset configuration
$blockManager->add_block('my-plugin/gallery-block');

$galleryBlock = $blockManager->block('my-plugin/gallery-block');
$galleryBlock
    ->add_script(array(
        'handle'  => 'gallery-block-editor',
        'src'     => 'assets/js/gallery-editor.js',
        'deps'    => array('wp-blocks', 'wp-element', 'wp-components'),
        'context' => 'editor'
    ))
    ->add_script(array(
        'handle'  => 'gallery-block-frontend',
        'src'     => 'assets/js/gallery-frontend.js',
        'deps'    => array('jquery'),
        'context' => 'frontend'
    ))
    ->add_style(array(
        'handle'  => 'gallery-block-editor-style',
        'src'     => 'assets/css/gallery-editor.css',
        'context' => 'editor'
    ))
    ->add_style(array(
        'handle'  => 'gallery-block-style',
        'src'     => 'assets/css/gallery.css',
        'context' => 'both'
    ))
    ->hook('wp_loaded', 20);

// Example 3: Conditional block registration
$blockManager->add_block('my-plugin/admin-only-block', array(
    'render_callback' => 'my_plugin_render_admin_block'
));

$adminBlock = $blockManager->block('my-plugin/admin-only-block');
$adminBlock
    ->condition('is_admin')
    ->add_script(array(
        'handle' => 'admin-block-script',
        'src'    => 'assets/js/admin-block.js'
    ));

// Example 4: Deferred registration with custom hook
$blockManager->add_block('my-plugin/late-block');

$lateBlock = $blockManager->block('my-plugin/late-block');
$lateBlock
    ->hook('plugins_loaded', 15)
    ->add_style(array(
        'handle' => 'late-block-style',
        'src'    => 'assets/css/late-block.css'
    ));

// Register all configured blocks
$results = $blockManager->register();

// Example 5: Individual block registration with status checking
// The new Block.register() API returns WP_Block_Type on success, status array on failure
$heroResult = $heroBlock->register();
if ($heroResult instanceof WP_Block_Type) {
	// Successfully registered
	echo 'Hero block registered successfully: ' . $heroResult->name . "\n";
} else {
	// Registration failed or pending - $heroResult is a status array
	echo 'Hero block registration status: ' . $heroResult['status'] . "\n";
	if (isset($heroResult['error'])) {
		echo 'Error: ' . $heroResult['error'] . "\n";
	}
}

// Check status of all blocks
$statuses = $blockManager->get_block_status();
foreach ($statuses as $blockName => $status) {
	echo "Block {$blockName}: {$status['status']}\n";
} // This calls stage() then load()

// Alternative: Stage and load separately for more control
// $blockManager->stage()->load();

// Cross-plugin override example:
// If another plugin wants to override or extend blocks from this plugin,
// they can create a new BlockFactory instance and it will share the same
// underlying state, allowing them to modify existing blocks or add new ones.

// In another plugin:
// $anotherBlockFactory = new BlockFactory($anotherConfig);
// $anotherBlockFactory->remove_block('my-plugin/hero-block'); // Remove existing block
// $anotherBlockFactory->add_block('my-plugin/enhanced-hero-block', [...]);

// Check what blocks are registered
$registeredBlocks = $blockManager->get_registered_block_types();
foreach ($registeredBlocks as $blockName => $blockType) {
	echo "Registered block: {$blockName}\n";
}

// Get specific block type
$heroBlock = $blockManager->get_registered_block_type('my-plugin/hero-block');
if ($heroBlock) {
	echo 'Hero block is registered with render callback: ' . $heroBlock->render_callback . "\n";
}

/**
 * Key Benefits of BlockFactory:
 *
 * 1. Factory Pattern: Creates and manages Block objects
 * 2. Coordination: Handles registration lifecycle for multiple blocks
 * 3. Shared State: Multiple BlockFactory instances share underlying state
 * 4. Status Tracking: Provides visibility into block registration status
 * 5. Bulk Operations: Register multiple blocks with single call
 * 6. Cross-Plugin Compatibility: Allows plugins to interact with each other's blocks
 */
