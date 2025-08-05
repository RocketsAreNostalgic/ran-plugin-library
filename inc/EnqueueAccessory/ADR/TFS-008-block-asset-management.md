# TFS-008: Block Asset Management Architecture

## Status

**Accepted** - Implementation Complete with Flattened API

## Context

WordPress blocks require a different asset management approach compared to traditional scripts and styles. While our existing asset system is optimized for performance through deferred loading, blocks have unique requirements:

1. **Early Registration**: Blocks must be registered early (typically on `init`) for WordPress editor functionality
2. **Conditional Assets**: Block assets should only load when blocks are present on the page
3. **WordPress Integration**: Must integrate seamlessly with WordPress's `register_block_type()` API
4. **Asset Lifecycle**: Block assets have different scopes (editor, frontend, dynamic) with different loading requirements

## WordPress Built-in vs BlockRegistrar Capabilities

### What WordPress `register_block_type()` Already Provides

WordPress automatically handles conditional loading for these asset types:

- **`editorScript`** - Only loads in block editor (admin)
- **`editorStyle`** - Only loads in block editor (admin)
- **`script`** - Loads on both frontend and editor
- **`style`** - Loads on both frontend and editor
- **`viewScript`** - Only loads on frontend when block is present (WordPress 5.9+)
- **`viewStyle`** - Only loads on frontend when block is present (WordPress 6.1+)

### What BlockRegistrar Adds (Non-Duplicative)

Our BlockRegistrar provides **additional** conditional loading capabilities that WordPress doesn't have:

1. **Block-level conditions** - Whether to register the entire block at all
2. **Custom asset conditions** - Beyond WordPress's built-in contexts
3. **Dynamic asset loading** - Assets that load during `render_block` hook
4. **Environment-aware loading** - Dev vs prod asset selection
5. **Preloading logic** - `<link rel="preload">` tag generation
6. **Unified asset management** - Integration with existing AssetEnqueueBase infrastructure

### Key Principle: Complement, Don't Duplicate

BlockRegistrar **complements** WordPress's built-in conditional loading rather than duplicating it. Use WordPress's built-in asset types for standard contexts, and BlockRegistrar's custom conditions for specialized scenarios.

## Decision

We will extend our existing asset management system with block-aware functionality that **inverts the default behavior** compared to traditional assets, and provide a **flattened API** that supports all WordPress block properties plus arbitrary custom properties:

### **Architectural Pattern Inversion**

| Component                   | Default Behavior       | Exception Cases                           | Rationale                          |
| --------------------------- | ---------------------- | ----------------------------------------- | ---------------------------------- |
| **Assets (Scripts/Styles)** | Deferred loading       | Immediate loading (`enqueue_immediate()`) | Performance optimization           |
| **Blocks**                  | Immediate registration | Deferred registration                     | WordPress architecture requirement |

### **Core Components**

1. **BlockAssetTrait**: Provides block-aware asset management functionality
2. **BlockRegistrar**: Extends `AssetEnqueueBaseAbstract`, handles WordPress block registration with asset integration

### **Underlying Infrastructure**

BlockRegistrar leverages the existing Scripts and Styles asset management system through **composition**:

- **ScriptsHandler**: Manages all JavaScript assets for blocks (editor, frontend, dynamic)
- **StylesHandler**: Manages all CSS assets for blocks (editor, frontend, dynamic)
- **Unified API**: Block developers use a single interface while benefiting from proven asset management features
- **Feature Inheritance**: Block assets automatically support all Scripts/Styles features (deferred loading, conditions, environment-aware sources, replacement, inline assets, etc.)

### **Flattened API Design**

BlockRegistrar provides a **natural, WordPress-like API** with a clean flattened configuration:

**Flattened Configuration:**

```php
$block_registrar->add([
    'block_name' => 'my-plugin/hero',
    'title' => 'Hero Block',
    'description' => 'A hero section block',
    'category' => 'design',
    'supports' => ['align' => true],
    'render_callback' => 'render_hero',
    'assets' => [...]
]);
```

**Key Benefits:**

- **Natural WordPress API**: Familiar to WordPress developers
- **Full WordPress Support**: All `register_block_type()` properties supported
- **Arbitrary Properties**: Custom properties for plugin-specific configuration
- **Clean Architecture**: No nested configuration objects required
- **Better Discoverability**: IDE autocomplete for WordPress properties

**Arbitrary Properties Support:**

