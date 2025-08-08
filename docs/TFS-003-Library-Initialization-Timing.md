# TFS-003: Library Initialization Timing and WordPress Hook Integration

## Status

**Accepted** - 2025-01-31

## Context

During the development of our EnqueueAccessory system, we identified critical timing considerations that affect how plugin authors should initialize and use our entire system. The library provides multiple asset management components (EnqueuePublic, EnqueueAdmin, EnqueueMedia, and BlockRegistrar) that integrate with different WordPress hooks, requiring careful coordination of initialization timing.

### The Core Problem

When plugin authors initialize our library components on the same hooks that those components need to register with WordPress, timing conflicts occur across the entire system:

```php
// PROBLEMATIC PATTERN - System-Wide Timing Conflicts
add_action('init', function() {
    // 1. Library components load
    $public_assets = new EnqueuePublic($config);
    $admin_assets = new EnqueueAdmin($config);
    $block_registrar = new BlockRegistrar($config);

    // 2. Immediate usage attempts
    $public_assets->scripts()->add([...]); // Will stage on wp_enqueue_scripts
    $admin_assets->styles()->add([...]);   // Will stage on admin_enqueue_scripts
    $block_registrar->add([...])->stage(); // ❌ Tries to register hook for 'init' but we're already on it!
});
```

### WordPress Hook Execution Timeline

Our library integrates with multiple WordPress hooks that execute in sequence:

```php
plugins_loaded          // ← Ideal library initialization point
    ↓
init                   // ← Block registration, asset addition
    ↓
wp_enqueue_scripts     // ← Public asset staging
admin_enqueue_scripts  // ← Admin asset staging
enqueue_block_editor_assets // ← Block editor asset staging
render_block           // ← Dynamic block asset loading
```

### System-Wide Timing Analysis

**BlockRegistrar Timing Requirements:**

- Needs to register WordPress actions for `init` (or other early hooks)
- Cannot register hooks for the same hook it's currently executing on
- Block registration must happen early for editor functionality

**EnqueuePublic/Admin Timing Requirements:**

- Hook registration happens in `load()` method
- Asset staging occurs on `wp_enqueue_scripts`/`admin_enqueue_scripts`
- These hooks fire **after** `init`, providing natural separation

**Library Architecture Requirements:**

- All components need to be available when developers want to use them
- Components must be initialized before their target hooks fire
- Consistent developer experience across all asset types

### Research Findings

Our investigation revealed that:

1. **WordPress framework pattern** is for libraries to load early (`plugins_loaded`) and provide APIs for later use
2. **Major WordPress frameworks** (ACF, WooCommerce, Jetpack) follow this initialization pattern
3. **Asset management hooks** (`wp_enqueue_scripts`, `admin_enqueue_scripts`) fire after `init`, providing natural timing separation
4. **Block registration** can occur on `plugins_loaded` or later, not strictly requiring `init`
5. **Hook registration conflicts** affect the entire deferred loading system, not just blocks

```text
plugins_loaded          // ← Ideal library initialization point
    ↓
init                   // ← Block registration, asset addition
    ↓
wp_enqueue_scripts     // ← Public asset staging
admin_enqueue_scripts  // ← Admin asset staging
enqueue_block_editor_assets // ← Block editor asset staging
render_block           // ← Dynamic block asset loading
```

## Decision

We recommend that **plugin authors initialize our entire library system on the `plugins_loaded` hook** rather than `init`. This creates clean separation between infrastructure initialization and application usage across all asset management components.

### Recommended System-Wide Initialization Pattern

```php
// RECOMMENDED PATTERN - Complete Library Initialization
add_action('plugins_loaded', function() {
    // 1. Initialize all library components early (infrastructure layer)
    $public_assets = new EnqueuePublic($config);
    $admin_assets = new EnqueueAdmin($config);
    $block_registrar = new BlockRegistrar($config);

    // 2. Set up hook registrations for asset managers
    $public_assets->load(); // Hooks to wp_enqueue_scripts
    $admin_assets->load();  // Hooks to admin_enqueue_scripts

    // 3. Components are now available for later use via plugin's service methods
});

add_action('init', function() {
    // 4. Developer uses all components (application layer)
    // Note: MyPlugin::get_*() methods are hypothetical plugin author implementations

    // Add public assets - will stage later on wp_enqueue_scripts ✅
    MyPlugin::get_public_assets()->scripts()->add(['handle' => 'my-public-script', ...]);

    // Add admin assets - will stage later on admin_enqueue_scripts ✅
    MyPlugin::get_admin_assets()->styles()->add(['handle' => 'my-admin-style', ...]);

    // Add blocks - will register immediately on init ✅
    MyPlugin::get_block_registrar()->add(['block_name' => 'my/block'])->stage();
});
```

## Rationale

### 1. Follows WordPress Framework Best Practices

