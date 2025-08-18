# RegisterOptions – Technical Feature Specification

## Document Information

- **Status**: Draft
- **Date**: 2025-08-10
- **Updated**: 2025-08-10
- **Implementation Priority**: Medium
- **Technical Complexity**: Medium
- **Dependencies**: `ConfigInterface`, `Logger`, WordPress Options API

## Context

WordPress plugins frequently scatter settings across multiple `wp_options` rows, complicating discovery, migration, autoload behavior, and performance tuning. This feature introduces a pragmatic options manager that stores all plugin options in a single grouped row with a small metadata wrapper per sub-option. It supports schema-driven defaults, sanitization, and validation, while providing batching and escape hatches for advanced cases.

### Problem Statement

- Settings for a plugin are often fragmented and inconsistently sanitized/validated.
- Repeated writes occur for no-op updates, increasing DB churn.
- Changing autoload behavior post-creation is non-trivial in WordPress.
- Complex/nested values need caller-defined merge strategies.

### Current System Limitations

- WordPress applies `autoload` primarily at creation time; flipping later requires a delete+add pattern.
- No standard pattern for value-level sanitization/validation or default seeding tied to a schema.

## Decision

Adopt a single-row, schema-aware options manager (`RegisterOptions`) that:

- Groups all sub-options under one main option name.
- Wraps each sub-option as `{ value, autoload_hint }` for metadata introspection.
- Supports callable defaults, sanitization, and validation per key.
- Avoids no-op writes and supports batch updates with an explicit `flush()`.
- Provides an explicit `set_main_autoload()` escape hatch to flip autoload safely.
- Keeps the API surface tight while allowing advanced patterns (deep merges, post-construction schema registration).

### Core Architecture

- One main `wp_options` row stores an associative array: `key => { value, autoload_hint }`.
- In-memory cache refreshed via `refresh_options()`; persisted via `_save_all_options()`.
- Schema map enables per-key lifecycle: default seeding, sanitization, and validation.
- Logging via `Logger` with concise debug messages.

### Key Design Principles

- Favor simple, explicit primitives; provide escape hatches not abstractions for everything.
- Make writes intentional; skip no-ops and support batching.
- Keep coupling low; DI `ConfigInterface` for option name and `Logger` resolution, with a guarded fallback.
- Document WordPress-specific semantics (autoload creation-time behavior).

## Implementation Strategy

### Core Components

- `Ran\PluginLib\Options\RegisterOptions`
  - Construction, schema normalization, default seeding, read/write operations.

### Integration Points

- WordPress Options API: `get_option`, `update_option`, `add_option`, `delete_option`.
- `ConfigInterface#get_config()['RAN']['AppOption']` to derive the main option name.

### Data Flow

1. Construct instance (from config or directly).
2. Load existing grouped options from DB.
3. Normalize schema; seed defaults for missing keys if provided.
4. Callers read via `get_option()`/`get_values()` and write via `set_option()` for single writes or `add_option(s)` + `flush()` for batching.
5. Persist explicitly via `flush()` (or implicitly via `set_option`).

## API Design

### Public Interface

```php
__construct(
  string $main_wp_option_name,
  array $initial_options = [],
  bool $main_option_autoload = true,
  ?Logger $logger = null,
  ?ConfigInterface $config = null,
  array $schema = []
)

static from_config(
  ConfigInterface $config,
  array $initial = [],
  bool $autoload = true,
  ?Logger $logger = null,
  array $schema = []
): self

public function get_option(string $option_name, mixed $default = false): mixed
public function get_options(): array // values with metadata
public function get_values(): array  // values only
public function has_option(string $option_name): bool
public function set_option(string $option_name, mixed $value, ?bool $autoload_hint = null): bool
public function add_option(string $option_name, mixed $value, ?bool $autoload_hint = null): self
public function add_options(array $keyToValue): self
public function update_option(string $option_name, mixed $value, ?bool $autoload_hint = null): bool
public function delete_option(string $option_name): bool
public function clear(): bool
public function flush(): bool
public function refresh_options(): void
public function get_autoload_hint(string $key): ?bool
public function set_main_autoload(bool $autoload): bool
public function register_schema(array $schema, bool $seedDefaults = false, bool $flush = false): bool
public function with_schema(array $schema, bool $seedDefaults = false, bool $flush = false): self
public static function sanitize_option_key(string $key): string
```

### Usage Examples

