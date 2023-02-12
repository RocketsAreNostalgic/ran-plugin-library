<?php
/**
 * Abstract implementation of Plugin class.
 *
 * TODO: it is an open question as to if Accessory interfaces should be called before or after the FeatureController's init method is called.
 *  * Currently in we:
 *  *  create a new FeatureController object
 *  *  inject any dependencies that have been declared
 *  *  trigger any Accessories found on the FeatureController
 *  *  and _then_ call the FeatureController's init method.
 *
 * @package  RanPlugin
 */

namespace Ran\PluginLib\FeaturesAPI;

use Exception;
use Ran\PluginLib\FeaturesAPI\RegistrableFeatureInterface;
use Ran\PluginLib\Plugin\PluginInterface;

/**
 * Manages Features Objects by registering them with the Plugin class, and loading them.
 */
abstract class FeaturesManagerAbstract {

	/**
	 * The container object for individual registered features.
	 *
	 * @var FeatureContainer
	 */
	protected FeatureContainer $feature;

	/**
	 * A registry or object cache of registered features.
	 *
	 * @var FeatureRegistry A registry or object cache of registered features.
	 */
	private FeatureRegistry $registery;

	/**
	 * Reference to the current Plugin object.
	 *
	 * @var PluginInterface
	 */
	protected PluginInterface $plugin;

	/**
	 * Constructor creates a new FeatureContainer object
	 * and assigns our Plugin object reference.
	 *
	 * @param  PluginInterface $plugin The Plugin instance.
	 */
	public function __construct( PluginInterface $plugin ) {
		$this->plugin = $plugin;
		$this->registery = new FeatureRegistry();
	}

	/**
	 * Does some sanitization, of the $slug_id, and then sets  incoming feature values to a FeatureContainer and registers it to the FeatureRegistry.
	 *
	 * @param  string $slug_id Required. A url safe slug for the feature page in the admin.
	 * @param  string $qualified_classname Required. The string representation of a fully qualified feature class.
	 * @param  array  $deps Optional. An array of dependancies that the feature container may need. NOTE the array is cast to an object in the registered FeatureContainer for DX.
	 *
	 * @return void
	 * @throws Exception Throws if the slug_id is not unique.
	 */
	public function register_feature(
		string $slug_id,
		string $qualified_classname,
		array $deps = array(),
		) {

		$slug_id = sanitize_title( $slug_id );

		if ( ! $slug_id || array_key_exists( $slug_id, $this->registery->get_registery() ) ) {
			throw new Exception( \sprintf( '"A unique feature slug is required for managed class: "%s', $qualified_classname ) );
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
	 *
	 * @return void
	 */
	public function load_all():void {
		$features = $this->registery->get_registery();
		foreach ( $features as $feature ) {
			// Create our new feature class.
			$instance = $this->create_new_feature_class(
				$this->plugin,
				$feature->qualified_classname,
				$feature->deps
			);
			// Initialize the FeatureController.
			$instance->init();

			// Cache a reference to the FeatureController in its FeatureContainer in the FeatureRegistry.
			$feature->set_instance( $instance );
		}
	}

	/**
	 * Create new feature class and sets dependencies, provided it is a child of FeatureControllerAbstract and implements the RegistrableFeatureInterface.
	 *
	 * @param  PluginInterface $plugin The current Plugin instance.
	 * @param  string          $class The fully qualified name of the class to be instantiated.
	 * @param  array           $deps An array of dependencies for the FeatureController.
	 *
	 * @return RegistrableFeatureInterface New instance of the given feature class.
	 * @throws \BadMethodCallException Throws if the calling class does not extend FeatureControllerAbstract or implement RegistrableFeatureInterface or .
	 * @throws \Exception Throws if $deps property hasn't been declared on the FeatureController before trying to set its value.
	 */
	private function create_new_feature_class( PluginInterface $plugin, string $class, array $deps ):RegistrableFeatureInterface {

		if ( ! isset( class_implements( $class )['Ran\PluginLib\FeaturesAPI\RegistrableFeatureInterface'] ) ) {
			throw new \BadMethodCallException( \sprintf( 'Each FeatureController must impliment RegistrableFeatureInterface:  %s', print_r( $class ) ) );
		}
		if ( ! is_subclass_of( $class, 'Ran\PluginLib\FeaturesAPI\FeatureControllerAbstract' ) ) {
			throw new \BadMethodCallException( \sprintf( 'Each FeatureController must extend FeatureControllerAbstract:  %s', print_r( $class ) ) );
		}
		$instance = new $class( $plugin );
		// Loop through all deps and assign them to the object, but only if the prop has been set on the FeatureController.
		// Dynamic property assignments are deprecated in PHP 8.2, so lets future proof.
		// At the time of this writing, WP only offers beta support for 8.0 - 8.2.
		foreach ( $deps as $key => $value ) {
			if ( property_exists( $instance, key( $value ) ) ) {
				$instance->{key( $value )} = $value[ key( $value ) ];
			} else {
				throw new Exception( \sprintf( 'Can not set property "%s because it has not been declared in %s"', key( $value ), $class ) );
			}
		}

		// Loop thought any Aspect interfaces and call their managers.
		$interfaces  = \class_implements( $instance );
		foreach ( $interfaces as $interface_name ) {
			// Check that our AccessoryInterface is an implementation of AccessoryBaseInterface.
			$reflection_interface = new \ReflectionClass( $interface_name );
			if ( $reflection_interface->implementsInterface( 'Ran\PluginLib\AccessoryAPI\AccessoryBaseInterface' ) ) {
				// Manager classes have a suffix of 'Manager', without the word Interface ie
				// EventsInterface becomes 'EventsManager'.
				$adjacent_class = str_replace( 'Interface', '', $interface_name ) . 'Manager';
				if ( class_exists( $adjacent_class ) ) {
					// Check that the AttributeManager is an implementation of AttributeManagerBaseInterface.
					$reflection_manager = new \ReflectionClass( $adjacent_class );
					if ( $reflection_manager->implementsInterface( 'Ran\PluginLib\AttributesAPI\AttributeManagerBaseInterface' ) ) {
						// Load the AttributeManager and call its init method for it to do its work.
						$manager = new $adjacent_class();
						$manager->init( $instance );
					}
				}
			}
		}
		return $instance;
	}

	/**
	 * Returns the array of registered features
	 *
	 * @return array of FeatureContainer objects.
	 */
	public function get_registery():array {
		return $this->registery->get_registery();
	}

	/**
	 * Returns FeatureContainer object of the requested feature, or null.
	 *
	 * @param  string $slug_id The feature's slug ID string passed through sanitize_title().
	 *
	 * @return FeatureContainer|null
	 */
	public function get_registered_feature( string $slug_id ):FeatureContainer|null {
		return $this->registery->get_feature( $slug_id );
	}
}
