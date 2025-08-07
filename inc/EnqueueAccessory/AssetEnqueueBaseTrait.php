<?php
/**
 * Provides common, asset-agnostic functionality for asset processing.
 *
 * This trait contains the shared helper methods used by the asset-specific traits
 * (ScriptsEnqueueTrait, StylesEnqueueTrait). It provides the foundational layer
 * for asset management using a flattened array structure where each trait instance
 * is responsible for a single asset type.
 *
 * It is not intended to be used directly by a consumer class, but rather as a
 * dependency for the specialized asset handler classes.
 *
 * @todo - Implement the de-registering of assets.
 * @todo - Review input validation approach
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
use Ran\PluginLib\Util\WPWrappersTrait;
use Ran\PluginLib\EnqueueAccessory\AssetType;

/**
 * Trait AssetEnqueueBaseTrait
 *
 * Manages the registration, enqueuing, and processing of assets.
 * This includes handling general assets, inline assets, and deferred assets.
 *
 * @package Ran\PluginLib\EnqueueAccessory
 */
trait AssetEnqueueBaseTrait {
	use WPWrappersTrait;

	/**
	 * Holds all asset definitions registered by this plugin/theme.
	 *
	 * This array contains all script / style assets that have been registered
	 * via the add_asset() method. Each asset is an array with keys for 'handle',
	 * 'src', 'deps', 'ver', and type-specific properties.
	 *
	 * After stage() is called, this array will contain ONLY immediate assets
	 * (those without a 'hook' property) that were successfully registered. All
	 * deferred assets are moved to the $deferred_assets array.
	 *
	 * @var array<string, array<int, array<string, mixed>>>
	 */
	protected array $assets = array();

	/**
	 * Array of assets to be loaded at specific WordPress action hooks, grouped by priority.
	 * These are assets that will be deferred and enqueued only when their specified hook fires.
	 *
	 * This array is populated by the stage() method, which moves all assets with a 'hook' property
	 * from the $assets array. WordPress actions are registered for each hook to handle these assets
	 * at the appropriate time. After the hook is fired, the asset refrence is removed from this array.
	 *
	 * @var array<string, array<string, array<int, array<int, array<string, mixed>>>>>
	 */
	protected array $deferred_assets = array();

	/**
	 * Array of inline assets (CSS/JS code snippets) for external handles, keyed by hook.
	 * These are for attaching inline code to assets registered by WordPress core, other plugins, or themes.
	 * Organized by hook to control when they're processed in the WordPress lifecycle.
	 *
	 * @var array<string, array<string, array<int, array<string, mixed>>>>
	 */
	protected array $external_inline_assets = array();

	/**
	 * Tracks which hooks have had an action registered for external inline assets.
	 *
	 * This property is used to prevent duplicate action registrations for the same hook.
	 * It is only used during the registration phase and is not referenced for any logic afterward.
	 * The key is the hook name and the value is set to true once an action is registered.
	 *
	 * @var array<string, array<string, bool>>
	 */
	protected array $registered_external_hooks = array();

	/**
	 * Holds all registered hooks.
	 *
	 * This property is used to track which hook+priority combinations have already
	 * had WordPress actions registered. It acts as a simple boolean flag to prevent
	 * duplicate action registrations. The key format is '{hook_name}_{priority}' and
	 * the value is set to true once an action is registered. This property is only used
	 * during the registration phase and is not referenced for any logic afterward.
	 *
	 * @var array<string, array<int, array<string, mixed>>>
	 */
	protected array $registered_hooks = array();

	/**
	 * Request-level cache for expensive operations.
	 *
	 * This property provides a simple caching mechanism for expensive operations
	 * that should only be performed once per request, such as block presence detection.
	 * The cache is cleared automatically at the end of each request.
	 *
	 * @var array<string, mixed>
	 */
	protected array $_request_cache = array();

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
	 * Uses the Config's is_dev_environment() method to determine environment:
	 * - First checks for a developer-defined callback (if provided)
	 * - Falls back to SCRIPT_DEBUG constant if no callback is set
	 *
	 * If development environment: prefers the 'dev' URL
	 * If production environment: prefers the 'prod' URL
	 *
	 * Uses request-scoped caching to avoid redundant environment detection and
	 * array processing for the same source configuration within a single request.
	 *
	 * @param string|array $src The source URL(s) for the asset.
	 *
	 * @return string The resolved source URL.
	 */
	protected function _resolve_environment_src(string|array $src): string {
		if (is_string($src)) {
			return $src;
		}

		// Cache the entire environment source resolution for arrays to avoid
		// redundant environment checks and array processing for the same
		// source configuration within a single request.
		$cache_key = 'env_src_' . md5(serialize($src));
		return $this->_cache_for_request($cache_key, function() use ($src) {
			$is_dev = $this->_cache_for_request('is_dev_environment', function() {
				return $this->get_config()->is_dev_environment();
			});

			if ($is_dev && !empty($src['dev'])) {
				return (string) $src['dev'];
			} elseif (!empty($src['prod'])) {
				return (string) $src['prod'];
			}

			// Fallback to the first available URL in the array.
			return (string) reset($src);
		});
	}

