# Config System - Technical Feature Specification

## Document Information

- **Status**: Implemented
- **Date**: 2025-08-14
- **Updated**: 2025-08-14
- **Implementation Priority**: High
- **Technical Complexity**: Medium
- **Dependencies**: Logger system

## Context

The Config system provides a unified, environment-agnostic configuration management solution for both WordPress plugins and themes. It eliminates the need for separate configuration handling between plugins and themes while providing a consistent API for accessing metadata, custom headers, options, and environment detection.

### Problem Statement

Previously, the plugin library had only a single configuration system for plugins, and we wanted to create a versatile configuration system that could support themes as well.

### Decision

Implement a unified Config system with a single `ConfigInterface` and concrete implementations that can hydrate from either plugin files or theme directories, providing normalized configuration data through consistent APIs.

#### Core Architecture

```text
ConfigInterface
├── ConfigAbstract (base implementation)
├── Config (concrete factory class)
├── ConfigType (enum: plugin|theme)
├── HeaderProviderInterface
├── PluginHeaderProvider
└── ThemeHeaderProvider
```

### Key Design Principles

1. **Environment Agnostic**: Single API works for both plugins and themes
2. **Normalized Data**: Consistent key names and structures regardless of source
3. **Custom Headers**: Support for namespaced custom metadata via `@RAN:` annotations
4. **Lazy Loading**: Configuration data loaded and cached on first access
5. **Dependency Injection**: Config instances passed to consuming components
6. **WordPress Integration**: Leverages native WordPress APIs for header parsing

## Implementation Strategy

### Core Components

#### ConfigInterface

```php
interface ConfigInterface {
    public function get_config(): array;
    public function get_options(mixed $default = false): mixed;
    public function get_logger(): Logger;
    public function is_dev_environment(): bool;
    public function get_type(): ConfigType;
}
```

#### Config Factory Class

```php
final class Config implements ConfigInterface {
    public static function fromPluginFile(string $pluginFile): self;
    public static function fromThemeDir(?string $stylesheetDir = null): self;
}
```

#### Header Providers

- `PluginHeaderProvider`: Handles plugin-specific metadata extraction
- `ThemeHeaderProvider`: Handles theme-specific metadata extraction
- Both implement `HeaderProviderInterface` for consistent behavior

### Integration Points

- **Logger System**: Integrated logger configuration via custom headers
- **Options System**: Seamless WordPress options integration
- **Feature System**: Config passed to feature controllers and managers
- **Enqueue System**: Asset management uses config for paths and URLs

### Data Flow

1. **Initialization**: Factory methods create Config instances
2. **Hydration**: Header providers extract metadata from source files
3. **Normalization**: Raw data transformed into consistent structure
4. **Caching**: Normalized data cached for performance
5. **Access**: Consumers retrieve data via interface methods

## API Design

### Public Interface

#### Factory Methods

```php
// Plugin initialization
$config = Config::fromPluginFile(__FILE__);

// Theme initialization
$config = Config::fromThemeDir();
$config = Config::fromThemeDir(get_stylesheet_directory());
```

#### Core Methods

```php
// Get normalized configuration array
$cfg = $config->get_config();

// Get WordPress options payload for this app (entire row)
$options = $config->get_options([]); // default if row is missing

// Get configured logger instance
$logger = $config->get_logger();

// Environment detection
$isDev = $config->is_dev_environment();

// Get environment type
$type = $config->get_type(); // ConfigType::Plugin or ConfigType::Theme
```

### Usage Examples

#### Plugin Usage

```php
<?php
/**
 * Plugin Name: My Plugin
 * Version: 1.0.0
 * Text Domain: my-plugin
 * @RAN: App Option: my_plugin_settings
 * @RAN: Log Constant Name: MY_PLUGIN_DEBUG
 * @RAN: Log Request Param: my_debug
 */

use Ran\PluginLib\Config\Config;

// Initialize from plugin file
$config = Config::fromPluginFile(__FILE__);

// Access normalized configuration
$cfg = $config->get_config();
echo $cfg['Name'];        // "My Plugin"
echo $cfg['Version'];     // "1.0.0"
echo $cfg['PATH'];        // "/path/to/wp-content/plugins/my-plugin/"
echo $cfg['URL'];         // "https://example.com/wp-content/plugins/my-plugin/"
echo $cfg['Type'];        // "plugin"
echo $cfg['Slug'];        // "my-plugin"

// Access custom headers
echo $cfg['RAN']['AppOption'];       // "my_plugin_settings"
echo $cfg['RAN']['LogConstantName']; // "MY_PLUGIN_DEBUG"

// Use integrated services
$logger = $config->get_logger();
$options = $config->get_options();
$isDev = $config->is_dev_environment();
```

#### Theme Usage

```php
<?php
/**
 * Theme Name: My Theme
 * Version: 2.0.0
 * Text Domain: my-theme
 * @RAN: App Option: my_theme_options
 */

use Ran\PluginLib\Config\Config;

// Initialize from theme directory
$config = Config::fromThemeDir();

// Access normalized configuration
$cfg = $config->get_config();
echo $cfg['Name'];           // "My Theme"
echo $cfg['Type'];           // "theme"
echo $cfg['PATH'];           // "/path/to/wp-content/themes/my-theme/"
echo $cfg['StylesheetDir'];  // "/path/to/wp-content/themes/my-theme/"
echo $cfg['StylesheetURL'];  // "https://example.com/wp-content/themes/my-theme/"
```

