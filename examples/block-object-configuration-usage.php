<?php
/**
 * Block Object Configuration Usage Example
 *
 * This example demonstrates the object-oriented approach to block configuration
 * using individual Block objects. BlockFactory serves as a factory and coordinator,
 * while all block-specific configuration is done on Block objects.
 *
 * Note: BlockFactory no longer has feature-specific methods like add_script(),
 * add_style(), condition(), etc. These methods exist only on Block objects.
 *
 * @package Ran\PluginLib\Examples
 * @author  Ran Plugin Lib
 * @license GPL-2.0+ <http://www.gnu.org/licenses/gpl-2.0.txt>
 * @link    https://github.com/RocketsAreNostalgic
 * @since   0.1.0
 */

declare(strict_types=1);

use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\EnqueueAccessory\Block;
use Ran\PluginLib\EnqueueAccessory\BlockFactory;

// Assuming you have a config instance
/** @var ConfigInterface $config */

// Create BlockFactory instance
$blockManager = new BlockFactory($config);

// Example 1: Object-oriented block configuration
// BlockFactory only handles basic block management - all configuration is on Block objects

// Method 1: Add block to manager, then get Block object to configure it
$blockManager->add_block('my-plugin/hero-block');
$heroBlock = $blockManager->block('my-plugin/hero-block');

// Method 2: Create Block directly (uses shared BlockFactory instance)
$heroBlock = new Block('my-plugin/hero-block'); // Works after BlockFactory is created

// All block configuration is done on the Block object (not BlockFactory)
$heroBlock
	->set('title', 'Hero Block')
	->set('description', 'A customizable hero section for landing pages')
	->category('layout')
	->icon('superhero')
	->set('keywords', array('hero', 'banner', 'landing', 'header'))
	->attributes(array(
		'title'      => array('type' => 'string', 'default' => 'Welcome'),
		'subtitle'   => array('type' => 'string', 'default' => ''),
		'buttonText' => array('type' => 'string', 'default' => 'Learn More'),
		'buttonUrl'  => array('type' => 'string', 'default' => '#'),
		'alignment'  => array('type' => 'string', 'default' => 'center')
	))
	->set('supports', array(
		'align'      => array('wide', 'full'),
		'color'      => array('background' => true, 'text' => true),
		'spacing'    => array('padding' => true, 'margin' => true),
		'typography' => array('fontSize' => true, 'lineHeight' => true)
	))
	->render_callback('my_plugin_render_hero_block')
	->add_script(array(
		'handle'  => 'hero-block-script',
		'src'     => 'assets/js/hero-block.js',
		'deps'    => array('wp-blocks', 'wp-element', 'wp-components'),
		'context' => 'editor'
	))
	->add_style(array(
		'handle'  => 'hero-block-style',
		'src'     => 'assets/css/hero-block.css',
		'context' => 'both'
	))
	->condition('is_admin')
	->preload(true);

// Example 2: Gallery block with complex asset configuration
// Using direct Block creation (new simplified API)
$galleryBlock = new Block('my-plugin/gallery-block');

$galleryBlock
	->set('title', 'Image Gallery')
	->set('description', 'Responsive image gallery with lightbox')
	->category('media')
	->icon('format-gallery')
	->set('keywords', array('gallery', 'images', 'photos', 'lightbox'))
	->attributes(array(
		'images'       => array('type' => 'array', 'default' => array()),
		'columns'      => array('type' => 'number', 'default' => 3),
		'showCaptions' => array('type' => 'boolean', 'default' => true),
		'lightbox'     => array('type' => 'boolean', 'default' => true)
	))
	->set('supports', array(
		'align'   => array('wide', 'full'),
		'spacing' => array('padding' => true, 'margin' => true)
	))
	->render_callback('my_plugin_render_gallery_block');

