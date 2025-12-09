# Contributing Guide (General)

This short guide describes general testing and contribution practices for `ran/plugin-lib`.
For area-specific guidance, see area-specific CONTRIBUTING.md files, eg Options tests: `Tests/Unit/Options/CONTRIBUTING.md`

Genearal testing philosophy and patterns are outlined in: `docs/TFSs/TFS-001-Testing-Patterns-Public-Interface-Testing.md`

## General Practices

- Prefer public interface testing. Use reflection only for setup or documented exceptions.
- Keep tests deterministic. Avoid relying on global state or prior test side effects.
- Use the lightest appropriate base test class (see below).
- Place assertions on behavior first, then verify logs as needed.
- Keep mocks scoped to the test. Use class-level defaults with `->byDefault()` only when they won’t mask specific expectations.
- Do not call `Mockery::close()` in child tests; base teardown handles it.

## Base Test Class Selection (Quick)

- `PHPUnit\\TestCase` (vanilla):
  - Pure PHP logic; no WordPress APIs; no logger/assert-log needs.
- `RanTestCase` (framework-level):
  - Need WP_Mock lifecycle only; no plugin config/logging scaffolding.
- `PluginLibTestCase` (library-level):
  - Any test that calls/stubs WordPress APIs, asserts logs, or needs plugin context & helpers.
- Targeted SUT bases (e.g., EnqueueTraitTestCase):
  - Use when a domain provides a dedicated base with realistic fixtures/mocks.

See “Selecting the Appropriate Base Test Class” in
`docs/TFSs/TFS-001-Testing-Patterns-Public-Interface-Testing.md` for details and a decision checklist.

## WordPress Mocking Patterns

Set broad defaults in `setUp()` using `->byDefault()` when multiple tests need safe fallbacks, then override within tests as needed:

```php
WP_Mock::userFunction('get_option')->andReturn([])->byDefault();
WP_Mock::userFunction('get_site_option')->andReturn([])->byDefault();
WP_Mock::userFunction('get_blog_option')->andReturn([])->byDefault();
WP_Mock::userFunction('get_user_option')->andReturn([])->byDefault();
WP_Mock::userFunction('get_user_meta')->andReturn([])->byDefault();
WP_Mock::userFunction('wp_load_alloptions')->andReturn([])->byDefault();
```

- Avoid permissive global mocks that return the provided default for all calls (they can shadow specific `with(...)` expectations).
- For storage adapters, use precise `with(...)` and `once()` expectations (e.g., `add_blog_option`, `get_blog_option`, `update_blog_option`, `delete_blog_option`).

## Logging

- Use `CollectingLogger` (provided by `PluginLibTestCase`) and `ExpectLogTrait` for log verification.
- Assert logs **after** the SUT has run (post-execution verification), not before.
- For constructor-time log assertions, inject the logger via a `ConfigInterface` double.

## Running Tests

From the library root (`vendor/ran/plugin-lib`):

- Run all tests:

  ```bash
  composer test
  ```

- Run a specific test class or method:

  ```bash
  composer test -- --filter RegisterOptionsUtilityTest
  composer test -- --filter RegisterOptionsUtilityTest::test_migrate_with_array_result
  ```
