# ADR-006: Inline Assets Handling in AssetEnqueueBaseTrait

## Status

Accepted

## Date

2025-07-21

## Context

The `AssetEnqueueBaseTrait` manages several types of assets, including inline assets (CSS/JS code snippets) that need to be attached to parent assets. Understanding how these inline assets are handled throughout their lifecycle is crucial for maintaining and extending the codebase.

The trait maintains four key arrays for asset management:

1. `$assets` - Holds immediate assets after staging
2. `$deferred_assets` - Holds assets deferred to specific WordPress hooks
3. `$inline_assets` - Array for inline assets associated with plugin/theme assets
4. `$external_inline_assets` - Array for inline assets attached to external handles

This ADR documents the current state of how inline assets are handled throughout their lifecycle, from addition to processing and cleanup.

## Decision

This ADR documents the current implementation of inline asset handling in the `AssetEnqueueBaseTrait`. The key aspects of this implementation are:

1. Inline assets can be added through two pathways:

   - As part of asset definitions via `add()`/`add_assets()`
   - Separately via `add_inline()`/`add_inline_assets()`

2. Storage of inline assets depends on their parent asset location:
   - For immediate assets: stored within the parent asset definition in `$assets`
   - For deferred assets: stored within the parent asset definition in `$deferred_assets`
   - For external assets: stored in the `$external_inline_assets` array

## Consequences

### Current Implementation Observations

- The system effectively handles inline assets for immediate, deferred, and external assets
- The `_process_inline_assets()` method references and cleans up the `$inline_assets` array
- Unit tests expect the `$inline_assets` property to be populated, leading to test failures

### Array Structure

### Immediate Assets with Inline Assets

```php
$assets['my-style-handle'] = [
    'handle' => 'my-style-handle',
    'src' => 'path/to/style.css',
    'deps' => ['wp-components'],
    'ver' => '1.0.0',
    'media' => 'all',
    'inline' => [
        [
            'content' => 'body { background-color: #f1f1f1; }',
            'position' => 'after',
            'condition' => function() { return is_single(); }
        ],
        [
            'content' => '.header { padding: 20px; }',
            'position' => 'after',
            'condition' => null
        ]
    ]
];
```

### Deferred Assets with Inline Assets

```php
$deferred_assets['admin_enqueue_scripts'][10]['my-admin-script'] = [
    'handle' => 'my-admin-script',
    'src' => 'path/to/admin-script.js',
    'deps' => ['jquery', 'wp-api'],
    'ver' => '1.2.0',
    'in_footer' => true,
    'hook' => 'admin_enqueue_scripts',
    'priority' => 10,
    'inline' => [
        [
            'content' => 'const ADMIN_API = { root: "' . esc_js(rest_url()) . '" };',
            'position' => 'before',
            'condition' => null
        ],
        [
            'content' => 'console.log("Admin script loaded");',
            'position' => 'after',
            'condition' => function() { return is_admin(); }
        ]
    ]
];
```

### External Inline Assets

```php
$external_inline_assets['wp_enqueue_scripts']['jquery'] = [
    [
        'content' => 'jQuery(document).ready(function($) { console.log("Document ready!"); });',
        'position' => 'after',
        'condition' => null,
        'status' => 'registered'
    ]
];
```

Note that the structure of inline assets is consistent across immediate and deferred assets - they are stored as an array under the `'inline'` key of the parent asset. The only difference is in how the parent assets themselves are organized in their respective arrays.

## Asset Lifecycle Summary

For reference, here is the complete lifecycle of inline assets in the current implementation:

### 1. Adding Inline Assets

There are two main ways inline assets are added to the system:

1. **As part of asset definitions** via `add()` method:

   ```php
   $styles->add([
       'handle' => 'my-style',
       'src' => 'path/to/style.css',
       'inline' => 'body { background: red; }' // Inline CSS added here
   ]);
   ```

2. **Separately via `add_inline()`** method:

   ```php
   $styles->add_inline([
       'parent_handle' => 'my-style',
       'content' => 'body { background: red; }',
       'position' => 'after' // Optional, default is 'after'
   ]);
   ```

### 2. Storage and Processing

When inline assets are added, they're handled differently based on where their parent asset is located:

1. **For immediate assets** (in the `$assets` array):

   - Inline content is added directly to the parent asset's definition:

   ```php
   $asset['inline'][] = $inline_asset_definition;
   ```

2. **For deferred assets** (in the `$deferred_assets` array):

   - Similarly, inline content is added directly to the deferred parent asset:

   ```php
   $asset['inline'][] = $inline_asset_definition;
   ```

3. **For external assets** (registered by WordPress or other plugins):

   - Inline content is added to the `$external_inline_assets` array:

   ```php
   $this->external_inline_assets[$hook][$parent_handle][] = $inline_asset_definition;
   ```

   - WordPress actions are registered to process these at the appropriate hook time

### 3. Processing and Output

1. **For immediate assets**:

   - When `_process_single_asset()` is called during registration/enqueuing, it calls `_process_inline_assets()` for each asset
   - This adds the inline content to WordPress via `wp_add_inline_style()` or `wp_add_inline_script()`
   - After processing, the inline asset is removed from its storage array

2. **For deferred assets**:

   - When the hook for a deferred asset fires, `_enqueue_deferred_assets()` is called
   - This processes the deferred assets and their inline content
   - After processing, the deferred assets are removed from `$deferred_assets`

3. **For external assets**:
   - When the hook for external inline assets fires, `_enqueue_external_inline_assets()` is called
   - This calls `_process_inline_assets()` for each parent handle
   - After processing, the hook entry is removed from `$external_inline_assets`
