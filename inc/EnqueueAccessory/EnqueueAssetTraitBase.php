<?php
/**
 * Base trait for enqueuing assets
 *
 * @todo FEATURE - cachebusting beyond version numbers
 * @todo FEATURE - dev vs prod for loading minififed files
 * @todo Clearify - How is load order handeled in current context?
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
	 * @var array<int, array<string, mixed>>
	 */
	protected array $assets = array();

	/**
	 * Array of inline asset definitions to be added.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	protected array $inline_assets = array();

	/**
	 * Array of assets to be loaded at specific WordPress action hooks.
	 *
	 * @var array<string, array<int, array<string, mixed>>>
	 */
	protected array $deferred_assets = array();

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
	 * @return array<string, array> An associative array of asset definitions, keyed by 'general', 'deferred', and 'inline'.
	 */
	public function get_assets( string $asset_type ): array {
		return array(
			'general'  => $this->assets[ $asset_type ]          ?? array(),
			'deferred' => $this->deferred_assets[ $asset_type ] ?? array(),
			'inline'   => $this->inline_assets[ $asset_type ]   ?? array(),
		);
	}

	/**
	 * Retrieves the currently registered array of asset definitions.
	 *
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public function get_inline_assets( string $asset_type ): array {
		return $this->inline_assets[ $asset_type ] ?? array();
	}

	/**
	 * Retrieves the registered deferred assets.
	 *
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public function get_deferred_assets( string $asset_type ): array {
		return $this->deferred_assets[ $asset_type ] ?? array();
	}

	/**
	 * Retrieves the unique hook names for all registered deferred assets.
	 *
	 * This method inspects both unprocessed assets and already-registered deferred assets
	 * to provide a complete list of hooks, which is necessary for the `load()` method
	 * to correctly register all necessary WordPress actions before assets are processed.
	 *
	 * @return string[] An array of unique hook names.
	 * @see ARD/ADR-001.md For the rationale behind this preemptive check.
	 */
	public function get_deferred_hooks( string $asset_type ): array {
		$hooks = array();

		// Check for hooks in the main assets array for the given type.
		foreach ( ( $this->assets[ $asset_type ]['general'] ?? array() ) as $asset ) {
			if ( ! empty( $asset['hook'] ) ) {
				$hooks[] = $asset['hook'];
			}
		}

		// Merge with hooks from already-processed deferred assets for the given type.
		$deferred_hooks = array_keys( $this->deferred_assets[ $asset_type ] ?? array() );

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
	public function add_assets( array $assets_to_add, string $asset_type ): self {
		$logger  = $this->get_logger();
		$context = __TRAIT__ . '::add_' . $asset_type;

		// Ensure the asset type key exists to prevent notices on count().
		if ( ! isset( $this->assets[ $asset_type ] ) ) {
			$this->assets[ $asset_type ] = [];
		}

		if ( empty( $assets_to_add ) ) {
			if ( $logger->is_active() ) {
				$logger->debug( "{$context} - Entered with empty array. No {$asset_type}s to add." );
			}
			return $this;
		}

		// Normalize single asset definition into an array of definitions.
		if ( isset( $assets_to_add['handle'] ) ) {
			$assets_to_add = array( $assets_to_add );
		}

		if ( $logger->is_active() ) {
			$logger->debug( "{$context} - Entered. Current {$asset_type} count: " . count( $this->assets[ $asset_type ] ) . '. Adding ' . count( $assets_to_add ) . " new {$asset_type}(s)." );
			foreach ( $assets_to_add as $asset_key => $asset_data ) {
				$handle = $asset_data['handle'] ?? 'N/A';
				$src    = $asset_data['src']    ?? 'N/A';
				$logger->debug( "{$context} - Adding {$asset_type}. Key: {$asset_key}, Handle: {$handle}, src: {$src}" );
			}
			$logger->debug( "{$context} - Adding " . count( $assets_to_add ) . " {$asset_type} definition(s). Current total: " . count( $this->assets[ $asset_type ] ) );
		}

		// Append new assets to the existing list.
		foreach ( $assets_to_add as $asset_definition ) {
			$this->assets[ $asset_type ][] = $asset_definition;
		}
		if ( $logger->is_active() ) {
			$new_total = count( $this->assets[ $asset_type ] );
			$logger->debug( "{$context} - Exiting. New total {$asset_type} count: {$new_total}" );
			if ( $new_total > 0 ) {
				$current_handles = array_map( static fn( $a ) => $a['handle'] ?? 'N/A', $this->assets[ $asset_type ]);
				$logger->debug( "{$context} - All current {$asset_type} handles: " . implode( ', ', $current_handles ) );
			}
		}
		return $this;
	}

	/**
	 * Registers assets, separating deferred assets and preparing immediate assets for enqueuing.
	 *
	 * This method processes asset definitions previously added via `add_assets($asset_type)`.
	 * It begins by taking a copy of all current assets and then clearing the main `$this->assets[ $asset_type ]` array.
	 *
	 * It then iterates through the copied asset definitions:
	 * - If an asset specifies a 'hook', it is considered deferred. The asset definition is moved
	 *   to the `$this->deferred_assets[ $asset_type ]` array, keyed by its hook name. An action is scheduled
	 *   with WordPress to call `enqueue_deferred_assets()` for that hook. Deferred assets are
	 *   not re-added to the main `$this->assets[ $asset_type ]` array.
	 * - If an asset does not specify a 'hook', it is considered immediate. `_process_single_asset()`
	 *   is called for this asset with `do_register = true` and `do_enqueue = false` to handle
	 *   its registration with WordPress.
	 * - If an immediate asset is successfully registered, its definition is added back into the
	 *   (initially cleared) `$this->assets[ $asset_type ]` array, preserving its original index.
	 *
	 * After processing all assets, the `$this->assets[ $asset_type ]` array will contain only those immediate
	 * assets that were successfully registered. This array is then re-indexed using `array_values()`.
	 * The primary role of this method is to manage the initial registration phase and to ensure
	 * that `$this->assets[ $asset_type ]` is correctly populated with only immediate, registered assets, ready
	 * for the `enqueue_assets()` method to handle their final enqueuing.
	 *
	 * @return self Returns the instance for method chaining.
	 */
	public function register_assets(string $asset_type): self {
		$logger  = $this->get_logger();
		$context = __TRAIT__ . '::register_' . $asset_type;

		if ( $logger->is_active() ) {
			$logger->debug( __METHOD__ . ' - Entered. Processing ' . count( $this->assets[ $asset_type ] ) . ' ' . $asset_type . ' definition(s) for registration.' );
		}

		$assets_to_process           = $this->assets[ $asset_type ];
		$this->assets[ $asset_type ] = array(); // Clear original to re-populate with non-deferred assets that are successfully processed.

		foreach ( $assets_to_process as $index => $asset_definition ) {
			$handle_for_log = $asset_definition['handle'] ?? 'N/A';
			$hook           = $asset_definition['hook']   ?? null;

			if ( $logger->is_active() ) {
				$logger->debug( "{$context} - Processing {$asset_type}: \"{$handle_for_log}\", original index: {$index}." );
			}

			if ( ! empty( $hook ) ) {
				if ( $logger->is_active() ) {
					$logger->debug( "{$context} - Deferring registration of {$asset_type} '{$handle_for_log}' (original index {$index}) to hook: {$hook}." );
				}
				if ( ! isset( $this->deferred_assets[$asset_type][ $hook ] ) ) {
					$this->deferred_assets[$asset_type][ $hook ] = array();
				}
				$this->deferred_assets[$asset_type][ $hook ][ $index ] = $asset_definition;

				// Ensure the action for deferred assets is added only once per hook.
				// The callback method 'enqueue_deferred_assets' is part of this base trait.
				$action_exists = has_action( $hook, array( $this, 'enqueue_deferred_assets' ) );
				if ( ! $action_exists ) {
					add_action( $hook, array( $this, 'enqueue_deferred_assets' ), 10, 1 );
					if ( $logger->is_active() ) {
						$logger->debug( "{$context} - Added action for 'enqueue_deferred_assets' on hook: {$hook}." );
					}
				} else {
					if ( $logger->is_active() ) {
						$logger->debug( "{$context} - Action for 'enqueue_deferred_assets' on hook '{$hook}' already exists." );
					}
				}
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
				// Re-add to $this->assets[$asset_type] if it was meant for immediate registration and was successfully processed.
				if ( $processed_successfully ) {
					$this->assets[ $asset_type ][ $index ] = $asset_definition;
				}
			}
		}

		// Ensure $this->assets[$asset_type] is a list if all items were deferred or none were processed successfully.
		$this->assets[ $asset_type ] = array_values($this->assets[ $asset_type ]);

		if ( $logger->is_active() ) {
			$deferred_count = 0;
			foreach ($this->deferred_assets[$asset_type] as $hook_assets) {
				$deferred_count += count($hook_assets);
			}
			$logger->debug( "{$context} - Exited. Remaining immediate {$asset_type}s: " . count( $this->assets[ $asset_type ] ) . ". Total deferred {$asset_type}s: " . $deferred_count . '.' );
		}
		return $this;
	}

	/**
	 * Processes and enqueues all immediate assets, then clears them from the assets array.
	 *
	 * This method iterates through the `$this->assets[$asset_type]` array, which at this stage should only
	 * contain non-deferred assets. The `register_assets()` method is responsible for separating
	 * out deferred assets and handling initial registration. This method calls `_process_single_asset`
	 * for each immediate asset to handle the final enqueuing step, and then clears the `$this->assets[$asset_type]` array.
	 *
	 * For each immediate asset, it calls `_process_single_asset()` to handle enqueuing and
	 * the processing of any associated inline scripts or attributes.
	 *
	 * Enqueues all immediate assets from the internal queue.
	 *
	 * This method processes assets from the `$this->assets[$asset_type]` queue. It is designed to be
	 * robust. If it encounters an asset that has a 'hook' property, it will throw a
	 * `LogicException`, as this indicates that `register_assets()` was not called first
	 * to correctly defer the asset.
	 *
	 * For all other (immediate) assets, this method ensures they are both registered and
	 * enqueued by calling `_process_single_asset()` with both `do_register` and `do_enqueue`
	 * set to `true`. This makes the method safe to call even if `register_assets()` was
	 * skipped for immediate-only assets.
	 *
	 * After processing, this method clears the `$this->assets[$asset_type]` array. Deferred assets stored
	 * in `$this->deferred_assets[$asset_type]` are not affected.
	 *
	 * @throws \LogicException If a deferred asset is found in the queue, indicating `register_assets()` was not called.
	 * @return self Returns the instance for method chaining.
	 */
	public function enqueue_assets(string $asset_type): self {
		$logger  = $this->get_logger();
		$context = __TRAIT__ . '::enqueue_' . $asset_type . 's';

		if ( $logger->is_active() ) {
			$logger->debug( "{$context} - Entered. Processing " . count( $this->assets[ $asset_type ] ) . " {$asset_type} definition(s) from internal queue." );
		}

		$assets_to_process           = $this->assets[ $asset_type ];
		$this->assets[ $asset_type ] = array(); // Clear the main queue, as we are processing all of them now.

		foreach ( $assets_to_process as $index => $asset_definition ) {
			$handle_for_log = $asset_definition['handle'] ?? 'N/A';

			// Check for mis-queued deferred assets. This is a critical logic error.
			if ( ! empty( $asset_definition['hook'] ) ) {
				throw new \LogicException(
					"{$context} - Found a deferred asset ('{$handle_for_log}') in the immediate queue. " .
					'The `register_assets()` method must be called before `enqueue_assets()` to correctly process deferred assets.'
				);
			}

			if ( $logger->is_active() ) {
				$logger->debug( "{$context} - Processing {$asset_type}: \"{$handle_for_log}\", original index: {$index}." );
			}

			// Defensively register and enqueue. The underlying `_process_single_asset`
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
			$deferred_count = empty($this->deferred_assets[ $asset_type ]) ? 0 : array_sum(array_map('count', $this->deferred_assets[ $asset_type ]));
			$logger->debug( "{$context} - Exited. Deferred {$asset_type}s count: {$deferred_count}." );
		}
		return $this;
	}

	/**
	 * Enqueues assets that were deferred to a specific hook.
	 *
	 * This method is typically called by WordPress as an action callback.
	 *
	 * @param string $hook_name The WordPress hook name that triggered this method.
	 * @return void
	 */
	public function enqueue_deferred_assets( string $hook_name, string $asset_type ): void {
		$logger  = $this->get_logger();
		$context = __TRAIT__ . '::enqueue_deferred_' . $asset_type . 's';

		if ( $logger->is_active() ) {
			$logger->debug( "{$context} - Entered hook: \"{$hook_name}\"." );
		}

		if ( ! isset( $this->deferred_assets[ $asset_type ][ $hook_name ] ) ) {
			if ( $logger->is_active() ) {
				$logger->debug( "{$context} - Hook \"{$hook_name}\" not found in deferred {$asset_type}s. Nothing to process." );
			}
			return;
		}

		$assets_on_this_hook = $this->deferred_assets[ $asset_type ][ $hook_name ];
		unset( $this->deferred_assets[ $asset_type ][ $hook_name ] );

		if ( empty( $assets_on_this_hook ) ) {
			if ( $logger->is_active() ) {
				$logger->debug( "{$context} - Hook \"{$hook_name}\" was set but had no {$asset_type}s. It has now been cleared." );
			}
			return;
		}

		foreach ( $assets_on_this_hook as $original_index => $asset_definition ) {
			$handle_for_log = $asset_definition['handle'] ?? 'N/A_at_original_index_' . $original_index;
			if ( $logger->is_active() ) {
				$logger->debug( "{$context} - Processing deferred {$asset_type}: \"{$handle_for_log}\" (original index {$original_index}) for hook: \"{$hook_name}\"." );
			}
			// _process_single_asset handles all logic including registration, enqueuing, and conditions.
			$processed_handle = $this->_process_single_asset(
				$asset_type,
				$asset_definition,
				$context, // processing_context
				$hook_name,         // hook_name
				true,               // do_register
				true                // do_enqueue
			);

			if ( $processed_handle ) {
				$this->_process_inline_assets(
					$asset_type,
					$processed_handle,
					$hook_name,
					'deferred from enqueue_deferred_' . $asset_type . 's'
				);
			}
		}

		if ( $logger->is_active() ) {
			$logger->debug( "{$context} - Exited for hook: \"{$hook_name}\"" );
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
	 *     - 'handle'    (string, required): Handle of the asset to attach the inline asset to.
	 *     - 'content'   (string, required): The inline asset content.
	 *     - 'position'  (string, optional): 'before' or 'after'. Default 'after'.
	 *     - 'condition' (callable, optional): A callable that returns a boolean. If false, the asset is not added.
	 *     - 'parent_hook' (string, optional): Explicitly associate with a parent's hook.
	 * @param string $asset_type The type of asset to add inline assets for.
	 * @return self Returns the instance of this class for method chaining.
	 */
	public function add_inline_assets(
		array $inline_assets_to_add,
		string $asset_type
	): self {
		$logger  = $this->get_logger();
		$context = __TRAIT__ . '::add_inline_' . $asset_type . 's';

		if ( empty( $inline_assets_to_add ) ) {
			if ( $logger->is_active() ) {
				$logger->debug( "{$context} - Entered with empty array. No inline {$asset_type}s to add." );
			}
			return $this;
		}

		// Normalize single asset definition into an array of definitions.
		if ( ! is_array( current( $inline_assets_to_add ) ) ) {
			$inline_assets_to_add = array( $inline_assets_to_add );
		}

		if ( $logger->is_active() ) {
			$logger->debug( "{$context} - Entered. Current inline {$asset_type} count: " . count( $this->inline_assets[$asset_type] ) . '. Adding ' . count( $inline_assets_to_add ) . " new inline {$asset_type}(s)." );
		}

		foreach ( $inline_assets_to_add as $asset_definition ) {
			$this->_add_inline_asset(
				$asset_type,
				$asset_definition['handle']      ?? '',
				$asset_definition['content']     ?? '',
				$asset_definition['position']    ?? 'after',
				$asset_definition['condition']   ?? null,
				$asset_definition['parent_hook'] ?? null,
			);
		}

		if ( $logger->is_active() ) {
			$logger->debug( "{$context} - Exiting. New total inline {$asset_type} count: " . count( $this->inline_assets[$asset_type] ) );
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
	 * @param string $asset_type The type of asset ('script' or 'style').
	 * @return self
	 */
	private function _add_inline_asset(
		string $asset_type,
		string $handle,
		string $content,
		string $position = 'after',
		?callable $condition = null,
		?string $parent_hook = null,
	): self {
		$logger  = $this->get_logger();
		$context = __TRAIT__ . '::add_inline_' . $asset_type . 's';

		if ( $logger->is_active() ) {
			$logger->debug( "{$context} - Entered. Current inline {$asset_type} count: " . count( $this->inline_assets[$asset_type] ) . ". Adding new inline {$asset_type} for handle: " . \esc_html( $handle ) );
		}

		$inline_asset_item = array(
			'handle'      => $handle,
			'content'     => $content,
			'position'    => $position,
			'condition'   => $condition,
			'parent_hook' => $parent_hook,
		);

		// Associate inline asset with its parent's hook if the parent is deferred.
		foreach ( $this->assets[$asset_type] as $original_asset_definition ) {
			if ( ( $original_asset_definition['handle'] ?? null ) === $handle && ! empty( $original_asset_definition['hook'] ) ) {
				if ( null === $inline_asset_item['parent_hook'] ) {
					$inline_asset_item['parent_hook'] = $original_asset_definition['hook'];
				}
				if ( $logger->is_active() ) {
					$logger->debug( "{$context} - Inline {$asset_type} for '{$handle}' associated with parent hook: '{$inline_asset_item['parent_hook']}'. Original parent {$asset_type} hook: '" . ( $original_asset_definition['hook'] ?? 'N/A' ) . "'." );
				}
				break;
			}
		}

		$this->inline_assets[$asset_type][] = $inline_asset_item;

		if ( $logger->is_active() ) {
			$logger->debug( "{$context} - Exiting. New total inline {$asset_type} count: " . count( $this->inline_assets[$asset_type] ) );
		}
		return $this;
	}

	/**
	 * Processes all immediate inline assets, then clears them from the queue.
	 *
	 * This method scans the `$this->inline_assets` array for any assets that do not have a
	 * `parent_hook` defined, meaning they are intended for immediate processing. It then
	 * calls the abstract `_process_inline_assets` method for each unique parent handle found.
	 * Finally, it removes the processed immediate assets from the `$this->inline_assets` array.
	 *
	 * @return self Returns the instance for method chaining.
	 */
	public function enqueue_inline_assets(string $asset_type): self {
		$logger  = $this->get_logger();
		$context = __TRAIT__ . '::enqueue_inline_' . $asset_type . 's';

		if ( $logger->is_active() ) {
			$logger->debug( "{$context} - Entered method." );
		}

		$immediate_parent_handles = array();
		foreach ( $this->inline_assets[$asset_type] as $key => $inline_asset_data ) {
			if ( ! is_array( $inline_asset_data ) ) {
				if ( $logger->is_active() ) {
					$logger->warning( "{$context} - Invalid inline {$asset_type} data at key '{$key}'. Skipping." );
				}
				continue;
			}
			$parent_hook = $inline_asset_data['parent_hook'] ?? null;
			$handle      = $inline_asset_data['handle']      ?? null;

			if ( empty( $parent_hook ) && ! empty( $handle ) ) {
				if ( ! in_array( $handle, $immediate_parent_handles, true ) ) {
					$immediate_parent_handles[] = $handle;
				}
			}
		}

		if ( empty( $immediate_parent_handles ) ) {
			if ( $logger->is_active() ) {
				$logger->debug( "{$context} - No immediate inline {$asset_type}s found needing processing." );
			}
			return $this;
		}

		if ( $logger->is_active() ) {
			$logger->debug( "{$context} - Found " . count( $immediate_parent_handles ) . " unique parent handle(s) with immediate inline {$asset_type}s to process: " . implode( ', ', array_map( 'esc_html', $immediate_parent_handles ) ) );
		}

		foreach ( $immediate_parent_handles as $parent_handle_to_process ) {
			$this->_process_inline_assets(
				$asset_type,
				$parent_handle_to_process,
				null, // hook_name (null for immediate)
				'enqueue_inline_' . $asset_type . 's' // processing_context
			);
		}

		// Clear the processed immediate inline assets from the main queue.
		$this->inline_assets[$asset_type] = array_filter($this->inline_assets[$asset_type], function($asset) {
			return !empty($asset['parent_hook']);
		});

		if ( $logger->is_active() ) {
			$remaining_count = count($this->inline_assets[$asset_type]);
			$logger->debug("{$context} - Exited. Processed " . count($immediate_parent_handles) . " parent handle(s). Remaining deferred inline {$asset_type}s: {$remaining_count}.");
		}
		return $this;
	}

	/**
	 * Processes inline assets for a given parent handle.
	 *
	 * This method must be implemented by the child trait to handle asset-specific
	 * inline logic (e.g., calling `wp_add_inline_script` vs `wp_add_inline_style`).
	 *
	 * @param string      $_asset_type        The type of asset ('script' or 'style').
	 * @param string      $parent_handle      The handle of the parent asset.
	 * @param string|null $hook_name          The hook context, if any.
	 * @param string      $processing_context A string indicating the calling context for logging.
	 * @return void
	 */
	abstract protected function _process_inline_assets(string $_asset_type, string $parent_handle, ?string $hook_name, string $processing_context): void;

	/**
	 * Processes a single asset definition, handling registration, enqueuing, and data additions.
	 *
	 * This abstract method must be implemented by the consuming trait (e.g., ScriptsEnqueueTrait)
	 * to handle the specific logic for that asset type, such as calling wp_register_script vs.
	 * wp_register_style and handling asset-specific parameters like 'in_footer' for scripts
	 * or 'media' for styles.
	 *
	 * @param array<string, mixed> $asset_definition The definition of the asset to process.
	 * @param string               $processing_context The context (e.g., 'register_assets', 'enqueue_deferred') in which the asset is being processed, used for logging.
	 * @param string|null          $hook_name The name of the hook if the asset is being processed deferred, otherwise null.
	 * @param bool                 $do_register Whether to register the asset with WordPress.
	 * @param bool                 $do_enqueue Whether to enqueue the asset immediately after registration.
	 * @return string|false The handle of the processed asset, or false if a critical error occurred.
	 */
	abstract protected function _process_single_asset(
		string $asset_type,
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
	 * @param string $tag The original HTML asset tag.
	 * @param string $filter_tag_handle The handle of the asset currently being filtered by WordPress.
	 * @param string $asset_handle_to_match The handle of the asset we are targeting for modification.
	 * @param array  $attributes_to_apply The attributes to apply to the asset tag.
	 *
	 * @return string The modified (or original) HTML asset tag.
	 */
	abstract protected function _modify_html_tag_attributes(
		string $_asset_type,
		string $tag,
		string $tag_handle,
		string $handle_to_match,
		array $attributes_to_apply
	): string;
}
