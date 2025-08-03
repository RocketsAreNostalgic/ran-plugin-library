<?php
/**
 * BlockRegistrar Flattened API Usage Examples
 *
 * This file demonstrates the flattened API that allows WordPress block properties
 * and arbitrary custom properties to be specified at the top level, making the API
 * more natural and WordPress-like.
 *
 * Shows both the modern BlockFactory/Block API and the direct BlockRegistrar approach.
 * The BlockFactory/Block approach is recommended for new code.
 *
 * @package RanPluginLib
 */

use Ran\PluginLib\EnqueueAccessory\Block;
use Ran\PluginLib\EnqueueAccessory\BlockFactory;
use Ran\PluginLib\EnqueueAccessory\BlockRegistrar;

// =====================================================
// MODERN APPROACH: BlockFactory/Block API (Recommended)
// =====================================================

// Example 1A: Basic Block with WordPress Properties (Modern API)
function example_basic_flattened_api_modern() {
	// Create block using modern API
	$heroBlock = new Block('my-plugin/hero-block');

	// WordPress block properties can be set directly
	$heroBlock
		->set('title', 'Hero Block')
		->set('description', 'A customizable hero section block')
		->category('design')
		->icon('cover-image')
		->set('keywords', array('hero', 'banner', 'header'))
		->set('supports', array(
			'align' => array('wide', 'full'),
			'color' => array(
				'background' => true,
				'text'       => true
			)
		))
		->attributes(array(
			'heading' => array(
				'type'    => 'string',
				'default' => 'Welcome'
			),
			'backgroundImage' => array(
				'type' => 'string'
			)
		))
		->render_callback('render_hero_block')
		// Asset management with modern API
		->add_script(array(
			'handle' => 'hero-frontend',
			'src'    => array('dev' => 'src/hero.js', 'prod' => 'dist/hero.min.js'),
			'deps'   => array('jquery')
		))
		->add_style(array(
			'handle' => 'hero-styles',
			'src'    => array('dev' => 'src/hero.css', 'prod' => 'dist/hero.min.css')
		))
		->preload(true);

	return $heroBlock;
}

// =====================================================
// ALTERNATIVE: Direct BlockRegistrar API
// =====================================================

// Example 1B: Basic Block with WordPress Properties (Direct API)
function example_basic_flattened_api($block_registrar) {
	$block_registrar->add(array(
	    'block_name' => 'my-plugin/hero-block',

	    // WordPress block properties (no nesting required!)
	    'title'       => 'Hero Block',
	    'description' => 'A customizable hero section block',
	    'category'    => 'design',
	    'icon'        => 'cover-image',
	    'keywords'    => array('hero', 'banner', 'header'),
	    'supports'    => array(
	        'align' => array('wide', 'full'),
	        'color' => array(
	            'background' => true,
	            'text'       => true
	        )
	    ),
	    'attributes' => array(
	        'heading' => array(
	            'type'    => 'string',
	            'default' => 'Welcome'
	        ),
	        'backgroundImage' => array(
	            'type' => 'string'
	        )
	    ),
	    'render_callback' => 'render_hero_block',

	    // Our asset management
	    'assets' => array(
	        'frontend_scripts' => array(
	            array(
	                'handle' => 'hero-frontend',
	                'src'    => array('dev' => 'src/hero.js', 'prod' => 'dist/hero.min.js'),
	                'deps'   => array('jquery')
	            )
	        ),
	        'frontend_styles' => array(
	            array(
	                'handle' => 'hero-styles',
	                'src'    => array('dev' => 'src/hero.css', 'prod' => 'dist/hero.min.css')
	            )
	        )
	    ),

	    // Preload configuration
	    'preload' => true
	));
}

// Example 2: Block with Arbitrary Custom Properties
function example_custom_properties($block_registrar) {
	$block_registrar->add(array(
	    'block_name' => 'my-plugin/api-block',

	    // Standard WordPress properties
	    'title'           => 'API Data Block',
	    'description'     => 'Display data from external APIs',
	    'category'        => 'widgets',
	    'render_callback' => 'render_api_block',

	    // Arbitrary custom properties for your plugin
	    'api_config' => array(
	        'endpoint'       => 'https://api.example.com/data',
	        'cache_duration' => 3600,
	        'auth_required'  => true,
	        'rate_limit'     => 100
	    ),
	    'display_options' => array(
	        'theme'     => 'modern',
	        'animation' => 'fade-in',
	        'layout'    => 'grid'
	    ),
	    'plugin_metadata' => array(
	        'version'       => '2.1.0',
	        'feature_flags' => array('lazy_loading', 'infinite_scroll'),
	        'compatibility' => array('gutenberg' => '>=12.0')
	    ),

	    // Assets with environment-aware loading
	    'assets' => array(
	        'frontend_scripts' => array(
	            array(
	                'handle' => 'api-block-frontend',
	                'src'    => array(
	                    'dev'  => 'src/api-block.js',
	                    'prod' => 'dist/api-block.min.js'
	                )
	            )
	        )
	    )
	));
}

