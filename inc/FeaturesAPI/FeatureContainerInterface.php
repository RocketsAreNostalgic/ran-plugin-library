<?php
/**
 * A container for feature configuration.
 *
 * @package  RanPlugin
 */

declare(strict_types=1);
namespace Ran\PluginLib\FeaturesAPI;

use Ran\PluginLib\FeaturesAPI\RegistrableFeatureInterface;
/**
 * Interface for FeatureContainer objects.
 */
interface FeatureContainerInterface {

	/**
	 * The string representation of a fully qualified feature class.
	 *
	 * @var string
	 *
	 * * public readonly string $qualified_classname;
	 */


	/**
	 * A unique string slug for the feature used as key in the registry and as page id slugs in any admin screens.
	 * This should be passed through the WordPress sanitize_title() function.
	 *
	 * @var string
	 *
	 * * public readonly ?string $slug;
	 */

	/**
	 * An object of named dependancies required by the feature.
	 * Each key name should represent a public object parameter already declared on the FeatureController,
	 * as PHP 8.2.0 deprecated dynamic property assignments.
	 *
	 * @var \stdClass
	 *
	 * private readonly \stdClass $deps;
	 */


	/**
	 * A reference to the feature instance.
	 *
	 * @var ?RegistrableFeatureInterface|null The feature instance.
	 *
	 * * private ?RegistrableFeatureInterface $instance = null;
	 */

	/**
	 * Store the current instance of the registered feature as a WeakReference.
	 *
	 * @param  RegistrableFeatureInterface $instance The stored instance must be Registrable.
	 *
	 * @return bool
	 */
	public function set_instance( RegistrableFeatureInterface $instance ):RegistrableFeatureInterface|false;

	/**
	 * Get the instance associated with a slug identifier string
	 *
	 * @return RegistrableFeatureInterface|null
	 */
	public function get_instance():RegistrableFeatureInterface | null;

}
