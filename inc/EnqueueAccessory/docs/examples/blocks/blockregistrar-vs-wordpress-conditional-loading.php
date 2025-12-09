<?php
/**
 * WordPress vs BlockRegistrar: Conditional Loading Comparison
 *
 * This file demonstrates the differences between WordPress's native block registration
 * and BlockRegistrar's enhanced conditional loading capabilities.
 *
 * Shows both the modern BlockFactory/Block API and the direct BlockRegistrar approach.
 * The BlockFactory/Block approach is recommended for new code.
 *
 * @package RanPluginLib
 * @subpackage Examples
 */

use Ran\PluginLib\EnqueueAccessory\Block;
use Ran\PluginLib\EnqueueAccessory\BlockFactory;
use Ran\PluginLib\EnqueueAccessory\BlockRegistrar;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

/**
 * PART 1: WHAT WORDPRESS ALREADY PROVIDES
 * =======================================
 *
 * WordPress register_block_type() automatically handles conditional loading
 * for these asset types. NO ADDITIONAL CONDITIONS NEEDED.
 */

// Example 1: Standard WordPress Block with Built-in Conditional Loading
function example_wordpress_builtin_loading() {
	// WordPress automatically handles all the conditional loading here
	register_block_type('my-plugin/standard-block', array(
	    'editor_script'   => 'my-plugin-editor-script',     // Auto: Editor only
	    'editor_style'    => 'my-plugin-editor-style',      // Auto: Editor only
	    'script'          => 'my-plugin-script',             // Auto: Both contexts
	    'style'           => 'my-plugin-style',              // Auto: Both contexts
	    'view_script'     => 'my-plugin-view-script',        // Auto: Frontend when block present (WP 5.9+)
	    'view_style'      => 'my-plugin-view-style',         // Auto: Frontend when block present (WP 6.1+)
	    'render_callback' => 'render_my_standard_block'
	));

	// WordPress handles:
	// ✅ editor_script only loads in block editor
	// ✅ view_script only loads on frontend when block is present
	// ✅ script loads in both contexts
	// ✅ All automatic, no conditions needed
}

/**
 * PART 2: WHAT BLOCKREGISTRAR ADDS
 * ==================================================
 *
 * BlockRegistrar provides additional capabilities that WordPress doesn't have.
 * These complement WordPress's built-in loading.
 */

// Example 2: Block-Level Conditional Registration (Original Direct API)
function example_block_level_conditions_direct($block_registrar) {
	// WordPress CAN'T do this - register entire block conditionally
	$block_registrar->add(array(
	    'block_name' => 'my-plugin/admin-only-block',
	    'condition'  => function() {
	    	// Block only exists for administrators
	    	return current_user_can('manage_options');
	    },
	    'block_config' => array(
	        'render_callback' => 'render_admin_block'
	    ),
	    'assets' => array(
	        // Use WordPress's built-in types where possible
	        'editor_scripts' => array(
	            array(
	                'handle' => 'admin-block-editor',
	                'src'    => 'assets/js/admin-block-editor.js'
	            )
	        )
	    )
	));
}

// Example 3: Custom Asset Conditions Beyond WordPress Defaults
function example_custom_asset_conditions($block_registrar) {
	$block_registrar->add(array(
	    'block_name' => 'my-plugin/ecommerce-block',
	    'assets'     => array(
	        // WordPress handles this automatically - no condition needed
	        'frontend_scripts' => array(
	            array(
	                'handle' => 'ecommerce-frontend',
	                'src'    => 'assets/js/ecommerce-frontend.js'
	                // Maps to WordPress view_script - auto-loads on frontend when block present
	            )
	        ),

	        // BlockRegistrar adds custom conditions WordPress can't do
	        'dynamic_scripts' => array(
	            array(
	                'handle'    => 'ecommerce-cart',
	                'src'       => 'assets/js/cart-integration.js',
	                'condition' => function() {
	                	// Only load on WooCommerce product pages
	                	return function_exists('is_product') && is_product();
	                }
	            )
	        )
	    )
	));
}

// Example 4: Environment-Aware Asset Loading
function example_environment_aware_loading($block_registrar) {
	$block_registrar->add(array(
	    'block_name' => 'my-plugin/gallery-block',
	    'assets'     => array(
	        'view_scripts' => array(
	            array(
	                'handle' => 'gallery-script',
	                // WordPress can't do environment-aware source selection
	                'src' => array(
	                    'dev'  => 'assets/js/gallery.js',        // Unminified for debugging
	                    'prod' => 'assets/js/gallery.min.js'    // Minified for production
	                ),
	                'deps' => array('jquery')
	                // WordPress still handles the conditional loading (frontend when present)
	            )
	        )
	    )
	));
}

// Example 5: Asset Preloading (Performance Optimization)
function example_asset_preloading($block_registrar) {
	$block_registrar->add(array(
	    'block_name' => 'my-plugin/hero-block',
	    // WordPress can't generate preload tags
	    'preload' => function() {
	    	// Preload on homepage and landing pages for better Core Web Vitals
	    	return is_front_page() || is_root_tempate('page-landing.php');
	    },
	    'assets' => array(
	        'view_scripts' => array(
	            array(
	                'handle' => 'hero-script',
	                'src'    => 'assets/js/hero.js'
	                // WordPress handles conditional loading, BlockRegistrar adds preloading
	            )
	        )
	    )
	));
}