	/**
	 * Retrieves the currently registered array of asset definitions and registered hooks.
	 *
	 * @return array<string, array> An associative array of asset definitions and registered hooks.
	 */
	public function get_assets_info(): array {
		return array(
			'assets'                  => $this->assets                    ?? array(),
			'deferred'                => $this->deferred_assets           ?? array(),
			'external_inline'         => $this->external_inline_assets    ?? array(),
            'hooks'          => $this->registered_hooks          ?? array(),
            'external_hooks' => $this->registered_external_hooks ?? array(),
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
	 * @deprecated - functionality not required due to stage() and hook processing
	 * @return string[] An array of unique hook names.
	 */
	public function get_deferred_hooks(): array {
		$hooks = array();

		// Check for hooks in the main assets array for the given type.
		foreach ( ($this->assets['assets'] ?? array()) as $asset ) {
			if ( ! empty( $asset['hook'] ) ) {
				$hooks[] = $asset['hook'];
			}
		}

		// Merge with hooks from already-processed deferred assets for the given type.
		$deferred_hooks = array_keys( $this->deferred_assets ?? array() );

		return array_unique( array_merge( $hooks, $deferred_hooks ) );
	}

	/**
	 * Provides the core logic for adding one or more asset definitions to the internal queue.
	 *
	 * This method is intended to be called by a public-facing wrapper method (e.g., `add_scripts`, `add_styles`)
	 * which provides the necessary context for logging.
	 *
	 * Accepts either a single asset definition or an array of asset definitions.
	 * Single asset definitions are automatically normalized to an array.
	 *
	 * @param array<string, mixed>|array<int, array<string, mixed>> $assets_to_add Single asset definition or array of asset definitions.
	 *								Each definition should contain:
	 *								- 'handle' (string): Unique asset handle (required)
	 *								- 'src' (string|false): Asset source URL or false for registered-only assets (required)
	 *								- 'deps' (array, optional): Array of dependency handles. Default: []
	 *								- 'version' (string|false, optional): Asset version or false to use plugin version. Default: false
	 *								- 'condition' (callable, optional): Condition callback for conditional loading. Default: null (always load)
	 *								- 'hook' (string, optional): WordPress hook for deferred loading. Default: immediate loading
	 *								- 'priority' (int, optional): Hook priority for deferred assets. Default: 10
	 *								- 'replace' (bool, optional): Whether to replace existing asset with same handle. Default: false
	 *								- 'cache_bust' (bool, optional): Whether to add cache busting parameter. Default: false
	 *								- 'inline' (string|array, optional): Inline content to attach to this asset
	 *								- 'attributes' (array, optional): Custom HTML attributes for the asset tag
	 *
	 *								Script-specific properties:
	 *								- 'in_footer' (bool, optional): Whether to load script in footer. Default: false
     *								- 'data' (array, optional): Key-value pairs of data attributes for the `<script>` tag (e.g., `['data-some-attr' => 'value']`). Defaults to an empty array.
	 *
	 *								Style-specific properties:
	 *								- 'media' (string, optional): CSS media attribute. Default: 'all'
	 *
	 * @param AssetType $asset_type The type of asset being added (Script, Style, or Media).
	 * @return self Returns the instance of this class for method chaining.
	 */
	public function add_assets(array $assets_to_add, AssetType $asset_type): self {
		$logger  = $this->get_logger();
		$context = get_class($this) . '::add_' . $asset_type->value . 's';

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
			$handle  = $asset['handle']  ?? null;
			$src     = $asset['src']     ?? null;
			$replace = $asset['replace'] ?? false;

			if (empty($handle)) {
				throw new \InvalidArgumentException("Invalid {$asset_type->value} definition at index {$key}. Asset must have a 'handle'.");
			}

			if ($src !== false && empty($src)) {
				throw new \InvalidArgumentException("Invalid {$asset_type->value} definition for handle '{$handle}'. Asset must have a 'src' or 'src' must be explicitly set to false.");
			}

			// Validate replace flag is boolean
			if ($replace !== false && !is_bool($replace)) {
				throw new \InvalidArgumentException("Invalid {$asset_type->value} definition for handle '{$handle}'. The 'replace' property must be a boolean.");
			}
		}

		if ( $logger->is_active() ) {
			$logger->debug( "{$context} - Entered. Current {$asset_type->value} count: " . count( $this->assets ) . '. Adding ' . count( $assets_to_add ) . " new {$asset_type->value}(s)." );
			foreach ( $assets_to_add as $asset_key => $asset_data ) {
				$handle  = $asset_data['handle'] ?? 'N/A';
				$src_val = $asset_data['src']    ?? 'N/A';
				$src_log = is_array($src_val) ? json_encode($src_val) : $src_val;
				$logger->debug( "{$context} - Adding {$asset_type->value}. Key: {$asset_key}, Handle: {$handle}, src: {$src_log}" );
			}
			$logger->debug( "{$context} - Adding " . count( $assets_to_add ) . " {$asset_type->value} definition(s). Current total: " . count( $this->assets ) );
		}

		// Append new assets to the existing list.
		foreach ( $assets_to_add as $asset_definition ) {
			$this->assets[] = $asset_definition;
		}
		if ( $logger->is_active() ) {
			$new_total = count( $this->assets );
			$logger->debug( "{$context} - Exiting. New total {$asset_type->value} count: {$new_total}" );
			if ( $new_total > 0 ) {
				$current_handles = array_map( static fn( $a ) => $a['handle'] ?? 'N/A', $this->assets );
				$logger->debug( "{$context} - All current {$asset_type->value} handles: " . implode( ', ', $current_handles ) );
			}
		}
		return $this;
	}

	/**
	 * Processes and enqueues all immediate assets, then clears them from the assets array.
	 *
	 * This method iterates through the `$this->assets` array, which at this stage should only
	 * contain non-deferred assets. The `register_assets()` method is responsible for separating
	 * out deferred assets and handling initial registration. This method calls `_process_single_asset`
	 * for each immediate asset to handle the final enqueuing step, and then clears the `$this->assets` array.
	 *
	 * For each immediate asset, it calls `_process_single_asset()` to handle enqueuing and
	 * the processing of any associated inline scripts or attributes.
	 *
	 * Enqueues all immediate assets from the internal queue.
	 *
	 * This method processes assets from the `$this->assets` queue. It is designed to be
	 * robust. If it encounters an asset that has a 'hook' property, it will throw a
	 * `LogicException`, as this indicates that `register_assets()` was not called first
	 * to correctly defer the asset.
	 *
	 * For all other (immediate) assets, this method ensures they are both registered and
	 * enqueued by calling `_process_single_asset()` with both `do_register` and `do_enqueue`
	 * set to `true`. This makes the method safe to call even if `register_assets()` was
	 * skipped for immediate-only assets.
	 *
	 * After processing, this method clears the `$this->assets` array. Deferred assets stored
	 * in `$this->deferred_assets` are not affected.
	 *
	 * @throws \LogicException If a deferred asset is found in the queue, indicating `register_assets()` was not called.
	 * @return self Returns the instance for method chaining.
	 */
	public function enqueue_immediate_assets(AssetType $asset_type): self {
		$logger  = $this->get_logger();
		$context = get_class($this) . '::stage_' . $asset_type->value . 's';

		if ( $logger->is_active() ) {
			$logger->debug( "{$context} - Entered. Processing " . count( $this->assets ) . " {$asset_type->value} definition(s) from internal queue." );
		}

		$assets_to_process = $this->assets;
		$this->assets      = array(); // Clear the main queue, as we are processing all of them now.

		foreach ( $assets_to_process as $index => $asset_definition ) {
			$handle = $asset_definition['handle'] ?? null;

			if ( empty( $handle ) ) {
				if ( $logger->is_active() ) {
					$logger->warning( "{$context} - Skipping asset at index {$index} due to missing handle - this should not be possible when using add()." );
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
			$deferred_count = empty($this->deferred_assets) ? 0 : array_sum(array_map('count', $this->deferred_assets));
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
		$context = get_class($this) . '::_enqueue_deferred_' . $asset_type->value . 's';

		if ( $logger->is_active() ) {
			$logger->debug( "{$context} - Entered hook: \"{$hook_name}\" with priority: {$priority}." );
		}

		// Check if there are any assets for this specific hook and priority.
		if ( ! isset( $this->deferred_assets[ $hook_name ][ $priority ] ) ) {
			if ( $logger->is_active() ) {
				$logger->debug( "{$context} - Hook \"{$hook_name}\" with priority {$priority} not found in deferred {$asset_type->value}s. Exiting - nothing to process." );
			}
			// If the hook itself is now empty (i.e., it had no priorities, or the last one was just processed),
			// remove it to keep the deferred assets array clean.
			if ( isset( $this->deferred_assets[ $hook_name ] ) && empty( $this->deferred_assets[ $hook_name ] ) ) {
				unset( $this->deferred_assets[ $hook_name ] );
			}
			return;
		}

		// Retrieve the assets for this specific hook and priority.
		$assets_to_process = $this->deferred_assets[ $hook_name ][ $priority ];

		// Process each asset.
		foreach ( $assets_to_process as $asset_definition ) {
			if ( $logger->is_active() ) {
				$handle = is_array( $asset_definition ) ? $asset_definition['handle'] : $asset_definition;
				$logger->debug( "{$context} - Processing deferred asset '{$handle}'." );
			}
			$this->_process_single_asset( $asset_type, $asset_definition, $context, $hook_name, true, true );
		}

		// Once processed, remove this priority's assets to prevent re-processing.
		unset( $this->deferred_assets[ $hook_name ][ $priority ] );

		// If this was the last priority for this hook, clean up the hook key as well.
		if ( empty( $this->deferred_assets[ $hook_name ] ) ) {
			unset( $this->deferred_assets[ $hook_name ] );
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
		$context = get_class($this) . '::add_inline_' . $asset_type->value . 's';

		// If a single asset definition is passed (detected by the presence of a 'parent_handle' key),
		// wrap it in an array to handle it uniformly.
		if ( isset( $inline_assets_to_add['parent_handle'] ) ) {
			$inline_assets_to_add = array( $inline_assets_to_add );
		}

		$count = count( $inline_assets_to_add );
		if ( $logger->is_active() ) {
			$current_total = count( $this->external_inline_assets, COUNT_RECURSIVE ) - count( $this->external_inline_assets );
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
		$context          = get_class($this) . '::add_inline_' . $asset_type->value . 's';
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
		foreach ( $this->assets as &$asset ) {
			if ( isset( $asset['handle'] ) && $asset['handle'] === $parent_handle ) {
				$asset['inline'][] = $inline_asset_definition;
				if ( $parent_hook && $logger->is_active() ) {
					$logger->warning( "{$context} - A 'parent_hook' was provided for '{$parent_handle}', but it's ignored as the parent was found internally in the immediate queue." );
				}
				return; // Found and attached.
			}
		}

		// Step 2: Search the deferred assets queue.
		foreach ( $this->deferred_assets as $hook => &$priorities ) {
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
		$this->external_inline_assets[$hook][$parent_handle][] = $inline_asset_definition;

		// Register the action to enqueue the external inline assets, but only once per hook.
		if ( ! isset( $this->registered_external_hooks[$hook] ) ) {
			// add_action() with a direct method reference approach is PREFERRED here because:
			// 1. No additional parameters need to be passed to the callback - unlike in
			//    AssetEnqueueBaseAbstract where we need to pass $hook_name and $priority
			// 2. The method name is deterministic and constructed at runtime
			//    (enqueue_external_inline_scripts or enqueue_external_inline_styles)
			// 3. The method definitely exists in this class - no safety check needed
			// 4. Direct method references are more efficient than closures when no additional
			//    parameters need to be passed
			$enqueue_method = 'enqueue_external_inline_' . $asset_type->value . 's';

			// Using a hardcoded value of 11 as we want inline assets to be added after all other hooks.
			$this->_do_add_action( $hook, array( $this, $enqueue_method ), 11 );
			$this->registered_external_hooks[$hook] = true;
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
		$context          = get_class($this) . '::enqueue_external_inline_' . $asset_type_value . 's';

		if ( $logger->is_active() ) {
			$logger->debug("{$context} - Fired on hook '{$hook_name}'.");
		}

		$assets_for_hook = $this->external_inline_assets[ $hook_name ] ?? array();

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
				'external'
			);
		}

		// Note: We don't need to remove processed assets here as the _process_external_inline_assets method
		// already handles cleanup of the $external_inline_assets array

		if ( $logger->is_active() ) {
			$logger->debug("{$context} - Finished processing for hook '{$hook_name}'.");
		}
	}

	/**
	 * Process inline assets for a parent asset.
	 *
	 * Process inline assets associated with a specific parent asset handle and hook context.
	 * This method handles both script and style inline assets with appropriate conditional logic.
	 * It processes inline assets from their actual storage locations: $assets, $deferred_assets, or $external_inline_assets.
	 *
	 * @param AssetType   $asset_type        The type of asset (script or style).
	 * @param string      $parent_handle     The handle of the parent asset.
	 * @param string|null $hook_name         (Optional) The hook name if processing for a deferred context.
	 * @param string      $processing_context A string indicating the context for logging purposes.
	 * @return void
	 */
	protected function _process_inline_assets(
		AssetType $asset_type,
		string $parent_handle,
		?string $hook_name = null,
		string $processing_context = 'immediate'
	): void {
		$logger  = $this->get_logger();
		$context = get_class($this) . '::' . __METHOD__ . " (context: {$processing_context}) - ";

		$logger->debug("{$context}Checking for inline {$asset_type->value}s for parent {$asset_type->value} '{$parent_handle}'" . ($hook_name ? " on hook '{$hook_name}'." : '.'));

		// Check if the parent asset is registered or enqueued before processing its inline assets
		$is_registered_function = $asset_type === AssetType::Script ? 'wp_script_is' : 'wp_style_is';
		if (!$is_registered_function($parent_handle, 'registered') && !$is_registered_function($parent_handle, 'enqueued')) {
			$logger->error("{$context}Cannot add inline {$asset_type->value}s. Parent {$asset_type->value} '{$parent_handle}' is not registered or enqueued.");
			return;
		}

		$processed_count = 0;

		// Case 1: Check for external inline assets
		if ($hook_name && isset($this->external_inline_assets[$hook_name][$parent_handle])) {
			$processed_count += $this->_process_external_inline_assets(
				$asset_type,
				$parent_handle,
				$hook_name,
				$context
			);
		}
		// Case 2: Check for immediate assets
		elseif (!$hook_name) {
			$processed_count += $this->_process_immediate_inline_assets(
				$asset_type,
				$parent_handle,
				$context
			);
		}
		// Case 3: Check for deferred assets
		else {
			$processed_count += $this->_process_deferred_inline_assets(
				$asset_type,
				$parent_handle,
				$hook_name,
				$context
			);
		}

		if ($processed_count === 0) {
			$logger->debug("{$context}No inline {$asset_type->value} found or processed for '{$parent_handle}'" . ($hook_name ? " on hook '{$hook_name}'." : '.'));
		}
	}


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
		$context = get_class($this) . '::' . __METHOD__;

		// Resolve environment-specific source URL (dev vs. prod) if src is provided.
		if (!empty($asset_definition['src'])) {
			$asset_definition['src'] = $this->_resolve_environment_src($asset_definition['src']);
		}

		$handle    = $asset_definition['handle'] ?? null;
		$src       = $asset_definition['src']    ?? null; // resolved
		$deps      = $asset_definition['deps']   ?? array();
		$ver       = $this->_generate_asset_version($asset_definition);
		$ver       = (false === $ver) ? null : $ver;
		$condition = $asset_definition['condition'] ?? null;
		$replace   = $asset_definition['replace']   ?? false;

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

		// Check if we need to deregister an existing asset before registering
		if ($replace && $do_register) {
			if ($logger->is_active()) {
				$logger->debug("{$context} - Asset '{$handle}' has replace flag set to true. Attempting to deregister existing asset.");
			}

			// Use the same helper method chain as the deregister() method for consistency
			$this->_deregister_assets(
				array('handle' => $handle, 'immediate' => true), // Use immediate deregistration for replace flag
				$asset_type
			);
		}

		// Standard handling for non-deferred assets
		$this->_do_register(
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
			$enqueue_result = $this->_do_enqueue(
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
	protected function _do_register(
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
			// Handle script modules - we can't directly check registration status
			// Unified approach: determine functions based on asset type
			if ($asset_type === AssetType::Script) {
				$is_registered_fn = 'wp_script_is';
			} elseif ($asset_type === AssetType::ScriptModule) {
				$is_registered_fn = array($this, '_module_is');
			} else { // AssetType::Style
				$is_registered_fn = 'wp_style_is';
			}

			// Check if already registered using unified approach
			$is_registered = call_user_func($is_registered_fn, $handle, 'registered');

			if ($is_registered) {
				if ($logger->is_active()) {
					$logger->debug("{$context} - {$asset_type->value} '{$handle}'{$log_hook_context} already registered. Skipping registration.");
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
					$result = \wp_register_script($handle, $src, $deps, $ver, array('in_footer' => $in_footer));
				} elseif ($asset_type === AssetType::ScriptModule) {
					$result = \wp_register_script_module($handle, $src, $deps, $ver);
					// Track registration in internal registry
					if ($result && !isset($this->_script_module_registry)) {
						$this->_script_module_registry = array('registered' => array(), 'enqueued' => array());
					}
					if ($result) {
						$this->_script_module_registry['registered'][] = $handle;
					}
				} else { // AssetType::Style
					// For styles, $extra_args would be $media
					$media  = $extra_args['media'] ?? 'all';
					$result = \wp_register_style($handle, $src, $deps, $ver, $media);
				}

				if (!$result) {
					if ($logger->is_active()) {
						$function_name = $asset_type === AssetType::ScriptModule ? 'wp_register_script_module' : "wp_register_{$asset_type->value}";
						$logger->warning("{$context} - {$function_name}() failed for handle '{$handle}'{$log_hook_context}. Skipping further processing for this asset.");
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
	protected function _do_enqueue(
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

		$logger = $this->get_logger();

		// Determine functions based on asset type
		if ($asset_type === AssetType::Script) {
			$is_enqueued_fn   = 'wp_script_is';
			$is_registered_fn = 'wp_script_is';
			$enqueue_fn       = 'wp_enqueue_script';
		} elseif ($asset_type === AssetType::ScriptModule) {
			$is_enqueued_fn   = array($this, '_module_is');
			$is_registered_fn = array($this, '_module_is');
			$enqueue_fn       = 'wp_enqueue_script_module';
		} else { // AssetType::Style
			$is_enqueued_fn   = 'wp_style_is';
			$is_registered_fn = 'wp_style_is';
			$enqueue_fn       = 'wp_enqueue_style';
		}

		// Check if already enqueued using unified approach
		$is_enqueued = call_user_func($is_enqueued_fn, $handle, 'enqueued');

		if ($is_enqueued) {
			if ($logger->is_active()) {
				$logger->debug("{$context} - {$asset_type->value} '{$handle}'{$log_hook_context} already enqueued. Skipping.");
			}
			return true;
		}

		// For deferred assets that are being processed during their hook, we don't want to auto-register if not registered
		// This prevents double registration since these assets will be explicitly registered above
		$skip_auto_registration = $is_deferred && $hook_name !== null;

		// Check if registered using unified approach
		$is_registered = call_user_func($is_registered_fn, $handle, 'registered');

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
				$register_result = \wp_register_script($handle, $src, $deps, $ver, $extra_args);
			} elseif ($asset_type === AssetType::ScriptModule) {
				$register_result = \wp_register_script_module($handle, $src, $deps, $ver);
			} else { // AssetType::Style
				$register_result = \wp_register_style($handle, $src, $deps, $ver, $extra_args);
			}

			if (!$register_result) {
				return false;
			}
		}

		// Asset is now registered, enqueue it
		if ($logger->is_active()) {
			$logger->debug("{$context} - Enqueuing {$asset_type->value} '{$handle}'{$log_hook_context}.");
		}

		// Enqueue using unified approach
		$enqueue_fn($handle);
		$enqueue_result = call_user_func($is_enqueued_fn, $handle, 'enqueued');

		if (!$enqueue_result) {
			if ($logger->is_active()) {
				$logger->warning(
					sprintf(
						"%s - wp_enqueue_%s() failed for handle '%s'%s. Asset was registered but not enqueued.",
						$context,
						$asset_type->value,
						$handle,
						$log_hook_context
					)
				);
			}
			return false;
		}

		// Track enqueue success for script modules
		if ($asset_type === AssetType::ScriptModule) {
			if (!isset($this->_script_module_registry)) {
				$this->_script_module_registry = array('registered' => array(), 'enqueued' => array());
			}
			if (!in_array($handle, $this->_script_module_registry['enqueued'], true)) {
				$this->_script_module_registry['enqueued'][] = $handle;
			}
		}

		return true;
	}

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
		$context = get_class($this) . '::' . __METHOD__;

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

		// If src is an array with 'dev' and 'prod' keys, resolve it to a string URL
		if (is_array($src) && (isset($src['dev']) || isset($src['prod']))) {
			$src = $this->_resolve_environment_src($src, $handle);
			if ($logger->is_active()) {
				$logger->debug("{$context} - Resolved environment-specific src for '{$handle}' to '{$src}'.");
			}
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
			}
			return $version;
		}

		$cache_key = 'file_hash_' . md5($file_path);
		$hash      = $this->_cache_for_request($cache_key, function() use ($file_path) {
			return $this->_md5_file($file_path);
		});

		return $hash ? substr($hash, 0, 10) : $version;
	}

	/**
	 * Resolves a URL to a physical file path on the server.
	 *
	 * This is a helper for the cache-busting mechanism to locate the file and read its content.
	 * It needs to handle various URL formats (plugin-relative, theme-relative, absolute).
	 *
	 * Uses request-scoped caching to avoid redundant string operations for the same URL
	 * within a single request, improving performance when multiple assets reference the
	 * same URL or when cache-busting processes the same asset multiple times.
	 *
	 * @param string $url The asset URL to resolve.
	 * @return string|false The absolute file path, or false if resolution fails.
	 */
	protected function _resolve_url_to_path(string $url): string|false {
		// Cache the entire URL-to-path resolution to avoid redundant string operations
		// for the same URL within a single request. This is safe because URL-to-path
		// mappings are static during a request and the cache is cleared at request end.
		$cache_key = 'url_to_path_' . md5($url);
		return $this->_cache_for_request($cache_key, function() use ($url) {
			$logger  = $this->get_logger();
			$context = get_class($this) . '::_resolve_url_to_path';

			// Use content_url() and WP_CONTENT_DIR for robust path resolution in
			// single and multisite environments.
			$content_url = $this->_cache_for_request('content_url', function() {
				return \content_url();
			});
			$content_dir = \WP_CONTENT_DIR;

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
			$site_url = $this->_cache_for_request('site_url', function() {
				return \site_url();
			});
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
		});
	}



	// ------------------------------------------------------------------------
	// FILESYSTEM WRAPPERS FOR TESTABILITY
	// ------------------------------------------------------------------------

	/**
	 * Wraps the native file_exists function to allow for mocking in tests.
	 *
	 * Uses request-scoped caching to avoid redundant filesystem calls for the same
	 * path within a single request. This is safe because files don't appear or
	 * disappear during a single request, and the cache is cleared at request end.
	 *
	 * @param string $path The file path to check.
	 *
	 * @return bool True if the file exists, false otherwise.
	 */
	protected function _file_exists(string $path): bool {
		// Cache file existence checks to avoid redundant filesystem calls
		// for the same path within a single request. This is safe because
		// files don't appear/disappear during a request lifecycle.
		$cache_key = 'file_exists_' . md5($path);
		return $this->_cache_for_request($cache_key, function() use ($path) {
			return file_exists($path);
		});
	}

	/**
	 * Process attributes and build attribute string for HTML tags.
	 *
	 * @param array     $attributes_to_apply Attributes to process and apply
	 * @param array     $managed_attributes   List of attribute names that should not be overridden
	 * @param string    $context             Logging context (typically trait::method)
	 * @param string    $handle_to_match     Asset handle for logging
	 * @param AssetType $asset_type          Asset type for logging
	 * @param array     $special_attributes  Optional map of attributes with special handling, with callbacks
	 * @return string   Attribute string ready to insert into HTML tag
	 */
	protected function _build_attribute_string(
		array $attributes_to_apply,
		array $managed_attributes,
		string $context,
		string $handle_to_match,
		AssetType $asset_type,
		array $special_attributes = array()
	): string {
		$logger   = $this->get_logger();
		$attr_str = '';

		foreach ($attributes_to_apply as $attr => $value) {
			// Handle boolean attributes (indexed array, e.g., ['async'])
			if (is_int($attr)) {
				$attr  = $value;
				$value = true;
			}

			$attr_lower = strtolower((string) $attr);

			// Check for special attribute handling
			if (isset($special_attributes[$attr_lower])) {
				$result = call_user_func($special_attributes[$attr_lower], $attr_lower, $value);
				if ($result === false) {
					continue; // Skip this attribute
				}
			}

			// Check for attempts to override managed attributes
			if (in_array($attr_lower, $managed_attributes, true)) {
				if ($logger->is_active()) {
					$logger->warning(
						sprintf(
							"%s - Attempt to override managed attribute '%s' for %s handle '%s'. This attribute will be ignored.",
							$context,
							$asset_type->value,
							$attr_lower,
							$handle_to_match
						),
						array(
							'handle'    => $handle_to_match,
							'attribute' => $attr_lower,
						)
					);
				}
				continue; // Skip this attribute
			}

			// Boolean attributes (value is true)
			if (true === $value) {
				$attr_str .= ' ' . esc_attr($attr_lower);
			} elseif (false !== $value && null !== $value && '' !== $value) {
				// Regular attributes with non-empty, non-false, non-null values
				$attr_str .= ' ' . esc_attr($attr_lower) . '="' . esc_attr((string) $value) . '"';
			}
			// Attributes with false, null, or empty string values are skipped
		}

		return $attr_str;
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
	 * Caches the result of an expensive operation for the duration of the current request.
	 *
	 * This method provides a simple caching mechanism for expensive operations that should
	 * only be performed once per request, such as block presence detection or file system
	 * operations. The cache is automatically cleared at the end of each request.
	 *
	 * @param string   $key      The cache key to use for storing/retrieving the result.
	 * @param callable $callback The callback function to execute if the result is not cached.
	 *
	 * @return mixed The cached result or the result of executing the callback.
	 */
	protected function _cache_for_request(string $key, callable $callback) {
		if (!isset($this->_request_cache[$key])) {
			$this->_request_cache[$key] = $callback();
		}
		return $this->_request_cache[$key];
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
	 * Process external inline assets for a specific parent handle and hook.
	 *
	 * @param AssetType $asset_type    The type of asset (script or style).
	 * @param string    $parent_handle The handle of the parent asset.
	 * @param string    $hook_name     The hook name.
	 * @param string    $context       Context for logging.
	 * @return int Number of processed assets.
	 */
	private function _process_external_inline_assets(
		AssetType $asset_type,
		string $parent_handle,
		string $hook_name,
		string $context
	): int {
		$logger          = $this->get_logger();
		$processed_count = 0;

		if (!isset($this->external_inline_assets[$hook_name][$parent_handle])) {
			return $processed_count;
		}

		$inline_assets = $this->external_inline_assets[$hook_name][$parent_handle];

		foreach ($inline_assets as $key => $inline_asset) {
			$content   = $inline_asset['content']   ?? '';
			$condition = $inline_asset['condition'] ?? null;
			$position  = $asset_type === AssetType::Script ? ($inline_asset['position'] ?? 'after') : null;

			// Skip if condition is false
			if (is_callable($condition) && !$condition()) {
				$logger->debug("{$context}Condition false for external inline {$asset_type->value} targeting '{$parent_handle}'");
				continue;
			}

			// Skip if content is empty
			if (empty($content)) {
				$logger->warning("{$context}Empty content for external inline {$asset_type->value} targeting '{$parent_handle}'. Skipping.");
				continue;
			}

			$logger->debug("{$context}Adding external inline {$asset_type->value} for '{$parent_handle}'" .
				($asset_type === AssetType::Script ? ", position: {$position}" : ''));

			$add_inline_function = $asset_type === AssetType::Script ? 'wp_add_inline_script' : 'wp_add_inline_style';
			$result              = $asset_type === AssetType::Script
				? $add_inline_function($parent_handle, $content, $position)
				: $add_inline_function($parent_handle, $content);

			if ($result) {
				$logger->debug("{$context}Successfully added external inline {$asset_type->value} for '{$parent_handle}'.");
				$processed_count++;
			} else {
				$logger->warning("{$context}Failed to add external inline {$asset_type->value} for '{$parent_handle}'.");
			}
		}

		// Remove all processed assets for this handle
		unset($this->external_inline_assets[$hook_name][$parent_handle]);

		// Clean up empty hook entries
		if (empty($this->external_inline_assets[$hook_name])) {
			unset($this->external_inline_assets[$hook_name]);
		}

		return $processed_count;
	}

	/**
	 * Process immediate inline assets for a specific parent handle.
	 *
	 * @param AssetType $asset_type    The type of asset (script or style).
	 * @param string    $parent_handle The handle of the parent asset.
	 * @param string    $context       Context for logging.
	 * @return int Number of processed assets.
	 */
	private function _process_immediate_inline_assets(
		AssetType $asset_type,
		string $parent_handle,
		string $context
	): int {
		$logger          = $this->get_logger();
		$processed_count = 0;

		// Find the parent asset in the assets array
		foreach ($this->assets as $key => $asset) {
			if ($asset['handle'] === $parent_handle && $asset['type'] === $asset_type) {
				// Check if this asset has inline assets
				if (!empty($asset['inline'])) {
					$logger->debug("{$context}Found immediate inline {$asset_type->value}s for '{$parent_handle}'.");

					// Process each inline asset
					foreach ($asset['inline'] as $inline_key => $inline_asset) {
						$content   = $inline_asset['content']   ?? '';
						$condition = $inline_asset['condition'] ?? null;
						$position  = $asset_type === AssetType::Script ? ($inline_asset['position'] ?? 'after') : null;

						// Skip if condition is false
						if (is_callable($condition) && !$condition()) {
							$logger->debug("{$context}Condition false for immediate inline {$asset_type->value} targeting '{$parent_handle}'");
							continue;
						}

						// Skip if content is empty
						if (empty($content)) {
							$logger->warning("{$context}Empty content for immediate inline {$asset_type->value} targeting '{$parent_handle}'. Skipping.");
							continue;
						}

						$logger->debug("{$context}Adding immediate inline {$asset_type->value} for '{$parent_handle}'" .
							($asset_type === AssetType::Script ? ", position: {$position}" : ''));

						$add_inline_function = $asset_type === AssetType::Script ? 'wp_add_inline_script' : 'wp_add_inline_style';
						$result              = $asset_type === AssetType::Script
							? $add_inline_function($parent_handle, $content, $position)
							: $add_inline_function($parent_handle, $content);

						if ($result) {
							$logger->debug("{$context}Successfully added immediate inline {$asset_type->value} for '{$parent_handle}'.");
							$processed_count++;
						} else {
							$logger->warning("{$context}Failed to add immediate inline {$asset_type->value} for '{$parent_handle}'.");
						}
					}

					// Remove all processed inline assets
					unset($this->assets[$key]['inline']);
				}

				// We found the asset, no need to continue searching
				break;
			}
		}

		return $processed_count;
	}

	/**
	 * Common helper for handling asset operations (dequeue, deregister, or both).
	 *
	 * This method provides a unified interface for dequeue and deregister operations,
	 * eliminating code duplication between the specific operation methods.
	 *
	 * @param string    $handle          The handle of the asset to operate on.
	 * @param string    $context         Context for logging.
	 * @param AssetType $asset_type      The type of asset (script, style, or script module).
	 * @param string    $operation       The operation to perform: 'dequeue', 'deregister', or 'remove'.
	 * @param array     $asset_locations Optional. Asset locations for cleanup (used for remove operation).
	 * @return bool True if the operation was successful, false otherwise.
	 */
	protected function _handle_asset_operation(
		string $handle,
		string $context,
		AssetType $asset_type,
		string $operation,
		array $asset_locations = array()
	): bool {
		$logger  = $this->get_logger();
		$context = __TRAIT__ . '::' . __FUNCTION__ . '(' . $asset_type->value . ') called from ' . $context;

		if ($logger->is_active()) {
			$logger->debug("{$context} - Attempting to {$operation} existing '{$handle}'.");
		}

		// For remove operation, check if asset exists in internal queues and get locations
		if ($operation === 'remove' && empty($asset_locations)) {
			$asset_locations = $this->_asset_exists_in_internal_queues($handle, $asset_type);
			if (!empty($asset_locations) && $logger->is_active()) {
				$logger->debug("{$context} - Asset '{$handle}' found in internal queues at " . count($asset_locations) . ' location(s). Will clean up after removal.');
			}
		}

		$success            = true;
		$initial_registered = false;
		$initial_enqueued   = false;

		// Determine which operations to perform
		$should_dequeue    = in_array($operation, array('dequeue', 'remove'));
		$should_deregister = in_array($operation, array('deregister', 'remove'));

		// Check initial status (unified for all asset types)
		if ($asset_type === AssetType::Script) {
			$initial_registered = wp_script_is($handle, 'registered');
			$initial_enqueued   = wp_script_is($handle, 'enqueued');
		} elseif ($asset_type === AssetType::ScriptModule) {
			$initial_registered = $this->_module_is($handle, 'registered');
			$initial_enqueued   = $this->_module_is($handle, 'enqueued');
		} else { // Style
			$initial_registered = wp_style_is($handle, 'registered');
			$initial_enqueued   = wp_style_is($handle, 'enqueued');
		}

		// Attempt dequeue if requested (WordPress handles missing assets gracefully)
		if ($should_dequeue) {
			if ($asset_type === AssetType::Script) {
				wp_dequeue_script($handle);
			} elseif ($asset_type === AssetType::ScriptModule) {
				wp_dequeue_script_module($handle);
				// Update internal registry to track dequeue
				if (!isset($this->_script_module_registry)) {
					$this->_script_module_registry = array('registered' => array(), 'enqueued' => array());
				}
				$this->_script_module_registry['enqueued'] = array_diff($this->_script_module_registry['enqueued'], array($handle));
			} else { // Style
				wp_dequeue_style($handle);
			}

			// Verify dequeue success using unified status checking
			if ($initial_enqueued) {
				// Use unified status checking approach
				if ($asset_type === AssetType::Script) {
					$still_enqueued = wp_script_is($handle, 'enqueued');
				} elseif ($asset_type === AssetType::ScriptModule) {
					$still_enqueued = $this->_module_is($handle, 'enqueued');
				} else { // Style
					$still_enqueued = wp_style_is($handle, 'enqueued');
				}

				if ($still_enqueued) {
					$success = false;
					if ($logger->is_active()) {
						$logger->warning("{$context} - Failed to dequeue '{$handle}'. It may be protected or re-enqueued by another plugin.");
					}
				} else {
					if ($logger->is_active()) {
						$logger->debug("{$context} - Successfully dequeued '{$handle}'.");
					}
				}
			} elseif ($logger->is_active()) {
				$logger->debug("{$context} - '{$handle}' was not enqueued. Nothing to dequeue.");
			}
		}

		// Attempt deregister if requested (WordPress handles missing assets gracefully)
		if ($should_deregister) {
			if ($asset_type === AssetType::Script) {
				wp_deregister_script($handle);
			} elseif ($asset_type === AssetType::ScriptModule) {
				wp_deregister_script_module($handle);
				// Update internal registry to track deregister
				if (!isset($this->_script_module_registry)) {
					$this->_script_module_registry = array('registered' => array(), 'enqueued' => array());
				}
				$this->_script_module_registry['registered'] = array_diff($this->_script_module_registry['registered'], array($handle));
				$this->_script_module_registry['enqueued']   = array_diff($this->_script_module_registry['enqueued'], array($handle));
			} else { // Style
				wp_deregister_style($handle);
			}

			// Verify deregister success using unified status checking
			if ($initial_registered) {
				// Use unified status checking approach
				if ($asset_type === AssetType::Script) {
					$still_registered = wp_script_is($handle, 'registered');
				} elseif ($asset_type === AssetType::ScriptModule) {
					$still_registered = $this->_module_is($handle, 'registered');
				} else { // Style
					$still_registered = wp_style_is($handle, 'registered');
				}

				if ($still_registered) {
					$success = false;
					if ($logger->is_active()) {
						$logger->warning("{$context} - Failed to deregister '{$handle}'. It may be a protected WordPress core {$asset_type->value} or re-registered by another plugin.");
					}
				} else {
					if ($logger->is_active()) {
						$logger->debug("{$context} - Successfully deregistered '{$handle}'.");
					}
				}
			} elseif ($logger->is_active()) {
				$logger->debug("{$context} - '{$handle}' was not registered. Nothing to deregister.");
			}
		}

		// Handle comprehensive status logging (unified for all asset types)
		if (!$initial_registered && !$initial_enqueued) {
			if ($logger->is_active()) {
				$logger->debug("{$context} - '{$handle}' was not registered or enqueued. Nothing to {$operation}.");
			}
		} elseif ($success) {
			if ($logger->is_active()) {
				$operation_text = $should_dequeue && $should_deregister ? 'removal' : $operation;
				$logger->debug("{$context} - Successfully completed {$operation_text} of '{$handle}'.");
			}
		} else {
			if ($logger->is_active()) {
				$operation_text = $should_dequeue && $should_deregister ? 'removal' : $operation;
				$logger->warning("{$context} - {$operation_text} of '{$handle}' was only partially successful.");
			}
		}

		// Handle internal cleanup for remove operation only
		if ($operation === 'remove' && !empty($asset_locations)) {
			$this->_clean_deferred_asset_queues($handle, $asset_type, $asset_locations);
		}

		return $success;
	}

	/**
	 * Deregisters one or more assets from WordPress.
	 *
	 * This method supports flexible input formats:
	 * - A single string handle
	 * - An array of string handles
	 * - An array of asset definition arrays with optional hook and priority
	 * - A mixed array of strings and asset definition arrays
	 *
	 * @param string|array<string|array> $assets_to_deregister Assets to deregister.
	 * @param AssetType $asset_type The type of asset (script or style).
	 * @return self Returns the instance of this class for method chaining.
	 */
	protected function _deregister_assets($assets_to_deregister, AssetType $asset_type): self {
		$logger  = $this->get_logger();
		$context = __TRAIT__ . '::' . __FUNCTION__;

		// Normalize input to array of asset definitions
		$normalized_assets = $this->_normalize_asset_input($assets_to_deregister);

		if ($logger->is_active()) {
			$count = count($normalized_assets);
			$logger->debug("{$context} - Processing {$count} {$asset_type->value} deregistration(s).");
		}

		foreach ($normalized_assets as $index => $asset_definition) {
			$this->_process_single_deregistration($asset_definition, $asset_type);
		}

		return $this;
	}

	/**
	 * Removes one or more assets from WordPress by both dequeuing and deregistering them.
	 *
	 * This method supports flexible input formats:
	 * - A single string handle
	 * - An array of string handles
	 * - An array of asset definition arrays with optional hook and priority
	 * - A mixed array of strings and asset definition arrays
	 *
	 * @param string|array<string|array> $assets_to_remove Assets to remove.
	 * @param AssetType $asset_type The type of asset (script, style, or script module).
	 * @return self Returns the instance of this class for method chaining.
	 */
	protected function _remove_assets($assets_to_remove, AssetType $asset_type): self {
		$logger  = $this->get_logger();
		$context = get_class($this) . '::' . __METHOD__ . ' (' . strtolower($asset_type->value) . 's)';

		if ($logger->is_active()) {
			$logger->debug($context . ' - Entered. Processing removal request.');
		}

		// Normalize input to standard format
		$normalized_assets = $this->_normalize_asset_input($assets_to_remove);

		foreach ($normalized_assets as $asset_definition) {
			$this->_process_single_removal($asset_definition, $asset_type);
		}

		return $this;
	}

	/**
	 * Validates that a handle is not empty and logs a warning if it is.
	 *
	 * @param string $handle The handle to validate.
	 * @param string $context The context for logging.
	 * @param string $location_description Description of where the empty handle was found.
	 * @return bool True if handle is valid (not empty), false if empty.
	 */
	private function _is_valid_handle(string $handle, string $context, string $location_description): bool {
		if (empty($handle)) {
			$logger = $this->get_logger();
			if ($logger->is_active()) {
				$logger->warning("{$context} - Skipping {$location_description}.");
			}
			return false;
		}
		return true;
	}

	/**
	 * Normalizes asset input to a standard array format for all asset operations.
	 *
	 * This method handles flexible input formats for dequeue, deregister, and remove operations:
	 * - Single string handle
	 * - Single asset definition array
	 * - Array of string handles
	 * - Array of asset definition arrays
	 * - Mixed arrays of strings and definitions
	 *
	 * @param mixed $input The input to normalize.
	 * @return array An array of normalized asset definitions.
	 * @throws \InvalidArgumentException If the input format is invalid.
	 */
	protected function _normalize_asset_input($input): array {
		$logger     = $this->get_logger();
		$context    = __TRAIT__ . '::' . __FUNCTION__;
		$normalized = array();

		// Single string handle
		if (is_string($input)) {
			if (!$this->_is_valid_handle($input, $context, 'empty handle')) {
				return array();
			}
			return array(array('handle' => $input));
		}

		// Array input
		if (is_array($input)) {
			// Check if it's an associative array (single asset definition)
			if (isset($input['handle'])) {
				if (!$this->_is_valid_handle($input['handle'], $context, 'asset definition with empty handle')) {
					return array();
				}
				return array($input);
			}

			// Process array of mixed types
			foreach ($input as $index => $item) {
				if (is_string($item)) {
					// String handle - validate using helper
					if ($this->_is_valid_handle($item, $context, "empty handle at index {$index}")) {
						$normalized[] = array('handle' => $item);
					}
				} elseif (is_array($item) && isset($item['handle']) && is_string($item['handle'])) {
					// Asset definition array - validate handle using helper
					if ($this->_is_valid_handle($item['handle'], $context, "asset definition with empty handle at index {$index}")) {
						$normalized[] = $item;
					}
				} else {
					// Invalid item
					if ($logger->is_active()) {
						$type = gettype($item);
						$logger->warning("{$context} - Invalid input type at index {$index}. Expected string or array, got {$type}.");
					}
				}
			}

			return $normalized;
		}

		// Invalid input type
		$type = gettype($input);
		throw new \InvalidArgumentException("Invalid input for asset operation. Expected string, array of strings, or array of asset definitions, got {$type}.");
	}

	/**
	 * Processes a single asset deregistration.
	 *
	 * @param array $asset_definition The asset definition to process.
	 * @param AssetType $asset_type The type of asset (script or style).
	 * @return void
	 * @throws \InvalidArgumentException If the asset definition is invalid.
	 */
	protected function _process_single_deregistration(array $asset_definition, AssetType $asset_type): void {
		$logger  = $this->get_logger();
		$context = __TRAIT__ . '::' . __FUNCTION__;

		// Validate handle
		if (!isset($asset_definition['handle']) || !is_string($asset_definition['handle']) || empty($asset_definition['handle'])) {
			if ($logger->is_active()) {
				$logger->warning("{$context} - Invalid {$asset_type->value} configuration. A 'handle' is required and must be a string.");
			}
			return;
		}

		$handle    = $asset_definition['handle'];
		$hook      = $asset_definition['hook']      ?? 'wp_enqueue_scripts';
		$priority  = $asset_definition['priority']  ?? 10;
		$immediate = $asset_definition['immediate'] ?? false;

		// Get the trait name for logging
		$trait_name = match ($asset_type) {
			AssetType::Script       => 'ScriptsEnqueueTrait',
			AssetType::ScriptModule => 'ScriptModulesEnqueueTrait',
			AssetType::Style        => 'StylesEnqueueTrait',
			default                 => 'UnknownEnqueueTrait'
		};

		if ($immediate) {
			// Immediate deregistration
			$this->_handle_asset_operation($handle, __FUNCTION__, $asset_type, 'deregister');
			if ($logger->is_active()) {
				$logger->debug("{$trait_name}::deregister - Immediately deregistered {$asset_type->value} '{$handle}'.");
			}
				} else {
		// Deferred deregistration via hook
		$this->_do_add_action($hook, function() use ($handle, $asset_type) {
				$this->_handle_asset_operation($handle, __FUNCTION__, $asset_type, 'deregister');
			}, $priority);

			if ($logger->is_active()) {
				$logger->debug("{$trait_name}::deregister - Scheduled deregistration of {$asset_type->value} '{$handle}' on hook '{$hook}' with priority {$priority}.");
			}
		}
	}

	/**
	 * Processes a single asset removal (both dequeue and deregister).
	 *
	 * @param array $asset_definition The asset definition to process.
	 * @param AssetType $asset_type The type of asset (script, style, or script module).
	 * @return void
	 * @throws \InvalidArgumentException If the asset definition is invalid.
	 */
	protected function _process_single_removal(array $asset_definition, AssetType $asset_type): void {
		$logger  = $this->get_logger();
		$context = __TRAIT__ . '::' . __FUNCTION__;

		// Validate handle
		if (!isset($asset_definition['handle']) || !is_string($asset_definition['handle']) || empty($asset_definition['handle'])) {
			throw new \InvalidArgumentException('Asset definition must include a non-empty string handle.');
		}

		$handle     = $asset_definition['handle'];
		$hook       = $asset_definition['hook']      ?? 'wp_enqueue_scripts';
		$priority   = $asset_definition['priority']  ?? 10;
		$immediate  = $asset_definition['immediate'] ?? false;
		$trait_name = get_class($this);

		if ($immediate) {
			// Immediate removal
			$this->_handle_asset_operation($handle, __FUNCTION__, $asset_type, 'remove');
			if ($logger->is_active()) {
				$logger->debug("{$trait_name}::remove - Immediately removed {$asset_type->value} '{$handle}'.");
			}
				} else {
		// Deferred removal via hook
		$this->_do_add_action($hook, function() use ($handle, $asset_type) {
				$this->_handle_asset_operation($handle, __FUNCTION__, $asset_type, 'remove');
			}, $priority);

			if ($logger->is_active()) {
				$logger->debug("{$trait_name}::remove - Scheduled removal of {$asset_type->value} '{$handle}' on hook '{$hook}' with priority {$priority}.");
			}
		}
	}

	/**
	 * Finds an asset in the internal queues and returns its locations.
	 *
	 * This method traverses all internal queues (general, deferred, external_inline)
	 * and returns an array of locations where the asset is found. Each location includes
	 * the queue type, hook name (for deferred/external_inline), priority (for deferred/external_inline),
	 * and index within the queue.
	 *
	 * @param string $handle The asset handle to check.
	 * @param AssetType $asset_type The type of asset (script or style).
	 * @return array An array of asset locations, empty if not found.
	 */
	protected function _asset_exists_in_internal_queues(string $handle, AssetType $asset_type): array {
		$all_queues = $this->get_assets_info();
		$locations  = array();

		// Check general queue
		if (isset($all_queues['assets'][$handle])) {
			$locations[] = array(
				'queue_type' => 'assets',
				'handle'     => $handle
			);
		}

		// Check deferred queue (nested by hook and priority)
		if (isset($this->deferred_assets)) {
			foreach ($this->deferred_assets as $hook_name => $hook_priorities) {
				foreach ($hook_priorities as $priority => $priority_assets) {
					foreach ($priority_assets as $index => $asset_definition) {
						if (($asset_definition['handle'] ?? '') === $handle && ($asset_definition['type'] ?? null) === $asset_type) {
							$locations[] = array(
								'queue_type' => 'deferred',
								'hook_name'  => $hook_name,
								'priority'   => $priority,
								'index'      => $index,
								'handle'     => $handle
							);
						}
					}
				}
			}
		}

		// Check external inline queue (nested by hook and priority)
		foreach ($all_queues['external_inline'] as $hook_name => $hook_priorities) {
			foreach ($hook_priorities as $priority => $priority_assets) {
				foreach ($priority_assets as $index => $asset_data) {
					if ($index === $handle) {
						$locations[] = array(
							'queue_type' => 'external_inline',
							'hook_name'  => $hook_name,
							'priority'   => $priority,
							'handle'     => $handle
						);
					}
				}
			}
		}

		return $locations;
	}



	/**
	 * Process deferred inline assets for a specific parent handle and hook.
	 *
	 * @param AssetType $asset_type    The type of asset (script or style).
	 * @param string    $parent_handle The handle of the parent asset.
	 * @param string    $hook_name     The hook name.
	 * @param string    $context       Context for logging.
	 * @return int Number of processed assets.
	 */
	private function _process_deferred_inline_assets(
		AssetType $asset_type,
		string $parent_handle,
		string $hook_name,
		string $context
	): int {
		$logger          = $this->get_logger();
		$processed_count = 0;

		// Check if we have deferred assets for this hook
		if (!isset($this->deferred_assets[$hook_name])) {
			return $processed_count;
		}

		// Look through all priorities for this hook
		foreach ($this->deferred_assets[$hook_name] as $priority => $assets_by_priority) {
			// Look for the specific asset by handle
			foreach ($assets_by_priority as $key => $asset) {
				if ($asset['handle'] === $parent_handle && $asset['type'] === $asset_type) {
					// Check if this asset has inline assets
					if (!empty($asset['inline'])) {
						$logger->debug("{$context}Found deferred inline {$asset_type->value}s for '{$parent_handle}' on hook '{$hook_name}' priority {$priority}.");

						// Process each inline asset
						foreach ($asset['inline'] as $inline_key => $inline_asset) {
							$content   = $inline_asset['content']   ?? '';
							$condition = $inline_asset['condition'] ?? null;
							$position  = $asset_type === AssetType::Script ? ($inline_asset['position'] ?? 'after') : null;

							// Skip if condition is false
							if (is_callable($condition) && !$condition()) {
								$logger->debug("{$context}Condition false for deferred inline {$asset_type->value} targeting '{$parent_handle}'");
								continue;
							}

							// Skip if content is empty
							if (empty($content)) {
								$logger->warning("{$context}Empty content for deferred inline {$asset_type->value} targeting '{$parent_handle}'. Skipping.");
								continue;
							}

							$logger->debug("{$context}Adding deferred inline {$asset_type->value} for '{$parent_handle}'" .
								($asset_type === AssetType::Script ? ", position: {$position}" : ''));

							$add_inline_function = $asset_type === AssetType::Script ? 'wp_add_inline_script' : 'wp_add_inline_style';
							$result              = $asset_type === AssetType::Script
								? $add_inline_function($parent_handle, $content, $position)
								: $add_inline_function($parent_handle, $content);

							if ($result) {
								$logger->debug("{$context}Successfully added deferred inline {$asset_type->value} for '{$parent_handle}'.");
								$processed_count++;
							} else {
								$logger->warning("{$context}Failed to add deferred inline {$asset_type->value} for '{$parent_handle}'.");
							}
						}

						// Remove all processed inline assets
						unset($this->deferred_assets[$hook_name][$priority][$key]['inline']);
					}

					// We found the asset, no need to continue searching in this priority
					break;
				}
			}
		}

		return $processed_count;
	}
}
