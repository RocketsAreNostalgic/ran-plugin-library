# Plan: Remove Singleton Assumptions from Config Tests and Scaffolding (No-BC, Direct Edit)

This document defines the direct, sequential edits to eliminate all singleton dependencies around `Config` in tests and scaffolding. As we are pre‑release, there is no migration path or backward compatibility requirement. We will change the shared scaffolding and update tests accordingly in-place.

## Scope

- Tests and scaffolding under `Tests/Unit/` that reference:
  - `ConfigAbstract::get_instance()` and/or legacy `::init()`
  - `SingletonAbstract` registration and teardown from test scaffolding
- Test helper classes under `Tests/Unit/TestClasses/`
- Documentation updates where references exist

## High-Level Objectives

- Replace singleton-based config access with explicit construction/factory DI.
- Remove all `SingletonAbstract` registrations/teardown from test scaffolding.
- Delete test-only `init()`/`get_instance()` helpers.
- Keep the entire suite green by updating usages in the same PR.

## Immediate Direct Edits

1. Remove singleton scaffolding from `PluginLibTestCase`

- File: `Tests/Unit/PluginLibTestCase.php`
- Actions:
  - Delete imports and references to `Ran\PluginLib\Singleton\SingletonAbstract`.
  - Remove `tearDown()` cleanup that calls `_removeSingletonInstance(...)`.
  - Delete the helper method `_removeSingletonInstance()` entirely.
  - Rename `get_and_register_concrete_config_instance()` to `get_concrete_config_instance()` and remove any logic that registers the instance in a singleton registry. It should simply build and return a fully-hydrated config instance (mock or real via factories) and assign it to `$this->config_mock` (or rename to `$this->config`).
  - Ensure `setUp()` uses the returned instance directly without any global registration behavior.

2. Delete singleton-style helper in `TestableConfig`

- File: `Tests/Unit/TestClasses/TestableConfig.php`
- Actions:
  - Remove the static method `init(string $plugin_file = ''): self` that returns `self::get_instance()`.
  - Verify the class no longer exposes or references `get_instance()` in any way.

3. Remove any references to `ConfigAbstract::get_instance()` and legacy `::init()` in comments/docs within tests

- Files:
  - `Tests/Unit/PluginLibTestCase.php` (header/comment blocks)
  - Any other test files referencing these methods in comments/examples
- Actions:
  - Update docblocks to reflect DI/factory usage only.

4. Update all call sites that depend on registered singletons

- Search patterns:
  - `ConfigAbstract::get_instance(`
  - `->get_instance(`
  - `::init(`
  - `SingletonAbstract`
- Actions:
  - Replace with explicit config instance usage available on the test class (e.g., `$this->config_mock` / `$this->config`) and pass into SUT constructors/factories.
  - Where SUT currently has no injection path, prefer using its public factory methods that accept `ConfigInterface`. If none exists, adjust the SUT’s constructor/factory to accept `ConfigInterface` (tests-only change if possible). If production change is required, perform it directly (no BC concern) and update usages.

5. Ensure `ConfigTestCase` remains DI/factory focused

- File: `Tests/Unit/Config/ConfigTestCase.php`
- Actions:
  - No changes expected; it already builds via `Config::fromPluginFileWithLogger` / `Config::fromThemeDirWithLogger`.

6. Remove any Patchwork redefinitions or similar shims for `get_instance()`

- Actions:
  - If any test introduced redefinitions for `ConfigAbstract::get_instance`, delete them.

## Sequencing

1. Apply Edit (1) in `PluginLibTestCase`.
2. Apply Edit (2) in `TestableConfig`.
3. Apply Edit (3) comment/doc cleanups in the same files.
4. Run a global search & replace pass (Edit 4) and fix call sites:
   - Prefer local DI instance from the test case.
   - For SUTs missing DI path, add constructor/factory param `ConfigInterface $config` and update test constructions.
5. Confirm `Config` suites still pass (they should already be DI based).
6. Run the entire suite and resolve remaining references incrementally.

## Validation Commands

- Run full suite from library root `vendor/ran/plugin-lib/`:

```bash
composer test
```

- Focused runs while fixing suites:

```bash
composer test -- --filter Config
composer test -- --filter Options
composer test -- --filter Enqueue
```

- Grep to find remaining singleton references:

```bash
rg -n "ConfigAbstract::get_instance|::init\(|SingletonAbstract" Tests/Unit inc | sed 's/^/FOUND: /'
```

## Editing Guidelines

- Prefer constructing `Config` instances via:
  - `Config::fromPluginFileWithLogger($file, $this->logger_mock)`
  - `Config::fromThemeDirWithLogger($dir, $this->logger_mock)`
- For tests that require a `ConfigAbstract` derivative mock, use PHPUnit mocks but do not register them globally.
- Keep `CollectingLogger` as the standard logger for log assertions.

## Post-Change Cleanup

- Ensure no lingering private helpers exist that imply singleton lifecycle.
- Ensure `tearDown()` across bases does not reference singleton cleanups.
- Update any developer docs within `docs/` that referenced the previous singleton scaffolding to reflect DI-first approach.

## Risks & Mitigation

- Risk: Tests that implicitly relied on global state may fail.
  - Mitigation: Use the config instance stored on the test case and pass it explicitly; add DI hooks to SUTs as needed.
- Risk: Some SUTs have no DI path.
  - Mitigation: Add constructor/factory parameters; we are pre-release and can break BC.

## Completion Criteria

- No references to `ConfigAbstract::get_instance()` or `SingletonAbstract` in tests/scaffolding.
- No test scaffolding registers global instances.
- All suites pass using explicit DI for `Config`.
