# Contributing Guide for EnqueueAccessory Test Suites

This guide documents conventions and scaffolding for tests under
`Tests/Unit/EnqueueAccessory/`. It complements the Config tests guide and
explains Enqueue-specific patterns so new tests are consistent and reliable.

## Base class selection

- `Tests/Unit/EnqueueAccessory/EnqueueTraitTestCase.php` extends
  `Tests/Unit/PluginLibTestCase.php`, which in turn extends `RanTestCase`
  (`Tests/test_bootstrap.php`).

- **Why PluginLibTestCase?**

  - Provides powerful helpers required by Enqueue tests:
    - `_set_protected_property_value()` / `_invoke_protected_method()`
    - `define_constant()`
    - A ready-to-use `ConcreteConfigForTesting` instance for DI
  - Centralizes `WP_Mock` lifecycle and a shared `CollectingLogger`.
  - Ensures consistent config scaffolding; tests should inject the config
    instance into the SUT (no singleton access).

- **When to use `PluginLibTestCase` directly**
  - Orchestrator/handler tests like `EnqueueAdminTest.php` and
    `EnqueuePublicTest.php` that do not need the trait-specific base but do need
    the config scaffolding and logger.

## Default WP stubs set in EnqueueTraitTestCase

`EnqueueTraitTestCase::setUp()` configures a shared test environment. Highlights:

- **Config and logger**

  - Mocks a `ConfigInterface` (`$this->config_mock`) with sensible defaults:
    - `get_is_dev_callback()` returns `null`
    - `is_dev_environment()` returns `false`
    - `get_logger()` returns the `CollectingLogger` created by `PluginLibTestCase`

- **Concrete under test**

  - Creates a partial mock of the concrete enqueue class and wires:
    - `get_logger()` → shared logger
    - `get_config()` → the `ConfigInterface` mock
    - `_get_hooks_manager()` → a mock of `HooksManager` (see below)
    - `_get_asset_url($src)` → identity function for stable testing

- **Hooks manager**

  - `$this->hooks_manager_mock` is a Mockery mock of
    `Ran\PluginLib\HooksAccessory\HooksManager` with permissive defaults:
    - `register_action()`/`register_filter()` return `true` by default

- **WordPress function defaults**

  - Asset-related (parameterized by asset type):
    - `wp_register_{asset_type}` → returns `true`
    - `wp_add_inline_{asset_type}` → returns `true`
    - `wp_{asset_type}_is` → returns `false`
  - Generic utility shims:
    - `did_action()` → `0`
    - `current_action()` → `null`
    - `is_admin()` → `false`
    - `wp_doing_ajax()` → `false`
    - `_doing_it_wrong()` → `null`
    - `wp_json_encode()` → `json_encode`
    - `esc_attr()` / `esc_html()` → basic HTML escaping

- **Script/style specifics in concrete tests**
  - Individual trait tests (e.g., `ScriptsEnqueueTraitTest.php`) add specific
    stubs such as `wp_enqueue_script()` as needed.

## Hooks manager expectations via helpers

Use the dedicated helpers from `EnqueueTraitTestCase` when you want to verify
that the SUT registers callbacks on the internal hooks manager:

- `expectAction(string $hook, int $priority = 10, int $times = 1, int $acceptedArgs = 1)`
- `expectFilter(string $hook, int $priority = 10, int $times = 1, int $acceptedArgs = 1)`

These assert calls to `HooksManager::register_action()` or
`HooksManager::register_filter()` with a `callable` and the specified parameters.
Prefer these when testing that the trait coordinates with our internal hooks
manager abstraction.

## When to use WP_Mock::expectActionAdded directly

Use `WP_Mock::expectActionAdded($hook, $callback, $priority, $acceptedArgs)`
when you need to verify WordPress action registration at the WP level rather
than through the hooks manager abstraction. Examples:

- Orchestrator/handler classes (`EnqueueAdmin`, `EnqueuePublic`) that wire
  `stage()` to WP hooks such as `admin_enqueue_scripts` or `wp_enqueue_scripts`.
- Any path where the SUT intentionally calls `add_action()` directly rather than
  delegating to the hooks manager.

Guideline: **Prefer hooks manager helpers** for internal coordination checks; **use
WP_Mock expectations** when asserting direct WP integration behavior.

## Logger usage

- Default: rely on the `CollectingLogger` installed by `PluginLibTestCase` and
  verify messages with `ExpectLogTrait::expectLog()`.
- It’s acceptable to directly inspect `get_logs()` for absence checks or
  complex assertions, but consider adding a small helper if that pattern repeats.

## Cleanup and lifecycle

- `PluginLibTestCase::tearDown()` handles `Mockery::close()` and
  `WP_Mock::tearDown()`. Avoid calling `Mockery::close()` again in child tests.
- Keep test-specific mocks local to avoid conflicts; use `byDefault()` for safe
  shared stubs.

## Quick references

- Base classes:

  - `Tests/test_bootstrap.php` → defines `RanTestCase`
  - `Tests/Unit/PluginLibTestCase.php` → defines `PluginLibTestCase` and
    `ConcreteConfigForTesting`
  - `Tests/Unit/EnqueueAccessory/EnqueueTraitTestCase.php` → Enqueue shared base

- Representative tests:
  - `Tests/Unit/EnqueueAccessory/ScriptsEnqueueTraitTest.php`
  - `Tests/Unit/EnqueueAccessory/StylesEnqueueTraitTest.php`
  - `Tests/Unit/EnqueueAccessory/EnqueueAdminTest.php`
  - `Tests/Unit/EnqueueAccessory/EnqueuePublicTest.php`
