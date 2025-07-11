<?php
/**
 * Base trait for enqueuing assets
 *
 * @todo FEATURE - cachebusting beyond version numbers
 * @todo FEATURE - dev vs prod for loading minififed files
 * @todo CLEARIFY - How is load order handeled in current context?
 * @todo EXPLORE MULTI-SITE compatibility
 * @todo EXPLORE WP_NETWORK
 * @todo EXPLORE Script localization (wp_localize_script)
 * @todo EXPLORE handeling of known core assets such as jQuery
 *
 * @package Ran\PluginLib\EnqueueAccessory
 * @author  Ran Plugin Lib
 * @license GPL-2.0+ <http://www.gnu.org/licenses/gpl-2.0.txt>
 * @link    https://github.com/RocketsAreNostalgic
 * @since   0.1.0
 */

declare(strict_types=1);

namespace Ran\PluginLib\EnqueueAccessory;

use Ran\PluginLib\EnqueueAccessory\AssetType;
use Ran\PluginLib\Util\Logger;

/**
 * Trait EnqueueAssetTraitBase
 *
 * Manages the registration, enqueuing, and processing of assets.
 * This includes handling general assets, inline assets, and deferred assets.
 *
 * @package Ran\PluginLib\EnqueueAccessory
 */
trait EnqueueAssetTraitBase {
	/**
	 * Holds all asset definitions.
	 *
	 * @var array<string, array<int, array<string, mixed>>>
	 */
	protected array $assets = array(
		'script' => array(),
		'style'  => array(),
	);

	/**
	 * Array of assets to be loaded at specific WordPress action hooks.
	 *
	 * @var array<string, array<string, array<int, array<string, mixed>>>>
	 */
	protected array $deferred_assets = array(
		'script' => array(),
		'style'  => array(),
	);

	/**
	 * Array of inline assets for external handles, keyed by hook.
	 *
	 * @var array<string, array<string, array<int, array<string, mixed>>>>
	 */
	protected array $external_inline_assets = array(
		'script' => array(),
		'style'  => array(),
	);

	/**
	 * Tracks which hooks have had an action registered for external inline assets.
	 *
	 * @var array<string, array<string, bool>>
	 */
	protected array $registered_external_hooks = array(
		'script' => array(),
		'style'  => array(),
	);

	/**
	 * Abstract method to get the logger instance.
	 *
	 * This ensures that any class using this trait provides a logger.
	 *
	 * @return Logger The logger instance.
	 */
	abstract public function get_logger(): Logger;

	/**
	 * Retrieves the currently registered array of asset definitions.
	 *
	 * @return array<string, array> An associative array of asset definitions, keyed by 'general', 'deferred', and 'external_inline'.
	 */
	public function get_assets(AssetType $asset_type): array {
		return array(
			'general'         => $this->assets[$asset_type->value]                 ?? array(),
			'deferred'        => $this->deferred_assets[$asset_type->value]        ?? array(),
			'external_inline' => $this->external_inline_assets[$asset_type->value] ?? array(),
		);
	}

	/**
	 * Retrieves the unique hook names for all registered deferred assets.
	 *
	 * This method performs a "look-ahead" by inspecting both unprocessed (`$this->assets`)
	 * and already-processed (`$this->deferred_assets`) asset arrays. This is crucial for
	 * the public-facing enqueue process (`EnqueuePublic::load()`) to preemptively register
	 * all necessary WordPress actions for custom hooks.
	 *
	 * In the WordPress lifecycle, actions must be registered before the logic that determines
	 * their necessity has fully run. This method solves that timing issue by providing a
	 * complete list of hooks upfront. This is not required in the admin context, as the
	 * `admin_enqueue_scripts` hook provides sufficient context.
	 *
	 * @return string[] An array of unique hook names.
	 * @see ARD/ADR-001.md For the rationale behind this preemptive check.
	 */
	public function get_deferred_hooks(AssetType $asset_type): array {
		$hooks = array();

		// Check for hooks in the main assets array for the given type.
		foreach ( ($this->assets[$asset_type->value]['general'] ?? array()) as $asset ) {
			if ( ! empty( $asset['hook'] ) ) {
				$hooks[] = $asset['hook'];
			}
		}

		// Merge with hooks from already-processed deferred assets for the given type.
		$deferred_hooks = array_keys( $this->deferred_assets[$asset_type->value] ?? array() );

		return array_unique( array_merge( $hooks, $deferred_hooks ) );
	}

