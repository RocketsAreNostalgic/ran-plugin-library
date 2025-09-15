# RegisterOptions

A pragmatic options manager that stores plugin/theme settings in a single WordPress `wp_options` row. It provides schema-driven defaults, sanitization/validation, batching, and safe escape hatches for WordPress autoload semantics (including WP 6.6+ nullable autoload heuristics on creation).

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
  ->with_schema(['enabled' => ['default' => true, 'validate' => fn($v) => is_bool($v)]])
  ->with_policy($customPolicy)
  ->with_defaults(['api_timeout' => 30]);
```

You can also construct instances for specific scopes using named factories, or via the public `from_config()` (typed `StorageContext`) for construction-time concerns only:

```php
use Ran\PluginLib\Options\OptionScope;

// Named factories
$siteOpts    = RegisterOptions::site('my_plugin_option');
$networkOpts = RegisterOptions::network('my_plugin_network_option');
$blogOpts    = RegisterOptions::blog('my_plugin_blog_option', 123 /* blog_id */);
$userOpts    = RegisterOptions::user('my_plugin_user_option', 456 /* user_id */, true /* global? */);

// Factory from Config (construction-only args, typed context)
use Ran\PluginLib\Options\Storage\StorageContext;
$opts = RegisterOptions::from_config($config, StorageContext::forSite(), true);
```

#### Typed StorageContext (recommended)

Use the typed `StorageContext` path via `Config::options()` for validation and clear intent:

```php
use Ran\PluginLib\Options\Storage\StorageContext;

$opts = $config->options(StorageContext::forBlog(123));
```

### Factory vs. fluents

```php
use Ran\PluginLib\Options\Storage\StorageContext;

// Construction-only via factory; no writes happen here
$opts = $config->options(StorageContext::forSite());

// Configuration and persistence via fluents (20% use-cases)
$opts
  ->with_schema(['feature_flag' => ['default' => false, 'validate' => fn($v) => is_bool($v)]])
  ->with_policy($customPolicy)
  ->with_defaults(['another_key' => 'val'])
  ->commit_replace();

// from_config() notes
// - from_config() is intended for construction-time concerns (e.g., autoload, basic scope selection via typed StorageContext).
// - Prefer Config::options() + fluents for most cases; it’s a no-write accessor that binds the logger.
```

### Choosing an entry point

Most callers should start with `Config::options()` and then use fluent methods to attach schema/defaults/policy. Use `RegisterOptions::from_config()` only when you must control construction-time concerns (e.g., explicit typed `StorageContext` or autoload preference for first-create) at creation.

- `Config::options(StorageContext $ctx = StorageContext::forSite())`
  - No writes during construction
  - Binds logger from `ConfigInterface::get_logger()` when available
  - Preferred entry point for application code
- `RegisterOptions::from_config(ConfigInterface $config, ?StorageContext $ctx = null, bool $autoload = true)`
  - Construction-only concerns (scope via typed context, autoload preference)
  - No implicit writes; still configure schema/defaults/policy via fluents after creation
- Named factories (e.g., `RegisterOptions::site|network|blog|user`) are available for low-level cases when you don’t have a `Config` instance.

Tip: After construction, always register an explicit schema before calling `set_option()`/`stage_option()`/`stage_options()`/`with_defaults()`. Under strict validation, every key must have a `validate` callable.

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
$opts->with_schema($schema); // seeds defaults and normalizes values in-memory (no implicit writes)
```

Note: Schema registration seeds defaults and normalizes values in-memory by default. To persist, call `commit_replace()` (or `commit_merge()`) explicitly:

```php
$opts->commit_replace(); // single DB write for seeded defaults
```

You can also register schema after construction:

```php
$opts->register_schema([
  'new_feature' => ['default' => false, 'validate' => fn($v) => is_bool($v)],
]);
$opts->commit_replace(); // explicit persistence
```

### Schema reference and normalization (important)

Schemas are the single place to define per-key defaults, normalization (sanitize), and validation. They directly influence change detection and persistence behavior.

• **Keys per schema entry**

