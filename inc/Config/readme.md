# Config System (Plugins & Themes)

The Config system provides a single, environment‑agnostic way to read WordPress metadata, expose normalized paths/URLs, and surface custom headers for both plugins and themes.

It builds on WordPress' own APIs for “blessed” headers and augments them with a simple, namespaced custom‑header mechanism.

## Goals

- Use WordPress to resolve standard header fields (robust and future‑proof).
- Normalize common fields (PATH, URL, Slug, Type) across plugins and themes.
- Support custom headers via namespaced annotations in file comments.
- Provide a single API for options, logging, and dev environment detection.

## Quick start

### Plugins

```php
use Ran\PluginLib\Config\Config;

// Typically from your plugin root (e.g., my-plugin.php)
$config = Config::fromPluginFile(__FILE__);

$cfg = $config->get_config();
// $cfg['Name'], $cfg['Version'], $cfg['PATH'], $cfg['URL'], $cfg['Slug'], $cfg['Type'] === 'plugin'

// Logger (uses RAN.LogConstantName / RAN.LogRequestParam or sane defaults)
$logger  = $config->get_logger();

// Environment detection (callback > constant > SCRIPT_DEBUG > WP_DEBUG)
$isDev   = $config->is_dev_environment();
```

### Themes

```php
use Ran\PluginLib\Config\Config;

// Provide explicit stylesheet directory or allow runtime detection
$config = Config::fromThemeDir(get_stylesheet_directory());

$cfg = $config->get_config();
// $cfg['Name'], $cfg['Version'], $cfg['PATH'] (StylesheetDir), $cfg['URL'] (StylesheetURL), $cfg['Slug'], $cfg['Type'] === 'theme'

$logger = $config->get_logger();
$isDev  = $config->is_dev_environment();
```

## Initialization helpers

- `Config::fromPluginFile(string $pluginFile): Config`
  - Hydrates from the plugin root file (typically `__FILE__` from your main plugin).
- `Config::fromPluginFileWithLogger(string $pluginFile, \Ran\PluginLib\Util\Logger $logger): Config`
  - Same as above, but uses the provided logger during hydration.
- `Config::fromThemeDir(?string $stylesheetDir = null): Config`
  - Hydrates from a theme stylesheet directory. If omitted, it attempts runtime detection (requires WP loaded).
- `Config::fromThemeDirWithLogger(?string $stylesheetDir, \Ran\PluginLib\Util\Logger $logger): Config`
  - Same as above, but uses the provided logger during hydration.

## Normalized structure

`get_config()` returns a single normalized array with these keys:

- Core (both environments)
  - `Name`, `Version`, `TextDomain`
  - `PATH` (base path), `URL` (base URL), `Slug`
  - `Type` (one of: `plugin`, `theme`)
- Plugin‑specific
  - `Basename`, `File`
- Theme‑specific
  - `StylesheetDir`, `StylesheetURL`
- Extra headers (generic, non‑reserved)
  - `ExtraHeaders` (associative array of additional non‑reserved header pairs)
- Namespaced custom headers (see below)
  - Each `@<Namespace>:` block is added as a top‑level object: `RAN`, `Acme`, etc.

Example (plugin):

```php
$cfg = [
  'Name' => 'My Plugin',
  'Version' => '1.2.3',
  'TextDomain' => 'my-plugin',
  'PATH' => '/path/to/wp-content/plugins/my-plugin/',
  'URL'  => 'https://example.com/wp-content/plugins/my-plugin/',
  'Slug' => 'my-plugin',
  'Type' => 'plugin',
  'Basename' => 'my-plugin/my-plugin.php',
  'File' => '/path/to/my-plugin/my-plugin.php',
  'RAN' => [
    'AppOption'       => 'my_plugin',
    'LogConstantName' => 'RAN_LOG',
    'LogRequestParam' => 'ran_log',
  ],
  'ExtraHeaders' => [ /* ... */ ],
];
```

## Custom headers (namespaced)

Custom metadata is declared in the file's first comment block using an `@<Namespace>:` prefix:

```text
@RAN: App Option: my_plugin
@RAN: Log Constant Name: MY_DEBUG_FLAG
@RAN: Log Request Param: my_debug

@Acme: Api Base: https://api.example.com
@Acme: Feature Flag: on
```

The parser produces top‑level namespaces with normalized PascalCase keys:

```php
$cfg['RAN']['AppOption']        === 'my_plugin';
$cfg['RAN']['LogConstantName']  === 'MY_DEBUG_FLAG';
$cfg['RAN']['LogRequestParam']  === 'my_debug';

$cfg['Acme']['ApiBase']         === 'https://api.example.com';
$cfg['Acme']['FeatureFlag']     === 'on';
```

Notes:

- Reserved WP header names are blocked (collision throws).
- Empty namespace/name/value lines are ignored.
- Generic "Key: Value" pairs (without `@<NS>:`) are added to `ExtraHeaders` if they don’t collide with reserved names.

