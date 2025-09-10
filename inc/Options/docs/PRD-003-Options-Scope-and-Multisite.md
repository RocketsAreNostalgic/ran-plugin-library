# PRD-003: Options Scope - Site, Multisite & Blog Support

- Area: `plugin-lib/inc/Config/*`, `plugin-lib/inc/Options/*`
- Related: `ConfigInterface`, `Config`, `RegisterOptions`
- Date: 2025-08-19

## Overview

Introduce explicit option scope support (site, network, blog) while keeping today’s behavior unchanged. Provide ergonomic access via `Config::options()` with optional scope parameters, a factory on `RegisterOptions`, and internal storage adapters that map to the appropriate WordPress APIs. Migration guidance is provided via WP-CLI recipes. No implicit writes; no breaking changes.

## Goals

- Non‑breaking default scope (`'site'`).
- Explicit, testable scope control for network and blog contexts.
- Internals isolate WP API differences via storage adapters.
- Clear documentation of scope semantics and autoload limits.
- Migration guidance (site→network) via WP-CLI recipe.

## Non-Goals

- Implicit scope detection based on current admin screen.
- Automatic data migrations at runtime.
- Changing option key resolution (`get_options_key()` remains unchanged).

## Late Addition: User Scope Support

**Note:** Late in the development cycle, we decided to add support for user-scoped options and user metadata storage. This extends the original scope design to include:

- `OptionScope::User` enum value
- `UserOptionStorage` adapter implementing user meta (`get_user_meta`, `update_user_meta`, etc.)
- Support for both per-user options and global user options via the `user_global` parameter
- User storage selection via `user_storage` parameter (`'meta'` or `'option'` - though `'option'` may have limited WordPress core support)

This addition maintains the same architectural patterns established for site/network/blog scopes, with user ID as a required parameter similar to how blog ID is required for blog scope.

## Architecture Decision: Option A — Enum + Storage Adapters (Strategy)

We will implement scope routing using a small, injected storage adapter selected by an enum. `RegisterOptions` composes this adapter and delegates all persistence to it. This keeps the public API stable while isolating WP API differences per scope.

- OptionScope enum (library‑internal, may be referenced in docs/examples)

```php
namespace Ran\PluginLib\Options;

enum OptionScope: string { case Site = 'site'; case Network = 'network'; case Blog = 'blog'; }
```

- OptionStorageInterface (private API) implemented by concrete adapters

```php
namespace Ran\PluginLib\Options\Storage;

use Ran\PluginLib\Options\OptionScope;

interface OptionStorageInterface {
    public function scope(): OptionScope;
    public function blogId(): ?int;               // null unless scope=Blog
    public function supports_autoload(): bool;     // Site and Blog(current) only

    public function read(string $key, mixed $default = false): mixed;
    public function update(string $key, mixed $value, ?bool $autoload = null): bool; // WP does not change autoload on update
    public function add(string $key, mixed $value, ?bool $autoload = null): bool;   // null defers to WP heuristics (6.6+)
    public function delete(string $key): bool;
}
```

- Concrete adapters (private):
  - SiteOptionStorage → `get_option` / `update_option` / `add_option` / `delete_option` + `wp_load_alloptions()`; supports autoload
  - NetworkOptionStorage → `get_site_option` / `update_site_option` / `add_site_option` / `delete_site_option`; autoload N/A
  - BlogOptionStorage → `get_blog_option` / `update_blog_option` / `add_blog_option` / `delete_blog_option`; requires `blog_id`; autoload only when targeting current blog

A small factory selects the adapter from `(scope, blog_id)` and is used by both `Config::options()` and `RegisterOptions::fromConfig()`.

## Proposed Additions

### 1) Config options accessor with scope params

Extend `Config::options(array $args = [])`:

```php
public function options(array $args = []): \Ran\PluginLib\Options\RegisterOptions;
```

