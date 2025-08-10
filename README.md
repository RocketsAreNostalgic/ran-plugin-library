# RAN PluginLib

RAN PluginLib is a (work in progress) library designed to accelerate WordPress plugin development by providing a robust foundation for common plugin tasks, including configuration management, asset enqueuing, and feature organization.

**BETA - THIS IS A CONCEPTUAL AND IS NOT INTENDED FOR PRODUCTION USE.**

An example implementation is available at <https://github.com/RocketsAreNostalgic/ran-starter-plugin>

## Features

- **Configuration Management:** Easily load plugin metadata and custom configuration from your main plugin file's docblock.
- **Asset Enqueuing Accessory:** A flexible system for adding and managing CSS and JavaScript files for both admin and public-facing pages, with support for conditions, dependencies, inline scripts, and deferred loading.

- **Hooks Accessory:** A system to organize plugin filter and action hooks.
- **Features API:** A system to organize plugin 'features' into distinct classes with Dependency Injection support.
- **Block Management:** A system to register plugin 'blocks', with comprehensive asset management.

## Utilities

- **Logging:** Built-in PSR-3 compatible logger, configurable via plugin headers or constants, to aid in development and debugging.
- **Options Management:** A system to register plugin 'options', with comprehensive schema management, and dynamic default seeding.

- **WordPress Coding Standards Compliant:** Developed with WordPress coding standards in mind.

## Getting Started

### Prerequisites

- PHP 8.1+
- WordPress 6.7+
- Composer (for managing dependencies if you distribute your library this way)

### Installation

1. **Include the Library:**
   The most common way to use PluginLib is by including it as a vendor library via Composer:

   ```bash
   composer require ran/plugin-lib
   ```

   Ensure your plugin includes the Composer `vendor/autoload.php` file:

   ```php
   // In your main plugin file (e.g., my-plugin.php)
   if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
       require_once __DIR__ . '/vendor/autoload.php';
   }
   ```

### Basic Setup: Your Main Plugin File

Your main plugin file (e.g., `my-awesome-plugin.php`) is where you'll define essential metadata and custom configuration values in its header docblock. `PluginLib`'s `Config` class will parse this.

```php
<?php
/**
 * Plugin Name: My Awesome Plugin
 * Plugin URI: https://example.com/my-awesome-plugin
 * Description: Does awesome things with WordPress.
 * Version: 1.0.0
 * Requires at least: 6.7
 * Requires PHP: 8.1
 * Author: Your Name
 * Author URI: https://example.com
 * Text Domain: my-awesome-plugin
 * Domain Path: /languages
 *
 * === PluginLib Configuration ===
 * Log Constant Name: MY_AWESOME_PLUGIN_DEBUG
 * Log Request Param: my_awesome_debug
 * Custom Config Value: Some setting specific to my plugin
 */

declare(strict_types = 1);

namespace MyAwesomePlugin;

defined( 'ABSPATH' ) || die( 'No direct access allowed.' );

// Require Composer Autoload if you're using it
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// 1. Initialize PluginLib Config
// Replace `MyAwesomePlugin\Base\Config` with your plugin's class that extends `Ran\PluginLib\Config\ConfigAbstract`
$plugin_config = MyAwesomePlugin\Base\Config::init( __FILE__ );

// 2. Bootstrap your plugin
// `MyAwesomePlugin\Base\Bootstrap` would implement `Ran\PluginLib\BootstrapInterface`
add_action(
    'plugins_loaded', // Or another appropriate hook
    function (): void {
        // Pass the singleton Config instance to your Bootstrap class
        $bootstrap = new MyAwesomePlugin\Base\Bootstrap( MyAwesomePlugin\Base\Config::get_instance() );
        $bootstrap->init();
    },
    0 // Adjust priority as needed
);

// Activation/Deactivation hooks (optional, but good practice)
register_activation_hook( __FILE__, __NAMESPACE__ . '\activate_plugin' );
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\deactivate_plugin' );

function activate_plugin(): void {
    // MyAwesomePlugin\Base\Activate::activate( MyAwesomePlugin\Base\Config::get_instance() );
}

function deactivate_plugin(): void {
    // MyAwesomePlugin\Base\Deactivate::deactivate( MyAwesomePlugin\Base\Config::get_instance() );
}
```

### Core Components

#### 1. Configuration (`Ran\PluginLib\Config\ConfigAbstract`)

Your plugin should have a class that extends `Ran\PluginLib\Config\ConfigAbstract`. This class becomes the central point for accessing plugin configuration.