	/**
	 * Provides the core logic for adding one or more asset definitions to the internal queue.
	 *
	 * This method is intended to be called by a public-facing wrapper method (e.g., `add_scripts`, `add_styles`)
	 * which provides the necessary context for logging.
	 *
	 * @param array<string, mixed>|array<int, array<string, mixed>> $assets_to_add A single asset definition array or an array of asset definition arrays.
	 * @return self Returns the instance of this class for method chaining.
	 */
	public function add_assets(array $assets_to_add, AssetType $asset_type): self {
		$logger  = $this->get_logger();
		$context = __TRAIT__ . '::add_' . $asset_type->value . 's';

		// Ensure the asset type key exists to prevent notices on count().
		if ( ! isset( $this->assets[$asset_type->value] ) ) {
			$this->assets[$asset_type->value] = array();
		}

		if ( empty( $assets_to_add ) ) {
			if ( $logger->is_active() ) {
				$logger->debug( "{$context} - Entered with empty array. No {$asset_type->value}s to add." );
			}
			return $this;
		}

		// Normalize single asset definition into an array of definitions.
		if ( isset( $assets_to_add['handle'] ) && is_string( $assets_to_add['handle'] ) ) {
			$assets_to_add = array( $assets_to_add );
		}

		// Validate all assets before adding them to the queue.
		foreach ($assets_to_add as $key => $asset) {
			$handle = $asset['handle'] ?? null;
			$src    = $asset['src']    ?? null;

			if (empty($handle)) {
				throw new \InvalidArgumentException("Invalid {$asset_type->value} definition at index {$key}. Asset must have a 'handle'.");
			}

			if ($src !== false && empty($src)) {
				throw new \InvalidArgumentException("Invalid {$asset_type->value} definition for handle '{$handle}'. Asset must have a 'src' or 'src' must be explicitly set to false.");
			}
		}

		if ( $logger->is_active() ) {
			$logger->debug( "{$context} - Entered. Current {$asset_type->value} count: " . count( $this->assets[$asset_type->value] ) . '. Adding ' . count( $assets_to_add ) . " new {$asset_type->value}(s)." );
			foreach ( $assets_to_add as $asset_key => $asset_data ) {
				$handle = $asset_data['handle'] ?? 'N/A';
				$src    = $asset_data['src']    ?? 'N/A';
				$logger->debug( "{$context} - Adding {$asset_type->value}. Key: {$asset_key}, Handle: {$handle}, src: {$src}" );
			}
			$logger->debug( "{$context} - Adding " . count( $assets_to_add ) . " {$asset_type->value} definition(s). Current total: " . count( $this->assets[$asset_type->value] ) );
		}

		// Append new assets to the existing list.
		foreach ( $assets_to_add as $asset_definition ) {
			$this->assets[$asset_type->value][] = $asset_definition;
		}
		if ( $logger->is_active() ) {
			$new_total = count( $this->assets[$asset_type->value] );
			$logger->debug( "{$context} - Exiting. New total {$asset_type->value} count: {$new_total}" );
			if ( $new_total > 0 ) {
				$current_handles = array_map( static fn( $a ) => $a['handle'] ?? 'N/A', $this->assets[$asset_type->value] );
				$logger->debug( "{$context} - All current {$asset_type->value} handles: " . implode( ', ', $current_handles ) );
			}
		}
		return $this;
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
		$context = __TRAIT__ . '::stage_' . $asset_type->value . 's';

		// Ensure the asset type key exists to prevent notices on count().
		if ( ! isset( $this->assets[$asset_type->value] ) ) {
			$this->assets[$asset_type->value] = array();
		}
		$asset_type_string = strtolower($asset_type->value);

		if ( $logger->is_active() ) {
			$logger->debug( $context . ' - Entered. Processing ' . count( $this->assets[$asset_type->value] ) . ' ' . $asset_type->value . ' definition(s) for registration.' );
		}

		$assets_to_process                = $this->assets[$asset_type->value];
		$this->assets[$asset_type->value] = array(); // Clear original to re-populate with non-deferred assets that are successfully processed.

		foreach ( $assets_to_process as $index => $asset_definition ) {
			$hook = $asset_definition['hook'] ?? null;

			if ( $logger->is_active() ) {
				$logger->debug( "{$context} - Processing {$asset_type->value}: \"{$asset_definition['handle']}\", original index: {$index}." );
			}

			if ( ! empty( $hook ) ) {
				if ( $logger->is_active() ) {
					$logger->debug( "{$context} - Deferring registration of {$asset_type->value} '{$asset_definition['handle']}' (original index {$index}) to hook: {$hook}." );
				}
				if ( ! isset( $this->deferred_assets[$asset_type->value][$hook] ) ) {
					$this->deferred_assets[$asset_type->value][$hook] = array();
				}
				$this->deferred_assets[$asset_type->value][$hook][$index] = $asset_definition;

				// Ensure the action for deferred assets is added only once per hook.
				// The callback method '_enqueue_deferred_assets' is part of this base trait.
				$action_exists = has_action( $hook, array( $this, 'enqueue_deferred_' . $asset_type_string . 's' ) );
				if ( ! $action_exists ) {
					add_action( $hook, array( $this, 'enqueue_deferred_' . $asset_type_string . 's' ), 10, 1 );
					if ( $logger->is_active() ) {
						$logger->debug( "{$context} - Added action for 'enqueue_deferred_{$asset_type_string}s' on hook: {$hook}." );
					}
				} else {
					if ( $logger->is_active() ) {
						$logger->warning( "{$context} - Action for 'enqueue_deferred_{$asset_type_string}s' on hook '{$hook}' already exists, skipping." );
					}
				}

				// Skip immediate processing for this deferred asset.
				continue;
			} else {
				// Process immediately for registration.
				// The processing_context passed to _process_single_asset is the same as our current log_context_prefix.
				$processed_successfully = $this->_process_single_asset(
					$asset_type,
					$asset_definition,
					$context, // processing_context
					null,               // hook_name (null for immediate registration)
					true,               // do_register
					false              // do_enqueue (registration only)
				);
				// Re-add to $this->assets[$asset_type->value] if it was meant for immediate registration and was successfully processed.
				if ( $processed_successfully ) {
					$this->assets[$asset_type->value][$index] = $asset_definition;
				}
			}
		}

		// Ensure $this->assets[$asset_type->value] is a list if all items were deferred or none were processed successfully.
		$this->assets[$asset_type->value] = array_values($this->assets[$asset_type->value]);

		if ( $logger->is_active() ) {
			$deferred_count = 0;
			foreach (($this->deferred_assets[$asset_type->value] ?? array()) as $hook_assets) {
				$deferred_count += count($hook_assets);
			}
			$logger->debug( "{$context} - Exited. Remaining immediate {$asset_type->value}s: " . count( $this->assets[$asset_type->value] ) . ". Total deferred {$asset_type->value}s: " . $deferred_count . '.' );
		}
		return $this;
	}

