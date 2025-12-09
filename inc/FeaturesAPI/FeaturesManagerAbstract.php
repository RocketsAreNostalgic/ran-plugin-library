<?php
/**
 * Abstract Features Manager for registering and loading feature controllers.
 *
 * Order of operations:
 *  * create a new FeatureController instance
 *  * inject declared dependencies
 *  * enable any Accessory managers found on the instance
 *  * call init() from _load_feature_container()
 *
 * @package  RanPluginLib
 */

declare(strict_types = 1);

namespace Ran\PluginLib\FeaturesAPI;

use Exception;
use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\FeaturesAPI\FeatureRegistry;
use Ran\PluginLib\FeaturesAPI\FeatureContainer;
use Ran\PluginLib\FeaturesAPI\RegistrableFeatureInterface;

/**
 * Manages feature objects by registering them and loading them.
 */
abstract class FeaturesManagerAbstract {
	/**
	 * The container object for individual registered features.
	 *
	 * @var FeatureContainer Container for storing feature information.
	 */
	protected FeatureContainer $feature;

	/**
	 * A registry or object cache of registered features.
	 *
	 * @var FeatureRegistry Registry for storing multiple feature containers.
	 */
	private FeatureRegistry $registery;

	/**
	 * Reference to the current configuration object.
	 *
	 * @var ConfigInterface Reference to the application configuration object.
	 */
	protected ConfigInterface $plugin;

	/**
	 * Constructor assigns the configuration reference and creates a new FeatureRegistry.
	 *
	 * @param  ConfigInterface $plugin The configuration instance.
	 */
	public function __construct( ConfigInterface $plugin ) {
		$this->plugin    = $plugin;
		$this->registery = new FeatureRegistry();
	}

	/**
	 * Does some sanitization of the $slug_id, then sets incoming feature values to a FeatureContainer and registers it to the FeatureRegistry.
	 *
	 * @param  string               $slug_id Required. A url safe slug for the feature page in the admin.
	 * @param  string               $qualified_classname Required. The string representation of a fully qualified feature class.
	 * @param  array<string, mixed> $deps Optional. A nested array of dependency values to inject into the FeatureController at instantiation. Each dependency key must match a declared property on the controller (public, or private/protected with a corresponding public set_<property>() setter).
	 *
	 * @throws Exception Throws if the slug_id is not unique.
	 */
	public function register_feature(
		string $slug_id,
		string $qualified_classname,
		array $deps = array(),
	): void {
		$slug_id = sanitize_title( $slug_id );

		if ( ! $slug_id || array_key_exists( $slug_id, $this->registery->get_registery() ) ) {
			throw new Exception( \sprintf( '"A unique feature slug is required for managed class: "%s', $qualified_classname ) );
		}

		$feature = new FeatureContainer(
			$qualified_classname,
			$slug_id,
			$deps,
		);

		// Add the FeatureContainer to the FeatureRegistry.
		$this->registery->set_feature( $feature );
	}

	/**
	 * Iterates over all registered features and loads each one by delegating to _load_feature_container().
	 *
	 * For each feature, we:
	 * - create the FeatureController instance
	 * - inject declared dependencies
	 * - enable Accessory managers for any implemented Accessory interfaces
	 * - call init() on the instance
	 */
	public function load_all(): void {
		$features = $this->registery->get_registery();
		foreach ( $features as $feature ) {
			$this->_load_feature_container( $feature );
		}
	}

	/**
	 * Load a single FeatureController by its $slug_id.
	 *
	 * @param  string $slug_id The string id of the FeatureController.
	 */
	public function load( string $slug_id ): void {
		$feature = $this->get_registered_feature( $slug_id );

		$this->_load_feature_container( $feature );
	}

	/**
	 * Load a single Feature from its FeatureContainer.
	 *
	 * @param  FeatureContainer $feature A FeatureContainer object.
	 */
	protected function _load_feature_container( FeatureContainer $feature ): void {
		// Create new feature class.
		$instance = $this->_create_new_feature_class(
			$this->plugin,
			$feature->qualified_classname,
			$feature->deps
		);
		// Initialize the FeatureController.
		$instance->init();

		// Add a reference to the the new feature instance on its FeatureContainer.
		$feature->set_instance( $instance );
	}

