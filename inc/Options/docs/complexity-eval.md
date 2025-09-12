# Options System Complexity Evaluation

This document evaluates concerns from `inc/Options/docs/complexity-report.md` against the current simplification direction in `inc/Options/docs/ARD-00X-Options-API-Simplification.md`. Each item is scored for:

- Impact: 1 (low) to 5 (high) positive impact on the codebase
- Effort/Thrash: 1 (low) to 5 (high) implementation effort and code churn

References:

- ARD: `inc/Options/docs/ARD-00X-Options-API-Simplification.md`
- Report: `inc/Options/docs/complexity-report.md`
- Core: `inc/Options/RegisterOptions.php`, `inc/Config/Config.php`

## Summary of Findings

- The ARD’s decision to make `Config::options(array $args = [])` the 80/20, no‑write entry point and to push customization (schema/defaults/policy) into fluent methods addresses multiple top concerns (constructor overload, API sprawl, late validation).
- Moving toward typed `scope` + `entity` via `Config::options()` reduces stringly‑typed `storage_args` issues.
- Documentation clarifications (memory vs persistence, shallow vs deep merge, strict equality rationale) deliver high comprehension gains at very low cost.
- Optional small refactors (extracting `_apply_write_gate()` internals; removing test-artifact comments) improve maintainability without altering behavior.

## Scored Concerns

- **Constructor/factory choice overload (wont fix)**

  - Assessment: Multiple construction paths (named factories vs `from_config`) increase cognitive load. ARD standardizes on `Config::options()` for the common path and discourages parameter soup.
  - Impact: 5
  - Effort/Thrash: 2

- **Stringly‑typed storage args in `_make_storage()` (DONE)**

  - Assessment (final): `_make_storage()` now consumes a typed `StorageContext` exclusively via `_get_storage_context()`. All legacy `storage_args` usage was removed from code and tests. Scope resolution is handled by `ScopeResolver::resolveToContext(...)` which returns a `StorageContext`. Entities expose `toStorageContext()` (the earlier `toStorageArgs()` helper was removed).
  - How this differs from the original note: Instead of validating early through `ScopeEntity::toStorageArgs()` + array payloads, we migrated entirely to a typed context (`StorageContext`) and removed array args altogether. This eliminates stringly/array validation paths rather than tightening them.
  - Impact: 5
  - Effort/Thrash: 3

- **In‑memory vs persistence behavior divergence**

  - Assessment: `set_option()/update_option()` persist, `add_option(s)` require `flush()`. ARD reinforces "no implicit writes"; requires clearer docs.
  - Impact: 4
  - Effort/Thrash: 1

- **Shallow merge semantics nuance**

  - Assessment: Rules are correct but nuanced. Provide cheat‑sheet examples for `flush(true)` vs deep merge (read–modify–write + `flush(false)`).
  - Impact: 3
  - Effort/Thrash: 1

- **Strict no‑op guards (`===`) in `set_option()`**

  - Assessment: Precise by design; can surprise callers expecting deep equality. Clarify rationale and guidance.
  - Impact: 2
  - Effort/Thrash: 1

- **Verbose `_apply_write_gate()` method (wontfix)**

  - Assessment: Correct but dense. Optionally split into small private helpers or a tiny collaborator for readability, no behavior change.
  - Impact: 3
  - Effort/Thrash: 2–3

- **WP wrappers obscuring behavior (wontfix)**

  - Assessment: Keep wrappers for testability, add a mapping table (wrapper → WP function) in docs.
  - Impact: 2
  - Effort/Thrash: 1

- **Coverage‑related comment in `_make_storage()` (DONE)**

  - Assessment: Testing artifact comment distracts; rephrase or remove.
  - Impact: 1
  - Effort/Thrash: 1

- **API surface sprawl across projects**

  - Assessment: ARD narrows the “blessed” path to `Config::options()` + fluents; soft‑deprecate extras in `from_config()` via phpdoc and examples.
  - Impact: 5
  - Effort/Thrash: 2

- **Runtime‑only validation of `storage_args` (DONE)**

  - Assessment: Address via typed `scope` + `entity` in `Config::options()`.
  - Impact: 5
  - Effort/Thrash: 3

NOTE: standardized on typed StorageContext across the stack.

- **Brittle logging contracts in tests**

  - Assessment: Reduce strictness/ordering dependency of log assertions; focus on behavior and key signals.
  - Impact: 3
  - Effort/Thrash: 3–4

- **Deep merge left to callers**

  - Assessment: Provide a reference helper or utility for deep merges to promote consistency.
  - Impact: 3
  - Effort/Thrash: 2–3

- **Prefer typed `ScopeEntity` via `Config::options()` (DONE)**

  - Assessment: Align with ARD: smaller arg surface, early validation, standardized flow.
  - Impact: 5
  - Effort/Thrash: 2–3

- **Extract `_apply_write_gate()` collaborator**

  - Assessment: Optional separation for readability; behavior unchanged.
  - Impact: 3
  - Effort/Thrash: 2–3

- **Separate schema concerns (`SchemaManager`)**

  - Assessment: Future‑facing; reduces `RegisterOptions` size and increases SRP.
  - Impact: 4
  - Effort/Thrash: 3–4

- **Deep‑merge helper utilities**
  - Assessment: Encourages consistent, predictable merging across plugins.
  - Impact: 3
  - Effort/Thrash: 2

## Prioritized Actions

- **Immediate (low thrash, high impact)**

  - Standardize around `Config::options()` + fluents; update docblocks on `from_config()` to discourage extras.
  - Document memory vs persistence, shallow vs deep merge, and strict equality rationale in `inc/Options/readme.md`.
  - Recommend typed `scope` + `entity` path (`ScopeEntity::toStorageArgs()`) via `Config::options()`.

- **Short‑term structural cleanup**

  - Rephrase/remove the coverage comment in `_make_storage()`.
  - Optionally split `_apply_write_gate()` into smaller private helpers (no behavior change).

- **Medium‑term test stability**

  - Reduce brittleness of logging assertions; prefer behavior‑centric checks and tolerant matching of messages.
  - Provide a reference deep‑merge helper or example for consistent caller behavior.

- **Longer‑term (if growth continues)**
  - Consider extracting schema handling to a small `SchemaManager` to shrink `RegisterOptions` and clarify responsibilities.

## Notes on Test Plan Alignment

- Cross‑reference Options Test Plan in `inc/Options/docs/Options-Tests-Plan.md` to ensure coverage includes:
  - `Config::options()` integration (typed scope/entity, autoload defaults, no implicit writes)
  - Autoload tri‑state and write policy gating
  - Schema registration, seeding, and explicit `flush()` behavior
  - Shallow vs deep merge scenarios and helpers
