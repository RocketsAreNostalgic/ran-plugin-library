# TFS-001: Asset Enqueuing and Hook Registration

**Date:** 2025-06-25
**Status:** Accepted
**Updated:** 2025-07-18

## Context

The WordPress plugin asset enqueueing lifecycle requires that all actions (like `add_action('wp_head', ...)` or `add_action('custom_hook', ...)` ) be registered during the `wp_enqueue_scripts` hook. However, the full processing of assets—which determines if they have head/footer callbacks or are deferred to a custom hook—happens within the same `wp_enqueue_scripts` hook.

This creates a critical timing issue: the system doesn't know it needs to register a `wp_head` action until _after_ the window to register that action has already passed. The `EnqueuePublic::stage_assets()/EnqueueAdmin::stage_assets()` methods, which are responsible for registering these hooks, runs before the asset processing is complete.

## Decision

The system registers hooks dynamically during the asset staging process, registering WordPress actions with closures for each deferred asset at the time of staging.

This approach has several advantages:

1. **Simplified Logic**: No need to scan assets twice (once for look-ahead, once for processing)
2. **Direct Hook Registration**: Hooks are registered exactly when they're needed during the staging process
3. **Reduced Coupling**: Asset processing is no longer tied to the internal structure of the raw asset array

The legacy helper methods (`get_head_callbacks()`, `get_footer_callbacks()`, and `get_deferred_hooks()`) have been marked as deprecated and will be removed in a future release. They are currently maintained only for backward compatibility with existing tests and implementations.

## API Design

### Public Interface

The asset enqueueing system provides a simple, predictable API that abstracts away the complexity of WordPress hook timing:

#### Core Methods

```php
// Add assets to the system
public function add_scripts(array $scripts): self
public function add_styles(array $styles): self

// Stage assets for processing
public function stage(): self

// Enqueue immediate assets
public function enqueue_immediate(): self
```

#### Asset Definition Format

```php
$asset_definition = [
    'handle' => 'my-script',                    // Required: Unique identifier
    'src' => 'path/to/script.js',              // Required: Asset source
    'deps' => ['jquery', 'wp-api'],            // Optional: Dependencies
    'ver' => '1.0.0',                          // Optional: Version
    'in_footer' => true,                        // Optional: Script placement
    'hook' => 'wp_footer',                      // Optional: Deferred hook
    'priority' => 10,                           // Optional: Hook priority
    'condition' => function() {                 // Optional: Conditional loading
        return is_single() && has_block('my-block');
    }
];
```

### Hook Registration API

The system automatically handles hook registration for deferred assets:

```php
// Deferred asset - hook registered automatically during staging
$handler->add_scripts([
    [
        'handle' => 'deferred-script',
        'src' => 'path/to/deferred.js',
        'hook' => 'wp_footer',
        'priority' => 20
    ]
]);

// Immediate asset - requires manual enqueueing
$handler->add_scripts([
    [
        'handle' => 'immediate-script',
        'src' => 'path/to/immediate.js'
    ]
]);
```

### Usage Examples

#### Basic Asset Registration

```php
class MyPlugin {
    private EnqueuePublic $asset_handler;

    public function __construct(ConfigInterface $config) {
        $this->asset_handler = new EnqueuePublic($config);
        $this->asset_handler->load();
    }

    public function init() {
        // Add assets
        $this->asset_handler->scripts()->add([
            [
                'handle' => 'my-public-script',
                'src' => 'assets/js/public.js',
                'deps' => ['jquery'],
                'ver' => '1.0.0',
                'in_footer' => true
            ]
        ]);

        $this->asset_handler->styles()->add([
            [
                'handle' => 'my-public-style',
                'src' => 'assets/css/public.css',
                'deps' => ['wp-block-library']
            ]
        ]);
    }
}
```

#### Deferred Asset Loading

```php
// Assets that load on specific hooks
$this->asset_handler->scripts()->add([
    [
        'handle' => 'footer-script',
        'src' => 'assets/js/footer.js',
        'hook' => 'wp_footer',
        'priority' => 10
    ],
    [
        'handle' => 'admin-script',
        'src' => 'assets/js/admin.js',
        'hook' => 'admin_enqueue_scripts',
        'condition' => function() { return is_admin(); }
    ]
]);
```

#### Conditional Asset Loading

```php
// Assets that load based on conditions
$this->asset_handler->scripts()->add([
    [
        'handle' => 'single-post-script',
        'src' => 'assets/js/single-post.js',
        'condition' => function() { return is_single(); }
    ],
    [
        'handle' => 'block-specific-script',
        'src' => 'assets/js/block-specific.js',
        'condition' => function() { return has_block('my-plugin/block'); }
    ]
]);
```

