# TFS-01: Hooks Manager - WordPress Hook Management System

## Status

**ACCEPTED** - 2025-01-15

## Background

The HooksManager provides a comprehensive solution for WordPress hook management that addresses the limitations of traditional hook registration patterns. It offers both declarative (interface-based) and dynamic hook registration capabilities with advanced features like deduplication, conditional registration, and comprehensive debugging.

### Value Proposition

- **Unified Hook Management**: Single system for both static and dynamic hook registration
- **Deduplication & Tracking**: Prevents duplicate registrations and provides full visibility
- **Developer Experience**: Simple trait-based API with powerful debugging capabilities
- **Backward Compatibility**: Seamless integration with existing interface-based patterns
- **Real-World Problem Solving**: Addresses complex patterns found in asset enqueueing and other dynamic scenarios

## Strengths

### ✅ **Comprehensive Architecture**

- Hybrid declarative/dynamic registration support
- Strong type safety with value objects and enums
- Clear separation of concerns across components

### ✅ **Advanced Features**

- Automatic deduplication prevents performance issues
- Conditional registration based on runtime conditions
- Bulk registration and grouping capabilities
- Comprehensive error handling and validation

### ✅ **Developer Experience**

- Simple trait inclusion provides full functionality
- WordPress-specific convenience methods (`register_admin_action`, etc.)
- Built-in debugging and introspection tools
- Excellent documentation and examples

### ✅ **Production Ready**

- Comprehensive test coverage
- Robust error handling with detailed reporting
- Performance optimized with lazy initialization
- Backward compatible with existing patterns

## Weaknesses

### ⚠️ **Complexity**

- Multiple components to understand (Manager, Trait, Registrars, etc.)
- Learning curve for advanced features
- Potential over-engineering for simple use cases

### ⚠️ **Performance Considerations**

- Memory overhead for tracking and statistics
- Reflection usage in validation may impact performance
- Lazy initialization adds slight complexity

### ⚠️ **WordPress Integration**

- Potential race conditions with WordPress enqueue system (see below)
- Dependency on WordPress hook timing
- Need for careful consideration of hook priorities

## Supporting Files Documentation

### **HooksManager.php** - Core Engine

**Purpose**: Main implementation class providing all hook management functionality
**Key Responsibilities**:

- Hybrid registration (declarative + dynamic)
- Deduplication and tracking
- Statistics and debugging
- WordPress integration

### **HooksManagementTrait.php** - Convenience Layer

**Purpose**: Simple trait providing easy access to HooksManager
**Key Responsibilities**:

- Delegation to HooksManager
- WordPress-specific convenience methods
- Common pattern implementations

### **ActionHooksRegistrar.php** - Declarative Action Processor

**Purpose**: Handles registration of declarative action hooks from ActionHooksInterface
**Key Responsibilities**:

- Interface validation and processing
- Hook definition parsing
- WordPress add_action() integration

### **FilterHooksRegistrar.php** - Declarative Filter Processor

**Purpose**: Handles registration of declarative filter hooks from FilterHooksInterface
**Key Responsibilities**:

- Interface validation and processing
- Hook definition parsing
- WordPress add_filter() integration

### **HookDefinition.php** - Type-Safe Configuration

**Purpose**: Value object ensuring type safety and validation
**Key Responsibilities**:

- Immutable hook configuration
- Validation and error checking
- Factory methods for easy creation

### **RegistrationResult.php** - Result Tracking

**Purpose**: Comprehensive result tracking and error reporting
**Key Responsibilities**:

- Success/failure status tracking
- Detailed error reporting
- Statistics and debugging information

## Usage Examples

### Direct HooksManager Usage

