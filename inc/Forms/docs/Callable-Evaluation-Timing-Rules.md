# Callable Evaluation Timing Rules

This document defines when callable-valued configuration is evaluated in the Forms system, and what context is provided to those callables.

## Core rule: store callables raw, evaluate only through the invoker

- Callable-valued configuration MUST be stored as the original callable (closure/callable array/invokable object).
- Callables MUST NOT be invoked directly via `$cb()` or `call_user_func(...)` inside templates or render entrypoints.
- Callables MUST be invoked via `FormsCallbackInvoker::invoke($callable, $ctx)` so both arities are supported:
  - `callable(): mixed`
  - `callable(array $ctx): mixed`

## Canonical stored-only $ctx contract

When a callable is evaluated at render-time, it must be passed a curated “stored-only” context array containing only these keys (when available):

- `field_id`
- `container_id`
- `root_id`
- `section_id`
- `group_id`
- `value`
- `values`

Notes:

- `$ctx['values']` represents the stored/saved values payload (not transient “pending” state).
- Callables should not depend on arbitrary render-only keys; those are intentionally excluded from the canonical ctx.

## Timing: where evaluation is allowed

### 1) Template override resolution

- Callable template overrides are evaluated during template resolution.
- Authoritative site: `FormsTemplateOverrideResolver` uses `FormsCallbackInvoker::invoke(...)` when an override is callable.

### 2) Render-time wrapper callbacks (before/after)

- `before` / `after` callbacks for sections/groups/fields/submit zones are evaluated at render-time.
- Authoritative sites:
  - `FormsRenderService` callback helpers (e.g. `_render_callback_output` / `render_callback_output`)
  - `AdminSettings` / `UserSettings` callback helpers (e.g. `_render_callback_output`)

### 3) Render-time callable keys inside component context (e.g. style/description)

- Callable-valued keys such as `style` and `description` are evaluated at render-time.
- Authoritative sites:
  - `FormsRenderService` (field/section resolution)
  - `AdminSettings` / `UserSettings` (page/collection resolution)

### 4) Template-level callables

Templates may receive callable values (e.g. wrapper-level callbacks like `render_submit`) but must still invoke them through the invoker and must pass only the canonical stored-only `$ctx`.

## Practical guidance

- Prefer writing callables that accept `array $ctx` when behavior depends on:
  - another field’s value (`$ctx['values']`)
  - structural identifiers (`field_id`, `section_id`, etc.)

- Keep callables side-effect free and deterministic where possible, since they may be evaluated multiple times per request.

## Anti-patterns (do not do this)

- Direct invocation inside templates:
  - `$renderSubmit()`
- Invoking with a non-canonical context payload:
  - passing the full render context / including transient keys
- Using `call_user_func(...)` to execute stored configuration callables
