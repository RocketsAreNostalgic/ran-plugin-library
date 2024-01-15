<?php
/**
 * FeatureControllerAbstract class.
 *
 * @package  RanPlugin
 */

declare(strict_types=1);
namespace Ran\PluginLib\FeaturesAPI;

use Ran\PluginLib\Config\ConfigInterface;

/**
 * Feature controllers must implement a RegistrableFeatureInterface, with a register() method in order to be activated by the RegisterService class.
 *
 * @package  RanPluginLib
 */
abstract class FeatureControllerAbstract implements RegistrableFeatureInterface {

	/**
	 * Plugin object.
	 *
	 * @var object
	 */
	protected ConfigInterface $plugin;

	/**
	 * Array of plugin data returned from the Plugin object.
	 *
	 * @var array
	 */
	protected array $plugin_array = array();

	/**
	 * Array of feature 'managers' or admin settings screens.
	 *
	 * @var array
	 */
	protected array $feature_settings_pages = array();

	/**
	 * Constructor.
	 *
	 * @param  ConfigInterface $plugin Plugin instance that conforms to the interface.
	 */
	public function __construct( ConfigInterface $plugin ) {
		$this->plugin = $plugin;
		$this->plugin_array = $plugin->plugin_array;
	}
	/**
	 * Base initialization method for FeatureControllers.
	 * Init methods should return the current instance.
	 *
	 * @return FeatureControllerAbstract
	 */
	abstract public function init(): FeatureControllerAbstract;

	/**
	 * Returns the value of an active option, or false.
	 *
	 * This assumes that the feature is controllable by a configuration page which sets this option.
	 * If the feature is always on then this function can be overridden.
	 *
	 * @param  string      $key The name of the key to retrieve.
	 * @param  string|null $option_name The name of the option to retrieve.
	 *
	 * @return mixed If will return the value of the key array|string or else false
	 */
	public function is_activated( string $key, string $option_name = '' ): mixed {

		if ( $option_name ) {
			$option_name = $this->plugin_array['PluginOption'];
		}

		$option = get_option( $option_name );

		$option = get_option( $option_name );

		return isset( $option[ $key ] ) ? $option[ $key ] : false;
	}
}