	/**
	 * Processes and enqueues all immediate assets, then clears them from the assets array.
	 *
	 * This method iterates through the `$this->assets[$asset_type->value]` array, which at this stage should only
	 * contain non-deferred assets. The `register_assets()` method is responsible for separating
	 * out deferred assets and handling initial registration. This method calls `_process_single_asset`
	 * for each immediate asset to handle the final enqueuing step, and then clears the `$this->assets[$asset_type->value]` array.
	 *
	 * For each immediate asset, it calls `_process_single_asset()` to handle enqueuing and
	 * the processing of any associated inline scripts or attributes.
	 *
	 * Enqueues all immediate assets from the internal queue.
	 *
	 * This method processes assets from the `$this->assets[$asset_type->value]` queue. It is designed to be
	 * robust. If it encounters an asset that has a 'hook' property, it will throw a
	 * `LogicException`, as this indicates that `register_assets()` was not called first
	 * to correctly defer the asset.
	 *
	 * For all other (immediate) assets, this method ensures they are both registered and
	 * enqueued by calling `_process_single_asset()` with both `do_register` and `do_enqueue`
	 * set to `true`. This makes the method safe to call even if `register_assets()` was
	 * skipped for immediate-only assets.
	 *
	 * After processing, this method clears the `$this->assets[$asset_type->value]` array. Deferred assets stored
	 * in `$this->deferred_assets[$asset_type->value]` are not affected.
	 *
	 * @throws \LogicException If a deferred asset is found in the queue, indicating `register_assets()` was not called.
	 * @return self Returns the instance for method chaining.
	 */
	public function enqueue_immediate_assets(AssetType $asset_type): self {
		$logger  = $this->get_logger();
		$context = __TRAIT__ . '::stage_' . $asset_type->value . 's';

		if ( $logger->is_active() ) {
			$logger->debug( "{$context} - Entered. Processing " . count( $this->assets[$asset_type->value] ) . " {$asset_type->value} definition(s) from internal queue." );
		}

		$assets_to_process                = $this->assets[$asset_type->value];
		$this->assets[$asset_type->value] = array(); // Clear the main queue, as we are processing all of them now.

		foreach ( $assets_to_process as $index => $asset_definition ) {
			$handle = $asset_definition['handle'] ?? null;

			if ( empty( $handle ) ) {
				if ( $logger->is_active() ) {
					$logger->warning( "{$context} - Skipping asset at index {$index} due to missing handle - this should not be possible when using add_* methods." );
				}
				continue;
			}

			// Check for mis-queued deferred assets. This is a critical logic error.
			if ( ! empty( $asset_definition['hook'] ) ) {
				throw new \LogicException(
					"{$context} - Found a deferred asset ('{$handle}') in the immediate queue. " .
					'The `stage_assets()` method must be called before `enqueue_immediate_assets()` to correctly process deferred assets.'
				);
			}

			if ( $logger->is_active() ) {
				$logger->debug( "{$context} - Processing {$asset_type->value}: \"{$handle}\", original index: {$index}." );
			}

			// Defensively register and enqueue. The underlying `_process_single_*_asset`
			// will check if the asset is already registered/enqueued and skip redundant calls.
			$this->_process_single_asset(
				$asset_type,
				$asset_definition,
				$context, 			// processing_context
				null, 				// hook_name (null for immediate)
				true, 				// do_register
				true 				// do_enqueue
			);
		}
		if ( $logger->is_active() ) {
			$deferred_count = empty($this->deferred_assets[$asset_type->value]) ? 0 : array_sum(array_map('count', $this->deferred_assets[$asset_type->value]));
			$logger->debug( "{$context} - Exited. Deferred {$asset_type->value}s count: {$deferred_count}." );
		}
		return $this;
	}

