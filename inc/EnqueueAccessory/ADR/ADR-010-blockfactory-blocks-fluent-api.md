# ADR-010: BlockFactory/Block Fluent API

**Date:** 2025-01-08
**Status:** ✅ Implemented
**Updated:** 2025-01-08

## Context

The BlockRegistrar system (documented in ADR-008) provides powerful block asset management capabilities through a comprehensive array-based configuration API. While functionally complete, the "huge array" approach presents significant developer experience challenges:

### Developer Experience Issues with Array-Based Configuration

```php
// BlockRegistrar approach - functional but overwhelming
$block_registrar->add([
    'block_name' => 'my-plugin/complete-dynamic-block',
    'render_callback' => [$this, 'render_block'],
    'title' => 'Complete Dynamic Block',
    'description' => 'A fully featured dynamic block',
    'category' => 'widgets',
    'icon' => 'admin-post',
    'keywords' => ['dynamic', 'custom'],
    'supports' => ['html' => false, 'align' => true],
    'attributes' => [
        'content' => ['type' => 'string', 'default' => ''],
        'alignment' => ['type' => 'string', 'default' => 'left']
    ],
    'assets' => [
        'frontend_scripts' => [
            ['handle' => 'my-block-frontend', 'src' => 'assets/js/frontend.js']
        ],
        'frontend_styles' => [
            ['handle' => 'my-block-style', 'src' => 'assets/css/style.css']
        ]
    ],
    'condition' => 'is_user_logged_in',
    'hook' => 'wp_footer',
    'preload' => ['scripts' => true, 'styles' => true]
    // ... potentially hundreds more lines for complex blocks
]);
```

**Problems:**

1. **Cognitive Overload**: Single massive array difficult to read and maintain
2. **Configuration Complexity**: Hard to understand relationships between features
3. **Maintenance Burden**: Changes require navigating large configuration blocks
4. **Learning Curve**: New developers overwhelmed by full API surface
5. **Code Organization**: No natural way to split configuration across files/methods
6. **IDE Support**: Limited autocomplete and type checking for array keys

## Decision

Implement a **BlockFactory/Block object-oriented fluent API** that provides:

1. **Incremental Configuration**: Build block definitions step by step via Block objects
2. **Feature-Specific Methods**: Dedicated methods for each available feature
3. **Chainable API**: Fluent interface on Block objects for readable configuration
4. **Block Retrieval**: Access and modify existing block configurations via `block()` method
5. **Backward Compatibility**: Wrapper around existing BlockRegistrar
6. **Object-Oriented Design**: Each block is an independent object with its own configuration
7. **Shared Instance Pattern**: BlockFactory provides singleton access for convenience

## Architecture

### Class Responsibilities

#### BlockFactory

- **Factory/Coordinator**: Creates and manages Block objects
- **Shared Instance**: Provides singleton pattern for cross-plugin coordination
- **Testing Support**: Isolated instances for test environments
- **Block Lifecycle**: Coordinates registration with underlying BlockRegistrar

#### Block

- **Configuration Object**: Encapsulates individual block configuration
- **Fluent Interface**: Chainable methods for incremental configuration
- **WordPress Integration**: Handles registration with WordPress block system
- **Status Tracking**: Provides visibility into registration state

### API Design Pattern

```php
// Modern BlockFactory/Block approach - readable and maintainable
$blocks = new BlockFactory($config); // singleton instance

// Create and configure blocks with fluent interface
$hero_block = $blocks->block('my-plugin/hero-block')
    ->title('Hero Block')
    ->description('A customizable hero section')
    ->category('layout')
    ->icon('admin-post')
    ->render_callback([$this, 'render_hero_block'])
    ->add_script([
        'handle' => 'hero-frontend',
        'src' => 'assets/js/hero.js',
        'deps' => ['jquery']
    ])
    ->add_style([
        'handle' => 'hero-style',
        'src' => 'assets/css/hero.css'
    ])
    ->condition('is_front_page')
    ->preload(['scripts' => true])
    ->register();

// Retrieve and modify existing blocks
$existing_block = $blocks->block('my-plugin/hero-block')
    ->add_style([
        'handle' => 'hero-responsive',
        'src' => 'assets/css/hero-responsive.css'
    ])
    ->register();
```

