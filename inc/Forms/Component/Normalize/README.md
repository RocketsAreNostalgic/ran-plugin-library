# Component Normalizer Message Integration

This document describes the message integration functionality added to the component normalizer system as part of the component validator integration sprint.

## Overview

The normalizer system has been enhanced to support dual message types:

- **Validation Warnings**: Messages from form submission validation that block persistence
- **Display Notices**: Informational messages about data transformations during display

## Key Features

### 1. Validation Warning Pass-through

The `_add_validation_warnings()` method integrates POST validation warnings into component context:

```php
protected function _add_validation_warnings(array $context): array
```

- Extracts field ID from context
- Looks for `_validation_warnings` in context (passed from form processing layer)
- Merges warnings into structured `warnings` array
- Removes temporary `_validation_warnings` to avoid duplication

### 2. Display Notice Emission

Enhanced sanitization methods now support optional notice emission:

```php
protected function _sanitize_string(mixed $value, string $context = '', ?callable $emitNotice = null): string
protected function _sanitize_boolean(mixed $value, string $context = '', ?callable $emitNotice = null): bool
```

- Accept optional `$emitNotice` callback parameter
- Emit notices when values are transformed during sanitization
- Provide context about what transformations occurred

### 3. Structured Context Preparation

The normalizer now prepares structured message context:

```php
// Context structure after normalization
$context = [
    'warnings' => ['Validation error message'],
    'notices' => ['Value was trimmed during sanitization'],
    // ... other context data
];
```

## Usage Examples

### Basic Message Integration

```php
// Context passed to normalizer
$context = [
    'field_id' => 'user_email',
    '_validation_warnings' => ['Email format is invalid'],
    '_display_notices' => ['Email was converted to lowercase'],
    'value' => 'user@example.com'
];

// After normalization
$normalizedContext = [
    'warnings' => ['Email format is invalid'],
    'notices' => ['Email was converted to lowercase'],
    'value' => 'user@example.com'
];
```

### Sanitization with Notice Emission

```php
$emitNotice = function(string $notice): void {
    // Handle notice (e.g., store for display)
};

$sanitized = $normalizer->_sanitize_string('  trimmed value  ', 'field_name', $emitNotice);
// Result: 'trimmed value'
// Notice emitted: 'String value for field_name was trimmed during sanitization'
```

## Component Template Integration

Component templates have been updated to handle dual message types:

```php
// In component View.php files
$warnings = isset($context['warnings']) && is_array($context['warnings']) ? $context['warnings'] : array();
$notices = isset($context['notices']) && is_array($context['notices']) ? $context['notices'] : array();

// Render warnings with error styling
if (!empty($warnings)) {
    foreach ($warnings as $warning) {
        echo '<p class="form-message form-message--warning">' . esc_html($warning) . '</p>';
    }
}

// Render notices with info styling
if (!empty($notices)) {
    foreach ($notices as $notice) {
        echo '<p class="form-message form-message--notice">' . esc_html($notice) . '</p>';
    }
}
```

## CSS Styling

Form messages use consistent CSS classes:

- `.form-message`: Base message styling
- `.form-message--warning`: Error appearance for validation warnings
- `.form-message--notice`: Info appearance for display notices

See `inc/Forms/assets/form-messages.css` for complete styling definitions.

## Testing

The message integration functionality is tested in:

- `Tests/Unit/Forms/Component/Normalize/NormalizerMessageIntegrationTest.php`: Unit tests for normalizer methods
- `Tests/Unit/Forms/Integration/ComponentValidatorFoundationTest.php`: Integration tests for complete flow

## Future Enhancements

This implementation provides the foundation for:

1. **Component Validator Discovery**: Automatic injection of component-specific validators
2. **Settings Layer Integration**: AdminSettings and UserSettings using shared message handling
3. **Template Architecture**: Enhanced template system with consistent message display
4. **Performance Optimization**: Caching and optimization of message processing

## API Reference

### NormalizerBase Methods

#### `_add_validation_warnings(array $context): array`

Integrates validation warnings from POST submission into component context.

#### `_sanitize_string(mixed $value, string $context = '', ?callable $emitNotice = null): string`

Sanitizes string values with optional notice emission for transformations.

#### `_sanitize_boolean(mixed $value, string $context = '', ?callable $emitNotice = null): bool`

Sanitizes boolean values with optional notice emission for conversions.

### Context Structure

```php
[
    'field_id' => 'field_identifier',            // Required for message lookup
    '_validation_warnings' => ['warning1', ...], // Temporary, removed after processing
    '_display_notices' => ['notice1', ...],      // Temporary, removed after processing
    'warnings' => ['warning1', ...],             // Final warnings array for templates
    'notices' => ['notice1', ...],               // Final notices array for templates
    // ... other context data
]
```
