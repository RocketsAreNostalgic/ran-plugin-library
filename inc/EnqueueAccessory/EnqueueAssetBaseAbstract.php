<?php
/**
 * Asset Enqueue Base Abstract Class.
 *
 * Provides core, asset-agnostic functionality for enqueueing assets in WordPress.
 * Designed to be extended by concrete enqueue handlers, which will use traits
 * for specific asset-type (scripts, styles, media) management.
 *
 * @package RanPluginLib\EnqueueAccessory
 */

declare(strict_types=1);

namespace Ran\PluginLib\EnqueueAccessory;

use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\Util\Logger;

/**
 * Abstract base class for managing the enqueuing of assets.
 *
 * This class provides core, asset-agnostic functionality. Concrete classes
 * (e.g., EnqueueAdmin, EnqueuePublic) will extend this and use specific traits
 * (ScriptsEnqueueTrait, StylesEnqueueTrait, MediaEnqueueTrait) to handle
 * different asset types.
 *
 * @method array get_assets() Retrieves the registered assets.
 * @method array get_styles() Retrieves the registered styles.
 * @method array get_media_tool_configs() Retrieves the media tool configurations.
 * @method void enqueue_assets() Enqueues registered assets.
 * @method void enqueue_styles() Enqueues registered styles.
 * @method void enqueue_media() Enqueues media tools.
 * @method array get_inline_assets() Retrieves the registered inline assets.
 * @method void enqueue_inline_assets() Enqueues inline assets.
 */
abstract class EnqueueAssetBaseAbstract {
	use ScriptsEnqueueTrait, StylesEnqueueTrait {
		// Resolve conflicts for all methods from the base trait.
		// We'll use ScriptsEnqueueTrait as the default and then alias both versions.
		ScriptsEnqueueTrait::get_logger insteadof StylesEnqueueTrait;
		ScriptsEnqueueTrait::get_assets insteadof StylesEnqueueTrait;
		ScriptsEnqueueTrait::get_inline_assets insteadof StylesEnqueueTrait;
		ScriptsEnqueueTrait::get_deferred_assets insteadof StylesEnqueueTrait;
		ScriptsEnqueueTrait::get_deferred_hooks insteadof StylesEnqueueTrait;
		ScriptsEnqueueTrait::add_assets insteadof StylesEnqueueTrait;
		ScriptsEnqueueTrait::register_assets insteadof StylesEnqueueTrait;
		ScriptsEnqueueTrait::enqueue_assets insteadof StylesEnqueueTrait;
		ScriptsEnqueueTrait::enqueue_deferred_assets insteadof StylesEnqueueTrait;
		ScriptsEnqueueTrait::add_inline_assets insteadof StylesEnqueueTrait;
		ScriptsEnqueueTrait::enqueue_inline_assets insteadof StylesEnqueueTrait;
		ScriptsEnqueueTrait::_process_inline_assets insteadof StylesEnqueueTrait;

		ScriptsEnqueueTrait::_modify_html_tag_attributes insteadof StylesEnqueueTrait;

		// --- Alias all ScriptsEnqueueTrait methods to be protected ---
		ScriptsEnqueueTrait::get_assets as protected trait_get_assets_scripts;
		ScriptsEnqueueTrait::get_inline_assets as protected trait_get_inline_assets_scripts;
		ScriptsEnqueueTrait::get_deferred_assets as protected trait_get_deferred_assets_scripts;
		ScriptsEnqueueTrait::get_deferred_hooks as protected trait_get_deferred_hooks_scripts;
		ScriptsEnqueueTrait::add_assets as protected trait_add_assets_scripts;
		ScriptsEnqueueTrait::register_assets as protected trait_register_assets_scripts;
		ScriptsEnqueueTrait::enqueue_assets as protected trait_enqueue_assets_scripts;
		ScriptsEnqueueTrait::enqueue_deferred_assets as protected trait_enqueue_deferred_assets_scripts;
		ScriptsEnqueueTrait::add_inline_assets as protected trait_add_inline_assets_scripts;
		ScriptsEnqueueTrait::enqueue_inline_assets as protected trait_enqueue_inline_assets_scripts;
		ScriptsEnqueueTrait::_process_inline_assets as protected trait_process_inline_scripts;
		ScriptsEnqueueTrait::_process_single_asset as protected trait_process_single_script;
		ScriptsEnqueueTrait::_modify_html_tag_attributes as protected trait_modify_script_tag_attributes;

		// --- Alias all StylesEnqueueTrait methods to be protected ---
		StylesEnqueueTrait::get_assets as protected trait_get_assets_styles;
		StylesEnqueueTrait::get_inline_assets as protected trait_get_inline_assets_styles;
		StylesEnqueueTrait::get_deferred_assets as protected trait_get_deferred_assets_styles;
		StylesEnqueueTrait::get_deferred_hooks as protected trait_get_deferred_hooks_styles;
		StylesEnqueueTrait::add_assets as protected trait_add_assets_styles;
		StylesEnqueueTrait::register_assets as protected trait_register_assets_styles;
		StylesEnqueueTrait::enqueue_assets as protected trait_enqueue_assets_styles;
		StylesEnqueueTrait::enqueue_deferred_assets as protected trait_enqueue_deferred_assets_styles;
		StylesEnqueueTrait::add_inline_assets as protected trait_add_inline_assets_styles;
		StylesEnqueueTrait::enqueue_inline_assets as protected trait_enqueue_inline_assets_styles;
		StylesEnqueueTrait::_process_inline_assets as protected trait_process_inline_styles;
		StylesEnqueueTrait::_process_single_asset as protected trait_process_single_style;
		StylesEnqueueTrait::_modify_html_tag_attributes as protected trait_modify_style_tag_attributes;
	}