// Example 6: Dynamic Asset Loading During Block Render
function example_dynamic_asset_loading($block_registrar) {
	$block_registrar->add(array(
	    'block_name' => 'my-plugin/interactive-block',
	    'assets'     => array(
	        // WordPress built-in: loads when block present
	        'view_scripts' => array(
	            array(
	                'handle' => 'interactive-base',
	                'src'    => 'assets/js/interactive-base.js'
	            )
	        ),

	        // BlockRegistrar addition: loads during render_block hook
	        'dynamic_scripts' => array(
	            array(
	                'handle'    => 'interactive-dynamic',
	                'src'       => 'assets/js/interactive-dynamic.js',
	                'hook'      => 'render_block',
	                'condition' => function() {
	                	// Only load if user has specific capability
	                	return current_user_can('edit_posts');
	                }
	            )
	        )
	    )
	));
}

/**
 * PART 3: BEST PRACTICES - WHEN TO USE WHAT
 * ==========================================
 */

// ✅ GOOD: Use WordPress built-in types for standard contexts
function good_practice_use_wordpress_builtin($block_registrar) {
	$block_registrar->add(array(
	    'block_name' => 'my-plugin/content-block',
	    'assets'     => array(
            // Let WordPress handle the conditional loading
	        'editor_scripts'   => array(array('handle' => 'content-editor', 'src' => 'editor.js')),  // Editor only - WordPress handles this
	        'frontend_scripts' => array(array('handle' => 'content-frontend', 'src' => 'frontend.js')),    // Frontend when present - WordPress handles this
	        'styles'           => array(array('handle' => 'content-style', 'src' => 'style.css')),           // Both contexts - WordPress handles this
	    )
	));
}

// ❌ BAD: Don't duplicate WordPress's built-in conditional loading
function bad_practice_duplicate_wordpress($block_registrar) {
	$block_registrar->add(array(
	    'block_name' => 'my-plugin/bad-example',
	    'assets'     => array(
	        'scripts' => array(
	            array(
	                'handle' => 'bad-editor-script',
	                'src'    => 'editor.js',
	                // ❌ DON'T DO THIS - WordPress already handles editor-only loading
	                'condition' => function() {
	                	return is_admin();
	                }
	            )
	        )
	    )
	));

	// ✅ INSTEAD: Use WordPress's built-in editor_scripts type
	// 'editor_scripts' => [['handle' => 'good-editor-script', 'src' => 'editor.js']]
}

// ✅ GOOD: Use BlockRegistrar for capabilities WordPress doesn't have
function good_practice_use_blockregistrar($block_registrar) {
	$block_registrar->add(array(
	    'block_name' => 'my-plugin/advanced-block',

	    // Block-level condition (WordPress can't do this)
	    'condition' => function() {
	    	return !is_admin() && is_user_logged_in();
	    },

        // Asset preloading (WordPress can't do this)
	    'preload' => 'inherit', // Use same condition as block registration

	    'assets' => array(
	        // Standard WordPress loading
	        'frontend_scripts' => array(array('handle' => 'advanced-frontend', 'src' => 'frontend.js')),

	        // Custom conditions beyond WordPress defaults
	        'dynamic_scripts' => array(
	            array(
	                'handle'    => 'custom-script',
	                'src'       => 'custom.js',
	                'condition' => function() {
	                	return is_singular('product');
	                }
	            )
	        )
	    )
	));
}

/**
 * SUMMARY
 * =======
 *
 * WordPress Built-in (Use These First):
 * - editorScript/editorStyle: Editor only
 * - script/style: Both contexts
 * - viewScript/viewStyle: Frontend when block present
 *
 * BlockRegistrar Additions (Use When WordPress Can't):
 * - Block-level conditional registration
 * - Custom asset conditions beyond WordPress defaults
 * - Environment-aware asset loading (dev/prod)
 * - Asset preloading for performance
 * - Dynamic asset loading during render
 * - Integration with existing AssetEnqueueBase infrastructure
 */

/**
 * PART 2: WHAT BLOCKREGISTRAR ADDS
 * =================================
 *
 * BlockRegistrar extends WordPress's capabilities with:
 * - Block-level conditional registration
 * - Custom asset conditions beyond WordPress defaults
 * - Environment-aware loading
 * - Asset preloading
 * - Dynamic asset loading during render
 *
 * Available in two approaches:
 * A) Modern BlockFactory/Block API (Recommended)
 * B) Direct BlockRegistrar API (Alternative)
 */

// =====================================================
// MODERN APPROACH: BlockFactory/Block API (Recommended)
// =====================================================

// Example 2A: Block-Level Conditional Registration (Modern API)
function example_block_level_conditions_modern() {
	// Create block that only registers on specific conditions
	$adminBlock = new Block('my-plugin/admin-only-block');

	$adminBlock
		->condition(function() {
			// Block only exists in admin area
			return is_admin();
		})
		->add_script(array(
			'handle' => 'admin-block-script',
			'src'    => 'assets/js/admin-block.js'
		))
		->add_style(array(
			'handle' => 'admin-block-style',
			'src'    => 'assets/css/admin-block.css'
		));

	return $adminBlock;
}

// =====================================================
// ALTERNATIVE: Direct BlockRegistrar API
// =====================================================

// Example 2B: Block-Level Conditional Registration (Direct API)
function example_block_level_conditions_alternative($block_registrar) {
	// Block that only registers on specific conditions
	$block_registrar->add(array(
	    'block_name' => 'my-plugin/admin-only-block',
	    'condition'  => function() {
	    	// Block only exists in admin area
	    	return is_admin();
	    },
	    'assets' => array(
	        'scripts' => array(
	            array(
	                'handle' => 'admin-block-script',
	                'src'    => 'assets/js/admin-block.js'
	            )
	        ),
	        'styles' => array(
	            array(
	                'handle' => 'admin-block-style',
	                'src'    => 'assets/css/admin-block.css'
	            )
	        )
	    )
	));
}
