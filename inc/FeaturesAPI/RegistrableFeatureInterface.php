<?php
/**
 * An interface for a registrable feature.
 *
 * @package  RanPluginLib
 */

declare(strict_types=1);
namespace Ran\PluginLib\FeaturesAPI;

/**
 * Any feature or service that is registered through RegisterFeatures must implement a init() method.
 */
interface RegistrableFeatureInterface {

	/**
	 * Register methods add implementation details, including any WP actions and hooks required by the class.
	 * Normally called RegisterFeatures->instantiate() method.
	 *
	 * @return ?self
	 */
	public function init(): self|false;
}
