# Feature: HooksManagementTrait Integration with EnqueueAccessory

## Overview

This feature integrates the `HooksManagementTrait` into the `EnqueueAccessory` system to provide enhanced hook management capabilities across the entire asset management toolchain. The integration leverages the existing inheritance structure to ensure a single HookManager instance is available throughout all asset handlers and their associated traits.

## Rationale

### Current State

The EnqueueAccessory system currently uses direct WordPress hook registration via `_do_add_action()` calls scattered throughout the codebase. This approach lacks:

- Centralized hook management
- Consistent debugging and introspection
- Conditional hook registration patterns
- Hook lifecycle management

### Inheritance Structure Analysis

The current inheritance chain provides an ideal integration point:

```php
AssetEnqueueBaseTrait (foundation trait)
    ↓
AssetEnqueueBaseAbstract (uses AssetEnqueueBaseTrait)
    ↓
ScriptsHandler/StylesHandler/MediaHandler (extend AssetEnqueueBaseAbstract)
    ↓
ScriptsEnqueueTrait/StylesEnqueueTrait/MediaEnqueueTrait (used by handlers)
```

By integrating `HooksManagementTrait` into `AssetEnqueueBaseTrait`, we ensure:

- **Single Instance**: One HookManager instance serves the entire asset management system
- **Inheritance Benefits**: All asset handlers and traits inherit the same hook management capabilities
- **Consistent Patterns**: Unified hook registration patterns across all asset types
- **Shared Context**: Hook context includes asset type information for better debugging

## Implementation Strategy

### Phase 1: Foundation Integration

#### 1.1 Add HooksManagementTrait to AssetEnqueueBaseTrait

**Before:**

```php
trait AssetEnqueueBaseTrait {
    use WPWrappersTrait;

    // ... existing properties and methods
}
```

**After:**

```php
trait AssetEnqueueBaseTrait {
    use WPWrappersTrait;
    use HooksManagementTrait;

    /**
     * Shared HookManager instance for the entire asset management toolchain.
     * This single instance will be available to all asset handlers and their traits.
     */
    private ?HooksManager $shared_hooks_manager = null;

    /**
     * Override the HooksManagementTrait's get_hooks_manager to use our shared instance.
     * This ensures a single HookManager instance is used throughout the entire asset management system.
     */
    protected function get_hooks_manager(): HooksManager {
        if ($this->shared_hooks_manager === null) {
            $this->shared_hooks_manager = new HooksManager($this, $this->get_logger());
        }
        return $this->shared_hooks_manager;
    }
}
```

#### 1.2 Replace Direct Hook Registration in Deferred Asset System

**Before:**

```php
// In AssetEnqueueBaseAbstract::stage_assets()
$callback = function () use ( $hook_name, $priority, $context ) {
    if ( method_exists( $this, $context ) ) {
        $this->{$context}( $hook_name, $priority );
    }
};

$hook_string = is_string($hook_name) ? $hook_name : (string)$hook_name;
$this->_do_add_action( $hook_string, $callback, $priority, 0 );
$this->registered_hooks[ $hook_name . '_' . $priority ] = true;
```

**After:**

```php
// In AssetEnqueueBaseAbstract::stage_assets()
$this->register_action_method(
    $hook_name,
    $context,
    $priority,
    0,
    [
        'context' => 'deferred_asset',
        'asset_type' => $asset_type->value,
        'hook_name' => $hook_name,
        'priority' => $priority
    ]
);
```

### Phase 2: Enhanced Hook Registration Patterns

#### 2.1 External Inline Asset Hook Registration

**Before:**

```php
// In AssetEnqueueBaseTrait::_add_inline_asset()
$enqueue_method = 'enqueue_external_inline_' . $asset_type->value . 's';
$this->_do_add_action( $hook, array( $this, $enqueue_method ), 11 );
$this->registered_external_hooks[$hook] = true;
```

**After:**

```php
// In AssetEnqueueBaseTrait::_add_inline_asset()
$enqueue_method = 'enqueue_external_inline_' . $asset_type->value . 's';
$this->register_action_method(
    $hook,
    $enqueue_method,
    11,
    1,
    [
        'context' => 'external_inline_asset',
        'asset_type' => $asset_type->value,
        'position' => 'after_assets'
    ]
);
```

#### 2.2 Block Registrar Hook Registration

**Before:**

```php
// In BlockRegistrar::_setup_asset_hooks()
$this->_do_add_action('wp_enqueue_scripts', array($this, 'stage'), 10);
$this->_do_add_action('enqueue_block_editor_assets', array($this, '_enqueue_editor_assets'), 10);
$this->_do_add_action('wp_head', array($this, '_generate_preload_tags'), 2);
```

**After:**

```php
// In BlockRegistrar::_setup_asset_hooks()
$this->register_action_method(
    'wp_enqueue_scripts',
    'stage',
    10,
    1,
    ['context' => 'block_assets', 'asset_type' => 'block']
);

$this->register_action_method(
    'enqueue_block_editor_assets',
    '_enqueue_editor_assets',
    10,
    1,
    ['context' => 'block_editor', 'asset_type' => 'block']
);

$this->register_action_method(
    'wp_head',
    '_generate_preload_tags',
    2,
    1,
    ['context' => 'block_preload', 'asset_type' => 'block']
);
```

### Phase 3: Conditional Hook Registration

#### 3.1 Admin vs Frontend Hook Registration

**Before:**

```php
// In EnqueueAdmin::load()
$this->_do_add_action('admin_enqueue_scripts', array($this, 'stage'));

// In EnqueuePublic::load()
$this->_do_add_action('wp_enqueue_scripts', array($this, 'stage'));
```

**After:**

