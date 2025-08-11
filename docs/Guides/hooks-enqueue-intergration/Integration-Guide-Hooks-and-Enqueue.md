# Integration Guide: HooksAccessory × EnqueueAccessory

This guide explains how to use HooksAccessory (HooksManager + HooksManagementTrait) together with EnqueueAccessory (asset handlers and enqueue traits), and how to compose them safely with WordPress timing. It expands on `inc/HooksAccessory/docs/TFS-001-HooksManager-and-Trait-Usage.md` and complements `docs/TFS-003-Library-Initialization-Timing.md`.

## Goals

- Consistent, deduplicated hook registration across enqueue handlers and block tooling
- Clear timing model that avoids registering hooks on a hook that is already executing
- Testable and introspectable orchestration using `HooksManager`

## Where to use what

- One-off boundary hooks (e.g., `EnqueuePublic::load()` attaching to `wp_enqueue_scripts`) can continue to use `_do_add_action` wrappers for simplicity.
- Internal orchestration (bulk, conditional, grouped, or method-based) should use `HooksManagementTrait` helpers which delegate to `HooksManager`:
  - `_register_action` / `_register_filter`
  - `_register_action_method` / `_register_filter_method`
  - `_register_conditional_action` / `_register_conditional_filter`
  - `_register_hooks_bulk` / `_register_hook_group`
  - Enqueue ergonomics: `_register_asset_hooks()` and `_register_deferred_hooks()`

## Typical owner composition

- Asset handlers (e.g., `ScriptsHandler`, `StylesHandler`, `MediaHandler`) extend `AssetEnqueueBaseAbstract` and may adopt `HooksManagementTrait` to manage complex hook flows internally.
- `BlockRegistrar` gains the most from manager-driven hooks due to multiple lifecycle hooks (`wp`, `enqueue_block_editor_assets`, `register_block_type_args`, `render_block`).

## Timing model (summary)

See `docs/TFS-003-Library-Initialization-Timing.md` for details. Recommended flow:

1. `plugins_loaded`: construct library components and call their `load()`/`stage()` so that registrations happen before target hooks fire.
2. `init`: userland adds assets and blocks; block registration can occur here or earlier if needed.
3. `wp_enqueue_scripts` / `admin_enqueue_scripts` / `enqueue_block_editor_assets`: actual staging/enqueue.
4. `render_block`: dynamic, per-instance block assets (keep light and idempotent).

## Example: method-based enqueue registration

```php
class ScriptsHandler extends AssetEnqueueBaseAbstract {
    use HooksManagementTrait;

    public function load(): void {
        // Registers enqueue_scripts + admin_enqueue_scripts to call enqueue_scripts()
        $this->_register_asset_hooks('script');
    }

    public function enqueue_scripts(): void {
        // enqueue logic here
    }
}
```

## Example: conditional admin-only registration

```php
$this->_register_admin_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
```

## Example: deferred group

```php
$this->_register_deferred_hooks([
  'wp_head' => [
    'priority' => 10,
    'callback' => [$this, 'render_head'],
    'context'  => ['deferred' => true]
  ],
  'wp_footer' => [
    'priority' => 10,
    'callback' => [$this, 'render_footer'],
    'context'  => ['deferred' => true]
  ],
]);
```

## Introspection and debugging

From any owner using the trait:

- `get_hook_stats()` → totals and duplicates prevented
- `get_registered_hooks()` → unique keys of tracked registrations

## Testing guidance

- Keep wrapper-based boundary expectations (`WP_Mock::expectActionAdded`) where wrappers are used.
- For manager-driven internals, mock `get_hooks_manager()` and set expectations on `register_action` / `register_filter`.
- Prefer method-based helpers in tests (`_register_action_method`, `_register_filter_method`) for clearer intent.

## Best practices

- Initialize components early (`plugins_loaded`), use later hooks for work.
- Prefer trait helpers when a manager exists; provide `context` for diagnostics.
- Avoid mixing wrappers and manager inside the same owner unless clearly justified.
- Keep `render_block` work minimal and idempotent.

## References

- inc/HooksAccessory/docs/TFS-001-HooksManager-and-Trait-Usage.md
- docs/TFS-003-Library-Initialization-Timing.md
