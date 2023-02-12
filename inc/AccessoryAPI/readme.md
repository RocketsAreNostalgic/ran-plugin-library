# Accessory API

The Accessory API is an elegant way for Features to implement functionality that might otherwise require a lot of boilerplate and or are otherwise confusing to implement (think the WordPress Plugin's API, adding Admin Pages, sub-pages and more.

## How to use

To use an Accessory, your FeatureController must implement an interface belonging to one or more Accessories. Each Accessory's interface requires your FeatureController to implement any combination of public functions and variables it may require. You can find full instructions for each Accessory within it's interface's comments.

When the `FeatureManager->load_all()` method is called, each `FeatureController` is scanned for compatible Accessory interfaces, and when they are found that Accessory's `Manager` is called to carry out the work. You may of course inspect the manager, but knowladge about its inner owrkings isn't required, so long as you impliment the interface as required and your `FeatureController` passes back the data that the Accessory requires.

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
2. Using Marker Interfaces as metadata is 'magical' or atypical.
3. PHP Attributes are designed for just this purpose (adding metadata to classes).
4. Marker Interfaces are inherited and so each FeatureController that implements a Accessory interface cannot be decoupled from it.

The design challenge with `Accessories` is that we would like one 'simple' means for plugin authors to implement complex or tedious WordPress APIs. In every use case conceived so far, an arbitrary `XAccessoryInterface` would require specific implementation (and offer extensive documentation) of what needs to implemented on a `FeatureController` to use that `Accessory`. So while our `AccessoryBaseInterface` acts as a['marker' interface](https://en.wikipedia.org/wiki/Marker_interface_pattern) with no implementation requirements of its own, we do not expect this interface to be implemented directly on a `FeatureController` only its ancestors, ie `XAccessoryInterface extends AccessoryBaseInterface` and then offers its own implementation. This way it is possible to check, using `Reflection` in our `FeaturesManager` if a `FeatureController` implements this common ancestor `AccessoryBaseInterface`, and then call its manager `XAccessoryManager` to do the work.

Using `Reflection` happens to be exactly how arbitrary `Attributes` are managed on classes as well, so why not use them instead? This is a fair question and it might be possible, but has some drawbacks.

At this stage, we don't know how complex the data may be that we might need to pass to an Accessory, nor what conditional logic a developer might want to implement, other then to say it will likely be a more complex then a string `#[Attrbute($some-compex-data)]`. Further, there may be logic developers might need to invoke beforehand (permissions etc). These two factors make `Attributes` as typically used a less likely candidate as the implimentation becomes [more atypical](https://en.wikipedia.org/wiki/Principle_of_least_astonishment). at least if an `Attribute` were to be applied to the `FeatureController` itself.

So as we have seen, yes we are using a `marker interface` but it its use is internal and not user facing. The 'magic' if any of this, is that child Accessories are automatically called in an almost identical way as `Attributes` using `Reflection`.

One of the nice things with Attributes is that they automatically call the constructor on their named classes, a feature we are essentially with our adjacent `YAccessoryManager` class. One of the principle difference here is that while we may or may not use a constructor we can also invoke these manually using the enforced `init()`. This gives us greater control as to how our Accessory is triggered in the `FeatureManager`, is it triggered when the FeatureController is registered, where `__contruct()` is called, or could can it be called with a post registration method after all Accessories have been registered and initialized?

The last point on the potential problem of inheritance has yet to be addressed, and cannot be resolved if the plan is to further extend our `FeatureController`. This is why we strongly recommend that all FeatureControllers are declared as `final`, to keep your inheritance tree relatively flat.

### How could we pass complex parameters to an `Attribute` if we went that direction?

One way to do this, would be to separate the configuration passed to an `Attribute`, into a separate class, perhaps as static methods, or even reference a static class on the current class, which would be convoluted but keep all the logic in one place. While this approach could would indeed separate out our concerns, which is good, it makes it much harder to support `Interfaces` to structure the implementation on these new classes and methods being passed in. Further, most `Accessory` classes as envisioned wouldn't require so much logic that they would bloat the `FeatureController` significantly, indeed the goal of the Accessory API is to remove most of that bloat.

It allows us keep any required methods and data need by the `Accessory` on the `FeatureController` class itself if we wish, and not shunt them onto other class, or use convoluted references static methods.

### Conclusion

We have chosen to stick with an `Interface` implementation of our `Accessory API` as it seems to offer the most flexibility, while also providing guidance for developers with a straight forward implementation.

Sources:

-   [Wikipedia: Marker Interface Pattern](https://en.wikipedia.org/wiki/Marker_interface_pattern)
-   [Stack Overflow: What's the Good of Marker Interface](https://stackoverflow.com/questions/56862117/whats-the-good-of-marker-interface)
-   [Marker Interface Isn't a Pattern or a Good Idea](https://dzone.com/articles/marker-interface-isnt-a-pattern-or-a-good-idea)
-   [Principle of least astonishment](https://en.wikipedia.org/wiki/Principle_of_least_astonishment)
-   [PHP 8: Attributes](https://stitcher.io/blog/attributes-in-php-8)
