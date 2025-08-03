<?php
/**
 * Example: WP_Block_Type Collection Usage Examples
 *
 * This file demonstrates how to work with collections of WP_Block_Type objects
 * returned by BlockRegistrar methods, including filtering, iteration, and
 * bulk operations.
 *
 * Shows both the modern BlockFactory/Block API and the direct BlockRegistrar approach.
 * The BlockFactory/Block approach is recommended for new code.
 *
 * @package RanPluginLib
 * @since 1.0.0
 */

use Ran\PluginLib\EnqueueAccessory\Block;
use Ran\PluginLib\EnqueueAccessory\BlockFactory;
use Ran\PluginLib\EnqueueAccessory\BlockRegistrar;
use Ran\PluginLib\Config\ConfigInterface;

// Assume we have a config instance
/** @var ConfigInterface $config */

// Create BlockRegistrar instance
$block_registrar = new BlockRegistrar($config);

// Register multiple blocks with various configurations
$block_registrar->add(array(
    array(
        'block_name'  => 'my-plugin/hero-banner',
        'title'       => 'Hero Banner',
        'description' => 'A customizable hero banner block',
        'category'    => 'layout',
        'attributes'  => array(
            'title' => array(
                'type'    => 'string',
                'default' => 'Welcome'
            ),
            'backgroundImage' => array(
                'type'    => 'string',
                'default' => ''
            )
        ),
        'scripts' => array(
            'dev'  => 'http://localhost:3000/hero-banner.js',
            'prod' => '/dist/hero-banner.min.js'
        ),
        'styles' => array(
            'dev'  => 'http://localhost:3000/hero-banner.css',
            'prod' => '/dist/hero-banner.min.css'
        )
    ),
    array(
        'block_name'  => 'my-plugin/testimonial',
        'title'       => 'Testimonial',
        'description' => 'Display customer testimonials',
        'category'    => 'widgets',
        'attributes'  => array(
            'quote' => array(
                'type'    => 'string',
                'default' => ''
            ),
            'author' => array(
                'type'    => 'string',
                'default' => ''
            ),
            'rating' => array(
                'type'    => 'number',
                'default' => 5
            )
        ),
        'scripts' => array(
            'prod' => '/dist/testimonial.min.js'
        )
    )
));

// Stage the blocks for registration
$block_registrar->stage();

// After WordPress 'init' hook has fired and blocks are registered,
// we can access the WP_Block_Type objects

