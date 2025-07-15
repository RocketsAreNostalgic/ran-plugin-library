<?php
/**
 * Provides common, asset-agnostic functionality for asset processing.
 *
 * This trait contains the shared helper methods used by the asset-specific traits
 * (ScriptsEnqueueTrait, StylesEnqueueTrait). It forms the foundational layer of
 * the dispatcher pattern (see ADR-003), providing the concrete logic that is
 * ultimately called by the dispatcher methods in AssetEnqueueBaseAbstract.
 *
 * It is not intended to be used directly by a consumer class, but rather as a
 * dependency for the main asset processing base class.
 *
 * @todo - Implement the de-registering of assets.
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
 * Trait AssetEnqueueBaseTrait
 *
 * Manages the registration, enqueuing, and processing of assets.
 * This includes handling general assets, inline assets, and deferred assets.
 *
 * @package Ran\PluginLib\EnqueueAccessory
 */
trait AssetEnqueueBaseTrait {
	/**
	 * Holds all asset definitions.
	 *
	 * @var array<string, array<int, array<string, mixed>>>
	 */
	protected array $registered_hooks = array();

	protected array $assets = array(
		'script' => array(),
		'style'  => array(),
	);

	/**
	 * Array of assets to be loaded at specific WordPress action hooks, grouped by priority.
	 *
	 * @var array<string, array<string, array<int, array<int, array<string, mixed>>>>>
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
	 * Resolves the asset source URL based on the environment.
	 *
	 * If `SCRIPT_DEBUG` is true, it prefers the 'dev' URL.
	 * Otherwise, it prefers the 'prod' URL.
	 *
	 * @param string|array $src The source URL(s) for the asset.
	 *
	 * @return string The resolved source URL.
	 */
	protected function _resolve_environment_src($src): string {
		if (is_string($src)) {
			return $src;
		}

		$is_dev = $this->get_config()->is_dev_environment();

		if ($is_dev && !empty($src['dev'])) {
			return $src['dev'];
		} elseif (!empty($src['prod'])) {
			return $src['prod'];
		}

		// Fallback to the first available URL in the array.
		return (string) reset($src);
	}

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
				$handle  = $asset_data['handle'] ?? 'N/A';
				$src_val = $asset_data['src']    ?? 'N/A';
				$src_log = is_array($src_val) ? json_encode($src_val) : $src_val;
				$logger->debug( "{$context} - Adding {$asset_type->value}. Key: {$asset_key}, Handle: {$handle}, src: {$src_log}" );
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
		$context = __TRAIT__ . '::stage_' . strtolower( $asset_type->value ) . 's';

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

					$this->_add_action( $hook_name, $callback, $priority, 0 );
					$this->registered_hooks[ $asset_type->value ][ $hook_name . '_' . $priority ] = true;

