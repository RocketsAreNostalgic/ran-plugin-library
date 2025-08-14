# PRD: Unified Config System for Plugins & Themes

- Area: `plugin-lib/inc/Config/*`
- Related: `inc/FeaturesAPI/*`, `inc/EnqueueAccessory/*`, `inc/Config/readme.md`
- Date: 2025-08-11

## Overview

- What: A single, environment-agnostic configuration system that initializes from either a WordPress plugin or a theme and exposes a normalized configuration surface for consumers (Features, Enqueue, etc.).
- Why: Reduce friction and duplication by having one Config that works across environments; simplify docs and usage; avoid environment leakage into higher layers.
- Who: Library users integrating Ran Plugin Lib in plugins or themes; internal consumers `FeaturesAPI`, `EnqueueAccessory`.

## Objectives

- Provide a single `ConfigInterface` and one concrete `Config` supporting both plugin and theme sources.
- Offer simple, explicit initialization for each environment.
- Return a normalized config array (`get_config()`) with consistent keys regardless of environment.
- Provide neutral naming (no “plugin-only” terms) since this must work in themes as well.
- OK to rename methods/keys (alpha, no BC constraints).

## Non-goals

- Multi-environment beyond plugin/theme (e.g., mu-plugins) in this iteration.
- Changing consumer APIs beyond agreed renames and straightforward replacements.

## Public API (proposed)

- `ConfigInterface`

  - `public function get_config(): array<string,mixed>`
  - `public function get_option(?string $optionKey = null, mixed $default = false): mixed`
  - `public function get_logger(): Ran\PluginLib\Util\Logger`
  - `public function is_dev_environment(): bool`
  - `public function get_type(): Ran\PluginLib\Config\ConfigType`

- `final class Config implements ConfigInterface`
  - Factories:
    - `public static function fromPluginFile(string $pluginFile): self`
    - `public static function fromThemeDir(?string $stylesheetDir = null): self` (defaults to `get_stylesheet_directory()`)
  - Introspection:
    - `public function get_type(): ConfigType`

## Normalized Keys (returned by `get_config()`)

- Common keys

  - `Name` (string)
  - `Version` (string)
  - `TextDomain` (string)
  - `PATH` (string) absolute base path
  - `URL` (string) base URL
  - `Slug` (string) normalized identifier (typically sanitized `TextDomain`)
  - `RANAppOption` (string) app-wide option name derived from `TextDomain`/slug
  - `Type` (string: `plugin`|`theme`) for diagnostics
  - Logging: `RANLogConstantName`, `RANLogRequestParam`

- Plugin-specific additions

  - `Basename` (string) plugin basename
  - `File` (string) plugin root file

- Theme-specific additions
  - `StylesheetDir` (string) absolute path
  - `StylesheetURL` (string) URL
  - Other `wp_get_theme()` headers normalized where sensible

Notes:

- Keys are neutral and stable across environments. Environment-only keys are additive.

## Architecture

- Enum selector: `enum ConfigType: string { Plugin = 'plugin'; Theme = 'theme'; }`
- Single concrete `Config` with two factory constructors and two hydrators:
  - `private function hydratePlugin(string $pluginFile): void`
  - `private function hydrateTheme(string $stylesheetDir): void`
- Internally normalize all values into one `private array $config_array;`
- Expose `$config_array` via `get_config()`; keep it readonly at the object level.

Rationale:

- Minimal moving parts, easy to test, matches current needs. We can extract providers/traits later if more environments appear.

## Initialization Flows

- Plugin

  - `Config::fromPluginFile(__FILE__)`
  - Uses `get_plugin_data()`, `plugin_dir_path()`, `plugin_dir_url()`, `plugin_basename()`

- Theme
  - `Config::fromThemeDir()` or `Config::fromThemeDir(get_stylesheet_directory())`
  - Uses `wp_get_theme()`, `get_stylesheet_directory()`, `get_stylesheet_directory_uri()`

## Method Renames (no BC required)

- `get_plugin_config()` → `get_config()`
- `get_plugin_options()` → `get_option()`
- Internal property on consumer base classes: `$plugin_array` → `$config_array`
- Option key: `RANPluginOption` → `RANAppOption`

## Consumer Impact

- `FeaturesAPI`

  - `FeatureControllerAbstract` reads from `$config_array` instead of `$plugin_array`.
  - Docs updated to reference `ConfigInterface` and `get_config()`.

- `EnqueueAccessory`
  - Where traits call `$this->get_config()->is_dev_environment()`, standardize to
    `AssetEnqueueBaseAbstract::$config` or add `protected function get_config(): ConfigInterface` in the base and call that.

## Usage Examples

- Plugin

```php
use Ran\PluginLib\Config\Config;

$config = Config::fromPluginFile(__FILE__);
$data   = $config->get_config();
$value  = $config->get_option();
```

- Theme

```php
use Ran\PluginLib\Config\Config;

$config = Config::fromThemeDir();
$data   = $config->get_config();
```

## Tasks / Roadmap

1. Implement `ConfigType` (already present)
2. Implement `Config` class with:
   - Factories: `fromPluginFile()`, `fromThemeDir()`
   - Hydrators: `hydratePlugin()`, `hydrateTheme()`
   - API: `get_config()`, `get_option()`, `get_logger()`, `is_dev_environment()`, `get_type()`
3. Rename internal arrays and references in `FeaturesAPI` and `EnqueueAccessory` to `$config_array`
4. Update docs:
   - `inc/Config/readme.md` to reflect unified approach
   - `inc/FeaturesAPI/readme.md` paragraph(s) to remove `Plugin::get_plugin()` and reference `ConfigInterface` and `get_config()`
5. Add unit tests for both plugin and theme hydration paths (WP bootstrap/shims as needed) - 100% coverage is required, follow existing patterns, and docs/TFS-001-Testing-Patterns-Public-Interface-Testing.md for guidance.
6. Create developer documentation:
   - Update `inc/Config/readme.md`
   - Add `inc/Config/docs/TFS-001-Unified-Config.md` (technical spec)
7. Add examples folder with common use cases:
   - `inc/Config/docs/examples/` including plugin init, theme init, option retrieval, logger usage, env detection

## Risks & Mitigations

- Theme metadata parity: Some plugin headers do not exist for themes. Normalize best-effort and document which keys may be absent. Tests must cover both paths.
- Option key derivation: Ensure `RANAppOption` derivation is deterministic; prefer sanitized `TextDomain` fallback.
- Trait/base references in Enqueue: Add a single accessor or consistently use `$this->config` to avoid ambiguity.

## Acceptance Criteria

- One `Config` class supports plugin/theme via explicit factory methods.
- `get_config()` returns normalized keys listed above for both environments.
- `get_option()` works using `RANAppOption` by default and respects `$default`.
- `get_type()` returns the correct `ConfigType`.
- `FeaturesAPI` and `EnqueueAccessory` compile and run with the renamed `$config_array` and new method names.
- Documentation:
  - `inc/Config/readme.md` updated to environment-agnostic language
  - `inc/Config/docs/TFS-001-Unified-Config.md` created with technical details
- Examples:
  - `inc/Config/docs/examples/` contains working examples for common scenarios (plugin init, theme init, options, logger, env)
- Tests:
  - Full test suite for Config with 100% coverage for the Config module, follow existing patterns, and docs/TFS-001-Testing-Patterns-Public-Interface-Testing.md for guidance.

## References

- `inc/Config/ConfigType.php`
- WP Plugin headers: `get_plugin_data()`
- WP Theme data: `wp_get_theme()`
