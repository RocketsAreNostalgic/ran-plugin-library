# Feature Proposal: WordPress Block Bindings API Integration

**Status**: Proposed
**Date**: 2025-01-08
**Deciders**: Development Team
**Technical Story**: Extend BlockRegistrar to support WordPress Block Bindings API (WP 6.5+)

## Context

WordPress 6.5 introduced the Block Bindings API, enabling dynamic data binding between block attributes and server-side data sources. This API allows blocks to display dynamic content by connecting their attributes to PHP functions that return live data.

### WordPress Block Bindings API Overview

- **Core Function**: `register_block_bindings_source()` - Registers custom data sources
- **Registration Hook**: Must be called from `init` hook or later
- **Data Flow**: Server-side PHP functions provide data to block attributes in real-time
- **Context Support**: Can access block context (postId, etc.) for contextual data
- **Conditional Registration**: Sources can be registered conditionally based on user capabilities, post types, etc.

### Key Concepts

1. **Binding Sources**: PHP-registered data providers (e.g., post meta, API data, user info)
2. **Block Attributes**: Frontend block properties that can be bound to dynamic data
3. **Context Awareness**: Sources can access WordPress block context for contextual data
4. **Real-time Updates**: Data is fetched server-side during block rendering

### Use Cases

- **Dynamic Content**: Bind paragraph text to post meta or API responses
- **User-Specific Data**: Display personalized content based on current user
- **Live Data**: Connect blocks to external APIs or database queries
- **Content Personalization**: Customize block content based on user roles or preferences

## Problem Statement

Our current `BlockRegistrar` class provides sophisticated block and asset registration but lacks support for the Block Bindings API. This creates gaps in:

- **Dynamic Data Integration**: Cannot register binding sources alongside block definitions
- **Unified Architecture**: Block registration and data binding are handled separately
- **Developer Experience**: No consistent API for managing blocks and their data sources
- **Conditional Loading**: Cannot apply same conditional logic to binding sources as blocks

### Current System Strengths

Our existing `BlockRegistrar` provides:

- Unified block and asset registration
- Conditional registration with hook/priority control
- Comprehensive logging and error handling
- Integration with asset management system
- Support for WordPress block properties and custom extensions

## Decision

Extend `BlockRegistrar` to support **integrated block bindings source registration** using the existing architectural patterns.

### Rationale for Integration

1. **Unified Interface**: Manage blocks, assets, and data sources in one place
2. **Consistent Patterns**: Apply same conditional loading and hook patterns to binding sources
3. **Architectural Coherence**: Follows existing `BlockRegistrar` design principles
4. **Developer Experience**: Single configuration object for complete block setup
5. **Logging Integration**: Leverage existing logging infrastructure for debugging

## Implementation Architecture

### Core Components

#### 1. Enhanced Block Definition Format

```php
// Extended block definition with bindings_sources
$block_definition = [
    'block_name' => 'my-plugin/dynamic-content',
    'title' => 'Dynamic Content Block',
    'category' => 'common',

    // NEW: Block Bindings Sources
    'bindings_sources' => [
        [
            'name' => 'my-plugin/post-meta',
            'label' => __('Post Meta Data', 'my-plugin'),
            'get_value_callback' => [$this, 'get_post_meta_value'],
            'uses_context' => ['postId'],
            'hook' => 'init',        // When to register (default: 'init')
            'priority' => 10,        // Hook priority (default: 10)
            'condition' => null      // Optional condition callback
        ],
        [
            'name' => 'my-plugin/user-data',
            'label' => __('User Information', 'my-plugin'),
            'get_value_callback' => [$this, 'get_user_data'],
            'condition' => function() { return is_user_logged_in(); }
        ]
    ],

    // Existing block properties
    'render_callback' => [$this, 'render_dynamic_content'],
    'assets' => [/* asset definitions */]
];
```

#### 2. BlockRegistrar Enhancement

```php
/**
 * Process block bindings sources from block definition.
 *
 * @param string $block_name The block name.
 * @param array  $block_definition The block definition.
 */
protected function _register_block_bindings_sources(string $block_name, array $block_definition): void {
    $logger = $this->get_logger();
    $context = get_class($this) . '::' . __FUNCTION__;

    if (!isset($block_definition['bindings_sources']) || !is_array($block_definition['bindings_sources'])) {
        return;
    }

    foreach ($block_definition['bindings_sources'] as $source_definition) {
        if (!isset($source_definition['name']) || !isset($source_definition['get_value_callback'])) {
            if ($logger->is_active()) {
                $logger->warning("{$context} - Block bindings source missing required 'name' or 'get_value_callback'. Skipping.");
            }
            continue;
        }

        $source_name = $source_definition['name'];
        $hook = $source_definition['hook'] ?? 'init';
        $priority = $source_definition['priority'] ?? 10;
        $condition = $source_definition['condition'] ?? null;

        // Register the binding source on the specified hook
        add_action($hook, function() use ($source_definition, $source_name, $condition, $logger, $context) {
            // Check condition if provided
            if ($condition && !call_user_func($condition)) {
                if ($logger->is_active()) {
                    $logger->debug("{$context} - Condition failed for bindings source '{$source_name}'. Skipping registration.");
                }
                return;
            }

            // Prepare arguments for register_block_bindings_source
            $args = [
                'label' => $source_definition['label'] ?? $source_name,
                'get_value_callback' => $source_definition['get_value_callback']
            ];

            if (isset($source_definition['uses_context'])) {
                $args['uses_context'] = $source_definition['uses_context'];
            }

            // Register with WordPress
            register_block_bindings_source($source_name, $args);

            if ($logger->is_active()) {
                $logger->debug("{$context} - Successfully registered block bindings source '{$source_name}'.");
            }
        }, $priority);

        if ($logger->is_active()) {
            $logger->debug("{$context} - Scheduled block bindings source '{$source_name}' for registration on '{$hook}' hook.");
        }
    }
}
```

