# Options System – Complexity and Maintainability Report

This report evaluates developer comprehension and maintenance risks for the Options subsystem centering on `inc/Options/RegisterOptions.php` and its collaborators in `inc/Options/`.

## Overview

- The subsystem stores all plugin options as a single array under one main option key, with explicit persistence via `flush()`.
- Scope-aware storage is abstracted via adapters in `inc/Options/Storage/` implementing `OptionStorageInterface`.
- Write operations are guarded by an immutable policy plus filter-based gates with rich `WriteContext` payloads.
- Logging is integrated (via `ConfigInterface::get_logger()`) and used extensively for diagnostics.

Key entry points and collaborators:

- `RegisterOptions` (primary orchestrator): `inc/Options/RegisterOptions.php`
- Storage adapters: `inc/Options/Storage/{Site,Network,Blog,UserMeta,UserOption}OptionStorage.php`
- Scope enum: `inc/Options/OptionScope.php`
- Write context: `inc/Options/WriteContext.php`
- Policy: `inc/Options/Policy/RestrictedDefaultWritePolicy.php` implementing `WritePolicyInterface`

## Complexity Hotspots

- **Constructor + Factories (choice overload):**

  - `protected function __construct()` with multiple named factories: `site()`, `network()`, `blog()`, `user()` and a flexible `from_config()`.
  - Developers may be unsure whether to prefer named factories or `from_config($config, $autoload, $scope, $storage_args)`.
  - Location: `RegisterOptions::from_config()` in `inc/Options/RegisterOptions.php` (around lines 274–321).

- **Scope/Args encoding is stringly-typed:**

  - `_make_storage()` expects specific keys in `$this->storage_args` (`blog_id`, `user_id`, `user_storage`, `user_global`), validated at runtime.
  - Errors surface late (runtime exceptions), IDE hinting is limited, and it’s easy to pass invalid combinations.
  - Location: `RegisterOptions::_make_storage()` in `inc/Options/RegisterOptions.php` (around lines 955–1000).

- **In-memory vs persistence behavior differs across methods:**

  - `set_option()`/`update_option()` persist immediately.
  - `add_option()`/`add_options()` only mutate memory; require `flush()` to persist.
  - Although documented, this can surprise developers who forget to call `flush()`.
  - Locations: `set_option()` (around 506–576), `add_options()` (around 579–612), `add_option()` (around 615–650), `flush()` (around 737–755).

- **Shallow merge semantics require careful reading:**

  - `register_schema()` performs per-key shallow rule merges; default seeding replaces entire values for missing keys.
  - `flush(true)` performs a top-level shallow merge with DB; deep merges require manual read–modify–write then `flush(false)`.
  - These rules are correct but nuanced; easy to miss if the header docs aren’t read closely.
  - Locations: `register_schema()` (around 381–456), `flush()`/`_save_all_options()` (around 753–1239).

- **Strict no-op guards may be surprising:**

  - `set_option()` uses `===` (strict) equality; arrays must match exactly (order/keys) and objects must be the same instance to avoid a write.
  - This is precise but may not match developer expectations (e.g., deep value equality).
  - Location: `set_option()` (around 512–541).

- **Write-gating method is long and verbose:**

  - `_apply_write_gate()` applies immutable policy, general filter, then scoped filter; logs heavily.
  - Correct but dense; the ordering is non-obvious without reading the method or accompanying docs.
  - Location: `RegisterOptions::_apply_write_gate()` (around 1002–1137).

- **WP wrappers hide behavior:**

  - `WPWrappersTrait` methods (`_do_get_option`, `_do_add_option`, `_do_update_option`, `_do_apply_filter`, `_do_get_current_blog_id`) abstract WordPress internals.
  - Great for testing but obscures exact WP API interactions for new developers.

- **Coverage-related comment distracts:**
  - `_make_storage()` contains a comment about avoiding `switch` because of PHPUnit coverage recognition.
  - This is an internal testing artifact that may confuse maintainers.

## Maintenance Risks

- **API surface sprawl:** Multiple construction patterns (named factories vs `from_config` with scope/args) can encourage inconsistent usage across plugins.
- **Runtime-only validation:** Incorrect `storage_args` combos fail late; refactors may cause incidental behavior changes.
- **Brittle logging contracts in tests:** Tests that assert exact message sequences and wording can fail on benign logging changes, increasing change cost.
- **Deep merge handled ad hoc by callers:** Recommending caller-implemented deep merges can lead to inconsistent behavior and duplicated logic.

## Recommendations

