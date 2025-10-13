# Forms Subsystem Overview

This directory contains the building blocks used to render reusable form components in WordPress admin settings. The goal is to keep component creation simple:

1. Provide a **component template** that returns structured metadata
2. Provide a **component normalizer** that normalizes and prepares incoming component data before it is passed to the template.
3. Expose a colocated **fluent builder** for ergonomic configuration.

The Forms dispatcher and registry discover templates automatically via `ComponentLoader`, so no manual wiring is required.

## Templates (`inc/Forms/Components/<category>/<component-name>/view.php`)

Every template is a PHP file that returns an associative array with the following keys:

```php
return [
    'markup' => (string) $html,
    /** @var ScriptDefinition|array|null */
    'script' => $scriptDefinitionOrArrayOrNull,
    /** @var StyleDefinition|array|null */
    'style' => $styleDefinitionOrArrayOrNull,
    'requires_media' => (bool) $needsMediaLibraryAssets,
    'context_schema' => [
        'required' => ['context key', /* ... */],
        'optional' => ['context key', /* ... */],
        'defaults' => ['context key' => $defaultValue, /* ... */],
    ],
];
```

## Normalizers (`inc/Forms/Components/<category>/<component-name>/normalizer.php`)

Normalizers prepare incoming component data before rendering. They receive the raw component context and a shared `ComponentNormalizationContext` instance, then return both the rendered payload (including `markup`, optional assets, and `context_schema`) and any warnings emitted during normalization.

```php
return [
    'payload' => [
        'markup'         => (string) $html,
        'script'         => $scriptDefinitionOrNull,
        'style'          => $styleDefinitionOrNull,
        'requires_media' => (bool) $needsMediaLibraryAssets,
        'context_schema' => [
            'required' => ['context key', /* ... */],
            'optional' => ['context key', /* ... */],
            'defaults' => ['context key' => $defaultValue, /* ... */],
        ],
    ],
    'warnings' => ['warning message', /* ... */],
];
```

## Fluent Field Builders (`inc/Forms/Components/<category>/<component-name>/builder.php`)

Example usage:

```php
use Ran\PluginLib\Forms\Components\<category>\<component-name>\Builder;

$field = Builder::checkbox('newsletter_opt_in', 'Subscribe to updates')
    ->description('Send me product news')
    ->defaultChecked(true)
    ->to_array();
```

Builders populate the `component_context` array according to the template's `context_schema`. Consumers can also construct that array manually if the fluent API is not needed.

- **`markup`**: rendered HTML for the component.
- **`script`/`style`**: optional `ScriptDefinition`/`StyleDefinition` instances when the component needs assets.
- **`requires_media`**: `true` if WordPress media picker assets should be enqueued.
- **`context_schema`**: documents what keys the component expects in its context. The dispatcher can use this metadata to validate inputs and tooling can present documentation automatically.

## Registry and Dispatcher

- `FormComponentRegistry` auto-registers every template alias exposed by `ComponentLoader::aliases()`.
- `ComponentDispatcher` executes the registered factory, constructs a `ComponentRenderResult`, aggregates assets via `FormAssets`, and returns the markup string.

Because templates publish `context_schema`, the dispatcher can provide this metadata to callers or log warnings if required keys are missing.