### Configuration Options

#### Custom Headers

Custom metadata declared using `@RAN:` prefix in file docblocks:

```php
/**
 * @RAN: App Option: my_custom_option_key
 * @RAN: Log Constant Name: MY_DEBUG_CONSTANT
 * @RAN: Log Request Param: debug_param
 * @RAN: API Endpoint: https://api.example.com
 * @RAN: Feature Flag: enable_advanced_features
 */
```

#### Environment Detection

Multiple mechanisms for development environment detection:

1. **Custom Callback**: Developer-provided function (highest priority)
2. **Custom Constant**: Named in `RAN.LogConstantName` (e.g., `MY_DEBUG_FLAG`)
3. **SCRIPT_DEBUG**: WordPress core constant
4. **WP_DEBUG**: WordPress core constant (fallback)

#### Normalized Keys Structure

```php
[
    // Core (both environments)
    'Name' => 'Plugin/Theme Name',
    'Version' => '1.0.0',
    'TextDomain' => 'text-domain',
    'PATH' => '/absolute/path/',
    'URL' => 'https://example.com/path/',
    'Slug' => 'normalized-identifier',
    'Type' => 'plugin|theme',

    // Plugin-specific
    'Basename' => 'plugin-dir/plugin-file.php',
    'File' => '/absolute/path/to/plugin-file.php',

    // Theme-specific
    'StylesheetDir' => '/absolute/theme/path/',
    'StylesheetURL' => 'https://example.com/theme/',

    // Custom headers (namespaced)
    'RAN' => [
        'AppOption' => 'option_key',
        'LogConstantName' => 'DEBUG_CONSTANT',
        'LogRequestParam' => 'debug_param',
        // ... other custom headers
    ],

    // Extra headers (non-reserved)
    'ExtraHeaders' => [
        'CustomField' => 'value',
        // ... other extra headers
    ]
]
```

## Technical Constraints

### Performance Requirements

- **Single File Read**: Configuration files read once and cached
- **Lazy Loading**: Data loaded only when accessed
- **Memory Efficient**: Minimal memory footprint for cached data
- **Request Scoped**: Cache cleared between requests

### Compatibility Requirements

- **WordPress**: 5.0+ (uses modern WordPress APIs)
- **PHP**: 8.1+ (uses modern PHP features like enums, readonly properties)
- **Themes**: Compatible with both classic and block themes
- **Plugins**: Works with standard WordPress plugin structure

### Security Considerations

- **Input Validation**: All header data validated and sanitized
- **File Access**: Only reads from WordPress-approved locations
- **Option Security**: WordPress options API used for data persistence
- **Debug Protection**: Development detection doesn't expose sensitive data

## Implementation Phases

### Phase 1: Core Infrastructure ✅

- [x] ConfigInterface definition
- [x] ConfigAbstract base implementation
- [x] ConfigType enum
- [x] Basic plugin support

### Phase 2: Multi-Environment Support ✅

- [x] Header provider interfaces
- [x] PluginHeaderProvider implementation
- [x] ThemeHeaderProvider implementation
- [x] Factory methods for both environments

### Phase 3: Advanced Features ✅

- [x] Custom header parsing with `@RAN:` namespace
- [x] Logger integration
- [x] Environment detection
- [x] WordPress options integration

### Phase 4: Testing & Documentation ✅

- [x] Comprehensive unit tests
- [x] Integration tests
- [x] API documentation
- [x] Usage examples

### Phase 5: Options Integration (PRD-002)

- [ ] Options integration, to provide a batteries-included experience for managing theme & plugin options
- [ ] Updated test coverage
- [ ] Updated documentation & examples

## Testing Strategy

### Unit Tests

- Header parsing for both plugins and themes
- Custom header extraction and normalization
- Environment detection logic
- Logger integration
- Options integration

### Integration Tests

- End-to-end configuration loading
- WordPress API integration
- Cross-environment compatibility
- Performance benchmarks

### Test Coverage

- 100% line coverage for core Config classes
- Edge case handling for malformed headers
- Error conditions and exception handling
- Memory usage and performance tests

### Planned Features

- Plugin & theme options integration
- Migrations and update handling and examples
- Multi-site support and network-wide configuration
- Configuration validation schemas
- Hot-reloading for development environments
- Configuration export/import functionality

### Extension Points

- Custom header providers for other WordPress environments
- Pluggable environment detection mechanisms
- Configuration transformation filters
- Caching backend abstraction

## Appendix

### Related Documentation

- [PRD-001: Unified Config for Themes & Plugins](PRD-001-Unified-Config-for-themes-plugins.md)
- [PRD-002: Config Options Integration](PRD-002-Config-Options-Integration.md)
- [Config System README](../readme.md)

### Code Examples

See the `examples/` directory for comprehensive usage examples and integration patterns.

### Performance Benchmarks

- Configuration loading: < 1ms for typical plugin/theme
- Memory usage: < 50KB for cached configuration data
- File I/O: Single read per configuration source
