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
- Stores values directly as an associative array: `key => value`.
- Supports callable defaults, sanitization, and validation per key.
- Avoids no-op writes and supports batch updates with explicit commit helpers (`commit_merge()` / `commit_replace()`).
- Keeps the API surface tight while allowing advanced patterns (deep merges, post-construction schema registration).

### Core Architecture

- One main `wp_options` row stores an associative array: `key => value`.
- In-memory cache refreshed via `refresh_options()`; persisted via `_save_all_options()`.
- Schema map enables per-key lifecycle: default seeding, sanitization, and validation.
- Logging via `Logger` with concise debug messages.

### Key Design Principles

- Favor simple, explicit primitives; provide escape hatches not abstractions for everything.
- Make writes intentional; skip no-ops and support batching.
- Keep coupling low; DI `ConfigInterface` for option name and `Logger` resolution, with a guarded fallback.
- Document WordPress-specific semantics (autoload creation-time behavior).

### Schema Key Principles

- **No implicit writes**: Registering a schema does not write to the DB by itself.
- **Separation of concerns**: `Config::options()` returns a pre-wired manager; persistence is always explicit on `RegisterOptions`.
- **Single source of truth**: Autoload policy and option key come from `Config` unless you intentionally construct options directly.

See also: `inc/Options/docs/TFS-002-Using-Schemas.md` for detailed schema usage guidance.

## Implementation Strategy

### Core Components

- `Ran\PluginLib\Options\RegisterOptions`
  - Construction, schema normalization, default seeding, read/write operations.

### Integration Points

- WordPress Options API: `get_option`, `set_option`, `add_option`, `delete_option`.
- `ConfigInterface#get_config()['RAN']['AppOption']` to derive the main option name.

### Data Flow

1. Construct instance (from config or directly).
2. Load existing grouped options from DB.
3. Normalize schema; seed defaults for missing keys if provided.
4. Callers read via `get_option()`/`get_options()` and write via `set_option()` for single writes or `add_option(s)` + `commit_merge()`/`commit_replace()` for batching.
5. Persist explicitly via `commit_merge()`/`commit_replace()` (or implicitly via `set_option`).

## API Design

### Public Interface

```php
// Construction is via named/static factories; constructor is protected.

// Protected constructor (internal)
protected function __construct(
  string $main_wp_option_name,
  bool $main_option_autoload = true,
  ?ConfigInterface $config = null,
  ?Logger $logger = null
)

// Named factories
public static function site(string $option_name, bool $autoload_on_create = true, ?Logger $logger = null): static
public static function network(string $option_name, ?Logger $logger = null): static
public static function blog(string $option_name, int $blog_id, ?bool $autoload_on_create = null, ?Logger $logger = null): static
public static function user(string $option_name, int $user_id, bool $global = false, ?Logger $logger = null): static

// Factory from Config (scope and storage args optional)
public static function from_config(
  ConfigInterface $config,
  bool $autoload = true,
  OptionScope|string|null $scope = null,
  array $storage_args = []
): static

// Reads
public function get_option(string $option_name, mixed $default = false): mixed
public function get_options(): array
public function has_option(string $option_name): bool

// Writes
public function set_option(string $option_name, mixed $value): bool
public function stage_option(string $option_name, mixed $value): self
public function stage_options(array $keyToValue): self
public function set_option(string $option_name, mixed $value): bool
public function delete_option(string $option_name): bool
public function clear(): bool
public function commit_merge(): bool
public function commit_replace(): bool
public function refresh_options(): void

// Schema (Option A)
public function register_schema(array $schema): bool
public function with_schema(array $schema): self
```

### Usage Examples

- Basic usage: `plugin-lib/inc/Options/docs/examples/basic-usage.php`
- Constructor schema + default seeding: `plugin-lib/inc/Options/docs/examples/schema-defaults.php`
- Sanitization and validation: `plugin-lib/inc/Options/docs/examples/schema-sanitize-validate.php`
- Batch and commit: `plugin-lib/inc/Options/docs/examples/batch-and-flush.php`
- Deep merge pattern (caller-defined): `plugin-lib/inc/Options/docs/examples/deep-merge-pattern.php`
- Flip autoload safely: `plugin-lib/inc/Options/docs/examples/autoload-flip-example.php`

### Configuration Options

- Main option name derived from `ConfigInterface` via `RAN.AppOption`, with a fallback to `Slug`.
- `main_option_autoload` controls grouped row autoload on creation.

## Technical Constraints

### Performance Requirements

- No-op guard in `set_option()` avoids unnecessary DB writes.
- Batch updates via `add_option(s)` + `commit_merge()`/`commit_replace()` minimize write frequency.

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
- Add batching (`set_options`, `commit_merge()`/`commit_replace()`) and no-op guards.

### Phase 3: Escape Hatches & Docs

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
- Batch via `add_option(s)` and `commit_merge()`/`commit_replace()`.
- Schema default seeding; callable defaults; sanitize/validate flows.

- Error messages include truncated value and callable descriptor.

### Integration Tests

- Interaction with WordPress Options API.
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

- When migrating from scattered keys, map each previous key to a grouped sub-key.

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

### Troubleshooting

- Use `refresh_options()` if external processes might have changed the row during long operations.
- Inspect `get_options()` to verify metadata and actual values.

## Related Documentation

### Dependencies

- `plugin-lib/docs/ARDs/ARD-001-Documentation-Process.md`

### References

- Template: `plugin-lib/docs/Templates/TFS-Template.md`
- WordPress Options API (`get_option`, `set_option`, `add_option`, `delete_option`)

## Logger binding: DI vs `with_logger()`

`RegisterOptions` supports two ways to bind a logger:

- Constructor/factory DI (earliest binding)

  - Factories accept an optional `Logger` instance.
    - `RegisterOptions::site($option, $autoload = true, ?Logger $logger = null)`
    - `RegisterOptions::network($option, ?Logger $logger = null)`
    - `RegisterOptions::blog($option, $blogId, ?bool $autoloadOnCreate = null, ?Logger $logger = null)`
    - `RegisterOptions::user($option, $userId, $global = false, ?Logger $logger = null)`
  - `from_config()` binds logger via `ConfigInterface::get_logger()` when provided.
  - Benefits: constructor-time reads/logs are captured by your logger.

- Post‑construction fluent: `with_logger(Logger $logger): static`
  - Rebinds the logger on an already constructed instance.
  - Useful for runtime overrides (e.g., temporarily attach a `CollectingLogger` during a diagnostic flow) or when the creation site cannot be changed to pass DI.
  - Note: constructor-time logs are not captured if you rebind later.

Preferred usage:

- Use factory/constructor DI whenever possible to ensure earliest logging and consistent test capture.
- Use `with_logger()` when you need to swap/override the logger mid‑lifecycle or when testing the fluent itself (e.g., chaining semantics).

### Examples: Logger DI

```php
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Util\Logger;

// 1) Factory DI — ensure earliest logging
$logger = new Logger([ /* config */ ]);
$opts   = RegisterOptions::site('my_option', true, $logger);

// 2) From Config — provide get_logger() on your Config
$configWithGetLogger = new class($logger) implements \Ran\PluginLib\Config\ConfigInterface {
    public function __construct(private Logger $logger) {}
    public function get_logger(): Logger { return $this->logger; }
    public function get_options_key(): string { return 'my_option'; }
    // ...other required ConfigInterface methods...
};

$optsFromConfig = new RegisterOptions($configWithGetLogger->get_options_key(), [
    'autoload' => true,
    'scope'    => 'site',
]);
```