```php
// In EnqueueAdmin::load()
$this->register_admin_action('admin_enqueue_scripts', [$this, 'stage']);

// In EnqueuePublic::load()
$this->register_frontend_action('wp_enqueue_scripts', [$this, 'stage']);
```

#### 3.2 Universal Asset Hook Registration

**Before:**

```php
// Manual registration for both admin and frontend
$this->_do_add_action('wp_head', array($this, 'render_head'));
$this->_do_add_action('admin_head', array($this, 'render_head'));
```

**After:**

```php
// Single registration that handles both contexts
$this->register_universal_action('wp_head', [$this, 'render_head']);
```

### Phase 4: Advanced Hook Management Features

#### 4.1 Hook Grouping for Asset Types

```php
// Group all script-related hooks
$this->register_hook_group('scripts_management', [
    [
        'type' => 'action',
        'hook' => 'wp_enqueue_scripts',
        'callback' => [$this, 'stage'],
        'priority' => 10,
        'context' => ['asset_type' => 'script', 'scope' => 'frontend']
    ],
    [
        'type' => 'action',
        'hook' => 'admin_enqueue_scripts',
        'callback' => [$this, 'stage'],
        'priority' => 10,
        'context' => ['asset_type' => 'script', 'scope' => 'admin']
    ]
]);
```

#### 4.2 Conditional Hook Registration

```php
// Register hooks only when specific conditions are met
$this->register_conditional_action(
    'wp_enqueue_scripts',
    [$this, 'enqueue_conditional_assets'],
    function() {
        return is_single() && has_block('my-custom-block');
    },
    10,
    1,
    ['context' => 'conditional_assets', 'asset_type' => 'script']
);
```

## Incremental Migration Approach

### Step 1: Foundation (Week 1)

- [ ] Add `HooksManagementTrait` to `AssetEnqueueBaseTrait`
- [ ] Implement shared HookManager instance
- [ ] Override `get_hooks_manager()` method
- [ ] Add basic tests for shared instance functionality

### Step 2: Deferred Asset System (Week 2)

- [ ] Replace direct `_do_add_action` calls in `stage_assets()`
- [ ] Update deferred asset hook registration
- [ ] Maintain backward compatibility with existing `_do_add_action` calls
- [ ] Add tests for deferred asset hook registration

### Step 3: External Inline Assets (Week 3)

- [ ] Replace external inline asset hook registration
- [ ] Update `_add_inline_asset()` method
- [ ] Test external inline asset functionality
- [ ] Verify hook deduplication works correctly

### Step 4: Block Registrar (Week 4)

- [ ] Update BlockRegistrar hook registration
- [ ] Implement conditional hook registration for blocks
- [ ] Test block asset loading functionality
- [ ] Verify editor vs frontend hook separation

### Step 5: Main Entry Points (Week 5)

- [ ] Update EnqueueAdmin hook registration
- [ ] Update EnqueuePublic hook registration
- [ ] Implement universal hook registration patterns
- [ ] Test admin vs frontend functionality

### Step 6: Advanced Features (Week 6)

- [ ] Implement hook grouping
- [ ] Add hook debugging and introspection
- [ ] Implement hook statistics and reporting
- [ ] Add comprehensive test coverage

### Step 7: Cleanup (Week 7)

- [ ] Remove deprecated `_do_add_action` calls
- [ ] Clean up old hook tracking arrays
- [ ] Update documentation
- [ ] Performance testing and optimization

## Benefits

### Immediate Benefits

1. **Centralized Hook Management**: Single HookManager instance manages all hooks
2. **Consistent Patterns**: Unified hook registration across all asset types
3. **Better Debugging**: Enhanced logging and introspection capabilities
4. **Reduced Code Duplication**: Shared hook management logic

### Long-term Benefits

1. **Conditional Logic**: Sophisticated hook registration patterns
2. **Performance**: Better hook deduplication and lifecycle management
3. **Maintainability**: Centralized hook management logic
4. **Testing**: Easier to mock and test hook interactions
5. **Extensibility**: Foundation for advanced hook management features

## Risks and Mitigation

### Breaking Changes

- **Risk**: Existing hook registration patterns may be affected
- **Mitigation**: Implement incrementally with feature flags and backward compatibility

### Performance Impact

- **Risk**: Additional overhead from HookManager instance
- **Mitigation**: Minimal impact - trait methods are lightweight and shared instance reduces overhead

### Complexity

- **Risk**: Increased complexity in hook management
- **Mitigation**: Start with simple integration, add advanced features gradually

### Testing

- **Risk**: Comprehensive testing required for hook interactions
- **Mitigation**: Extensive test coverage for all hook registration patterns

## Success Metrics

1. **Code Reduction**: 30% reduction in direct `_do_add_action` calls
2. **Consistency**: 100% of hook registrations use unified patterns
3. **Debugging**: Enhanced hook introspection capabilities
4. **Performance**: No measurable performance degradation
5. **Test Coverage**: 95%+ test coverage for hook management functionality

## Future Enhancements

1. **Hook Dependency Resolution**: Automatic hook dependency management
2. **Hook Performance Monitoring**: Real-time hook performance metrics
3. **Hook Validation**: Automatic hook registration validation
4. **Hook Lifecycle Management**: Advanced hook lifecycle tracking
5. **Hook Error Handling**: Comprehensive error handling for hook failures

## Conclusion

This integration provides a solid foundation for enhanced hook management across the entire EnqueueAccessory system. The inheritance-based approach ensures a single HookManager instance serves all asset types, while the incremental migration strategy minimizes risk and maintains backward compatibility. The enhanced hook management capabilities will improve debugging, consistency, and maintainability of the asset management system.
