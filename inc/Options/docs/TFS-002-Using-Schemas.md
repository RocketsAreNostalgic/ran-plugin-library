# TFS-002 — Using Schemas with RegisterOptions

## Purpose

Explain how to use schemas with `RegisterOptions` safely and predictably, with no implicit writes. This complements `inc/Options/readme.md` and the Config integration in `inc/Config/docs/PRD-002-Config-Options-Integration.md`.

## Key Principles

- **No implicit writes**: Registering a schema does not write to the DB by itself.
- **Separation of concerns**: `Config::options()` returns a pre-wired manager; persistence is always explicit on `RegisterOptions`.
- **Single source of truth**: Autoload policy and option key come from `Config` unless you intentionally construct options directly.

These principles also inform method behavior in `RegisterOptions` (see method-level notes and TFS‑001).

## Applying a schema via RegisterOptions (fluent)

```php
$schema = [
  'enabled' => ['default' => true,  'validate' => fn($v) => is_bool($v)],
  'timeout' => ['default' => 30,    'validate' => fn($v) => is_int($v) && $v >= 0],
];

$opts = $config->options(); // no writes
$opts->register_schema($schema, false, false); // register only; no seed, no flush
```

- Rationale: Developers can wire validation early (read-only boot), then choose if/when to persist via explicit helpers.

## Common Flows

- **Read-only setup (boot/init; no DB writes)**

```php
$opts = $config->options();
$opts->register_schema($schema, false, false);
$values = $opts->get_values(); // validated reads; still no writes
```

- **Activation-time seeding (first install)**

```php
$opts = $config->options();
$opts->register_schema($schema, /* seed */ true, /* flush */ true); // backfill defaults and persist once
```

- **Guarded first-write (idempotent)**

```php
$opts = $config->options();
$opts->seed_if_missing(['enabled' => true, 'timeout' => 30])->flush();
```

- **Update/expansion of schema**

```php
$opts = $config->options();
$opts->register_schema($schema, /* seed */ true, /* flush */ true); // safely backfill new fields
```

- **Batch updates (recommended)**

```php
$opts = $config->options();
$opts
  ->add_options([
    'enabled' => ['value' => true],
    'timeout' => 45,
  ])
  ->flush(); // single DB write
```

## Schema callbacks contract

- Sanitization

  - Signature: `callable(mixed $value): mixed`
  - Purpose: return a cleaned value; may throw on unrecoverable input
  - Behavior: If present and callable, it is invoked before validation

- Validation

  - Signature: `callable(mixed $value): bool`
  - Return: must return `true` for success. Any non-true result triggers an `InvalidArgumentException`
  - May throw: Exceptions thrown by validators bubble to the caller (not caught internally)

- Exception behavior and partial-state notes

  - The library does not catch exceptions from `sanitize`/`validate`; they bubble to the caller
  - Atomic flows (no partial in-memory state on failure):
    - `seed_if_missing([...])`
    - `migrate($fn)`
    - These first build a normalized snapshot; on any exception, nothing is mutated or persisted
  - Per-key seeding flow:
    - `register_schema($schema, seed_defaults: true, flush: <bool>)` seeds each missing key in a loop
    - If a callback throws mid-loop, some prior keys may already be staged in memory (no DB write unless `flush: true`)
    - Best practice: either use `seed_if_missing()` for first-writes, or wrap the call site in try/catch if you need to handle errors centrally

- Return type expectations
  - Sanitizers should return the exact type you expect to store
  - Validators should strictly enforce type/range and return `true` only on success
  - Example:

```php
'enabled' => [
  'default'  => false,
  'sanitize' => fn($v) => is_bool($v) ? $v : (bool) $v,
  'validate' => fn($v) => is_bool($v),
],
```

## Why pass a schema without seeding?

- **Validation without persistence**: Ensure shape and sanitization for reads and future writes.
- **Safe bootstrap**: Avoid accidental writes in early boot stages or on front-end requests.
- **Tooling**: Drive admin UIs, docs, and type expectations from a single schema.
- **Future scope reuse**: The same schema can apply to multiple scopes without redefinition.

## Autoload behavior

- Autoload is applied only on creation by WordPress (e.g., via `seed_if_missing(...)->flush()` or `register_schema(..., true, true)`).
- WP 6.6+: when adding an option, a nullable autoload hint (`?bool`) is accepted; passing `null` defers to WordPress heuristics. Updates do not change autoload.
- For manual autoload flipping, use WordPress core functions directly (delete + add with a bool|null autoload where supported).
- Performance note: when autoload is supported, WordPress may preload options globally (e.g., via `wp_load_alloptions()`). On large sites this can be heavy. The library does not implicitly load all options; prefer targeted reads and cache results at your call site. Use `supports_autoload()` to detect capability.

## Scope note (forward-looking)

- For scope-aware options (site/network/blog), see `inc/Config/docs/PRD-003-Options-Scope-and-Multisite.md`.
- In PRD‑002, scope args are deferred; schema behavior remains the same.

## FAQ

- **Does `Config::options()` write to the DB?** No. It is a no-write accessor; use the fluent API on the returned `RegisterOptions` to register schema, seed defaults, and flush.
- **Do I still need `register_schema()`?** Yes, if you want to seed or persist: call `register_schema($schema, true, true)` or use `seed_if_missing(...)->flush()`.
- **Can I define schema after construction?** Yes:

```php
$opts = $config->options();
$opts->register_schema($schema, seed_defaults: true, flush: true);
```

## Testing Tips

- Use `seed_if_missing()` for idempotent first-writes; assert autoload state via `$wpdb->options` when needed.
- Verify “no implicit writes” by asserting no row exists after `options(['schema'=>...])` and after `register_schema($schema, false, false)`.
