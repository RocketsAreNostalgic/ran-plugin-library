# RegisterOptions

A small, pragmatic options manager that stores all of your plugin settings in a single WordPress `wp_options` row. It provides schema-driven defaults, sanitization/validation, batching, and safe escape hatches for WordPress autoload semantics (including WP 6.6+ nullable autoload heuristics on creation).

## Quick start

```php
use Ran\PluginLib\Config\Config;
use Ran\PluginLib\Options\RegisterOptions;

// Assume you already have a Config instance (e.g., from your plugin bootstrap):
// $config = Config::fromPluginFile( __FILE__ );
// Site scope (default)
$opts = $config->options();

// Set
$opts->set_option('enabled', true);
$opts->set_option('api_key', 'abc123');

// Get (with safe defaults)
$enabled = $opts->get_option('enabled', false);
$apiKey  = $opts->get_option('api_key', '');

// Values-only view (strips metadata)
$values = $opts->get_options();
```

### Construction options

Use the minimal `Config::options()` accessor for the common case (no writes), then configure extras via fluent methods:

```php
$opts = $config->options();
$opts
  ->with_schema(['enabled' => ['default' => true]], seed_defaults: true, flush: true)
  ->with_policy($customPolicy)
  ->with_defaults(['api_timeout' => 30]);
```

You can also construct instances for specific scopes using named factories, or via the public `from_config()` (typed `StorageContext`) for construction-time concerns only:

````php
use Ran\PluginLib\Options\OptionScope;

// Named factories
$siteOpts    = RegisterOptions::site('my_plugin_option');
$networkOpts = RegisterOptions::network('my_plugin_network_option');
$blogOpts    = RegisterOptions::blog('my_plugin_blog_option', 123 /* blog_id */);
$userOpts    = RegisterOptions::user('my_plugin_user_option', 456 /* user_id */, true /* global? */);

// Factory from Config (construction-only args, typed context)
use Ran\PluginLib\Options\Storage\StorageContext;
$opts = RegisterOptions::from_config($config, StorageContext::forSite(), true);

#### Typed StorageContext (recommended)

Prefer the typed `StorageContext` path via `Config::options()` for earlier validation and clearer intent:

```php
use Ran\PluginLib\Options\Storage\StorageContext;

$opts = $config->options(StorageContext::forBlog(123));
````

This approach avoids stringly `storage_args` and ensures consistent scope resolution.

````

### Factory vs. fluents

```php
use Ran\PluginLib\Options\Storage\StorageContext;

// Construction-only via factory; no writes happen here
$opts = $config->options(StorageContext::forSite());

// Configuration and persistence via fluents (20% use-cases)
$opts
  ->with_schema(['feature_flag' => ['default' => false]], seed_defaults: true)
  ->with_policy($customPolicy)
  ->with_defaults(['another_key' => 'val'])
  ->flush();

// from_config() notes
// - from_config() is intended for construction-time concerns (e.g., autoload, basic scope selection via typed StorageContext).
// - Prefer Config::options() + fluents for most cases; it’s a no-write accessor that binds the logger.
// - storage_args are not supported; use StorageContext for scope selection.
```

### Logger binding: DI vs `with_logger()`

`RegisterOptions` supports two ways to bind a logger:

- Constructor/factory DI (earliest binding)

  - Factories accept an optional `Logger` instance:
    - `RegisterOptions::site($option, $autoload = true, ?Logger $logger = null)`
    - `RegisterOptions::network($option, ?Logger $logger = null)`
    - `RegisterOptions::blog($option, $blogId, ?bool $autoloadOnCreate = null, ?Logger $logger = null)`
    - `RegisterOptions::user($option, $userId, $global = false, ?Logger $logger = null)`
  - `from_config()` binds a logger via `ConfigInterface::get_logger()` when available.
  - Benefit: constructor-time reads/logs are captured by your logger.

- Post‑construction fluent: `with_logger(Logger $logger): static`
  - Rebinds the logger on an already constructed instance.
  - Useful for runtime overrides (e.g., temporarily attach a `CollectingLogger` during a diagnostic flow) or when you cannot change the creation site to pass DI.
  - Note: constructor-time logs will not be captured if you rebind later.

Recommended usage:

- Prefer factory/constructor DI for earliest logging and consistent test capture.
- Use `with_logger()` when you need to swap/override the logger mid‑lifecycle or when testing the fluent itself (e.g., chaining semantics).

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
$opts = $config->options();
$opts->with_schema($schema); // seed/defaults/flush are explicit flags
```

Note: Registration seeds defaults in-memory only when `seed_defaults` is true. To persist, call `flush()` explicitly:

```php
$opts->flush(); // single DB write for seeded defaults
```

You can also register schema after construction:

```php
$opts->register_schema([
  'new_feature' => ['default' => false, 'validate' => fn($v) => is_bool($v)],
], seed_defaults: true, flush: true);
```

### Batch updates (recommended for bulk operations)

Stage changes in memory and persist once:

```php
$opts
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
$current = $opts->get_option('complex_map', []);
$patch   = ['level1' => ['added' => 'x']];
$merged  = array_replace_recursive(is_array($current) ? $current : [], $patch);
$opts->set_option('complex_map', $merged);
```

### No implicit writes (cheat sheet)

- set_option/update_option
  - Persists immediately on success (after write-gate policy and filters).
  - Uses the current scope from typed `StorageContext`.
- add_option/add_options
  - Stages changes in memory only; call `flush()` to persist.
- flush(true)
  - Performs a shallow, top-level merge with the current DB snapshot (keeps DB keys, overwrites collisions with in-memory values). Does not deep-merge nested structures.
- flush(false)
  - Replaces the stored row with the in-memory payload (no merge).
- register_schema/with_schema
  - Seeding defaults persists only when you pass `seed_defaults: true` together with a `flush` request (or call `flush()` separately afterward). No implicit writes by default.

### Examples

- Basic usage: `plugin-lib/inc/Options/docs/examples/basic-usage.php`
- Batch + flush: `plugin-lib/inc/Options/docs/examples/batch-and-flush.php`
- Deep merge pattern: `plugin-lib/inc/Options/docs/examples/deep-merge-pattern.php`
- Sanitization & validation: `plugin-lib/inc/Options/docs/examples/sanitize-validate.php`
- Schema defaults at construction: `plugin-lib/inc/Options/docs/examples/schema-defaults.php`
- Flip autoload safely: `plugin-lib/inc/Options/docs/examples/autoload-flip.php`

### API highlights

- `get_option($key, $default)` / `set_option($key, $value)`
- `get_options()` returns values only
- Fluent batching: `add_option($k,$v)->add_option($k2,$v2)->flush()` or `add_options([...])->flush()`
- `flush(bool $merge_from_db = false)` supports optional shallow merge-from-DB
- `register_schema(...)` / `with_schema(...)` for post-construction schema
````
