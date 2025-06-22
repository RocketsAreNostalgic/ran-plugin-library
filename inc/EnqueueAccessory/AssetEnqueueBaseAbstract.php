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
 * @property array $scripts Array of script definitions.
 * @property array $styles Array of style definitions.
 * @property array $media_tool_configs Array of media tool configurations.
 * @property array $inline_scripts Array of inline script definitions.
 *
 * @method void enqueue_scripts() Enqueues registered scripts.
 * @method void enqueue_styles() Enqueues registered styles.
 * @method void enqueue_media() Enqueues media tools.
 * @method void enqueue_inline_scripts() Enqueues inline scripts.
 */
abstract class AssetEnqueueBaseAbstract {
	/**
	 * The ConfigInterface object holding plugin configuration.
	 *
	 * @var ConfigInterface
	 */
	protected ConfigInterface $config;

	/**
	 * Array of callbacks to execute in the head section.
	 *
	 * @var array<int, callable|array<string, mixed>>
	 */
	protected array $head_callbacks = array();

	/**
	 * Array of callbacks to execute in the footer section.
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
	 * Retrieves the logger instance for the plugin.
	 *
	 * @return Logger The logger instance.
	 */
	public function get_logger(): Logger {
		// @codeCoverageIgnoreStart
		return $this->config->get_logger();
		// @codeCoverageIgnoreEnd
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
	 * Orchestrates the enqueuing of all assets.
	 *
	 * This method checks for the existence of asset-specific processing methods
	 * (expected to be provided by traits) and calls them if available.
	 * It handles scripts, styles, media, and inline scripts.
	 * Inline styles are typically handled by a separate mechanism or hook.
	 */
	public function enqueue(): void {
		$logger            = $this->get_logger();
		$initial_log_parts = array();

		// Dynamically build log parts based on available properties from traits
		if (property_exists($this, 'scripts') && is_array($this->scripts)) {
			$initial_log_parts[] = 'Scripts: ' . count($this->scripts);
		}
		if (property_exists($this, 'styles') && is_array($this->styles)) {
			$initial_log_parts[] = 'Styles: ' . count($this->styles);
		}
		if (property_exists($this, 'media_tool_configs') && is_array($this->media_tool_configs)) {
			$initial_log_parts[] = 'Media: ' . count($this->media_tool_configs);
		}
		if (property_exists($this, 'inline_scripts') && is_array($this->inline_scripts)) {
			$initial_log_parts[] = 'Inline Scripts: ' . count($this->inline_scripts);
		}
		// Note: Original EnqueueAbstract::enqueue did not log count for inline_styles here.

		if ($logger->is_active()) {
			$logger->debug(
				sprintf(
					'AssetEnqueueBaseAbstract::enqueue - Main enqueue process started. %s.',
					implode(', ', $initial_log_parts)
				)
			);
		}

		// Process scripts if the method exists (from ScriptsEnqueueTrait)
		if (method_exists($this, 'enqueue_scripts')) {
			$this->enqueue_scripts($this->scripts ?? array()); // Trait method should handle its own $this->scripts
		} elseif ($logger->is_active() && property_exists($this, 'scripts') && !empty($this->scripts)) {
			$logger->debug('AssetEnqueueBaseAbstract::enqueue - Scripts data found, but no enqueue_scripts method (expected from trait).');
		} elseif ($logger->is_active() && (!property_exists($this, 'scripts') || empty($this->scripts))) {
			$logger->debug('AssetEnqueueBaseAbstract::enqueue - No scripts to process or script handling method not found.');
		}


		// Process styles if the method exists (from StylesEnqueueTrait)
		if (method_exists($this, 'enqueue_styles')) {
			$this->enqueue_styles($this->styles ?? array()); // Trait method should handle its own $this->styles
		} elseif ($logger->is_active() && property_exists($this, 'styles') && !empty($this->styles)) {
			$logger->debug('AssetEnqueueBaseAbstract::enqueue - Styles data found, but no enqueue_styles method (expected from trait).');
		} elseif ($logger->is_active() && (!property_exists($this, 'styles') || empty($this->styles))) {
			$logger->debug('AssetEnqueueBaseAbstract::enqueue - No styles to process or style handling method not found.');
		}

		// Process media if the method exists (from MediaEnqueueTrait)
		if (method_exists($this, 'enqueue_media')) {
			$this->enqueue_media($this->media_tool_configs ?? array()); // Trait method should handle its own $this->media_tool_configs
		} elseif ($logger->is_active() && property_exists($this, 'media_tool_configs') && !empty($this->media_tool_configs)) {
			$logger->debug('AssetEnqueueBaseAbstract::enqueue - Media data found, but no enqueue_media method (expected from trait).');
		} elseif ($logger->is_active() && (!property_exists($this, 'media_tool_configs') || empty($this->media_tool_configs))) {
			$logger->debug('AssetEnqueueBaseAbstract::enqueue - No media to process or media handling method not found.');
		}

		// Process inline scripts if the method exists (from ScriptsEnqueueTrait)
		// Original EnqueueAbstract::enqueue always called $this->enqueue_inline_scripts().
		if (method_exists($this, 'enqueue_inline_scripts')) {
			$this->enqueue_inline_scripts($this->inline_scripts ?? array()); // Trait method should handle its own $this->inline_scripts
		} elseif ($logger->is_active() && property_exists($this, 'inline_scripts') && !empty($this->inline_scripts)) {
			$logger->debug('AssetEnqueueBaseAbstract::enqueue - Inline scripts data found, but no enqueue_inline_scripts method (expected from trait).');
		}
		// enqueue_inline_scripts() itself should log if its $this->inline_scripts array is empty.

		// Note: enqueue_inline_styles() is NOT called here, consistent with original EnqueueAbstract.
		// It's expected to be handled by the StylesEnqueueTrait or a separate hook.

		$logger->debug('AssetEnqueueBaseAbstract::enqueue - Main enqueue process finished.');
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
				$logger->debug('AssetEnqueueBaseAbstract::render_head - No head callbacks to execute.');
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
					$logger->debug(sprintf('AssetEnqueueBaseAbstract::render_head - Skipping head callback %d due to false condition.', $index));
				}
				continue;
			}

			if (is_callable($callback)) {
				if ($logger->is_active()) {
					$logger->debug(sprintf('AssetEnqueueBaseAbstract::render_head - Executing head callback %d.', $index));
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
				$logger->debug('AssetEnqueueBaseAbstract::render_footer - No footer callbacks to execute.');
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
					$logger->debug(sprintf('AssetEnqueueBaseAbstract::render_footer - Skipping footer callback %d due to false condition.', $index));
				}
				continue;
			}

			if (is_callable($callback)) {
				if ($logger->is_active()) {
					$logger->debug(sprintf('AssetEnqueueBaseAbstract::render_footer - Executing footer callback %d.', $index));
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
}