**Example: `my-awesome-plugin/inc/Base/Config.php`**

```php
<?php
namespace MyAwesomePlugin\Base;

use Ran\PluginLib\Config\ConfigAbstract;
use Ran\PluginLib\Config\ConfigInterface;

final class Config extends ConfigAbstract implements ConfigInterface {
    // Usually, no additional implementation is needed here for basic usage.
    // PluginLib handles parsing standard WP headers and your custom headers.
}
```

**Accessing Configuration:**

Once `MyAwesomePlugin\Base\Config::init(__FILE__)` is called, you can access the configuration:

```php
// In your Bootstrap or other classes, after injecting/retrieving the Config instance:
$config_instance = MyAwesomePlugin\Base\Config::get_instance();
$plugin_data = $config_instance->get_plugin_config();

$plugin_name = $plugin_data['Name']; // "My Awesome Plugin"
$custom_setting = $plugin_data['CustomConfigValue']; // "Some setting specific to my plugin"

// Accessing the logger
$logger = $config_instance->get_logger();
$logger->info('Plugin initialized.');
```

**Supported Headers for Logger Configuration:**

- `Log Constant Name`: Defines the PHP constant (e.g., `MY_AWESOME_PLUGIN_DEBUG`) that can be used to set the log level.
- `Log Request Param`: Defines the URL query parameter (e.g., `?my_awesome_debug=DEBUG`) that can be used to set the log level.

#### 2. Bootstrapping (`Ran\PluginLib\BootstrapInterface`)

Create a `Bootstrap` class in your plugin that implements `Ran\PluginLib\BootstrapInterface`. This class is responsible for initializing various parts of your plugin, such as enqueuing assets and setting up features.

**Example: `my-awesome-plugin/inc/Base/Bootstrap.php`**

```php
<?php
namespace MyAwesomePlugin\Base;

use Ran\PluginLib\BootstrapInterface;
use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\EnqueueAccessory\EnqueueAdmin; // For admin assets
use Ran\PluginLib\EnqueueAccessory\EnqueuePublic; // For public assets
use Ran\PluginLib\Util\Logger;

class Bootstrap implements BootstrapInterface {

    private ConfigInterface $config;
    private array $plugin_data;
    private Logger $logger;

    public function __construct( ConfigInterface $config ) {
        $this->config = $config;
        $this->plugin_data = $this->config->get_plugin_config();
        $this->logger = $this->config->get_logger();
    }

    /**
     * Initialize the plugin.
     *
     * This method is called by the WordPress `plugins_loaded` action hook.
     *
     * @return ConfigInterface The configuration object.
     */
    public function init(): ConfigInterface {
        $this->logger->info( $this->plugin_data['Name'] . ' - Bootstrap init.' );
        $this->stage_assets();
        // $this->initialize_features();
        return $this->config;
    }

    /**
     * Get the logger instance.
     *
     * @return Logger The logger instance.
     */
    public function get_logger(): Logger {
        return $this->logger;
    }

    /**
     * Enqueue assets for the plugin.
     *
     * @return void
     */
    private function stage_assets(): void {
        // Public-facing assets
        $public_assets = new EnqueuePublic( $this->config );
        $public_assets->add_styles([
            [
                'handle' => 'my-plugin-public-style',
                'src'    => $this->plugin_data['URL'] . 'assets/css/public.css',
                'deps'   => [],
                'version'=> $this->plugin_data['Version'],
            ]
        ]);
        $public_assets->add_scripts([
            [
                'handle'    => 'my-plugin-public-script',
                'src'       => $this->plugin_data['URL'] . 'assets/js/public.js',
                'deps'      => ['jquery'],
                'version'   => $this->plugin_data['Version'],
                'in_footer' => true,
            ]
        ]);
        $public_assets->load(); // Registers WordPress hooks

        // Admin assets (conditionally loaded)
        $admin_assets = new EnqueueAdmin( $this->config );
        $is_my_admin_page = function() {
            return isset( $_GET['page'] ) && 'my-awesome-plugin-settings' === $_GET['page'];
        };
        $admin_assets->add_styles([
            [
                'handle' => 'my-plugin-admin-style',
                'src'    => $this->plugin_data['URL'] . 'assets/css/admin.css',
                'condition' => $is_my_admin_page,
            ]
        ]);
        $admin_assets->load();
    }
}
```

#### 3. Enqueuing Assets (`Ran\PluginLib\EnqueueAccessory\*`)

