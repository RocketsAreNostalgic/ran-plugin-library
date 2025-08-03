# Block Registration System Roadmap

**Status**: Planning Document
**Date**: 2025-01-08
**Deciders**: Development Team
**Purpose**: Consolidate future enhancements and planned features for the Block Registration ecosystem

## Overview

This roadmap consolidates planned enhancements across the Block Registration system, including asset management optimizations, WordPress Block Bindings API integration, and unified block variations/styles/patterns support.

## Current System Status

### ✅ Completed Features

- **BlockRegistrar**: Unified block and asset registration
- **Block Asset Manager**: Conditional loading, bundling, and performance optimization
- **Asset Integration**: Scripts, styles, and script modules support
- **Performance Features**: Hook-based deferring, conditional loading

### ✅ Recently Completed (2025-01)

- **BlockFactory/Block API**: Complete object-oriented block registration system
- **Optional BlockFactory**: Block constructor with shared instance support
- **Status Tracking**: Comprehensive block lifecycle visibility
- **Test Coverage**: 100% coverage for Block and BlockFactory classes
- **Example Files**: Modernized all examples with both modern and legacy API approaches
- **File Organization**: Example files renamed for feature clarity
- **Developer Experience**: Block-first approach with clear separation of concerns

### 🚧 In Progress

- **Documentation**: Final ADR updates and system documentation

## Roadmap Phases

### Phase 1: High Priority Features _(Next Release)_

#### 1.1 WordPress Block Bindings API Integration

**Priority**: High | **Complexity**: Medium | **Impact**: High

- [ ] **Feature**: Register binding sources alongside block definitions
- [ ] **API Extension**: Add `bindings_sources` property to block definitions
- [ ] **Integration**: Leverage existing conditional registration and logging
- [ ] **WordPress Version**: Requires WP 6.5+ for Block Bindings API

#### 1.2 WordPress Interactivity API Integration

**Priority**: High | **Complexity**: Medium | **Impact**: High

- [ ] **Feature**: Register interactive blocks with context data and store configuration
- [ ] **API Extension**: Add `interactivity` property to block definitions
- [ ] **Asset Integration**: Automatic handling of interactivity script modules
- [ ] **Context Management**: Declarative context data and store setup
- [ ] **WordPress Version**: Requires WP 6.5+ for Interactivity API

#### 1.3 BlockFactory/Block Object-Oriented API ✅ **COMPLETED**

**Priority**: High | **Complexity**: Medium | **Impact**: High | **Status**: ✅ Implemented

- [x] **BlockFactory**: Factory and coordinator for block lifecycle management
- [x] **Block Objects**: Individual block configuration with fluent interface
- [x] **API Design**: Block-first approach with clear separation of concerns
- [x] **Core Methods**: `add_block()`, `block()`, `register()`, `get_block_status()`
- [x] **Block Configuration**: `add_script()`, `add_style()`, `condition()`, `preload()`, `hook()`, etc.
- [x] **Optional Manager**: Block constructor defaults to shared BlockFactory instance
- [x] **Status Tracking**: Comprehensive block lifecycle visibility (staged, pending, registered, failed)
- [x] **WordPress Alignment**: Return values match WordPress conventions (WP_Block_Type|false)
- [x] **Error Handling**: Fail-fast behavior with clear error messages
- [x] **Testing**: 100% test coverage for all classes and methods
- [x] **Cross-Plugin Support**: Shared singleton pattern for plugin interoperability
- [x] **Documentation**: Comprehensive usage examples with modern API patterns

**Developer Experience**: ✅ Successfully transforms overwhelming "huge array" configuration into manageable, object-oriented block configuration while maintaining full feature access.

**Implementation Notes**:

- **Architecture**: BlockFactory serves as factory/coordinator, Block objects handle configuration
- **API Patterns**: Block-first approach with clear separation of concerns
- **Shared Instance**: Optional BlockFactory parameter with automatic shared instance fallback
- **WordPress Alignment**: Return values and patterns match WordPress conventions
- **Status Tracking**: Comprehensive lifecycle visibility (staged, pending, registered, failed)
- **Testing**: 100% coverage with isolated test instances
- **Ready for Enhancement**: Extensible architecture for Block Bindings, Interactivity API, etc.

**Complementary Relationship**: Block Bindings provides server-side dynamic data, while Interactivity API provides client-side reactive behavior. Together they enable complete dynamic interactive blocks.

**Implementation Overview - Unified Dynamic Interactive Blocks**:

```php
// Complete example showing both APIs working together
$block_registrar->add([
    'block_name' => 'my-plugin/dynamic-interactive-block',
    'render_callback' => [$this, 'render_block'],

    // Block Bindings: Server-side dynamic data
    'bindings_sources' => [
        'user-profile-source' => [
            'label' => 'User Profile Data',
            'get_value_callback' => [$this, 'get_user_profile_data'],
            'uses_context' => ['postId', 'userId']
        ]
    ],

    // Interactivity API: Client-side reactive behavior
    'interactivity' => [
        'namespace' => 'my-plugin/user-block',
        'context' => [
            'isExpanded' => false,
            'showDetails' => false
        ],
        'store_config' => [
            'debugMode' => WP_DEBUG
        ]
    ],

    // Asset management for interactive functionality
    'assets' => [
        'script_modules' => [
            'handle' => 'user-block-interactivity',
            'deps' => ['@wordpress/interactivity']
        ]
    ]
]);
```