```php
$block_registrar->add([
    'block_name' => 'my-plugin/api-block',
    'title' => 'API Block',
    'render_callback' => 'render_api_block',

    // Custom properties accessible via $block->block_type->property_name
    'api_config' => ['endpoint' => 'https://api.example.com'],
    'display_options' => ['theme' => 'dark'],
    'plugin_metadata' => ['version' => '2.1.0']
]);
```

## Implementation Architecture

### **1. Block Asset Registration**

```php
// Block assets are registered with scope-specific loading
$block_registrar->add([
    'block_name' => 'my-plugin/hero-block',
    'block_config' => [
        'render_callback' => [$this, 'render_hero']
    ],
    'assets' => [
        'editor_scripts' => [
            [
                'handle' => 'hero-editor',
                'src' => 'blocks/hero/editor.js',
                'deps' => ['wp-blocks', 'wp-element']
                // Loads in editor context only
            ]
        ],
        'frontend_scripts' => [
            [
                'handle' => 'hero-frontend',
                'src' => 'blocks/hero/frontend.js'
                // Maps to WordPress view_script - loads only when block is present
            ]
        ],
        'dynamic_scripts' => [
            [
                'handle' => 'hero-dynamic',
                'src' => 'blocks/hero/dynamic.js',
                'hook' => 'render_block'
                // Loads during block render
            ]
        ]
    ]
]);
```

### **2. WordPress Hook Integration**

```php
// BlockRegistrar hooks into WordPress lifecycle automatically when stage() is called
public function stage(): self {
    // Set up block registration hooks for deferred blocks
    foreach ($this->blocks as $hook_name => $priorities) {
        foreach ($priorities as $priority => $blocks) {
            $this->_do_add_action($hook_name, function() use ($hook_name, $priority) {
                $this->_register_blocks($hook_name, $priority);
            }, $priority);
        }
    }

    // Enhance external block registrations with our asset system
    $this->_do_add_filter('register_block_type_args', [$this, '_integrate_block_assets'], 10, 2);

    // Set up asset management hooks (inherited from BlockAssetTrait)
    $this->_setup_asset_hooks();

    // Stage the underlying asset handlers
    $this->scripts_handler->stage();
    $this->styles_handler->stage();

    return $this;
}
```

### **3. Block Registration Integration**

```php
// BlockRegistrar provides unified block + asset registration
$block_registrar->add([
    [
        'block_name' => 'my-plugin/hero-block',
        // Default: immediate registration on 'init'
        'condition' => function() { return current_user_can('edit_posts'); },
        'block_config' => [
            'render_callback' => [$this, 'render_hero']
        ],
        'assets' => [/* asset definitions */]
    ],
    [
        'block_name' => 'my-plugin/admin-block',
        'hook' => 'admin_init',  // Exception: deferred registration
        'condition' => function() { return is_admin(); },
        'block_config' => [/* ... */],
        'assets' => [/* ... */]
    ]
]);
```

## Asset Loading Lifecycle

### **1. Block Presence Detection**

```php
// Early detection on 'wp' hook for static blocks
public function detect_block_presence(): array {
    if ($this->blocks_detected) {
        return $this->detected_blocks;
    }

    global $post;
    if (!$post || !has_blocks($post->post_content)) {
        return [];
    }

    $blocks = parse_blocks($post->post_content);
    $this->detected_blocks = $this->_extract_block_names($blocks);
    $this->blocks_detected = true;

    return $this->detected_blocks;
}
```

### **2. WordPress Integration for Conditional Loading**

```php
// Frontend assets automatically load only when block is present via WordPress view_script/view_style
// No manual conditions needed - WordPress handles this through our asset type mapping:
// 'frontend_scripts' → 'view_script' (WordPress 5.9+)
// 'frontend_styles'  → 'view_style' (WordPress 6.1+)

// Manual conditions only needed for dynamic assets or custom scenarios
foreach ($asset_config['dynamic_scripts'] ?? [] as $script) {
    $script['condition'] = function() use ($block_name) {
        return $this->_is_block_present($block_name);
    };
    $this->add_assets([$script], AssetType::Script);
}
```

### **3. WordPress Integration**

BlockRegistrar integrates with WordPress through **automatic filter-based enhancement** that happens behind the scenes when you call `stage()`:

```php
// Developer API - Simple block registration
$block_registrar->add([
    'block_name' => 'my-plugin/hero',
    'block_config' => [
        'render_callback' => [$this, 'render_hero']
    ],
    'assets' => [
        'editor_scripts' => [
            ['handle' => 'hero-editor', 'src' => 'blocks/hero/editor.js']
        ],
        'frontend_scripts' => [
            ['handle' => 'hero-frontend', 'src' => 'blocks/hero/frontend.js']
        ]
    ]
]);

$block_registrar->stage(); // Sets up WordPress integration automatically
```

#### **Internal Filter Integration**

When `stage()` is called, BlockRegistrar automatically registers a WordPress filter to enhance block registrations:

```php
// This happens automatically inside stage() - not called by developers
$this->_do_add_filter('register_block_type_args', [$this, '_integrate_block_assets'], 10, 2);

// Internal method that maps our asset handles to WordPress format
public function _integrate_block_assets(array $args, string $block_name): array {
    if (!isset($this->block_assets[$block_name])) {
        return $args;
    }

    $block_config = $this->block_assets[$block_name];

    // Map asset handles to WordPress expected format
    if (isset($block_config['editor_scripts'][0]['handle'])) {
        $args['editor_script'] = $block_config['editor_scripts'][0]['handle'];
    }
    if (isset($block_config['frontend_scripts'][0]['handle'])) {
        $args['script'] = $block_config['frontend_scripts'][0]['handle'];
    }
    if (isset($block_config['editor_styles'][0]['handle'])) {
        $args['editor_style'] = $block_config['editor_styles'][0]['handle'];
    }
    if (isset($block_config['frontend_styles'][0]['handle'])) {
        $args['style'] = $block_config['frontend_styles'][0]['handle'];
    }

    return $args;
}
```

## Usage Patterns

### **1. Simple Block with Assets**

```php
$block_registrar->add([
    'block_name' => 'my-plugin/simple-block',
    'block_config' => [
        'render_callback' => [$this, 'render_simple']
    ],
    'assets' => [
        'editor_scripts' => [
            ['handle' => 'simple-editor', 'src' => 'blocks/simple/editor.js']
        ],
        'frontend_styles' => [
            ['handle' => 'simple-style', 'src' => 'blocks/simple/style.css']
        ]
    ]
]);
```

### **2. Dynamic Block with Conditional Loading**

```php
$block_registrar->add([
    'block_name' => 'my-plugin/dynamic-block',
    'condition' => function() { return !is_admin(); }, // Block-level condition
    'block_config' => [
        'render_callback' => [$this, 'render_dynamic']
    ],
    'assets' => [
        'dynamic_scripts' => [
            [
                'handle' => 'dynamic-script',
                'src' => 'blocks/dynamic/script.js',
                'hook' => 'render_block'
            ]
        ]
    ]
]);
```

### **3. Multiple Blocks with Deferred Registration**

```php
$block_registrar->add([
    [
        'block_name' => 'my-plugin/block-a',
        'assets' => [
            'frontend_scripts' => [
                ['handle' => 'block-a-script', 'src' => 'blocks/a/script.js']
            ]
        ]
    ],
    [
        'block_name' => 'my-plugin/block-b',
        'hook' => 'admin_init', // Deferred registration
        'assets' => [
            'frontend_scripts' => [
                ['handle' => 'block-b-script', 'src' => 'blocks/b/script.js']
            ]
        ]
    ]
]);
```

## Environment-Aware Asset Loading

Block assets support **environment-specific source URLs** for development vs production workflows. This feature uses the Config system's `is_dev_environment()` method to automatically select appropriate assets.

### **Environment Detection Priority**

1. **Custom Callback** (if provided in Config): Developer-defined logic
2. **SCRIPT_DEBUG Fallback**: WordPress constant check

### **Single String Source (Simple)**

```php
$block_registrar->add([
    'block_name' => 'my-plugin/hero',
    'assets' => [
        'frontend_scripts' => [
            [
                'handle' => 'hero-script',
                'src' => 'assets/dist/blocks/hero/script.min.js', // Single URL
                'deps' => ['wp-blocks']
            ]
        ]
    ]
]);
```

### **Array Source with Dev/Prod Keys (Environment-Aware)**

