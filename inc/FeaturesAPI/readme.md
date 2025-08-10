# Features API

The Features API handles registering and loading of a plugin or theme's 'Features'. Features are self-contained bits of functionality, such as a shortcode or an admin page –– any discreet tasks or groups of tasks can be considered a 'Feature'. The work done by a Feature is carried by its `FeatureController`, and by convention these are found in the directory `/plugin-root/inc/features/SomeFeatureController.php`.

At a high level, in a bootstrapping file, the `FeatureManager::register_feature()` method handles the registration of each Feature, storing its qualified namespace, `slug_id`, and dependencies into separate `FeatureManager` objects.

A unique string key (slug_id) acts as both the key to each `FeatureContainer` object in the `FeatureRegistry` and as an internal field on the `FeatureContainer`, which may be used as an identifier in wp_options and in URLs.

## Feature Containers

`FeatureContainers`, as previously stated, are controller classes that contain the implementation of discreet chunks of functionality, and the `FeaturesManager` class loads them. In order for a `FeatureContainer` to be recognized as a valid feature, it must implement `Ran\PluginLib\FeaturesAPI\RegistrableFeatureInterface`, which requires the implementation of an `init()` method.

### Dependencies

When registering a Feature with `FeaturesManager->register_feature()`, an optional array of dependencies can be passed, which will be injected into the FeatureController during instantiation. Each of the keys must correspond to a property on your `FeatureController` class. As of PHP 8.2, arbitrary property declarations are deprecated, so this step helps to future-proof your `FeatureContainer` code.

If the property is `public`, then the `FeatureManager` will assign the value directly, and setting a PHP type declaration will help control the kinds of values that can be set. If the property is `protected`, or `private` then the `FeatureManager` will look for a matching setter method with the name `set_<yourproperty>($value)`. This is useful if you need to keep the value from being accessed from outside the class or if you want to do other operations on the incoming value.

The `FeatureManager` will throw exceptions if the property is not found or if it cannot access it, either directly or with an optional setter method.

### Plugin

After registration, each`FeatureController` is instantiated and passed a reference to the `Plugin` class as a required parameter. The Plugin class contains relevant parameters such as paths and text domains, which can be accessed as an array with the `Plugin::get_plugin()` method. See the readme Plugin for more information.

### Accessories (synopsis)

Accessories are ways to interact with WordPress APIs that are commonly used but otherwise involve complex implementation or excessive boilerplate. They are triggered by adding an Attribute interface to a registered `FeatureController`.

Accessories interfaces that have an associated `XXAccessoryManager` class. It's the job of an `AccessoryManager` to call any required methods within the `FeatureController`. For example, the `HooksAccessory` provides a means to interact with WordPress Action Hooks and Filters APIs.

During the `FeatureManager->load()` process, `FeatureControllers` are checked to see if they `implement` any Accessory interface(s). If an Attribute interface is found, its implementation is executed while loading.

Each Accessory's interface has detailed notes on what a FeatureController requires to implement that interface.

## `FeatureManager` Class

In your bootstrap file, `FeatureControllers` can be registered using the `FeatureManager->register_feature()` method:

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

- Required: (string) The fully qualified name of the feature controller class:
  `Fully\Qualified\Classname\ToFeatureController::class`
- Required: (string) A unique slug name of the feature. This is used as the key for the feature in the `Registry`, and may be used as the feature's slug name in any admin UI screens. This string is passed through the WordPress [`sanitize_title()`](https://developer.wordpress.org/reference/functions/sanitize_title/) function.
- Optional: (array) A nested array of dynamically injected values that the Feature may need. The key name of the dependency _must also_ be a public property of the Feature object.
  `array( array(dependency_public_key => its_value) )`
- Optional: (boolean) indicating if the feature has a public settings screen

Under the hood, `register_feature()` loads the above values unto `FeatureContainer` objects and then adds each object into a FeatureRegistry object.

### `load_all()`

The `load_all()` method should be called after all FeatureControllers have been registered. This method iterates over all registered features and does the following:

- Checks that the feature has implemented the `Registerable` interface
- Creates a new instance of the `FeatureController`
- Iterates over all the implemented interfaces, looking for any Accessories † which should be called on the `FeatureController`
- Instantiates the feature by calling its `init()` method

### `load($slug_id)`

The `load(FeatureContainer)` takes a feature and checks that it is not in the registry, and, if not, adds it and immediately invokes it.

### `get_registery()`

`get_registery()` returns an array of the registered Features.

### `get_registered_feature()`

`get_registered_feature('slug_id')` Returns the `FeatuerContainer` object for the given Feature slug.

## `FeatureContainer` Class

A `FeatureContainer` is an intermediary object that contains details about a specific feature. Generally, you would not create your own `FeatureContainer` but instead register new features with `FeatureManager-register_feature()`.

```php
    public readonly string $qualified_classname
    //$slug_id's must be unique to be added to the registry.
    public readonly string $slug_id,
    public readonly \stdClass $deps
```

Additionally, FeatureContainers can store a `private` reference to the instantiated FeatureContainer, which can be accessed via
