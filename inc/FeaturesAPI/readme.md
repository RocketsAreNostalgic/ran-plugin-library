# Features API

The Features API handles registering and loading of a plugin or theme's 'Features'. Features are self-contained bits of functionality, such as a shortcode or an admin page –– any discrete tasks or groups of tasks can be considered a 'Feature'. The work done by a Feature is carried out by its `FeatureController`, and by convention these are found in the directory `/plugin-root/inc/features/SomeFeatureController.php`.

At a high level, in a bootstrap file, the `FeaturesManager::register_feature($slug_id, $qualified_classname, $deps)` method registers each Feature by creating a `FeatureContainer` and storing it in a `FeatureRegistry`.

A unique string key (`slug_id`) acts as both the key to each `FeatureContainer` object in the `FeatureRegistry` and as an internal field on the `FeatureContainer`, which may be used as an identifier in wp_options and in URLs.

## Feature Containers

`FeatureContainers`, as previously stated, are controller classes that contain the implementation of discrete chunks of functionality, and the `FeaturesManager` class loads them. In order for a `FeatureContainer` to be recognized as a valid feature, it must implement `Ran\PluginLib\FeaturesAPI\RegistrableFeatureInterface`, which requires the implementation of an `init()` method.

### Dependencies

When registering a Feature with `FeaturesManager->register_feature()`, an optional array of dependencies can be passed, which will be injected into the FeatureController during instantiation. Each of the keys must correspond to a property on your `FeatureController` class. As of PHP 8.2, arbitrary property declarations are deprecated, so this step helps to future-proof your `FeatureContainer` code.

If the property is `public`, then the `FeatureManager` will assign the value directly, and setting a PHP type declaration will help control the kinds of values that can be set. If the property is `protected`, or `private` then the `FeatureManager` will look for a matching setter method with the name `set_<yourproperty>($value)`. This is useful if you need to keep the value from being accessed from outside the class or if you want to do other operations on the incoming value.

The `FeatureManager` will throw exceptions if the property is not found or if it cannot access it, either directly or with an optional setter method.

### Config

After registration, each `FeatureController` is instantiated and passed a `ConfigInterface` implementation. The Config object provides normalized configuration (paths, slugs, text domains, etc.) via `ConfigInterface::get_config()`. See `inc/Config/ConfigInterface.php` for details.

### Accessories (synopsis)

Accessories are ways to interact with WordPress APIs that are commonly used but otherwise involve complex implementation or excessive boilerplate. They are triggered by adding an Accessory interface to a registered `FeatureController`.

Accessories interfaces that have an associated `XXAccessoryManager` class. It's the job of an `AccessoryManager` to call any required methods within the `FeatureController`. For example, the `HooksAccessory` provides a means to interact with WordPress Action Hooks and Filters APIs.

During the `FeaturesManager->load_all()`/`load()` process, `FeatureControllers` are checked to see if they implement any Accessory interface(s). If an Accessory interface is found, its implementation is executed while loading.

Each Accessory's interface has detailed notes on what a FeatureController requires to implement that interface.

## `FeaturesManager` Class

In your bootstrap file, `FeatureControllers` can be registered using the `FeaturesManager->register_feature()` method:

```php
use Ran\PluginLib\FeaturesAPI\FeaturesManager;

// $config must implement Ran\PluginLib\Config\ConfigInterface
$manager = new FeaturesManager($config);

// Register a feature: slug, FQCN, optional nested deps array
$manager->register_feature(
    'example-feature',
    Features\ExampleFeatureController::class,
    [ [ 'some_dep' => $value ] ]
);

$manager->load_all();
```

### `register_feature()`

The `register_feature()` method takes the following parameters:

- Required: (string) A unique slug name of the feature. This is used as the key for the feature in the `Registry`, and may be used as the feature's slug name in any admin UI screens. This string is passed through the WordPress [`sanitize_title()`](https://developer.wordpress.org/reference/functions/sanitize_title/) function.
- Required: (string) The fully qualified name of the feature controller class:
  `Fully\Qualified\Classname\ToFeatureController::class`
- Optional: (array) A nested array of dynamically injected values that the Feature may need. Each dependency key must correspond to a declared property on the FeatureController (public, or private/protected with a matching public `set_<property>()` setter).
  `array( array(dependency_public_key => its_value) )`

Under the hood, `register_feature()` loads the above values onto `FeatureContainer` objects and then adds each object into a `FeatureRegistry`.

### `load_all()`

The `load_all()` method should be called after all FeatureControllers have been registered. This method iterates over all registered features and does the following:

- Checks that the feature implements `RegistrableFeatureInterface`
- Creates a new instance of the `FeatureController`
- Iterates over all the implemented interfaces, looking for any Accessories † which should be called on the `FeatureController`
- Instantiates the feature by calling its `init()` method

### `load($slug_id)`

The `load($slug_id)` method loads a registered feature by its slug ID. It instantiates the controller, injects declared dependencies, enables any Accessory interfaces, then calls the feature’s `init()` method.

### `get_registry()`

`get_registry()` returns an array of the registered Features.

### `get_registered_feature()`

`get_registered_feature('slug_id')` returns the `FeatureContainer` object (or null) for the given feature slug.

## `FeatureContainer` Class

A `FeatureContainer` is an intermediary object that contains details about a specific feature. Generally, you would not create your own `FeatureContainer` but instead register new features with `FeaturesManager->register_feature()`.

```php
    public readonly string $qualified_classname
    // $slug must be unique to be added to the registry.
    public readonly string $slug,
    public readonly array $deps
```

Additionally, FeatureContainers store a private reference to the instantiated feature, which can be accessed via `get_instance()` (and set once via `set_instance()`).
