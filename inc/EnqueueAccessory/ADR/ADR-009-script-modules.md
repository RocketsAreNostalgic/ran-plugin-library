# ADR-009: Script Modules Support

**Date:** 2025-01-02
**Status:** Accepted
**Updated:** 2025-01-02

## Context

WordPress 6.5 introduced the Script Modules API, providing native support for ECMAScript modules (ESM) with import/export syntax. WordPress 6.7 further enhanced this with server-to-client data passing capabilities via the `script_module_data_{$module_id}` filter. Our existing asset handling system (`AssetEnqueueBaseTrait` and `ScriptsEnqueueTrait`) lacks support for this modern JavaScript module system.

### Current System Limitations

Our existing script handling provides:

- Traditional script registration via `wp_register_script()` and `wp_enqueue_script()`
- Data passing via `wp_localize_script()` (creates global variables)
- HTML tag modification via `script_loader_tag` filter
- Script metadata via `wp_script_add_data()`

However, it cannot:

- Register or enqueue ES modules using WordPress's native module system
- Leverage import maps for module resolution
- Use the modern `script_module_data_{$module_id}` filter for data passing
- Handle static vs dynamic module dependencies
- Benefit from automatic module preloading

### WordPress Script Modules vs Traditional Scripts

Script modules in WordPress are **completely separate** from traditional scripts:

- **Different Functions**: `wp_register_script_module()` vs `wp_register_script()`
- **Different Data Passing**: `script_module_data_{$module_id}` filter vs `wp_localize_script()`
- **Different Output**: `<script type="module">` with import maps vs traditional `<script>` tags
- **No Cross-Dependencies**: Modules cannot depend on scripts and vice versa
- **Different Capabilities**: Modules lack some script features (custom attributes, inline scripts)

## Decision

Implement a separate `ScriptModulesEnqueueTrait` that provides script module support while maintaining clear separation from traditional script handling.

### Key Design Principles

1. **Separate Interface**: Script modules use their own trait, not an extension of scripts
2. **Consistent API**: Follow the same patterns as `ScriptsEnqueueTrait` where possible
3. **Clear Limitations**: Document what script features are NOT available for modules
4. **Future-Ready**: Design for upcoming WordPress module enhancements

### Implementation Architecture

#### 1. New AssetType Enum Value

```php
enum AssetType: string {
    case Script = 'script';
    case Style = 'style';
    case ScriptModule = 'script_module';  // NEW
}
```

#### 2. ScriptModulesHandler Class Structure

```php
class ScriptModulesHandler extends AssetEnqueueBaseAbstract {
    use ScriptModulesEnqueueTrait;

    public function __construct(ConfigInterface $config) {
        parent::__construct($config);
    }

    public function load(): void {
        // Empty - handler doesn't auto-hook into WordPress
        // Consumer classes manage the enqueue timing
    }
}
```

#### 3. ScriptModulesEnqueueTrait Structure

```php
trait ScriptModulesEnqueueTrait {
    use AssetEnqueueBaseTrait;

    protected function _get_asset_type(): AssetType {
        return AssetType::ScriptModule;
    }

    public function add(array $modules_to_add): self;
    public function stage(): self;
    public function enqueue(): self;
    protected function _process_single_asset(...): string|false;
    protected function _process_module_extras(...): void;
}
```

#### 4. AssetEnqueueBaseTrait Updates

Extend core methods to handle `AssetType::ScriptModule`:

- `_do_register()`: Add branch for `wp_register_script_module()`
- `_do_enqueue()`: Add branch for `wp_enqueue_script_module()`

## Module Definition Format

### Supported Properties

```php
$modules = [
    [
        'handle' => '@my-plugin/component',           // Module identifier
        'src' => 'assets/modules/component.js',
        'deps' => [
            '@wordpress/interactivity',              // Static dependency
            ['id' => '@my-plugin/utils', 'import' => 'dynamic'] // Dynamic dependency
        ],
        'version' => '1.0.0',
        'module_data' => [                           // Data passing (WP 6.7+)
            'apiUrl' => rest_url('my-plugin/v1/'),
            'nonce' => wp_create_nonce('my-plugin'),
            'settings' => get_option('my_plugin_settings', [])
        ],
        'condition' => function() { return !is_admin(); },
        'hook' => 'wp_enqueue_scripts'
    ]
];
```

