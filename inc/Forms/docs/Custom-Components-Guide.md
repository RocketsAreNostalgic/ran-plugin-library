# Custom Components Guide

This guide explains how to create and register custom form components in the ran/plugin-lib Forms system.

## Overview

The component system supports two approaches:

1. **Built-in components**: Auto-discovered from the library's `inc/Forms/Components/` directory
2. **Plugin components**: Registered externally from your own plugin (recommended for custom components)

> **Important**: Custom components should be created in your own plugin, not in the library's vendor directory. The library's `Components/` directory is for built-in components only.

## Component Structure

Each component can have up to 5 files:

| File | Purpose | Required? |
|------|---------|----------|
| `View.php` | Template that returns `ComponentRenderResult` | Required |
| `Validator.php` | Validates submitted values | Required for components that submit data |
| `Sanitizer.php` | Sanitizes submitted values before storage | Strongly recommended for components that submit data |
| `Normalizer.php` | Transforms raw context into normalized context | Recommended (best practice) |
| `Builder.php` | Fluent API for constructing field definitions | Optional (recommended for DX) |

Notes:

- A component is treated as an input/data-submitting component when it has a `Validator`.
- Normalizers and sanitizers are not strictly required. You can do all logic in the View, but best practice is to keep Views thin and isolate normalization/sanitization/validation in their respective classes.

## How Built-in Auto-Discovery Works

The library's `ComponentLoader` scans `inc/Forms/Components/` and generates aliases from the folder structure:

```text
Fields/Text/View.php       →  alias: "fields.text"
Fields/Checkbox/View.php   →  alias: "fields.checkbox"
Elements/Button/View.php   →  alias: "elements.button"
```

`ComponentManifest` then resolves companion classes by convention. For alias `fields.text`, it looks for:

```text
Ran\PluginLib\Forms\Components\Fields\Text\Normalizer
Ran\PluginLib\Forms\Components\Fields\Text\Builder
Ran\PluginLib\Forms\Components\Fields\Text\Validator
Ran\PluginLib\Forms\Components\Fields\Text\Sanitizer
```

## Creating a Custom Component in Your Plugin

### Step 1: Create the Directory Structure

Create a directory in your plugin for custom components:

```text
my-plugin/
├── inc/
│   └── Components/
│       └── ColorPicker/
│           ├── View.php
│           ├── Normalizer.php   (recommended)
│           ├── Builder.php      (optional)
│           ├── Validator.php    (required for input components)
│           └── Sanitizer.php    (recommended for input components)
└── my-plugin.php
```

### Step 2: Create View.php (Required)

The View is the only required file. It must return a `ComponentRenderResult`:

```php
<?php
/**
 * MyCustom component template.
 *
 * @var array{
 *     input_attributes: string,
 *     my_option?: string
 * } $context
 */

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

$inputAttributes = isset($context['input_attributes']) ? trim((string) $context['input_attributes']) : '';

ob_start();
?>
<input<?php echo $inputAttributes !== '' ? ' ' . $inputAttributes : ''; ?>>
<?php

return new ComponentRenderResult(
    markup: (string) ob_get_clean(),
    script: null,                    // Optional inline JS
    style: null,                     // Optional inline CSS
    requires_media: false,           // True if needs WP media library
    repeatable: true,                // True if supports repeater fields
    context_schema: array(
        'required' => array('input_attributes'),
        'optional' => array('my_option', 'repeatable'),
        'defaults' => array(),
    )
);
```

### Step 3: Create Normalizer.php (Optional)

The Normalizer transforms raw context data into the normalized format expected by the View.

This is optional, but recommended to keep your `View.php` focused on rendering only:

```php
<?php
declare(strict_types=1);

namespace MyPlugin\Components\ColorPicker;

use Ran\PluginLib\Forms\Component\Normalize\NormalizerInputBase;

final class Normalizer extends NormalizerInputBase {
    /**
     * Normalize component-specific context.
     *
     * @param array<string,mixed> $context Component context.
     * @return array<string,mixed> Normalized context.
     */
    protected function _normalize_component_specific(array $context): array {
        // Set the input type
        $context['input_type'] = 'text';
        $context['attributes']['type'] = 'text';

        // Handle custom options
        if (isset($context['my_option'])) {
            $context['attributes']['data-my-option'] = $this->_sanitize_string(
                $context['my_option'],
                'my_option'
            );
        }

        // Use the complete input normalization pipeline
        return $this->_complete_input_normalization($context, 'my-custom');
    }
}
```

