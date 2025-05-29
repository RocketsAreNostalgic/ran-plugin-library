<?php
/**
 * Abstract implementation of Plugin class.
 *
 * TODO: it is an open question as to if Accessory interfaces should be called before or after the FeatureController's init method is called.
 * Currently in we:
 *  *  create a new FeatureController object
 *  *  inject any dependencies that have been declared
 *  *  trigger any Accessories found on the FeatureController
 *  *  and then return the new FeatureController.
 *  *  We leave it to the Bootstrap method to call the init method.
 *
 * @package  RanPluginLib
 */

declare(strict_types = 1);

namespace Ran\PluginLib\FeaturesAPI;

use Exception;
use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\FeaturesAPI\FeatureContainer;
use Ran\PluginLib\FeaturesAPI\FeatureRegistry;
use Ran\PluginLib\FeaturesAPI\RegistrableFeatureInterface;

/**
 * Manages Features Objects by registering them with the Plugin class, and loading them.
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
	 * Reference to the current Plugin object.
	 *
	 * @var ConfigInterface Reference to the plugin configuration object.
	 */
	protected ConfigInterface $plugin;

	/**
	 * Constructor creates a new FeatureContainer object
	 * and assigns our Plugin object reference.
	 *
	 * @param  ConfigInterface $plugin The Plugin instance.
	 */
	public function __construct( ConfigInterface $plugin ) {
		$this->plugin    = $plugin;
		$this->registery = new FeatureRegistry();
	}

	/**
	 * Does some sanitization, of the $slug_id, and then sets  incoming feature values to a FeatureContainer and registers it to the FeatureRegistry.
	 *
	 * @param  string               $slug_id Required. A url safe slug for the feature page in the admin.
	 * @param  string               $qualified_classname Required. The string representation of a fully qualified feature class.
	 * @param  array<string, mixed> $deps Optional. An array of dependancies that the feature container may need. NOTE the array is cast to an object in the registered FeatureContainer for DX.
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
			throw new Exception( \sprintf( '"A unique feature slug is required for managed class: "%s', esc_html( $qualified_classname ) ) );
		}

		$feature = new FeatureContainer(
			$qualified_classname,
			$slug_id,
			$deps, // Cast the incoming array to an object.
		);

		// Add the FeatureContainer to the FeatureRegistry.
		$this->registery->set_feature( $feature );
	}

	/**
	 * Loop through all registered Feature classes.
	 *
	 * Here we inspect each interface that the Feature class implements.
	 * * If the interface implements the InterfacesAPIInterface then we check if there is a manager class.
	 * * If the
	 */
	public function load_all(): void {
		$features = $this->registery->get_registery();
		foreach ( $features as $feature ) {
			$this->load_feature_container( $feature );
		}
	}

	/**
	 * Load a single FeatureController by its $slug_id.
	 *
	 * @param  string $slug_id The string id of the FeatureController.
	 */
	public function load( string $slug_id ): void {
		$feature = $this->get_registered_feature( $slug_id );

		$this->load_feature_container( $feature );
	}

	/**
	 * Load a single Feature from its FeatureContainer.
	 *
	 * @param  FeatureContainer $feature A FeatureContainer object.
	 */
	protected function load_feature_container( FeatureContainer $feature ): void {
		// Create new feature class.
		$instance = $this->create_new_feature_class(
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
	 * @param  ConfigInterface      $plugin The current Plugin instance.
	 * @param  string               $class The fully qualified class name.
	 * @param  array<string, mixed> $deps An array of dependencies to set on the new instance.
	 *
	 * @throws \Exception Throws when the feature class doesn't exist.
	 * @throws \Exception Throws when the feature class doesn't implement the RegistrableFeatureInterface.
	 * @throws \Exception Throws when the feature class doesn't extend FeatureControllerAbstract or implement RegistrableFeatureInterface or .
	 * @throws \Exception Throws if $deps property hasn't been declared on the FeatureController before trying to set its value.
	 */
	protected function create_new_feature_class(
		ConfigInterface $plugin,
		string $class,
		array $deps
	): RegistrableFeatureInterface {
		if ( ! isset( class_implements( $class )['Ran\PluginLib\FeaturesAPI\RegistrableFeatureInterface'] ) ) {
			throw new \Exception(
				\sprintf(
					'Each FeatureController must impliment RegistrableFeatureInterface:  %s',
					esc_html( print_r( $class ) )
				)
			);
		}
		if ( ! is_subclass_of( $class, 'Ran\PluginLib\FeaturesAPI\FeatureControllerAbstract' ) ) {
			throw new \Exception( \sprintf( 'Each FeatureController must extend FeatureControllerAbstract:  %s', esc_html( print_r( $class ) ) ) );
		}

		// Create new instance.
		$instance = new $class( $plugin );

		// Inject dependencies.
		$this->set_instance_dependencies( $instance, $deps );
		// Call any Accessory Interfaces.
		$this->enable_instance_accessiories( $instance );

		return $instance;
	}
	/**
	 * This methods injects soft dependencies onto our FeatureController instances.
	 *
	 * If the FeatureController
	 * * has a public property with the same name as the array key of the dependency it will set it directly (type checking would be wise).
	 * * has a private or protected property with the same name as the array key of the dependency it will look for a public setter method named set_{property_name}().
	 * * has a public method named set_{property_name}() it will call that method with the dependency value.
	 *
	 * @param  RegistrableFeatureInterface $instance The FeatureController instance.
	 * @param  array<string, mixed>        $deps An array of dependencies to set on the instance.
	 *
	 * @throws \Exception Will throw if the property is not present, public, or if the property is protected|private and a sett_*() method has not been defined.
	 */
	protected function set_instance_dependencies( RegistrableFeatureInterface $instance, array $deps ): void {
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
							throw new Exception( \sprintf( 'Can not set property "%s" because it is not "public", and a public method "set_%s()" has not been declared on "%s".', esc_html( key( $value ) ), esc_html( key( $value ) ), esc_html( get_class( $instance ) ) ) );
						}
					} else {
						throw new Exception( \sprintf( 'Can not set property "%s" because the public method "set_%s()" has not been declared on "%s".', esc_html( key( $value ) ), esc_html( key( $value ) ), esc_html( get_class( $instance ) ) ) );
					}
				}
			} else {
				throw new Exception( \sprintf( 'Can not set property "%s" because it has not been declared on "%s"', esc_html( key( $value ) ), esc_html( get_class( $instance ) ) ) );
			}
		}
	}

	/**
	 * This methods iterates over the interfaces implemented by the current FeatureController instance and looks to see if any
	 * extend AccessoryBaseInterface. If one does, it will then load the adjacent <Accessory>Manager and call its init() method.
	 *
	 * @param  RegistrableFeatureInterface $instance The FeatureController instance.
	 *
	 * @throws \Exception Throws when the Accessory is missing its <Accessory>Manager adjacent class.
	 * @throws \Exception Throws when the <Accessory>Manager adjacent class doesn't implement AccessoryManagerBaseInterface.
	 */
	protected function enable_instance_accessiories( RegistrableFeatureInterface $instance ): void {
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
						throw new \Exception( \sprintf( 'The Manager class for AccessoryInterface "%s" does not impliment the "AccessoryManagerBaseInterface" and cannot be called.', esc_html( $interface_name ), esc_html( $adjacent_class ), esc_html( get_class( $instance ) ) ) );
					}
				} else {
					throw new \Exception( \sprintf( 'The AccessoryInterface "%s" is missing its adjacent Manager "%s".', esc_html( $interface_name ), esc_html( $adjacent_class ), esc_html( \get_class( $instance ) ) ) );
				}
			}
		}
	}

	/**
	 * Returns the array of registered FeaturesControllers.
	 *
	 * @return array<string, FeatureContainer> Array of FeatureContainer objects indexed by slug.
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