```php
class MyPlugin {
    private HooksManager $hooks_manager;

    public function __construct() {
        $this->hooks_manager = new HooksManager($this, new Logger());
    }

    public function init(): void {
        // Initialize declarative hooks from interfaces
        $this->hooks_manager->init_declarative_hooks();

        /**
         * Register dynamic hooks
         *
         * register_action($hook, $callback, $priority = 10, $accepted_args = 1, $context = array())
         * register_filter($hook, $callback, $priority = 10, $accepted_args = 1, $context = array())
         */
        $this->hooks_manager->register_action('wp_init', [$this, 'on_wp_init']);
        $this->hooks_manager->register_filter('the_content', [$this, 'filter_content']);

        /**
         * Conditional registration
         *
         * register_conditional_hooks(array $hook_definitions): array
         */
        $this->hooks_manager->register_conditional_hooks([
            [
                'type' => 'action',
                'hook' => 'admin_init',
                'callback' => [$this, 'admin_setup'],
                'condition' => 'is_admin'
            ]
        ]);

        /**
         *  Bulk registration
         *
         * register_hook_group(string $group_name, array $hook_definitions): bool
         */
        $this->hooks_manager->register_hook_group('assets', [
            [
                'type' => 'action',
                'hook' => 'wp_enqueue_scripts',
                'callback' => [$this, 'enqueue_scripts']
            ],
            [
                'type' => 'action',
                'hook' => 'wp_enqueue_styles',
                'callback' => [$this, 'enqueue_styles']
            ]
        ]);
    }

    // === EXAMPLE HOOK CALLBACKS, MUST BE PUBLIC ===
    public function on_wp_init(): void {
        // WordPress initialization logic
    }

    public function filter_content(string $content): string {
        return $content . '<!-- Modified by MyPlugin -->';
    }

    public function admin_setup(): void {
        // Admin-specific setup
    }
}
```

### HooksManagementTrait Usage

HooksManagementTrait provides a simple way to integrate the HooksManager into any class. It automatically handles both declarative and dynamic hook registration.

```php
class SimpleComponent implements ActionHooksInterface, FilterHooksInterface {
    use HooksManagementTrait;

    public function init(): void {
        $this->_init_hooks(); // Handles both declarative and dynamic
    }

    // === DECLARATIVE HOOKS ===
    public static function declare_action_hooks(): array {
        return [
            'wp_init' => 'on_wp_init',
            'wp_loaded' => ['on_wp_loaded', 5],
        ];
    }

    public static function declare_filter_hooks(): array {
        return [
            'the_content' => 'filter_content',
            'the_title' => ['filter_title', 15],
        ];
    }

    // === DYNAMIC HOOKS ===
    protected function _register_hooks(): void {
        // WordPress-specific patterns
        $this->register_admin_action('admin_init', [$this, 'admin_setup']);
        $this->register_frontend_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

        // Conditional registration
        $this->register_conditional_action(
            'wp_enqueue_scripts',
            [$this, 'enqueue_conditional_assets'],
            function() { return is_user_logged_in(); }
        );

        // Asset-specific patterns
        $this->register_asset_hooks('script', [
            'wp_enqueue_scripts' => 10,
            'admin_enqueue_scripts' => 10
        ]);

        // Bulk registration
        $this->_register_hooks_bulk([
            [
                'type' => 'action',
                'hook' => 'wp_head',
                'callback' => [$this, 'add_meta_tags']
            ],
            [
                'type' => 'filter',
                'hook' => 'body_class',
                'callback' => [$this, 'add_body_classes']
            ]
        ]);
    }

    // === CALLBACK METHODS ===
    public function on_wp_init(): void {
        // WordPress initialization
    }

    public function on_wp_loaded(): void {
        // WordPress loaded
    }

    public function filter_content(string $content): string {
        return $content;
    }

    public function filter_title(string $title): string {
        return $title;
    }

    public function admin_setup(): void {
        // Admin setup
    }

    public function enqueue_assets(): void {
        // Asset enqueueing
    }

    public function enqueue_conditional_assets(): void {
        // Conditional asset enqueueing
    }

    public function add_meta_tags(): void {
        // Meta tags
    }

    public function add_body_classes(array $classes): array {
        return $classes;
    }
}
```

