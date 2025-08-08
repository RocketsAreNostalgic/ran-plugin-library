<?php
/**
 * Example: Block Asset Preloading Usage
 *
 * This example demonstrates how to use the Block Asset Preloading feature
 * with both the modern BlockFactory/Block API and the underlying BlockRegistrar.
 * The BlockFactory/Block approach is recommended for new code.
 *
 * For a comprehensive comparison of WordPress built-in capabilities vs
 * BlockRegistrar additions, see: wordpress-vs-blockregistrar-conditional-loading.php
 *
 * @package Ran\PluginLib\Examples
 * @author  Ran Plugin Lib
 * @license GPL-2.0+ <http://www.gnu.org/licenses/gpl-2.0.txt>
 * @link    https://github.com/RocketsAreNostalgic
 * @since   0.1.0
 */

declare(strict_types=1);

use Ran\PluginLib\EnqueueAccessory\BlockRegistrar;
use Ran\PluginLib\EnqueueAccessory\BlockFactory;
use Ran\PluginLib\EnqueueAccessory\Block;
use Ran\PluginLib\Config\ConfigInterface;

// Assuming you have a config instance
/** @var ConfigInterface $config */

// MODERN APPROACH: BlockFactory/Block API (Recommended)
// =====================================================

// Create BlockFactory instance (creates shared instance)
$blockManager = new BlockFactory($config);

// ALTERNATIVE: Direct BlockRegistrar API (Lower-level)
// ===================================================

// Create BlockRegistrar instance for direct usage
$block_registrar = new BlockRegistrar($config);

// =====================================================
// MODERN EXAMPLES: BlockFactory/Block API
// =====================================================

// Example 1A: Always preload block assets (Modern API)
$heroBlock = new Block('my-plugin/hero-block');
$heroBlock
	->add_script(array(
		'handle' => 'hero-script',
		'src'    => 'assets/js/hero-block.js',
		'deps'   => array('wp-blocks', 'wp-element')
	))
	->add_style(array(
		'handle' => 'hero-style',
		'src'    => 'assets/css/hero-block.css'
	))
	->preload(true); // Always preload this block's assets

// Example 2A: Conditionally preload block assets (Modern API)
$ctaBlock = new Block('my-plugin/cta-block');
$ctaBlock
	->add_script(array(
		'handle' => 'cta-script',
		'src'    => array(
			'dev'  => 'assets/js/cta-block.js',
			'prod' => 'assets/js/cta-block.min.js'
		),
		'deps' => array('wp-blocks')
	))
	->add_style(array(
		'handle' => 'cta-style',
		'src'    => array(
			'dev'  => 'assets/css/cta-block.css',
			'prod' => 'assets/css/cta-block.min.css'
		)
	))
	->preload(function() {
		// Only preload on front page and landing pages
		return is_front_page() || is_page_template('page-landing.php');
	});

// Example 3A: Block without preloading (Modern API)
$testimonialBlock = new Block('my-plugin/testimonial-block');
$testimonialBlock
	->add_script(array(
		'handle' => 'testimonial-script',
		'src'    => 'assets/js/testimonial-block.js'
	));
// No preload() call means no preloading (default behavior)

// =====================================================
// REGISTRATION: Modern BlockFactory/Block API
// =====================================================

// Register all blocks using BlockFactory
$results = $blockManager->register();

// Or register individual blocks
// $heroResult = $heroBlock->register();
// $ctaResult = $ctaBlock->register();
// $testimonialResult = $testimonialBlock->register();

// Check registration results
foreach ($results as $blockName => $result) {
	if ($result instanceof WP_Block_Type) {
		echo "Block {$blockName} registered successfully\n";
	} else {
		echo "Block {$blockName} registration failed\n";
	}
}

// =====================================================
// ALTERNATIVE: Direct BlockRegistrar API Examples
// =====================================================

// Example 1B: Always preload block assets (Direct API)
$hero_block = array(
    'block_name' => 'my-plugin/hero-block',
    'preload'    => true, // Always preload this block's assets
    'assets'     => array(
        'scripts' => array(
            array(
                'handle' => 'hero-script',
                'src'    => 'assets/js/hero-block.js',
                'deps'   => array('wp-blocks', 'wp-element')
            )
        ),
        'styles' => array(
            array(
                'handle' => 'hero-style',
                'src'    => 'assets/css/hero-block.css'
            )
        )
    )
);

