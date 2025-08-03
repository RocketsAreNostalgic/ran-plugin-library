# Feature Proposal: Block Variations, Styles, and Patterns Integration

**Status**: Proposed
**Date**: 2025-01-08
**Deciders**: Development Team
**Technical Story**: Extend BlockRegistrar with unified API for block variations, styles, and patterns

## Context

The BlockRegistrar currently provides unified block + asset registration that integrates with WordPress's block registration system. However, WordPress also provides separate APIs for:

1. **Block Variations** (`register_block_variation()`) - Different configurations of the same block
2. **Block Styles** (`register_block_style()`) - CSS-based style variations
3. **Block Patterns** (`register_block_pattern()`) - Pre-configured combinations of blocks

Currently, developers must register these separately from blocks, missing opportunities for:

- Unified asset management across variations/styles/patterns
- Conditional loading of variation-specific assets
- Performance optimizations through integrated caching
- Consistent API patterns across all block-related registrations

## Problem Statement

**Current Pain Points:**

- **Fragmented APIs**: Blocks, variations, styles, and patterns registered separately
- **Manual Asset Management**: No automatic asset handling for variations/styles/patterns
- **No Conditional Loading**: Variations can't leverage BlockRegistrar's conditional asset system
- **Performance Gaps**: Missing optimization opportunities for variation-specific assets
- **Inconsistent Patterns**: Different registration approaches across block-related features

**Example of Current Fragmentation:**

```php
// Current approach - fragmented registration
register_block_type('my-plugin/button', [...]);
register_block_variation('my-plugin/button', [...]);
register_block_style('my-plugin/button', [...]);
register_block_pattern('my-plugin/hero-pattern', [...]);

// Manual asset management for each
wp_enqueue_style('button-cta-style', ...);
wp_enqueue_style('button-rounded-style', ...);
```

## Decision

Extend BlockRegistrar with **unified API support** for variations, styles, and patterns while maintaining backward compatibility and leveraging existing asset management infrastructure.

### **Phased Implementation Approach**

#### **Phase 1: Block Variations API (Priority 1)**

- Highest value alignment with BlockRegistrar's core strengths
- Clear use cases and technical simplicity
- Significant performance benefits from conditional variation assets

#### **Phase 2: Block Style Variations (Priority 2)**

- Medium complexity with clear asset management benefits
- Automatic style handle management and conditional loading

#### **Phase 3: Block Pattern Integration (Priority 3)**

- Most complex due to full block markup handling
- Requires research into pattern usage detection strategies

## Implementation Architecture

### **Core Location**

- **Primary**: `BlockRegistrar.php` - Main orchestrator for all block-related registration
- **Supporting**: Leverage existing `BlockAssetTrait` for asset management
- **Future**: Consider `BlockVariationTrait` if complexity grows

### **API Design Principles**

1. **Unified Configuration**: All block-related features in single configuration array
2. **Backward Compatibility**: Zero breaking changes to existing API
3. **Asset Integration**: Leverage existing Scripts/Styles infrastructure
4. **Conditional Logic**: Support same conditional patterns as main assets
5. **WordPress Compatibility**: Use standard WordPress APIs under the hood

### **Example Unified API**

```php
$block_registrar->add([
    'block_name' => 'my-plugin/button',
    'render_callback' => [$this, 'render_button'],

    // NEW: Unified variations with asset management
    'variations' => [
        [
            'name' => 'call-to-action',
            'title' => 'Call to Action',
            'attributes' => ['className' => 'is-style-cta'],
            'assets' => [
                'styles' => [
                    [
                        'handle' => 'button-cta-style',
                        'src' => 'variations/cta.css',
                        'condition' => function() { return !is_admin(); }
                    ]
                ]
            ]
        ]
    ],

    // FUTURE: Block styles with automatic handle management
    'styles' => [
        [
            'name' => 'rounded',
            'label' => 'Rounded',
            'assets' => [
                'styles' => [['handle' => 'button-rounded', 'src' => 'styles/rounded.css']]
            ]
        ]
    ],

    // FUTURE: Patterns with asset preloading
    'patterns' => [
        [
            'name' => 'hero-cta-pattern',
            'title' => 'Hero with CTA',
            'content' => '<!-- wp:my-plugin/button {"className":"is-style-cta"} -->',
            'preload' => function() { return is_front_page(); },
            'assets' => [
                'scripts' => [['handle' => 'hero-cta', 'src' => 'patterns/hero-cta.js']]
            ]
        ]
    ]
]);
```

