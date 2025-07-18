<?php
/**
 * Internal Asset Dispatcher Engine.
 *
 * This abstract class provides the core engine for processing assets. Its primary
 * architectural role is to act as a "dispatcher" that allows multiple asset-type-specific
 * traits (e.g., ScriptsEnqueueTrait, StylesEnqueueTrait) to be used together without
 * method name collisions.
 *
 * It is not intended for direct extension by top-level consumers. Instead, it serves
 * as the base for AssetHandlerBase, which is then extended by specific handlers
 * like ScriptsHandler.
 *
 * @package RanPluginLib\EnqueueAccessory
 * @internal
 */

declare(strict_types=1);

namespace Ran\PluginLib\EnqueueAccessory;

use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\EnqueueAccessory\AssetType;

/**
 * Provides the core dispatcher functionality for asset processing.
 *
 * This class contains the central dispatcher methods (e.g., `_process_single_asset`)
 * that use an `AssetType` enum to route calls to the correctly-named method
 * within a specific trait (e.g., `_process_single_asset` in `ScriptsEnqueueTrait`).
 *
 * This pattern avoids fatal errors from trait method name conflicts and provides
 * a clean, unified internal API for asset processing logic that is shared across
 * all asset handlers.
 *
 */
abstract class AssetEnqueueBaseAbstract {
	use AssetEnqueueBaseTrait;

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
	 * Basic implementation.
	 * Processes a single asset definition, fullly implimented by Styles/ScriptsEnqueueTrait
	 * MediaEnqueueTrait uses a different processing model.
	 *
	 * @param AssetType $asset_type The asset type.
	 * @param array $asset_definition The asset definition.
	 * @param string $processing_context The processing context.
	 * @param string|null $hook_name The hook name.
	 * @param bool $do_register Whether to register the asset.
	 * @param bool $do_enqueue Whether to enqueue the asset.
	 * @return string|false The asset handle if successful, false otherwise.
	 */
	protected function _process_single_asset(
		AssetType $asset_type,
		array $asset_definition,
		string $processing_context,
		?string $hook_name = null,
		bool $do_register = true,
		bool $do_enqueue = false
	): string|false {
		// Simple implementation for testing purposes
		$logger = $this->get_logger();
		$logger->debug('Processing media asset in test class');
		return $asset_definition['handle'] ?? false;
	}

	/**
	 * Registers assets, separating deferred assets and preparing immediate assets for enqueuing.
	 *
	 * This method processes asset definitions previously added via `add_assets($asset_type)`.
	 * It begins by taking a copy of all current assets and then clearing the main `$this->assets[$asset_type->value]` array.
	 *
	 * It then iterates through the copied asset definitions:
	 * - If an asset specifies a 'hook', it is considered deferred. The asset definition is moved
	 *   to the `$this->deferred_assets[$asset_type->value]` array, keyed by its hook name. An action is scheduled
	 *   with WordPress to call `_enqueue_deferred_assets()` for that hook. Deferred assets are
	 *   not re-added to the main `$this->assets[$asset_type->value]` array.
	 * - If an asset does not specify a 'hook', it is considered immediate. `_process_single_asset()`
	 *   is called for this asset with `do_register = true` and `do_enqueue = false` to handle
	 *   its registration with WordPress.
	 * - If an immediate asset is successfully registered, its definition is added back into the
	 *   (initially cleared) `$this->assets[$asset_type->value]` array, preserving its original index.
	 *
	 * After processing all assets, the `$this->assets[$asset_type->value]` array will contain only those immediate
	 * assets that were successfully registered. This array is then re-indexed using `array_values()`.
	 * The primary role of this method is to manage the initial registration phase and to ensure
	 * that `$this->assets[$asset_type->value]` is correctly populated with only immediate, registered assets, ready
	 * for the `stage_assets()` method to handle their final enqueuing.
	 *
	 * @return self Returns the instance for method chaining.
	 */
	public function stage_assets(AssetType $asset_type): self {
		$logger  = $this->get_logger();
		$context = __CLASS__ . '::' . __METHOD__ . ' (' . strtolower( $asset_type->value ) . 's)';

		// Ensure the asset type key exists to prevent notices on count().
		if ( ! isset( $this->assets[$asset_type->value] ) ) {
			$this->assets[$asset_type->value] = array();
		}

		if ( $logger->is_active() ) {
			$logger->debug( $context . ' - Entered. Processing ' . count( $this->assets[$asset_type->value] ) . ' ' . $asset_type->value . ' definition(s) for registration.' );
		}

		$assets_to_process                = $this->assets[$asset_type->value];
		$immediate_assets                 = array(); // Initialize for collecting immediate assets.
		$this->assets[$asset_type->value] = array(); // Clear original to re-populate with non-deferred assets that are successfully processed.

		foreach ( $assets_to_process as $index => $asset_definition ) {
			$hook_name = $asset_definition['hook'] ?? null;

			if ( $logger->is_active() ) {
				$logger->debug( "{$context} - Processing {$asset_type->value}: \"{$asset_definition['handle']}\", original index: {$index}." );
			}

			if ( ! empty( $hook_name ) ) {
				$priority = $asset_definition['priority'] ?? 10;
				if ( ! is_int( $priority ) ) {
					$priority = 10;
				}

				if ( $logger->is_active() ) {
					$logger->debug( "{$context} - Deferring registration of {$asset_type->value} '{$asset_definition['handle']}' to hook '{$hook_name}' with priority {$priority}." );
				}

				// Group asset by hook and priority.
				$this->deferred_assets[ $asset_type->value ][ $hook_name ][ $priority ][] = $asset_definition;

				// Register the action for this specific hook and priority if not already done.
				if ( ! isset( $this->registered_hooks[ $asset_type->value ][ $hook_name . '_' . $priority ] ) ) {
					$callback = function () use ( $hook_name, $priority, $context ) {
						if ( method_exists( $this, $context ) ) {
							$this->{$context}( $hook_name, $priority );
						}
					};

					$this->_do_add_action( $hook_name, $callback, $priority, 0 );
					$this->registered_hooks[ $asset_type->value ][ $hook_name . '_' . $priority ] = true;

					if ( $logger->is_active() ) {
						$logger->debug( "{$context} - Added action for hook '{$hook_name}' with priority {$priority}." );
					}
				}

				continue; // Skip immediate processing for this deferred asset.
			} else {
				// Process immediately for registration.
				// _process_single_asset is implimented by Styles/ScriptsEnqueueTrait
				$processed_successfully = $this->_process_single_asset(
					$asset_type,
					$asset_definition,
					$context, // processing_context
					null,               // hook_name (null for immediate registration)
					true,               // do_register
					false              // do_enqueue (registration only)
				);
				if ( $processed_successfully ) {
					$immediate_assets[] = $asset_definition;
				}
			}
		}

		// Replace the original assets array with only the successfully processed immediate assets.
		$this->assets[$asset_type->value] = $immediate_assets;

		if ( $logger->is_active() ) {
			$deferred_count = 0;
			foreach ( ( $this->deferred_assets[ $asset_type->value ] ?? array() ) as $hook_assets ) {
				foreach ( $hook_assets as $priority_assets ) {
					$deferred_count += count( $priority_assets );
				}
			}
			$logger->debug( "{$context} - Exited. Remaining immediate {$asset_type->value}s: " . count( $this->assets[$asset_type->value] ) . ". Total deferred {$asset_type->value}s: " . $deferred_count . '.' );
		}
		return $this;
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
}
