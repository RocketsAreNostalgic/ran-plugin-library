# Features API

The Features API handles the loading our plugin's 'Features'. Features are self contained bits of our plugin's functionality, such as a shortcode, or an admin page –– any discreet tasks or groups of tasks can be considered a Feature. The work done by a Feature is managed in its `FeatureController`.

At a high level, the `FeatureManager` class registers the details for each Feature, its qualified namespace, slug, and any dependencies into separate `FeatureContainer` objects, each with a unique key (slug). Each `FeatureContainer` object is then register into a `FeatureRegistry` object which contains all registered features.

## Feature Containers

`FeatureContainers` are controller classes that contain the implementation of that discreet chunks of functionality, and they are loaded by the `FeaturesManager` class. In order for a FeatureContainer to be recognized as a valid feature it must implement `Ran\PluginLib\FeaturesAPI\RegistrableFeatureInterface` which requires the implementation of a `init()` method.

### Dependencies

When registering a Feature with `FeaturesManager->register_feature()`, there is an optional array of dependencies that can be passed, which will be injected into the FeatureController during instantiation. Each of the keys must correspond to a public property on the your `FeatureController` class.

### Attributes (synopsis)

Attributes are ways to interact with WordPress API's that are commonly used, but otherwise involve complex implementation or excessive boilerplate. They are triggered by adding an Attribute interface to an registered `FeatureController`.

`Attributes` are just interfaces that have an associated `XXAttributeManager` class. Its the job of the `AttributeManager` to call any required methods within the Feature. For example the `HooksAttribute` provides a means to interact with WordPresses' Action Hooks and Filters APIs.

During the `FeatureManager->load_all()` process, `FeatureControllers` are checked to see if they `implement` any Attribute interface(s). If an Attribute interface is found, its implementation is executed while loading.

Each Attribute's interface has detailed notes as to what is required by the controller to implement that interface.

## `FeatureManager` Class

In your bootstrap file FeatureControllers can be registered using the `FeatureManager->register_feature()` method:

```php

use Ran\PluginLib\FeaturesAPI\FeaturesManager;

$manager = new FeaturesManager( $this->plugin );

$manager->register_feature(
   Features\ExampleFeatureController::class,
   array(),
   true,
   'example-feature-manager',
   'Example Feature'
  );

$manager->load_all();
```

### `register_feature()`

The `register_feature()` method takes the following parameters:

-   Required: (string) The fully qualified name of the feature controller class:
    `Fully\Qualified\Classname\ToFeatureController::class`
-   Required: (string) A unique slug name of the feature. This is used as the key for the feature in the `Registry`, and may be used as the feature's slug name in any admin UI screens. This string is passed through the WordPress [`sanitize_title()`](https://developer.wordpress.org/reference/functions/sanitize_title/) function.
-   Optional: (array) A nested array of dynamically injected values that the Feature may need. The key name of the dependency _must also_ be a public property of the Feature object.
    `array( array(dependency_public_key => its_value) )`
-   Optional: (boolean) indicating if the feature has a public settings screen

Under the hood, `register_feature()` loads the above values unto `FeatureContainer` objects and then adds each object into a FeatureRegistry object.

### `load_all()`

The `load_all()` method is called after all features have been registered. This method iterates over all registered features does the following:

-   Checks that the feature has implemented the `Registerable` interface.
-   Iterates over all the implemented interfaces looking for any Attributes † that should be called on the feature.

### `load($slug_id)`

**TODO: Implement**

The `load(FeatureContainer)` takes a feature and checks that it is not in the registry and if not, adds it and immediately invokes it.

### `get_registery()`

`get_registery()` returns an array of the registered Features.

### `get_registered_feature()`

`get_registered_feature('slug_id')` Returns the `FeatuerContainer` object for the given Feature slug.

## `FeatureContainer` Class

A `FeatureContainer` is an intermediary object that contains details about a specific feature. Generally you would not create your own `FeatureContainer` by instead register new features with `FeatureManager-register_feature()`.

```php
    public readonly string $qualified_classname
    //$slug_id's must be unique to be added to the registery.
    public readonly string $slug_id,
    public readonly \stdClass $deps
```

Additionally FeatureContainers can store a `private` reference to the instantiated FeatureContainer, which can be accessed via  
