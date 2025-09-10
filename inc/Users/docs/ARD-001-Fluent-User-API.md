# ARD-004: Fluent User API (RegisterOptions-backed)

## Summary

A fluent, cohesive User API that standardizes user creation and per-user plugin settings using the library's `RegisterOptions` system. The API emphasizes dependency injection for logging, clear validation, and a builder-style interface for optional attributes.

## Goals

- Provide a fluent API to create users and configure per-user plugin options.
- Centralize per-user plugin settings via `RegisterOptions` (user scope) rather than ad‑hoc meta writes.
- Support storage backend selection for user options (`meta` vs `option`) and network-global semantics.
- Support optional Logger DI for consistent logging across the Users subsystem.
- Keep user creation and user options concerns separate but composable in a single flow.

## Non-Goals

- Do not replace WordPress core user functions beyond safe wrappers.
- Do not provide a generic arbitrary user meta writer (removed pre-release).
- Do not implement complex notification workflows beyond a simple toggle.

## Current State

- `InsertUserDataAbstract::insert_user()` provides a safe wrapper around `wp_insert_user()`.
- Arbitrary user meta writer (`insert_user_metta`) and `InsertUserMetaInterface` were removed pre-release.
- `UserOptionsStore` exists and uses `RegisterOptions` under the hood to provide a focused store API for per-user settings.

## Problem Statement

The Users subsystem needs a consistent, fluent interface that matches the ergonomics of `RegisterOptions`. Consumers should be able to:

- Create a user with the minimal required input
- Optionally set profile attributes
- Optionally configure this user's plugin-scoped settings within the same builder flow (kept separate from core user profile fields). These settings are namespaced and managed via `RegisterOptions` in user scope, not commingled with WordPress core user data.
- Rely on consistent logging via DI

## Terminology (Scope vs. Settings)

- **Core user profile data**

  - WordPress-managed fields stored in `wp_users` / `wp_usermeta` (via WP APIs): email, login, password, role, first/last name, etc.
  - Managed by this API through safe wrappers around `wp_insert_user()` and (optionally) a controlled update path.

- **Plugin-scoped per-user settings**
  - Your plugin/theme’s namespaced configuration for a specific user, managed by `RegisterOptions` in user scope.
  - Persisted as a single options array under the plugin’s main options key from `ConfigInterface::get_options_key()`.
  - Storage backends:
    - `user_storage = 'meta'` (default):
      - Table: `wp_usermeta`
      - Key: `meta_key = <Config::get_options_key()>`
      - Example: `meta_key = 'my_plugin_options'` for user_id 42
    - `user_storage = 'option'`:
      - Table: `wp_usermeta` (WordPress per-user options are stored as user meta with prefixes)
      - Key: `meta_key = <wp_prefix><Config::get_options_key()>`, where `<wp_prefix>` is site- or network-derived
      - Example (illustrative): `meta_key = 'wp_7_my_plugin_options'` (site) or `meta_key = 'wp_my_plugin_options'` (network/global)
  - These settings are intentionally separate from core user profile fields and should not be commingled.

## Proposed Design

Introduce a fluent `User` builder class and a `UserResult` value object:

- Namespace: `Ran\PluginLib\Users`.
- DI: `ConfigInterface` (required), `?Logger` (optional) in constructor.
- Minimal required input: `email`.
- Derived defaults: `user_login = strtolower(email)`, password auto-generated, role = `subscriber`, notify = false.
- Optional fluent setters for profile and behavior.
- First-class support for per-user plugin options via `RegisterOptions` user scope (with `UserEntity`).

### Class: `User`

```php
final class User {
    public function __construct(ConfigInterface $config, ?Logger $logger = null);

    // Required
    public function email(string $email): self;

    // Optional profile
    public function login(string $login): self;               // override derived from email
    public function name(string $first, string $last): self;
    public function role(string $role = 'subscriber'): self;
    public function password(string $password): self;
    public function generatePassword(): self;                 // mark to auto-generate if none provided
    public function notify(bool $send = true): self;          // send WP welcome email

    // Plugin-scoped options (RegisterOptions)
    public function userScope(bool $global = false, string $storage = 'meta'): self; // 'meta'|'option'
    public function options(array $kv): self;                 // array<string, mixed>
    public function schema(array $schema, bool $seedDefaults = false, bool $flush = false): self;
    public function withPolicy(WritePolicyInterface $policy): self;

    // Execute
    public function create(): UserResult;                     // performs wp_insert_user, applies options
}
```

### Class: `UserResult`

```php
final class UserResult {
    public function __construct(
        public readonly int $id,
        public readonly string $email,
        public readonly string $login,
        public readonly bool $created,
        public readonly array $messages = []
    ) {}
}
```

## Behavioral Details

- **Required Input**

  - `email` must be provided and valid. `login` defaults to lowercased email unless overridden.

- **Defaults**

  - `role`: `subscriber`
  - `notify`: false
  - `password`: auto-generated if not set (WP generates if omitted)

- **Options Storage**

  - `userScope(global, storage)` controls `RegisterOptions` user scope via `UserEntity`:
    - storage = `meta` (default) → `wp_usermeta`
    - storage = `option` → per-user key in `wp_options`
    - `global = true` only meaningful with storage `option` (network-wide)