- Basic usage: `plugin-lib/inc/Options/docs/examples/basic-usage.php`
- Constructor schema + default seeding: `plugin-lib/inc/Options/docs/examples/schema-defaults.php`
- Sanitization and validation: `plugin-lib/inc/Options/docs/examples/sanitize-validate.php`
- Batch and flush: `plugin-lib/inc/Options/docs/examples/batch-and-flush.php`
- Deep merge pattern (caller-defined): `plugin-lib/inc/Options/docs/examples/deep-merge-pattern.php`
- Flip autoload safely: `plugin-lib/inc/Options/docs/examples/autoload-flip.php`

### Configuration Options

- Main option name derived from `ConfigInterface` via `RAN.AppOption`, with a fallback to `Slug`.
- `main_option_autoload` controls grouped row autoload on creation.
- Per-key `autoload_hint` is metadata only (does not affect WP autoload), useful for audits/migrations.

## Technical Constraints

### Performance Requirements

- No-op guard in `set_option()` avoids unnecessary DB writes.
- Batch updates via `set_options()` + `flush()` minimize write frequency.

### Compatibility Requirements

- PHP 8.1+ (strict types; union types used)
- WordPress Options API available

### Security Considerations

- Schema validators should ensure values are of expected types and formats.
- Callers must avoid storing sensitive secrets in auto-loaded groups if memory concerns exist.

## Implementation Phases

### Phase 1: Core Infrastructure

- Implement grouped options storage, basic getters/setters, and logging.

### Phase 2: Schema & Ergonomics

- Add schema normalization, default seeding, sanitization/validation.
- Add batching (`set_options`, `flush`) and no-op guards.

### Phase 3: Escape Hatches & Docs

- Implement `set_main_autoload()` and `get_autoload_hint()`.
- Provide examples and developer documentation.

## Alternatives Considered

### Alternative 1: One-option-per-key

**Why this was rejected:** Higher DB churn, less cohesive management, harder to batch and audit; autoload per key can be managed, but fragmentation increases complexity.

### Alternative 2: Heavy configuration framework

**Why this was rejected:** Over-engineers a common need; the chosen design prefers small, composable primitives and documented escape hatches.

## Consequences

### Positive

- Simpler operations; fewer DB writes with batching and no-op guard.
- Consistent sanitization/validation with schema.
- Clear migration path for autoload behavior and deep merges when needed.

### Negative

- Grouped-row writes can still risk concurrent overwrite without explicit merge; callers should batch and minimize write windows.

### Limitations

- Autoload flip requires delete+add due to WordPress semantics.
- Schema merges are shallow by design; nested structures require caller-defined merges.

## Testing Strategy

### Unit Tests

- Getter/setter correctness, including `has_option`, `get_values`.
- No-op write guard: unchanged values skip persistence.
- Batch via `add_option(s)` and `flush()`.
- Schema default seeding; callable defaults; sanitize/validate flows.
- `set_main_autoload` preserves data and flips autoload.
- Error messages include truncated value and callable descriptor.

### Integration Tests

- Interaction with WordPress Options API (creation vs. update semantics for autoload).
- Concurrent write scenarios simulated to confirm batching patterns.

### Performance Tests

- Measure write counts for typical install/migration paths with batching.

## Error Handling

### Validation Strategy

- Per-key callable validators return `true` or throw (`InvalidArgumentException`) with diagnostic context.

### Error Recovery

- Callers can catch exceptions and surface admin notices for invalid configuration.

### Logging and Debugging

- Debug logs at key lifecycle points; truncated values for large data.

## Migration Path (if required)

### From Previous Implementation

- When migrating from scattered keys, map each previous key to a grouped sub-key, optionally recording `autoload_hint` to support future splits.

### Backward Compatibility

- Existing consumers of WordPress Options API can coexist; grouped manager does not modify unrelated options.

## Future Considerations

### Potential Enhancements

- Optional merge-from-DB strategy to reduce lost updates for disjoint keys.
- Pluggable deep-merge strategies for nested structures.

### Scalability Considerations

- For very large blobs, consider splitting into multiple groups; hints and schema can guide splitting strategies.

## Developer Guidelines

### Best Practices

- Prefer flat keys; reserve nested structures for well-defined maps.
- Use `set_options` + `flush` for installer/migration code.
- Document validator intent; provide actionable messages.

### Common Pitfalls

- Expecting per-key `autoload_hint` to affect WordPress autoload behavior.
- Forgetting delete+add when flipping autoload for the grouped row.

### Troubleshooting

- Use `refresh_options()` if external processes might have changed the row during long operations.
- Inspect `get_options()` to verify metadata and actual values.

## Related Documentation

### Dependencies

- `plugin-lib/docs/ARDs/ARD-001-Documentation-Process.md`

### References

- Template: `plugin-lib/docs/Templates/TFS-Template.md`
- WordPress Options API (`get_option`, `update_option`, `add_option`, `delete_option`)
