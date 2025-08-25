# RegisterOptions

A small, pragmatic options manager that stores all of your plugin settings in a single WordPress `wp_options` row. It provides schema-driven defaults, sanitization/validation, batching, and escape hatches for WordPress autoload semantics.

## Quick start

```php
use Ran\PluginLib\Config\Config;
use Ran\PluginLib\Options\RegisterOptions;

$config  = Config::get_instance();
$options = RegisterOptions::from_config($config, /* initial */ [], /* autoload */ true);

// Set
$options->set_option('enabled', true);
$options->set_option('api_key', 'abc123');

// Get (with safe defaults)
$enabled = $options->get_option('enabled', false);
$apiKey  = $options->get_option('api_key', '');

// Values-only view (strips metadata)
$values = $options->get_values();
```

Alternative constructor:

```php
$options = new RegisterOptions('my_plugin_option_name', /* initial */ [], /* autoload */ true, $logger, $config, /* schema */ []);
```

### What this class assumes

- All plugin settings live under one grouped option row (one DB row), keyed by normalized sub-option names.
- Option key normalization uses WordPress `sanitize_key()` when available; otherwise a safe lowercase/underscore fallback.
- Stored shape is an associative array: `sub_key => { value: mixed, autoload_hint: bool|null }`.
  - `autoload_hint` is metadata only (for audits/migrations); it does NOT affect WordPress autoload.
- Autoload behavior for the grouped row is set at creation time by WordPress. Flipping later requires delete+add.
- Writes replace the entire grouped array (standard WordPress behavior). Use batching and careful merge patterns for nested structures.

### Minimal schema (optional)

Provide default values and data integrity via per-key schema. Defaults can be a callable that receives `ConfigInterface|null`.

```php
$schema = [
  'analytics_enabled' => [
    'default'  => false,
    'validate' => fn($v) => is_bool($v),
  ],
  'api_timeout' => [
    'default'  => fn($cfg) => $cfg && $cfg->is_dev_environment() ? 5 : 30,
    'validate' => fn($v) => is_int($v) && $v > 0 && $v <= 300,
  ],
];
$options = RegisterOptions::from_config($config, [], true, null, $schema);
```

Note: Construction seeds defaults in-memory only. To persist seeded defaults, call `flush()` explicitly:

```php
$options->flush(); // single DB write for seeded defaults
```

You can also register schema after construction:

```php
$options->register_schema([
  'new_feature' => ['default' => false, 'validate' => fn($v) => is_bool($v)],
], seedDefaults: true, flush: true);
```

### Batch updates (recommended for bulk operations)

Stage changes in memory and persist once:

```php
$options
  ->add_options([
    'enabled' => ['value' => true],
    'timeout' => 30,
  ])
  ->flush(); // single DB write
```

### Autoload semantics (WordPress)

What “autoload” means: WordPress can preload certain options into memory on every request. Options with autoload=yes are loaded on every page load; options with autoload=no are only loaded when you call `get_option()`.

Why you should care:

- Performance: Large auto-loaded rows increase memory usage and slow down every request, frontend and admin.
- Access patterns: Frequently-read, small settings benefit from autoload. Large or rarely used data should not autoload.
- Creation-time rule: WordPress applies the autoload flag when an option row is created. Changing the flag later requires delete+add.

When it matters (guidelines):

- Prefer autoload=true for small, frequently-read configuration (feature flags, booleans, small strings).
- Prefer autoload=false for large payloads (reports, caches, analytics, bulk mappings) or rarely used admin-only data.
- Reassess if your grouped options grow beyond ~50–100KB serialized.

How to flip safely (escape hatch):

```php
// Flip the grouped row's autoload flag (data preserved)
$options->set_main_autoload(false); // delete + add under the hood
```

### Merging and nested structures

- Saves write the entire grouped array. If two processes modify different keys concurrently, last writer wins.
- For nested arrays, use a read–modify–write pattern and your chosen merge function, then write back. See example below.

```php
$current = $options->get_option('complex_map', []);
$patch   = ['level1' => ['added' => 'x']];
$merged  = array_replace_recursive(is_array($current) ? $current : [], $patch);
$options->set_option('complex_map', $merged);
```

### Examples

- Basic usage: `plugin-lib/inc/Options/docs/examples/basic-usage.php`
- Batch + flush: `plugin-lib/inc/Options/docs/examples/batch-and-flush.php`
- Deep merge pattern: `plugin-lib/inc/Options/docs/examples/deep-merge-pattern.php`
- Sanitization & validation: `plugin-lib/inc/Options/docs/examples/sanitize-validate.php`
- Schema defaults at construction: `plugin-lib/inc/Options/docs/examples/schema-defaults.php`
- Flip autoload safely: `plugin-lib/inc/Options/docs/examples/autoload-flip.php`

### API highlights

- `get_option($key, $default)` / `set_option($key, $value, ?$hint)`
- `get_values()` returns values only; `get_options()` includes metadata
- Fluent batching: `add_option($k,$v)->add_option($k2,$v2)->flush()` or `add_options([...])->flush()`
- `flush(bool $mergeFromDb = false)` supports optional shallow merge-from-DB
- `register_schema(...)` / `with_schema(...)` for post-construction schema
- `get_autoload_hint($key)` reads stored hints
- `set_main_autoload($bool)` performs a safe autoload flip for the grouped row
