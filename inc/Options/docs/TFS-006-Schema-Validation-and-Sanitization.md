# TFS-006: Schema, Validation, and Sanitization – Strict, Explicit, and Idempotent

## Summary

Define a strict, explicit schema process for `RegisterOptions` covering default seeding, sanitization, and validation. Eliminate inference, require explicit validators for every schema entry, and provide composable helpers via `Validate` and `Sanitize` that are pure and idempotent. Document canonicalization caveats and developer guidance.

## Goals

- Require a callable `validate` for every schema key.
- Reject setting/staging values for keys that are not in the registered schema.
- Provide a rich, grouped set of pure, idempotent sanitizers and validators to keep authoring ergonomic while remaining explicit.
- Document deterministic canonicalization options and when to use them.
- Ensure clear failure diagnostics and fail-fast behavior during registration and normalization.

## Non-Goals

- No fallback type inference from defaults.
- No hidden coercions when `sanitize` is absent.
- No compat/strict toggle; strict is the default.

## API Surface (Reference)

- `inc/Options/RegisterOptions.php`

  - `register_schema(array $schema): bool`
  - `with_schema(array $schema): self` (fluent alias)
  - `with_defaults(array $defaults): static` (in-memory seed)
  - `set_option(string $key, mixed $value): bool`
  - `stage_option(string $key, mixed $value): self`
  - `stage_options(array $kv): self`
  - `commit_merge(): bool`, `commit_replace(): bool`
  - Internals: `_sanitize_and_validate_option(string $key, mixed $value): mixed` (throws on unknown key or failed validation)

- `inc/Options/Validate.php` (grouped validators)

  - `Validate::basic()`, `number()`, `string()`, `collection()`, `enums()`, `compose()`, `format()`
  - Typical data: `email()`, `phone()` (strict leading "+"), `jsonString()`, `url()`, `domain()`, `hostname()`, `origin()`

- `inc/Options/Sanitize.php` (grouped sanitizers)
  - `Sanitize::string()->trim(), toLower(), stripTags()`
  - `Sanitize::number()->to_int(), to_float(), to_bool_strict()`
  - `Sanitize::array()->ensure_list(), unique_list(), ksort_assoc()`
  - `Sanitize::json()->decode_to_value(), decode_object(), decode_array()`
  - `Sanitize::combine()` composition helpers:
    - `pipe(...$sanitizers)` — sequentially apply multiple sanitizers
    - `nullable($sanitizer)` — pass-through null, sanitize non-null
    - `optional($sanitizer)` — alias of `nullable` for ergonomics
    - `when($predicate, $sanitizer)` — apply when predicate returns true
    - `unless($predicate, $sanitizer)` — apply when predicate returns false
  - Canonicalizers (deterministic, order-insensitive):
    - Static: `Sanitize::order_insensitive_deep(mixed $v): mixed`, `Sanitize::order_insensitive_shallow(mixed $v): mixed`
    - Callable wrappers: `Sanitize::canonical()->order_insensitive_deep()`, `...Shallow()`

## Schema Contract

- Every schema entry MUST include a callable `validate`.
- `sanitize` is optional but, when provided, MUST be callable.
- If `default` is provided, the system MAY run `sanitize` then `validate` at registration time to fail fast on misconfiguration.
- Runtime, values are normalized as: `value' = sanitize?(value)`, then `validate(value') === true` must hold.

## Registration-Time Enforcement

- `register_schema()` performs the following checks per key:
  - `validate` exists and is callable; otherwise, throws `InvalidArgumentException` with the offending key.
  - `sanitize` (if present and non-null) is callable; otherwise, throws `InvalidArgumentException`.
  - Optionally self-checks provided defaults by applying `sanitize` then `validate` (fail fast).
- Shallow rule merge: new rules override existing per key; this is a shallow (per-field) merge. Schema authors must not assume deep merges.

## Runtime Behavior

- Unknown keys are rejected:
  - `set_option()` / `stage_option()` / `stage_options()` call `_sanitize_and_validate_option()` which throws when a key has no schema.
- Sanitization then validation:
  - When `sanitize` is provided, it is applied before `validate`.
  - Any non-true result from `validate` is considered a failure and results in an exception path.
- Persistence is policy-gated and independent of schema logic (see TFS-005 for `WriteContext` policy typing).

## Execution frequency and hot paths

- Multiple passes are expected by design to keep values consistent at each lifecycle stage:
  - `register_schema()` applies rules when seeding defaults for missing keys and normalizing existing values.
  - `set_option()` / `stage_options()` apply rules again to incoming values.
- Performance considerations:
  - Keep sanitizers pure and idempotent (now enforced); avoid heavy I/O or global-side effects.
  - Validators must return strict booleans (now enforced) and be deterministic.
  - In bulk operations, rely on operation-level summary logs and set the logger level to INFO/WARNING to reduce DEBUG noise.
  - Prefer read–modify–write patterns for deep merges to avoid unnecessary churn.
  - Use canonicalizers only when order is not semantically meaningful.

