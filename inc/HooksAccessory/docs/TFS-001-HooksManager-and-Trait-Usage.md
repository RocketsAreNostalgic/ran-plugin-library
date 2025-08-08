# TFS-003: HooksManager and HooksManagementTrait Usage

## Document Information

- Status: Implemented
- Date: 2025-01-15
- Updated: 2025-08-08
- Implementation Priority: High
- Technical Complexity: Medium
- Dependencies: None

## Context

Existing wrapper-based hook calls made deduplication, grouping, and introspection difficult. We introduced a per-owner `HooksManager` exposed through `HooksManagementTrait` to standardize registration and improve diagnostics while retaining compatibility with declarative interfaces.

### Problem Statement

- Repeated registrations and inconsistent patterns
- Hard to debug complex asset-related timing issues
- Tests tightly coupled to wrappers

### Current System Limitations

- No unified place to track hook registrations
- Limited introspection and stats

## Decision

Adopt `HooksManager` with `HooksManagementTrait` for owner-scoped, deduplicated registration, conditional and grouped patterns, and debug/introspection support. Keep wrapper methods for valid one-off usage.

### Core Architecture

- Owner class uses `HooksManagementTrait` → memoized `HooksManager` (with `Logger`)
- Manager integrates with WordPress via `WPWrappersTrait`
- Declarative interfaces remain supported via registrars

### Key Design Principles

- Owner-scoped, not global
- Deduplicate by (type, hook, priority, callback[, context])
- Ergonomic helpers for common WP patterns
- Public getters for stats and keys

## Implementation Strategy

### Core Components

- `inc/HooksAccessory/HooksManager.php`
- `inc/HooksAccessory/HooksManagementTrait.php`
- `inc/HooksAccessory/ActionHooksRegistrar.php`, `FilterHooksRegistrar.php`
- `inc/HooksAccessory/HookDefinition.php`, `RegistrationResult.php`

### Integration Points

- Enqueue traits (scripts, styles, media), BlockRegistrar, future features.

### Data Flow

Owner → Trait → Manager → WordPress (add_action/add_filter via wrappers)

## API Design

### Public Interface (from owner classes)

- `get_hook_stats(): array`
- `get_registered_hooks(): array`

### Protected Helpers

- `_register_action(string $hook, callable $cb, int $priority=10, int $acceptedArgs=1, array $context=[])`
- `_register_filter(string $hook, callable $cb, int $priority=10, int $acceptedArgs=1, array $context=[])`
- `_register_action_method(string $hook, string $method, int $priority=10, int $acceptedArgs=1, array $context=[])`
- `_register_filter_method(string $hook, string $method, int $priority=10, int $acceptedArgs=1, array $context=[])`
- `_register_conditional_action(...)`, `_register_conditional_filter(...)`
- `_register_hooks_bulk(array $defs)`, `_register_hook_group(string $group, array $defs)`
- `_register_universal_action(...)`, `_register_admin_action(...)`, `_register_frontend_action(...)`
- Introspection: `_is_hook_registered(...)`, `_get_registered_hooks()`, `_get_hook_stats()`, `_get_hooks_by_group(...)`, `_get_hooks_debug_report()`

### Usage Examples

```php
class Feature {
    use HooksManagementTrait;

    public function init(): void {
        $this->_init_hooks();
        $this->_register_action('wp_init', [$this, 'boot']);
        $this->_register_conditional_action('admin_init', [$this, 'admin_boot'], 'is_admin');
    }

    public function boot(): void {}
    public function admin_boot(): void {}
}
```

### Configuration Options

- None in manager; owners may gate debug calls by `ConfigInterface::is_dev_environment()`.

## Technical Constraints

### Performance Requirements

- Avoid duplicate registrations; O(1) lookup via hashed keys.

### Compatibility Requirements

- PHP 8.1+, WordPress hooks; tests use PHPUnit + Mockery + WP_Mock.

### Security Considerations

- No changes to capability checks.

## Implementation Phases

- Phase 1: Simple migrations and helpers (done)
- Phase 2: Broader adoption in enqueue/block registrar (done/ongoing)

## Alternatives Considered

- Wrappers-only approach — lacks dedup and insight.
- Global singleton manager — loses ownership and testing ergonomics.

## Consequences

### Positive

- Consistent API, deduplication, tracking, easier testing, better docs.

### Negative

- Slight complexity increase; not always justified for trivial one-offs.

### Limitations

- Must maintain wrapper compatibility where manager is not present.

## Testing Strategy

### Unit Tests

- 100% coverage for `HooksManager` and near-100% for helpers and registrars.

### Integration Tests

- Enqueue traits and BlockRegistrar verified with manager calls.

### Performance Tests

- Not required; deduplication handles primary perf concern.

## Error Handling

### Validation Strategy

- `HookDefinition` validation; defensive checks in manager.

### Error Recovery

- `RegistrationResult` to aggregate errors (used in extension points).

### Logging and Debugging

- `Logger` integration; `get_stats()`, `get_registered_hooks()`, debug report helpers.

## Migration Path

### From Previous Implementation

- Replace `_do_add_action/_do_add_filter` with trait helpers in classes already using the trait. Retain wrappers where no manager exists and complexity is trivial.

### Backward Compatibility

- Declarative interfaces and WP wrappers remain supported.

## Future Considerations

### Potential Enhancements

- Extended stats, richer debug views, optional telemetry.

### Scalability Considerations

- Per-owner isolation scales naturally across many classes.

## Developer Guidelines

### Best Practices

- Prefer trait helpers when a manager exists; provide `context` for diagnostics; use conditional/grouped patterns for complex flows.

### Common Pitfalls

- Registering the same callback multiple times (avoid with context-aware keys); mixing wrappers and manager in the same owner.

### Troubleshooting

- Use `get_hook_stats()` and `get_registered_hooks()`; generate debug report from the trait.

## Related Documentation

- `docs/Documentation-Process-Guide.md`
- `inc/HooksAccessory/HooksManagementTrait.php`, `HooksManager.php`
- Tests: `Tests/Unit/EnqueueAccessory/EnqueueTraitTestCase.php`