	/**
	 * Enqueues assets that were deferred to a specific hook.
	 *
	 * @internal This is an internal method called by WordPress as an action callback and should not be called directly.
	 * @param string $hook_name The WordPress hook name that triggered this method.
	 * @return void
	 */
	protected function _enqueue_deferred_assets(string $hook_name, AssetType $asset_type): void {
		$logger  = $this->get_logger();
		$context = __TRAIT__ . '::enqueue_deferred_' . $asset_type->value . 's';

		if ( $logger->is_active() ) {
			$logger->debug( "{$context} - Entered hook: \"{$hook_name}\"." );
		}

		if ( empty( $this->deferred_assets[$asset_type->value][$hook_name] ) ) {
			if ( $logger->is_active() ) {
				$logger->debug( "{$context} - Hook \"{$hook_name}\" not found in deferred {$asset_type->value}s. Exiting - nothing to process." );
			}
			unset( $this->deferred_assets[$asset_type->value][$hook_name] );
			return;
		}

		$assets_on_this_hook = $this->deferred_assets[$asset_type->value][$hook_name];
		unset( $this->deferred_assets[$asset_type->value][$hook_name] );

		foreach ($assets_on_this_hook as $asset_definition) {
			$this->_process_single_asset($asset_type, $asset_definition, $context, $hook_name, true, true);
		}
	}