- Supported args:
  - `scope` (`'site'|'network'|'blog'`, default `'site'`)
  - `blog_id` (`?int`, optional for `'blog'`; defaults to current blog when omitted)
  - `autoload` (`bool`, default `true`) — policy hint for future writes; no write by itself
  - `initial` (`array<string,mixed>`, default `[]`) — staged values merged in‑memory on the manager
  - `schema` (`array<string,mixed>`, default `[]`) — staged schema merged in‑memory on the manager
- Side‑effect free; returns a pre‑wired `RegisterOptions`.
- Unknown args are ignored and a warning is emitted via the configured logger.

### 2) Scope‑aware factory on RegisterOptions

Extend `RegisterOptions`:

```php
public static function fromConfig(\Ran\PluginLib\Config\ConfigInterface $cfg, string $scope = 'site', ?int $blog_id = null): self;
```

- Accepts scope as `'site'|'network'|'blog'` (string). Internally normalized to `OptionScope`.
- Wires an appropriate `OptionStorageInterface` via the internal factory.
- Binds to `$cfg->get_options_key()` with the requested scope and blog context.
- For `scope='blog'`, `blog_id` is optional; omitted means current blog.

### 3) Internal storage adapters (private)

- Adapters implement `OptionStorageInterface` (see Architecture Decision above).
- SiteOptionStorage → `get_option` / `update_option` / `add_option` / `delete_option` + `wp_load_alloptions()`; supports autoload.
- NetworkOptionStorage → `get_site_option` / `update_site_option` / `add_site_option` / `delete_site_option`; autoload not applicable.
- BlogOptionStorage → `get_blog_option` / `update_blog_option` / `add_blog_option` / `delete_blog_option`; requires `blog_id`; autoload only when targeting current blog.

These are internal implementation details; not part of the public API.

### 4) Autoload behavior

Autoload behavior is determined by WordPress core and the storage scope:

- **Site scope**: Options can be autoloaded (configurable during option creation; pass bool or null where null defers to WP 6.6+ heuristics)
- **Blog scope**: Options can be autoloaded only when targeting the current blog
- **Network scope**: Options are never autoloaded (stored in wp_sitemeta)
- **User scope**: Options are never autoloaded

For manual autoload flipping when needed:

```php
// Manual autoload flip
$current = get_option($option_name);
delete_option($option_name);

// WP 6.6+: prefer bool|null (null defers to heuristics)
add_option($option_name, $current, '', $newAutoload); // true|false|null

// Pre-6.6 fallback
// add_option($option_name, $current, '', $newAutoload ? 'yes' : 'no');
```

### 5) Optional header default (opt‑in)

- Header: `RAN.OptionScope: network|site`
- Sets a default scope for `Config::options()`. Explicit arguments always override.

### 6) Migration (WP‑CLI recipe)

- Provide a recipe/guide to consolidate or migrate rows from per‑site storage to a network‑wide row.
- Support dry‑run, progress output, and rollback guidance.

## Usage Examples

### Plugin (default: site scope)

```php
use Ran\PluginLib\Config\Config;

$config = Config::fromPluginFile(__FILE__);
$opts   = $config->options(); // defaults to 'site'

$current = $opts->get_values();
```

### Network‑wide options (multisite)

```php
if (is_multisite() && current_user_can('manage_network_options')) {
  $opts = $config->options(['scope' => 'network']);
  $global = $opts->get_values();
}
```

### Scope with initial and schema (no write until explicit)

```php
// Stage values and schema for network scope; this does not persist yet
$opts = $config->options([
  'scope'   => 'network',
  'initial' => ['enabled' => true],
  'schema'  => ['enabled' => ['default' => false]],
]);

// Persist explicitly (seed defaults and flush once)
$opts->register_schema(['enabled' => ['default' => false]], true, true);
```

### Blog‑specific lookup (by blog ID)

```php
$blog_id = 123; // known site ID
$opts = $config->options(['scope' => 'blog', 'blog_id' => $blog_id]);
$blog_settings = $opts->get_values();
// Alternatively:
// $opts = \Ran\PluginLib\Options\RegisterOptions::fromConfig($config, 'blog', $blog_id);
// $blog_settings = $opts->get_values();
```