The `EnqueueAbstract` class and its children (`EnqueuePublic`, `EnqueueAdmin`) provide a structured way to manage your CSS and JavaScript.

- **`EnqueuePublic`:** Use for assets loaded on the public-facing side of your site. Hooks into `wp_enqueue_scripts`.
- **`EnqueueAdmin`:** Use for assets loaded in the WordPress admin area. Hooks into `admin_stage_scripts`.

#### Key Methods

##### `add_scripts(array $scripts_to_add)`

Adds one or more script definitions to the enqueuer instance's internal list. Each script definition is an associative array detailing the script's properties (e.g., handle, source URL, dependencies, version, and whether to load in the footer).

##### `add_styles(array $styles_to_add)`

Adds one or more style definitions to the enqueuer instance's internal list. Each style definition is an associative array detailing the style's properties (e.g., handle, source URL, dependencies, version, and media type).

##### `add_inline_scripts(array $inline_scripts_to_add)`

Adds one or more inline script definitions to the enqueuer instance's internal list. This is useful for adding small JavaScript snippets or localizing data for a registered script. Each inline script definition is an associative array.

##### `load()`

This is a crucial method that finalizes the asset registration process. It iterates through all script, style, and inline script definitions that have been added to the enqueuer instance and registers the appropriate WordPress action hooks (e.g., `wp_enqueue_scripts` for public assets or `admin_stage_scripts` for admin assets). These hooks ensure that WordPress enqueues the assets at the correct time during page load.