## WordPress Enqueue System Race Conditions

### **Critical Considerations**

When using HooksManager with WordPress enqueue system, several race conditions must be considered:

### **1. Hook Timing Dependencies**

```php
// ❌ RISKY: Hook might fire before dependencies are registered
$this->register_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);

// ✅ SAFER: Use conditional registration with dependency checks
$this->register_conditional_action(
    'wp_enqueue_scripts',
    [$this, 'enqueue_scripts'],
    function() {
        return wp_script_is('jquery', 'registered') &&
               wp_style_is('theme-style', 'registered');
    }
);
```

### **2. Priority Conflicts**

```php
// ❌ RISKY: Default priority might cause conflicts
$this->register_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);

// ✅ SAFER: Explicit priority management
$this->register_action('wp_enqueue_scripts', [$this, 'enqueue_scripts'], 20);
$this->register_action('wp_enqueue_scripts', [$this, 'enqueue_dependencies'], 10);
```

### **3. Conditional Loading Issues**

```php
// ❌ RISKY: Admin/frontend confusion
$this->register_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);

// ✅ SAFER: Explicit context separation
$this->register_frontend_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);
$this->register_admin_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
```

### **4. Dependency Registration Order**

```php
// ❌ RISKY: Dependencies registered after dependents
$this->register_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
$this->register_action('wp_init', [$this, 'register_dependencies']);

// ✅ SAFER: Register dependencies first
$this->register_action('wp_init', [$this, 'register_dependencies'], 5);
$this->register_action('wp_enqueue_scripts', [$this, 'enqueue_scripts'], 20);
```

### **5. Hook Removal Conflicts**

```php
// ❌ RISKY: Removing hooks that might be needed elsewhere
$this->register_action('wp_enqueue_scripts', [$this, 'remove_default_scripts']);

// ✅ SAFER: Conditional removal with checks
$this->register_conditional_action(
    'wp_enqueue_scripts',
    [$this, 'remove_default_scripts'],
    function() {
        return !$this->is_script_needed_elsewhere();
    }
);
```

### **Best Practices for Enqueue Integration**

1. **Use Conditional Registration**: Always check dependencies before enqueueing
2. **Explicit Priorities**: Set specific priorities to control execution order
3. **Context Separation**: Use `register_admin_action()` and `register_frontend_action()`
4. **Dependency Tracking**: Track what's been registered to avoid conflicts
5. **Hook Timing**: Consider WordPress hook execution order carefully

### **HooksManager Advantages for Enqueue**

The HooksManager actually **helps prevent** many race conditions:

```php
// Automatic deduplication prevents double registration
$this->register_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
$this->register_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']); // Prevented

// Comprehensive tracking helps debug timing issues
$stats = $this->get_hook_stats();
$registered_hooks = $this->get_registered_hooks();

// Conditional registration prevents premature execution
$this->register_conditional_action(
    'wp_enqueue_scripts',
    [$this, 'enqueue_scripts'],
    function() { return did_action('wp_init'); }
);
```

## Conclusion

The HooksManager provides a robust, feature-rich solution for WordPress hook management that significantly improves developer experience while maintaining backward compatibility. While complexity exists, the benefits of unified hook management, automatic deduplication, and comprehensive debugging outweigh the learning curve.

The system is particularly valuable for complex WordPress plugins that need to manage multiple hook registration patterns and require robust debugging capabilities.

## Related Documentation

- WordPress Plugin Handbook: [Hooks](https://developer.wordpress.org/plugins/hooks/)
- WordPress Codex: [Plugin API](https://codex.wordpress.org/Plugin_API)
- WordPress Codex: [wp_enqueue_script](https://developer.wordpress.org/reference/functions/wp_enqueue_script/)
