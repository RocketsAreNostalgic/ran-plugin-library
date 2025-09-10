# Contributing Guide for Config Test Suites

This guide documents conventions and patterns for tests under `Tests/Unit/Config/`.
It aims to keep our Config-related tests consistent, maintainable, and reliable.

## Inheritance map (base classes)

```
RanTestCase (defined in Tests/test_bootstrap.php)
├─ PluginLibTestCase (Tests/Unit/PluginLibTestCase.php)
└─ ConfigTestCase (Tests/Unit/Config/ConfigTestCase.php)
```

Note: `ConfigTestCase` and `PluginLibTestCase` are siblings. Neither extends the other; both extend `RanTestCase` directly.

## Base Test Classes: Which one to use

- **ConfigTestCase** (`Tests/Unit/Config/ConfigTestCase.php`)

  - Use when you need a shared `CollectingLogger` and helpers to build hydrated `Config` instances.
  - Helpers provided:
    - `configFromPluginFileWithLogger(string $pluginFile): Config`
    - `configFromThemeDirWithLogger(string $stylesheetDir): Config`
  - Typical for factory/hydration and integration-style tests (e.g., `ConfigFactoryTest.php`, plugin/theme smoke/extended tests).

- **PluginLibTestCase** (`Tests/Unit/PluginLibTestCase.php`)

  - Use when you need shared helpers like `_set_protected_property_value()` and/or a ready `$config_mock` instance.
  - Typical for public interface tests of `ConfigAbstract` and when injecting values into protected caches (e.g., `_unified_cache`).
  - Example: `ConfigAbstractPublicTest.php`, options edge/no-writes suites.

- **RanTestCase** (`Tests/test_bootstrap.php`)
  - Use for small, focused units that do not need the helpers above.
  - Good for provider tests and probe-style tests that directly exercise low-level behavior.
  - Example: `PluginHeaderProviderTest.php`, `ThemeHeaderProviderTest.php`.

Add a short note at the top of a new test explaining the choice if it’s not obvious.

### Choosing your base class (capability summary)

- ConfigTestCase

  - Provides: shared `CollectingLogger`, simple hydrated `Config` builders (plugin/theme) using that logger.
  - Best for: testing `Config` factories and hydration paths where you don’t need singleton wiring or protected property helpers.

- PluginLibTestCase

  - Provides: `_set_protected_property_value()`, `_invoke_protected_method()`, `_removeSingletonInstance()`, `define_constant()`, and a fully registered `ConcreteConfigForTesting` via `SingletonAbstract`.
  - Logger: `CollectingLogger` tied to the config mock; offers `set_logger_mock()` when strict partial logging behavior is needed.
  - Best for: tests that require deeper config scaffolding, protected/private access, or code paths that call `ConfigAbstract::get_instance()`.

- RanTestCase
  - Minimal bootstrap for WP_Mock integration and common assertions such as `expectLog()`.
  - Best for: isolated units (e.g., header providers, narrow probes) where additional scaffolding is unnecessary.

Why some Config tests use PluginLibTestCase: even within `Tests/Unit/Config/`, certain tests intentionally rely on `PluginLibTestCase` for its powerful helpers (protected property access, singleton registration) rather than `ConfigTestCase`. This is a scope choice, not an inheritance one.

## WP_Mock lifecycle and structure

- **Always** balance lifecycle calls:
  - Call `WP_Mock::setUp()` in `setUp()`
  - Call `WP_Mock::tearDown()` in `tearDown()`
- For tests that expect exceptions but still need cleanup, use `try { ... } finally { WP_Mock::tearDown(); }` inside the test method (in addition to the class-level `tearDown()` call).
- Keep mocks as local as possible to avoid conflicts with other tests. Prefer `byDefault()` for shared, uncontroversial stubs (`sanitize_key`, etc.).

## Logger usage and assertions

