# Contributing Guide for Options Test Suites

This guide documents conventions and scaffolding for tests under
`Tests/Unit/Options/`. It explains Options-specific patterns so new tests are consistent and reliable.

## Base class selection

- Most Options tests should extend `Tests/Unit/PluginLibTestCase.php`.

  - This provides `WP_Mock` lifecycle, a shared `CollectingLogger`, helpers for
    protected/private access, and a configured plugin context.
  - Use `ExpectLogTrait` for verifying logs.

- When to use `PHPUnit\Framework\TestCase`

  - If the unit is pure logic with no WordPress interaction and no need for our
    helpers (e.g., simple value objects).
  - Example: `WriteContextTest.php`.

- When to use `RanTestCase`

  - If you only need balanced `WP_Mock` lifecycle without the full plugin
    configuration/logging scaffolding provided by `PluginLibTestCase`.

- Targeted base cases
  - If a domain-specific base exists for the SUT (e.g., enqueue traits use
    `EnqueueTraitTestCase`), prefer that for realistic fixtures and reduced
    boilerplate. For Options, `PluginLibTestCase` is typically sufficient.

## Default WordPress stubs (patterns)

Options tests frequently interact with WordPress option APIs via storage
adapters or `RegisterOptions`. To avoid brittle tests and allow per-test
specific overrides, set broad defaults in `setUp()` using `->byDefault()`:

```php
WP_Mock::userFunction('get_option')->andReturn([])->byDefault();
WP_Mock::userFunction('get_site_option')->andReturn([])->byDefault();
WP_Mock::userFunction('get_blog_option')->andReturn([])->byDefault();
WP_Mock::userFunction('get_user_option')->andReturn([])->byDefault();
WP_Mock::userFunction('get_user_meta')->andReturn([])->byDefault();
WP_Mock::userFunction('wp_load_alloptions')->andReturn([])->byDefault();
```

- Use specific `with(...)` expectations inside test methods to override these
  defaults for that test only.
- Avoid global, permissive mocks that return the provided default value for all
  calls (e.g., returning `$default` unconditionally), as they can shadow
  specific expectations in tests.

Key normalization helper used in many suites:

```php
WP_Mock::userFunction('sanitize_key')->andReturnUsing(function ($key) {
  $key = strtolower((string) $key);
  $key = preg_replace('/[^a-z0-9_\-]+/i', '_', $key) ?? '';
  return trim($key, '_');
});
```

## Logger usage

- Default: rely on the `CollectingLogger` installed by `PluginLibTestCase` and
  verify messages with `ExpectLogTrait::expectLog()`.
- Prefer post-execution log assertions (run SUT, then assert logs). This keeps
  expectations aligned to our logging pattern and ensures assertions observe
  emitted logs.
- For constructor-time logs, provide the logger via a `ConfigInterface` double
  so logs are captured during construction.

## Cleanup and lifecycle

- `PluginLibTestCase::tearDown()` handles `Mockery::close()` and
  `WP_Mock::tearDown()`. Do not call `Mockery::close()` again in child tests.
- Keep test-specific mocks local to avoid conflicts; use `byDefault()` for safe
  shared stubs.

## Storage adapter testing patterns

- Use precise expectations for adapter methods that call WordPress functions:

```php
$blog_id = 5;
WP_Mock::userFunction('add_blog_option')
  ->once()->with($blog_id, 'foo', 'bar')->andReturn(true);
WP_Mock::userFunction('get_blog_option')
  ->once()->with($blog_id, 'foo', false)->andReturn('bar');
WP_Mock::userFunction('update_blog_option')
  ->once()->with($blog_id, 'foo', 'baz')->andReturn(true);
WP_Mock::userFunction('delete_blog_option')
  ->once()->with($blog_id, 'foo')->andReturn(true);
```

- For `supports_autoload()` behavior, control `get_current_blog_id()` to
  deterministically test both true and false cases.

## RegisterOptions testing patterns

- Constructor safety: ensure no writes happen during construction by stubbing
  write functions as `->never()` and/or overriding wrappers in a test double to
  throw on writes.
- Scope/autoload semantics: use `OptionScope` and `get_current_blog_id()` to
  test current vs. non-current blog paths.
- Schema behavior: verify normalization, sanitization, validation, and default
  seeding through public methods.
- Write gate filters: use `WP_Mock::onFilter('ran/plugin_lib/options/allow_persist')`
  and the scope-specific variant to allow or veto writes deterministically.
- Migrations/flush: create a storage mock implementing `OptionStorageInterface`
  and control `read()/update()` return values to exercise merge/no-merge paths.

## Policy testing patterns

- Policies often depend on current user/capabilities.
- Use targeted `current_user_can` and `get_current_user_id` expectations in
  tests. Keep them specific to the scenario; avoid global mocks that can mask
  test intentions.

## Quick references

- Base classes:

  - `Tests/test_bootstrap.php` → defines `RanTestCase`
  - `Tests/Unit/PluginLibTestCase.php` → defines `PluginLibTestCase` and
    `ConcreteConfigForTesting`

- Representative tests:
  - Options core: `RegisterOptionsConstructorTest.php`, `RegisterOptionsReadTest.php`,
    `RegisterOptionsSeedingTest.php`, `RegisterOptionsUtilityTest.php`
  - Gate/Policies: `RegisterOptionsGateTest.php`,
    `Policy/RestrictedDefaultWritePolicyTest.php`,
    `Policy/ExampleSubscriberWritePolicyTest.php`
  - Storage adapters: `Storage/BlogOptionStorageTest.php`,
    `Storage/SiteOptionStorageTest.php`, `Storage/NetworkOptionStorageTest.php`,
    `Storage/UserOptionStorageTest.php`, `Storage/UserMetaStorageTest.php`,
    `Storage/OptionStorageFactoryTest.php`
