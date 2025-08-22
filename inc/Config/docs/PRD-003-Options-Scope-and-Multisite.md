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

## Proposed Additions

### 1) Config options accessor with scope params

Extend `Config::options(array $args = [])`:

```php
public function options(array $args = []): \Ran\PluginLib\Options\RegisterOptions;
```

- Supported args: `scope` (`'site'|'network'|'blog'`, default `'site'`), `blog_id` (`?int`, optional for `'blog'`; defaults to current blog when omitted).
- Side‑effect free; returns a pre‑wired `RegisterOptions`.

### 2) Scope‑aware factory on RegisterOptions

Extend `RegisterOptions`:

```php
public static function fromConfig(\Ran\PluginLib\Config\ConfigInterface $cfg, string $scope = 'site', ?int $blog_id = null): self;
```

- Binds to `$cfg->get_options_key()` with the requested scope.

### 3) Internal storage adapters (private)

- SiteOptionStorage → `get_option` / `update_option` / `delete_option` (supports autoload)
- NetworkOptionStorage → `get_site_option` / `update_site_option` / `delete_site_option`
- BlogOptionStorage → `get_blog_option` / `update_blog_option` / `delete_blog_option`

These are internal implementation details; not part of the public API.

### 4) Autoload semantics (implemented in prep work for PRD-002)

- `RegisterOptions::get_main_autoload(): ?bool`
  - Returns `true` when the main option is autoloaded (preloaded via `wp_load_alloptions()`).
  - Returns `false` when the main option row exists but is not autoloaded.
  - Returns `null` when the main option row does not exist or autoload is not applicable in the current scope.
- `RegisterOptions::set_main_autoload(bool $autoload)`
  - MUST short‑circuit (no delete+add) when the current autoload already matches the requested value.
  - Otherwise forces the flip via delete+add (WordPress only applies autoload on creation).
- Content source policy during flip
  - The library re‑adds the latest DB snapshot of the option row (default). This preserves external writers’ changes.
  - If callers need to include staged in‑memory changes, they should call `$options->flush(true)` first, then `set_main_autoload(...)`.
- Missing‑row behavior
  - If the main option row does not exist, `set_main_autoload(...)` will create it (empty array) with the requested autoload value.
- Scope applicability
  - Applies to `'site'` and to `'blog'` when targeting the current blog (i.e., `blog_id` omitted or equals the current blog).
  - For `'network'` and for `'blog'` targeting a different blog than current, autoload is not applicable: `get_main_autoload()` returns `null` and `set_main_autoload(...)` is a no‑op that logs a developer notice via the configured logger.

> Rationale: Network options live in `wp_sitemeta` (no autoload). Blog autoload preloading occurs only for the current runtime blog. Therefore autoload flips are meaningful for `'site'` and for `'blog'` only when targeting the current blog.

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

The filter receives (2nd arg) context: `op`, `main_option`, `options`, `autoload`, `scope`, `blog_id`, `mergeFromDb?`, `config?`. Return false to veto the write.

Context fields:

- **op (string)** — operation attempted: `save_all` | `set_main_autoload`.
- **main_option (string)** — main WordPress option key this manager writes to.
- **options (array)** — associative values to be persisted (treat as read‑only).
- **autoload (bool)** — autoload state that will result if the write proceeds.
- **scope ('site'|'network'|'blog')** — target storage context for the operation.
- **blog_id (?int)** — when `scope='blog'`: defaults to current blog if omitted; otherwise set to the target blog ID. Null otherwise.
- **mergeFromDb (bool)** — only for `op='save_all'`; whether values will merge with DB vs replace.
- **config (array|null)** — optional library config snapshot for policy decisions.

```php
// Generic gate: allow network writes only to admins with manage_network_options
add_filter('ran/plugin_lib/options/allow_persist', function (bool $allowed, array $ctx) {
    if (!is_multisite()) {
        return $allowed;
    }
    if (($ctx['scope'] ?? 'site') === 'network') {
        if (!is_network_admin() || !current_user_can('manage_network_options')) {
            return false;
        }
    }
    return $allowed;
}, 10, 2);
```