	use MediaEnqueueTrait;

	/**
	 * The ConfigInterface object holding plugin configuration.
	 *
	 * @var ConfigInterface
	 */
	protected ConfigInterface $config;

	/**
	 * Array of media tool configurations.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	protected array $media_tool_configs = array();

	/**
	 * Array of callbacks to be executed in the HTML head.
	 *
	 * @var array<int, callable|array<string, mixed>>
	 */
	protected array $head_callbacks = array();

	/**
	 * Array of callbacks to be executed in the HTML footer.
	 *
	 * @var array<int, callable|array<string, mixed>>
	 */
	protected array $footer_callbacks = array();

	/**
	 * Constructor.
	 *
	 * @param ConfigInterface $config The configuration object.
	 */
	public function __construct(ConfigInterface $config) {
		$this->config = $config;
	}

	/**
	 * Abstract method for hooking into WordPress to enqueue assets.
	 *
	 * Concrete implementations of this method should use WordPress action hooks
	 * (e.g., `admin_enqueue_scripts`, `wp_enqueue_scripts`, `login_enqueue_scripts`)
	 * to call a method (often `enqueue()` from this class, or a custom one)
	 * that will process and enqueue the registered scripts and styles.
	 *
	 * This method is typically called during the plugin's initialization sequence.
	 */
	abstract public function load(): void;

	/**
	 * Retrieves the logger instance for the plugin.
	 * This method provides a concrete implementation that overrides the abstract
	 * @return Logger The logger instance.
	 */
	public function get_logger(): Logger {
		return $this->config->get_logger();
	}

	/**
	 * Retrieves the registered head callbacks.
	 *
	 * @return array<int, callable|array<string, mixed>>
	 * @see ARD/ADR-001.md For the rationale behind this preemptive check.
	 */
	public function get_head_callbacks(string $_asset_type): array {
		if (!empty($this->head_callbacks)) {
			return $this->head_callbacks;
		}

		foreach ($this->assets as $asset) {
			if (empty($asset['data'])) {
				continue;
			}
			if ('script' === $_asset_type && !empty($asset['in_footer'])) {
				continue;
			}
			return array(true); // Return a non-empty array to signify a callback is needed.
		}

		return array();
	}

	/**
	 * Retrieves the registered footer callbacks.
	 *
	 * @return array<int, callable|array<string, mixed>>
	 * @see ARD/ADR-001.md For the rationale behind this preemptive check.
	 */
	public function get_footer_callbacks(string $_asset_type): array {
		if (!empty($this->footer_callbacks)) {
			return $this->footer_callbacks;
		}

		if ('script' !== $_asset_type) {
			return array();
		}

		foreach ($this->assets as $asset) {
			if (!empty($asset['data']) && !empty($asset['in_footer'])) {
				return array(true);
			}
		}

		return array();
	}

