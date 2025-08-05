# Feature Implementation: BlockManager/Block Fluent Interface

**Status**: ✅ Implemented (with architectural changes)
**Date**: 2025-01-08
**Deciders**: Development Team
**Technical Story**: Create a fluent interface wrapper around BlockRegistrar to improve developer experience

> **Implementation Note**: The final implementation uses a **BlockFactory/Block** object-oriented approach as originally proposed. BlockFactory serves as the factory/coordinator with a shared instance pattern, while Block objects handle individual block configuration with fluent interfaces.

## Context

The unified BlockRegistrar API, while powerful and comprehensive, presents a significant developer experience challenge. The "huge array" approach with all features in a single configuration can be overwhelming and difficult to manage:

```php
// Current approach - overwhelming configuration
$block_registrar->add([
    'block_name' => 'my-plugin/complete-dynamic-block',
    'render_callback' => [$this, 'render_block'],
    'bindings_sources' => [/* large array */],
    'interactivity' => [/* large array */],
    'assets' => [/* large array */],
    'variations' => [/* large array */],
    'style_variations' => [/* large array */],
    'patterns' => [/* large array */]
    // ... potentially hundreds of lines
]);
```

### Developer Experience Issues

1. **Cognitive Overload**: Single massive array is difficult to read and maintain
2. **Configuration Complexity**: Hard to understand relationships between features
3. **Maintenance Burden**: Changes require navigating large configuration blocks
4. **Learning Curve**: New developers overwhelmed by the full API surface
5. **Code Organization**: No natural way to split configuration across files/methods

## Decision

✅ **Implemented a BlockFactory/Block object-oriented API** that provides:

1. **Incremental Configuration**: Build block definitions step by step via Block objects
2. **Feature-Specific Methods**: Dedicated methods for each currently available feature
3. **Chain-able API**: Fluent interface on Block objects for readable configuration
4. **Block Retrieval**: Access and modify existing block configurations via `block()` method
5. **Backward Compatibility**: Wrapper around existing BlockRegistrar
6. **Object-Oriented Design**: Each block is an independent object with its own configuration
7. **Shared Instance Pattern**: BlockFactory provides singleton access for convenience

## Proposed API Design

### Basic Usage Pattern

```php
// Create manager with optional global configuration
$blocks = new BlockFactory($config);

// Add and configure blocks using chainable methods
$blocks->add_block('my-plugin/complete-block', [
        'render_callback' => [$this, 'render_complete_block']
    ])
    ->bindings('my-plugin/complete-block', [
        'user-profile-source' => [
            'label' => 'User Profile Data',
            'get_value_callback' => [$this, 'get_user_profile_data'],
            'uses_context' => ['postId', 'userId']
        ]
    ])
    ->interactivity('my-plugin/complete-block', [
        'namespace' => 'my-plugin/complete-block',
        'context' => [
            'isExpanded' => false,
            'showDetails' => false
        ]
    ])
    ->assets('my-plugin/complete-block', [
        'scripts' => [
            [
                'handle' => 'my-block-frontend',
                'src' => 'assets/js/frontend.js'
            ]
        ]
    ])
    ->variations('my-plugin/complete-block', [
        'cta-button' => [
            'title' => 'Call to Action Button',
            'attributes' => ['style' => 'cta']
        ]
    ])
    ->register();
```

### Magic Constructor API Interface

````php
class BlockFactory
{
    private static ?self $shared_instance = null;
    private static bool $testing_mode = false;

    private BlockRegistrar $registrar;
    private array $blocks = [];

    public function __construct(array $config = []) {
        // Magic: return shared instance in production, new instance in testing
        if (!self::$testing_mode && self::$shared_instance !== null) {
            // Copy shared state to this instance (singleton behavior)
            $this->blocks = self::$shared_instance->blocks;
            $this->registrar = self::$shared_instance->registrar;
            return;
        }

        // Initialize new instance
        $this->registrar = new BlockRegistrar($config);
        $this->blocks = [];

        // Store as shared instance in production mode
        if (!self::$testing_mode) {
            self::$shared_instance = $this;
        }
    }

