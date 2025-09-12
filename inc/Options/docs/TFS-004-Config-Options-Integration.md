# TFS-003: Config + Options Integration with Multi-Scope Support

========

## Status

**Accepted** - 2025-08-26

## Context

This TFS documents the completion of a comprehensive Config + Options integration work cycle that spanned two major PRDs (PRD-002 and PRD-003). The work delivered a batteries-included approach to WordPress options management with full multi-scope support including user-scoped storage.

## Summary of Implemented Features

### Core Integration (PRD-002)

**Config Enhancement:**

- `Config::get_options_key()` - deterministic option key from `RAN.AppOption` header or slug fallback
  `Config::options(?StorageContext $context = null, bool $autoload = true): RegisterOptions`
- Typed StorageContext selects scope (site/network/blog/user); no implicit writes during Config hydration
- All persistence remains explicit via Options helpers

**RegisterOptions Enhancement:**

- `RegisterOptions::fromConfig()` - static factory from Config instance
- `seed_if_missing(array $defaults)` - idempotent activation-time seeding
- `migrate(callable $migration)` - user-provided migration logic with strict change detection
- Enhanced autoload management with tri-state getter and guarded setter
- Write-gating system with comprehensive filter hooks for security

### Multi-Scope Support (PRD-003)

**Scope Architecture:**

- `OptionScope` enum: `Site`, `Network`, `Blog`, `User` (User added late in cycle)
- Storage adapter pattern via `OptionStorageInterface`
- Concrete adapters: `SiteOptionStorage`, `NetworkOptionStorage`, `BlogOptionStorage`, `UserOptionStorage`
- Factory-based adapter selection with scope and context parameters

**Storage Adapters:**

- **SiteOptionStorage**: `get_option`/`set_option`/`add_option`/`delete_option` + autoload support
- **NetworkOptionStorage**: `get_site_option`/`update_site_option`/`add_site_option`/`delete_site_option` (no autoload)
- **BlogOptionStorage**: `get_blog_option`/`update_blog_option`/`add_blog_option`/`delete_blog_option` + conditional autoload
- **UserOptionStorage**: `get_user_meta`/`update_user_meta`/`add_user_meta`/`delete_user_meta` with global user option support

**Advanced Features:**

- Autoload semantics with scope-aware tri-state logic (`true`/`false`/`null`)
- Guarded autoload flips via delete+add pattern preserving DB snapshots
- Write-gating filters: `ran/plugin_lib/options/allow_persist` and scoped variants
- Support for both user metadata and global user options via `user_storage` parameter
- Blog-specific targeting with current vs. explicit blog ID handling

### Security & Performance

**Write Gating:**

- Comprehensive gate system protecting both in-memory mutations and DB persistence
- Context-rich filter hooks with operation type, scope, user/blog IDs, and metadata
- Performance optimization: gates only invoked when mutations would occur (no-op bypass)
- Centralized logging for vetoed operations

**Performance Optimizations:**

- No-op guards prevent unnecessary DB writes and filter invocations
- Batch operations (`add_options`) gate once before processing
- Shallow merge support in `flush(true)` preserves external changes
- Memoized storage adapters per instance

## Technical Architecture

### Storage Adapter Pattern

```php
interface OptionStorageInterface {
    public function scope(): OptionScope;
    public function supports_autoload(): bool;
    public function read(string $key): mixed;
    public function update(string $key, mixed $value, bool $autoload = false): bool; // autoload ignored on update
    public function add(string $key, mixed $value, ?bool $autoload = null): bool;    // null defers to WP 6.6+ heuristics where supported
    public function delete(string $key): bool;
}
```

### Write Gating System

```php
// Generic gate
add_filter('ran/plugin_lib/options/allow_persist', function(bool $allowed, array $context) {
    // Context includes standardized keys:
    // - Common: op, main_option, scope, blog_id?, user_id?, user_storage?, user_global?
    // - Per-op: key|keys|changed_keys
    // - Save-time only: options, merge_from_db
    return $security_check($context) ? $allowed : false;
}, 10, 2);

// Scope-specific gates
add_filter('ran/plugin_lib/options/allow_persist/scope/network', $callback, 10, 2);
add_filter('ran/plugin_lib/options/allow_persist/scope/user', $callback, 10, 2);
```