	/**
	 * Adds one or more inline asset definitions to the internal queue.
	 *
	 * This method supports adding a single inline asset definition (associative array) or an
	 * array of inline asset definitions. This method is chainable.
	 *
	 * @param array<string, mixed>|array<int, array<string, mixed>> $inline_assets_to_add A single inline asset definition array or an array of them.
	 *     Each definition array can include:
	 *     - 'parent_handle'    (string, required): Handle of the asset to attach the inline asset to.
	 *     - 'content'   (string, required): The inline asset content.
	 *     - 'position'  (string, optional): 'before' or 'after'. Default 'after'.
	 *     - 'condition' (callable, optional): A callable that returns a boolean. If false, the asset is not added.
	 *     - 'parent_hook' (string, optional): Explicitly associate with a parent's hook.
	 * @param AssetType $asset_type The type of asset to add inline assets for.
	 * @return self Returns the instance of this class for method chaining.
	 */
	public function add_inline_assets(
		array $inline_assets_to_add,
		AssetType $asset_type
	): self {
		$logger  = $this->get_logger();
		$context = __TRAIT__ . '::add_inline_' . $asset_type->value . 's';

		// If a single asset definition is passed (detected by the presence of a 'parent_handle' key),
		// wrap it in an array to handle it uniformly.
		if ( isset( $inline_assets_to_add['parent_handle'] ) ) {
			$inline_assets_to_add = array( $inline_assets_to_add );
		}

		$count = count( $inline_assets_to_add );
		if ( $logger->is_active() ) {
			$current_total = count( $this->external_inline_assets[$asset_type->value], COUNT_RECURSIVE ) - count( $this->external_inline_assets[$asset_type->value] );
			$logger->debug( "{$context} - Entered. Current inline {$asset_type->value}s count: {$current_total}. Adding {$count} new definitions." );
		}

		foreach ( $inline_assets_to_add as $asset ) {
			$this->_add_inline_asset(
				$asset_type,
				$asset['parent_handle'],
				$asset['content'],
				$asset['position']    ?? 'after',
				$asset['condition']   ?? null,
				$asset['parent_hook'] ?? null
			);
		}

		return $this;
	}
	/**
	 * Chain-able call to add an inline asset.
	 *
	 * @param string      $handle     (required) Handle of the asset to attach the inline content to.
	 * @param string      $content    (required) The inline content.
	 * @param string      $position   (optional) Whether to add the inline content before or after. Default 'after'.
	 * @param callable|null $condition  (optional) Callback that determines if the inline content should be added.
	 * @param string|null $parent_hook (optional) The WordPress hook name that the parent asset is deferred to.
	 * @param AssetType $asset_type The type of asset ('script' or 'style').
	 * @return self
	 */
	protected function _add_inline_asset(
		AssetType $asset_type,
		string $parent_handle,
		string $content,
		string $position = 'after',
		?callable $condition = null,
		?string $parent_hook = null
	): void {
		$logger           = $this->get_logger();
		$context          = __TRAIT__ . '::add_inline_' . $asset_type->value . 's';
		$asset_type_value = $asset_type->value;

		if ( $logger->is_active() ) {
			$logger->debug( "{$context} - Attempting to add inline {$asset_type_value} to parent '{$parent_handle}'." );
		}

		$inline_asset_definition = array(
			'content'   => $content,
			'position'  => $position,
			'condition' => $condition,
		);

		// Step 1: Search the immediate queue first.
		foreach ( $this->assets[$asset_type_value] as &$asset ) {
			if ( isset( $asset['handle'] ) && $asset['handle'] === $parent_handle ) {
				$asset['inline'][] = $inline_asset_definition;
				if ( $parent_hook && $logger->is_active() ) {
					$logger->warning( "{$context} - A 'parent_hook' was provided for '{$parent_handle}', but it's ignored as the parent was found internally in the immediate queue." );
				}
				return; // Found and attached.
			}
		}

		// Step 2: Search the deferred assets queue.
		foreach ( $this->deferred_assets[$asset_type_value] as &$hook_assets ) {
			foreach ( $hook_assets as &$asset ) {
				if ( isset( $asset['handle'] ) && $asset['handle'] === $parent_handle ) {
					$asset['inline'][] = $inline_asset_definition;
					if ( $parent_hook && $logger->is_active() ) {
						$logger->warning( "{$context} - A 'parent_hook' was provided for '{$parent_handle}', but it's ignored as the parent was found internally in the deferred queue." );
					}
					return; // Found and attached.
				}
			}
		}
		unset( $hook_assets, $asset ); // Clean up references.

		// Step 3: Handle all other cases as external, or bail.
		$is_wp_registered = ( AssetType::Script === $asset_type && \wp_script_is( $parent_handle, 'registered' ) )
			|| ( AssetType::Style === $asset_type && \wp_style_is( $parent_handle, 'registered' ) );

		// We can only proceed if the asset is already registered OR we have a hook to wait for.
		if ( ! $is_wp_registered && ! $parent_hook ) {
			if ( $logger->is_active() ) {
				$logger->warning( "{$context} - Could not find parent handle '{$parent_handle}' in any internal queue or in WordPress, and no 'parent_hook' was provided. Bailing." );
			}
			return; // Bail
		}

		// Determine the hook. Use the provided one, or fall back to a safe default if the asset is already registered.
		$hook = $parent_hook ?? ( $asset_type === AssetType::Script ? 'wp_enqueue_scripts' : 'wp_enqueue_scripts' ); // wp_enqueue_scripts is a safe bet for both

		// Add the status flag to the definition for better debugging.
		$inline_asset_definition['status'] = $is_wp_registered ? 'registered' : 'promised';

		// Add to the single external queue.
		$this->external_inline_assets[$asset_type_value][$hook][$parent_handle][] = $inline_asset_definition;

		// Register the action to enqueue the external inline assets, but only once per hook.
		if ( ! isset( $this->registered_external_hooks[$asset_type_value][$hook] ) ) {
			$enqueue_method = 'enqueue_external_inline_' . $asset_type->value . 's';
			add_action( $hook, array( $this, $enqueue_method ), 11 );
			$this->registered_external_hooks[$asset_type_value][$hook] = true;
		}
	}