## Implementation Details

### BlockFactory Class

```php
class BlockFactory {
    private static ?self $shared_instance = null;
    private static bool $testing_mode = false;

    private BlockRegistrar $registrar;
    private array $blocks = [];

    /**
     * Magic constructor - returns shared instance in production,
     * unique instance in testing mode
     */
    public function __construct(ConfigInterface $config = null) {
        // Implementation handles shared instance pattern
    }

    /**
     * Get or create a Block object for configuration
     */
    public function block(string $block_name): Block {
        if (!isset($this->blocks[$block_name])) {
            $this->blocks[$block_name] = new Block($block_name, $this);
        }
        return $this->blocks[$block_name];
    }

    /**
     * Register a block configuration with BlockRegistrar
     */
    public function register_block(string $block_name, array $config): mixed {
        return $this->registrar->add($config);
    }

    // Additional factory methods...
}
```

### Block Class

```php
class Block {
    private string $block_name;
    private BlockFactory $factory;
    private array $config = [];

    public function __construct(string $block_name, BlockFactory $factory = null) {
        $this->block_name = $block_name;
        $this->factory = $factory ?? BlockFactory::getShared();
        $this->config['block_name'] = $block_name;
    }

    /**
     * Set block title
     */
    public function title(string $title): self {
        $this->config['title'] = $title;
        return $this;
    }

    /**
     * Set render callback
     */
    public function render_callback(callable $callback): self {
        $this->config['render_callback'] = $callback;
        return $this;
    }

    /**
     * Add script asset
     */
    public function add_script(array $script_config): self {
        $this->config['assets']['frontend_scripts'][] = $script_config;
        return $this;
    }

    /**
     * Add style asset
     */
    public function add_style(array $style_config): self {
        $this->config['assets']['frontend_styles'][] = $style_config;
        return $this;
    }

    /**
     * Set block condition
     */
    public function condition(string|callable $condition): self {
        $this->config['condition'] = $condition;
        return $this;
    }

    /**
     * Configure preloading
     */
    public function preload(array $preload_config): self {
        $this->config['preload'] = $preload_config;
        return $this;
    }

    /**
     * Register block with WordPress
     */
    public function register(): array {
        return $this->factory->register_block($this->block_name, $this->config);
    }

    // Additional fluent methods for all BlockRegistrar features...
}
```

## Usage Examples

### Basic Block Registration

```php
$blocks = new BlockFactory($config);

$blocks->block('my-plugin/simple-block')
    ->title('Simple Block')
    ->render_callback([$this, 'render_simple'])
    ->register();
```

### Complex Block with Assets

```php
$blocks->block('my-plugin/complex-block')
    ->title('Complex Interactive Block')
    ->description('A feature-rich block with multiple assets')
    ->category('widgets')
    ->icon('admin-tools')
    ->render_callback([$this, 'render_complex'])
    ->add_script([
        'handle' => 'complex-frontend',
        'src' => 'assets/js/complex.js',
        'deps' => ['jquery', 'wp-api']
    ])
    ->add_script([
        'handle' => 'complex-admin',
        'src' => 'assets/js/admin.js',
        'context' => 'admin'
    ])
    ->add_style([
        'handle' => 'complex-style',
        'src' => 'assets/css/complex.css'
    ])
    ->condition(function() {
        return current_user_can('edit_posts');
    })
    ->hook('wp_footer')
    ->preload(['scripts' => true, 'styles' => true])
    ->register();
```

### Block Modification and Retrieval

```php
// Retrieve existing block for modification
$hero = $blocks->block('my-plugin/hero-block');

// Add additional assets
$hero->add_style([
    'handle' => 'hero-mobile',
    'src' => 'assets/css/hero-mobile.css',
    'media' => '(max-width: 768px)'
])->register();

// Check registration status
$status = $hero->get_status();
if ($status['registered']) {
    // Block successfully registered
}
```

