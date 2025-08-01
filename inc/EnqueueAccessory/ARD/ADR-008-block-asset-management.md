# ADR-008: Block Asset Management Architecture

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
    foreach ($this->deferred_blocks as $hook_name => $priorities) {
        foreach ($priorities as $priority => $blocks) {
            $this->_do_add_action($hook_name, function() use ($hook_name, $priority) {
                $this->_register_deferred_blocks($hook_name, $priority);
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

### **Best Practices**

1. **Critical Assets Only**: Preload only above-the-fold or critical block assets
2. **Conditional Logic**: Use conditions to avoid over-preloading on irrelevant pages
3. **Performance Monitoring**: Test impact with PageSpeed Insights and Core Web Vitals
4. **Context Awareness**: Consider page templates, device types, and user context

## Benefits

1. **Performance**: Assets load only when blocks are present + preloading for critical resources
2. **Consistency**: Familiar API patterns from existing asset system
3. **Flexibility**: Supports immediate and deferred block registration
4. **Integration**: Seamless WordPress block registration enhancement
5. **Maintainability**: Extends proven asset management architecture
6. **Core Web Vitals**: Preloading improves LCP, FID, and overall user experience

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

**Related ADRs**:

- **ADR-001**: Asset Deferral - Block assets inherit deferred loading capabilities
- **ADR-004**: Asset Queues - Block assets use the same queuing system as Scripts/Styles
- **ADR-005**: Scripts and Styles Management - BlockRegistrar composes ScriptsHandler and StylesHandler
- **ADR-007**: Asset Replacement - Block assets support the `replace` flag functionality