#### 3. Integration with add() Method

```php
// In BlockRegistrar::add() method, after asset registration:

// Register block bindings sources if provided
if (isset($block_definition['bindings_sources'])) {
    $this->_register_block_bindings_sources($block_name, $block_definition);
}
```

## Benefits

### 1. Unified Architecture

- Single configuration object for blocks, assets, and data sources
- Consistent conditional loading patterns across all components
- Integrated logging and error handling

### 2. Developer Experience

- Familiar API patterns from existing asset system
- Clear separation of concerns within unified interface
- Comprehensive debugging through existing logging infrastructure

### 3. Flexibility

- Conditional registration of binding sources
- Custom hook timing and priorities
- Support for context-aware data sources

### 4. Performance

- No impact when bindings aren't used
- Leverages existing deferred loading system
- Conditional registration prevents unnecessary overhead

## Limitations

### 1. WordPress Version Dependency

- Requires WordPress 6.5+ for Block Bindings API
- Graceful degradation needed for older versions

### 2. Server-Side Only

- Block Bindings API is server-side focused
- Client-side interactivity requires separate solutions (Script Modules, Interactivity API)

### 3. Block Context Dependency

- Some binding sources require specific block context
- Context availability varies by block placement and type

## Usage Examples

### Basic Implementation

```php
class MyPlugin {
    use BlockRegistrarTrait;

    public function init() {
        $this->block_registrar->add([
            [
                'block_name' => 'my-plugin/user-profile',
                'title' => 'User Profile Block',
                'category' => 'widgets',

                'bindings_sources' => [
                    [
                        'name' => 'my-plugin/current-user',
                        'label' => __('Current User Data', 'my-plugin'),
                        'get_value_callback' => [$this, 'get_current_user_data'],
                        'condition' => function() { return is_user_logged_in(); }
                    ]
                ],

                'assets' => [
                    'editor_scripts' => [
                        [
                            'handle' => 'user-profile-editor',
                            'src' => 'blocks/user-profile/editor.js',
                            'deps' => ['wp-blocks', 'wp-element']
                        ]
                    ]
                ],

                'render_callback' => [$this, 'render_user_profile']
            ]
        ]);

        $this->block_registrar->load();
    }

    /**
     * Get current user data for block bindings.
     */
    public function get_current_user_data(array $source_args, $block_instance, string $attribute_name) {
        if (!is_user_logged_in()) {
            return '';
        }

        $user = wp_get_current_user();

        switch ($attribute_name) {
            case 'display_name':
                return $user->display_name;
            case 'email':
                return $user->user_email;
            case 'avatar_url':
                return get_avatar_url($user->ID);
            default:
                return '';
        }
    }
}
```

### Advanced Context-Aware Implementation

```php
$this->block_registrar->add([
    [
        'block_name' => 'my-plugin/post-analytics',
        'title' => 'Post Analytics Block',
        'category' => 'widgets',

        'bindings_sources' => [
            [
                'name' => 'my-plugin/post-stats',
                'label' => __('Post Statistics', 'my-plugin'),
                'get_value_callback' => [$this, 'get_post_statistics'],
                'uses_context' => ['postId', 'postType'],
                'condition' => function() {
                    return current_user_can('edit_posts') && is_single();
                }
            ],
            [
                'name' => 'my-plugin/social-metrics',
                'label' => __('Social Media Metrics', 'my-plugin'),
                'get_value_callback' => [$this, 'get_social_metrics'],
                'uses_context' => ['postId'],
                'hook' => 'wp_loaded',  // Later registration for API availability
                'priority' => 20
            ]
        ],

        'condition' => function() { return is_admin() || is_single(); },
        'assets' => [/* asset definitions */]
    ]
]);
```

## Integration with Existing Features

### Compatibility with Script Modules

Block Bindings and Script Modules work together seamlessly:

```php
[
    'block_name' => 'my-plugin/interactive-content',

    // Server-side data binding
    'bindings_sources' => [
        [
            'name' => 'my-plugin/api-data',
            'get_value_callback' => [$this, 'get_api_data']
        ]
    ],

    // Client-side interactivity
    'assets' => [
        'modules' => [
            [
                'handle' => '@my-plugin/interactive-content',
                'src' => 'blocks/interactive-content/module.js',
                'deps' => ['@wordpress/interactivity'],
                'module_data' => [
                    'apiUrl' => rest_url('my-plugin/v1/'),
                    'nonce' => wp_create_nonce('my-plugin')
                ]
            ]
        ]
    ]
]
```

This provides:

- **Block Bindings**: Server-side dynamic data binding
- **Script Modules**: Modern client-side interactivity
- **Unified Management**: Single configuration for complete block functionality

## Implementation Phases

### Phase 1: Core Integration

- Add `_register_block_bindings_sources()` method to `BlockRegistrar`
- Integrate with existing `add()` method
- Implement basic logging and error handling

### Phase 2: Advanced Features

- Add validation for binding source definitions
- Implement conditional registration patterns
- Add support for custom hook timing

### Phase 3: Documentation and Examples

- Create comprehensive usage examples
- Document integration patterns with Script Modules
- Add troubleshooting guides

## Conclusion

Integrating Block Bindings API support into `BlockRegistrar` provides a unified, powerful interface for managing dynamic block content while maintaining architectural consistency with existing asset management patterns. This enhancement positions the system for modern WordPress block development while preserving the flexibility and performance characteristics that make the current system effective.