	/**
	 * Orchestrates the enqueuing of all assets.
	 *
	 * This method checks for the existence of asset-specific processing methods
	 * (expected to be provided by traits) and calls them if available.
	 * It handles assets, styles, media, and inline assets.
	 * Inline styles are typically handled by a separate mechanism or hook.
	 */
	public function enqueue(): void {
		$logger = $this->get_logger();

		// Safely determine counts for logging by using public getters, not direct property access.
		$assets_count        = method_exists($this, 'get_assets') ? count($this->get_assets()['general'] ?? array()) : 0;
		$styles_count        = method_exists($this, 'get_styles') ? count($this->get_styles()['general'] ?? array()) : 0;
		$media_count         = method_exists($this, 'get_media_tool_configs') ? count($this->get_media_tool_configs()) : 0;
		$inline_assets_count = method_exists($this, 'get_inline_assets') ? count($this->get_inline_assets()) : 0;

		$logger->debug(
			sprintf(
				'EnqueueAssetBaseAbstract::enqueue - Main enqueue process started. Assets: %d, Styles: %d, Media: %d, Inline Assets: %d.',
				$assets_count,
				$styles_count,
				$media_count,
				$inline_assets_count
			)
		);

		// Process assets if the method exists (from ScriptsEnqueueTrait).
		if ( method_exists( $this, 'enqueue_assets' ) ) {
			$this->enqueue_assets();
		}

		// Process styles if the method exists (from StylesEnqueueTrait).
		if ( method_exists( $this, 'enqueue_styles' ) ) {
			$this->enqueue_styles();
		}

		// Process media if the method exists (from MediaEnqueueTrait).
		if ( method_exists( $this, 'enqueue_media' ) ) {
			$this->enqueue_media( $this->media_tool_configs ?? array() );
		}

		// Process inline assets if the method exists (from ScriptsEnqueueTrait).
		if ( method_exists( $this, 'enqueue_inline_assets' ) ) {
			$this->enqueue_inline_assets();
		}

		$logger->debug( 'EnqueueAssetBaseAbstract::enqueue - Main enqueue process finished.' );
	}

	/**
	 * Executes callbacks registered to output content in the HTML <head> section.
	 *
	 * This is typically hooked to `wp_head`.
	 */
	public function render_head(): void {
		$logger = $this->get_logger();
		if (empty($this->head_callbacks)) {
			if ($logger->is_active()) {
				$logger->debug('EnqueueAssetBaseAbstract::render_head - No head callbacks to execute.');
			}
			return;
		}

		foreach ($this->head_callbacks as $index => $callback_data) {
			$callback  = $callback_data;
			$condition = null;

			if (is_array($callback_data) && isset($callback_data['callback'])) {
				$callback  = $callback_data['callback'];
				$condition = $callback_data['condition'] ?? null;
			}

			if (is_callable($condition) && !$condition()) {
				if ($logger->is_active()) {
					$logger->debug(sprintf('EnqueueAssetBaseAbstract::render_head - Skipping head callback %d due to false condition.', $index));
				}
				continue;
			}

			if (is_callable($callback)) {
				if ($logger->is_active()) {
					$logger->debug(sprintf('EnqueueAssetBaseAbstract::render_head - Executing head callback %d.', $index));
				}
				call_user_func($callback);
			}
		}
	}

	/**
	 * Executes callbacks registered to output content before the closing </body> tag.
	 *
	 * This is typically hooked to `wp_footer`.
	 */
	public function render_footer(): void {
		$logger = $this->get_logger();
		if (empty($this->footer_callbacks)) {
			if ($logger->is_active()) {
				$logger->debug('EnqueueAssetBaseAbstract::render_footer - No footer callbacks to execute.');
			}
			return;
		}

		foreach ($this->footer_callbacks as $index => $callback_data) {
			$callback  = $callback_data;
			$condition = null;

			if (is_array($callback_data) && isset($callback_data['callback'])) {
				$callback  = $callback_data['callback'];
				$condition = $callback_data['condition'] ?? null;
			}

			if (is_callable($condition) && !$condition()) {
				if ($logger->is_active()) {
					$logger->debug(sprintf('EnqueueAssetBaseAbstract::render_footer - Skipping footer callback %d due to false condition.', $index));
				}
				continue;
			}

			if (is_callable($callback)) {
				if ($logger->is_active()) {
					$logger->debug(sprintf('EnqueueAssetBaseAbstract::render_footer - Executing footer callback %d.', $index));
				}
				call_user_func($callback);
			}
		}
	}

	/**
	 * Wrapper for the global add_filter function to allow for easier mocking in tests.
	 *
	 * @param string   $hook          The name of the filter to hook the $callback to.
	 * @param callable $callback      The callback to be run when the filter is applied.
	 * @param int      $priority      Used to specify the order in which the functions
	 *                                associated with a particular action are executed.
	 * @param int      $accepted_args The number of arguments the function accepts.
	 * @return void
	 */
	protected function _do_add_filter(string $hook, callable $callback, int $priority, int $accepted_args): void {
		add_filter($hook, $callback, $priority, $accepted_args);
	}