### Step 4: Create Builder.php (Optional)

The Builder provides a fluent API for constructing field definitions:

```php
<?php
declare(strict_types=1);

namespace MyPlugin\Components\ColorPicker;

use Ran\PluginLib\Forms\Component\Build\ComponentBuilderInputBase;

final class Builder extends ComponentBuilderInputBase {
    protected ?string $myOption = null;

    /**
     * Set the custom option value.
     *
     * @param string|null $value
     * @return static
     */
    public function my_option(?string $value): static {
        $this->myOption = $value;
        return $this;
    }

    /**
     * Get the custom option value.
     *
     * @return string|null
     */
    public function get_my_option(): ?string {
        return $this->myOption;
    }

    /**
     * Build the component context.
     *
     * @return array<string,mixed>
     */
    protected function _build_component_context(): array {
        $context = $this->_build_input_context();

        if ($this->myOption !== null) {
            $context['my_option'] = $this->myOption;
        }

        return $context;
    }

    /**
     * Get the component identifier.
     *
     * @return string
     */
    protected function _get_component(): string {
        return 'my-custom';
    }
}
```

### Step 5: Create Validator.php (Optional)

The Validator validates submitted values before they are saved.

If your component submits data, you should treat a `Validator` as required. Validator presence is used by the Forms system to determine if a component participates in the validation pipeline:

```php
<?php
declare(strict_types=1);

namespace MyPlugin\Components\ColorPicker;

use Ran\PluginLib\Forms\Component\Validate\ValidatorBase;

final class Validator extends ValidatorBase {
    /**
     * Validate the component value.
     *
     * @param mixed $value The submitted value.
     * @param array<string,mixed> $context The field context.
     * @param callable $emitWarning Callback to emit validation warnings.
     * @return bool True if valid, false otherwise.
     */
    protected function _validate_component(mixed $value, array $context, callable $emitWarning): bool {
        // Check type
        if (!is_string($value)) {
            $emitWarning('Value must be a string.');
            return false;
        }

        // Custom validation logic
        if (strlen($value) < 3) {
            $emitWarning('Value must be at least 3 characters.');
            return false;
        }

        return true;
    }
}
```

### Step 6: Create Sanitizer.php (Optional)

The Sanitizer cleans submitted values before storage.

This is not strictly required, but is best practice for any component that submits data:

```php
<?php
declare(strict_types=1);

namespace MyPlugin\Components\ColorPicker;

use Ran\PluginLib\Forms\Component\Sanitize\SanitizerBase;

final class Sanitizer extends SanitizerBase {
    /**
     * Sanitize the component value.
     *
     * @param mixed $value The submitted value.
     * @param array<string,mixed> $context The field context.
     * @param callable $emitNotice Callback to emit sanitization notices.
     * @return mixed The sanitized value.
     */
    protected function _sanitize_component(mixed $value, array $context, callable $emitNotice): mixed {
        if (!is_string($value)) {
            $emitNotice('Value was converted to string.');
            return '';
        }

        // Apply WordPress sanitization
        return sanitize_text_field($value);
    }
}
```

### Step 7: Register Your Component

After creating your component files, you need to register them with the Forms system. This is typically done during plugin initialization.

#### Option A: Register Component Directory (Recommended)

Use the Forms registration API (`register_component()` / `register_components()`) so the system can derive the component namespace. This enables auto-discovery of companion classes (`Normalizer`, `Builder`, `Validator`, `Sanitizer`) for external components.

```php
<?php
// In your plugin's initialization code

// $settings can be AdminSettings, UserSettings, or any FormsInterface implementation.

// Register a single component directory
$settings->register_component('color-picker', array(
    'path'   => 'inc/Components/ColorPicker',
    'prefix' => 'my-plugin',
));

// Or register all component directories under a folder (each must contain View.php)
$settings->register_components(array(
    'path'   => 'inc/Components',
    'prefix' => 'my-plugin',
));
```

This will register your view at `my-plugin.color-picker` and also allow the system to discover:

```text
MyPlugin\Components\ColorPicker\Normalizer
MyPlugin\Components\ColorPicker\Builder
MyPlugin\Components\ColorPicker\Validator
MyPlugin\Components\ColorPicker\Sanitizer
```