**Data Flow**: Block Bindings provides server-side user data → Interactivity API enables client-side interactions with that data → Complete dynamic interactive block experience.

### Phase 2: Advanced Dynamic Block Features _(Medium Priority)_

#### 2.1 Advanced Block Bindings Features

**Priority**: Medium | **Complexity**: Medium | **Impact**: Medium

- [ ] **Conditional Bindings**: Apply same conditional logic as blocks
- [ ] **Asset Integration**: Binding-specific scripts and styles
- [ ] **Context Management**: Enhanced context passing and validation
- [ ] **Error Handling**: Comprehensive logging and fallback mechanisms

**Benefits**:

- Unified architecture for blocks and data sources
- Consistent developer experience
- Performance optimizations through integrated caching
- Enhanced debugging and logging capabilities

#### 2.2 Advanced Interactivity API Features

**Priority**: Medium | **Complexity**: Medium | **Impact**: Medium

- [ ] **Cross-Block Communication**: Shared state between different blocks
- [ ] **Context Inheritance**: Parent-child context data flow
- [ ] **Performance Optimization**: Selective reactivity and batched updates
- [ ] **Developer Tools**: Enhanced debugging and state inspection
- [ ] **SSR Integration**: Server-side rendering with hydration support

**Benefits**:

- Advanced interactive block patterns
- Improved performance for complex interactive UIs
- Better developer experience with debugging tools
- Enhanced SEO and accessibility through SSR

### Phase 3: Block Variations, Styles, and Patterns Integration _(Proposed)_

#### 3.1 Unified Variations API

**Priority**: High | **Complexity**: Medium | **Impact**: High

- [ ] **Feature**: Register block variations with integrated asset management
- [ ] **API Extension**: Add `variations` property to block definitions
- [ ] **Asset Support**: Variation-specific scripts, styles, and conditional loading
- [ ] **Performance**: Lazy load variation assets only when needed

**Implementation Preview**:

```php
$block_registrar->add([
    'block_name' => 'my-plugin/button',
    'variations' => [
        'cta-button' => [
            'title' => 'Call to Action',
            'attributes' => ['style' => 'cta'],
            'assets' => [
                'styles' => ['cta-button-style.css'],
                'condition' => 'is_front_page'
            ]
        ]
    ]
]);
```

#### 3.2 Block Styles Integration

**Priority**: Medium | **Complexity**: Low | **Impact**: Medium

- [ ] **Feature**: Register block styles with automatic asset management
- [ ] **API Extension**: Add `styles` property to block definitions
- [ ] **CSS Handling**: Automatic style enqueueing and conditional loading
- [ ] **Performance**: Style-specific asset optimization

#### 3.3 Block Patterns Support

**Priority**: Medium | **Complexity**: Medium | **Impact**: Medium

- [ ] **Feature**: Register patterns with asset dependency management
- [ ] **API Extension**: Add `patterns` property to block definitions
- [ ] **Asset Resolution**: Automatic detection and loading of pattern block assets
- [ ] **Conditional Loading**: Pattern-specific asset optimization

## Implementation Priorities

### High Priority (Next Release)

1. **Block Bindings API Integration** - High impact, medium complexity
2. **Block Variations with Asset Management** - High impact, addresses common use case

### Medium Priority (Future Releases)

1. **Block Asset Dependencies** - Valuable for enterprise use cases
2. **Block Styles Integration** - Completes the unified API vision
3. **Block Patterns Support** - Rounds out WordPress block ecosystem support

## Technical Considerations

### WordPress Script Modules Integration

WordPress 6.5+ introduced the Script Modules API, and our library provides enhanced functionality through `ScriptModulesEnqueueTrait`:

**Current WordPress Block Registration:**

```php
// Traditional approach
register_block_type('my-plugin/block', [
    'script' => 'my-block-script',  // Traditional script
    'view_script' => 'my-block-view'
]);

// WordPress native script modules
register_block_type('my-plugin/block', [
    'view_script_module' => 'my-block-module'  // Basic ES6 module
]);
```

**Our Enhanced Script Modules System:**
We have `ScriptModulesEnqueueTrait` that provides:

- **Advanced Asset Management**: Conditional loading, environment-specific sources
- **Request-Scoped Caching**: Performance optimization
- **Enhanced Data Passing**: Module data via `script_module_data_{$module_id}` filter
- **Granular Control**: `dequeue()`, `deregister()`, and `remove()` methods
- **Comprehensive Logging**: Debug and error tracking

**Current Gap - Block Integration:**

```php
// Our current system works independently
$script_modules_handler->add([
    'handle' => '@my-plugin/block-module',
    'src' => 'assets/block-module.js',
    'module_data' => ['config' => $block_config]
]);

// But doesn't integrate with BlockRegistrar
$block_registrar->add([
    'block_name' => 'my-plugin/block',
    // No direct script module support yet
]);
```

**Roadmap Opportunity:**
Integrating our `ScriptModulesEnqueueTrait` with `BlockRegistrar` would provide:

- **Unified Asset Management**: Scripts, styles, AND script modules in one place
- **Advanced Module Features**: Conditional loading, caching, enhanced data passing
- **Consistent API**: Same patterns across all asset types
- **Performance Benefits**: Leverage our optimization infrastructure

### WordPress Version Requirements

- **Block Bindings**: WordPress 6.5+ required
- **Script Modules**: WordPress 6.5+ required
- **Enhanced Patterns**: WordPress 6.0+ recommended
- **Modern Block APIs**: WordPress 5.8+ minimum