add_action('wp_loaded', function() use ($block_registrar) {
	// Example 1: Get all registered block types
	$all_blocks = $block_registrar->get_registered_block_types();

	echo '<!-- Registered ' . count($all_blocks) . " blocks -->\n";

	foreach ($all_blocks as $block_name => $wp_block_type) {
		echo "<!-- Block: {$block_name} -->\n";
	}

	// Example 2: Get a specific block type
	$hero_block = $block_registrar->get_registered_block_type('my-plugin/hero-banner');

	if ($hero_block instanceof WP_Block_Type) {
		// Example 3: Runtime Block Introspection
		echo "<!-- Hero Banner Block Details:\n";
		echo 'Title: ' . $hero_block->title . "\n";
		echo 'Description: ' . $hero_block->description . "\n";
		echo 'Category: ' . $hero_block->category . "\n";
		echo 'Has Editor Script: ' . (isset($hero_block->editor_script) ? 'Yes' : 'No') . "\n";
		echo 'Has View Script: ' . (isset($hero_block->view_script) ? 'Yes' : 'No') . "\n";
		echo "-->\n";

		// Example 4: Attribute Validation
		$default_attributes = array();
		if (isset($hero_block->attributes)) {
			foreach ($hero_block->attributes as $attr_name => $attr_config) {
				if (isset($attr_config['default'])) {
					$default_attributes[$attr_name] = $attr_config['default'];
				}
			}
		}
		echo '<!-- Default Attributes: ' . json_encode($default_attributes) . " -->\n";

		// Example 5: Dynamic Block Rendering
		if (is_callable(array($hero_block, 'render'))) {
			$sample_attributes = array(
			    'title'           => 'Dynamic Hero Title',
			    'backgroundImage' => '/images/hero-bg.jpg'
			);

			// Note: This would typically be done in a proper rendering context
			// $rendered_content = $hero_block->render($sample_attributes);
		}
	}

	// Example 6: Block Relationship Analysis
	$blocks_with_scripts = array();
	$blocks_with_styles  = array();

	foreach ($all_blocks as $block_name => $wp_block_type) {
		if (isset($wp_block_type->editor_script) || isset($wp_block_type->view_script)) {
			$blocks_with_scripts[] = $block_name;
		}
		if (isset($wp_block_type->editor_style) || isset($wp_block_type->style)) {
			$blocks_with_styles[] = $block_name;
		}
	}

	echo '<!-- Blocks with Scripts: ' . implode(', ', $blocks_with_scripts) . " -->\n";
	echo '<!-- Blocks with Styles: ' . implode(', ', $blocks_with_styles) . " -->\n";

	// Example 7: Asset Handle Access
	$testimonial_block = $block_registrar->get_registered_block_type('my-plugin/testimonial');
	if ($testimonial_block && isset($testimonial_block->view_script)) {
		$script_handle = $testimonial_block->view_script;
		echo "<!-- Testimonial script handle: {$script_handle} -->\n";

		// You could use this handle for advanced asset management
		// wp_localize_script($script_handle, 'testimonialConfig', $custom_config);
	}

	// Example 8: Theme Integration
	if (current_theme_supports('custom-blocks')) {
		$theme_supported_blocks = array();
		foreach ($all_blocks as $block_name => $wp_block_type) {
			// Check if theme has specific support for this block
			if (current_theme_supports('custom-block-' . str_replace('/', '-', $block_name))) {
				$theme_supported_blocks[] = $block_name;
			}
		}
		echo '<!-- Theme supported blocks: ' . implode(', ', $theme_supported_blocks) . " -->\n";
	}

	// Example 9: Debugging Information
	if (defined('WP_DEBUG') && WP_DEBUG) {
		foreach ($all_blocks as $block_name => $wp_block_type) {
			$debug_info = array(
			    'name'                => $wp_block_type->name,
			    'title'               => $wp_block_type->title ?? 'No title',
			    'has_attributes'      => !empty($wp_block_type->attributes),
			    'has_render_callback' => is_callable($wp_block_type->render_callback),
			    'supports'            => $wp_block_type->supports ?? array()
			);
			echo "<!-- DEBUG {$block_name}: " . json_encode($debug_info) . " -->\n";
		}
	}

	// Example 10: Custom Block Editor Integration
	add_action('enqueue_block_editor_assets', function() use ($all_blocks) {
		// Pass block metadata to custom editor components
		$block_metadata = array();
		foreach ($all_blocks as $block_name => $wp_block_type) {
			$block_metadata[$block_name] = array(
			    'title'       => $wp_block_type->title,
			    'description' => $wp_block_type->description,
			    'category'    => $wp_block_type->category,
			    'attributes'  => $wp_block_type->attributes ?? array()
			);
		}

		wp_localize_script(
			'wp-blocks',
			'myPluginBlockMetadata',
			$block_metadata
		);
	});
});

// Example 11: Plugin Extension Hook
add_action('my_plugin_extend_blocks', function() use ($block_registrar) {
	$hero_block = $block_registrar->get_registered_block_type('my-plugin/hero-banner');

	if ($hero_block) {
		// Extend the block with additional functionality
		add_filter('render_block_my-plugin/hero-banner', function($block_content, $block) use ($hero_block) {
			// Add custom wrapper based on block configuration
			if (isset($block['attrs']['backgroundImage']) && !empty($block['attrs']['backgroundImage'])) {
				$bg_style = 'style="background-image: url(' . esc_url($block['attrs']['backgroundImage']) . ')"';
				return '<div class="hero-with-background" ' . $bg_style . '>' . $block_content . '</div>';
			}
			return $block_content;
		}, 10, 2);
	}
});

// Example 12: Block Variations Management
add_action('init', function() use ($block_registrar) {
	$testimonial_block = $block_registrar->get_registered_block_type('my-plugin/testimonial');

	if ($testimonial_block) {
		// Register block variations based on the registered block
		wp_register_block_pattern(
			'my-plugin/testimonial-5-star',
			array(
			    'title'       => '5-Star Testimonial',
			    'description' => 'A testimonial with 5-star rating',
			    'content'     => '<!-- wp:my-plugin/testimonial {"rating":5,"quote":"Amazing service!","author":"Happy Customer"} /-->',
			    'categories'  => array('testimonials'),
			    'blockTypes'  => array($testimonial_block->name)
			)
		);
	}
}, 20); // Run after blocks are registered