#### Option B: Register Factory Function (Simple Components)

For simple components without separate files, register a factory directly:

```php
<?php
use Ran\PluginLib\Forms\Component\ComponentRenderResult;

$manifest->register('my-plugin.simple-display', function(array $context): ComponentRenderResult {
    $content = esc_html($context['content'] ?? '');

    return new ComponentRenderResult(
        markup: "<div class=\"my-plugin-display\">{$content}</div>"
    );
});
```

#### Option C: Full Registration with Validator/Sanitizer

For input components that need validation and sanitization, embed the logic directly in the factory:

```php
<?php
use Ran\PluginLib\Forms\Component\ComponentRenderResult;

// Register a factory that handles its own validation/sanitization context
$manifest->register('my-plugin.color-picker', function(array $context): ComponentRenderResult {
    // Your component can access validation state from context
    $value = $context['value'] ?? '';
    $warnings = $context['warnings'] ?? [];

    ob_start();
    ?>
    <input type="color"
           name="<?php echo esc_attr($context['name'] ?? ''); ?>"
           value="<?php echo esc_attr($value); ?>">
    <?php if (!empty($warnings)): ?>
        <p class="form-message form-message--warning">
            <?php echo esc_html(implode(', ', $warnings)); ?>
        </p>
    <?php endif; ?>
    <?php

    return new ComponentRenderResult(
        markup: (string) ob_get_clean()
    );
});
```

> **Note**: If you register external components using `register_component()` / `register_components()`, the system can auto-discover companion classes (Validator/Sanitizer/Normalizer/Builder) for those external components. If you register only a template path, only the View is available.

### Alias Naming Convention

Use a namespaced alias format to avoid conflicts with built-in components:

```text
your-plugin.component-name     ✓ Good
my-theme.custom-field          ✓ Good
fields.my-custom               ✗ Avoid (may conflict with built-in)
```

## Base Class Hierarchy

### Normalizers

| Class | Description |
|-------|-------------|
| `NormalizerBase` | Base class for all normalizers |
| `NormalizerInputBase` | Extends base with input field helpers (`_complete_input_normalization()`) |
| `NormalizerTextBase` | Extends input with text-specific helpers |

### Builders

| Class | Description |
|-------|-------------|
| `ComponentBuilderBase` | Base with `id`, `label`, `order`, `to_array()` |
| `ComponentBuilderInputBase` | Adds `name`, `value`, `required`, `disabled`, etc. |
| `ComponentBuilderTextBase` | Adds `placeholder`, `autocomplete`, `minlength`, `maxlength`, `pattern` |

### Validators & Sanitizers

| Class | Description |
|-------|-------------|
| `ValidatorBase` | Base validator with logging integration |
| `SanitizerBase` | Base sanitizer with logging integration |

## Example: Built-in Text Field Component

Here's the structure of the library's built-in Text component for reference:

```text
ran/plugin-lib/inc/Forms/Components/Fields/Text/
├── View.php        # Returns ComponentRenderResult with <input> markup
├── Normalizer.php  # Extends NormalizerInputBase, handles text attributes
├── Builder.php     # Extends ComponentBuilderTextBase, adds size()
└── Sanitizer.php   # Extends Input\Sanitizer for text sanitization
```

## Checklist

When creating a custom component in your plugin:

- [ ] Create directory in your plugin (e.g., `my-plugin/inc/Components/MyComponent/`)
- [ ] Add `View.php` returning `ComponentRenderResult`
- [ ] Define `context_schema` with required/optional/defaults
- [ ] Add `Normalizer.php` if context transformation is needed (recommended)
- [ ] Add `Builder.php` if fluent API is desired (optional)
- [ ] Add `Validator.php` if the component submits data
- [ ] Add `Sanitizer.php` if the component submits data (recommended)
- [ ] Use your plugin's namespace (e.g., `MyPlugin\Components\MyComponent`)
- [ ] Register the component with `register_component()` / `register_components()`
- [ ] Use namespaced alias (e.g., `my-plugin.my-component`) to avoid conflicts

## Related Documentation

- `inc/Forms/Component/Normalize/README.md` - Normalizer message integration
- `inc/Forms/Components/Fields/README.md` - Field component usage examples
