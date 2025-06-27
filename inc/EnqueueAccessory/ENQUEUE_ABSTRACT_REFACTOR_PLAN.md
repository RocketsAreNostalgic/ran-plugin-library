# EnqueueAbstract Refactoring Plan

## 1. Introduction & Goals

- **Current State:** The `EnqueueAbstract` class (approx. 1300+ lines) is responsible for managing the enqueuing of scripts, styles, and the wordpress media JS APIs assets within the plugin-library. It implements `EnqueueInterface`.
- **Problem:** Its large size and multiple responsibilities (handling scripts, styles, and media, plus core enqueuing lifecycle) violate the Single Responsibility Principle. This leads to reduced maintainability, readability, and makes unit testing more complex and cumbersome.
- **Goal:** Refactor `EnqueueAbstract` into a more modular and maintainable structure. This will be achieved by:
  1. Introducing a new base abstract class (`EnqueueAssetBaseAbstract`) for truly shared, generic enqueuing logic.
  2. Creating separate PHP traits (`ScriptsEnqueueTrait`, `StylesEnqueueTrait`, `MediaEnqueueTrait`) for managing each specific asset type.
- **Benefits:** This refactoring aims to improve code organization, enhance readability, simplify unit testing of individual components, and provide greater flexibility for future extensions.

## 2. Proposed New Structure

### 2.1. `EnqueueAssetBaseAbstract.php` (New Abstract Base Class)

- **Purpose:** To hold core, asset-agnostic functionality.
- **Key Responsibilities:**
  - Constructor: `__construct(\Ran\PluginLib\Config\ConfigInterface $config)` for dependency injection.
  - Logger access: `get_logger(): \Ran\PluginLib\Util\Logger`.
  - Abstract `load(): void` method (to be implemented by concrete child classes like `EnqueueAdmin` and `EnqueuePublic` to hook into WordPress actions).
  - Core `enqueue(): void` method: This method will orchestrate the enqueuing process. It will check for the existence of asset-specific processing methods (provided by the traits) and call them if available.
  - Management of generic head/footer callbacks: `render_head(): void`, `render_footer(): void`.
- **Properties:**
  - `protected \Ran\PluginLib\Config\ConfigInterface $config`
  - `protected \Ran\PluginLib\Util\Logger $logger` (potentially initialized via config)
  - `protected array $head_callbacks = []`
  - `protected array $footer_callbacks = []`
- **Interface Implementation:** This base class itself will _not_ directly implement `EnqueueInterface`. The concrete classes (`EnqueueAdmin`, `EnqueuePublic`) will continue to implement `EnqueueInterface`, fulfilling its contract using methods from this base class and the composed traits.

### 2.2. `ScriptsEnqueueTrait.php` (New Trait)

- **Purpose:** Encapsulate all logic specifically related to script enqueuing.
- **Properties:**
  - `protected array $scripts = []`
  - `protected array $inline_scripts = []`
  - `protected array $deferred_scripts = []`
- **Methods (examples, matching `EnqueueAbstract` and `EnqueueInterface` where applicable):**
  - `add_scripts(array $scripts_to_add): self`
  - `register_scripts(array $scripts_to_register = []): self` (internal registration logic)
  - `enqueue_scripts(array $scripts_to_enqueue): self` (direct enqueuing, may call WordPress functions)
  - `process_single_script(array $script_data, string $default_hook_action): void`
  - `add_inline_scripts(array $inline_scripts_to_add): self`
  - `enqueue_inline_scripts(string $location_hook = 'wp_print_scripts'): void`
  - `enqueue_deferred_scripts(): void`
  - `wp_localize_script(string $handle, string $object_name, array $data): bool`
  - A protected method like `process_script_assets()` to be called by the base `enqueue()` method.

### 2.3. `StylesEnqueueTrait.php` (New Trait)

- **Purpose:** Encapsulate all logic specifically related to style enqueuing.
- **Properties:**
  - `protected array $styles = []`
  - `protected array $inline_styles = []` (for `wp_add_inline_style`)
  - `protected array $deferred_styles = []`
- **Methods (examples):**
  - `add_styles(array $styles_to_add): self`
  - `register_styles(array $styles_to_register = []): self`
  - `enqueue_styles(array $styles_to_enqueue): self`
  - `process_single_style(array $style_data, string $default_hook_action): void`
  - `wp_add_inline_style(string $handle, string $data): bool`
  - `enqueue_inline_styles(string $location_hook = 'wp_print_styles'): void`
  - `enqueue_deferred_styles(): void`
  - A protected method like `process_style_assets()` to be called by the base `enqueue()` method.

### 2.4. `MediaEnqueueTrait.php` (New Trait)

- **Purpose:** Encapsulate all logic specifically related to media enqueuing (e.g., `wp_enqueue_media`).
- **Properties:**
  - `protected array $media = []`
- **Methods (examples):**
  - `add_media(array $media_to_add): self`
  - `enqueue_media(array $media_to_enqueue): self` (direct enqueuing)
  - A protected method like `process_media_assets()` to be called by the base `enqueue()` method.