	/**
	 * Enqueues external inline assets for a specific hook.
	 *
	 * This method is registered as a callback on a WordPress hook (e.g., 'wp_enqueue_scripts')
	 * and is responsible for processing all external inline assets queued for that hook.
	 *
	 * @param AssetType $asset_type The type of asset ('script' or 'style') to process.
	 * @internal This is an internal method called by WordPress as an action callback and should not be called directly.
	 * @return void
	 */
	protected function _enqueue_external_inline_assets(AssetType $asset_type): void {
		$logger           = $this->get_logger();
		$hook_name        = current_action();
		$asset_type_value = $asset_type->value;
		$context          = __TRAIT__ . '::enqueue_external_inline_' . $asset_type_value . 's';

		if ( $logger->is_active() ) {
			$logger->debug("{$context} - Fired on hook '{$hook_name}'.");
		}

		$assets_for_hook = $this->external_inline_assets[ $asset_type_value ][ $hook_name ] ?? array();

		if ( empty( $assets_for_hook ) ) {
			if ( $logger->is_active() ) {
				$logger->debug("{$context} - No external inline {$asset_type_value}s found for hook '{$hook_name}'. Exiting.");
			}
			return;
		}

		foreach ( array_keys( $assets_for_hook ) as $parent_handle ) {
			$this->_process_inline_assets(
				$asset_type,
				$parent_handle,
				$hook_name,
				$context
			);
		}

		// Remove the processed assets for this hook from the queue.
		unset( $this->external_inline_assets[ $asset_type_value ][ $hook_name ] );

		if ( $logger->is_active() ) {
			$logger->debug("{$context} - Finished processing for hook '{$hook_name}'.");
		}
	}