```php
$block_registrar->add([
    'block_name' => 'my-plugin/hero',
    'assets' => [
        'frontend_scripts' => [
            [
                'handle' => 'hero-script',
                'src' => [
                    'dev'  => 'assets/src/blocks/hero/script.js',      // Unminified for development
                    'prod' => 'assets/dist/blocks/hero/script.min.js'  // Minified for production
                ],
                'deps' => ['wp-blocks']
            ]
        ],
        'frontend_styles' => [
            [
                'handle' => 'hero-style',
                'src' => [
                    'dev'  => 'assets/src/blocks/hero/style.css',
                    'prod' => 'assets/dist/blocks/hero/style.min.css'
                ]
            ]
        ]
    ]
]);
```

### **Custom Environment Detection**

```php
// In plugin configuration
$config = new Config([
    // ... other config
    'is_dev_callback' => function() {
        // Custom logic - could check:
        // - Environment variables: $_ENV['APP_ENV'] === 'development'
        // - Server hostname: strpos($_SERVER['HTTP_HOST'], 'localhost') !== false
        // - Custom constants: defined('MY_CUSTOM_DEV_MODE') && MY_CUSTOM_DEV_MODE
        // - Database settings, user roles, etc.

        return $_ENV['NODE_ENV'] === 'development'
            || wp_get_environment_type() === 'development'
            || (defined('WP_DEBUG') && WP_DEBUG);
    }
]);
```

### **Multiple Blocks with Environment Assets**

```php
$block_registrar->add([
    [
        'block_name' => 'my-plugin/hero',
        'assets' => [
            'frontend_scripts' => [
                [
                    'handle' => 'hero-script',
                    'src' => [
                        'dev'  => 'assets/src/blocks/hero/script.js',
                        'prod' => 'assets/dist/blocks/hero/script.min.js'
                    ]
                ]
            ]
        ]
    ],
    [
        'block_name' => 'my-plugin/cta',
        'assets' => [
            'frontend_scripts' => [
                [
                    'handle' => 'cta-script',
                    'src' => [
                        'dev'  => 'assets/src/blocks/cta/script.js',
                        'prod' => 'assets/dist/blocks/cta/script.min.js'
                    ]
                ]
            ]
        ]
    ]
]);
```

### **Environment Benefits**

- **Development**: Un-minified files for debugging, cache busting, source maps
- **Production**: Minified files for performance, stable versioning
- **Flexibility**: Custom environment detection beyond SCRIPT_DEBUG
- **Consistency**: Same pattern across all asset types (scripts, styles, media)

## Block Asset Preloading

The BlockRegistrar supports **preloading critical block assets** to improve performance by generating `<link rel="preload">` tags in the HTML head. This feature helps reduce render-blocking time for above-the-fold blocks and improves Core Web Vitals.

### **Preload Configuration**

Preloading is configured at the block level using the `preload` property:

```php
// Always preload critical assets
$block_registrar->add([
    'block_name' => 'my-plugin/hero-block',
    'preload' => true,
    'assets' => [
        'scripts' => [['handle' => 'hero-script', 'src' => 'hero.js']],
        'styles' => [['handle' => 'hero-style', 'src' => 'hero.css']]
    ]
]);

// Conditional preload based on context
$block_registrar->add([
    'block_name' => 'my-plugin/cta-block',
    'preload' => function() {
        return is_front_page() || is_page_template('page-landing.php');
    },
    'assets' => [
        'scripts' => [['handle' => 'cta-script', 'src' => 'cta.js']]
    ]
]);

// Inherit preload condition from block registration
$block_registrar->add([
    'block_name' => 'my-plugin/admin-block',
    'condition' => function() { return !is_admin(); },
    'preload' => 'inherit', // Use same condition as block registration
    'assets' => [
        'scripts' => [['handle' => 'admin-script', 'src' => 'admin.js']]
    ]
]);
```

### **Preload Inheritance Behavior**

