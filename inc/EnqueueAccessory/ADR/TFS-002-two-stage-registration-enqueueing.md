# TFS-002: Two-Stage Asset Registration and Enqueuing

**Date**: 2025-07-03
**Status**: Accepted
**Updated**: 2025-07-18

## Context

Managing JavaScript and CSS assets in WordPress requires adherence to a specific lifecycle: assets must first be **registered** with a unique handle and then **enqueued** to be included on a page. This library provides a streamlined, object-oriented interface for managing this process. The core architectural challenge is to provide a powerful and flexible API that aligns with WordPress best practices while remaining intuitive for developers.

### Input Validation and Error Handling

To ensure robustness and prevent silent failures, a strict, fail-fast validation strategy has been implemented at the point of asset addition.

1. **Centralized Validation**: All validation logic is consolidated within the `AssetEnqueueBaseTrait::add_assets()` method. This is the single entry point for adding both scripts and styles to the system.

2. **Strict Checks**: When `add_assets()` is called, it iterates through each provided asset definition and performs the following checks:

   - The asset **must** have a `handle` key with a non-empty string value.
   - The asset **must** have a `src` key, and its value must either be a non-empty string or the boolean `false`. An empty string or `null` is considered invalid.

3. **Fail-Fast with Exceptions**: If any asset definition fails these validation checks, the method immediately throws an `InvalidArgumentException`. The exception message clearly indicates which asset is invalid and why (e.g., "Invalid script definition at index 0. Asset must have a 'handle'.").

## Decision

We have implemented a decoupled, multi-stage process for asset management that mirrors the WordPress core lifecycle but provides more explicit control and safety. This state machine ensures that assets are registered and enqueued at the correct time, respecting WordPress's loading sequence.

The process is managed by the following primary methods, which must be called in a specific order:

1. **`add_assets()`** (e.g., `add_scripts()`): This is the first step. You add all asset definitions (both immediate and deferred) to an internal queue. This method only collects definitions and performs initial validation; it does no WordPress-related processing.

2. **`stage_assets()`** (e.g., `stage_scripts()`): This is the critical gatekeeper stage. It iterates through the assets added in the previous step.

   - **For deferred assets** (those with a `hook` key): Its main responsibility is to set up the WordPress action (`add_action`) that will process the asset when its specific hook fires. It registers WordPress hooks dynamically with closures during the staging process. Each deferred asset gets its hook registered with a closure that will be executed when the hook fires. It also moves the asset to a separate "deferred" queue, effectively taking it out of the immediate processing pipeline. This ensures deferred assets are handled in the correct context.
   - **For immediate assets** (those without a `hook` key): It processes them for registration (but not enqueueing) and keeps them in the main queue, ready for the next stage.

3. **`stage()`** (in handler classes like `EnqueuePublic`): This method orchestrates the staging process by calling `stage_assets()` on the appropriate handlers (scripts, styles, etc.).

   - It's typically hooked to WordPress actions like `wp_enqueue_scripts` to ensure assets are processed at the correct time in the WordPress lifecycle.
   - After staging, immediate assets are registered but not yet enqueued, while deferred assets have their hooks registered for later processing.

4. **Processing of Deferred Assets**: When a registered hook fires, the closure registered during the staging process is executed.

   - The closure calls the appropriate processing method with the hook name and priority.
   - This method retrieves the assets for that specific hook, processes them, and then removes them from the deferred queue.

   - It immediately copies the assets for that specific hook to a local variable and then **removes them from the main deferred queue**. This "copy and unset" pattern ensures that assets for a given hook are processed exactly once, preventing race conditions or duplicate processing if a hook were to fire multiple times.
   - It then iterates over the local copy, registering and enqueuing each asset.
   - This means the deferred asset queue is actively and safely emptied during the request lifecycle.

This architecture establishes a clear and predictable state flow for each asset, from addition to final output on the page.

### Handling Inline Assets

The system provides two flexible mechanisms for adding inline CSS and JavaScript, catering to different use cases.

**1. The `inline` Property (Simple Use Case)**

For simple cases where a piece of inline code is tightly coupled with a specific asset file, you can add an `inline` key directly to the asset's definition array.

- **How it works:** The inline content is attached to its parent asset during the standard registration/enqueuing process.
- **Requirement:** The parent asset must have a valid `src`.
- **Example:**

  ```php
  $this->add_styles([
      [
          'handle' => 'my-component-style',
          'src'    => 'path/to/style.css',
          'inline' => '.component { color: red; }'
      ]
  ]);
  ```

**2. Dedicated Methods: `add_inline_scripts()` & `add_inline_styles()` (Advanced Use Case)**

For more complex scenarios, such as adding inline code that is decoupled from the parent asset's definition or adding multiple inline blocks to the same parent, you can use the dedicated `add_inline_*` methods.

- **How it works:** These methods add inline asset definitions to a separate, dedicated queue. They are processed later when you call `enqueue_inline_scripts()` or `enqueue_inline_styles()`.
- **Requirement:** The `handle` property must refer to an _already registered_ asset. The inline content is provided via the `data` (for scripts) or `content` (for styles) key.
- **Example:**

  ```php
  // First, add the main asset
  $this->add_styles([['handle' => 'my-component-style', 'src' => 'path/to/style.css']]);

  // Later, from anywhere else...
  $this->add_inline_styles([
      [
          'handle'  => 'my-component-style',
          'content' => '.component .title { font-weight: bold; }',
      ]
  ]);

  // Finally, trigger the enqueueing of the inline assets
  $this->enqueue_inline_styles();
  ```