**Important:** You must call `load()` once for each enqueuer instance (e.g., once for public assets and once for admin assets if you're using separate instances). This call should be made _after_ all `add_scripts()`, `add_styles()`, and `add_inline_scripts()` calls for that specific instance have been completed. The `load()` method is what actually instructs WordPress to process your asset list and include them on the page.

**Script/Style Array Structure:**

Each script or style is an associative array. Refer to the PHPDoc in `EnqueueAbstract.php` for all available keys (e.g., `handle`, `src`, `deps`, `version`, `in_footer`, `media`, `condition`, `attributes`, `hook` for deferred scripts).

Example:

```php
$public_assets->add_scripts([
    [
        'handle'    => 'my-special-script',
        'src'       => $this->plugin_data['URL'] . 'assets/js/special.js',
        'deps'      => ['jquery'],
        'version'   => $this->plugin_data['Version'],
        'in_footer' => true,
        'condition' => function() { // Only load if a specific condition is met
            return is_singular('post');
        },
        'attributes' => [ // Add custom attributes to the script tag
            'defer' => true,
            'data-custom' => 'my-value'
        }
    ]
]);
```

## Advanced Usage

### Deferred Script Loading

You can defer loading of non-critical scripts until a specific action hook fires.
When adding a script, include the `'hook'` key:

```php
$public_assets->add_scripts([
    [
        'handle' => 'my-deferred-script',
        'src'    => $this->plugin_data['URL'] . 'assets/js/deferred.js',
        'hook'   => 'wp_footer', // Or any other action hook
        // ... other params
    ]
]);
```

The `EnqueueAbstract::load()` method will automatically set up the necessary `add_action` calls.

### Inline Scripts & Data (`wp_localize_script` alternative)

Use `add_inline_scripts()` to add JavaScript data directly or to attach data to an existing script handle (similar to `wp_localize_script` but more flexible).

```php
// Add data before an existing script
$public_assets->add_inline_scripts([
    [
        'handle'   => 'my-plugin-public-script', // Handle of an already added script
        'content'  => 'var myPluginData = ' . wp_json_encode(['ajax_url' => admin_url('admin-ajax.php')]),
        'position' => 'before', // 'before' or 'after' the script tag
    ]
]);

// Add a standalone inline script block
$public_assets->add_inline_scripts([
    [
        'handle'   => 'my-inline-block', // Can be a new unique handle
        'content'  => "console.log('My inline script block executed!');",
        'position' => 'after', // Typically 'after' for standalone blocks if no specific order needed
        'hook'     => 'wp_footer' // Optionally defer this inline block too
    ]
]);
```

## Work in Progress (WIP)

This library is under active development, and several areas are still evolving or awaiting more comprehensive implementation. Contributions in these areas are particularly welcome.

### Testing (WIP)

Basic testing infrastructure is in place, but comprehensive test coverage is an ongoing goal.

- **Framework**: [PHPUnit](https://phpunit.de/) is used for testing, often in conjunction with [WP Mock](https://github.com/10up/wp-mock) for mocking WordPress functions.
- **Location**: Tests are located in the `Tests/` directory at the root of the library.

- **Running Tests**: Currently, there isn't a dedicated Composer script for running tests. You would typically run PHPUnit directly from the command line, ensuring you are in the library's root directory:

  ```bash
  composer test
  ```

  Or, to run tests for a specific suite or file, refer to the PHPUnit documentation.

- **Goal**: We aim to increase test coverage for all core components and utilities. If you're contributing code, please consider adding relevant unit tests.

### Key Library Components & APIs (Overview - some parts are WIP)

Below is a brief overview of some core components. Documentation and functionality for these are still being refined.

- **`AccessoryAPI` (`inc/AccessoryAPI/`)**: This API aims to simplify interactions with complex or boilerplate-heavy WordPress functionalities. "Features" (see `FeaturesAPI` below) can opt-in to use an "Accessory" by implementing its corresponding interface. The system then automatically handles much of the setup. For example, an accessory might make it easier to register custom post types or manage admin notices.
- **`FeaturesAPI` (`inc/FeaturesAPI/`)**: This is a foundational part of the library designed to help organize your plugin's code into modular, manageable "Features." Each distinct piece of functionality (like a shortcode, an admin settings page, or a custom REST endpoint) can be encapsulated in its own `FeatureController` class. The `FeaturesManager` handles registering these features, injecting dependencies, and loading them at the appropriate time. It also integrates with the `AccessoryAPI`.
- **`HooksAccessory` (`inc/HooksAccessory/`)**: This is an example of an "Accessory" built on the `AccessoryAPI`. It provides a comprehensive hook management system that supports both declarative and dynamic hook registration patterns. The system includes specialized registrars (`ActionHooksRegistrar`, `FilterHooksRegistrar`) and an advanced `HooksManager` for complex hook scenarios including conditional registration, deduplication, and comprehensive logging.
- **`Util` (`inc/Util/`)**: Core utility components used throughout the library. This includes the `Logger` class for PSR-3 compatible logging, `WPWrappersTrait` for wrapping WordPress functions to enable easier testing and potential future modifications, and other shared utilities that provide common functionality across different library components.
- **`Users` (`inc/Users/`)**: This component provides utility functions related to WordPress user management. For instance, it includes methods for inserting new users and adding user metadata, with a focus on robust error handling (e.g., throwing exceptions instead of WordPress's typical `WP_Error` returns, which can sometimes be missed).

Further details on these and other components will be added as the library matures.

## Contributing

We welcome contributions to the RAN PluginLib! To ensure consistency and maintain high code quality, please adhere to the following guidelines:

### 1. Setting Up a Local Development Environment for PluginLib

If you plan to contribute to the PluginLib itself, you'll need to set up a local development environment. We provide a script to help with this process.

**Steps:**

1. Navigate to the root directory of the PluginLib (e.g., where this `README.md` file is located).
2. Make the setup script executable (you only need to do this once):

   ```bash
   chmod +x scripts/setup-dev.sh
   ```

3. Run the setup script from your terminal:

   ```bash
   ./scripts/setup-dev.sh
   ```

This script will perform the following actions:

- Checks if Composer is installed on your system.
- Removes any existing `composer.lock` file and `vendor/` directory to ensure a clean environment.
- Installs all project dependencies by running `composer update`.
- Lists the available Composer scripts for common development tasks such as linting and formatting your code.

After the script completes successfully, your environment will be ready for development on the PluginLib.

### 2. Coding Standards

Our project follows a modified version of the WordPress Coding Standards, enforced by PHP_CodeSniffer (PHPCS). The primary rulesets include:

- **WordPress Standards:**

  - `WordPress`: The main WordPress ruleset, with specific exclusions for compatibility (e.g., `PrefixAllGlobals`, `I18n`) and to avoid deprecated sniffs.
  - `WordPress-Core`: The foundational WordPress coding conventions.
  - `WordPress-Extra`: A superset of `WordPress-Core`, incorporating additional best practices. We customize this to exclude specific rules (e.g., related to file naming for PSR-4 autoloading, enqueued resources, and short ternaries).
  - `WordPress-Docs`: Strict rules for PHPDoc comments and inline documentation. All code, including classes, methods, functions, and hooks, must be thoroughly documented.

- **PHP_CodeSniffer Generic Sniffs:**
  - Covers general coding style aspects like class opening brace placement (`Generic.Classes.OpeningBraceSameLine`).
  - Enforces the use of braces for all control structures (`Generic.ControlStructures.InlineControlStructure`).
  - Provides warnings for unused function parameters (`Generic.CodeAnalysis.UnusedFunctionParameter`).
  - Flags `@todo` and `@fixme` comments (`Generic.Commenting.Todo`, `Generic.Commenting.Fixme`).
- **Squiz Sniffs:**
  - Includes rules like `Squiz.ControlStructures.ControlSignature` to enforce consistent control structure syntax, including the use of braces.
- **Slevomat Coding Standard:**
  - A comprehensive set of sniffs focused on modern PHP features and strictness.
  - Enforces `declare(strict_types=1);`.
  - Manages type hint syntax (e.g., disallowing old array syntax, preferring long type hints, ensuring correct nullable type usage).
  - Requires type hints for parameters, properties, and return types, along with consistent spacing.
  - Includes rules for class and trait structure, such as requiring multi-line method signatures and standardizing trait usage.

**Key project-specific conventions include:**

- **Global Prefix:** All global PHP constructs (functions, classes, constants, hooks) must be prefixed with `ran_plugLib` to avoid conflicts (as defined in `.phpcs.xml`).
- **File Naming:** We follow PSR-4 autoloading standards, so class file names should match the class name (e.g., `MyClass.php`). This means standard WordPress hyphenated file naming rules are relaxed for class files.
- **PHP Version:** Code should be compatible with PHP 8.1+ as per the library's requirements.
- **WordPress;guidelines Version:** The library targets WordPress `6.7.0` and above (as configured in `.phpcs.xml`).

While the `WordPress.WP.I18n` sniff is currently excluded from strict linting in `.phpcs.xml`, contributions are encouraged to follow WordPress internationalization best practices for broader plugin usability.

### 3. Running Linters

Before submitting a pull request, please ensure your code passes our linting checks.

1. **Install Dependencies:**
   If you haven't already, install project dependencies, including PHPCS and the required coding standards:

   ```bash
   composer install
   ```

2. **Run Linters:**
   Our project uses both `PHP-CS-Fixer` (for general code style) and `PHP_CodeSniffer` (for WordPress coding standards, configured via `.phpcs.xml`). We have convenient Composer scripts to manage these:

   - **To check for linting errors (dry run):**
     This command runs both `php-cs-fixer` (in dry-run mode to show differences) and `phpcs` (to report any violations according to `.phpcs.xml`). No files are changed.

     ```bash
     composer run lint
     ```

   - **To automatically fix linting errors:**
     This command runs `php-cs-fixer` to apply its fixes and `phpcbf` (PHP Code Beautifier and Fixer) to automatically correct violations found by `phpcs`.

     ```bash
     composer run format
     ```

   These primary `lint` and `format` commands are composed of more specific scripts (like `@cs:check`, `@standards:full`, `@cs`, `@standards:fix`) also defined in `composer.json`.

#### In-Place Development and Linting (Using Custom Runner)

When developing or debugging the PluginLib directly within a consuming plugin's `vendor` directory (e.g., via symlinks or direct edits), standard PHP_CodeSniffer path resolution for rulesets and scanned files can sometimes be problematic.

To address this, we provide a custom; script: `scripts/php-codesniffer.php`. This script is designed to facilitate linting the library "in-place" with more reliable path handling in such development scenarios.

You can use this custom runner via the following Composer; scripts:

- **To check for standards violations (using the custom runner):**
  This command utilizes `scripts/php-codesniffer.php` to lint the library, often providing more stable path resolution when the library is a nested dependency.

  ```bash
  composer run; runner:standards:full
  ```

- **To automatically fix standards violations (using the custom runner):**
  This command uses the custom runner with `phpcbf` capabilities to fix issues identified by PHP_CodeSniffer.

  ```bash
  composer run runner:standards:fix
  ```

The `runner:standards:full` and `runner:standards:fix` scripts will take the rules from the `.phpcs.xml` file and generate a custom `.phpcs-runner.xml` file that is used by the custom runner, with full path resolution for rulesets and scanned files. This is useful for in-place development, as it allows you to run `phpcs`/`phpcbf` without having to modify the `.phpcs.xml` file with absolute file paths.

While this approach is very useful for in-place development, be aware that it might have specific behaviors or limitations compared to running `phpcs`/`phpcbf` directly in a standalone checkout of the library. It's particularly helpful for quick checks and fixes during iterative development within a larger project structure.

Please review the `.phpcs.xml` file for WordPress-specific rules and `scripts/php-codesniffer.php` for details.

## License

MIT