## 3. Impact on Existing Classes

### 3.1. `EnqueueAbstract.php`

- This class will be **removed**. Its responsibilities and code will be distributed among `EnqueueAssetBaseAbstract` and the new traits.

### 3.2. `EnqueueInterface.php`

- **No changes are required** to the interface definition itself.
- Concrete classes (`EnqueueAdmin`, `EnqueuePublic`) will continue to `implement EnqueueInterface`. The methods required by the interface will be provided by the combination of `EnqueueAssetBaseAbstract` and the used traits.
  - `load(): void` (from `EnqueueAssetBaseAbstract`, implemented by concrete class)
  - `add_styles(array $styles): self` (from `StylesEnqueueTrait`)
  - `add_scripts(array $scripts): self` (from `ScriptsEnqueueTrait`)
  - `add_media(array $media): self` (from `MediaEnqueueTrait`)
  - `enqueue_scripts(array $scripts): self` (from `ScriptsEnqueueTrait`)
  - `enqueue_styles(array $styles): self` (from `StylesEnqueueTrait`)
  - `enqueue_media(array $media): self` (from `MediaEnqueueTrait`)
  - `enqueue(): void` (from `EnqueueAssetBaseAbstract`)

### 3.3. `EnqueueAdmin.php`

- **Parent Class Change:** Will change from `extends EnqueueAbstract` to `extends EnqueueAssetBaseAbstract`.
- **Trait Usage:** Will incorporate the necessary traits: `use ScriptsEnqueueTrait, StylesEnqueueTrait, MediaEnqueueTrait;`.
- **`load()` method:** The existing `load()` method implementation will be retained, as it contains admin-specific hooking logic.
- **Interface Compliance:** Will continue to `implement EnqueueInterface`.

### 3.4. `EnqueuePublic.php`

- **Parent Class Change:** Will change from `extends EnqueueAbstract` to `extends EnqueueAssetBaseAbstract`.
- **Trait Usage:** Will incorporate the necessary traits: `use ScriptsEnqueueTrait, StylesEnqueueTrait, MediaEnqueueTrait;`.
- **`load()` method:** The existing `load()` method implementation will be retained for public-specific hooking.
- **Interface Compliance:** Will continue to `implement EnqueueInterface`.

## 4. Refactoring Steps (Order of Operations)

1. **Create `EnqueueAssetBaseAbstract.php`:**

   - Define the class structure, namespace, and `use` statements.
   - Migrate shared properties (`$config`, `$logger`, `$head_callbacks`, `$footer_callbacks`) from `EnqueueAbstract`.
   - Migrate shared methods (`__construct`, `get_logger`, `render_head`, `render_footer`).
   - Define the abstract `load(): void` method.
   - Implement the core `enqueue(): void` orchestrator method. This method will check `method_exists($this, 'process_script_assets')` etc., and call them.

2. **Create `ScriptsEnqueueTrait.php`:**

   - Define the trait, namespace.
   - Migrate all script-specific properties from `EnqueueAbstract`.
   - Migrate all script-specific methods from `EnqueueAbstract`. Adapt method visibility (e.g., public for interface methods, protected for internal helpers). Create `process_script_assets()` helper.

3. **Create `StylesEnqueueTrait.php`:**

   - Define the trait, namespace.
   - Migrate all style-specific properties from `EnqueueAbstract`.
   - Migrate all style-specific methods from `EnqueueAbstract`. Adapt visibility. Create `process_style_assets()` helper.

4. **Create `MediaEnqueueTrait.php`:**

   - Define the trait, namespace.
   - Migrate media-specific properties and methods from `EnqueueAbstract`. Adapt visibility. Create `process_media_assets()` helper.

5. **Refactor `EnqueueAdmin.php`:**

   - Change `extends EnqueueAbstract` to `extends EnqueueAssetBaseAbstract`.
   - Add `use ScriptsEnqueueTrait, StylesEnqueueTrait, MediaEnqueueTrait;`.
   - Ensure `load()` method is correctly implemented.
   - Verify it still fulfills the `EnqueueInterface` contract.
   - Adjust any internal calls if method names/visibilities changed during trait creation.

6. **Refactor `EnqueuePublic.php`:**

   - Perform the same changes as for `EnqueueAdmin.php`.

7. **Testing (Crucial Phase):**

   - **Unit Tests:**
     - Update existing unit tests for `EnqueueAdmin` and `EnqueuePublic` to reflect the new structure. Mocks might need to target traits or the new base class.
     - Write new unit tests for `EnqueueAssetBaseAbstract` (for its direct responsibilities).
     - Write unit tests for each trait. This can be done by creating a minimal test class that `use`s the trait and testing its methods.
   - **Integration Tests:** Thoroughly test the enqueuing functionality in a WordPress environment for both admin and public contexts. Verify scripts, styles, inline assets, deferred assets, and media enqueuing.

8. **Documentation:**

   - Update all PHPDoc blocks for the new and modified classes/traits.
   - Update any external developer documentation or READMEs that reference `EnqueueAbstract`.