	/**
	 * Wraps the global add_action function to allow for easier mocking in tests.
	 *
	 * @param string   $hook          The name of the action to which the $function_to_add is hooked.
	 * @param callable $callback      The name of the function you wish to be called.
	 * @param int      $priority      Optional. Used to specify the order in which the functions
	 *                                associated with a particular action are executed. Default 10.
	 * @param int      $accepted_args Optional. The number of arguments the function accepts. Default 1.
	 * @return void
	 */
	protected function _do_add_action( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
		add_action( $hook, $callback, $priority, $accepted_args );
	}


	/**
	 * Dispatches the processing of a single asset to the appropriate trait.
	 *
	 * This method acts as a router, determining whether the asset is a script or a style
	 * based on the provided `$asset_type` and then calling the corresponding aliased
	 * method from either `ScriptsEnqueueTrait` or `StylesEnqueueTrait`.
	 *
	 * @param string               $asset_type The type of asset ('script' or 'style').
	 * @param array<string, mixed> $asset_definition The definition of the asset to process.
	 * @param string               $processing_context The context in which the asset is being processed.
	 * @param string|null          $hook_name The name of the hook if the asset is deferred.
	 * @param bool                 $do_register Whether to register the asset.
	 * @param bool                 $do_enqueue Whether to enqueue the asset.
	 * @return string|false The handle of the processed asset, or false on failure.
	 */
	protected function _process_single_asset(
		string $asset_type,
		array $asset_definition,
		string $processing_context,
		?string $hook_name = null,
		bool $do_register = true,
		bool $do_enqueue = false
	): string|false {
		if ( 'script' === $asset_type ) {
			return $this->trait_process_single_script( $asset_type, $asset_definition, $processing_context, $hook_name, $do_register, $do_enqueue );
		}

		if ( 'style' === $asset_type ) {
			return $this->trait_process_single_style( $asset_type, $asset_definition, $processing_context, $hook_name, $do_register, $do_enqueue );
		}

		$logger = $this->get_logger();
		if ( $logger->is_active() ) {
			$handle_for_log = $asset_definition['handle'] ?? 'N/A';
			$logger->error( "EnqueueAssetBaseAbstract::_process_single_asset - Unknown asset type '{$asset_type}' for handle '{$handle_for_log}'. Cannot process." );
		}

		return false;
	}

	/**
	 * Dispatches the processing of inline assets to the appropriate trait.
	 *
	 * @param string      $_asset_type        The type of asset ('script' or 'style').
	 * @param string      $parent_handle      The handle of the parent asset.
	 * @param string|null $hook_name          The hook context, if any.
	 * @param string      $processing_context A string indicating the calling context for logging.
	 * @return void
	 */
	protected function _process_inline_assets(
		string $_asset_type,
		string $parent_handle,
		?string $hook_name,
		string $processing_context
	): void {
		if ( 'script' === $_asset_type ) {
			$this->trait_process_inline_scripts( $_asset_type, $parent_handle, $hook_name, $processing_context );
			return;
		}

		if ( 'style' === $_asset_type ) {
			$this->trait_process_inline_styles( $_asset_type, $parent_handle, $hook_name, $processing_context );
			return;
		}

		$logger = $this->get_logger();
		if ( $logger->is_active() ) {
			$logger->error( "EnqueueAssetBaseAbstract::_process_inline_assets - Unknown asset type '{$_asset_type}' for parent handle '{$parent_handle}'. Cannot process." );
		}
	}

	/**
	 * Dispatches the modification of an HTML asset tag to the appropriate trait.
	 *
	 * @param string $_asset_type                 The type of asset ('script' or 'style').
	 * @param string $tag                         The original HTML tag.
	 * @param string $tag_handle                  The handle of the tag being processed.
	 * @param string $handle_to_match             The handle to match against.
	 * @param array<string, string|true> $attributes_for_tag_modifier An array of attributes to add/modify.
	 * @return string The modified (or original) HTML asset tag.
	 */
	protected function _modify_html_tag_attributes(
		string $_asset_type,
		string $tag,
		string $tag_handle,
		string $handle_to_match,
		array $attributes_for_tag_modifier
	): string {
		if ( 'script' === $_asset_type ) {
			return $this->trait_modify_script_tag_attributes( $_asset_type, $tag, $tag_handle, $handle_to_match, $attributes_for_tag_modifier );
		}

		if ( 'style' === $_asset_type ) {
			return $this->trait_modify_style_tag_attributes( $_asset_type, $tag, $tag_handle, $handle_to_match, $attributes_for_tag_modifier );
		}

		$logger = $this->get_logger();
		if ( $logger->is_active() ) {
			$logger->error( "EnqueueAssetBaseAbstract::_modify_html_tag_attributes - Unknown asset type '{$_asset_type}' for handle '{$tag_handle}'. Cannot modify tag." );
		}

		return $tag;
	}
}
