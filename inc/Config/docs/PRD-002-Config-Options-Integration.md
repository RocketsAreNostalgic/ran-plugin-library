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
  - Manages a single WP option (array/object), with helpers to read/write/merge/commit.

## Proposed Additions

### 1) Option key helper

Add to Config:

```php
public function get_options_key(): string;
```

- Returns `RAN.AppOption` if present; otherwise returns `Slug`.
- No I/O, just a deterministic key to be used with Options.

### 2) First-class Options accessor (typed StorageContext)

Add to Config:

```php
use Ran\PluginLib\Options\Storage\StorageContext;

public function options(StorageContext $context = null, bool $autoload = true): \Ran\PluginLib\Options\RegisterOptions;
```

- Returns a `RegisterOptions` instance pre-wired to the app’s option key.
- `StorageContext` determines scope (site/network/blog/user); when `null`, defaults to site.
- No implicit writes; just the manager instance.

### 3) Options convenience constructors

Use `Config::options()` as the constructor-like convenience.

- It returns a `RegisterOptions` instance already bound to `$cfg->get_options_key()`.
- Prefer this over adding additional `RegisterOptions` convenience constructors.

```php
use Ran\PluginLib\Options\Storage\StorageContext;

// Defaults to site scope
$opts = $config->options();

// Or pick a scope explicitly
$opts = $config->options(StorageContext::forNetwork());
```

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

### 6) Options Parameters (typed, no arrays)

`Config::options()` accepts a typed `StorageContext` and an `autoload` flag (no implicit writes).

```php
use Ran\PluginLib\Options\Storage\StorageContext;

public function options(StorageContext $context = null, bool $autoload = true): \Ran\PluginLib\Options\RegisterOptions;
```

- `StorageContext` selects scope: `forSite()`, `forNetwork()`, `forBlog(int)`, `forUser(string $storage, bool $global)`, `forUserId(int $user_id, string $storage, bool $global)`.
- `autoload` is a policy hint used at new-row creation; does not write by itself.

Notes:

- **No implicit writes** occur in `Config::options()`.
- Operational helpers (e.g., flipping autoload, schema registration, defaults, flushing) are performed on the returned `RegisterOptions` instance via its fluent API.

Autoload implementation details:

- WordPress option APIs expect autoload as strings `'yes'` or `'no'` (not booleans). The library adheres to this when calling `set_option()` and `stage_option()`.
- Initial creation respects the configured autoload policy: when constructing `RegisterOptions` with `$main_option_autoload = false` and persisting (via defaults/initials + flush), the stored row is created with autoload `'no'`.

### Examples

Keep defaults (no writes):

```php
use Ran\PluginLib\Options\Storage\StorageContext;

$opts = $config->options();                       // site, autoload=true
$opts = $config->options(StorageContext::forSite());
```

Set autoload policy for new row only (no writes):

```php
use Ran\PluginLib\Options\Storage\StorageContext;

$opts = $config->options(StorageContext::forSite(), false);
```

Flip autoload on the returned manager (guarded delete + add):

```php
$opts = $config->options();
$opts->set_main_autoload(false); // guarded; may write if row exists and differs
```

### Schema & Migration

```php
$opts = $config->options();
$opts->with_schema([
    'enabled' => ['default' => true],
    'timeout' => ['default' => 30, 'validate' => 'is_numeric']
]);

$opts->migrate(function($current, $manager) {
    if (version_compare($current['version'] ?? '0.0.0', '2.0.0', '<')) {
        $current['new_field'] = 'default_value';
    }
    return $current;
});
$opts->commit_replace();
```

Network scope (multisite):

```php
use Ran\PluginLib\Options\Storage\StorageContext;

$opts = $config->options(StorageContext::forNetwork());
```

Blog scope:

```php
use Ran\PluginLib\Options\Storage\StorageContext;

$opts = $config->options(StorageContext::forBlog(123));
```

User scope:

```php
use Ran\PluginLib\Options\Storage\StorageContext;

$opts = $config->options(StorageContext::forUserId(123, 'option', true));
```

User settings (profile fields):

```php
use Ran\PluginLib\Options\Storage\StorageContext;
use Ran\PluginLib\Settings\UserSettingsRegistry;

// Register at plugin load time; user_id is resolved later from WordPress profile hooks.
$config->settings(StorageContext::forUser())->register(function (UserSettingsRegistry $s) {
    $s->collection('my-user-settings')
        ->heading('My User Settings')
        ->on_render(function ($c) {
            $c->section('my-section', 'My Section')
                ->field('my_field', 'My Field', 'fields.input')
                ->end_field()
            ->end_section();
        });
});
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
$current = $opts->get_options();
```

### Theme

```php
$config = Config::fromThemeDir(get_stylesheet_directory());
$opts   = $config->options();
$slug   = $config->get_options_key();
$current = $opts->get_options();
```

### Reading values and a single field

```php
$opts = $config->options();

// Full values array (recommended to read all current values)
$values = $opts->get_options();

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
- `RegisterOptions` exposes `seed_if_missing()` and `migrate()`.
- README updated with examples for plugins and themes.
- No implicit DB writes during hydration; all writes occur via Options helpers.

## Feature Development (Target 0.1.3)

Non-breaking expansion to add explicit option scope support. See PRD-003-Options-Scope-and-Multisite.md.

- `Config::options()` accepts a typed `StorageContext` to select scope.
- `RegisterOptions` uses internal adapters mapping to `get_option` / `get_site_option` / `get_blog_option` and user storage.
- Documentation: add an “Option Scope” section (scopes, permissions, autoload limits). Explicitly discourage implicit detection; optionally allow header default (`RAN.OptionScope`) as opt-in.
- Migration: document a WP-CLI recipe for site→network migration (dry-run, progress, rollback notes).
