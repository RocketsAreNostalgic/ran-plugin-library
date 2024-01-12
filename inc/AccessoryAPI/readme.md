# Accessory API

The Accessory API is an elegant way for Features to implement functionality that might otherwise require a lot of boilerplate and or are otherwise confusing to implement (think the WordPress Plugin's API, adding Admin Pages, sub-pages and more.

## How to use

To use an Accessory, your FeatureController must implement an interface belonging to one or more Accessories. Each Accessory's interface requires your FeatureController to implement any combination of public functions and variables it may require. You can find full instructions for each Accessory within it's interface's comments.

When the `FeatureManager->load_all()` method is called, each `FeatureController` is scanned for compatible Accessory interfaces, and when they are found that Accessory's `Manager` is called to carry out the work. You may of course inspect the manager, but knowledge about its inner workings isn't required, so long as you implement the interface as required and your `FeatureController` passes back the data that the Accessory requires.

Another way to think of Accessory are as flags. If your `FeatureController` wants to access the goodness of any Accessory, simply add the Accessory's flag to the `implements` section on the class, implement the required functions that it will call, and profit.

### Examples

Currently we have a `HooksAccessory` which provides the interfaces `ActionHookInterface` and `FilterHookInterface`.

The `ActionHookInterface` requires your `FeatureController` implement a `get_actions()` method which returns a nested array of actions with parameters. The `get_actions()` method is called by the `FeatureManager` for the `ActionHookInterface` automatically when the feature is loaded in your bootstrapping file. The `FeatureManager` does all the heavy lifting of registering all the action hooks that you might send it.

## Implementation Details

In the `AccessoryAPI/` directory are two base interfaces: `AccessoryBaseInterface` and `AccessoryManagerBaseInterface`.

-   `AccessoryBaseInterface`

    -   must be implemented by any Accessory class to be valid. Is a "marker" interface and should not be implemented by FeatureControllers directly.

-   `AccessoryManagerBaseInterface`
    -   must also be extended by any AccessoryManager to be a valid Accessory Manager.
    -   requires your FeatureController to have an `init()` method.

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

The reason for this is that we need to predict where the Manger class will be found.
`MySomethingAccessoryManager` must be colocated in the same directory and namespace as its flag, `MySomethingAccessoryInterface`. No warning is issued for features without a manager.

## Design Decisions

Our implementation to Accessories relies on several design decisions using interfaces that could be considered problematic:

1. Use of marker interfaces (ie interfaces without any implementation as found in our `AccessoryBaseInterface`) is actually a form of metadata, even our [How To Use](#how-to-use) mentions a conceptual model of 'flags'.
2. Using Marker Interfaces as metadata could be considered 'magical' or atypical.
3. PHP Attributes are designed for just this purpose (adding metadata to classes).
4. Marker Interfaces are inherited and so each FeatureController that implements a Accessory interface cannot be decoupled from it.

### Marker Interface

The design challenge with `Accessories` is that we would like to fashion a 'simple' means for plugin authors to implement complex or tedious WordPress APIs. In every use case conceived so far, an arbitrary `XAccessoryInterface` imposes specific implementation details (and offers extensive documentation) of what authors need to add to their `FeatureController`. So while our `AccessoryBaseInterface` does act as a['marker' interface](https://en.wikipedia.org/wiki/Marker_interface_pattern) with no implementation requirements of its own, we don't intend this interface to implemented it directly, only its ancestors. This way due to inheritance, and using `Reflection` we can test in the `FeaturesManager` if a `FeatureController` implements the common ancestor `AccessoryBaseInterface`. If it does, we then access its manager `XAccessoryManager` to do any heavy lifting required to implement the `Accessory`.

### Attributes vs Interfaces

Using `Reflection` happens to be exactly how arbitrary `Attributes` are managed on classes as well, so why not use them instead? This is a fair question and it might be possible, but has some drawbacks for our use case. Consider this quote from Brent Roose, Dev Advocate for PHPStorm:

> Keep in mind the goal of attributes: they are meant to add meta data to classes and methods, nothing more. They shouldn't — and can't — be used for, for example, argument input validation. In other words: you wouldn't have access to the parameters passed to a method within its attributes.
> \> [Brent Roose, PHP 8: Attributes](https://stitcher.io/blog/attributes-in-php-8)

While we do use our Interfaces as a flag (metadata) as an `Attribute` could be, our Accessory Interfaces also enforce implementation details and guidance for developers on what is expected, directly within the `FeatureController` they are authoring. This is good, as we don't know the complexity or shape of the data that future `Accessiories` may require, or the logic a developer might want to cary out when preparing them. Further as Brent points out, Attributes were not designed to be used in this way, so passing complex programmatically derived data to them can be become difficult and convoluted.

### Inheritance

The final point on Interface inheritance (subtyping) cannot be avoided, and this could become problematic as any descendant `FeatureControllers` will be required to implement methods that they might not need. This said, it would be quite unusual to extend a `FeatureController` to the point where this would be an issue, and in the vast majority of cases each new `FeatureController` should be considered `final` if not declared as such. If this did become an issue, then composition becomes a better approach, and the Dependency array enables soft dependancies to be set on a `FeatureController` during registration (as key value pairs, where the key is the name of a public property declared on the `FeatureControler`). This approach would likely mean you would rolling your own implementation the WordPress API's, and so loose some of the connivence of `Accessories` though it may be possible to extend an `AttributeManager`.

### Conclusion

So as we have seen, yes we are using a `marker interface` but it its use is internal and not user facing. The 'magic' of this if any, is that child Accessories are automatically evaluated in an almost identical way to `Attributes` are using `Reflection` in our `FeatureManager`. We have chosen to stick with an `Interface` implementation of our `Accessory API` as it seems to offer the most flexibility, while also providing guidance for developers with a straight forward implementation.

Sources:

-   [Wikipedia: Marker Interface Pattern](https://en.wikipedia.org/wiki/Marker_interface_pattern)
-   [Stack Overflow: What's the Good of Marker Interface](https://stackoverflow.com/questions/56862117/whats-the-good-of-marker-interface)
-   [Marker Interface Isn't a Pattern or a Good Idea](https://dzone.com/articles/marker-interface-isnt-a-pattern-or-a-good-idea)
-   [Principle of least astonishment](https://en.wikipedia.org/wiki/Principle_of_least_astonishment)
-   [Brent Roose, PHP 8: Attributes](https://stitcher.io/blog/attributes-in-php-8)
