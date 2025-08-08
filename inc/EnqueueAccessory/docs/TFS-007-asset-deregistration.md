# TFS-007: Asset Deregistration and Replacement APIs

**Date:** 2025-07-29
**Status:** Accepted

## Context

WordPress developers frequently need to replace existing assets (scripts and styles) that have been registered by themes, plugins, or WordPress core. Common scenarios include:

- Replacing a theme's jQuery with a custom version
- Updating a plugin's script with a patched version
- Swapping WordPress core assets with optimized alternatives
- Re-registering assets with different dependencies or versions

Previously, developers had to manually dequeue and deregister assets before adding replacements, which was error-prone and required understanding WordPress's asset lifecycle timing. The asset enqueueing system needed a robust solution that would handle both explicit deregistration and automatic replacement scenarios.

## Decision

We have implemented two complementary APIs for asset deregistration and replacement:

### 1. Replace Flag API (Automatic Replacement)

A `replace` flag can be added to any asset definition. When set to `true`, the system automatically deregisters any existing asset with the same handle before registering the new one.

### 2. Explicit Deregistration API

The direct method for deregistering assets: `deregister()`, supports both immediate and deferred deregistration with flexible input formats, and exists on both `ScriptsEnqueueTrait` and `StylesEnqueueTrait`.

Both APIs integrate seamlessly with the existing immediate and deferred asset processing systems, ensuring proper timing and comprehensive logging.

## Implementation

### Replace Flag Integration

The replace flag is processed in `_concrete_process_single_asset()`, which calls `_deregister_existing_asset()` before asset registration when `replace: true` is detected.

### Deregistration Core Logic

The `_deregister_existing_asset()` method handles:

- WordPress core function calls (`wp_deregister_script`/`wp_deregister_style`)
- Verification of deregistration success
- Cleanup of internal asset queues (deferred assets, external inline assets)
- Comprehensive logging for debugging and tracing
- Graceful handling of protected WordPress core assets

WordPress will automatically handle dependance chains when using the replace flag, so long as the replace flag is set to true, and the hook timing is appropriate.

### Queue Cleanup

When assets are deregistered, the system automatically cleans up:

- Deferred asset queues across all hooks and priorities
- Empty priority and hook arrays to prevent memory leaks
- External inline asset queues

## Usage Examples

### Replace Flag API

#### Basic Replacement

```php
// Replace an existing jQuery with a custom version
$this->add([
    [
        'handle'  => 'jquery',
        'src'     => 'path/to/custom-jquery.js',
        'replace' => true  // Automatically deregisters existing jQuery
    ]
]);
```

#### Deferred Replacement

```php
// Replace a theme script that loads in the footer
$this->add([
    [
        'handle'   => 'theme-main-script',
        'src'      => 'path/to/improved-script.js',
        'hook'     => 'wp_footer',
        'priority' => 20,
        'replace'  => true
    ]
]);
```

#### Style Replacement

```php
// Replace theme styles with optimized versions
$this->add_styles([
    [
        'handle'  => 'theme-style',
        'src'     => 'path/to/optimized-theme.css',
        'replace' => true
    ]
]);
```

### Explicit Deregistration API

#### Simple Deregistration

```php
$admin = new EnqueueAdmin();

// Deregister scripts by handle
$admin->scripts->deregister('unwanted-script');
$admin->scripts->deregister(['script-1', 'script-2']);

// Deregister styles by handle
$admin->styles->deregister('unwanted-style');
$admin->styles->deregister(['style-1', 'style-2']);
```

#### Deferred Deregistration

```php
// Deregister at specific WordPress hooks
$admin->scripts->deregister_scripts([
    [
        'handle'   => 'late-script',
        'hook'     => 'wp_footer',
        'priority' => 15
    ]
]);
```

#### Immediate Deregistration

```php
// Deregister immediately (useful in early hooks)
$admin->scripts->deregister([
    [
        'handle'    => 'early-script',
        'immediate' => true
    ]
]);
```

#### Mixed Input Formats

```php
// Combine different input formats
$admin->scripts->deregister([
    'simple-handle',                    // String handle
    ['handle' => 'complex-script'],     // Array with just handle
    [                                   // Full definition
        'handle'   => 'deferred-script',
        'hook'     => 'wp_head',
        'priority' => 5
    ]
]);
```