### Unsupported Properties (Generate Warnings)

- `in_footer`: Modules use import maps, not footer placement
- `attributes`: No `script_module_loader_tag` filter exists yet
- `data`: Use `module_data` instead - `wp_script_add_data()` incompatible
- `inline`: Inline modules not supported by WordPress yet
- `localize`: Use `module_data` instead - `wp_localize_script()` incompatible

## Data Passing Comparison

### Traditional Scripts

```php
// PHP
'localize' => [
    'object_name' => 'myPluginData',        // Required wrapper
    'data' => ['apiUrl' => rest_url()]
]

// JavaScript
console.log(window.myPluginData.apiUrl);   // Global variable access
```

### Script Modules

```php
// PHP
'module_data' => [
    'apiUrl' => rest_url()                  // Direct data structure
]

// JavaScript (WordPress 6.7+)
import { getScriptModuleData } from '@wordpress/script-modules';
const data = getScriptModuleData('@my-plugin/component');
console.log(data.apiUrl);                   // Clean, scoped access
```

## Usage Examples

### Basic Module Registration

```php
class MyPlugin {
    private ScriptModulesHandler $modules_handler;

    public function __construct(ConfigInterface $config) {
        $this->modules_handler = new ScriptModulesHandler($config);
    }

    public function init() {
        $this->modules_handler->add([
            [
                'handle' => '@my-plugin/frontend',
                'src' => 'assets/modules/frontend.js',
                'deps' => ['@wordpress/interactivity'],
                'module_data' => [
                    'apiUrl' => rest_url('my-plugin/v1/'),
                    'nonce' => wp_create_nonce('my-plugin-api')
                ],
                'condition' => function() { return !is_admin(); }
            ]
        ]);

        $this->modules_handler->stage();
        $this->modules_handler->enqueue();
    }
}
```

### Mixed Script and Module Usage

Since traits share the same base trait, use separate handler classes:

```php
class MyPlugin {
    private ScriptsHandler $scripts_handler;
    private ScriptModulesHandler $modules_handler;

    public function __construct(ConfigInterface $config) {
        $this->scripts_handler = new ScriptsHandler($config);
        $this->modules_handler = new ScriptModulesHandler($config);
    }

    public function init() {
        // Legacy scripts with localization
        $this->scripts_handler->add([
            [
                'handle' => 'legacy-script',
                'src' => 'assets/legacy.js',
                'localize' => [
                    'object_name' => 'myPluginData',
                    'data' => ['apiUrl' => rest_url()]
                ]
            ]
        ]);

        // Modern modules with data passing
        $this->modules_handler->add([
            [
                'handle' => '@my-plugin/modern',
                'src' => 'assets/modules/modern.js',
                'deps' => ['@wordpress/interactivity'],
                'module_data' => [
                    'config' => get_option('my_plugin_config', [])
                ]
            ]
        ]);

        // Process both asset types
        $this->scripts_handler->stage();
        $this->scripts_handler->enqueue();

        $this->modules_handler->stage();
        $this->modules_handler->enqueue();
    }
}
```

## Implementation Status

### ✅ Completed

- `ScriptModulesEnqueueTrait` implementation
- `AssetType::ScriptModule` enum value
- Basic module registration and enqueuing
- Module data passing via `script_module_data_{$module_id}` filter
- Validation and warnings for unsupported features
- Comprehensive documentation and examples

### 🚧 In Progress

- `ScriptModulesHandler` class (following `ScriptsHandler` pattern)

### ❌ Current Limitations

- **No Custom Attributes**: WordPress lacks `script_module_loader_tag` filter
- **No Inline Modules**: WordPress doesn't support inline modules yet
- **No Script Interoperability**: Modules and scripts cannot depend on each other

