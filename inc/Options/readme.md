# RegisterOptions

A small, pragmatic options manager that stores all of your plugin settings in a single WordPress `wp_options` row. It provides schema-driven defaults, sanitization/validation, batching, and safe escape hatches for WordPress autoload semantics (including WP 6.6+ nullable autoload heuristics on creation).

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
$options = new RegisterOptions(
  'my_plugin_option_name',
  /* initial */ [],
  /* autoload */ true,
  $logger,
  $config,
  /* schema */ [],
  $customPolicy // optional WritePolicyInterface
);
```

### Constructor vs factory parity

- **Parity**: `RegisterOptions::from_config($config, $initial, $autoload, $logger, $schema)` behaves the same as direct construction for logger wiring and schema handling.
  - No implicit writes: construction (either path) seeds in-memory only; call `flush()` to persist.
  - Logger: if `$logger` is null, a logger is resolved from `Config` on first use.
  - Schema: defaults are evaluated/sanitized/validated when used (e.g., when seeding), never auto-persisted.
  - Policy: both constructor and factory accept an optional `WritePolicyInterface`. When omitted, a default policy is applied lazily.
- **Scope and storage args**: The factory supports optional scope/args that influence storage selection without changing API semantics.

  ```php
  $opts = RegisterOptions::from_config($config, [], true, null, $schema, /* scope */ null, /* storage_args */ []);
  ```

Inject a custom (optional) immutable write policy via the factory:

```php
use Ran\PluginLib\Options\Policy\WritePolicyInterface;

$opts = RegisterOptions::from_config(
  $config,
  [],       // initial
  true,     // autoload
  null,     // logger
  [],       // schema
  null,     // scope
  [],       // storage_args
  $customPolicy // WritePolicyInterface or null
);
```

### What this class assumes

- All plugin settings live under one grouped option row (one DB row), keyed by normalized sub-option names.
- Option key normalization uses WordPress `sanitize_key()` when available; otherwise a safe lowercase/underscore fallback.
- External callers who need to normalize keys themselves should use WordPress `sanitize_key()` directly. Internal normalization here exists primarily for testing seams and consistency.
- Stored shape is an associative array: `sub_key => mixed`.
- Autoload behavior for the grouped row is applied only at creation time by WordPress. In WP 6.6+, passing `null` for autoload lets core apply heuristics; updates never change autoload. Manual flipping still requires delete+add.
- Writes replace the entire grouped array (standard WordPress behavior). Use batching and careful merge patterns for nested structures.

### Autoload and performance

- **Autoload capability varies by scope**:
  - Use `supports_autoload()` on `RegisterOptions` to check whether the current storage adapter supports autoload semantics for the grouped option.
  - Implementations may leverage WordPress internals (e.g., `wp_load_alloptions()`) where applicable.
  - On large sites, preloading large option rows can be heavy; prefer targeted reads and cache results at your call site.

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
], seed_defaults: true, flush: true);
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

What “autoload” means: WordPress can preload certain options into memory on every request. Options with autoload=true are loaded on every page load; options with autoload=false are only loaded when you call `get_option()`.

Why you should care:

- Performance: Large auto-loaded rows increase memory usage and slow down every request, frontend and admin.
- Access patterns: Frequently-read, small settings benefit from autoload. Large or rarely used data should not autoload.
- Creation-time rule: WordPress applies the autoload flag when an option row is created. In WP 6.6+, you may pass `null` to defer to core heuristics. Changing the flag later requires delete+add.

When it matters (guidelines):

- Prefer autoload=true for small, frequently-read configuration (feature flags, booleans, small strings).
- Prefer autoload=false for large payloads (reports, caches, analytics, bulk mappings) or rarely used admin-only data.
- Reassess if your grouped options grow beyond ~50–100KB serialized.

How to flip safely (escape hatch):

```php
// Manual autoload flip if needed:
// $current = get_option($option_name);
// delete_option($option_name);
//
// WP 6.6+: prefer bool|null (null defers to heuristics)
// add_option($option_name, $current, '', $newAutoload); // $newAutoload: true|false|null
//
// Pre‑6.6 fallback:
// add_option($option_name, $current, '', $newAutoload ? 'yes' : 'no');
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

- `get_option($key, $default)` / `set_option($key, $value)`
- `get_values()` returns values only; `get_options()` returns values only
- Fluent batching: `add_option($k,$v)->add_option($k2,$v2)->flush()` or `add_options([...])->flush()`
- `flush(bool $merge_from_db = false)` supports optional shallow merge-from-DB
- `register_schema(...)` / `with_schema(...)` for post-construction schema