> Security note: `Config::options()` and related accessors do not perform capability checks.
> Callers MUST gate network/blog-scoped reads and ALL writes (admin UI, REST, CLI)
> with appropriate capabilities (e.g., `manage_network_options` for network, `manage_options` for site/blog).
> In multisite, when checking capabilities for another blog/site you may need `switch_to_blog()` or
> `user_can_for_blog()` as appropriate.

### Per‑scope validation hooks (write‑gating)

Example filter names:

- `ran/plugin_lib/options/allow_persist` (generic gate)
- `ran/plugin_lib/options/allow_persist/scope/{site|network|blog}` (scope aliases)

The filter receives (2nd arg) context: `op`, `main_option`, `options`, `scope`, `blog_id`, `user_id`, `merge_from_db?`, `config?`. Return false to veto the write.

Context fields:

- **op (string)** — operation attempted: `save_all`, `set_option`, `add_options`, etc.
- **main_option (string)** — main WordPress option key this manager writes to.
- **options (array)** — associative values to be persisted (treat as read‑only).
- **scope ('site'|'network'|'blog'|'user')** — target storage context for the operation.
- **blog_id (?int)** — when `scope='blog'`: defaults to current blog if omitted; otherwise set to the target blog ID. Null otherwise.
- **user_id (?int)** — when `scope='user'`: the target user ID.
- **merge_from_db (bool)** — only for `op='save_all'`; whether values will merge with DB vs replace.
- **config (array|null)** — optional library config snapshot for policy decisions.

```php
// Generic gate: allow network writes only to admins with manage_network_options
add_filter('ran/plugin_lib/options/allow_persist', function (bool $allowed, array $context) {
    if (!is_multisite()) {
        return $allowed;
    }
    if (($context['scope'] ?? 'site') === 'network') {
        if (!is_network_admin() || !current_user_can('manage_network_options')) {
            return false;
        }
    }
    return $allowed;
}, 10, 2);
```

```php
// Scope alias: block all blog-scoped writes except for a specific blog ID
add_filter('ran/plugin_lib/options/allow_persist/scope/blog', function (bool $allowed, array $context) {
    $blogId = (int) ($context['blog_id'] ?? 0);
    if ($blogId !== 123) {
        return false;
    }
    // Optionally also enforce a capability check:
    return current_user_can('manage_options');
}, 10, 2);
```

```php
// CLI/cron guidance: allow specific operations only when running via WP-CLI or cron
add_filter('ran/plugin_lib/options/allow_persist', function (bool $allowed, array $context) {
    $isCli  = defined('WP_CLI') && WP_CLI;
    $isCron = defined('DOING_CRON') && DOING_CRON;
    if ($isCli || $isCron) {
        // Allow all operations in CLI/cron context
        return $allowed;
    }
    return $allowed;
}, 10, 2);
```

### Manual autoload flipping

For manual autoload flipping when needed:

```php
// Manual autoload flip for site scope
$option_name = $config->get_options_key();
$current = get_option($option_name);
delete_option($option_name);
add_option($option_name, $current, '', $new_autoload ? 'yes' : 'no');
```

### Migration recipe (WP‑CLI sketch)

```php
// Pseudocode for a CLI command; actual implementation will live under a CLI namespace.
$target_key = $config->get_options_key();
$network    = $config->options(['scope' => 'network']);

$aggregate = [];
foreach (get_sites(['number' => 0]) as $site) {
  $blog_opts = $config->options(['scope' => 'blog', 'blog_id' => (int) $site->blog_id]);
  $data = $blog_opts->get_values();
  // Merge policy up to the product: union/override/etc.
  $aggregate = array_replace_recursive($aggregate, (array) $data);
}

// Dry‑run gate here in real CLI.
$network->update_option($target_key, $aggregate);
```

## Design Notes

- Keep `Config` read‑only with respect to options (no implicit writes).
- Default remains `'site'`; no behavioral change for existing consumers.
  292→- Explicit scoping reduces ambiguity and improves testability.
  293→- Internal adapters isolate WordPress API differences.
  294→- Persisted payload shape remains unchanged; scope and autoload are access‑layer concerns and are not stored in the option row.