    // === TESTING MODE MANAGEMENT ===

    /**
     * Enable testing mode - new instances will be unique
     */
    public static function enableTestingMode(): void {
        self::$testing_mode = true;
        self::$shared_instance = null;  // Clear shared instance
    }

    /**
     * Disable testing mode - return to shared instance behavior
     */
    public static function disableTestingMode(): void {
        self::$testing_mode = false;
        self::$shared_instance = null;  // Clear for clean slate
    }

    /**
     * Check if testing mode is enabled
     */
    public static function isTestingMode(): bool {
        return self::$testing_mode;
    }

    // === BLOCK MANAGEMENT ===

    /**
     * Add a new block with basic configuration
     */
    public function add_block(string $block_name, array $config = []): self {
        $this->blocks[$block_name] = array_merge(['block_name' => $block_name], $config);
        return $this;
    }

    /**
     * Get block configuration array
     */
    public function get_block(string $block_name): array {
        return $this->blocks[$block_name] ?? [];
    }

    /**
     * Check if block exists
     */
    public function has_block(string $block_name): bool {
        return isset($this->blocks[$block_name]);
    }

    /**
     * Remove a block configuration
     */
    public function remove_block(string $block_name): self {
        unset($this->blocks[$block_name]);
        return $this;
    }

    /**
     * Register all configured blocks
     */
    public function register(): self {
        foreach ($this->blocks as $config) {
            $this->registrar->add($config);
        }
        return $this;
    }

    // === FEATURE CONFIGURATION METHODS ===

    /**
     * Configure Block Bindings API sources
     */
    public function bindings(string $block_name, array $bindings_sources): self {
        $this->ensure_block_exists($block_name);
        $this->blocks[$block_name]['bindings_sources'] = $bindings_sources;
        return $this;
    }

    /**
     * Configure Interactivity API
     */
    public function interactivity(string $block_name, array $interactivity_config): self {
        $this->ensure_block_exists($block_name);
        $this->blocks[$block_name]['interactivity'] = $interactivity_config;
        return $this;
    }

    /**
     * Configure assets (scripts, styles, script_modules)
     */
    public function assets(string $block_name, array $assets_config): self {
        $this->ensure_block_exists($block_name);
        $this->blocks[$block_name]['assets'] = $assets_config;
        return $this;
    }

    /**
     * Configure block variations
     */
    public function variations(string $block_name, array $variations_config): self {
        $this->ensure_block_exists($block_name);
        $this->blocks[$block_name]['variations'] = $variations_config;
        return $this;
    }

    /**
     * Configure block style variations
     */
    public function style_variations(string $block_name, array $styles_config): self {
        $this->ensure_block_exists($block_name);
        $this->blocks[$block_name]['style_variations'] = $styles_config;
        return $this;
    }

    /**
     * Configure block patterns
     */
    public function patterns(string $block_name, array $patterns_config): self {
        $this->ensure_block_exists($block_name);
        $this->blocks[$block_name]['patterns'] = $patterns_config;
        return $this;
    }

    /**
     * Set block condition
     */
    public function condition(string $block_name, string $condition): self {
        $this->ensure_block_exists($block_name);
        $this->blocks[$block_name]['condition'] = $condition;
        return $this;
    }

    // === CONVENIENCE METHODS ===

    /**
     * Add a single binding source to existing bindings
     */
    public function add_binding(string $block_name, string $source_name, array $source_config): self {
        $this->ensure_block_exists($block_name);
        $bindings = $this->blocks[$block_name]['bindings_sources'] ?? [];
        $bindings[$source_name] = $source_config;
        $this->blocks[$block_name]['bindings_sources'] = $bindings;
        return $this;
    }