- `default: mixed|callable(ConfigInterface|null): mixed`
  - Literal default or a callable that returns a default (receives `ConfigInterface|null`).
- `sanitize: callable(mixed): mixed`
  - Normalizes/canonicalizes the value before comparison and storage on all write paths.
  - Use this to ensure semantically equivalent inputs map to one canonical form (e.g., sort associative keys, define a stable order for lists when order doesn’t matter).
- `validate: callable(mixed): bool`
  - Should return true for valid values; may throw or return false to reject.

• **Why this matters for strict equality and DB writes**

- `RegisterOptions` uses strict equality (`===`) to detect no-op changes.
- Without normalization, arrays with the same content but different order will not be equal and may cause needless writes.
- By normalizing in `sanitize`, you make structurally equivalent values compare equal, preventing redundant DB updates.

• **Where schema is applied (write paths)**

- `set_option($key, $value)` → sanitize + validate before strict equality check and persistence.
- `stage_option($key, $value)` / `stage_options([...])` → sanitize + validate before staging in memory.
- `with_defaults([...])` / `register_schema(...)` / `with_schema(...)` → defaults are resolved, then sanitized + validated before taking effect (in-memory). No implicit writes.
- `seed_if_missing([...])` and `migrate(callable)` → values are normalized and validated before persistence.

• **Consistent DB shape (read/write)**

- Because values are canonicalized via `sanitize` prior to storage, subsequent reads and writes interact with a consistent shape.
- This stabilizes equality checks, reduces write churn, and avoids drift caused by incidental ordering differences.

• **Practical guidance**

- For order-insensitive structures: in your `sanitize`, recursively sort associative keys and apply a stable ordering to lists (only if list order is semantically irrelevant).
- For order-sensitive data: omit normalization so that strict equality preserves intentional ordering.
- Keep validation focused on invariants (types, ranges, presence of required fields) after normalization.

See also: `plugin-lib/inc/Options/docs/examples/schema-sanitize-validate.php` for end-to-end examples of `sanitize` and `validate` callbacks.

### Strict validation (no inference)

Schemas must provide an explicit `validate` callable for every key. Sanitization is optional but, if present, must be callable. Registration-time checks enforce:

- A callable `validate` exists for each schema key (required)
- If `sanitize` is present, it is callable (optional)

Defaults remain supported (literal or callable), and are applied in-memory on registration. There is no type inference from defaults—validation must be explicit, e.g.:

```php
$schema = [
  'flag' => [ 'default' => false, 'validate' => fn($v) => is_bool($v) ],
  'max'  => [ 'default' => 10,    'validate' => fn($v) => is_int($v) && $v >= 0 ],
  'name' => [ 'default' => 'abc', 'validate' => fn($v) => is_string($v) && $v !== '' ],
];
```

### Validation helper recipes

Common validator shapes you may find useful:

- Booleans: `fn($v) => is_bool($v)`
- Non-empty string: `fn($v) => is_string($v) && $v !== ''`
- String pattern: `fn($v) => is_string($v) && preg_match('/^[A-Za-z0-9_-]+$/', $v)`
- Positive int (bounded): `fn($v) => is_int($v) && $v > 0 && $v <= 300`
- Enum: `fn($v) => in_array($v, ['a','b','c'], true)`

If you maintain shared validators, place them in `inc/Options/Validate.php` and reference them in your schema, e.g. `['validate' => [Validate::class, 'isNonEmptyString']]`.

See: `plugin-lib/inc/Options/docs/examples/schema-sanitize-validate.php` for end-to-end patterns (sanitize + validate).

### Batch updates (recommended for bulk operations)

Stage changes in memory and persist once:

```php
$opts
  ->stage_options([
    'enabled' => ['value' => true],
    'timeout' => 30,
  ])
  ->commit_replace(); // single DB write
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
// stage_option($option_name, $current, '', $newAutoload); // $newAutoload: true|false|null
//
// Pre‑6.6 fallback:
// stage_option($option_name, $current, '', $newAutoload ? 'yes' : 'no');
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

### Memory vs Persistence

- `set_option()` / `update_option()` — Immediate persistence path

  - Values are sanitized and validated against the current schema before any write.
  - A strict no‑op guard uses `===` to avoid redundant writes (arrays must match exactly; order matters unless normalized by `sanitize`).
  - Writes are allowed only if the immutable write policy and filters permit it:
    - General filter: `ran/plugin_lib/options/allow_persist`
    - Scoped filter: `ran/plugin_lib/options/allow_persist/scope/{site|network|blog|user}`
  - On success, the grouped option row for the current scope is updated in the DB immediately. Autoload is unaffected after creation (WordPress behavior).
  - Return value indicates whether a write occurred (true = persisted, false = blocked or no‑op).

- `stage_option()` / `stage_options()` — In‑memory batching path
  - Values are sanitized and validated, then stored in memory only (no DB write yet).
  - Use this to batch multiple changes and persist once with one of the commit methods:
    - `commit_merge()` — shallow, top‑level merge with the current DB snapshot (keeps DB keys not present in memory; in‑memory keys overwrite collisions with in-memory values).
    - `commit_replace()` — full replace of the grouped row with the in‑memory payload (no merge).
  - Typical flow:

```php
$opts
  ->stage_option('flag', true)
  ->stage_option('timeout', 45)
  ->commit_merge(); // single DB write
```

Use a schema to prevent unnecessary DB writes, and to seed defaults. You should use `commit_merge()` when you are only making additive changes to independent keys without touching nested structures.

### Shallow vs Deep merge cheat sheet

- Top‑level shallow merge (`commit_merge()`)

  - Reads the current DB row, performs a top‑level array merge, and writes back.
  - Good for additive changes to independent keys without touching nested structures.
  - Example result (DB: `{a:1, nested:{x:1}}`, Memory: `{b:2}`) → Saved: `{a:1, nested:{x:1}, b:2}`.

- Deep merge (read–modify–write + `commit_replace()`)
  - When nested structures must be merged, first compute the merged structure in PHP, then stage and `commit_replace()`.
  - Example:

```php
$current = $opts->get_option('complex_map', []);
$patch   = ['level1' => ['added' => 'x']];
$merged  = array_replace_recursive(is_array($current) ? $current : [], $patch);
$opts->stage_option('complex_map', $merged)->commit_replace();
```

### No implicit writes (cheat sheet)

- set_option/update_option
  - Persists immediately on success (after write-gate policy and filters).
  - Uses the current scope from typed `StorageContext`.
- add_option/stage_options
  - Stages changes in memory only; call `commit_merge()` or `commit_replace()` to persist.
- commit_merge()
  - Performs a shallow, top-level merge with the current DB snapshot (keeps DB keys, overwrites collisions with in-memory values). Does not deep-merge nested structures.
- commit_replace()
  - Replaces the stored row with the in-memory payload (no merge).
- register_schema/with_schema
  - Always seed defaults and normalize values in-memory. There are no implicit writes; persist with `commit_replace()` or `commit_merge()` when you are ready.

### Examples

- Basic usage: `plugin-lib/inc/Options/docs/examples/basic-usage.php`
- Batch + flush: `plugin-lib/inc/Options/docs/examples/batch-and-flush.php`
- Deep merge pattern: `plugin-lib/inc/Options/docs/examples/deep-merge-pattern.php`
- Sanitization & validation: `plugin-lib/inc/Options/docs/examples/schema-sanitize-validate.php`
- Schema defaults at construction: `plugin-lib/inc/Options/docs/examples/schema-defaults.php`
- Flip autoload safely: `plugin-lib/inc/Options/docs/examples/autoload-flip.php`

### API highlights

- `get_option($key, $default)` / `set_option($key, $value)`
- `get_options()` returns values only
- Fluent batching: `stage_option($k,$v)->stage_option($k2,$v2)->commit_replace()` or `stage_options([...])->commit_replace()`
- `commit_merge()` / `commit_replace()` to persist staged changes (shallow merge vs replace)
- `register_schema(...)` / `with_schema(...)` for post-construction schema