## Synchronization & Instance Lifecycle

- **Single instance per key**

  - Prefer a single `RegisterOptions` instance per `(scope, blog_id, main_option_key)` per request.
  - Library does not maintain a global registry; callers should manage sharing when needed.

- **Freshness rules**

  - Before must‑be‑fresh reads, call `refresh_options()` to reload from storage.
  - After external writes (other processes/plugins), call `refresh_options()` to avoid stale caches.

- **Write batching**

  - Stage multiple changes via `add_option(s)` and persist once with `flush(true)` to merge with the latest DB snapshot (shallow, top‑level), preserving disjoint keys.
  - For deep/nested merges, callers should prepare the merged payload and use `flush(false)` to replace without DB merge.

- **Multiple instances in same request**

  - If multiple instances target the same `(scope, blog_id, key)`, their internal caches can diverge. Either share one instance or call `refresh_options()` before reads/writes.

- **Blog scope and runtime context**

  - For `scope='blog'` with `blog_id` omitted, the adapter targets the current runtime blog. If `switch_to_blog()` is used later, obtain a new instance for that blog.
  - For explicit `blog_id`, autoload semantics only apply when the runtime blog equals the target blog. To flip autoload, `switch_to_blog($blog_id)` first.

- **Autoload flips**

  - See “Autoload semantics”. Setter is guarded; when flipping, content comes from the latest DB snapshot unless the caller `flush(true)` beforehand.

- **Write gating**
  - Use the documented filters (e.g., `ran/plugin_lib/options/allow_persist`) to gate writes per scope, operation, and execution context (admin/CLI/cron).

## Testing Plan

- **Adapters (unit, isolated)**

  - Verify each adapter routes to the correct WP wrapper (`get_option`, `get_site_option`, `get_blog_option`, etc.).
  - `supports_autoload()` truth table: Site=true; Blog=true when current blog equals target; Network=false; Blog(other)=false.

- **RegisterOptions integration**

  - `fromConfig()` and `Config::options()` return instances that read/write through the correct adapter for `(scope, blog_id)`.
  - `flush(true)` performs shallow, top‑level merge with current DB snapshot; `flush(false)` replaces without merge.

- **Multisite nuances**

  - Blog(current) vs Blog(other): ensure scope behavior differences and adapter selection are respected.
  - Switching blogs: demonstrate that a new instance is needed after `switch_to_blog()` when using implicit blog scope.

- **Filters and capability gates**

  - Tests where `ran/plugin_lib/options/allow_persist` returns false: verify no writes occur and appropriate logs are emitted.

- **Logging**
  - Verify developer notices for no‑op autoload flips and blocked writes. Match exact message patterns used in implementation.

Notes:

- Use WP_Mock for WordPress function expectations and Mockery for logger expectations.
- Keep tests non‑breaking with current public API; adapters and interface remain private to the library.

## Risks & Mitigations

- Hidden behavior via header default → Document clearly; explicit params always override.
- Autoload confusion on non‑site scopes → No‑op + logger notice; emphasize in docs.
  {{ ... }}

## Acceptance Criteria

- `Config::options(array $args = [])` accepts `scope` (default `'site'`) and optional `blog_id` for `'blog'` scope (defaults to current blog when omitted) without breaking existing call sites.
- `RegisterOptions::fromConfig($cfg, string $scope = 'site', ?int $blog_id = null)` available and uses the storage factory internally.
- `OptionScope` enum defined with values `site`, `network`, `blog` (library‑internal, referenced in docs/examples).
- `OptionStorageInterface` defined and implemented by `SiteOptionStorage`, `NetworkOptionStorage`, `BlogOptionStorage`.
  - Internal storage adapters route calls to correct WP APIs, honoring autoload only when `supports_autoload()` is true.
- Autoload behavior: Determined by WordPress core and storage scope as documented above.
- Documentation includes an “Architecture Decision” section and examples above.
- Capability checks are the caller's responsibility, per-scope validation hooks are provided.
- Migration recipe documented.
- Unit tests cover scope routing and storage adapter behavior.