```php
// Scope alias: block all blog-scoped writes except for a specific blog ID
add_filter('ran/plugin_lib/options/allow_persist/scope/blog', function (bool $allowed, array $ctx) {
    $blogId = (int) ($ctx['blog_id'] ?? 0);
    if ($blogId !== 123) {
        return false;
    }
    // Optionally also enforce a capability check:
    return current_user_can('manage_options');
}, 10, 2);
```

```php
// Operation-specific: disallow autoload flips outside admin screens
add_filter('ran/plugin_lib/options/allow_persist', function (bool $allowed, array $ctx) {
    if (($ctx['op'] ?? '') === 'set_main_autoload') {
        if (!is_admin()) {
            return false;
        }
    }
    return $allowed;
}, 10, 2);
```

```php
// CLI/cron guidance: allow specific operations only when running via WP-CLI or cron
add_filter('ran/plugin_lib/options/allow_persist', function (bool $allowed, array $ctx) {
    $isCli  = defined('WP_CLI') && WP_CLI;
    $isCron = defined('DOING_CRON') && DOING_CRON;
    if ($isCli || $isCron) {
        // e.g., allow save_all but not set_main_autoload
        return ($ctx['op'] ?? '') === 'save_all' ? $allowed : false;
    }
    return $allowed;
}, 10, 2);
```

### Autoload semantics

```php
// Guarded flip using tri‑state getter (site scope, current blog)
$opts = $config->options(['scope' => 'site']);
$status = $opts->get_main_autoload(); // true | false | null
if ($status !== true) {
    $opts->set_main_autoload(true); // no-op if already true; otherwise delete+add using DB snapshot
}

// Blog scope targeting current blog (blog_id omitted)
$blogOpts = $config->options(['scope' => 'blog']);
if ($blogOpts->get_main_autoload() !== true) {
    $blogOpts->set_main_autoload(true);
}

// Include staged in‑memory changes before flipping (optional)
$opts->flush(true); // shallow top-level merge with DB to preserve disjoint keys
if ($opts->get_main_autoload() !== false) {
    $opts->set_main_autoload(false);
}

// Different blog (requires switching runtime); autoload only applies when the runtime is the target blog
switch_to_blog($blog_id);
$config->options(['scope' => 'blog'])->set_main_autoload(true);
restore_current_blog();
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
- Explicit scoping reduces ambiguity and improves testability.
- Internal adapters isolate WordPress API differences.
- Persisted payload shape remains unchanged; scope and autoload are access‑layer concerns and are not stored in the option row.

## Risks & Mitigations

- Hidden behavior via header default → Document clearly; explicit params always override.
- Autoload confusion on non‑site scopes → No‑op + logger notice; emphasize in docs.
- Migration complexity → Provide WP‑CLI recipe with dry‑run and rollback notes.

## Acceptance Criteria

- `Config::options(array $args = [])` accepts `scope` (default `'site'`) and optional `blog_id` for `'blog'` scope (defaults to current blog when omitted) without breaking existing call sites.
- `RegisterOptions::fromConfig($cfg, string $scope = 'site', ?int $blog_id = null)` available.
- Internal storage adapters route calls to correct WP APIs.
- Autoload behavior:
  - `get_main_autoload(): ?bool` returns tri‑state (true/false/null) per scope and existence rules documented above.
  - `set_main_autoload(bool)` implements a no‑op guard when unchanged; when flipping, it re‑adds the latest DB snapshot; when missing, it creates the row with the requested autoload.
  - Applies to `'site'` and `'blog'`(current blog); no‑op + developer notice for `'network'` and `'blog'`(other blog).
- Documentation includes an “Option Scope” section and examples above.
- Capability checks are the caller's responsibility, per-scope validation hooks are provided.
- Migration recipe documented.
- Unit tests cover scope routing and autoload behavior, including tri‑state getter and the setter’s no‑op guard.
