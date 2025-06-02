<?php
/**
 * Add links to the active plugin entry in the WordPress admin plugins page.
 *
 * @package  RanPlugin
 */

declare(strict_types = 1);

namespace Ran\PluginLib;

use Ran\PluginLib\FeaturesAPI\FeatureControllerAbstract;
use Ran\PluginLib\FeaturesAPI\RegistrableFeatureInterface;

/**
 * Modify the action and meta arrays for the plugin's entry in the admin plugins page.
 */
abstract class PluginAdditionalLinksAbstract extends FeatureControllerAbstract implements RegistrableFeatureInterface {
	/**
	 * Array of plugin_action_links.
	 *
	 * @var array<mixed>
	 */
	public array $action_links = array();

	/**
	 * Array of plugin_row_meta.
	 *
	 * @var array<mixed>
	 */
	public array $plugin_row_meta = array();

	/**
	 * Our init hook to add_filter hooks.
	 */
	public function init(): PluginAdditionalLinksAbstract {
		add_filter( 'plugin_action_links_' . $this->plugin_array['Basename'], array( $this, 'plugin_action_links_callback' ) );
		add_filter( 'plugin_row_meta', array( $this, 'plugin_meta_links_callback' ), 10, 4 );
		// Silence is golden.
		defined( 'ABSPATH' ) || die( '' );
		return $this;
	}

	/**
	 * Modifies the plugin action link array.
	 * The WordPress add_filter callback for the plugin_action_links hook, modifying the add action links array.
	 * https://developer.wordpress.org/reference/hooks/plugin_action_links/
	 *
	 * @param  array<string, string> $links Array of plugin_action_links.
	 * @return array<string, string> Modified array of plugin action links.
	 */
	public function plugin_action_links_callback( array $links ): array {
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
	 * @param  array<int, string>   $plugin_meta The array of plugin meta information.
	 * @param  string               $plugin_file The current plugin file.
	 * @param  array<string, mixed> $plugin_data Data associated with the plugin.
	 * @param  string               $status The current status of the plugin ie 'active', 'inactive' and more.
	 *
	 * @return array<int, string> The modified plugin meta array.
	 */
	public function plugin_meta_links_callback(
		array $plugin_meta,
		string $plugin_file,
		array $plugin_data,
		string $status
	): array {
		if ( stripos( $plugin_file, $this->plugin_array['Basename'] ) === false ) {
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
