# PRD-002: Batteries-Included Config + Options Integration

- Area: `plugin-lib/inc/Config/*`, `plugin-lib/inc/Options/*`
- Related: `ConfigInterface`, `Config`, `RegisterOptions`
- Date: 2025-08-14

## Overview

We have a unified, environment-agnostic Config that hydrates from plugins or themes, exposes normalized keys, and supports namespaced custom headers. This PRD proposes a "batteries-included" layer that makes using WordPress options with our `Config` system effortless and consistent, while remaining optional and non-intrusive.

## Goals

- Provide a clear, single source of truth for the app option key used in WP options.
- Offer a one-liner to obtain a pre-wired Options manager tied to the app option key.
- Support safe seeding and schema/migration workflows without forcing adoption.
- Keep Config and Options loosely coupled but ergonomic.

## Non-Goals

- Changing how standard WP headers are read (we keep WP APIs).
- Writing options automatically during hydration (no side effects).
- Forcing schema/migrations; these remain opt-in.

## Current State (baseline)

- Config

  - Normalized `get_config()` with environment-neutral keys.
  - Namespaced custom headers (e.g., `RAN.AppOption`).
  - `get_options_key()` returns the computed options key based on `RAN.AppOption` or `Slug` for plugins/themes in WP options.
  - `get_logger()`, `is_dev_environment()` implemented.

- Options (`RegisterOptions`)
  - Manages a single WP option (array/object), with helpers to read/write/merge/flush.

## Proposed Additions

### 1) Option key helper

Add to Config:

```php
public function get_options_key(): string;
```

- Returns `RAN.AppOption` if present; otherwise returns `Slug`.
- No I/O, just a deterministic key to be used with Options.

### 2) First-class Options accessor

Add to Config:

```php
public function options(): \Ran\PluginLib\Options\RegisterOptions;
```

- Returns a `RegisterOptions` instance pre-wired to the app’s option key.
- No implicit writes; just the manager instance.

### 3) Options convenience constructors

Add to `RegisterOptions`:

```php
public static function fromConfig(\Ran\PluginLib\Config\ConfigInterface $cfg): self;
```

- Initializes a `RegisterOptions` bound to `$cfg->get_options_key()`.

### 4) Seeding helper (activation-time)

Add to `RegisterOptions`:

```php
public function seed_if_missing(array $defaults): self;
```

- On activation, creates the option row if it does not exist, with `$defaults`.
- Idempotent: no write if the option already exists.

### 5) Migration hook

Add to `RegisterOptions`:

```php
public function migrate(callable $migration): self;
```

- Executes user-provided migration logic to transform stored data (e.g., on version bump).
- Caller is responsible for version checks and idempotency.

### 6) Optional Arguments (non-breaking)

Extend `Config::options()` to accept optional arguments while preserving current defaults and behavior (no implicit writes).

```php
public function options(array $args = []): \Ran\PluginLib\Options\RegisterOptions;
```

- **`autoload: bool`** — default `true`. Hint used when creating a new row; does not write by itself.
- **`flip_autoload: bool`** — default `false`. If `true`, explicitly flip autoload via `RegisterOptions::set_main_autoload()` (delete+add).
  - Guarded: no-op when unchanged; uses latest DB snapshot by default. Until PRD‑003, this operates on the current blog (`wp_options`) only.
- **`scope: string`** — Available in 0.1.3 (see PRD‑003). Default `'site'`. One of `'site' | 'network' | 'blog'`.
- **`blog_id: ?int`** — Available in 0.1.3 (see PRD‑003). Required when `scope='blog'`; in 0.1.3 defaults to current blog when omitted.
- **`schema: array`** — default `[]`. Register schema on the manager; no write unless seeding/flush is requested.
- **`seed_defaults: bool`** — default `false`. When `true` and `schema` present, seed missing values from schema.
- **`flush: bool`** — default `false`. Persist once if changes occurred (seeding or providing `initial`).
- **`initial: array`** — default `[]`. Initial key/value(s) to add to the in-memory payload; only saved if `flush=true`.
- **`logger: ?Logger`** — optional override; defaults to `Config::get_logger()`.

Notes:

- **No implicit writes** unless `flip_autoload=true` or `flush=true` (with `seed_defaults` and/or `initial`).
- Autoload flip uses the documented safe pattern; see `inc/Options/docs/examples/autoload-flip.php`.
- Until PRD‑003 is implemented, autoload flipping operates on the current blog (site scope). Scope semantics and autoload limits for network/blog are introduced in 0.1.3 per PRD‑003.