9. **Cleanup:**
   - Once all functionality is migrated, thoroughly tested, and confirmed, delete the old `EnqueueAbstract.php` file.

## 5. Potential Challenges & Risks

- **Breaking Change:** This is a significant refactor. Any third-party code or internal project code directly extending `EnqueueAbstract` will break and require updates. Clear versioning (e.g., a major version bump) and communication are essential.
- **Complexity of Migration:** Carefully dissecting `EnqueueAbstract` and moving logic to the correct trait/base class without introducing regressions requires meticulous attention to detail.
- **Method Naming/Visibility:** Ensuring method signatures and visibility in traits correctly align with `EnqueueInterface` requirements and internal usage.
- **Testing Coverage:** Achieving comprehensive test coverage for all new components and ensuring existing functionality remains intact is paramount.
- **Trait Conflicts:** While unlikely with distinct asset types, if traits were to define methods or properties with the same name and incompatible signatures, conflicts could arise. This should be manageable with careful naming.

This plan provides a structured approach to refactoring `EnqueueAbstract`. Each step should be handled carefully, with thorough testing at each stage.

## 6. Impact on Consuming Code (Example: `Bootstrap.php`)

To illustrate how this refactoring impacts typical usage of the enqueuing classes, let's consider an example from a project's `Bootstrap.php` or a similar initialization file.

**Key Takeaway:** For standard asset registration and enqueuing operations, the public API exposed by `EnqueueAdmin` (or `EnqueuePublic`) will remain consistent. This is because these classes will continue to implement `EnqueueInterface`, and the necessary methods (`add_scripts`, `add_inline_script`, `add_inline_style`, `load`, etc.) will be available through the new `EnqueueAssetBaseAbstract` and the composed traits.

### 6.1. Before Refactor (Current Usage in `Bootstrap.php`)

```php
// Example: In your plugin's Bootstrap.php or service registration

// Assuming $this->config, $this->plugin_url, $this->plugin_version are available
// and $is_admin_page is a boolean condition

// Get the EnqueueAdmin service (currently extends EnqueueAbstract)
// In this project, EnqueueAdmin is instantiated directly:
$admin_page_assets = new \Ran\PluginLib\EnqueueAccessory\EnqueueAdmin($this->config); // Assuming $this->config is available

// Example: Adding a main script
$admin_page_assets->add_scripts([
    [
        'handle' => 'my-test-script',
        'src' => $this->plugin_url . 'assets/js/test-script.js', // Generic path
        'deps' => ['jquery'], // Example dependency
        'ver' => $this->plugin_version,
        'in_footer' => true,
        'condition' => $is_admin_page,
    ]
]);

// Example: Adding inline script data
$inline_data = "var myPlugin = { ajax_url: 'some_ajax_url', nonce: 'abcdef1234' };";

$admin_page_assets->add_inline_scripts([
    [
        'handle' => 'my-test-script', // Attaches to 'my-test-script'
        'content' => $inline_data,
        'position' => 'before', // Or 'after'
        'condition' => $is_admin_page,
    ]
]);

// Register WordPress hooks to perform the actual enqueuing
$admin_page_assets->load();
```

### 6.2. After Refactor (Usage in `Bootstrap.php`)

The code in `Bootstrap.php` for adding and loading scripts **would remain virtually identical**:

```php
// Example: In your plugin's Bootstrap.php or service registration

// Assuming $this->config, $this->plugin_url, $this->plugin_version are available
// and $is_admin_page is a boolean condition

// Get the EnqueueAdmin service (now extends EnqueueAssetBaseAbstract and uses traits)
// In this project, EnqueueAdmin would still be instantiated directly:
$admin_page_assets = new \Ran\PluginLib\EnqueueAccessory\EnqueueAdmin($this->config); // Assuming $this->config is available

// Example: Adding a main script
// THIS CODE REMAINS THE SAME
$admin_page_assets->add_scripts([
    [
        'handle' => 'my-test-script',
        'src' => $this->plugin_url . 'assets/js/test-script.js', // Generic path
        'deps' => ['jquery'], // Example dependency
        'ver' => $this->plugin_version,
        'in_footer' => true,
        'condition' => $is_admin_page,
    ]
]);

// Example: Adding inline script data
// THIS CODE REMAINS THE SAME
$inline_data = "var myPlugin = { ajax_url: 'some_ajax_url', nonce: 'abcdef1234' };";

$admin_page_assets->add_inline_scripts([
    [
        'handle' => 'my-test-script', // Attaches to 'my-test-script'
        'content' => $inline_data,
        'position' => 'before', // Or 'after'
        'condition' => $is_admin_page,
    ]
]);

// Register WordPress hooks to perform the actual enqueuing
// THIS CODE REMAINS THE SAME
$admin_page_assets->load();
```

**Conclusion for Consuming Code:**
The refactoring is primarily an internal architectural improvement. Developers using the `EnqueueAdmin` or `EnqueuePublic` classes as per the `EnqueueInterface` contract should not need to change their existing code for common asset enqueuing tasks. The internal implementation changes, but the public-facing API for these standard operations is preserved.