					if ( $logger->is_active() ) {
						$logger->debug( "{$context} - Added action for hook '{$hook_name}' with priority {$priority}." );
					}
				}

				continue; // Skip immediate processing for this deferred asset.
			} else {
				// Process immediately for registration.
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
	 * Enqueues assets that were deferred to a specific hook and priority.
	 *
	 * @internal This is an internal method called by WordPress as an action callback and should not be called directly.
	 * @param AssetType $asset_type The type of asset being processed.
	 * @param string    $hook_name The WordPress hook name that triggered this method.
	 * @param int       $priority The priority of the action that triggered this callback.
	 * @return void
	 */
	protected function _enqueue_deferred_assets( AssetType $asset_type, string $hook_name, int $priority ): void {
		$logger  = $this->get_logger();
		$context = __TRAIT__ . '::_enqueue_deferred_' . $asset_type->value . 's';

		if ( $logger->is_active() ) {
			$logger->debug( "{$context} - Entered hook: \"{$hook_name}\" with priority: {$priority}." );
		}

		// Check if there are any assets for this specific hook and priority.
		if ( ! isset( $this->deferred_assets[ $asset_type->value ][ $hook_name ][ $priority ] ) ) {
			if ( $logger->is_active() ) {
				$logger->debug( "{$context} - Hook \"{$hook_name}\" with priority {$priority} not found in deferred {$asset_type->value}s. Exiting - nothing to process." );
			}
			// If the hook itself is now empty (i.e., it had no priorities, or the last one was just processed),
			// remove it to keep the deferred assets array clean.
			if ( isset( $this->deferred_assets[ $asset_type->value ][ $hook_name ] ) && empty( $this->deferred_assets[ $asset_type->value ][ $hook_name ] ) ) {
				unset( $this->deferred_assets[ $asset_type->value ][ $hook_name ] );
			}
			return;
		}

		// Retrieve the assets for this specific hook and priority.
		$assets_to_process = $this->deferred_assets[ $asset_type->value ][ $hook_name ][ $priority ];

		// Process each asset.
		foreach ( $assets_to_process as $asset_definition ) {
			if ( $logger->is_active() ) {
				$handle = is_array( $asset_definition ) ? $asset_definition['handle'] : $asset_definition;
				$logger->debug( "{$context} - Processing deferred asset '{$handle}'." );
			}
			$this->_process_single_asset( $asset_type, $asset_definition, $context, $hook_name, true, true );
		}

		// Once processed, remove this priority's assets to prevent re-processing.
		unset( $this->deferred_assets[ $asset_type->value ][ $hook_name ][ $priority ] );

		// If this was the last priority for this hook, clean up the hook key as well.
		if ( empty( $this->deferred_assets[ $asset_type->value ][ $hook_name ] ) ) {
			unset( $this->deferred_assets[ $asset_type->value ][ $hook_name ] );
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
		foreach ( $this->deferred_assets[$asset_type_value] as $hook => &$priorities ) {
			foreach ( $priorities as $priority => &$assets ) {
				foreach ( $assets as &$asset ) {
					if ( $asset['handle'] === $parent_handle ) {
						$asset['inline'][] = $inline_asset_definition;
						if ( $logger->is_active() ) {
							$logger->debug( "{$context} - Found deferred parent '{$parent_handle}' and attached the inline asset." );
						}
						return;
					}
				}
			}
		}

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
	 * Concrete implementation for processing inline assets associated with a specific parent asset handle and hook context.
	 * This method handles both script and style inline assets with appropriate conditional logic.
	 *
	 * @param AssetType   $asset_type        The type of asset (script or style).
	 * @param string      $parent_handle     The handle of the parent asset.
	 * @param string|null $hook_name         (Optional) The hook name if processing for a deferred context.
	 * @param string      $processing_context A string indicating the context for logging purposes.
	 * @return void
	 */
	protected function _concrete_process_inline_assets(
		AssetType $asset_type,
		string $parent_handle,
		?string $hook_name = null,
		string $processing_context = 'immediate'
	): void {
		$logger  = $this->get_logger();
		$context = __METHOD__ . " (context: {$processing_context}) - ";

		$logger->debug( "{$context}Checking for inline {$asset_type->value}s for parent {$asset_type->value} '{$parent_handle}'" . ($hook_name ? " on hook '{$hook_name}'." : '.') );

		// Check if the parent asset is registered or enqueued before processing its inline assets.
		$is_registered_function = $asset_type === AssetType::Script ? 'wp_script_is' : 'wp_style_is';
		if ( ! $is_registered_function( $parent_handle, 'registered' ) && ! $is_registered_function( $parent_handle, 'enqueued' ) ) {
			$logger->error( "{$context}Cannot add inline {$asset_type->value}s. Parent {$asset_type->value} '{$parent_handle}' is not registered or enqueued." );
			return;
		}

		$keys_to_unset          = array();
		$inline_assets_for_type = $this->inline_assets[$asset_type->value] ?? array();

		foreach ( $inline_assets_for_type as $key => $inline_asset_data ) {
			if (!is_array($inline_asset_data)) {
				$logger->warning("{$context}Invalid inline {$asset_type->value} data at key '{$key}'. Skipping.");
				continue;
			}

			$inline_target_handle = $inline_asset_data['handle']      ?? null;
			$inline_parent_hook   = $inline_asset_data['parent_hook'] ?? null;
			$is_match             = false;

			if ( $inline_target_handle === $parent_handle ) {
				if ( $hook_name ) { // Deferred context
					if ( $inline_parent_hook === $hook_name ) {
						$is_match = true;
					}
				} else { // Immediate context
					if ( empty( $inline_parent_hook ) ) {
						$is_match = true;
					}
				}
			}

			if ( $is_match ) {
				$content          = $inline_asset_data['content']   ?? '';
				$condition_inline = $inline_asset_data['condition'] ?? null;

				// Position is only applicable for scripts
				$position = $asset_type === AssetType::Script
					? ($inline_asset_data['position'] ?? 'after')
					: null;

				if ( is_callable( $condition_inline ) && ! $condition_inline() ) {
					$logger->debug( "{$context}Condition false for inline {$asset_type->value} targeting '{$parent_handle}' (key: {$key})" . ($hook_name ? " on hook '{$hook_name}'." : '.') );
					$keys_to_unset[] = $key;
					continue;
				}

				if ( empty( $content ) ) {
					$logger->warning( "{$context}Empty content for inline {$asset_type->value} targeting '{$parent_handle}'" . ($hook_name ? " on hook '{$hook_name}'." : '.') . ' Skipping addition.' );
					$keys_to_unset[] = $key;
					continue;
				}

				$logger->debug( "{$context}Adding inline {$asset_type->value} for '{$parent_handle}' (key: {$key}" .
					($asset_type === AssetType::Script ? ", position: {$position}" : '') . ')' .
					($hook_name ? " on hook '{$hook_name}'." : '.') );

				$add_inline_function = $asset_type === AssetType::Script ? 'wp_add_inline_script' : 'wp_add_inline_style';
				$result              = $asset_type === AssetType::Script
					? $add_inline_function($parent_handle, $content, $position)
					: $add_inline_function($parent_handle, $content);

				if ($result) {
					$logger->debug("{$context}Successfully added inline {$asset_type->value} for '{$parent_handle}' with {$add_inline_function}.");
				} else {
					$logger->warning("{$context}Failed to add inline {$asset_type->value} for '{$parent_handle}' with {$add_inline_function}, key {$key} will be removed from queue.");
				}
				$keys_to_unset[] = $key;
			}
		}

		if ( ! empty( $keys_to_unset ) ) {
			foreach ( $keys_to_unset as $key_to_unset ) {
				if (isset($this->inline_assets[$asset_type->value][$key_to_unset])) {
					$removed_handle_for_log = $this->inline_assets[$asset_type->value][$key_to_unset]['handle'] ?? 'N/A';
					unset( $this->inline_assets[$asset_type->value][ $key_to_unset ] );
					$logger->debug( "{$context}Removed processed inline {$asset_type->value} with key '{$key_to_unset}' for handle '{$removed_handle_for_log}'" . ($hook_name ? " on hook '{$hook_name}'." : '.') );
				}
			}
			// Re-index the array to prevent issues with numeric keys after unsetting.
			$this->inline_assets[$asset_type->value] = array_values( $this->inline_assets[$asset_type->value] );
		} else {
			$logger->debug( "{$context}No inline {$asset_type->value} found or processed for '{$parent_handle}'" . ($hook_name ? " on hook '{$hook_name}'." : '.') );
		}
	}

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
		AssetType $asset_type,
		array $asset_definition,
		string $processing_context,
		?string $hook_name = null,
		bool $do_register = true,
		bool $do_enqueue = false
	): string|false;

	/**
	 * Process a single asset (script or style) with common handling logic.
	 *
	 * This method handles the common processing logic for both script and style assets,
	 * including environment-specific source resolution, condition checking, deferred asset detection,
	 * and registration/enqueuing.
	 *
	 * @param AssetType     $asset_type        The type of asset (script or style).
	 * @param array         $asset_definition  The asset definition array.
	 * @param string        $processing_context The context in which this asset is being processed.
	 * @param string|null   $hook_name         Optional. The hook name for deferred assets.
	 * @param bool          $do_register       Whether to register the asset.
	 * @param bool          $do_enqueue        Whether to enqueue the asset.
	 * @param array         $type_specific     Type-specific options (media for styles, in_footer for scripts).
	 *
	 * @return string|false The asset handle if successful, false otherwise.
	 */
	protected function _concrete_process_single_asset(
		AssetType $asset_type,
		array $asset_definition,
		string $processing_context,
		?string $hook_name = null,
		bool $do_register = true,
		bool $do_enqueue = false,
		array $type_specific = array()
	): string|false {
		$logger  = $this->get_logger();
		$context = get_class($this) . '::' . __FUNCTION__;

		// Resolve environment-specific source URL (dev vs. prod) if src is provided.
		if (!empty($asset_definition['src'])) {
			$asset_definition['src'] = $this->_resolve_environment_src($asset_definition['src']);
		}

		$handle    = $asset_definition['handle'] ?? null;
		$src       = $asset_definition['src']    ?? null;
		$deps      = $asset_definition['deps']   ?? array();
		$ver       = $this->_generate_asset_version($asset_definition);
		$ver       = (false === $ver) ? null : $ver;
		$condition = $asset_definition['condition'] ?? null;

		$log_hook_context = $hook_name ? " on hook '{$hook_name}'" : '';

		if ($logger->is_active()) {
			$logger->debug("{$context} - Processing {$asset_type->value} '{$handle}'{$log_hook_context} in context '{$processing_context}'.");
		}

		if (is_callable($condition) && !$condition()) {
			if ($logger->is_active()) {
				$logger->debug("{$context} - Condition not met for {$asset_type->value} '{$handle}'{$log_hook_context}. Skipping.");
			}
			return false;
		}

		if (empty($handle)) {
			if ($logger->is_active()) {
				$logger->warning("{$context} - {$asset_type->value} definition is missing a 'handle'. Skipping.");
			}
			return false;
		}

		// During staging phase, skip processing of deferred assets completely
		$deferred_handle = $this->_is_deferred_asset(
			$asset_definition,
			$handle,
			$hook_name,
			$context,
			$asset_type
		);

		if ($deferred_handle !== null) {
			// For deferred assets, we don't register during stage_scripts
			// We'll handle this during the hook
			if ($processing_context === 'stage_scripts') {
				return $deferred_handle;
			}
		}

		$src_url = ($src === false) ? false : $this->get_asset_url($src, $asset_type);

		if ($src_url === null && $src !== false) {
			if ($logger->is_active()) {
				$logger->error("{$context} - Could not resolve source for {$asset_type->value} '{$handle}'. Skipping.");
			}
			return false;
		}

		// Standard handling for non-deferred assets
		$this->do_register(
			$asset_type,
			$do_register,
			$handle,
			$src_url,
			$deps,
			$ver,
			$type_specific,
			$context,
			$log_hook_context
		);

		if ($do_enqueue) {
			$is_deferred = $deferred_handle !== null;

			// Use the shared do_enqueue method
			$enqueue_result = $this->do_enqueue(
				$asset_type,
				true,
				$handle,
				$src_url,
				$deps,
				$ver,
				$type_specific,
				$context,
				$log_hook_context,
				$is_deferred,
				$hook_name
			);

			if (!$enqueue_result) {
				return false;
			}
		}

		if ($logger->is_active()) {
			$logger->debug("{$context} - Finished processing {$asset_type->value} '{$handle}'{$log_hook_context}.");
		}

		return $handle;
	}

	/**
	 * Handles asset registration for both scripts and styles.
	 *
	 * @param AssetType    $asset_type      The type of asset (Script or Style).
	 * @param bool         $do_register     Whether to perform registration.
	 * @param string       $handle          The handle of the asset.
	 * @param string|false $src             The source URL of the asset or false if no source.
	 * @param array        $deps            Dependencies for the asset.
	 * @param string|false $ver             Version string.
	 * @param array|string $extra_args      Extra arguments (media for styles, in_footer for scripts).
	 * @param string       $context         The logging context.
	 * @param string       $log_hook_context Additional hook context for logging.
	 *
	 * @return bool False if registration fails, true otherwise.
	 */
	protected function do_register(
		AssetType $asset_type,
		bool $do_register,
		string $handle,
		$src,
		array $deps,
		$ver,
		$extra_args,
		string $context,
		string $log_hook_context = ''
	): bool {
		$logger = $this->get_logger();

		if ($do_register) {
			$is_registered = $asset_type === AssetType::Script
				? wp_script_is($handle, 'registered')
				: wp_style_is($handle, 'registered');

			if ($is_registered) {
				if ( $logger->is_active() ) {
					$logger->debug( "{$context} - {$asset_type->value} '{$handle}'{$log_hook_context} already registered. Skipping registration." );
				}
			} else {
				if ($logger->is_active()) {
					$logger->debug("{$context} - Registering {$asset_type->value}: '{$handle}'{$log_hook_context}: {$src}");
				}

				$result = false;
				if ($asset_type === AssetType::Script) {
					// For scripts, $extra_args would be $in_footer
					$in_footer = $extra_args['in_footer'] ?? false;
					// Pass in_footer as an array to match test expectations
					$result = wp_register_script($handle, $src, $deps, $ver, array('in_footer' => $in_footer));
				} else {
					// For styles, $extra_args would be $media
					$media  = $extra_args['media'] ?? 'all';
					$result = wp_register_style($handle, $src, $deps, $ver, $media);
				}

				if (!$result) {
					if ($logger->is_active()) {
						$logger->warning("{$context} - wp_register_{$asset_type->value}() failed for handle '{$handle}'{$log_hook_context}. Skipping further processing for this asset.");
					}
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Determines if an asset is deferred and handles early return for staging phase.
	 *
	 * @param array     $asset_definition The asset definition to check.
	 * @param string    $handle          The handle of the asset.
	 * @param string    $hook_name       The hook name if in hook-firing phase, null otherwise.
	 * @param string    $context         The logging context.
	 * @param AssetType $asset_type      The type of asset (Script or Style).
	 * @return string|null The handle if this is a deferred asset during staging (for early return),
	 *                     null otherwise.
	 */
	protected function _is_deferred_asset(
    array $asset_definition,
    string $handle,
    ?string $hook_name,
    ?string $context = null,
    ?AssetType $asset_type = null
): ?string {
		$is_deferred_asset = !empty($asset_definition['hook']);
		$is_hook_firing    = $hook_name !== null;

		// During staging phase, skip processing of deferred assets completely
		if ($is_deferred_asset && !$is_hook_firing) {
			// Only log if context and asset_type are provided
			if ($context !== null && $asset_type !== null) {
				$logger = $this->get_logger();
				if ($logger->is_active()) {
					$logger->debug("{$context} - Skipping processing of deferred {$asset_type->value} '{$handle}' during staging. Will process when hook '{$asset_definition['hook']}' fires.");
				}
			}
			return $handle;
		}

		return null;
	}

	/**
	 * Common enqueue method for both scripts and styles.
	 *
	 * @param AssetType $asset_type      The type of asset (Script or Style).
	 * @param bool      $do_enqueue      Whether to enqueue the asset.
	 * @param string    $handle          The handle of the asset.
	 * @param string|false $src         The source URL of the asset.
	 * @param array     $deps           The dependencies of the asset.
	 * @param string|false $ver         The version of the asset.
	 * @param mixed     $extra_args     Extra arguments (in_footer for scripts, media for styles).
	 * @param string    $context        The logging context.
	 * @param string    $log_hook_context Additional hook context for logging.
	 * @param bool      $is_deferred    Whether this is a deferred asset.
	 * @param string|null $hook_name    The hook name if in hook-firing phase.
	 *
	 * @return bool True if enqueued successfully, false otherwise.
	 */
	protected function do_enqueue(
		AssetType $asset_type,
		bool $do_enqueue,
		string $handle,
		$src,
		array $deps,
		$ver,
		$extra_args,
		string $context,
		string $log_hook_context = '',
		bool $is_deferred = false,
		?string $hook_name = null
	): bool {
		if (!$do_enqueue) {
			return true;
		}

		$logger      = $this->get_logger();
		$is_enqueued = $asset_type === AssetType::Script
			? wp_script_is($handle, 'enqueued')
			: wp_style_is($handle, 'enqueued');

		if ($is_enqueued) {
			if ($logger->is_active()) {
				$logger->debug("{$context} - {$asset_type->value} '{$handle}'{$log_hook_context} already enqueued. Skipping.");
			}
			return true;
		}

		// For deferred assets that are being processed during their hook, we don't want to auto-register if not registered
		// This prevents double registration since these assets will be explicitly registered above
		$skip_auto_registration = $is_deferred && $hook_name !== null;

		$is_registered = $asset_type === AssetType::Script
			? wp_script_is($handle, 'registered')
			: wp_style_is($handle, 'registered');

		if (!$skip_auto_registration && !$is_registered) {
			// Asset is not registered yet, register it first
			if ($src !== false && empty($src)) {
				if ($logger->is_active()) {
					$logger->error("{$context} - Cannot register or enqueue {$asset_type->value} '{$handle}' because its 'src' is missing.");
				}
				return false; // Cannot proceed without src being a source, or false.
			}

			// Log that we're registering the asset first
			if ($logger->is_active()) {
				$logger->warning(
					sprintf(
						"%s - %s '%s' was not registered before enqueuing. Registering now.",
						$context,
						$asset_type->value,
						$handle
					)
				);
			}

			// Register the asset directly using WP functions for test compatibility
			$register_result = false;
			if ($asset_type === AssetType::Script) {
				$register_result = wp_register_script($handle, $src, $deps, $ver, $extra_args);
			} else {
				$register_result = wp_register_style($handle, $src, $deps, $ver, $extra_args);
			}

			if (!$register_result) {
				return false;
			}
		}

		// Asset is now registered, enqueue it
		if ($logger->is_active()) {
			$logger->debug("{$context} - Enqueuing {$asset_type->value} '{$handle}'{$log_hook_context}.");
		}

		if ($asset_type === AssetType::Script) {
			wp_enqueue_script($handle);
		} else {
			wp_enqueue_style($handle);
		}

		return true;
	}

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


	/**
	 * Generates a version string for an asset, using cache-busting if configured.
	 *
	 * If the asset definition has 'cache_bust' set to true, this method will attempt
	 * to generate a version based on the file's content hash. Otherwise, it returns
	 * the version specified in the asset definition or false.
	 *
	 * @param array<string, mixed> $asset_definition The asset's definition array.
	 * @return string|false The calculated version string, or false if no version is applicable.
	 */
	protected function _generate_asset_version(array $asset_definition): string|false {
		$logger  = $this->get_logger();
		$context = __TRAIT__ . '::' . __FUNCTION__;

		$version    = $asset_definition['version']    ?? false;
		$cache_bust = $asset_definition['cache_bust'] ?? false;
		$handle     = $asset_definition['handle']     ?? 'N/A';
		$src        = $asset_definition['src']        ?? false;

		// If cache busting is not requested, or if there's no source file to bust,
		// just return the version from the definition.
		if ( ! $cache_bust || empty( $src ) ) {
			return $version;
		}

		$src = $asset_definition['src'] ?? null;
		if (empty($src)) {
			return $version; // Cannot cache-bust without a source URL.
		}

		$file_path = $this->_resolve_url_to_path($src);

		if (false === $file_path) {
			if ($logger->is_active()) {
				$logger->debug("{$context} - Could not resolve path for '{$handle}' from src '{$src}'. Cache-busting skipped.");
			}
			return $version;
		}

		if (!$this->_file_exists($file_path)) {
			if ($logger->is_active()) {
				$logger->warning("{$context} - Cache-busting for '{$handle}' failed. File not found at resolved path: '{$file_path}'.");
			}			return $version;
		}

		$hash = $this->_md5_file($file_path);
		return $hash ? substr($hash, 0, 10) : $version;
	}

	/**
	 * Resolves a URL to a physical file path on the server.
	 *
	 * This is a helper for the cache-busting mechanism to locate the file and read its content.
	 * It needs to handle various URL formats (plugin-relative, theme-relative, absolute).
	 *
	 * @param string $url The asset URL to resolve.
	 * @return string|false The absolute file path, or false if resolution fails.
	 */
	protected function _resolve_url_to_path(string $url): string|false {
		$logger  = $this->get_logger();
		$context = __TRAIT__ . '::' . __FUNCTION__;

		// Use content_url() and WP_CONTENT_DIR for robust path resolution in
		// single and multisite environments.
		$content_url = content_url();
		$content_dir = WP_CONTENT_DIR;

		// Check if the asset URL is within the content directory.
		if (strpos($url, $content_url) === 0) {
			$file_path       = str_replace($content_url, $content_dir, $url);
			$normalized_path = wp_normalize_path($file_path);

			if ($logger->is_active()) {
				$logger->debug("{$context} - Resolved URL to path: '{$url}' -> '{$normalized_path}'.");
			}
			return $normalized_path;
		}

		// Fallback for URLs outside of wp-content, e.g., in wp-includes.
		// This is less common for plugin/theme assets but adds robustness.
		$site_url = site_url();
		if (strpos($url, $site_url) === 0) {
			$relative_path   = substr($url, strlen($site_url));
			$file_path       = ABSPATH . ltrim($relative_path, '/');
			$normalized_path = wp_normalize_path($file_path);

			if ($logger->is_active()) {
				$logger->debug("{$context} - Resolved URL to path (fallback): '{$url}' -> '{$normalized_path}'.");
			}
			return $normalized_path;
		}

		if ($logger->is_active()) {
			$logger->warning("{$context} - Could not resolve URL to path: '{$url}'. URL does not start with content_url ('{$content_url}') or site_url ('{$site_url}').");
		}

		return false;
	}

	// ------------------------------------------------------------------------
	// region FILESYSTEM WRAPPERS FOR TESTABILITY
	// ------------------------------------------------------------------------

	/**
	 * Wraps the native file_exists function to allow for mocking in tests.
	 *
	 * @param string $path The file path to check.
	 *
	 * @return bool True if the file exists, false otherwise.
	 */
	protected function _file_exists(string $path): bool {
		return file_exists($path);
	}

	/**
	 * Wraps the native md5_file function to allow for mocking in tests.
	 *
	 * @param string $path The path to the file.
	 *
	 * @return string|false The MD5 hash of the file, or false on failure.
	 */
	protected function _md5_file(string $path): string|false {
		return md5_file($path);
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