### Usage Patterns (typed StorageContext)

```php
use Ran\PluginLib\Options\Storage\StorageContext;

// Site scope (default)
$opts = $config->options();

// Network scope (multisite)
$opts = $config->options(StorageContext::forNetwork());

// Blog-specific
$opts = $config->options(StorageContext::forBlog(123));

// User-specific (meta)
$opts = $config->options(StorageContext::forUser(456, 'meta', false));

// User global options (option storage, global=true)
$opts = $config->options(StorageContext::forUser(456, 'option', true));

// Inject a custom immutable write policy (fluent on RegisterOptions)
$opts = $config->options();
$opts->with_policy($customPolicy); // implements \Ran\PluginLib\Options\Policy\WritePolicyInterface
```

## Implementation Highlights

### Backward Compatibility

- All existing `Config` and `RegisterOptions` usage continues unchanged
- Default scope remains `'site'` - no breaking changes
- Optional arguments system allows gradual adoption

### User Scope Addition

- User scope support added later in development cycle without disrupting architecture
- Maintains same patterns as other scopes (user ID required like blog ID for blog scope)
- Supports both user meta and global user options via `user_storage` parameter (default: user meta, if `user_global` is not set)
- Full integration with write-gating and autoload systems

### Testing Strategy

- Public interface testing focusing on behavior over implementation
- WordPress API integration testing with WP_Mock
- Scope-specific adapter testing with isolated unit tests
- Write-gating security testing with filter simulation
- Autoload tri-state logic verification across all scopes

## Migration & Adoption

### Activation Patterns

```php
register_activation_hook(__FILE__, function() use ($config) {
    $opts = $config->options();
    $opts->seed_if_missing([
        'enabled' => true,
        'timeout' => 30
    ]);
});
```

### Schema & Migration

```php
$opts = $config->options();
$opts->with_schema([
    'enabled' => ['default' => true],
    'timeout' => ['default' => 30, 'validate' => 'is_numeric']
], false, false);

$opts->migrate(function($current, $manager) {
    if (version_compare($current['version'] ?? '0.0.0', '2.0.0', '<')) {
        $current['new_field'] = 'default_value';
    }
    return $current;
});
```

## Documentation & Examples

### Comprehensive Examples

- Plugin bootstrap patterns with activation hooks
- Theme integration examples
- Multisite network administration patterns
- User-specific option management
- Migration recipes for scope transitions
- Security patterns with capability checks

### WP-CLI Integration

- Migration recipes for site→network transitions
- Dry-run support with progress reporting
- Rollback guidance for failed migrations

## Acceptance Criteria Met

✅ **Config Integration:**

- `get_options_key()` and `options()` methods implemented
- No implicit writes during hydration
- Optional arguments support with validation

✅ **RegisterOptions Enhancement:**

- `fromConfig()`, `seed_if_missing()`, `migrate()` methods
- Autoload tri-state getter and guarded setter
- Write-gating system with comprehensive context

✅ **Multi-Scope Support:**

- All four scopes implemented: Site, Network, Blog, User
- Storage adapter pattern with concrete implementations
- Scope-aware autoload semantics

✅ **Security & Performance:**

- Write-gating filters with rich context
- Performance optimizations for hot paths
- Comprehensive logging for debugging

✅ **Documentation:**

- Complete PRD documentation with examples
- Migration guidance and WP-CLI recipes
- Security patterns and capability recommendations

## Future Considerations

### Potential Enhancements

- Caching layer for frequently accessed options
- Bulk operations for multi-site management
- Enhanced validation system integration
- Performance monitoring and metrics

### Maintenance Notes

- Monitor WordPress core changes affecting option APIs
- Review security patterns as WordPress capabilities evolve
- Consider community feedback on scope usage patterns
- Evaluate performance in high-traffic multisite environments

## Conclusion

This work cycle successfully delivered a comprehensive, secure, and performant options management system that maintains backward compatibility while providing powerful new capabilities. The late addition of user scope support demonstrates the flexibility of the chosen architecture. The implementation provides a solid foundation for WordPress plugin and theme development with enterprise-grade features and security considerations.