// Add multiple scripts and styles to gallery block
$galleryBlock
	->add_script(array(
		'handle'  => 'gallery-editor-script',
		'src'     => 'assets/js/gallery-editor.js',
		'deps'    => array('wp-blocks', 'wp-element', 'wp-media-utils'),
		'context' => 'editor'
	))
	->add_script(array(
		'handle'  => 'gallery-frontend-script',
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

// Example 3: Admin-only block
// Direct Block creation with conditional registration
$adminBlock = new Block('my-plugin/admin-dashboard');

$adminBlock
	->set('title', 'Admin Dashboard Widget')
	->set('description', 'Dashboard widget for admin users only')
	->category('widgets')
	->icon('dashboard')
	->render_callback('my_plugin_render_admin_dashboard')
	->condition('is_admin')
	->add_script(array(
		'handle' => 'admin-dashboard-script',
		'src'    => 'assets/js/admin-dashboard.js',
		'deps'   => array('wp-api', 'wp-components')
	));

// Example 4: Working with existing blocks
// Note: BlockFactory.has_block() only checks blocks added via add_block()
// For blocks created directly with new Block(), use different approach
if ($blockManager->has_block('my-plugin/hero-block')) {
	$existingHero = $blockManager->block('my-plugin/hero-block');

	// Modify existing block configuration
	$existingHero
		->set('custom_property', 'custom_value')
		->add_style(array(
			'handle' => 'hero-custom-style',
			'src'    => 'assets/css/hero-custom.css'
		));
} else {
	// If block was created directly, just create a new Block instance
	$existingHero = new Block('my-plugin/hero-block');
	$existingHero->set('custom_property', 'custom_value');
}

// Example 5: Creating blocks with initial configuration
// Method 1: Use BlockFactory.add_block() with initial config, then get Block object
$blockManager->add_block('my-plugin/call-to-action', array(
	'title'           => 'Call to Action',
	'description'     => 'Compelling call-to-action button',
	'category'        => 'design',
	'render_callback' => 'my_plugin_render_cta_block'
));
$ctaBlock = $blockManager->block('my-plugin/call-to-action');

// Method 2: Create Block directly and configure it (alternative approach)
// $ctaBlock = new Block('my-plugin/call-to-action');
// $ctaBlock->set('title', 'Call to Action')->set('description', 'Compelling call-to-action button');

$ctaBlock
	->attributes(array(
		'text'         => array('type' => 'string', 'default' => 'Click Here'),
		'url'          => array('type' => 'string', 'default' => '#'),
		'style'        => array('type' => 'string', 'default' => 'primary'),
		'size'         => array('type' => 'string', 'default' => 'medium'),
		'openInNewTab' => array('type' => 'boolean', 'default' => false)
	))
	->set('supports', array(
		'color'   => array('background' => true, 'text' => true),
		'spacing' => array('padding' => true, 'margin' => true)
	))
	->add_script(array(
		'handle' => 'cta-script',
		'src'    => 'assets/js/cta.js'
	));

// Register all blocks at once
$blockManager->register();

// Alternative: Register individual blocks
// $heroResult = $heroBlock->register();
// $galleryResult = $galleryBlock->register();

// Example 6: Direct Block creation (new simplified API)
// Once a BlockFactory exists, you can create Block instances directly
$directBlock = new Block('my-plugin/direct-block');
$directBlock
	->set('title', 'Direct Block')
	->set('description', 'Block created directly without manager reference')
	->category('common')
	->icon('block-default')
	->render_callback('my_plugin_render_direct_block');

// The block is automatically added to the shared BlockFactory
$directResult = $directBlock->register();
if ($directResult instanceof WP_Block_Type) {
	echo 'Direct block registered successfully: ' . $directResult->name . "\n";
}

// Example 7: Inspecting block configuration
echo "Hero block configuration:\n";
print_r($heroBlock->get_config());

echo "\nGallery block name: " . $galleryBlock->get_name() . "\n";
echo 'Gallery block title: ' . $galleryBlock->get('title') . "\n";
echo 'Gallery block has lightbox: ' . ($galleryBlock->get('attributes')['lightbox']['default'] ? 'Yes' : 'No') . "\n";

/**
 * Example render callback for hero block.
 *
 * @param array<string, mixed> $attributes Block attributes.
 * @param string               $content    Block content.
 * @return string
 */
function my_plugin_render_hero_block(array $attributes, string $content): string {
	$title      = esc_html($attributes['title'] ?? 'Welcome');
	$subtitle   = esc_html($attributes['subtitle'] ?? '');
	$buttonText = esc_html($attributes['buttonText'] ?? 'Learn More');
	$buttonUrl  = esc_url($attributes['buttonUrl'] ?? '#');
	$alignment  = esc_attr($attributes['alignment'] ?? 'center');

	$html = sprintf(
		'<div class="hero-block hero-block--align-%s">',
		$alignment
	);

	$html .= sprintf('<h1 class="hero-block__title">%s</h1>', $title);

	if (!empty($subtitle)) {
		$html .= sprintf('<p class="hero-block__subtitle">%s</p>', $subtitle);
	}

	$html .= sprintf(
		'<a href="%s" class="hero-block__button">%s</a>',
		$buttonUrl,
		$buttonText
	);

	$html .= '</div>';

	return $html;
}

/**
 * Example render callback for gallery block.
 *
 * @param array<string, mixed> $attributes Block attributes.
 * @param string               $content    Block content.
 * @return string
 */
function my_plugin_render_gallery_block(array $attributes, string $content): string {
	$images       = $attributes['images'] ?? array();
	$columns      = intval($attributes['columns'] ?? 3);
	$showCaptions = $attributes['showCaptions'] ?? true;
	$lightbox     = $attributes['lightbox'] ?? true;

	if (empty($images)) {
		return '<p>No images selected for gallery.</p>';
	}

	$html = sprintf(
		'<div class="gallery-block gallery-block--columns-%d%s">',
		$columns,
		$lightbox ? ' gallery-block--lightbox' : ''
	);

	foreach ($images as $image) {
		$html .= '<div class="gallery-block__item">';

		if ($lightbox) {
			$html .= sprintf(
				'<a href="%s" class="gallery-block__link" data-lightbox="gallery">',
				esc_url($image['url'])
			);
		}

		$html .= sprintf(
			'<img src="%s" alt="%s" class="gallery-block__image">',
			esc_url($image['url']),
			esc_attr($image['alt'] ?? '')
		);

		if ($lightbox) {
			$html .= '</a>';
		}

		if ($showCaptions && !empty($image['caption'])) {
			$html .= sprintf(
				'<p class="gallery-block__caption">%s</p>',
				esc_html($image['caption'])
			);
		}

		$html .= '</div>';
	}

	$html .= '</div>';

	return $html;
}

/**
 * Example render callback for admin dashboard block.
 *
 * @param array<string, mixed> $attributes Block attributes.
 * @param string               $content    Block content.
 * @return string
 */
function my_plugin_render_admin_dashboard(array $attributes, string $content): string {
	if (!is_admin()) {
		return '';
	}

	return sprintf(
		'<div class="admin-dashboard-block">
			<h3>Admin Dashboard Widget</h3>
			<p>This widget is only visible in the admin area.</p>
			<div class="admin-dashboard-block__content">%s</div>
		</div>',
		$content
	);
}

/**
 * Example render callback for CTA block.
 *
 * @param array<string, mixed> $attributes Block attributes.
 * @param string               $content    Block content.
 * @return string
 */
function my_plugin_render_cta_block(array $attributes, string $content): string {
	$text         = esc_html($attributes['text'] ?? 'Click Here');
	$url          = esc_url($attributes['url'] ?? '#');
	$style        = esc_attr($attributes['style'] ?? 'primary');
	$size         = esc_attr($attributes['size'] ?? 'medium');
	$openInNewTab = $attributes['openInNewTab'] ?? false;

	$target = $openInNewTab ? ' target="_blank" rel="noopener noreferrer"' : '';

	return sprintf(
		'<div class="cta-block">
			<a href="%s" class="cta-block__button cta-block__button--%s cta-block__button--%s"%s>%s</a>
		</div>',
		$url,
		$style,
		$size,
		$target,
		$text
	);
}

/**
 * Key Benefits of Block Object Configuration:
 *
 * 1. Object-Oriented: Each block is a self-contained object with its own configuration
 * 2. Fluent Interface: Chain method calls for readable configuration
 * 3. Flexible Creation: Create blocks via BlockFactory or directly with new Block()
 * 4. Separation of Concerns: BlockFactory handles lifecycle, Block handles configuration
 * 5. Reusable: Block objects can be passed around and modified independently
 * 6. Type Safety: Strong typing and IDE support for block configuration
 */