### Error Handling

The system provides comprehensive error handling and validation:

```php
// Invalid asset definition throws exception
try {
    $handler->add_scripts([
        [
            'handle' => '',  // Invalid: empty handle
            'src' => 'script.js'
        ]
    ]);
} catch (InvalidArgumentException $e) {
    // Handle validation error
    error_log("Asset validation failed: " . $e->getMessage());
}

// Graceful handling of immediate assets
$handler->add_scripts([
    [
        'handle' => 'immediate-script',
        'src' => 'script.js'
        // No hook = immediate asset
    ]
]);

// Manual enqueueing required for immediate assets
$handler->enqueue_immediate();
```

### Configuration Options

The system supports various configuration options through the Config interface:

```php
$config = new Config([
    'asset_path' => 'assets/',
    'version' => '1.0.0',
    'debug' => WP_DEBUG,
    'cache_bust' => true
]);

$handler = new EnqueuePublic($config);
```

## Alternatives Considered

### Process Assets During `stage_assets()`

We considered eliminating a look-ahead mechanism by processing all assets inside the `EnqueuePublic::stage_assets()` method. This would populate the `head_callbacks`, `footer_callbacks`, and `deferred_assets` arrays directly.

**Why this was rejected:** The `stage_assets()` method is designed to run early in the WordPress lifecycle (e.g., on `init`) to schedule actions. The official and only correct time to _call_ `wp_enqueue_script()` and `wp_enqueue_style()` is during the `wp_enqueue_scripts` hook, which fires much later. Processing assets inside `stage_assets()` would force us to enqueue them too early, which would break all conditional loading (e.g., `is_page()`, `is_singular()`). This would be a critical loss of functionality and a major performance anti-pattern.

### Dynamic Hook Registration (Current Implementation)

Instead of using a look-ahead mechanism, we now register WordPress hooks dynamically during the asset staging process. Each deferred asset gets its hook registered with a closure that will be executed when the hook fires.

**Why this was adopted:** This approach eliminates the need for the look-ahead mechanism entirely. It simplifies the codebase by removing duplicate logic and reduces coupling between components. The `stage_assets()` method now only needs to ensure that assets are staged at the appropriate time (via the `wp_enqueue_scripts` hook), and the staging process itself handles all hook registrations.

## Consequences

### Pros

- **Functional Correctness:** The current approach ensures the enqueueing system is robust and reliable within the strict constraints of the WordPress action lifecycle.
- **Preserves Conditional Loading:** It allows the library to continue using `wp_enqueue_scripts` as its main hook, preserving the ability to use powerful conditional tags (e.g., `is_page()`) to stage_assets assets efficiently.
- **Encapsulated Complexity:** The timing complexity is handled internally. The end-user of the library has a simple, predictable API (`add_scripts()`, `add_styles()`) that works as expected.
- **Reduced Duplication:** The dynamic hook registration approach eliminates the duplication of logic that existed in the look-ahead mechanism.
- **Improved Maintainability:** With hooks registered directly during staging, the code is more straightforward and easier to maintain.

### Cons

- **Backward Compatibility:** Legacy helper methods (`get_head_callbacks()`, `get_footer_callbacks()`, and `get_deferred_hooks()`) must be maintained for backward compatibility until they can be safely removed.
- **Developer Responsibility:** With immediate assets, developers must manually invoke them at the appropriate time, which requires clear documentation and understanding of the WordPress hook system.

## Legacy Helper Methods

The following methods have been marked as deprecated and will be removed in a future release:

### `get_head_callbacks()`

Originally used to identify assets that required callbacks in the document head. This method scanned both processed and unprocessed assets to determine if `wp_head` hooks needed to be registered.

### `get_footer_callbacks()`

Similar to `get_head_callbacks()`, but for footer callbacks. It identified assets that needed to be processed during the `wp_footer` hook.

### `get_deferred_hooks()`

Identified custom hooks that needed to be registered for deferred assets. This method was crucial for the look-ahead mechanism but is now redundant as hooks are registered directly during the staging process.

## Developer Guidelines

- **Deferred Assets:** These are automatically handled via hook registration during the staging process. No additional developer action is required.
- **Immediate Assets:** These require manual invocation by the developer at the appropriate time.
- **Asset Placement:** The placement of assets (header vs. footer) is determined by the asset definition itself (e.g., using the `'in_footer'` flag for scripts).
- **Timing:** The responsibility for ensuring assets are stage-assisted at the correct time lies with the developer.
