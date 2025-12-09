<?php
/**
 * A container for feature configuration.
 *
 * @package  RanPluginLib
 */

declare(strict_types = 1);

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
	 * * public readonly string $slug;
	 */

	/**
	 * An array of named dependencies required by the feature.
	 * Each inner array should contain a single key that matches a declared property on the FeatureController.
	 *
	 * @var array<int, array<string, mixed>>
	 *
	 * public readonly array $deps;
	 */


	/**
	 * A reference to the feature instance.
	 *
	 * @var ?RegistrableFeatureInterface|null The feature instance.
	 *
	 * * private ?RegistrableFeatureInterface $instance = null;
	 */

	/**
	 * Store the current instance of the registered feature if it has not already been set.
	 *
	 * @param  RegistrableFeatureInterface $instance The stored instance must be Registrable.
	 *
	 * @return RegistrableFeatureInterface|false Returns the instance on success, or false if already set.
	 */
	public function set_instance( RegistrableFeatureInterface $instance ): RegistrableFeatureInterface|false;

	/**
	 * Get the stored feature instance, or null if it has not been initialized.
	 */
	public function get_instance(): RegistrableFeatureInterface|null;
}
