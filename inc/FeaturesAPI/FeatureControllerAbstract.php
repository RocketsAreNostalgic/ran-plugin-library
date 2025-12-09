<?php
/**
 * FeatureControllerAbstract class file.
 *
 * This file contains the abstract class that serves as a base for feature controllers.
 *
 * @package  Ran\PluginLib\FeaturesAPI
 */

declare(strict_types = 1);

namespace Ran\PluginLib\FeaturesAPI;

use Ran\PluginLib\Config\ConfigInterface;

/**
 * Feature controllers must implement RegistrableFeatureInterface and provide an init() method.
 * Instances are created and initialized by the FeaturesManager.
 *
 * @since 0.1.0
 */
abstract class FeatureControllerAbstract implements RegistrableFeatureInterface {
	/**
	 * Config object.
	 *
	 * @var ConfigInterface
	 */
	protected ConfigInterface $config;

	/**
	 * Array of config data returned from the Config object.
	 *
	 * @var array<string, mixed>
	 */
	protected array $config_array = array();

	/**
	 * Array of feature 'managers' or admin settings screens.
	 *
	 * @var array<string, mixed>
	 */
	protected array $feature_settings_pages = array();

	/**
	 * Constructor.
	 *
	 * @param  ConfigInterface $config Config instance that conforms to the interface.
	 */
	public function __construct( ConfigInterface $config ) {
		$this->config       = $config;
		$this->config_array = $config->get_config();
	}
	/**
	 * Base initialization method for FeatureControllers.
	 * Init methods should return the current instance.
	 *
	 * @since 0.1.0
	 * @return FeatureControllerAbstract|false Returns the current instance or false on failure.
	 */
	abstract public function init(): FeatureControllerAbstract|false;

	/**
	 * Returns the value of an active option, or false.
	 *
	 * This assumes that the feature is controllable by a configuration page which sets this option.
	 * If the feature is always on then this function can be overridden.
	 *
	 * @since 0.1.0
	 * @param  string $key The name of the key to retrieve.
	 * @param  string $option_name The name of the option to retrieve.
	 * @return mixed Returns the value of the key (array|string) or false if not found.
	 */
	public function is_activated( string $key, string $option_name = '' ): mixed {
		if ( empty( $option_name ) ) {
			// Derive option key from normalized config (RAN.AppOption preferred, else Slug)
			$ran         = (array) ( $this->config_array['RAN'] ?? array() );
			$option_name = (string) ( ! empty( $ran['AppOption'] ) ? $ran['AppOption'] : ( $this->config_array['Slug'] ?? '' ) );
		}

		$option = get_option( $option_name );

		return isset( $option[ $key ] ) ? $option[ $key ] : false;
	}
}