// Example 3: Advanced Block with All Features
function example_advanced_block($block_registrar) {
	$block_registrar->add(array(
	    'block_name' => 'my-plugin/advanced-block',

	    // WordPress block definition
	    'title'       => 'Advanced Block',
	    'description' => 'A feature-rich block with custom configuration',
	    'category'    => 'common',
	    'icon'        => 'admin-tools',
	    'keywords'    => array('advanced', 'custom', 'configurable'),
	    'parent'      => array('core/group'),
	    'supports'    => array(
	        'align'           => true,
	        'anchor'          => true,
	        'customClassName' => true
	    ),
	    'attributes' => array(
	        'content' => array('type' => 'string'),
	        'layout'  => array('type' => 'string', 'default' => 'default')
	    ),
	    'variations' => array(
	        array(
	            'name'       => 'card',
	            'title'      => 'Card Layout',
	            'attributes' => array('layout' => 'card')
	        )
	    ),
	    'example' => array(
	        'attributes' => array(
	            'content' => 'Example content for preview'
	        )
	    ),
	    'render_callback' => 'render_advanced_block',

	    // Custom business logic properties
	    'workflow_config' => array(
	        'approval_required'   => true,
	        'auto_publish'        => false,
	        'notification_emails' => array('admin@example.com')
	    ),
	    'integration_settings' => array(
	        'crm_sync'           => true,
	        'analytics_tracking' => 'google_analytics',
	        'webhook_url'        => 'https://hooks.example.com/block-update'
	    ),

	    // Conditional registration
	    'condition' => function() {
	    	return current_user_can('edit_posts') && is_plugin_active('advanced-features/plugin.php');
	    },

	    // Asset management with preloading
	    'assets' => array(
	        'editor_scripts' => array(
	            array(
	                'handle' => 'advanced-block-editor',
	                'src'    => 'dist/editor.js',
	                'deps'   => array('wp-blocks', 'wp-element')
	            )
	        ),
	        'frontend_scripts' => array(
	            array(
	                'handle' => 'advanced-block-frontend',
	                'src'    => array('dev' => 'src/frontend.js', 'prod' => 'dist/frontend.min.js')
	            )
	        )
	    ),
	    'preload' => 'inherit' // Use same condition as block registration
	));
}

// Example render callback showing access to custom properties
function render_api_block($attributes, $content, $block) {
	// Access custom properties from the block type
	$block_type = $block->block_type;

	$api_config      = $block_type->api_config;
	$display_options = $block_type->display_options;
	$plugin_metadata = $block_type->plugin_metadata;

	// Use custom configuration in rendering
	$endpoint = $api_config['endpoint'];
	$theme    = $display_options['theme'];
	$version  = $plugin_metadata['version'];

	// Fetch data using custom config
	$data = fetch_cached_api_data($endpoint, $api_config['cache_duration']);

	return sprintf(
		'<div class="api-block theme-%s" data-version="%s">%s</div>',
		esc_attr($theme),
		esc_attr($version),
		wp_kses_post($data)
	);
}

// Example: Accessing custom properties from anywhere
function get_block_custom_config($block_name) {
	$registry   = WP_Block_Type_Registry::get_instance();
	$block_type = $registry->get_registered($block_name);

	if ($block_type) {
		// Access any custom properties
		return array(
		    'api_config'      => $block_type->api_config      ?? null,
		    'display_options' => $block_type->display_options ?? null,
		    'plugin_metadata' => $block_type->plugin_metadata ?? null
		);
	}

	return null;
}

