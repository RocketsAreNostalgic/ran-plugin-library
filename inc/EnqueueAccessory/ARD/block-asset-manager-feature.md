# Block-Aware Asset Manager Feature Implementation

## Overview

This document outlines the implementation of a block-aware asset manager that leverages our existing `AssetEnqueueBaseAbstract` and `AssetEnqueueBaseTrait` infrastructure to provide dynamic, server-side rendered block asset management while maintaining DRY principles and consistent patterns.

## Goals

1. **Extend Existing Infrastructure**: Leverage our proven asset management system for WordPress blocks
2. **Dynamic Block Focus**: Specifically target server-side rendered blocks with conditional asset loading
3. **Performance Optimization**: Load block assets only when blocks are present on the page
4. **Consistent API**: Maintain familiar patterns from our existing asset system
5. **WordPress Integration**: Seamlessly integrate with WordPress block registration and rendering lifecycle

## Architecture Review

### Current Asset System Strengths

- ✅ **Robust Asset Processing**: `_concrete_process_single_asset()` handles registration, enqueuing, and conditions
- ✅ **Deferred Asset Support**: Hook-based asset loading via `_enqueue_deferred_assets()`
- ✅ **Replace Flag Integration**: Asset replacement with `_deregister_existing_asset()`
- ✅ **Inline Asset Support**: Both immediate and external inline asset processing
- ✅ **Comprehensive Logging**: Full debugging and tracing capabilities
- ✅ **Validation & Error Handling**: Input validation and graceful error recovery
- ✅ **Cache-busting**: File-based versioning with `_generate_asset_version()`

### Extension Opportunities

- 🔄 **Block Context Awareness**: Extend asset processing to understand block presence
- 🔄 **Conditional Loading**: Leverage existing condition system for block-specific assets
- 🔄 **Hook Integration**: Use existing deferred asset system for block lifecycle hooks
- 🔄 **Asset Grouping**: Organize assets by block type for efficient management

## Implementation Plan

### Phase 1: Core Block Asset Manager

#### 1.1 Create BlockAssetManager Class

- [ ] **File**: `inc/EnqueueAccessory/BlockAssetManager.php`
- [ ] **Extends**: `AssetEnqueueBaseAbstract`
- [ ] **Purpose**: Primary interface for block asset management
- [ ] **Key Features**:
  - Block registration tracking
  - Asset-to-block mapping
  - WordPress block lifecycle integration
  - Conditional asset loading based on block presence

#### 1.2 Block Asset Registration System

- [ ] **Method**: `register_block_assets(string $block_name, array $asset_config)`
- [ ] **Storage**: Internal mapping of block names to asset definitions
- [ ] **Integration**: Use existing `add_assets()` method with block-specific metadata
- [ ] **Asset Types**:
  - Editor-only assets (admin context)
  - Frontend-only assets (public context)
  - Universal assets (both contexts)
  - Conditional assets (based on block presence)

#### 1.3 Block Presence Detection

- [ ] **Method**: `detect_block_presence(): array`
- [ ] **Implementation**: Parse post content for registered blocks
- [ ] **Caching**: Cache block presence per request to avoid multiple parsing
- [ ] **Hook Integration**: `wp` hook for early detection, `wp_enqueue_scripts` for asset loading

### Phase 2: WordPress Block Integration

#### 2.1 Block Registration Helper

- [ ] **File**: `inc/EnqueueAccessory/BlockRegistrar.php`
- [ ] **Purpose**: Simplify block registration with asset management
- [ ] **Method**: `register_block(string $block_name, array $config)`
- [ ] **Integration**:
  - Call WordPress `register_block_type()`
  - Register assets with `BlockAssetManager`
  - Set up render callbacks for dynamic blocks

#### 2.2 Dynamic Block Render Integration

- [ ] **Hook**: Integrate with block render callbacks
- [ ] **Asset Loading**: Trigger asset enqueuing during block rendering
- [ ] **Context Awareness**: Differentiate between editor and frontend contexts
- [ ] **Performance**: Avoid duplicate asset loading for multiple block instances

#### 2.3 WordPress Hook Integration

- [ ] **Editor Assets**: `enqueue_block_editor_assets` hook
- [ ] **Frontend Assets**: `wp_enqueue_scripts` hook with block presence detection
- [ ] **Block Assets**: `enqueue_block_assets` hook for universal assets
- [ ] **Dynamic Loading**: Asset loading during `render_block` for dynamic blocks

### Phase 3: Advanced Features

#### 3.1 Block Asset Grouping

- [ ] **Feature**: Group related assets by block family or plugin
- [ ] **Method**: `register_block_group(string $group_name, array $blocks, array $shared_assets)`
- [ ] **Benefits**: Reduce HTTP requests, share common dependencies
- [ ] **Implementation**: Extend existing asset processing to handle grouped assets

#### 3.2 Block Asset Replacement

