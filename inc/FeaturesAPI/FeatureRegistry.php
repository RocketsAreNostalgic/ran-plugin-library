<?php
/**
 * A feature cacheing class.
 *
 * @package  RanPluginLib
 */

declare(strict_types = 1);

namespace Ran\PluginLib\FeaturesAPI;

use Ran\PluginLib\FeaturesAPI\FeatureContainerInterface;

/**
 * A cache or registry of features, stored as an array of FeatureContainers.
 */
final class FeatureRegistry {
	/**
	 * Array of FeatureContainers.
	 *
	 * @var array<string, FeatureContainerInterface> Array of registered FeatureContainers indexed by slug
	 */
	private array $features = array();

	/**
	 * Sets a FeatureContainer to the private features array.
	 *
	 * @param  FeatureContainerInterface $feature  The object to store.
	 */
	public function set_feature( FeatureContainerInterface $feature ): void {
		$this->features[ $feature->slug ] = $feature;
	}

	/**
	 * Returns the FeatureContainer for the given slug ID or null.
	 *
	 * @param   string $slug_id  An identifier to store the object.
	 *
	 * @return  FeatureContainer|null The stored object, or null if that object is not in the cache.
	 */
	public function get_feature( string $slug_id ): FeatureContainerInterface|null {
		return $this->features[ $slug_id ] ?? null;
	}

	/**
	 * Returns an array of manager services with key and description.
	 *
	 * @return array<string, FeatureContainerInterface> Array of registered FeatureContainer objects indexed by slug.
	 */
	public function get_registery(): array {
		return $this->features;
	}
}