    /**
     * Add a single script asset to existing assets
     */
    public function add_script(string $block_name, array $script_config): self {
        $this->ensure_block_exists($block_name);
        $assets = $this->blocks[$block_name]['assets'] ?? [];
        $scripts = $assets['scripts'] ?? [];
        $scripts[] = $script_config;
        $assets['scripts'] = $scripts;
        $this->blocks[$block_name]['assets'] = $assets;
        return $this;
    }

    /**
     * Add a single style asset to existing assets
     */
    public function add_style(string $block_name, array $style_config): self {
        $this->ensure_block_exists($block_name);
        $assets = $this->blocks[$block_name]['assets'] ?? [];
        $styles = $assets['styles'] ?? [];
        $styles[] = $style_config;
        $assets['styles'] = $styles;
        $this->blocks[$block_name]['assets'] = $assets;
        return $this;
    }

    /**
     * Set interactivity context data
     */
    public function context(string $block_name, array $context_data): self {
        $this->ensure_block_exists($block_name);
        $interactivity = $this->blocks[$block_name]['interactivity'] ?? [];
        $interactivity['context'] = $context_data;
        $this->blocks[$block_name]['interactivity'] = $interactivity;
        return $this;
    }

    /**
     * Set interactivity store configuration
     */
    public function store_config(string $block_name, array $store_config): self {
        $this->ensure_block_exists($block_name);
        $interactivity = $this->blocks[$block_name]['interactivity'] ?? [];
        $interactivity['store_config'] = $store_config;
        $this->blocks[$block_name]['interactivity'] = $interactivity;
        return $this;
    }

    // === INTERNAL METHODS ===

    /**
     * Ensure block exists, throw exception if not
     */
    private function ensure_block_exists(string $block_name): void {
        if (!isset($this->blocks[$block_name])) {
            throw new InvalidArgumentException("Block '{$block_name}' not found. Call add_block() first.");
        }
    }
}
```

## Usage Examples

### Simple Block Registration

```php
// Simple static block - natural new syntax!
$blocks = new BlockFactory();
$blocks->add_block('my-plugin/simple-block', [
        'render_callback' => [$this, 'render_simple_block']
    ])
    ->register();
````

### Complex Interactive Block

```php
// Configure complete block with chained methods - natural new syntax!
$blocks = new BlockFactory();
$blocks->add_block('my-plugin/user-profile', [
        'render_callback' => [$this, 'render_user_profile']
    ])
    ->condition('my-plugin/user-profile', 'is_user_logged_in')
    ->bindings('my-plugin/user-profile', [
        'user-data' => [
            'label' => 'User Profile Data',
            'get_value_callback' => [$this, 'get_user_data']
        ]
    ])
    ->context('my-plugin/user-profile', [
        'isExpanded' => false,
        'showDetails' => false
    ])
    ->store_config('my-plugin/user-profile', [
        'debugMode' => WP_DEBUG
    ])
    ->add_script('my-plugin/user-profile', [
        'handle' => 'user-profile-frontend',
        'src' => 'assets/js/user-profile.js'
    ])
    ->add_style('my-plugin/user-profile', [
        'handle' => 'user-profile-style',
        'src' => 'assets/css/user-profile.css'
    ])
    ->register();
```

### Modular Configuration

````php
class MyPluginBlocks
{
    private BlockFactory $blocks;

    public function __construct() {
        $this->blocks = new BlockFactory();  // Natural syntax!
        $this->setup_blocks();
    }

    private function setup_blocks(): void {
        $this->setup_hero_block();
        $this->setup_cta_block();
        $this->setup_testimonial_block();
    }

    private function setup_hero_block(): void {
        $this->blocks->add_block('my-plugin/hero', [
                'render_callback' => [$this, 'render_hero']
            ])
            ->bindings('my-plugin/hero', $this->get_hero_bindings())
            ->interactivity('my-plugin/hero', $this->get_hero_interactivity())
            ->assets('my-plugin/hero', $this->get_hero_assets())
            ->variations('my-plugin/hero', $this->get_hero_variations());
    }