## Phase 1 Implementation: Block Variations API

### **BlockRegistrar.php Modifications**

**New Protected Properties:**

```php
/**
 * Registered block variations for tracking.
 * @var array<string, array>
 */
protected array $registered_variations = [];

/**
 * Variation assets organized by block name and variation name.
 * @var array<string, array<string, array>>
 */
protected array $variation_assets = [];
```

**Core Methods:**

```php
/**
 * Process block variations for registration.
 */
protected function _process_block_variations(string $block_name, array $variations): void;

/**
 * Register variation-specific assets.
 */
protected function _register_variation_assets(string $block_name, string $variation_name, array $assets): void;

/**
 * Register block variations with WordPress.
 */
protected function _register_block_variations(string $block_name): void;
```

### **Integration Points**

1. **Modify `add()` method** to process 'variations' property
2. **Extend `_register_single_block()`** to register variations after block registration
3. **Leverage existing asset system** for variation asset management
4. **Integrate with conditional loading** for variation-specific assets

### **Asset Management Integration**

- **Variation Detection**: Extend block detection to identify which variations are used
- **Conditional Loading**: Variation assets use same conditional system as main assets
- **Performance Optimization**: Only load assets for variations actually present in content
- **Caching Integration**: Leverage existing request-level caching for variation lookups

## Benefits

### **For Developers**

- **Unified API**: Single configuration for all block-related features
- **Automatic Asset Management**: Variation/style/pattern assets handled automatically
- **Conditional Loading**: Advanced conditional logic for all asset types
- **Performance Optimization**: Built-in caching and conditional loading
- **Consistent Patterns**: Same API patterns across all features

### **For Performance**

- **Reduced Asset Loading**: Only load assets for variations/styles/patterns actually used
- **Request-Level Caching**: Optimize repeated lookups and registrations
- **Conditional Logic**: Skip unnecessary registrations based on context
- **Asset Preloading**: Intelligent preloading for critical variations/patterns

### **For Maintainability**

- **Centralized Management**: All block-related features in one place
- **Consistent Architecture**: Leverage existing proven patterns
- **WordPress Integration**: Use standard WordPress APIs under the hood
- **Backward Compatibility**: No breaking changes to existing code

## Risks and Mitigations

### **Risk: Increased Complexity**

- **Mitigation**: Phased implementation starting with highest-value features
- **Mitigation**: Maintain clear separation of concerns within BlockRegistrar
- **Mitigation**: Consider trait extraction if complexity grows

### **Risk: Performance Impact**

- **Mitigation**: Leverage existing caching infrastructure
- **Mitigation**: Conditional loading to avoid unnecessary asset registration
- **Mitigation**: Performance testing at each phase

### **Risk: WordPress API Changes**

- **Mitigation**: Use standard WordPress APIs under the hood
- **Mitigation**: Abstract WordPress API calls for easier adaptation
- **Mitigation**: Monitor WordPress core development for API changes

## Future Roadmap

### **Phase 2: Block Style Variations**

- Automatic style handle management
- Conditional style loading
- Integration with `register_block_style()` API

### **Phase 3: Block Pattern Integration**

- Pattern-asset coupling
- Conditional pattern registration
- Pattern asset preloading
- Research pattern usage detection strategies

### **Phase 4: Advanced Features**

- Cross-variation asset optimization
- Pattern relationship analysis
- Advanced conditional logic
- Performance monitoring integration

## Conclusion

This phased approach extends BlockRegistrar's unified API to cover all block-related WordPress features while maintaining architectural consistency and performance optimization. Starting with Block Variations provides immediate value while establishing patterns for future Style and Pattern integration.

The implementation leverages existing infrastructure, maintains backward compatibility, and provides significant developer experience and performance benefits.
