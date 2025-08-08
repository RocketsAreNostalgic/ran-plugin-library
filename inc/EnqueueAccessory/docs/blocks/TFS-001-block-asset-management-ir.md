# Block Asset Management Architecture - Implementation Record

## Document Information

- **Status**: Implemented
- **Implementation Date**: 2025-01-08
- **Version**: 1.0
- **Contributors**: Development Team

## 1. Context & Decisions

- **Problem Solved**: WordPress blocks required different asset management approach with early registration, conditional loading, and WordPress integration
- **Key Decisions**: Extended existing asset system with block-aware functionality and flattened API design
- **Alternatives Considered**: Separate block system was rejected in favor of extending proven asset infrastructure

## 2. Implementation Summary

- **Final Architecture**: BlockRegistrar extends AssetEnqueueBaseAbstract with block-specific features
- **Integration Patterns**: Composes ScriptsHandler and StylesHandler for unified asset management

## 3. Code Structure

- **Core Classes**: BlockRegistrar, BlockAssetTrait, ScriptsHandler, StylesHandler
- **File Organization**: `inc/EnqueueAccessory/` directory
- **Namespace Structure**: `Ran\PluginLib\EnqueueAccessory`

## 4. Integration Points

- **WordPress Integration**: Integrates with `register_block_type()` and WordPress block lifecycle
- **System Integration**: Composes existing asset handlers for unified management
- **API Usage**: Developers use `add()` method with flattened configuration

## 5. Configuration & Usage

- **Setup Requirements**: No additional configuration required
- **Usage Examples**:

  ```php
  $block_registrar->add([
      'block_name' => 'my-plugin/hero',
      'render_callback' => [$this, 'render_hero'],
      'assets' => [
          'editor_scripts' => [
              ['handle' => 'hero-editor', 'src' => 'blocks/hero/editor.js']
          ],
          'frontend_scripts' => [
              ['handle' => 'hero-frontend', 'src' => 'blocks/hero/frontend.js']
          ]
      ]
  ]);
  ```

- **Best Practices**: Use WordPress built-in asset types for standard contexts, custom conditions for specialized scenarios

## 6. Testing & Quality

- **Test Coverage**: Comprehensive tests for block registration and asset management
- **Performance Results**: Conditional loading improves performance, preloading enhances Core Web Vitals
- **Known Issues**: Block presence detection requires content parsing

## 7. Lessons Learned

- **What Worked Well**: Flattened API provides natural WordPress-like interface
- **Challenges**: Block presence detection complexity and WordPress timing requirements
- **Future Improvements**: Block asset dependencies and bundling could be added

## 8. Maintenance Notes

- **Common Issues**: Block presence detection timing and WordPress integration complexity
- **Troubleshooting**: Check block registration timing and asset loading conditions

## 9. Additional Concerns

### WordPress vs BlockRegistrar Capabilities

**WordPress Built-in:**

- `editorScript` - Editor only
- `script` - Both contexts
- `viewScript` - Frontend when block present (WP 5.9+)
- `viewStyle` - Frontend when block present (WP 6.1+)

**BlockRegistrar Adds:**

- Block-level conditions
- Custom asset conditions
- Dynamic asset loading
- Environment-aware loading
- Preloading logic
- Unified asset management

### Flattened API Design

```php
$block_registrar->add([
    'block_name' => 'my-plugin/hero',
    'title' => 'Hero Block',
    'category' => 'design',
    'render_callback' => 'render_hero',
    'assets' => [...],
    'condition' => function() { return !is_admin(); },
    'preload' => true
]);
```

### Block Asset Preloading

```php
$block_registrar->add([
    'block_name' => 'my-plugin/hero',
    'preload' => function() { return is_front_page(); },
    'assets' => [
        'scripts' => [['handle' => 'hero-script', 'src' => 'hero.js']]
    ]
]);
```

### WP_Block_Type Collection

```php
// Access registered block objects
$all_blocks = $block_registrar->get_registered_block_types();
$hero_block = $block_registrar->get_registered_block_type('my-plugin/hero');
```

### Environment-Aware Assets

```php
$block_registrar->add([
    'block_name' => 'my-plugin/hero',
    'assets' => [
        'frontend_scripts' => [
            [
                'handle' => 'hero-script',
                'src' => [
                    'dev' => 'assets/src/blocks/hero/script.js',
                    'prod' => 'assets/dist/blocks/hero/script.min.js'
                ]
            ]
        ]
    ]
]);
```