## Error Handling and Edge Cases

### Protected Assets

WordPress core assets that cannot be deregistered are handled gracefully:

```php
// This will attempt deregistration but continue with replacement if it fails
$admin->scripts->add([
    [
        'handle'  => 'wp-admin-protected-script',
        'src'     => 'path/to/replacement.js',
        'replace' => true  // Will log warning but continue
    ]
]);
```

### Non-existent Assets

Attempting to replace or deregister non-existent assets is safe:

```php
// This will log debug info but won't cause errors
$admin->scripts->deregister('non-existent-script');
```

### Multiple Replacements

Each replacement operation is independent:

```php
// Multiple assets can replace the same handle safely
$admin->scripts->add([
    [
        'handle'  => 'shared-handle',
        'src'     => 'path/to/version-1.js',
        'replace' => true
    ]
]);

// Later in the code...
$admin->scripts->add([
    [
        'handle'  => 'shared-handle',
        'src'     => 'path/to/version-2.js',
        'replace' => true  // Will replace version-1
    ]
]);
```

## Logging and Debugging

Both APIs provide comprehensive logging for debugging:

```bash
[DEBUG] _concrete_process_single_asset - Asset 'jquery' has replace flag set to true. Attempting to deregister existing asset.
[DEBUG] _deregister_existing_asset - Attempting to deregister existing 'jquery' for replacement.
[DEBUG] _deregister_existing_asset - Successfully deregistered 'jquery'.
[DEBUG] _deregister_existing_asset - Successfully completed deregistration of 'jquery'.
```

For protected assets:

```bash
[WARNING] _deregister_existing_asset - Failed to deregister 'wp-admin-script'. It may be a protected WordPress core script or re-registered by another plugin.
[WARNING] _deregister_existing_asset - Deregistration of 'wp-admin-script' was only partially successful. Proceeding with replacement anyway.
```

## Alternatives Considered

### Manual Deregistration Only

We considered providing only explicit deregistration methods without the replace flag.

**Why this was rejected:** This would require developers to always make two separate calls (deregister, then add), increasing complexity and the chance for timing errors.

### Replace Flag Only

We considered providing only the replace flag without explicit deregistration methods.

**Why this was rejected:** Some use cases require deregistration without replacement, and explicit control over deregistration timing is valuable for complex scenarios.

### Automatic Detection

We considered automatically detecting and replacing assets with the same handle without requiring a flag.

**Why this was rejected:** This would be too aggressive and could cause unexpected behavior. Explicit opt-in via the replace flag provides better control and predictability.

## Consequences

### Pros

- **Developer Experience:** Simple, intuitive APIs that handle complex WordPress timing issues automatically
- **Flexibility:** Two complementary approaches cover different use cases and developer preferences
- **Safety:** Graceful handling of edge cases (protected assets, non-existent assets, multiple replacements)
- **Integration:** Seamless integration with existing immediate and deferred asset systems
- **Debugging:** Comprehensive logging makes troubleshooting straightforward
- **Performance:** Automatic cleanup prevents memory leaks from orphaned queue entries

### Cons

- **API Surface:** Adds two new public methods and one new asset property, increasing the learning curve
- **WordPress Limitations:** Cannot override WordPress's built-in protections for critical admin scripts
- **Timing Complexity:** Deferred deregistration still requires understanding of WordPress hook timing

## Migration Path

### From Manual Deregistration

```php
// Before: Manual approach
add_action('wp_enqueue_scripts', function() {
    wp_dequeue_script('theme-script');
    wp_deregister_script('theme-script');
    wp_enqueue_script('theme-script', 'path/to/new-script.js');
});

// After: Replace flag
$admin->scripts->add([
    [
        'handle'  => 'theme-script',
        'src'     => 'path/to/new-script.js',
        'replace' => true
    ]
]);
```

### From Complex Hook Management

```php
// Before: Manual hook timing
add_action('wp_footer',
 function() {
  wp_deregister_script('footer-script');
 },
 5
);

// After: Explicit deregistration
$admin->scripts->deregister([
    [
        'handle'   => 'footer-script',
        'hook'     => 'wp_footer',
        'priority' => 5
    ]
]);
```
