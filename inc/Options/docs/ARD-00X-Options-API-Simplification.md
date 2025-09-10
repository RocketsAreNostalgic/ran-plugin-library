---
# Architecture Rationale Document: Options API Simplification

- Title: Options API Simplification (from_config slimming, Config::options 80/20)
- Owner: PluginLib Team
- Status: Draft
- Date: 2025-09-08
---

## Problem Statement

`RegisterOptions::from_config(...)` currently accepts many parameters (initial, autoload, logger, schema, scope, storage_args, policy). This creates multiple ways to achieve the same setup and increases cognitive load. In parallel, we have fluent methods (`with_schema`, `with_defaults`, `with_policy`, `with_logger`) that are better suited for expressing optional/custom configuration.

`Config::options(array $args)` is becoming a catch‑all for wiring many knobs, pushing complexity into the configuration layer.

We want to set an ideal pattern before release: provide an opinionated, simple entry point for the 80/20 case via `Config::options()`, and use fluents for the remaining 20% of customization.

## Goals (80/20 entry point)

- Provide a site‑scoped `RegisterOptions` instance by default.
- Sensible defaults:
  - `autoload = true` on first create (site scope)
  - default write policy (`RestrictedDefaultWritePolicy`)
  - logger from `Config`
- Optionally register a schema and (optionally) seed defaults, with explicit flush only when requested (preserve "no implicit writes").
- Keep constructor/factory parameter lists minimal and predictable.

## Non‑Goals

- No breaking changes to released versions (this branch is unreleased; we can adjust now).
- No removal of fluent methods.
- No change to named scope factories (`site()`, `network()`, `blog()`, `user()`).

## Current State (References)

- `RegisterOptions::from_config()` [inc/Options/RegisterOptions.php:L331-L378]
- `RegisterOptions::with_*` fluents [inc/Options/RegisterOptions.php: around L381‑L414]
- `Config::options(array $args)` [inc/Config/Config.php]

## Decision Update (2025-09-08)

- We decided that `Config::options()` will NOT accept or apply `schema`, `seed_defaults`, or `flush`.
- Those concerns must be configured via fluent methods on the returned `RegisterOptions` instance (e.g., `with_schema(..., seed_defaults, flush)`).
- Rationale: keep `Config::options()` as a no-write accessor and avoid implicit writes/side-effects at the factory boundary.

## Proposal

1. Slim `RegisterOptions::from_config(...)` to construction‑centric concerns:

   - Keep: `ConfigInterface $config`, `bool $autoload = true`.
   - Keep: scope + storage args only if they affect initial read (e.g., site/blog/user selection), but discourage passing other knobs.
   - Discourage: **passing** `initial`, `logger`, `schema`, `policy` via `from_config` (document as discouraged; prefer fluents). No implicit writes.

2. Make `Config::options(array $args = [])` the opinionated 80/20 entry point:

   - Supported args (small, focused):
     - `autoload` (bool, default: true)
     - `scope` (null|'site'|'network'|'blog'|'user' or `OptionScope` enum)
     - `entity` (`ScopeEntity|null`) — used when relevant for `blog`/`user` scopes
   - Implementation guidance:
     - Construct with `from_config($config, autoload=..., scope=..., storage_args=...)` (minimal subset only)
     - Immediately chain only:
       - `.with_logger($this->get_logger())`
     - Do NOT apply schema/defaults/policy here; callers use fluents on the returned instance
     - Return the instance

3. Use fluents for the 20% use cases:

   - `.with_policy($policy)`
   - `.with_defaults($defaults)` (or use mutators + `flush()`)
   - `.with_schema(...)` for post‑construction/migrations
   - `.with_logger(...)` to override

4. Documentation & Guidance
   - Update phpdoc on `from_config()` to mark non-construction params as discouraged and point to fluents.
   - Document `Config::options()` as the recommended no-write entry for most plugins.
   - Emphasize "no implicit writes" at the factory; seeding/flush occur only via fluents on `RegisterOptions`.

## Timing & Sequencing Clarification

**Concern**: After removing extra params from Config/constructor, is it "too late" to call fluent methods like `with_schema()`, `with_policy()`?

**Answer**: No, fluent configuration is safe post-construction for most concerns:

- **Logger** (`with_logger`): Consulted at operation time; can be set anytime before logging occurs.
- **Policy** (`with_policy`): Evaluated at write-gate time; can be set anytime before first write operation.
- **Schema** (`with_schema`): Applied when values are set/seeded; can be called post-construction with `seed_defaults` + `flush` flags.

**Construction-time only**:

- **Scope/storage args**: Determine initial option read and storage adapter selection.
- **Autoload**: Only honored on first `add_option()` call; must be set at construction.

**Implementation strategy**: `Config::options()` constructs minimal instance, then immediately chains only the logger before returning:

```php
public function options(array $args = []): RegisterOptions {
    $opts = RegisterOptions::from_config(
        $this,
        autoload: $args['autoload'] ?? true,
        scope: $args['scope'] ?? null
        // storage args derived from `entity` when relevant
    );
    // Bind logger only; no schema/defaults/policy here
    $opts = $opts->with_logger($this->get_logger());
    return $opts;
}
```

This avoids the "too late" problem while encouraging fluent patterns for caller customization.

## Example Usage (Proposed)

```php
// 80/20: simple site options accessor (no writes)
$opts = $config->options([
  'autoload' => true,
  'scope'    => 'site', // default
]);

// Apply schema/defaults explicitly via fluents on the returned instance
$opts
  ->with_schema([ 'feature_flag' => ['default' => false] ], seed_defaults: true, flush: true)
  ->with_policy($customPolicy)
  ->with_defaults(['another_key' => 'val'])
  ->flush();
```

## Backwards Compatibility

- Unreleased branch: safe to adjust APIs and guidance.
- Maintain `from_config()` signature temporarily (soft deprecate some params via docblock) to avoid touching all tests immediately.
- Migrate tests gradually to prefer `Config::options()` and fluents.

## Risks & Mitigations

- Risk: Divergence between doc guidance and what `from_config()` accepts.
  - Mitigation: phpdoc warnings; unit tests/examples consistently use fluents.
- Risk: Confusion around implicit writes.
  - Mitigation: keep default `seed_defaults=false`, `flush=false`; call out in docs.

## Work Plan

- Phase 1 (Docs & Guidance)

  - Update docblocks in `from_config()` to discourage non‑construction params.
  - Add guide page with the usage pattern above.

- Phase 2 (Config::options tightening)

  - Limit accepted args to the small set listed above; warn on unknown args (already present in `Config::options`).
  - Implement chaining `.with_logger()` only (no schema/defaults/policy at the factory).

- Phase 3 (Tests & Examples)
  - Update tests to construct via `Config::options()` for common cases.
  - Use fluents for policy/defaults in tests, reducing reliance on `from_config()` parameter soup.

## Acceptance Criteria

- `Config::options()` returns a site‑scoped `RegisterOptions` (by default) with logger bound; it performs no writes.
- Schema/defaults/policy are configured exclusively via fluents on `RegisterOptions`; persistence occurs only when `flush()` is called on that instance.
- `from_config()` docs discourage passing `initial`, `logger`, `policy`, `schema` directly; examples show fluents.
- Tests and examples reflect the streamlined, no-write factory pattern.