Autoload implementation details:

- WordPress option APIs expect autoload as strings `'yes'` or `'no'` (not booleans). The library adheres to this when calling `update_option()` and `add_option()`.
- Initial creation respects the configured autoload policy: when constructing `RegisterOptions` with `$main_option_autoload = false` and persisting (via defaults/initials + flush), the stored row is created with autoload `'no'`.

### Examples

Keep defaults (no writes):

```php
$opts = $config->options();
```

Set autoload policy for new row only (no writes):

```php
$opts = $config->options(['autoload' => false]);
```

Explicitly flip autoload (delete + add):

```php
$opts = $config->options(['flip_autoload' => true]);
```

Note: `set_main_autoload()` is guarded (no-op when unchanged) and re-adds using the latest DB snapshot. To include staged in-memory changes before flipping, call `$opts->flush(true)` first. Until PRD‑003, flipping applies to the current blog only.

Register schema, seed, and persist once:

```php
$opts = $config->options([
  'schema'        => [
    'enabled' => ['default' => true],
    'timeout' => ['default' => 30],
  ],
  'seed_defaults' => true,
  'flush'         => true,
]);
```

Provide initial values and persist with a single flush:

```php
$opts = $config->options([
  'initial' => [
    'enabled' => true,
    'mode'    => 'fast',
  ],
  'flush' => true,
]);
```

Network scope (multisite) — Available in 0.1.3 (see PRD‑003):

```php
$opts = $config->options(['scope' => 'network']);
```

Blog scope (by blog ID) — Available in 0.1.3 (see PRD‑003):

```php
$opts = $config->options(['scope' => 'blog', 'blog_id' => 123]);
```

## Usage Examples

### Plugin bootstrap

```php
use Ran\PluginLib\Config\Config;

$config = Config::fromPluginFile(__FILE__)
  ->set_is_dev_callback(static fn() => defined('MY_ENV') && MY_ENV === 'dev'));

$opts = $config->options();

// Activation hook
register_activation_hook(__FILE__, function () use ($opts, $config) {
  $defaults = [
    'enabled' => !$config->is_dev_environment(),
    'timeout' => $config->is_dev_environment() ? 5 : 30,
  ];
  $opts->seed_if_missing($defaults);
});

// Runtime
$current = $opts->get_values();
```

### Theme

```php
$config = Config::fromThemeDir(get_stylesheet_directory());
$opts   = $config->options();
$slug   = $config->get_options_key();
$current = $opts->get_values();
```

### Reading values and a single field

```php
$opts = $config->options();

// Full values array (recommended to read all current values)
$values = $opts->get_values();

// Read a single field with a default
$enabled = $opts->get_option('enabled', false);
```

## Design Notes

- Separation of concerns: Config never writes options; Options manages persistence.
- Ergonomics: a single call (`options()`) provides a correctly wired `RegisterOptions`.
- Safety: `seed_if_missing()` avoids unintended overwrites; migrations are explicit and opt-in.
- Extensibility: schemas, validation, autoload, and complex merges remain the domain of `RegisterOptions` and can be layered in without changing Config.

## Risks & Mitigations

- Risk: Tighter coupling perceived between Config and Options.
  - Mitigation: Accessor only; Options remains independently usable.

## Acceptance Criteria

- Config exposes `get_options_key()` and `options()`.
- `RegisterOptions` exposes `fromConfig()`, `seed_if_missing()`, and `migrate()`.
- README updated with examples for plugins and themes.
- No implicit DB writes during hydration; all writes occur via Options helpers.

## Feature Development (Target 0.1.3)

Non-breaking expansion to add explicit option scope support. See PRD-003: [PRD-003-Options-Scope-and-Multisite.md](./PRD-003-Options-Scope-and-Multisite.md).

- Extend `Config::options(array $args = [])` to accept `scope` (default `'site'`) and optional `blog_id` (required for `'blog'`).

- Provide `RegisterOptions::fromConfig(\Ran\PluginLib\Config\ConfigInterface $cfg, string $scope = 'site', ?int $blog_id = null)`.
- Implement internal adapters mapping to `get_option` / `get_site_option` / `get_blog_option`.
- Documentation: add an “Option Scope” section (scopes, permissions, autoload limits). Explicitly discourage implicit detection; optionally allow header default (`RAN.OptionScope`) as opt-in.
- Migration: document a WP-CLI recipe for site→network migration (dry-run, progress, rollback notes).