This pattern aligns with how established WordPress frameworks operate across their entire ecosystem:

- **Infrastructure** (libraries, frameworks) loads on `plugins_loaded`
- **Application code** (blocks, assets, features) uses `init` and later hooks
- Clean architectural separation between system and application layers
- Consistent with ACF, WooCommerce, Jetpack initialization patterns

### 2. Eliminates System-Wide Timing Conflicts

By loading all library components before the `init` hook:

- **BlockRegistrar**: All WordPress hooks remain available for deferred registration
- **EnqueuePublic/Admin**: Hook registration occurs before asset staging hooks fire
- **Entire System**: No complex edge case handling required across any component
- **Predictable Behavior**: Deterministic execution order for all asset types

### 3. Maintains Complete API Consistency

This approach requires **zero changes** to our library implementation:

✅ **BlockRegistrar** implementation - unchanged
✅ **EnqueuePublic/Admin** implementation - unchanged
✅ **ScriptsHandler/StylesHandler** - unchanged
✅ **Developer APIs** - unchanged across all components
✅ **Asset management logic** - unchanged
✅ **Default hook behavior** - unchanged for all asset types

### 4. Optimizes Hook Execution Flow

The timing creates optimal WordPress integration:

```php
// Optimal Hook Flow:
plugins_loaded    // Library components initialize
    ↓
init             // Blocks register, assets added to handlers
    ↓
wp_enqueue_scripts      // Public assets stage automatically
admin_enqueue_scripts   // Admin assets stage automatically
enqueue_block_editor_assets // Block editor assets stage automatically
```

### 5. Future-Proof System Architecture

The pattern scales regardless of:

- Number of asset managers added to the system
- Complexity of hook dependencies across components
- Future WordPress core changes affecting any hook
- Additional library features (media handlers, custom post types, etc.)
- Integration with third-party asset optimization plugins

## Implementation Guidelines

### For Plugin Authors

**Complete Library System Initialization:**

> **Note**: The following example shows a hypothetical plugin implementation. The `set_asset_managers()`, `get_asset_managers()`, and similar service management methods are **not provided by our library** - they represent patterns that plugin authors would implement themselves for managing library components.

```php
class MyPlugin {
    private static $public_assets;
    private static $admin_assets;
    private static $block_registrar;

    public static function init() {
        add_action('plugins_loaded', [self::class, 'init_library']);
        add_action('init', [self::class, 'setup_assets_and_blocks']);
    }

    public static function init_library() {
        $config = new PluginConfig(); // Plugin author's config implementation

        // Initialize all asset management components
        self::$public_assets = new EnqueuePublic($config);
        self::$admin_assets = new EnqueueAdmin($config);
        self::$block_registrar = new BlockRegistrar($config);

        // Set up hook registrations for asset managers
        self::$public_assets->load(); // Hooks to wp_enqueue_scripts
        self::$admin_assets->load();  // Hooks to admin_enqueue_scripts
    }

    public static function setup_assets_and_blocks() {
        // Add public assets
        self::$public_assets->scripts()->add([
            'handle' => 'my-plugin-public',
            'src' => 'assets/js/public.js'
        ]);

        // Add admin assets
        self::$admin_assets->styles()->add([
            'handle' => 'my-plugin-admin',
            'src' => 'assets/css/admin.css'
        ]);

        // Register blocks
        self::$block_registrar
            ->add(['block_name' => 'my-plugin/hero'])
            ->stage();
    }

    // Plugin author implements these service management methods
    public static function get_public_assets(): EnqueuePublic {
        return self::$public_assets;
    }

    public static function get_admin_assets(): EnqueueAdmin {
        return self::$admin_assets;
    }

    public static function get_block_registrar(): BlockRegistrar {
        return self::$block_registrar;
    }
}
```

## References

- [WordPress Plugin API - plugins_loaded](https://developer.wordpress.org/reference/hooks/plugins_loaded/)
- [WordPress Plugin API - init](https://developer.wordpress.org/reference/hooks/init/)
- [WordPress Plugin API - wp_enqueue_scripts](https://developer.wordpress.org/reference/hooks/wp_enqueue_scripts/)
- [WordPress Plugin API - admin_enqueue_scripts](https://developer.wordpress.org/reference/hooks/admin_enqueue_scripts/)
- [Block Registration - WordPress Block Editor Handbook](https://developer.wordpress.org/block-editor/getting-started/fundamentals/registration-of-a-block/)
- [WordPress Plugin Development Best Practices](https://developer.wordpress.org/plugins/plugin-basics/best-practices/)
- [WordPress Asset Management - Enqueuing Scripts and Styles](https://developer.wordpress.org/themes/basics/including-css-javascript/)
- [WordPress Hook System - Actions and Filters](https://developer.wordpress.org/plugins/hooks/)
