<?php
/**
 * A container for feature configuration.
 *
 * @package  RanPluginLib
 */

declare(strict_types = 1);

namespace Ran\PluginLib\FeaturesAPI;

use Ran\PluginLib\FeaturesAPI\FeatureContainerInterface;
use Ran\PluginLib\FeaturesAPI\RegistrableFeatureInterface;

/**
 * Abstract Feature Container class.
 */
abstract class FeatureContainerAbstract implements FeatureContainerInterface {
	/**
	 * A reference to the feature instance.
	 *
	 * @var ?RegistrableFeatureInterface|null The feature instance.
	 */
	private ?RegistrableFeatureInterface $instance = null;

	/**
	 * Construct function uses PHP 8.0 constructor promoted properties to set readonly properties on the instance.
	 *
	 * @param  string        $qualified_classname The string representation of a fully qualified feature class.
	 * @param  string        $slug A unique string slug used as key in the registry and as page id slugs in any admin screens. Should be passed through WordPress sanitize_title().
	 * @param  array<int, array<string, mixed>> $deps An array of named dependencies required by the feature.
	 *
	 * @return void
	 */
	public function __construct(
		public readonly string $qualified_classname,
		public readonly string $slug,
		public readonly array $deps = array()
	) {
	}


	/**
	 * Store the current instance of the registered feature if it has not already been set.
	 *
	 * @param  RegistrableFeatureInterface $instance The stored instance must be Registrable.
	 *
	 * @return RegistrableFeatureInterface|false Returns the instance on success, or false if already set.
	 */
	public function set_instance( RegistrableFeatureInterface $instance ): RegistrableFeatureInterface|false {
		if ( $this->instance ) {
			return false;
		}
		$this->instance = $instance;
		return $instance;
	}

	/**
	 * Get the instance associated with a slug identifier string
	 */
	public function get_instance(): RegistrableFeatureInterface|null {
		return $this->instance;
	}
}