// Example 2B: Conditionally preload block assets (Direct API)
$cta_block = array(
    'block_name' => 'my-plugin/cta-block',
    'preload'    => function() {
    	// Only preload on front page and landing pages
    	return is_front_page() || is_page_template('page-landing.php');
    },
    'assets' => array(
        'scripts' => array(
            array(
                'handle' => 'cta-script',
                'src'    => array(
                    'dev'  => 'assets/js/cta-block.js',
                    'prod' => 'assets/js/cta-block.min.js'
                ),
                'deps' => array('wp-blocks')
            )
        ),
        'styles' => array(
            array(
                'handle' => 'cta-style',
                'src'    => array(
                    'dev'  => 'assets/css/cta-block.css',
                    'prod' => 'assets/css/cta-block.min.css'
                )
            )
        )
    )
);

// Example 3B: Block without preloading (Direct API)
$testimonial_block = array(
    'block_name' => 'my-plugin/testimonial-block',
    // No 'preload' key means no preloading
    'assets' => array(
        'scripts' => array(
            array(
                'handle' => 'testimonial-script',
                'src'    => 'assets/js/testimonial-block.js'
            )
        )
    )
);

// Example 4B: Inherit preload condition from block registration (Direct API)
$admin_block = array(
    'block_name' => 'my-plugin/admin-block',
    'condition'  => function() {
    	// Block only exists on frontend
    	return !is_admin();
    },
    'preload' => 'inherit', // Use the same condition as block registration
    'assets'  => array(
        'scripts' => array(
            array(
                'handle' => 'admin-script',
                'src'    => 'assets/js/admin-block.js'
            )
        )
    )
);

// Example 5: Complex conditional preloading
$gallery_block = array(
    'block_name' => 'my-plugin/gallery-block',
    'preload'    => function() {
    	// Preload on portfolio pages or when user is on mobile
    	return is_page_template('page-portfolio.php') || wp_is_mobile();
    },
    'assets' => array(
        'scripts' => array(
            array(
                'handle' => 'gallery-script',
                'src'    => 'assets/js/gallery-block.js',
                'deps'   => array('jquery', 'wp-blocks')
            )
        ),
        'styles' => array(
            array(
                'handle' => 'gallery-style',
                'src'    => 'assets/css/gallery-block.css'
            )
        ),
        'editor_styles' => array(
            array(
                'handle' => 'gallery-editor-style',
                'src'    => 'assets/css/gallery-block-editor.css'
            )
        )
    )
);

// Add all blocks to the registrar
$block_registrar->add(array(
    $hero_block,
    $cta_block,
    $testimonial_block,
    $admin_block,
    $gallery_block
));

// Stage the blocks (this sets up WordPress hooks including preload functionality)
$block_registrar->stage();

/**
 * What happens when stage() is called:
 *
 * 1. Block registration hooks are set up for WordPress 'init' action
 * 2. Asset management hooks are configured
 * 3. Preload functionality is activated:
 *    - A callback is registered with the 'wp_head' action (priority 2)
 *    - When wp_head fires, preload tags are generated for qualifying blocks
 *
 * Generated preload tags will look like:
 *
 * For hero-block (always preloaded):
 * <link rel="preload" href="https://example.com/assets/js/hero-block.js" as="script">
 * <link rel="preload" href="https://example.com/assets/css/hero-block.css" as="style" type="text/css">
 *
 * For cta-block (conditionally preloaded):
 * <link rel="preload" href="https://example.com/assets/js/cta-block.min.js" as="script">
 * <link rel="preload" href="https://example.com/assets/css/cta-block.min.css" as="style" type="text/css">
 * (Only appears when condition is met)
 *
 * For testimonial-block:
 * (No preload tags generated)
 *
 * For gallery-block:
 * <link rel="preload" href="https://example.com/assets/js/gallery-block.js" as="script">
 * <link rel="preload" href="https://example.com/assets/css/gallery-block.css" as="style" type="text/css">
 * (Only appears when condition is met - portfolio pages or mobile)
 */

/**
 * Performance Benefits:
 *
 * 1. Critical block assets are discovered and downloaded earlier
 * 2. Reduces render-blocking time for above-the-fold blocks
 * 3. Improves Core Web Vitals (LCP, FID, CLS)
 * 4. Better user experience with faster block rendering
 * 5. Conditional preloading prevents unnecessary downloads
 */

/**
 * Best Practices:
 *
 * 1. Only preload truly critical blocks (hero, CTA, above-the-fold content)
 * 2. Use conditional preloading to avoid over-preloading
 * 3. Consider page templates and user context in conditions
 * 4. Monitor performance impact with tools like PageSpeed Insights
 * 5. Test on various devices and connection speeds
 */
