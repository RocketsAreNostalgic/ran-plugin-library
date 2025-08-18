# PRD: Batteries-Included Config + Options Integration

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
  - `get_options()` returns the full option payload for the app’s option key (or default if missing).
  - `get_logger()`, `is_dev_environment()` implemented.

- Options (`RegisterOptions`)
  - Manages a single WP option (array/object), with helpers to read/write/merge/flush.

## Proposed Additions

### 1) App option key helper

Add to Config:

```php
public function get_app_option_key(): string;
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

- Initializes a `RegisterOptions` bound to `$cfg->get_app_option_key()`.

### 4) Seeding helper (activation-time)

Add to `RegisterOptions`:

```php
public function seed_if_missing(array $defaults): self;
```

- On activation, creates the option row if it does not exist, with `$defaults`.
- Idempotent: no write if the option already exists.

### 5) Migration hook (optional)

Add to `RegisterOptions`:

```php
public function migrate(callable $migration): self;
```

- Executes user-provided migration logic to transform stored data (e.g., on version bump).
- Caller is responsible for version checks and idempotency.

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
$current = $opts->get_option($config->get_app_option_key(), []);
```

### Theme

```php
$config = Config::fromThemeDir(get_stylesheet_directory());
$opts   = $config->options();
$slug   = $config->get_app_option_key();
$current = $opts->get_option($slug, []);
```

## Design Notes

- Separation of concerns: Config never writes options; Options manages persistence.
- Ergonomics: a single call (`options()`) provides a correctly wired `RegisterOptions`.
- Safety: `seed_if_missing()` avoids unintended overwrites; migrations are explicit and opt-in.
- Extensibility: schemas, validation, autoload, and complex merges remain the domain of `RegisterOptions` and can be layered in without changing Config.

## Risks & Mitigations

- Risk: Tighter coupling perceived between Config and Options.
  - Mitigation: Accessor only; Options remains independently usable.
- Risk: Confusion between `get_options()` (payload) and `options()` (manager).
  - Mitigation: Document clearly; `get_options()` returns the stored value, `options()` returns the manager.

## Acceptance Criteria

- Config exposes `get_app_option_key()` and `options()`.
- `RegisterOptions` exposes `fromConfig()`, `seed_if_missing()`, and `migrate()`.
- README updated with examples for plugins and themes.
- No implicit DB writes during hydration; all writes occur via Options helpers.

## Open Questions

- Should `options()` accept optional arguments (e.g., autoload strategy) for immediate configuration?
- Do we want a small `get_option_field(string $key, mixed $default = null)` convenience on Config that reads from the stored payload?