    private function get_hero_bindings(): array {
        return [
            'hero-content' => [
                'label' => 'Hero Content',
                'get_value_callback' => [$this, 'get_hero_content']
            ]
        ];
    }

    public function register(): void {
        $this->blocks->register();
    }
}

### Cross-Plugin Override Capability

```php
// Plugin A registers a block
$blocks_a = new BlockFactory();
$blocks_a->add_block('plugin-a/hero', [
        'render_callback' => 'plugin_a_render_hero'
    ])
    ->assets('plugin-a/hero', [
        'styles' => [['handle' => 'plugin-a-hero', 'src' => 'hero.css']]
    ])
    ->register();

// Plugin B overrides Plugin A's block (same shared instance due to magic constructor)
$blocks_b = new BlockFactory();  // Actually gets shared instance!
$blocks_b->add_block('plugin-a/hero', [
        'render_callback' => 'plugin_b_enhanced_hero'  // Override!
    ])
    ->interactivity('plugin-a/hero', [  // Add interactivity
        'namespace' => 'plugin-b/enhanced-hero',
        'context' => ['isEnhanced' => true]
    ])
    ->register();
````

### Testing Support

```php
class BlockFactoryTest extends TestCase
{
    protected function setUp(): void {
        BlockFactory::enableTestingMode();
    }

    protected function tearDown(): void {
        BlockFactory::disableTestingMode();
    }

    public function test_block_registration(): void {
        $manager1 = new BlockFactory();  // Natural syntax!
        $manager2 = new BlockFactory();

        // These are different instances in testing mode!
        $this->assertNotSame($manager1, $manager2);

        $manager1->add_block('test/block', [...]);
        $this->assertFalse($manager2->has_block('test/block'));
    }

    public function test_override_behavior(): void {
        $manager = new BlockFactory();  // Natural syntax!

        // Test override behavior in isolation
        $manager->add_block('vendor/block', ['original' => true]);
        $manager->add_block('vendor/block', ['overridden' => true]);

        $config = $manager->get_block('vendor/block');
        $this->assertTrue($config['overridden']);
    }
}

## Benefits

### Developer Experience Improvements

1. **Readable Code**: Fluent interface creates self-documenting code
2. **Incremental Development**: Build block configurations step by step
3. **Feature Discovery**: IDE autocomplete reveals available methods
4. **Modular Organization**: Split configuration across methods/classes
5. **Reduced Cognitive Load**: Focus on one feature at a time

### Maintainability Benefits

1. **Easier Debugging**: Smaller, focused configuration methods
2. **Better Testing**: Test individual feature configurations
3. **Code Reuse**: Share configuration methods between blocks
4. **Version Control**: Cleaner diffs when modifying configurations
5. **Team Collaboration**: Easier to understand and modify

### Backward Compatibility

1. **Wrapper Pattern**: BlockFactory wraps existing BlockRegistrar
2. **No Breaking Changes**: Existing BlockRegistrar usage continues to work
3. **Gradual Migration**: Teams can adopt fluent interface incrementally
4. **Same Power**: All unified features remain available

## Implementation Strategy

### Phase 1: Core Fluent Interface

1. Implement BlockFactory and BlockProxy classes
2. Add basic block management and feature methods
3. Ensure full compatibility with BlockRegistrar
4. Comprehensive test coverage

### Phase 2: Convenience Methods

1. Add convenience methods for common operations
2. Implement helper methods for incremental configuration
3. Add validation and error handling
4. Documentation and examples

### Phase 3: Advanced Features

1. Block templates and presets
2. Configuration validation and linting
3. IDE support and type definitions
4. Migration tools from array-based configuration

## Conclusion

The BlockFactory fluent interface solves the developer experience challenges of the unified BlockRegistrar API while maintaining all its power and flexibility. By providing a chain-able, feature-focused API, developers can build complex block configurations incrementally and maintainably.

This approach transforms the "huge array" problem into an opportunity for better code organization, improved readability, and enhanced developer productivity while preserving the comprehensive functionality of the unified block registration system.
```
