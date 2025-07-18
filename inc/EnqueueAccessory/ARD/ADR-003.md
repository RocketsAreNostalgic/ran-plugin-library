# ADR-003: Specialized Handler Classes with Trait Composition

**Date:** 2025-07-11
**Updated:** 2025-07-18
**Status:** Superseded

## Context

Due to the many common patterns in the WordPress APIs for scripts and styles, there is an opportunity to centralize much of the logic into a common base. However, PHP does not allow for multiple inheritance, so after some review we decided on a trait-based system where a common base trait (`AssetEnqueueBaseTrait`) would hold shared logic and then individual traits (e.g., `ScriptsEnqueueTrait`, `StylesEnqueueTrait`) would extend this base and hold only asset-specific logic.

The challenge arises when a class attempts to use multiple PHP traits that have a shared base, as it could lead to a fatal error when two or more traits surface methods with the same name from the underlying base trait. For example, a class that controls the asset loading on a plugin's admin screens might need both `ScriptsEnqueueTrait` and `StylesEnqueueTrait`, which could both inherit methods with the same name from a shared base trait.

## Decision

Initially, we implemented a **Trait Dispatcher Pattern** as described in the original version of this ADR. However, after further development and testing, we found that this approach added unnecessary complexity and indirection.

Instead, we have adopted a simpler and more direct approach:

1. **Specialized Handler Classes**: Rather than having a single class use multiple asset-specific traits, we've created specialized handler classes for each asset type:

   - `ScriptsHandler` - Uses `ScriptsEnqueueTrait`
   - `StylesHandler` - Uses `StylesEnqueueTrait`

2. **Composition in Consumer Classes**: Consumer classes like `EnqueuePublic` and `EnqueueAdmin` now use composition rather than multiple trait inheritance:

   - They instantiate the specialized handlers as properties
   - They delegate to these handlers for asset-specific operations

3. **Direct Method Implementation**: Each trait now implements its methods directly without relying on a dispatcher:

   - `ScriptsEnqueueTrait` implements `_process_single_asset()` with script-specific logic
   - `StylesEnqueueTrait` implements `_process_single_asset()` with style-specific logic

4. **Common Base Logic**: The `AssetEnqueueBaseTrait` still provides common functionality, but now:
   - It's used by both asset-specific traits
   - It provides concrete implementations of shared methods like `_concrete_process_single_asset()`
   - Each specialized trait calls these concrete methods as needed

## Consequences

### Positive

1. **Simplified Architecture**: The code is more straightforward without the dispatcher indirection.
2. **Better Separation of Concerns**: Each handler class has a single responsibility.
3. **Improved Testability**: Specialized handlers are easier to test in isolation.
4. **Clearer API**: The public API is more intuitive with methods like `scripts()->add_scripts()`.
5. **Reduced Risk of Conflicts**: No risk of trait method collisions since each trait is used in a separate class.

### Negative

1. **More Classes**: The architecture requires more classes, which could increase the learning curve.
2. **Delegation Overhead**: Consumer classes must delegate to the appropriate handler.

## Implementation

The implementation follows this pattern:

```php
// Base abstract class
abstract class AssetEnqueueBaseAbstract {
    use AssetEnqueueBaseTrait;
    // Common functionality
}

// Specialized handlers
class ScriptsHandler extends AssetEnqueueBaseAbstract {
    use ScriptsEnqueueTrait;
}

class StylesHandler extends AssetEnqueueBaseAbstract {
    use StylesEnqueueTrait;
}

// Consumer class using composition
class EnqueuePublic implements EnqueueInterface {
    private ScriptsHandler $scripts_handler;
    private StylesHandler $styles_handler;

    public function __construct(ConfigInterface $config) {
        $this->scripts_handler = new ScriptsHandler($config);
        $this->styles_handler = new StylesHandler($config);
    }

    public function scripts(): ScriptsHandler {
        return $this->scripts_handler;
    }

    public function styles(): StylesHandler {
        return $this->styles_handler;
    }

    // Other methods that delegate to handlers
}
```

This approach provides a clean, maintainable architecture that avoids the complexity of the dispatcher pattern while still achieving the goal of code reuse and separation of concerns.

This diagram shows the current architecture using specialized handler classes and composition:

```ascii
// 1. Consumer class uses composition to access specialized handlers
                   +---------------------+
                   |    EnqueuePublic    |
                   | (implements         |
                   |  EnqueueInterface)  |
                   +---------------------+
                   | - scripts_handler   |
                   | - styles_handler    |
                   +---------------------+
                   | + scripts()         |
                   | + styles()          |
                   | + stage()           |
                   +----------+----------+
                              |
                              | delegates to
                 +------------+------------+
                 |                         |
                 V                         V
    +-----------------------+   +------------------------+
    |    ScriptsHandler     |   |     StylesHandler      |
    | (extends BaseAbstract)|   | (extends BaseAbstract) |
    +-----------------------+   +------------------------+
    | + add_scripts()       |   | + add_styles()         |
    | + enqueue_scripts()   |   | + enqueue_styles()     |
    | + _process_single_    |   | + _process_single_     |
    |   asset()             |   |   asset()              |
    +------------+----------+   +------------+-----------+
                 |                          |
                 | uses                     | uses
                 |                          |
                 V                          V
    +------------------------+   +------------------------+
    |  ScriptsEnqueueTrait   |   |  StylesEnqueueTrait    |
    +------------------------+   +------------------------+
    | (script-specific       |   | (style-specific        |
    |  implementations)      |   |  implementations)      |
    +------------+-----------+   +------------+-----------+
                 |                          |
                 | both use                 |
                 |                          |
                 V                          V
           +-----------------------------------+
           |     AssetEnqueueBaseTrait         |
           +-----------------------------------+
           | + _concrete_process_single_asset()|
           | + _process_inline_assets()        |
           | (shared functionality)            |
           +-----------------------------------+
```

## Pro & Con

- **Pros:**

  - **Eliminates Trait Method Collisions:** This is the primary benefit. It allows for safe composition of multiple, complex traits.
  - **Clean Internal API:** Provides a single, predictable entry point (`_process_single_asset`) for a given operation.
  - **High Cohesion:** Logic for a specific domain (like script processing) remains entirely within its dedicated trait.

- **Cons:**
  - **Indirection:** Adds a layer of abstraction that requires understanding the dispatcher's routing logic.
  - **Strict Naming Discipline:** Requires that new methods in traits follow the unique naming convention to be integrated into the dispatcher.