- [ ] **Feature**: Replace default block assets with custom versions
- [ ] **Integration**: Use existing `replace` flag functionality
- [ ] **Method**: `replace_block_assets(string $block_name, array $replacement_assets)`
- [ ] **Use Cases**: Custom styling for core blocks, performance optimizations

#### 3.3 Block Asset Dependencies

- [ ] **Feature**: Automatic dependency resolution between block assets
- [ ] **Implementation**: Extend existing dependency handling
- [ ] **Method**: `add_block_dependency(string $block_name, string $dependency_block)`
- [ ] **Benefits**: Ensure required assets load when dependent blocks are present

### Phase 4: Performance Optimizations

#### 4.1 Block Asset Bundling

- [ ] **Feature**: Combine multiple block assets into bundles
- [ ] **Method**: `create_block_bundle(string $bundle_name, array $block_assets)`
- [ ] **Integration**: Use existing asset processing with bundle-specific handles
- [ ] **Benefits**: Reduce HTTP requests, improve loading performance

#### 4.2 Lazy Loading Integration

- [ ] **Feature**: Defer non-critical block assets until needed
- [ ] **Implementation**: Use existing deferred asset system
- [ ] **Triggers**: Intersection Observer, user interaction, scroll events
- [ ] **Method**: `defer_block_assets(string $block_name, string $trigger_event)`

#### 4.3 Block Asset Preloading

- [ ] **Feature**: Preload critical block assets
- [ ] **Method**: `preload_block_assets(array $critical_blocks)`
- [ ] **Implementation**: Generate `<link rel="preload">` tags
- [ ] **Integration**: Use existing render_head() functionality

## Technical Implementation Details

### 1. BlockAssetManager Class Structure

```php
class BlockAssetManager extends AssetEnqueueBaseAbstract {
    use BlockAssetTrait;

    protected array $block_assets = [];
    protected array $detected_blocks = [];
    protected array $block_groups = [];
    protected bool $blocks_detected = false;

    public function register_block_assets(string $block_name, array $asset_config): self
    public function detect_block_presence(): array
    public function enqueue_block_assets(): void
    public function replace_block_assets(string $block_name, array $replacement_assets): self
    public function create_block_bundle(string $bundle_name, array $block_assets): self
}
```

### 2. Asset Configuration Format

```php
$asset_config = [
    'editor_scripts' => [
        [
            'handle' => 'my-block-editor',
            'src' => 'blocks/my-block/editor.js',
            'deps' => ['wp-blocks', 'wp-element'],
            'version' => '1.0.0'
        ]
    ],
    'frontend_scripts' => [
        [
            'handle' => 'my-block-frontend',
            'src' => 'blocks/my-block/frontend.js',
            'deps' => ['jquery'],
            'version' => '1.0.0',
            'condition' => function() { return !is_admin(); }
        ]
    ],
    'editor_styles' => [...],
    'frontend_styles' => [...],
    'inline_assets' => [
        [
            'parent_handle' => 'my-block-frontend',
            'content' => 'var myBlockConfig = ' . wp_json_encode($block_config) . ';',
            'position' => 'before'
        ]
    ]
];
```

### 3. Block Registration Integration

```php
public function register_block(string $block_name, array $config): void {
    // Extract asset definitions
    $asset_config = $config['assets'] ?? [];

    // Register assets with our system
    $this->block_asset_manager->register_block_assets($block_name, $asset_config);

    // Register block with WordPress
    register_block_type($block_name, [
        'render_callback' => function($attributes, $content) use ($block_name) {
            // Trigger asset loading for this block
            $this->block_asset_manager->enqueue_block_specific_assets($block_name);

            // Call original render callback if provided
            if (isset($config['render_callback'])) {
                return call_user_func($config['render_callback'], $attributes, $content);
            }

            return $content;
        }
    ]);
}
```

### 4. Block Presence Detection

```php
protected function detect_block_presence(): array {
    if ($this->blocks_detected) {
        return $this->detected_blocks;
    }

    global $post;
    if (!$post || !has_blocks($post->post_content)) {
        $this->blocks_detected = true;
        return $this->detected_blocks = [];
    }

    $blocks = parse_blocks($post->post_content);
    $detected = [];

    foreach ($blocks as $block) {
        if (!empty($block['blockName']) && isset($this->block_assets[$block['blockName']])) {
            $detected[] = $block['blockName'];
        }

        // Handle nested blocks recursively
        if (!empty($block['innerBlocks'])) {
            $detected = array_merge($detected, $this->detect_nested_blocks($block['innerBlocks']));
        }
    }

    $this->detected_blocks = array_unique($detected);
    $this->blocks_detected = true;

    return $this->detected_blocks;
}
```

## Integration Points with Existing System

### 1. Asset Processing Pipeline

- **Reuse**: `_concrete_process_single_asset()` for all block asset processing
- **Extend**: Add block context to asset definitions
- **Enhance**: Block-specific condition callbacks

### 2. Deferred Asset System

- **Leverage**: Existing hook-based deferred loading
- **Extend**: Block-specific hooks (`render_block`, `wp_enqueue_scripts`)
- **Optimize**: Conditional loading based on block presence