### Quick Wins (docs and guidance)

- **Add a short "Choosing an entry point" section** in `inc/Options/readme.md` and class docs:

  - Recommend `RegisterOptions::from_config($config, autoload: true, scope: ..., storage_args: ...)` as the primary constructor.
  - Keep `site()`, `network()`, `blog()`, `user()` as convenience alternatives, but de-emphasize in examples.

- **Add a "Memory vs Persistence" table**:

  - `set_option()` / `update_option()` → persists immediately.
  - `add_option()` / `add_options()` → in-memory only; call `flush([merge_from_db])`.

- **Add a "Shallow vs Deep merge cheat sheet"** with code snippets:

  - Top-level shallow merge: `flush(true)`.
  - Deep merge: read–modify–write + `flush(false)`.

- **Clarify strict equality rationale** in `set_option()` docs:
  - Explain the no-op guard and include guidance for deep-equality comparisons where needed.

### Low-risk structural improvements

- **Prefer the existing typed ScopeEntity flow via `Config::options()`**:

  - Use `Config::options(array $args)` with:
    - `scope`: `'site'|'network'|'blog'|'user'` or `OptionScope`
    - `entity`: a `ScopeEntity` implementation (e.g., `BlogEntity`, `UserEntity`) where required
  - `ScopeEntity::toStorageArgs()` produces validated storage arguments for `RegisterOptions::from_config()` (avoids stringly-typed `storage_args`).
  - Cross-reference: `inc/Config/Config.php::options` (docblock explains semantics and recognized args).
  - Benefits: IDE-friendly, validated earlier, fewer runtime surprises, consistent across call sites.

- **Add a minimal façade for the common path**:

  - e.g., `RegisterOptions::for_site(ConfigInterface $config)` returning a pre-scoped instance with sensible defaults.
  - Reduces friction for typical usage while preserving full power for advanced cases.

- **Extract `_apply_write_gate()` to a collaborator** (optional):

  - A small `WriteGate` helper or an internal private method pair (e.g., `_apply_general_filter()`, `_apply_scoped_filter()`) would reduce method complexity without altering behavior.

- **Remove or rephrase the coverage comment** in `_make_storage()`:
  - Keep the current branching; remove testing-artifact commentary to reduce cognitive noise.

### Optional (if growth continues)

- **Separate schema concerns** into a small `SchemaManager`:

  - Owns normalize/seed/sanitize/validate steps. Keeps `RegisterOptions` smaller and more focused.

- **Provide helper utilities for common deep merges** or a reference implementation:
  - Encourages consistent, predictable merging across plugins.

## Notable Specifics (for reference)

- `RegisterOptions::from_config()` – `inc/Options/RegisterOptions.php` (274–321)
- `RegisterOptions::_make_storage()` – `inc/Options/RegisterOptions.php` (955–1000)
- `RegisterOptions::_apply_write_gate()` – `inc/Options/RegisterOptions.php` (1002–1137)
- `RegisterOptions::set_option()` – `inc/Options/RegisterOptions.php` (506–576)
- `RegisterOptions::add_options()` – `inc/Options/RegisterOptions.php` (579–612)
- `RegisterOptions::add_option()` – `inc/Options/RegisterOptions.php` (615–650)
- `RegisterOptions::flush()` – `inc/Options/RegisterOptions.php` (737–755)
- Storage adapters – `inc/Options/Storage/*.php`
- Scope enum – `inc/Options/OptionScope.php`
- Write context – `inc/Options/WriteContext.php`

## Prioritized Actions

1. Update `inc/Options/readme.md` and class-level docs with:
   - Constructor guidance (prefer `from_config`), memory vs persistence table, shallow vs deep merge cheat sheet, strict equality rationale.
2. Document and recommend the `ScopeEntity` + `Config::options()` pathway as the typed, validated way to provide scope + entity (link to `inc/Config/Config.php::options`).
3. Remove/rephrase the coverage comment in `_make_storage()`.
4. Consider extracting `_apply_write_gate()` logic if contributors find it hard to follow during reviews.

## Alignment with PRD-003 and Logger Integration

- Write-gating matches PRD-003’s per-scope validation hooks and documented context payload fields.
- Autoload rationale (site scope only) is correctly enforced via the storage adapter’s capabilities (`supports_autoload()`), surfaced through `RegisterOptions::supports_autoload()`.
- Logger integration via `ConfigInterface::get_logger()` (and lazy binding in `RegisterOptions::_get_logger()`) is consistent with the Config and Logger design.