## Benefits

### Developer Experience Improvements

1. **Readable Configuration**: Fluent interface makes block setup self-documenting
2. **Incremental Building**: Add features step-by-step rather than all-at-once
3. **IDE Support**: Better autocomplete and type checking with object methods
4. **Code Organization**: Natural separation of concerns across methods
5. **Maintainability**: Easier to modify and extend existing blocks
6. **Learning Curve**: Gradual feature discovery vs overwhelming array

### Technical Benefits

1. **Backward Compatibility**: Built on top of existing BlockRegistrar
2. **Performance**: No overhead - compiles to same BlockRegistrar calls
3. **Testing**: Isolated instances for unit testing
4. **Extensibility**: Easy to add new fluent methods for future features
5. **Cross-Plugin Coordination**: Shared instance enables block overrides
6. **Status Tracking**: Rich feedback about registration state

## Integration with BlockRegistrar

The BlockFactory/Block API is a **wrapper** around BlockRegistrar, not a replacement:

```php
// These are equivalent:

// Direct BlockRegistrar approach
$block_registrar->add([
    'block_name' => 'my-plugin/test',
    'title' => 'Test Block',
    'render_callback' => 'render_test',
    'assets' => [
        'frontend_scripts' => [
            ['handle' => 'test-js', 'src' => 'test.js']
        ]
    ]
]);

// BlockFactory/Block fluent approach
$blocks->block('my-plugin/test')
    ->title('Test Block')
    ->render_callback('render_test')
    ->add_script(['handle' => 'test-js', 'src' => 'test.js'])
    ->register();
```

Both approaches:

- Use the same underlying BlockRegistrar logic
- Provide identical WordPress integration
- Support all ADR-008 features (conditional loading, preloading, etc.)
- Have the same performance characteristics

## Future Extensibility

The fluent API design enables easy addition of new features:

```php
// Future features can be added as new fluent methods
$blocks->block('my-plugin/future-block')
    ->title('Future Block')
    ->bindings(['user-profile' => $binding_config])      // Future: Block Bindings API
    ->interactivity(['namespace' => 'my-plugin'])        // Future: Interactivity API
    ->variations(['cta' => $variation_config])           // Future: Block Variations
    ->patterns(['hero-pattern' => $pattern_config])      // Future: Block Patterns
    ->register();
```

## Testing Strategy

The BlockFactory supports both production and testing modes:

```php
// Production: shared instance
$blocks = new BlockFactory($config);

// Testing: isolated instance
BlockFactory::enableTestingMode();
$blocks = new BlockFactory($config); // Returns unique instance
```

This enables:

- **Unit Testing**: Isolated block configurations per test
- **Integration Testing**: Cross-plugin block override testing
- **Mocking**: Easy to mock BlockFactory for component tests

## Trade-offs

### Advantages

- **Developer Experience**: Significantly improved readability and maintainability
- **IDE Support**: Better autocomplete and error detection
- **Code Organization**: Natural separation of block configuration
- **Backward Compatibility**: No breaking changes to existing BlockRegistrar usage
- **Performance**: Zero overhead - compiles to identical BlockRegistrar calls

### Considerations

- **Learning Curve**: Developers need to learn new fluent API alongside array approach
- **Code Volume**: Slightly more verbose for very simple blocks
- **Abstraction Layer**: Additional layer between developer and BlockRegistrar

## Conclusion

The BlockFactory/Block fluent API successfully addresses the developer experience challenges of the array-based BlockRegistrar configuration while maintaining full backward compatibility and performance. It provides a modern, object-oriented interface that makes block registration more readable, maintainable, and discoverable without sacrificing any of the powerful features documented in ADR-008.

The implementation demonstrates that improved developer experience and technical excellence can coexist, providing both immediate usability benefits and a foundation for future feature expansion.