### 3. Asset Replacement System

- **Reuse**: Existing `replace` flag functionality
- **Extend**: Block-specific replacement scenarios
- **Enhance**: Bulk replacement for block families

### 4. Logging and Debugging

- **Maintain**: Consistent logging patterns
- **Extend**: Block-specific context information
- **Enhance**: Block presence detection logging

## Testing Strategy

### 1. Unit Tests

- [ ] **File**: `Tests/Unit/EnqueueAccessory/BlockAssetManagerTest.php`
- [ ] **Coverage**: All public methods and core functionality
- [ ] **Patterns**: Follow existing test patterns from `AssetEnqueueBaseTraitTest.php`
- [ ] **Mocking**: WordPress block functions (`has_blocks`, `parse_blocks`, etc.)

### 2. Integration Tests

- [ ] **File**: `Tests/Integration/EnqueueAccessory/BlockIntegrationTest.php`
- [ ] **Scenarios**: Full block registration and asset loading workflows
- [ ] **WordPress**: Test with actual WordPress block system
- [ ] **Performance**: Asset loading performance with multiple blocks

### 3. Test Scenarios

- [ ] Block presence detection accuracy
- [ ] Conditional asset loading based on block presence
- [ ] Asset replacement for existing blocks
- [ ] Editor vs frontend asset separation
- [ ] Dynamic block render callback integration
- [ ] Block asset bundling and optimization
- [ ] Error handling for invalid block configurations

## Documentation Requirements

### 1. API Documentation

- [ ] **File**: `inc/EnqueueAccessory/ARD/ADR-008-block-asset-management.md`
- [ ] **Content**: Architecture decisions, API usage, examples
- [ ] **Format**: Follow existing ADR format and structure

### 2. Usage Examples

- [ ] Basic block asset registration
- [ ] Dynamic block with conditional assets
- [ ] Block asset replacement scenarios
- [ ] Performance optimization techniques
- [ ] Integration with existing asset management

### 3. Migration Guide

- [ ] Converting from manual block asset management
- [ ] Integrating with existing WordPress block registration
- [ ] Performance considerations and best practices

## Success Criteria

### 1. Functionality

- ✅ **Block Asset Registration**: Simple API for registering block-specific assets
- ✅ **Conditional Loading**: Assets load only when blocks are present
- ✅ **WordPress Integration**: Seamless integration with WordPress block system
- ✅ **Performance**: No unnecessary asset loading or HTTP requests

### 2. Code Quality

- ✅ **DRY Principles**: Maximum reuse of existing asset management infrastructure
- ✅ **Consistent Patterns**: Follow established coding and testing patterns
- ✅ **Comprehensive Testing**: Full test coverage with realistic scenarios
- ✅ **Documentation**: Complete API documentation and usage examples

### 3. Performance

- ✅ **Minimal Overhead**: Block detection and asset loading with minimal performance impact
- ✅ **Efficient Loading**: Conditional loading prevents unnecessary asset requests
- ✅ **Caching**: Appropriate caching of block presence detection
- ✅ **Optimization**: Bundle and preload capabilities for performance tuning

## Implementation Timeline

### Week 1: Core Infrastructure

- [ ] Create `BlockAssetManager` class
- [ ] Implement basic block asset registration
- [ ] Add block presence detection
- [ ] Create initial unit tests

### Week 2: WordPress Integration

- [ ] Create `BlockRegistrar` helper class
- [ ] Implement WordPress hook integration
- [ ] Add dynamic block render integration
- [ ] Expand test coverage

### Week 3: Advanced Features

- [ ] Implement block asset replacement
- [ ] Add block asset grouping
- [ ] Create performance optimization features
- [ ] Complete integration tests

### Week 4: Documentation and Polish

- [ ] Write comprehensive ADR documentation
- [ ] Create usage examples and migration guide
- [ ] Performance testing and optimization
- [ ] Final code review and refinement

## Risk Assessment

### Technical Risks

- **WordPress Compatibility**: Ensure compatibility across WordPress versions
- **Performance Impact**: Block presence detection could impact page load times
- **Asset Conflicts**: Potential conflicts with other block asset management systems

### Mitigation Strategies

- **Extensive Testing**: Test across multiple WordPress versions and configurations
- **Performance Monitoring**: Implement performance metrics and optimization
- **Graceful Degradation**: Fallback mechanisms for compatibility issues

## Future Enhancements

### 1. Block Asset Analytics

- Track block usage and asset loading patterns
- Performance metrics for block-specific assets
- Optimization recommendations based on usage data

### 2. Block Asset CDN Integration

- Automatic CDN deployment for block assets
- Geographic optimization for block asset delivery
- Cache invalidation strategies for block updates

### 3. Block Asset Security

- Content Security Policy integration for block assets
- Asset integrity verification
- Secure asset loading for sensitive blocks

---

This feature implementation will provide a comprehensive, performance-optimized solution for managing WordPress block assets while maintaining consistency with our existing, well-tested asset management infrastructure.
