<?php
/**
 * An interface for a registrable feature.
 *
 * @package  RanPluginLib
 */

declare(strict_types = 1);

namespace Ran\PluginLib\FeaturesAPI;

/**
 * Any feature or service that is registered through FeaturesManager must implement an init() method.
 */
interface RegistrableFeatureInterface {
	/**
	 * Initializes the feature: add implementation details including any WordPress actions and filters required by the class.
	 * Called by the FeaturesManager during load() / load_all().
	 *
	 * @return self|false Returns the current instance on success or false on failure.
	 */
	public function init(): self|false;
}