## Validator and Sanitizer Principles

- Purity: No side effects (I/O, logging, global state mutation) inside validators and sanitizers.
- Idempotence: `s(s(v)) === s(v)` must hold for all provided sanitizers. Validators must be referentially transparent (deterministic true/false given the same input), without internal state.
- Determinism: Prefer canonicalizers only when order is not semantically meaningful; document reordering effects.

## Security Considerations

- Trust boundary: Sanitizers and validators are arbitrary PHP callables provided by plugin code. They must not be treated as a security barrier.
- Do not feed untrusted user input directly into schema callables without pre-validation at your application boundary (e.g., capability checks, nonce/CSRF, type/shape guards).
- Callables must be pure and idempotent (no I/O, no global state mutation). Side effects may cause non-deterministic behavior and are explicitly discouraged.
- Prefer using the provided `Sanitize` and `Validate` helpers where possible; they are designed to be pure and predictable.

## Canonicalization Guidance

- `order_insensitive_deep`:

  - Objects → arrays (prefers `JsonSerializable`)
  - Recursively normalizes associative maps and sorts keys
  - Lists: normalize each element, then stable sort by JSON representation
  - Caveat: list order is intentionally changed; use only when order is not meaningful

- `order_insensitive_shallow`:
  - Converts objects to arrays (prefers `JsonSerializable`)
  - Sorts top-level associative keys only; no recursion
  - Top-level lists: stable sort by JSON
  - Prefer shallow when you require deterministic top-level ordering while preserving nested semantics

## Developer Experience (DX) Guidance

- Always specify `validate` explicitly; never rely on default types implying validation intent.
- Pair `sanitize` and `validate` to express intent clearly, e.g. `trim()` + `minLength(1)`.
- Use grouped helpers to keep schemas small and readable.
- Avoid canonicalizers for order-sensitive data.
- Prefer `collection()->list_of(...)` and `collection()->shape(...)` for structured data.

## Risks & Mitigations

- **Risk: Side-effecting sanitizers**
  - Mitigation: Provide and recommend built-in pure/idempotent helpers; document purity requirement.
- **Risk: Validators returning non-boolean values**
  - Mitigation: Treat non-true as failure; consider wrapping/enforcing strict-bool returns in a future dev-mode.
- **Risk: Misuse of canonicalizers**
  - Mitigation: Document caveats prominently; add examples showing when deep vs shallow is appropriate.

## Acceptance Criteria

- All schema entries without callable `validate` are rejected at registration.
- Unknown keys are rejected at set/stage time.
- Example schemas compile and run using grouped `Validate`/`Sanitize` helpers.
- Test suite includes:
  - Format validators (email/phone/jsonString/url/domain/hostname/origin)
  - Sanitizer groups and canonicalizer behavior
  - RegisterOptions strict behavior (unknown keys, explicit validators)

## Examples (References)

- `inc/Options/docs/examples/schema-defaults.php`
- `inc/Options/docs/examples/schema-sanitize-validate.php`
- `inc/Options/docs/examples/merge-strategies.php` (read–merge–write patterns without new APIs)

Quick examples:

```php
use Ran\PluginLib\Util\Sanitize;
use Ran\PluginLib\Util\Validate;

$schema['username'] = [
  'default'  => '',
  'sanitize' => Sanitize::combine()->pipe(
    Sanitize::string()->trim(),
    Sanitize::string()->toLower(),
  ),
  'validate' => Validate::string()->min_length(3),
];

$schema['timeout'] = [
  'default'  => 30,
  'sanitize' => Sanitize::combine()->optional(Sanitize::number()->to_int()), // null passes through
  'validate' => Validate::number()->between(1, 300),
];

$schema['tags'] = [
  'default'  => ['a','b'],
  'sanitize' => Sanitize::combine()->pipe(
    Sanitize::array()->ensure_list(),
    Sanitize::array()->unique_list(),
  ),
  'validate' => Validate::collection()->list_of(Validate::basic()->is_string()),
];

$schema['origin'] = [
  'default'  => 'https://example.com',
  'sanitize' => Sanitize::combine()->when(static fn($v) => is_string($v), Sanitize::string()->trim()),
  'validate' => Validate::format()->origin(),
];

$schema['payload'] = [
  'default'  => '{}',
  'sanitize' => Sanitize::json()->decode_object(),
  'validate' => Validate::basic()->is_array(),
];
```

## Test References

- `Tests/Unit/Options/ValidateTest.php` – format validators and helpers
- `Tests/Unit/Options/SanitizeTest.php` – grouped sanitizers and canonicalizers
- `Tests/Unit/Options/RegisterOptionsStrictPlanTest.php` – strict schema enforcement

## Future Work

- Optional dev-mode enforcement:
  - Wrap validators to require strict boolean returns with diagnostics
  - Idempotence assertions for sanitizers during development
- Expand format helpers as needs arise (e.g., IP/CIDR, ports) keeping pragmatic scope
