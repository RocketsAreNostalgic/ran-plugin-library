# TFS-005: WriteContext – Typed policy context for persistence gates

## Summary

Introduce a typed `WriteContext` value object to replace array-shaped policy context passed to `WritePolicyInterface::allow(...)`. This strengthens invariants, improves IDE guidance, and centralizes validation for all persistence gate calls within `RegisterOptions`.

## Goals

- Replace legacy `array $ctx` with `WriteContext $wc` in `WritePolicyInterface::allow(...)`.
- Provide clear, typed fields for operation context (scope, option name, op-specific metadata, etc.).
- Centralize construction and validation via static factories on `WriteContext`.
- Migrate `RegisterOptions` call sites to use the factories.
- Update `RestrictedDefaultWritePolicy` to consume `WriteContext`.
- Expand tests to cover happy paths and validation failures.

## Non-Goals

- Changing gate semantics or policy logic.
- Changing public RegisterOptions API beyond internal policy interaction.

## API Changes (Breaking)

- File: `inc/Options/WritePolicyInterface.php`

  - Old:

    ```php
    public function allow(string $op, array $ctx): bool;
    ```

  - New:

    ```php
    public function allow(string $op, WriteContext $wc): bool;
    ```

- File: `inc/Options/WriteContext.php` (new)
  - Value object with the following typed fields (accessible via getters):
    - `main_option: string` (non-empty)
    - `scope: string` in {'site','network','blog','user'}
    - `merge_from_db?: bool` (for flush/save paths)
    - `blogId?: int|null` (blog scope)
    - `userId?: int|null` (user scope)
    - `user_storage?: 'meta'|'option'` (user scope)
    - `user_global?: bool` (user scope)
    - Op-specific context:
      - `key?: string` (set_option, delete_option, add_option)
      - `keys?: array<int,string>` (stage_options)
      - `options?: array<string,mixed>` (save_all)
      - `changed_keys?: array<int,string>` (migrate)
  - Static factories enforce invariant checks and normalize values:
    - `for_save_all(...)`, `for_set_option(...)`, `for_stage_options(...)`, `for_add_option(...)`, `for_delete_option(...)`, `for_clear(...)`, `for_seed_if_missing(...)`, `for_migrate(...)`.

## Implementation Plan

1. Add `WriteContext` with:

   - Private constructor, validated fields
   - Public getters
   - Static factories per operation
   - `InvalidArgumentException` on validation failures

2. Update `WritePolicyInterface` and `RestrictedDefaultWritePolicy`:

   - Signature to `WriteContext`
   - Replace array access with getters

3. Migrate `RegisterOptions` call sites:

   - Replace legacy inline `$context = array(...)` with `WriteContext::forXyz(...)`
   - Adjust `_apply_write_gate()` to accept `WriteContext` and pass through to policy
   - Logging: read values from getters for diagnostics

4. Tests
   - New: `Tests/Unit/Options/WriteContextTest.php` covering:
     - All factories happy paths
     - Validation errors: missing `blogId` for blog scope, missing `userId` for user scope, empty `main_option`, invalid `user_storage`, etc.
   - Update: `RestrictedDefaultWritePolicy` tests (if any) to accept `WriteContext`
   - Update: `RegisterOptions` tests that mock/spy policy calls

## Risks & Mitigations

- Breaking change for custom policies implementing `WritePolicyInterface`.
  - Mitigation: Document migration, provide code examples, and highlight in CHANGELOG.
- Missed call sites inside `RegisterOptions`.
  - Mitigation: Grep for `_apply_write_gate(` and any legacy `$context = array(` assignments; run full suite.

## Acceptance Criteria

- All compiler/type errors resolved; full test suite passes.
- New tests for `WriteContext` pass with coverage for all factories.
- All calls to `WritePolicyInterface::allow()` use `WriteContext`.
- Diagnostics (logs) still emit the same high-level trace lines at key decision points.

## Migration Notes

- Update custom implementations of `WritePolicyInterface`:
  - Change method signature to `allow(string $op, WriteContext $wc): bool`.
  - Replace array indexing with `WriteContext` getters.
- No public changes expected for `RegisterOptions` consumers.

## Example (after)

```php
// RegisterOptions::_save_all_options()
$ctx = $this->_get_storage_context(); // typed StorageContext
$wc  = WriteContext::for_save_all(
    main_option: $this->main_wp_option_name,
    scope: $ctx->scope->value,
    blogId: $ctx->blog_id,
    userId: $ctx->user_id,
    user_storage: $ctx->user_storage,
    user_global: $ctx->user_global,
    options: $to_save,
    merge_from_db: $merge_from_db
);
$allowed = $this->write_policy->allow($this->__persist_origin ?? 'save_all', $wc);
```

## Rollout

- Single PR since BC is not a concern for this repository.
- Ensure changelog documents the interface change and the migration advice for external policy implementations.

## Examples

- Wiring a user-scope policy with `ExampleUserSelfServiceWhitelistPolicy`: `inc/Options/docs/examples/policy-example-subscriber.php`
- Abstract helper base: `inc/Options/Policy/AbstractWritePolicy.php`
- AND-composite for stacking multiple policies: `inc/Options/Policy/WritePolicy.php` (note: use AND semantics by default; OR stacking can be added later)

### Choose your path

- Basic (recommended default):

  - Use `RestrictedDefaultWritePolicy` only (no composition). Aligns with WordPress capabilities by scope.
  - Example wiring: `inc/Options/docs/examples/policy-example-subscriber.php`

- Advanced (optional, application rules):
  - Compose policies with `WritePolicy` (AND semantics) for stricter control.
  - Common pattern: `new WritePolicy(new RestrictedDefaultWritePolicy(), new ExampleUserSelfServiceWhitelistPolicy())`
  - Single-policy example: `inc/Options/docs/examples/policy-example-subscriber.php`
  - Composite example: `inc/Options/docs/examples/policy-example-composite.php`

## Future work: OR semantics

In some scenarios you may want any one of several policies to allow a write (OR semantics). We currently default to AND semantics for security (all policies must allow). To add OR support later, consider one of these approaches:

- Create a dedicated OR composite:
  - `inc/Options/Policy/WritePolicyOr.php` with the same constructor signature as `WritePolicy`, but returns allow if any child allows and only denies if all deny.
- Add a static factory to the existing composite:
  - `WritePolicy::or(WritePolicyInterface ...$policies): WritePolicyInterface` returning an OR-composite instance.
- Add a boolean constructor flag (avoid if it risks ambiguity):
  - `new WritePolicy($policies..., $mode = 'AND')` — less explicit than separate types.

Operational considerations:

- Short-circuiting: Both AND and OR composites should short-circuit as soon as the outcome is known.
- Logging: When composing, include which policy decided (allow/deny) for traceability.
- Precedence: Keep nested composition readable; prefer explicit composition trees over flags for clarity.
- Defaults: Continue to default to AND semantics in examples and APIs for secure-by-default behavior.

Example skeleton (OR composite):

```php
<?php
declare(strict_types=1);

namespace Ran\PluginLib\Options\Policy;

use Ran\PluginLib\Options\WriteContext;

final class WritePolicyOr implements WritePolicyInterface {
    /** @var WritePolicyInterface[] */
    private array $policies;

    public function __construct(WritePolicyInterface ...$policies) {
        $this->policies = $policies;
    }

    public function allow(string $op, WriteContext $ctx): bool {
        $anyAllowed = false;
        foreach ($this->policies as $p) {
            if ($p->allow($op, $ctx)) {
                $anyAllowed = true; // short-circuit allowed
                break;
            }
        }
        return $anyAllowed;
    }
}
```