The `'inherit'` option provides a DRY (Don't Repeat Yourself) approach for preload conditions:

- **With Block Condition**: If the block has a `condition` callable, preloading inherits that same condition
- **Without Block Condition**: If no block condition exists, assets are always preloaded (since the block itself would always register)
- **Implementation**: The block's condition is passed directly to the preload system during registration, eliminating the need to duplicate conditional logic

```php
// Example: Conditional block with inherited preload
$block_registrar->add([
    'block_name' => 'my-plugin/premium-block',
    'condition' => function() {
        return current_user_can('manage_options') && get_option('premium_enabled');
    },
    'preload' => 'inherit', // Preloads only when condition above is true
    'assets' => [
        'scripts' => [['handle' => 'premium-script', 'src' => 'premium.js']]
    ]
]);

// Example: Always-registered block with inherited preload
$block_registrar->add([
    'block_name' => 'my-plugin/public-block',
    // No condition = always registers
    'preload' => 'inherit', // Always preloads (since block always registers)
    'assets' => [
        'scripts' => [['handle' => 'public-script', 'src' => 'public.js']]
    ]
]);
```

### **Generated Output**

For preloaded blocks, the system generates appropriate preload tags in the HTML head:

```html
<link rel="preload" href="https://example.com/hero.js" as="script" />
<link
  rel="preload"
  href="https://example.com/hero.css"
  as="style"
  type="text/css"
/>
```

### **Implementation Details**

- **Hook Integration**: Preload tags are generated via `wp_head` hook (priority 2)
- **Asset Coverage**: All asset types are supported (scripts, styles, editor_scripts, dynamic_scripts, etc.)
- **Environment Support**: Environment-aware URLs are resolved automatically
- **Conditional Logic**: Callable conditions are evaluated at render time
- **Inheritance Support**: `'inherit'` option reuses block registration conditions for DRY principle
- **Performance**: Only blocks with preload configuration trigger the preload system
- **Efficient Architecture**: Block conditions are passed directly during registration, eliminating the need for runtime block definition lookups

### **Best Practices**

1. **Critical Assets Only**: Preload only above-the-fold or critical block assets
2. **Conditional Logic**: Use conditions to avoid over-preloading on irrelevant pages
3. **Performance Monitoring**: Test impact with PageSpeed Insights and Core Web Vitals
4. **Context Awareness**: Consider page templates, device types, and user context

## WP_Block_Type Collection and Introspection

The BlockRegistrar provides **WP_Block_Type object collection** functionality that allows developers to access and work with successfully registered block objects for advanced use cases like runtime introspection, dynamic rendering, and block relationship analysis.

### **Core Functionality**

When blocks are successfully registered with WordPress via `register_block_type()`, the BlockRegistrar automatically:

1. **Captures Return Values**: Monitors `register_block_type()` success/failure
2. **Stores WP_Block_Type Objects**: Collects successful registrations in an internal array
3. **Logs Registration Status**: Provides success and failure logging
4. **Provides Public Access**: Offers getter methods for developer access

### **Public API Methods**

```php
// Get all successfully registered WP_Block_Type objects
$all_blocks = $block_registrar->get_registered_block_types();
// Returns: array<string, \WP_Block_Type> indexed by block name

// Get a specific registered WP_Block_Type object
$hero_block = $block_registrar->get_registered_block_type('my-plugin/hero-banner');
// Returns: \WP_Block_Type|null
```

### **When to Access WP_Block_Type Objects**

**✅ Safe Access Points** (after WordPress 'init' hook):

- `wp_loaded` hook and later
- `wp` hook and later
- `wp_head` hook and later
- Any frontend rendering context
- Admin contexts after 'admin_init'

**❌ Unsafe Access Points** (before block registration):

- Plugin initialization
- `plugins_loaded` hook
- Before `init` hook fires

### **Practical Use Cases**

#### **1. Runtime Block Introspection**

```php
add_action('wp_loaded', function() use ($block_registrar) {
    $hero_block = $block_registrar->get_registered_block_type('my-plugin/hero-banner');

    if ($hero_block instanceof WP_Block_Type) {
        // Access block metadata
        $title = $hero_block->title;
        $description = $hero_block->description;
        $category = $hero_block->category;
        $attributes = $hero_block->attributes ?? [];
        $supports = $hero_block->supports ?? [];

        // Check asset availability
        $has_editor_script = isset($hero_block->editor_script);
        $has_view_script = isset($hero_block->view_script);
    }
});
```

#### **2. Dynamic Block Rendering**

```php
add_action('wp_loaded', function() use ($block_registrar) {
    $testimonial_block = $block_registrar->get_registered_block_type('my-plugin/testimonial');

    if ($testimonial_block && is_callable($testimonial_block->render_callback)) {
        // Programmatically render block with custom attributes
        $custom_attributes = [
            'quote' => 'Amazing service!',
            'author' => 'Happy Customer',
            'rating' => 5
        ];

        $rendered_content = call_user_func(
            $testimonial_block->render_callback,
            $custom_attributes,
            '',
            new WP_Block(['blockName' => 'my-plugin/testimonial'])
        );
    }
});
```

#### **3. Attribute Validation and Defaults**

```php
add_action('wp_loaded', function() use ($block_registrar) {
    $hero_block = $block_registrar->get_registered_block_type('my-plugin/hero-banner');

    if ($hero_block && isset($hero_block->attributes)) {
        // Extract default values for validation
        $default_attributes = [];
        foreach ($hero_block->attributes as $attr_name => $attr_config) {
            if (isset($attr_config['default'])) {
                $default_attributes[$attr_name] = $attr_config['default'];
            }
        }

        // Use for form validation, API responses, etc.
        function validate_hero_attributes($input) use ($default_attributes, $hero_block) {
            $validated = wp_parse_args($input, $default_attributes);

            // Validate against registered attribute schema
            foreach ($hero_block->attributes as $attr_name => $attr_config) {
                if (isset($validated[$attr_name])) {
                    $type = $attr_config['type'] ?? 'string';
                    // Perform type validation...
                }
            }

            return $validated;
        }
    }
});
```

#### **4. Asset Handle Access for Advanced Management**

```php
add_action('wp_loaded', function() use ($block_registrar) {
    $cta_block = $block_registrar->get_registered_block_type('my-plugin/cta-block');

    if ($cta_block) {
        // Access asset handles for advanced manipulation
        if (isset($cta_block->view_script)) {
            $script_handle = $cta_block->view_script;

            // Add localized data
            wp_localize_script($script_handle, 'ctaConfig', [
                'apiEndpoint' => rest_url('my-plugin/v1/cta-data'),
                'nonce' => wp_create_nonce('cta_nonce')
            ]);

            // Modify script loading
            wp_script_add_data($script_handle, 'async', true);
        }

        if (isset($cta_block->style)) {
            $style_handle = $cta_block->style;

            // Add inline styles
            wp_add_inline_style($style_handle, '
                .wp-block-my-plugin-cta-block.theme-dark {
                    background: #1a1a1a;
                    color: #ffffff;
                }
            ');
        }
    }
});
```

#### **5. Block Relationship Analysis**

```php
add_action('wp_loaded', function() use ($block_registrar) {
    $all_blocks = $block_registrar->get_registered_block_types();

    // Analyze block ecosystem
    $blocks_by_category = [];
    $blocks_with_render_callbacks = [];
    $blocks_with_assets = [];

    foreach ($all_blocks as $block_name => $wp_block_type) {
        // Group by category
        $category = $wp_block_type->category ?? 'uncategorized';
        $blocks_by_category[$category][] = $block_name;

        // Find dynamic blocks
        if (is_callable($wp_block_type->render_callback)) {
            $blocks_with_render_callbacks[] = $block_name;
        }

        // Find blocks with assets
        if (isset($wp_block_type->editor_script) || isset($wp_block_type->view_script) ||
            isset($wp_block_type->style) || isset($wp_block_type->editor_style)) {
            $blocks_with_assets[] = $block_name;
        }
    }

    // Use analysis for dashboard widgets, debug tools, etc.
});
```

#### **6. Theme Integration and Customization**

```php
add_action('wp_loaded', function() use ($block_registrar) {
    if (current_theme_supports('custom-blocks')) {
        $all_blocks = $block_registrar->get_registered_block_types();

        foreach ($all_blocks as $block_name => $wp_block_type) {
            $theme_support_key = 'custom-block-' . str_replace('/', '-', $block_name);

            if (current_theme_supports($theme_support_key)) {
                // Apply theme-specific modifications
                add_filter("render_block_{$block_name}", function($block_content, $block) use ($wp_block_type) {
                    // Add theme wrapper based on block configuration
                    $theme_class = 'theme-' . get_template();
                    return '<div class="' . esc_attr($theme_class) . '">' . $block_content . '</div>';
                }, 10, 2);
            }
        }
    }
});
```

#### **7. Plugin Extension and Block Variations**

```php
add_action('init', function() use ($block_registrar) {
    // Register block variations based on registered blocks
    $testimonial_block = $block_registrar->get_registered_block_type('my-plugin/testimonial');

    if ($testimonial_block) {
        // Create variations based on block attributes
        $variations = [
            [
                'name' => 'testimonial-5-star',
                'title' => '5-Star Testimonial',
                'attributes' => ['rating' => 5],
                'scope' => ['inserter']
            ],
            [
                'name' => 'testimonial-quote-only',
                'title' => 'Quote Only',
                'attributes' => ['showAuthor' => false],
                'scope' => ['inserter']
            ]
        ];

        foreach ($variations as $variation) {
            wp_register_block_pattern(
                "my-plugin/{$variation['name']}",
                [
                    'title' => $variation['title'],
                    'content' => "<!-- wp:{$testimonial_block->name} " .
                               json_encode($variation['attributes']) . " /-->",
                    'categories' => ['testimonials'],
                    'blockTypes' => [$testimonial_block->name]
                ]
            );
        }
    }
}, 20); // Run after blocks are registered
```

#### **8. Debugging and Development Tools**

```php
if (defined('WP_DEBUG') && WP_DEBUG) {
    add_action('wp_loaded', function() use ($block_registrar) {
        $all_blocks = $block_registrar->get_registered_block_types();

        foreach ($all_blocks as $block_name => $wp_block_type) {
            $debug_info = [
                'name' => $wp_block_type->name,
                'title' => $wp_block_type->title ?? 'No title',
                'has_attributes' => !empty($wp_block_type->attributes),
                'has_render_callback' => is_callable($wp_block_type->render_callback),
                'supports' => $wp_block_type->supports ?? [],
                'asset_handles' => [
                    'editor_script' => $wp_block_type->editor_script ?? null,
                    'view_script' => $wp_block_type->view_script ?? null,
                    'style' => $wp_block_type->style ?? null,
                    'editor_style' => $wp_block_type->editor_style ?? null
                ]
            ];

            error_log("Block Debug [{$block_name}]: " . json_encode($debug_info));
        }
    });
}
```

### **Success and Failure Logging**

The BlockRegistrar automatically logs registration outcomes:

```php
// Success logging (debug level)
// "BlockRegistrar - Successfully registered block 'my-plugin/hero-banner' with WordPress."

// Failure logging (warning level)
// "BlockRegistrar - Failed to register block 'my-plugin/broken-block' with WordPress."
```

### **Implementation Notes**

- **Storage**: WP_Block_Type objects are stored in `protected array $registered_wp_block_types`
- **Indexing**: Objects are indexed by block name for efficient lookup
- **Memory**: Only successful registrations are stored (failures are logged but not stored)
- **Thread Safety**: Collection is populated during WordPress's single-threaded initialization
- **Performance**: Getter methods have O(1) lookup time using array keys

## Benefits

1. **Performance**: Assets load only when blocks are present + preloading for critical resources
2. **Consistency**: Familiar API patterns from existing asset system
3. **Flexibility**: Supports immediate and deferred block registration
4. **Integration**: Seamless WordPress block registration enhancement
5. **Maintainability**: Extends proven asset management architecture
6. **Core Web Vitals**: Preloading improves LCP, FID, and overall user experience
7. **Block Introspection**: Access to WP_Block_Type objects enables advanced block manipulation and analysis
8. **Developer Experience**: Rich debugging and development tools through block object access
9. **Extensibility**: Plugin and theme integration capabilities through block metadata access

## Trade-offs

1. **Complexity**: Additional abstraction layer over WordPress block registration
2. **Learning Curve**: Developers must understand inverted default behavior
3. **Documentation**: Requires clear explanation of asset vs block patterns

## Future Considerations

1. **Block Asset Dependencies**: Automatic dependency resolution between blocks
2. **Asset Bundling**: Combine related block assets for performance
3. **Cache Integration**: Block presence caching across requests
4. **Development Tools**: Debug utilities for block asset loading

---

**Example Files**:

- **`examples/flattened-api-usage.php`**: Comprehensive examples of the new flattened API with WordPress properties and arbitrary custom properties
- **`examples/wordpress-vs-blockregistrar-conditional-loading.php`**: Comparison of WordPress built-in capabilities vs BlockRegistrar additions
- **`examples/block-preload-usage.php`**: Block asset preloading examples and best practices
- **`examples/wp-block-type-collection-usage.php`**: WP_Block_Type object collection, introspection, and advanced use cases

**Related TFSs**:

- **TFS-001**: Asset Deferral - Block assets inherit deferred loading capabilities
- **TFS-004**: Asset Queues - Block assets use the same queuing system as Scripts/Styles
- **TFS-005**: Scripts and Styles Management - BlockRegistrar composes ScriptsHandler and StylesHandler
- **TFS-007**: Asset Replacement - Block assets support the `replace` flag functionality
