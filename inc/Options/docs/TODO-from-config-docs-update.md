# TODO – from_config() docs and examples update

Date: 2025-09-10
Owner: plugin-lib Options API
Status: Draft (temporary tracking list)

## Scope
Update all documentation and examples to reflect the new public signature:

- `RegisterOptions::from_config(ConfigInterface $config, array $args = [])`
- `$args` shape:
  - `autoload?: bool`
  - `scope?: OptionScope|string|null`
  - `entity?: \Ran\PluginLib\Options\Entity\ScopeEntity|null` (e.g., `BlogEntity`, `UserEntity`)
- Prefer `Config::options(array $args = [])` for the 80/20 no‑write accessor; use fluents for schema/defaults/policy.
- Avoid "parameter soup" examples; remove positional-argument usages.

---

## Task list

- [ ] Update `inc/Options/readme.md`
  - Replace positional `from_config(...)` examples with array‑args form.
  - Ensure examples prefer `$config->options([...])` for common paths.
  - Show fluents: `with_schema()`, `with_defaults()`, `with_policy()`; avoid passing these via factory.

- [ ] Update ARD/TFS docs
  - File: `inc/Options/docs/ARD-00Y-Temporary-Scope-Unification-and-Factory-Simplification.md`
    - Replace references to `_from_config()` for user code with `from_config($config, [...])`.
    - Keep `_from_config()` mentioned only as internal collaborator.
  - File: `inc/Options/docs/ARD-00X-Options-API-Simplification.md`
    - Align narrative with array‑args signature and entity usage.
  - File: `inc/Options/docs/TFS-001-RegisterOptions-Options-Manager.md`
    - Ensure examples use array‑args and entities.
  - File: `inc/Options/docs/TFS-003-Write-Permission-Guards.md`
    - Update any construction snippets accordingly.

- [ ] Update complexity notes
  - File: `inc/Options/docs/complexity-report.md`
    - Recommend `RegisterOptions::from_config($config, [...])` (array args) or `Config::options([...])`.
  - File: `inc/Options/docs/complexity-eval.md`
    - Ensure notes about `ScopeEntity::toStorageArgs()` are contextual (prefer entity in args).

- [ ] Update examples (PHP)
  - Path: `inc/Options/docs/examples/`
    - Files to touch (at minimum):
      - `basic-usage.php`
      - `merge-from-db.php`
      - `schema-defaults.php`
      - `register-schema-migration.php`
      - `user-scope.php`
      - `logger-injection.php`
      - `batch-and-flush.php`
      - `deep-merge-pattern.php`
      - `sanitize-validate.php`
      - `autoload-flip-example.php`
    - Actions:
      - Replace positional factories with `from_config($config, [...])` or `$config->options([...])`.
      - For blog/user scopes: pass `entity => new BlogEntity(id)` or `entity => new UserEntity(id, global, storage)`.
      - Move schema/defaults/policy to fluent methods.

- [ ] Update config integration doc
  - File: `inc/Config/docs/PRD-002-Config-Options-Integration.md`
    - Ensure parity examples between `Config::options([...])` and `RegisterOptions::from_config($config, [...])`.

- [ ] Top-level README
  - File: `README.md` (repo root)
    - Search for `from_config(` and update to array‑args signature; prefer `Config::options([...])` in quick starts.

- [ ] Internal collaborator notes
  - File: `inc/Options/RegisterOptions.php`
    - Confirm PHPDoc for `_from_config()` and `from_config()` show the `$args` shape and storage_args shape (done for code; reflect in docs).

- [ ] Cross-check tests for doc sync
  - Ensure any inline documentation in tests referencing the factory reflects the new signature (no code changes needed if tests already green).

- [ ] Style & validation
  - Run `composer cs` after each doc batch to keep formatting consistent.
  - Run `composer test` to ensure nothing in examples interferes with docblocks parsed by tooling.

---

## Examples – canonical forms (to copy into docs)

```php
// Site (default)
$opts = RegisterOptions::from_config($config, []);

// Network
$opts = RegisterOptions::from_config($config, [
  'scope' => 'network',
]);

// Blog (entity required)
use Ran\PluginLib\Options\Entity\BlogEntity;
$opts = RegisterOptions::from_config($config, [
  'scope'  => 'blog',
  'entity' => new BlogEntity(123),
]);

// User (entity required)
use Ran\PluginLib\Options\Entity\UserEntity;
$opts = RegisterOptions::from_config($config, [
  'scope'  => 'user',
  'entity' => new UserEntity(5, true, 'option'),
]);

// Prefer fluent methods for configuration beyond construction
$opts->with_schema($schema)
     ->with_defaults(['k' => 'v'])
     ->with_policy($policy);
```

---

## Notes
- Logger is derived from the provided `Config` in `_from_config()` (no need to pass logger in `$args`).
- Avoid raw `storage_args` in public examples; use typed entities in `$args['entity']`.
- `Config::options([...])` remains the 80/20 entry point; `from_config()` is the factory alternative with the same args semantics.