## Workarounds for Current Limitations

### Inline Module Workaround

While WordPress doesn't natively support inline modules, you can achieve similar functionality using the `ScriptsHandler` with `type="module"` attribute:

```php
class MyPlugin {
    private ScriptsHandler $scripts_handler;

    public function __construct(ConfigInterface $config) {
        $this->scripts_handler = new ScriptsHandler($config);
    }

    public function init() {
        // Create an inline module using regular script with type="module"
        $this->scripts_handler->add([
            [
                'handle' => 'my-inline-module',
                'src' => '',  // Empty src for inline-only script
                'attributes' => ['type' => 'module'],
                'inline' => [
                    [
                        'handle' => 'my-inline-module',
                        'content' => '
                            import { getScriptModuleData } from "@wordpress/script-modules";

                            const data = getScriptModuleData("@my-plugin/data");
                            console.log("Module data:", data);

                            // Your module code here
                            export function myModuleFunction() {
                                return "Hello from inline module!";
                            }
                        ',
                        'position' => 'after'
                    ]
                ]
            ]
        ]);

        $this->scripts_handler->stage();
        $this->scripts_handler->enqueue();
    }
}
```

#### Workaround Limitations

**⚠️ Important Constraints:**

1. **No Import Maps Integration**: Inline modules bypass WordPress's import map system
2. **No Module Data Access**: Cannot use `script_module_data_{$module_id}` filter - data must be passed via traditional `wp_localize_script()`
3. **No Module Dependencies**: Cannot declare dependencies on other registered modules
4. **Manual Import Handling**: Must manually handle all imports with full URLs
5. **Limited Browser Support**: Relies on native ES module support without WordPress polyfills
6. **No WordPress Module Integration**: Cannot interact with `@wordpress/*` modules properly

#### When to Use This Workaround

**✅ Suitable for:**

- Simple, self-contained module logic
- Prototyping module functionality
- Legacy code migration where full module support isn't critical
- Small utility functions that don't need complex dependencies

**❌ Avoid for:**

- Production applications requiring robust module support
- Code that needs to interact with WordPress modules
- Complex dependency chains
- Applications requiring broad browser compatibility

#### Better Alternatives

Instead of inline modules, consider:

1. **Separate Module Files**: Create dedicated `.js` files and use proper `ScriptModulesHandler`
2. **Dynamic Imports**: Use `import()` statements in your main modules
3. **Build Tools**: Use bundlers to combine modules at build time
4. **WordPress Module System**: Wait for native inline module support

### 🔮 Future Enhancements (Pending WordPress Core)

- Custom HTML tag attributes when filter becomes available
- Inline module support
- Enhanced debugging and development tools
- Potential script-module interoperability

## Benefits

1. **Modern JavaScript Support**: Native ES modules with import/export syntax
2. **Better Performance**: Automatic module preloading and import maps
3. **Cleaner Data Passing**: No global variable pollution
4. **Future-Ready**: Prepared for WordPress's module-first direction
5. **Consistent API**: Familiar patterns for existing users

## Risks and Mitigations

### Risk: WordPress API Changes

**Mitigation**: Follow WordPress core development closely and maintain compatibility layers

### Risk: Limited Browser Support

**Mitigation**: WordPress handles polyfills and fallbacks automatically

### Risk: Developer Confusion

**Mitigation**: Clear documentation of differences and migration paths

## Alternatives Considered

### Unified Script/Module Interface

**Rejected**: Scripts and modules are fundamentally different in WordPress with no cross-compatibility

### Extending ScriptsEnqueueTrait

**Rejected**: Would create confusion and complexity due to incompatible features

### Waiting for WordPress Maturity

**Rejected**: Early adoption allows us to provide feedback and be ready for widespread adoption

## Conclusion

The `ScriptModulesEnqueueTrait` provides essential support for WordPress's modern Script Modules API while maintaining clear separation from traditional scripts. This positions our asset handling system for the future of WordPress development while acknowledging current limitations.
