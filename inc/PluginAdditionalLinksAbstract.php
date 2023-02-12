<?php
/**
 * Add links to the active plugin entry in the WordPress admin plugins page.
 *
 * @package  RanPlugin
 */

namespace Ran\PluginLib;

use Ran\PluginLib\FeaturesAPI\FeatureControllerAbstract;
use Ran\PluginLib\FeaturesAPI\RegistrableFeatureInterface;
use Ran\PluginLib\Plugin\PluginInterface;

/**
 * Modify the action and meta arrays for the plugin's entry in the admin plugins page.
 */
abstract class  PluginAdditionalLinksAbstract extends FeatureControllerAbstract implements RegistrableFeatureInterface {

	/**
	 * Our init hook to add_filter hooks.
	 *
	 * @return PluginAdditionalLinksAbstract
	 */
	public function init(): PluginAdditionalLinksAbstract {
		add_filter( 'plugin_action_links_' . $this->plugin_data['FileName'], array( $this, 'plugin_action_links_callback' ) );
		add_filter( 'plugin_row_meta', array( $this, 'plugin_meta_links_callback' ), 10, 4 );

		return $this;
	}


	/**
	 * Modifies the plugin action link array.
	 * The WordPress add_filter callback for the plugin_action_links hook, modifying the add action links array.
	 * https://developer.wordpress.org/reference/hooks/plugin_action_links/
	 *
	 * @param  array $links Array of plugin_action_links.
	 *
	 * @return array
	 */
	public function plugin_action_links_callback( array $links ):array {

		/**
		 * We can modify the links array here, but must return the array.
		 *
		 * * $links[] = '<a href="admin.php?page=' . $this->plugin_data['TextDomain'] . '">Settings</a>';
		 */

		// You must return the links array.
		return $links;
	}

	/**
	 * Modifies plugin meta arrays.
	 * The WordPress add_filter callback for the plugin_row_meta hook, modifying the plugin meta array.
	 * This filter will be run agains all loaded plugins, so you have to implement your own checks that you are manipulating the correct plugin meta.
	 * https://developer.wordpress.org/reference/hooks/plugin_row_meta/
	 *
	 * @param  array  $plugin_meta The array of plugin meta information.
	 * @param  string $plugin_file The current plugin file.
	 * @param  array  $plugin_data Data associated with the plugin.
	 * @param  string $status The current status of the plugin ie 'active', 'inactive' and more.
	 *
	 * @return array The modified plugin meta array.
	 */
	public function plugin_meta_links_callback( array $plugin_meta, string $plugin_file, array $plugin_data, string $status ):array {

		if ( stripos( $plugin_file, $this->plugin_data['FileName'] ) === false ) {
			return $plugin_meta;
		}

		/**
		 *  Modify the plugin meta array with the links you want, for example:
		 * * $plugin_meta[] = '<a href="#">Love Tacos!</a>';
		 */

		// You must return the meta array.
		return $plugin_meta;
	}
}