## Options API

```php
// Resolve the app's main option key (prefers RAN.AppOption; else Slug)
$key = $config->get_options_key();
```

App option key resolution:

- Prefer `RAN.AppOption` if present.
- Otherwise default to `Slug`.

### Options accessor (no‑write)

```php
// Pre‑wired options manager for this app
$opts = $config->options([
  // recognized optional args — all staged in‑memory only
  'autoload' => true,                    // policy hint for future writes
  'initial'  => ['enabled' => true],     // values merged on the instance
  'schema'   => ['enabled' => ['default' => false]],
]);

// No writes occur until you call explicit persistence methods
$opts->stage_options(['enabled' => true]);
$opts->flush();
```

- Recognized args: `autoload` (bool, default `true`), `initial` (array<string,mixed>, default `[]`), `schema` (array<string,mixed>, default `[]`).
- This accessor performs no DB writes, seeding, or flushing by itself.
- Unknown args are ignored and a warning is emitted via the configured logger.

See the root `README.md` “Options Management” section for a detailed overview and persistence patterns.

## Logger & environment

```php
$logger = $config->get_logger();
$isDev  = $config->is_dev_environment();
```

- Logger settings from headers (with defaults):
  - `RAN.LogConstantName` (default: `RAN_LOG`)
  - `RAN.LogRequestParam` (default: `ran_log`)
- Development detection precedence:
  1. Developer callback (see below)
  2. Defined debug constant named in `RAN.LogConstantName`
  3. `SCRIPT_DEBUG === true`
  4. `WP_DEBUG === true`

Developer override:

```php
// Inside your filter or bootstrap code, add a callable to config normalized data
add_filter('ran/plugin_lib/config', function(array $normalized) {
  $normalized['is_dev_callback'] = static function (): bool {
    return defined('MY_ENV') && MY_ENV === 'development';
  };
  return $normalized;
});
```

Programmatic override (no filters):

```php
use Ran\PluginLib\Config\Config;

// Plugin
$config = Config::fromPluginFile(__FILE__)
  ->set_is_dev_callback(static function (): bool {
    return defined('MY_ENV') && MY_ENV === 'development';
  });

// Theme
$themeCfg = Config::fromThemeDir(get_stylesheet_directory())
  ->set_is_dev_callback(static function (): bool {
    return current_user_can('manage_options');
  });
```

## WordPress filter for final adjustments

Before validation, the normalized array is passed through a filter if available:

```php
apply_filters('ran/plugin_lib/config', $normalized, [
  'environment'      => 'plugin'|'theme',
  'standard_headers' => [ /* from WP */ ],
  'namespaces'       => [ /* parsed @NS headers */ ],
  'extra_headers'    => [ /* generic non-reserved */ ],
  'base_path'        => '...',
  'base_url'         => '...',
  'base_name'        => '...',
  'comment_source'   => '/absolute/path',
]);
```

Use this to set `is_dev_callback`, adjust normalized values, or derive additional metadata.

## Validation

The following keys must be present and non‑empty:

- Common: `Name`, `Version`, `TextDomain`, `PATH`, `URL`, `Slug`, `Type`
- Plugin: `Basename`, `File`
- Theme: `StylesheetDir`, `StylesheetURL`

If a required field is missing, an exception is thrown with a plain (non‑WP‑escaped) message.

## Performance

- Standard headers are read using WordPress APIs (`get_plugin_data`, `wp_get_theme`).
- Custom headers are parsed from the file’s first comment block (first 8KB).
- To avoid redundant reads within a single request, the comment‑block read is memoized (in‑memory static cache keyed by file path).

## Theme specifics

- Hydration requires a stylesheet directory. If not provided and WP is loaded, the system will try to detect it (`get_stylesheet_directory()`), otherwise hydration throws a clear exception.

## Examples

### Custom headers in a plugin root (my-plugin.php)

```php
/**
 * Plugin Name: My Plugin
 * Description: Example
 * Version: 1.0.0
 * Text Domain: my-plugin
 *
 * @RAN: App Option: my_plugin
 * @RAN: Log Constant Name: MY_DEBUG
 * @RAN: Log Request Param: my_debug
 *
 * @Acme: Api Base: https://api.example.com
 */
```

### Reading values

```php
$config = Ran\PluginLib\Config\Config::fromPluginFile(__FILE__);
$cfg    = $config->get_config();

$cfg['RAN']['AppOption'];       // my_plugin
$cfg['RAN']['LogConstantName']; // MY_DEBUG
$cfg['Acme']['ApiBase'];        // https://api.example.com

$options_key = $config->get_options_key();
$logger  = $config->get_logger();
```

---

For detailed architecture and header parsing semantics, see `docs/PRD-001-Unified-Config-for-themes-plugins.md`.

---