	/**
	 * Create new feature class and sets dependencies, provided it is a child of FeatureControllerAbstract and implements the RegistrableFeatureInterface.
	 *
	 * @param  ConfigInterface      $plugin The current configuration instance.
	 * @param  string               $class The fully qualified class name.
	 * @param  array<string, mixed> $deps An array of dependencies to set on the new instance.
	 *
	 * @throws \Exception Throws when the feature class doesn't exist.
	 * @throws \Exception Throws when the feature class doesn't implement the RegistrableFeatureInterface.
	 * @throws \Exception Throws when the feature class doesn't extend FeatureControllerAbstract or implement RegistrableFeatureInterface or .
	 * @throws \Exception Throws if $deps property hasn't been declared on the FeatureController before trying to set its value.
	 */
	protected function _create_new_feature_class(
		ConfigInterface $plugin,
		string $class,
		array $deps
	): RegistrableFeatureInterface {
		if ( ! isset( class_implements( $class )['Ran\PluginLib\FeaturesAPI\RegistrableFeatureInterface'] ) ) {
			throw new \Exception(
				\sprintf(
					'Each FeatureController must impliment RegistrableFeatureInterface:  %s',
					print_r( $class, true )
				)
			);
		}
		if ( ! is_subclass_of( $class, 'Ran\\PluginLib\\FeaturesAPI\\FeatureControllerAbstract' ) ) {
			throw new \Exception( \sprintf( 'Each FeatureController must extend FeatureControllerAbstract:  %s', print_r( $class, true ) ) );
		}

		// Create new instance.
		$instance = new $class( $plugin );

		// Inject dependencies.
		$this->_set_instance_dependencies( $instance, $deps );
		// Call any Accessory Interfaces.
		$this->_enable_instance_accessiories( $instance );

		return $instance;
	}
	/**
	 * This method injects soft dependencies onto our FeatureController instances.
	 *
	 * If the FeatureController:
	 * * has a public property with the same name as the array key of the dependency it will set it directly (type checking would be wise).
	 * * has a private or protected property with the same name as the array key of the dependency it will look for a public setter method named set_{property_name}().
	 * * has a public method named set_{property_name}() it will call that method with the dependency value.
	 *
	 * @param  RegistrableFeatureInterface $instance The FeatureController instance.
	 * @param  array<string, mixed>        $deps An array of dependencies to set on the instance.
	 *
	 * @throws \Exception Will throw if the property is not present, public, or if the property is protected|private and a set_*() method has not been defined.
	 */
	protected function _set_instance_dependencies( RegistrableFeatureInterface $instance, array $deps ): void {
		$reflected_instance = new \ReflectionClass( $instance );

		// Loop through all deps and assign them to the object, but only if the prop has been set on the FeatureController.
		// At the time of this writing, WP only offers beta support for 8.0 - 8.2.
		// However dynamic property assignments are deprecated in PHP 8.2, so lets future proof.
		foreach ( $deps as $key => $value ) {
			if ( $reflected_instance->hasProperty( key( $value ) ) ) {
				$reflected_property = $reflected_instance->getProperty( key( $value ) );
				if ( $reflected_property->isPublic() ) {
					// If the property is public set it directly.
					$instance->{key( $value )} = $value[ key( $value ) ];
				} else if ( $reflected_property->isPrivate() || $reflected_property->isProtected() ) {
					// Look for a set_* method with the name of the property key.
					$setter = 'set_' . key( $value );
					if ( $reflected_instance->hasMethod( $setter ) ) {
						$reflected_method = $reflected_instance->getMethod( $setter );
						if ( $reflected_method->isPublic() ) {
							// We have a public setter method, so set the property.
							$instance->$setter( $value[ key( $value ) ] );
						} else {
							throw new Exception( \sprintf( 'Can not set property "%s" because it is not "public", and a public method "set_%s()" has not been declared on "%s".', key( $value ), key( $value ), get_class( $instance ) ) );
						}
					} else {
						throw new Exception( \sprintf( 'Can not set property "%s" because the public method "set_%s()" has not been declared on "%s".', key( $value ), key( $value ), get_class( $instance ) ) );
					}
				}
			} else {
				throw new Exception( \sprintf( 'Can not set property "%s" because it has not been declared on "%s"', key( $value ), get_class( $instance ) ) );
			}
		}
	}

	/**
	 * This method iterates over the interfaces implemented by the current FeatureController instance and checks
	 * for any that extend AccessoryBaseInterface. For each match, it loads the adjacent <Accessory>Manager and calls its init() method.
	 *
	 * @param  RegistrableFeatureInterface $instance The FeatureController instance.
	 *
	 * @throws \Exception Throws when the Accessory is missing its <Accessory>Manager adjacent class.
	 * @throws \Exception Throws when the <Accessory>Manager adjacent class doesn't implement AccessoryManagerBaseInterface.
	 */
	protected function _enable_instance_accessiories( RegistrableFeatureInterface $instance ): void {
		// Array of implemented interfaces.
		$interfaces = \class_implements( $instance );

		// Loop thought any interfaces.
		foreach ( $interfaces as $interface_name ) {
			// Check that our AccessoryInterface is an implementation of AccessoryBaseInterface.
			$reflection_interface = new \ReflectionClass( $interface_name );
			if ( $reflection_interface->implementsInterface( 'Ran\PluginLib\AccessoryAPI\AccessoryBaseInterface' ) ) {
				// Manager classes have a suffix of 'Manager', without the word Interface ie
				// AccessoryInterface becomes 'AccessoryManager'.
				$adjacent_class = str_replace( 'Interface', '', $interface_name ) . 'Manager';
				if ( class_exists( $adjacent_class ) ) {
					// Check that the AccessoryManager is an implementation of AccessoryManagerBaseInterface.
					$reflection_manager = new \ReflectionClass( $adjacent_class );
					if ( $reflection_manager->implementsInterface( 'Ran\PluginLib\AccessoryAPI\AccessoryManagerBaseInterface' ) ) {
						// Load the AccessoryManager and call its init method for it to do its work.
						$manager = new $adjacent_class();
						$manager->init( $instance );
					} else {
						throw new \Exception( \sprintf( 'The Manager class for AccessoryInterface "%s" does not impliment the "AccessoryManagerBaseInterface" and cannot be called.', $interface_name, $adjacent_class, get_class( $instance ) ) );
					}
				} else {
					throw new \Exception( \sprintf( 'The AccessoryInterface "%s" is missing its adjacent Manager "%s".', $interface_name, $adjacent_class, \get_class( $instance ) ) );
				}
			}
		}
	}

	/**
	 * Returns the array of registered FeatureContainers.
	 *
	 * @return array<string, FeatureContainerInterface> Array of FeatureContainerInterface objects indexed by slug.
	 */
	public function get_registry(): array {
		return $this->registery->get_registery();
	}

	/**
	 * Returns a FeatureContainer object for the passed slug_id, or null.
	 *
	 * @param  string $slug_id The feature's slug ID string passed through sanitize_title().
	 */
	public function get_registered_feature( string $slug_id ): FeatureContainer|null {
		return $this->registery->get_feature( $slug_id );
	}
}
