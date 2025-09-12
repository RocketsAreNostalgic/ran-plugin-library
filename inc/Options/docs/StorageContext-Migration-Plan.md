# StorageContext Migration Plan (Internal Options Refactor)

## Goals

- Consolidate scope resolution to a single, typed path anchored on `StorageContext` (PRIMARY).
- Remove `storage_args` entirely (no arrays for scope selection anywhere).
- Enforce a unified typed-first public API with direct parameters (no args arrays):
  - `Config::options(StorageContext $context = null, bool $autoload = true)`
  - `RegisterOptions::from_config(ConfigInterface $config, StorageContext $context = null, bool $autoload = true)`
- Make `StorageContext` the single source of truth for storage selection and write-gating context.
- Keep public APIs straightforward (factories, `from_config`, `Config::options`).

## Current State

- `RegisterOptions` uses:
  - `storage_scope` (enum|string|null)
  - `storage_args` (array with `blog_id`, `user_id`, `user_storage`, `user_global`)
- `_make_storage()` and `WriteContext::for_*` read from `storage_args`.
- `ScopeResolver::resolve()` returns `['scope' => OptionScope, 'storage_args' => array]`.
- Scope Entities (e.g., `BlogEntity`, `UserEntity`) return `toStorageArgs()` arrays.

## Target Architecture

- Introduce `StorageContext` (DONE):
  - Immutable, typed: `scope`, `blog_id`, `user_id`, `user_storage ('meta'|'option')`, `user_global (bool)`.
  - Static constructors: `forSite()`, `forNetwork()`, `forBlog(int)`, `forUser(int, string, bool)`.
- `RegisterOptions` stores and uses a typed `StorageContext` for:
  - `_make_storage()` → construct correct adapter.
  - All `WriteContext::for_*` calls.
- Factories and `_from_config()` set `$storage_context`.
- Mark as deprecated `storage_scope` and `storage_args` temporarily for back-compat/reflection tests (pre‑release: remove after tests migrate). - acceptance criteria, tests pass with no deprecation warnings, `storage_scope` and `storage_args` removed from `RegisterOptions`.

## Public Entry Points

- Unified signatures (identical parameter list semantics):
  - `RegisterOptions::from_config(ConfigInterface $config, StorageContext $context = null, bool $autoload = true)`
  - `Config::options(StorageContext $context = null, bool $autoload = true)`

Behavior:

- When `$context` is null, default to `StorageContext::forSite()`.
- No support for `scope`, `entity`, or `storage_args` in public APIs.

## Test Strategy (pre‑release updates allowed)

- Update storage factory tests to avoid stringly introspection; assert behavior via adapter type or `StorageContext`.
- Add minimal WP_Mock stubs (`get_option`, `get_site_option`, `get_user_option`, `get_user_meta`, `wp_load_alloptions`) in suites that construct via factories (since `_read_main_option()` runs on construction).
- Where tests reflect on `storage_scope`/`storage_args`, either:
  - Also set `storage_context` reflectively to match the intended scope, or
  - Prefer asserting the resulting adapter from `_make_storage()`.

## Phases

1. Consolidate Resolver (PRIMARY)

   - Public API is `StorageContext` + `autoload` only (direct params). `scope` and `entity` are removed from public APIs.
   - If a resolver utility remains, it must produce a `StorageContext` exclusively (no `storage_args`) and is for internal use only.
   - Remove `storage_args` from all code paths and tests.

2. Wire `RegisterOptions` end‑to‑end (IN PROGRESS)

   - Ensure factories and `_from_config()` set `$storage_context` only.
   - `_make_storage()` + `WriteContext::for_*` read exclusively from `$storage_context`.
   - Remove `storage_args`, `storage_scope`, and any references to `scope`/`entity` in public args.

3. Update tests (IN PROGRESS)

   - `Tests/Unit/Options/Storage/OptionStorageFactoryTest.php`
     - Add setUp() stubs for WP functions when using factories (construction triggers `_read_main_option()`).
     - Prefer asserting adapter type (via `_make_storage()` reflection) or reflecting on `StorageContext`.
   - `Tests/Unit/Options/ConfigOptionsScopeParityTest.php`
     - Add missing WP stubs so `_get_storage()` calls do not error.
     - Update parity checks to compare `StorageContext` (or adapter type) rather than `storage_args`.

4. Cleanup & Docs
   - Remove `storage_args`/`storage_scope` from `RegisterOptions` after tests are migrated.
   - Document the internal typed storage context in TFS.

## Notes

- Pre‑release: breaking internal changes are acceptable; refactor tests and expectations.
- Public API remains simple; internal representation is now strictly typed.
- BC: Pre-release — backward compatibility is NOT a requirement. We will update/refactor tests and internal callers as needed.

## Unified Usage Examples

```php
use Ran\PluginLib\Config\Config;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Options\Storage\StorageContext;

$config = Config::fromPluginFile(__FILE__);

// 1) Site (default) – omit context to use StorageContext::forSite() and autoload=true
$optsSiteA = $config->options();
$optsSiteB = RegisterOptions::from_config($config);

// 2) Blog – typed context only
$blogCtx   = StorageContext::forBlog(123);
$optsBlogA = $config->options($blogCtx);               // autoload=true
$optsBlogB = RegisterOptions::from_config($config, $blogCtx, true);

// 3) User (option, global) – typed context only, autoload disabled
$userCtx   = StorageContext::forUser(5, 'option', true);
$optsUserA = $config->options($userCtx, false);
$optsUserB = RegisterOptions::from_config($config, $userCtx, false);
```

Notes:

- No support for `scope`, `entity`, or `storage_args` in public APIs.
- Entities may still offer helpers (e.g., `toStorageContext()`), but callers pass a `StorageContext` directly.