// Example: Complete integration showing the power of flattened API
function example_complete_integration() {
    // Initialize BlockRegistrar (normally done in your plugin's main class)
	$config          = new YourPluginConfig(); // Your config implementation
	$block_registrar = new BlockRegistrar($config);

	// Register multiple blocks with different configurations
	$block_registrar->add(array(
	    array(
	        'block_name' => 'my-plugin/hero-section',

	        // WordPress properties (no nesting!)
	        'title'       => 'Hero Section',
	        'description' => 'A customizable hero section with background image',
	        'category'    => 'design',
	        'icon'        => 'cover-image',
	        'keywords'    => array('hero', 'banner', 'header', 'section'),
	        'supports'    => array(
	            'align'   => array('wide', 'full'),
	            'color'   => array('background' => true, 'text' => true),
	            'spacing' => array('padding' => true, 'margin' => true)
	        ),
	        'attributes' => array(
	            'heading'         => array('type' => 'string', 'default' => 'Welcome'),
	            'subheading'      => array('type' => 'string'),
	            'backgroundImage' => array('type' => 'string'),
	            'alignment'       => array('type' => 'string', 'default' => 'center')
	        ),
	        'render_callback' => 'render_hero_section_block',

	        // Custom properties for your plugin
	        'animation_config' => array(
	            'entrance' => 'fade-in',
	            'duration' => 800,
	            'delay'    => 200
	        ),
	        'seo_config' => array(
	            'schema_type' => 'WebPageElement',
	            'priority'    => 'high'
	        ),

	        // Asset management with preloading
	        'assets' => array(
	            'frontend_scripts' => array(
	                array(
	                    'handle' => 'hero-section-frontend',
	                    'src'    => array('dev' => 'src/hero-section.js', 'prod' => 'dist/hero-section.min.js'),
	                    'deps'   => array('jquery')
	                )
	            ),
	            'frontend_styles' => array(
	                array(
	                    'handle' => 'hero-section-styles',
	                    'src'    => array('dev' => 'src/hero-section.css', 'prod' => 'dist/hero-section.min.css')
	                )
	            )
	        ),
	        'preload' => true // Always preload hero assets
	    ),

	    array(
	        'block_name'      => 'my-plugin/testimonial-carousel',
	        'title'           => 'Testimonial Carousel',
	        'description'     => 'Display customer testimonials in a carousel',
	        'category'        => 'widgets',
	        'icon'            => 'format-quote',
	        'keywords'        => array('testimonial', 'review', 'carousel', 'slider'),
	        'render_callback' => 'render_testimonial_carousel_block',

	        // Custom integration settings
	        'api_integration' => array(
	            'endpoint'              => 'https://api.trustpilot.com/testimonials',
	            'cache_duration'        => 3600,
	            'fallback_testimonials' => array()
	        ),
	        'display_settings' => array(
	            'autoplay'         => true,
	            'transition_speed' => 500,
	            'show_dots'        => true,
	            'show_arrows'      => true
	        ),

	        // Conditional loading - only load on pages with testimonials
	        'condition' => function() {
	        	return has_block('my-plugin/testimonial-carousel') || is_page('testimonials');
	        },

	        'assets' => array(
	            'frontend_scripts' => array(
	                array(
	                    'handle' => 'testimonial-carousel',
	                    'src'    => 'dist/testimonial-carousel.min.js',
	                    'deps'   => array('swiper')
	                )
	            )
	        ),
	        'preload' => 'inherit' // Use same condition as block registration
	    )
	));

	// Stage all blocks and assets
	$block_registrar->stage();

	return $block_registrar;
}

// Example: Clean, modern block registration
function example_modern_block_registration($block_registrar) {
	// Clean, flattened API - no nesting required!
	$block_registrar->add(array(
	    'block_name'  => 'my-plugin/modern-block',
	    'title'       => 'Modern Block',
	    'description' => 'Using clean flattened configuration',
	    'category'    => 'common',
	    'icon'        => 'admin-tools',
	    'supports'    => array(
	        'align' => true,
	        'color' => array('background' => true, 'text' => true)
	    ),
	    'attributes' => array(
	        'content'   => array('type' => 'string', 'default' => 'Hello World'),
	        'alignment' => array('type' => 'string', 'default' => 'left')
	    ),
	    'render_callback' => 'render_modern_block',

	    // Custom properties for your plugin
	    'plugin_config' => array(
	        'feature_flags' => array('animations', 'lazy_loading'),
	        'api_version'   => '2.0'
	    ),

	    'assets' => array(
	        'frontend_scripts' => array(
	            array('handle' => 'modern-script', 'src' => 'modern.js')
	        )
	    )
	));
}