This approach provides greater flexibility for managing complex or conditionally-loaded inline assets.

#### Interaction with Deferred Assets

How inline assets are handled depends on the method used:

- **Using the `inline` property**: If an asset with an `inline` property is deferred (using the `hook` key), the inline script/style is automatically deferred with it. The inline data is processed at the same time the parent asset is registered and enqueued, ensuring correct timing.

- **Using `add_inline_*` methods**: This method requires that the parent asset be _already registered_. Since a deferred asset's registration is delayed until its hook fires, you must also delay the addition and enqueuing of the inline asset to the same hook (or a later one). Attempting to attach an inline asset to a deferred handle before its hook has fired will fail.

### Recommended Usage Pattern

The intended workflow is to instantiate a pre-built handler class (like `EnqueueAdmin` or `EnqueuePublic`) and use its methods to add assets. The handler takes care of hooking itself into WordPress and managing the entire registration and enqueueing lifecycle automatically.

**Pseudo-code Example:**

```php
// --- In a central service registration file (e.g., RegisterServices.php) ---

// 1. The system instantiates the appropriate handler.
//    Assuming $plugin_config is an available instance of ConfigInterface.
$public_asset_handler = new \Ran\PluginLib\EnqueueAccessory\EnqueuePublic( $plugin_config );

// 2. The handler's load() method is called to hook it into WordPress.
$public_asset_handler->load();


// --- In a different part of the plugin (e.g., a specific feature's class) ---

// 3. The developer gets access to the handler instance (e.g., via a service locator or DI).
//    They then add assets as needed.

// Example: Adding a script for the public-facing site.
$public_asset_handler->scripts()->add_scripts([
    [
        'handle'    => 'my-public-feature',
        'src'       => 'path/to/public-feature.js',
        'deps'      => ['jquery'],
        'ver'       => '1.0.0',
        'in_footer' => true
    ]
]);

// Example: Adding a style for the public-facing site.
$public_asset_handler->styles()->add_styles([
    [
        'handle' => 'my-public-styles',
        'src'    => 'path/to/public-styles.css'
    ]
]);

// Example: Adding a deferred script that will only load on a specific hook.
$public_asset_handler->scripts()->add_scripts([
    [
        'handle' => 'my-deferred-script',
        'src'    => 'path/to/deferred-script.js',
        'hook'   => 'wp_footer',  // This will be loaded when wp_footer fires
    ]
]);

// The registration and enqueuing is now handled automatically by the
// methods that EnqueuePublic::load() hooked into WordPress. The developer's
// only job is to add the assets.
```

This pattern provides flexibility, allowing a developer to define a large library of assets once, and then conditionally enqueue them based on the context of a specific page.

This pattern provides flexibility, allowing a developer to define a large library of assets once, and then conditionally enqueue them based on the context of a specific page.

## Consequences

This design choice has several significant consequences, both positive and negative.

### Strengths

- **Alignment with WordPress Core**: The two-step process (`register` then `enqueue`) directly maps to the standard, recommended WordPress pattern. This makes the system more predictable for experienced WordPress developers.
- **Enhanced Flexibility**: Developers gain the ability to register a comprehensive library of assets at an early stage (e.g., on `init`) and then conditionally enqueue only the specific assets needed for a particular page view. This is a powerful pattern for complex plugins and themes.
- **Improved Testability**: Decoupling the logic allows for more focused and robust unit testing. We can write tests that verify the registration logic independently from the enqueuing logic.
- **Clear Separation of Concerns**: Each method has a single, well-defined responsibility. `register_assets()` is concerned with making WordPress aware of assets, while `stage_assets()` is concerned with adding them to the page.

### Weaknesses

- **Implicit State Management**: The status of an asset is tracked implicitly by its presence in the internal queue. There is no explicit `is_registered` flag on the asset definition itself, which requires developers to understand the stateful nature of the process.
- **Legacy Helper Methods**: The system still maintains deprecated helper methods (`get_head_callbacks()`, `get_footer_callbacks()`, and `get_deferred_hooks()`) for backward compatibility, which adds some complexity to the codebase. These methods are marked for removal in a future release as described in TFS-001.

### Error Handling and Forgiveness

The system is designed to be both robust and forgiving, handling common developer errors gracefully while preventing critical misconfigurations.

- **Forgiving Enqueuing of Unregistered Assets**: If a developer skips the registration step for a non-deferred asset and calls `stage_assets()` directly, the system does not fail. Instead, it logs a `warning` to alert the developer of a potential logic error, then proceeds to both register and enqueue the asset. This behavior mirrors the flexibility of WordPress's native `wp_enqueue_script()` function.

- **Strict Handling of Deferred Assets**: In contrast, attempting to enqueue a _deferred_ asset (one defined with a `hook`) without proper setup is a critical misuse of the API. The `stage_assets()` method is essential for correctly parsing the hook and setting up the deferred action with dynamic closures. This prevents assets from being loaded in the wrong context (e.g., an admin script loading on the frontend).

### Relationship with TFS-001

This TFS complements TFS-001 ("Asset Enqueuing and Hook Registration"), which describes the evolution from a "look-ahead" mechanism to the current dynamic hook registration approach. While TFS-001 focuses on the hook registration strategy, this TFS focuses on the overall asset processing lifecycle.

The current implementation uses dynamic hook registration during the staging process, eliminating the need for the legacy helper methods (`get_head_callbacks()`, `get_footer_callbacks()`, and `get_deferred_hooks()`) described in TFS-001. These methods are maintained only for backward compatibility and will be removed in a future release.