- Default approach: use `CollectingLogger` and verify with `expectLog()` assertions.
  - `ConfigTestCase` initializes a `CollectingLogger` as `$this->logger_mock`.
  - Prefer this for sequence/content validation and non-strict call counts.
- When you must assert a **single, specific** logger call (e.g., a `warning()` path), it’s acceptable to use a PHPUnit mock for `Logger`:
  - Example from `ConfigOptionsEdgeCasesTest`: strict `expects($this->once())->method('warning')->with('...')`.
  - Document why a PHPUnit mock is used instead of `CollectingLogger`.

## Temporary fixtures (plugin files and theme directories)

- Create fixtures under `sys_get_temp_dir()` with unique names (e.g., `uniqid()` per test/class).
- Write minimal, valid headers:
  - Plugin: `<?php /** Plugin Name: X */` block including `Version` and `Text Domain` where needed.
  - Theme: `style.css` comment block with `Theme Name`, `Version`, `Text Domain`.
- Clean up in `tearDown()`:
  - Remove created files (e.g., `@unlink($plugin_file)`, `array_map('unlink', glob($dir . '/*') ?: [])`).
  - Remove directories (e.g., `@rmdir($dir)`).

## Reflection and probes

- Prefer public interfaces where possible.
- When a protected/internal behavior must be tested, create a small **probe subclass** in the test file to expose protected methods intentionally.
  - Examples: `ConfigAbstractProbe` (protected utilities), `ConfigAbstractHydrator` (hydration internals), and narrow purpose variants.
- Keep probes small and scoped to the test file.

## Mocks for common WP functions

- Common defaults (use `byDefault()` when helpful):
  - `sanitize_key`: lowercases and underscores, e.g., `strtolower(preg_replace('/[^a-z0-9_\-]/i', '_', (string)$v))`.
  - `sanitize_title`: may be needed for default slug generation in some tests.
- Avoid overly broad global mocks that can mask test-specific expectations (e.g., `get_option`). Prefer narrowly scoped expectations inside each test.

## Namespaces and file style

- Prefer file-level `namespace Foo\Bar;` style (no braced namespace blocks) to match the dominant convention in this directory.
- If a test uses block-style namespaces for isolation, add a short comment explaining the reason.

## Patterns worth emulating

- **Hydration integration**: `ConfigFactoryTest.php` shows clear arrange/act/assert, temp resources, WP mocks, and teardown in `finally`.
- **Protected utilities**: `ConfigAbstractProtectedTest.php` isolates internal helpers with a probe and localized WP mocks.
- **Edge cases with options**: `ConfigOptionsEdgeCasesTest.php` demonstrates how to seed `_unified_cache` via reflection and then focus each test on a single branch.

## Optional helpers (future improvement)

- Consider adding test-only helpers/traits for common fixture creation:
  - `createTempPluginFile(array $headers): array { plugin_dir, plugin_file, basename, url }`
  - `createTempThemeDir(array $headers): string`
- Consider lightweight helpers in `ConfigTestCase` for stable default mocks like `mockSanitizeKeyByDefault()` to reduce duplication while avoiding global interference.

## Quick checklist before adding tests

- [ ] Pick the correct base class (`ConfigTestCase`, `PluginLibTestCase`, or `RanTestCase`) and note why if non-obvious.
- [ ] Scope `WP_Mock` expectations to the test; use `byDefault()` only for safe, shared behaviors.
- [ ] Prefer `CollectingLogger` + `expectLog()`; switch to a PHPUnit mock only when strict single-call verification is necessary.
- [ ] Create and clean temp fixtures deterministically.
- [ ] Use probes sparingly and keep them local to the test file.
- [ ] Keep assertions focused; one behavior/branch per test where possible.

## Quick file references

- Base classes:
  - `Tests/test_bootstrap.php` defines `RanTestCase`.
  - `Tests/Unit/PluginLibTestCase.php` defines `PluginLibTestCase` and `ConcreteConfigForTesting`.
  - `Tests/Unit/Config/ConfigTestCase.php` defines `ConfigTestCase`.