	/**
	 * Processes inline assets for a given parent handle.
	 *
	 * This method must be implemented by the child trait to handle asset-specific
	 * inline logic (e.g., calling `wp_add_inline_script` vs `wp_add_inline_style`).
	 *
	 * @param AssetType $asset_type The type of asset ('script' or 'style').
	 * @param string      $parent_handle      The handle of the parent asset.
	 * @param string|null $hook_name          The hook context, if any.
	 * @param string      $processing_context A string indicating the calling context for logging.
	 * @return void
	 */
	abstract protected function _process_inline_assets(AssetType $asset_type, string $parent_handle, ?string $hook_name, string $processing_context): void;

	/**
	 * Processes a single asset definition, handling registration, enqueuing, and data additions.
	 *
	 * This abstract method must be implemented by the consuming trait (e.g., ScriptsEnqueueTrait)
	 * to handle the specific logic for that asset type, such as calling wp_register_script vs.
	 * wp_register_style and handling asset-specific parameters like 'in_footer' for scripts
	 * or 'media' for styles.
	 *
	 * @param AssetType $asset_type The type of asset ('script' or 'style').
	 * @param array<string, mixed> $asset_definition The definition of the asset to process.
	 * @param string               $processing_context The context (e.g., 'register_assets', 'enqueue_deferred') in which the asset is being processed, used for logging.
	 * @param string|null          $hook_name The name of the hook if the asset is being processed deferred, otherwise null.
	 * @param bool                 $do_register Whether to register the asset with WordPress.
	 * @param bool                 $do_enqueue Whether to enqueue the asset immediately after registration.
	 * @return string|false The handle of the processed asset, or false if a critical error occurred.
	 */
	abstract protected function _process_single_asset(
		AssetType $asset_type,scripts'
		array $asset_definition,
		string $processing_context,
		?string $hook_name = null,
		bool $do_register = true,
		bool $do_enqueue = false
	): string|false;

	/**
	 * Modifies a asset html loading tag by adding attributes, intended for use with the 'asset_loader_tag' filter.
	 *
	 * This method adjusts the asset tag by adding attributes as specified in the $attributes_to_apply array.
	 * It's designed to work within the context of the 'asset_loader_tag' filter, allowing for dynamic
	 * modification of asset tags based on the handle of the asset being filtered.
	 *
	 * @param AssetType $asset_type The type of asset ('script' or 'style').
	 * @param string $tag The original HTML asset tag.
	 * @param string $filter_tag_handle The handle of the asset currently being filtered by WordPress.
	 * @param string $handle_to_match The handle of the asset we are targeting for modification.
	 * @param array  $attributes_to_apply The attributes to apply to the asset tag.
	 *
	 * @return string The modified (or original) HTML asset tag.
	 */
	abstract protected function _modify_html_tag_attributes(
		AssetType $asset_type,
		string $tag,
		string $tag_handle,
		string $handle_to_match,
		array $attributes_to_apply
	): string;
}
