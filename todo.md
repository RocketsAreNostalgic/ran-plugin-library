# Refactoring Ideas for PluginLib

This document outlines potential refactoring areas to improve testability, maintainability, and design of the PluginLib classes.

## 1. Complex Constructor Logic (e.g., `RegisterOptions`)

**Current Situation:**
The `RegisterOptions` constructor immediately calls `get_option()` to load existing WordPress options. This makes instantiation in unit tests more complex, requiring mocks for `get_option` even for tests not directly focused on initial loading.

**Suggestions for Implementation:**

- **Lazy Loading:** Instead of loading options in the constructor, load them on demand when they are first needed (e.g., in `get_options()` or a specific `load()` method).
- **Dedicated Initialization Method:** Introduce an explicit `initialize()` or `load_options()` method that the client code (or the class itself internally at a later point) calls after instantiation. The constructor would then only set up basic properties.
- **Pass Initial State (Less Ideal for this case):** Allow passing the initial options array directly to the constructor. This is more suitable if the class doesn't _always_ need to fetch from the database.

**Impact on API:**

- **Lazy Loading/Dedicated Init:**
  - The constructor signature might remain the same or become simpler.
  - Client code might need to call an `initialize()` method if that pattern is chosen.
  - The first call to a method that requires options (like `get_options()`) might have a slight performance overhead if options are loaded then.
  - Test setup becomes simpler as basic instantiation won't require immediate `get_option` mocks.
- **Passing Initial State:**
  - Constructor signature would change to accept an optional array of pre-loaded options.
  - Client code would have the option to pre-fetch options and pass them in, or let the class fetch them.

## 2. Getters with Implicit Data Fetching/Synchronization (e.g., `RegisterOptions::get_options`)

**Current Situation:**
The `RegisterOptions::get_options()` method calls `refresh_options()`, which in turn calls `get_option()` for potentially multiple sub-options to ensure data freshness. This makes the getter's behavior complex and increases test setup.

**Suggestions for Implementation:**

- **Separate Concerns:**
  - Keep `get_options()` as a simple getter that returns the current in-memory state of options.
  - Introduce an explicit `refresh_from_database()` or `sync_options()` method that client code can call when it needs to ensure the options are up-to-date with the WordPress database.
- **Eventual Consistency Model:** The class could internally decide when to refresh (e.g., based on a time-to-live or specific triggers) but `get_options()` itself would primarily return cached state. This is more complex to manage.

**Impact on API:**

- **Separate Concerns:**
  - `get_options()` becomes a straightforward, predictable getter.
  - A new public method like `refresh_from_database()` would be added.
  - Client code would be responsible for deciding when to call `refresh_from_database()`. This gives more control but also adds responsibility.
  - Testing `get_options()` becomes much simpler. Testing `refresh_from_database()` would still require `get_option` mocks but would be focused on that specific synchronization behavior.
- **Eventual Consistency:**
  - API might not change externally, but internal behavior and predictability for tests could be harder to manage unless the refresh mechanism is controllable.

## 3. Tight Coupling to External Framework (WordPress Global Functions) & Dependency Inversion

**Current Situation:**
Both `Config` and `RegisterOptions` directly call WordPress global functions like `get_option()`, `update_option()`, `get_plugin_data()`, `current_user_can()`. This makes them hard to test without `WP_Mock` and tightly couples them to the WordPress environment.

**Suggestions for Implementation:**

- **Introduce Interfaces and Adapters:**
  - Define interfaces for WordPress functionalities, e.g., `WordPressOptionManagerInterface` (with methods like `get`, `update`, `delete`), `WordPressPluginDataProviderInterface` (with `get_data`), `WordPressUserPermissionsInterface` (with `can`).
  - Create concrete adapter classes that implement these interfaces and wrap the actual WordPress global function calls. E.g., `WordPressOptionAdapter implements WordPressOptionManagerInterface`.
- **Dependency Injection:**
  - Inject instances of these adapter classes (or their interfaces) into `Config` and `RegisterOptions` via their constructors or setter methods.
  - The classes would then call methods on these injected dependencies instead of global WordPress functions.
  - Example: `Config` constructor could take a `WordPressPluginDataProviderInterface` and a `WordPressOptionManagerInterface`.

**Impact on API:**

- **Constructor Signatures Change:** Constructors for `Config` and `RegisterOptions` would change to accept these new dependencies.
  - `public function __construct(WordPressOptionManagerInterface $option_manager, ...)`
- **Instantiation Becomes More Involved (but clearer):** Client code (e.g., the main plugin bootstrap) would be responsible for creating and injecting the concrete adapter instances.
  - `$option_adapter = new WordPressOptionAdapter();`
  - `$config = new Config('plugin.php', $plugin_data_adapter, $option_adapter);`
  - `$register_options = new RegisterOptions('main_option', $option_adapter);`
- **Vastly Improved Testability:**
  - In unit tests, you can easily pass mock implementations of the interfaces instead of using `WP_Mock` for global functions. This makes tests cleaner, faster, and more reliable.
  - The classes become decoupled from the WordPress global namespace for these specific interactions.
- **Clearer Dependencies:** The class's dependencies on WordPress functionalities become explicit through its constructor or setters.
- **Flexibility:** Allows for easier substitution of WordPress functionality, e.g., for different storage mechanisms or contexts (though this is less common for typical plugins).

This approach aligns with the Dependency Inversion Principle, leading to more modular, testable, and maintainable code.