- **RegisterOptions Integration**

  - On `create()`, if `options()` was provided, the builder will:
    - Instantiate `RegisterOptions` via `$config->options(['scope' => 'user', 'entity' => new UserEntity($id, $global, $storage)])`
    - Optionally `with_logger($logger)` if provided
    - Optionally `with_policy($policy)` if provided
    - Optionally `register_schema($schema, $seedDefaults, $flush)` if provided
    - Apply `add_options($kv)` and `flush(true)` if any options were queued

- **Logging**

  - Optional `Logger` DI.
  - Log at key steps: input validation, pre-insert, insert result, applying schema/options, and flush.
  - The same logger is bound to `RegisterOptions`.

- **Error Handling**

  - Fail fast on invalid inputs (email, storage selection, etc.).
  - If `wp_insert_user()` returns `WP_Error`, throw an Exception with message propagated.
  - `RegisterOptions` operations already enforce write gates and report failures.

- **Existing User Handling**

  - Detection: prior to `create()`, check for existing users by email (and optionally by login if explicitly provided).
  - Policy (fluent): `onExists('attach' | 'fail' | 'update-profile')`
    - `attach` (default): do not change core profile fields; attach and apply plugin-scoped options via `RegisterOptions` for that user id.
    - `fail`: throw an exception indicating a user already exists (include email/login context).
    - `update-profile`: update an allowlist of core fields (e.g., first/last name, role) and then attach/apply plugin-scoped options. Enforce write-policy as needed.
  - Logging: log which policy path was taken and the outcome.

- **Create Flow**
  1. Validate inputs (email required; login, role, etc. optional with defaults).
  2. Detect existing user; handle per `onExists` policy.
  3. If creating:
     - Build `wp_insert_user` args (login derived from email unless overridden; password generated if omitted; role default 'subscriber'; notify toggle respected).
     - Call `wp_insert_user`; throw on `WP_Error`.
  4. If options were provided:
     - Instantiate `RegisterOptions` with `UserEntity($id, $global, $storage)` using `Config::options()`.
     - Bind logger and optional write policy.
     - Optionally `register_schema($schema, $seedDefaults, $flush)`.
     - `add_options($kv)` and `flush(true)` for batch save.

## Alternatives Considered

- Continue using `UserOptionsStore` only.

  - Pros: Simple, already available.
  - Cons: Less fluent and cohesive for the full create + configure flow, diverges from RegisterOptions’ style.

- Keep arbitrary `insert_user_meta` API.
  - Rejected: encourages scattered meta keys; RegisterOptions provides a cohesive, validated model.

## Testing Strategy

- Unit tests for `User` builder:
  - Minimal create: only `email` → user created, defaults applied.
  - Options meta storage: `userScope(false, 'meta')` + `options([...])` → verify `get_user_meta` path.
  - Options option storage: `userScope(true, 'option')` + `options([...])` → verify `get_user_option`/`update_site/user_option` path.
  - Schema registration with seed/flush.
  - Policy binding via `withPolicy()`.
  - Logging paths (mock Logger `is_active()` and verify messages at key points).

## Remaining Work (Prioritized)

1. Implement `Ran\\PluginLib\\Users\\User` builder and `UserResult` value object

   - Constructor DI: `ConfigInterface`, optional `Logger`
   - Fluent setters: `email()`, `login()`, `name()`, `role()`, `password()`/`generatePassword()`, `notify()`
   - Options integration: `userScope(global=false, storage='meta')`, `options(array)`, `schema(array, seedDefaults, flush)`, `withPolicy(WritePolicyInterface)`
   - Terminal: `create()` with detection + `onExists('attach'|'fail'|'update-profile')`

2. Implement `onExists` policy paths

   - attach (default): no profile mutation; apply plugin-scoped options
   - fail: throw with context
   - update-profile: update allowlisted fields (first/last/role) with write-policy checks

3. Bind logging consistently

   - Use injected `Logger` at key stages (validation, detection, insert, options, flush)
   - Bind `Logger` to underlying `RegisterOptions` via `with_logger()`

4. Unit tests for `User` builder

   - Minimal create (email only) → user created with defaults
   - Existing user: attach, fail, update-profile
   - Options persistence for `meta` and `option` (global) storages
   - Schema registration with seed/flush
   - Policy gating (`withPolicy()`)
   - Logging assertions (guarded)

5. Documentation updates

   - Add usage examples to `docs/` with both storage modes
   - Clarify `onExists` behaviors and safety guidelines
   - Cross-link from `UserOptionsStore` to the fluent builder

6. Integration and cleanup

   - (Optional) Provide a minimal adapter to use the builder from FeaturesManager contexts
   - Confirm no remaining references to removed meta APIs

7. Migration notes (pre-release)
   - Document the removal of `InsertUserMetaInterface` and `insert_user_metta()`
   - Provide guidance for migrating arbitrary meta writes (use WP APIs directly) vs. plugin-scoped settings (use builder/`UserOptionsStore`)

## Migration & Backward Compatibility

- Pre-release breaking change: removed `InsertUserMetaInterface` and `insert_user_metta()`.
- Consumers should migrate per-user plugin settings to this API (or `UserOptionsRepository` directly if preferred).

## Open Questions

- Should we expose raw meta writes in the builder (e.g., `meta([...])`) for third-party integrations? Current stance: No, keep API focused on plugin options. Direct callers can use WP functions when necessary.
- Should we add a convenience `site()`/`network()` method to control options storage scope implicitly? Current stance: `userScope(...)` is explicit and sufficient.
