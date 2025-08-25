# Accessory API

The Accessory API is an elegant way for Features to implement functionality that might otherwise require a lot of boilerplate and/or are otherwise confusing to implement (think the WordPress Plugin's API, adding Admin Pages, sub-pages and more).

## What is an Accessory?

An Accessory is a capability contract expressed as an interface that extends `AccessoryBaseInterface`. When a `FeatureController` implements an Accessory interface, the library locates the adjacent Manager class and calls its `init($feature)` to perform the heavy lifting. In practice, accessories act like flags that opt your feature into specific capabilities.

## How to use

To use an Accessory, your `FeatureController` implements one or more Accessory interfaces (they all extend `AccessoryBaseInterface`). Each Accessory interface specifies the public methods and/or properties you must provide. See the interface docblocks for exact requirements.

When features are loaded, the system scans each `FeatureController` for implemented Accessory interfaces and invokes the adjacent Manager for each one. You may inspect a Manager’s implementation, but it isn’t required—implementing the interface contract should be sufficient.

Another way to think of Accessories is as flags. If your `FeatureController` wants to access the goodness of any Accessory, simply add the Accessory's flag to the `implements` section on the class, implement the required functions that it will call, and profit.

### HooksAccessory example

Currently, we have a `HooksAccessory` that provides the interfaces `ActionHooksInterface` and `FilterHooksInterface` (see `inc/HooksAccessory/ActionHooksInterface.php` and `inc/HooksAccessory/FilterHooksInterface.php`).

- Hooks are declared statically via `ActionHooksInterface::declare_action_hooks()` and `FilterHooksInterface::declare_filter_hooks()`.
- Declarative hooks are registered automatically by the Hooks accessory Manager.
- Advanced hook management is provided by `HooksManager` and the `HooksManagementTrait` for programmatic registration, conditional hooks, groups, deduplication, logging, and stats.

See:

- `inc/HooksAccessory/docs/TFS-001-HooksManager-and-Trait-Usage.md`
- `inc/HooksAccessory/docs/FPD-001-HooksManager-and-Trait.md`

## Implementation Details

In the `AccessoryAPI/` directory are two base interfaces: `AccessoryBaseInterface` and `AccessoryManagerBaseInterface`.

- `AccessoryBaseInterface`

  - must be implemented by any Accessory class to be valid. Is a "marker" interface and should not be implemented by FeatureControllers directly.

- `AccessoryManagerBaseInterface`
  - must be implemented by any Accessory Manager.
  - defines `init(AccessoryBaseInterface $feature)` that performs per-feature initialization for the accessory before the feature’s own `init()` is called.

### Naming & file locations

Accessory interfaces by convention are named `MySomethingAccessoryInterface`, or simply `MySomethingAccessory`.
However `AccessoryManagers` must be suffixed with the string "Manager" and may not contain the string "Interface":

❌ Incorrect:

```php
 MySomethingAccessoryInterfaceManager.php
 PSR-4: Ran\PluginLib\MySomethingAccessory\MySomethingAccessoryInterfaceManager
```

✅ Correct:

```php
 MySomethingAccessoryManager.php
 PSR-4: Ran\PluginLib\MySomethingAccessory\MySomethingAccessoryManager
```

This is because we need to predict where the Manager class will be found.
`MySomethingAccessoryManager` must be co-located in the same directory and namespace as its flag, `MySomethingAccessoryInterface`. No warning is issued for features without a manager.

Manager resolution algorithm (as implemented by `FeaturesManagerAbstract::_enable_instance_accessiories()`): given an Accessory interface FQCN (Fully Qualified Class Name), remove the suffix `Interface` and append `Manager` in the same namespace. The resolved class must implement `AccessoryManagerBaseInterface`.

## Lifecycle

The current lifecycle when loading features (`FeaturesManagerAbstract`) is:

1. Create the `FeatureController` instance (`_create_new_feature_class()`).
2. Inject dependencies (`_set_instance_dependencies()`).
3. Enable accessories by locating each adjacent `<Accessory>Manager` and calling `init($feature)` (`_enable_instance_accessiories()`).
4. Call the feature's `init()` method (`_load_feature_container()`).
5. Store the instance on its `FeatureContainer`.

Other useful references:

- Managers: `inc/HooksAccessory/HooksManager.php`
- Trait: `inc/HooksAccessory/HooksManagementTrait.php`
- Other accessories: `inc/SmokeTestAccessory/readme.md`, `inc/EnqueueAccessory/` and `inc/EnqueueAccessory/docs/`

## Design Decisions

Our implementation of Accessories relies on several design decisions using interfaces that could be considered problematic:

1. Use of marker interfaces (ie interfaces without any implementation as found in our `AccessoryBaseInterface`) is actually a form of metadata, even our [How To Use](#how-to-use) mentions a conceptual model of 'flags'.
2. Using Marker Interfaces as metadata could be considered 'magical' or atypical.
3. PHP Attributes are designed for just this purpose (adding metadata to classes).
4. Marker Interfaces are inherited, so each FeatureController that implements an Accessory interface cannot be decoupled from it.

### Marker Interface

The design challenge with `Accessories` is that we would like to fashion a 'simple' means for plugin authors to implement complex or tedious WordPress APIs. In every use case conceived so far, an arbitrary `XAccessoryInterface` imposes specific implementation details (and offers extensive documentation) of what authors need to add to their `FeatureController`. So while our `AccessoryBaseInterface` does act as a['marker' interface](https://en.wikipedia.org/wiki/Marker_interface_pattern) with no implementation requirements of its own, we don't intend this interface to implement it directly, only its ancestors. This way, due to inheritance and using `Reflection`, we can test in the `FeaturesManager` if a `FeatureController` implements the common ancestor `AccessoryBaseInterface`. If it does, we then access its manager `XAccessoryManager` to do any heavy lifting required to implement the `Accessory`.

### Attributes vs Interfaces

Using `Reflection` is exactly how arbitrary `Attributes` are managed on classes, so why not use them instead? This is a fair question, and it might be possible, but it has some drawbacks for our use case. Consider this quote from Brent Roose, Dev Advocate for PHPStorm:

> Keep in mind the goal of attributes: they are meant to add metadata to classes and methods, nothing more. They shouldn't — and can't — be used for, for example, argument input validation. In other words, you wouldn't have access to the parameters passed to a method within its attributes.
> [Brent Roose, PHP 8: Attributes](https://stitcher.io/blog/attributes-in-php-8)

While we do use our Interfaces as a flag (metadata) as an `Attribute`, our Accessory Interfaces also enforce implementation details and guidance for developers on what is expected directly within the `FeatureController` they are authoring. This is good, as we don't know the complexity or shape of the data that future `Accessories` may require or the logic a developer might want to carry out when preparing them. Further, as Brent points out, Attributes were not designed to be used in this way, so passing complex programmatically derived data to them can become difficult and convoluted.

### Inheritance

The final point on Interface inheritance (subtyping) cannot be avoided, and this could become problematic as any descendant `FeatureControllers` will be required to implement methods they might not need. This said, it would be quite unusual to extend a `FeatureController` to the point where this would be an issue, and in the vast majority of cases, each new `FeatureController` should be considered `final` if not declared as such. If this did become an issue, then composition becomes a better approach, and the Dependency array enables soft dependencies to be set on a `FeatureController` during registration (as key-value pairs, where the key is the name of a public property declared on the `FeatureController`). This approach would likely mean you would roll your own implementation of the WordPress APIs and so lose some of the convenience of `Accessories`, though it may be preferable in this case to extend the relevant `AccessoryManager` instead.

### Conclusion

So, as we have seen, yes, we are using a `marker interface`, but its use is internal and not user-facing. The 'magic' of this, if any, is that child Accessories are automatically evaluated almost identical to `Attributes` using `Reflection` in our `FeaturesManager`. We have chosen to stick with an `Interface` implementation of our `Accessory API` as it seems to offer the most flexibility while also providing guidance for developers with a straightforward implementation.

Sources:

- [Wikipedia: Marker Interface Pattern](https://en.wikipedia.org/wiki/Marker_interface_pattern)
- [Stack Overflow: What's the Good of Marker Interface](https://stackoverflow.com/questions/56862117/whats-the-good-of-marker-interface)
- [Marker Interface Isn't a Pattern or a Good Idea](https://dzone.com/articles/marker-interface-isnt-a-pattern-or-a-good-idea)
- [Principle of least astonishment](https://en.wikipedia.org/wiki/Principle_of_least_astonishment)
- [Brent Roose, PHP 8: Attributes](https://stitcher.io/blog/attributes-in-php-8)
