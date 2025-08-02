<?php
/**
 * Trait ScriptModulesEnqueueTrait (experimental)
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

/**
 * Trait ScriptModulesEnqueueTrait (experimental)
 *
 * Manages the registration, enqueuing, and processing of JavaScript module assets.
 *
 * This trait provides script module support using WordPress's native Script Modules API
 * introduced in WordPress 6.5. Admin support was added in WordPress 6.7,
 * and module_data was added in WordPress 6.7.
 *
 * Key differences from ScriptsEnqueueTrait:
 * - Uses wp_register_script_module() and wp_enqueue_script_module() instead of script functions
 * - Supports module_data for passing server data (WordPress 6.7+) via script_module_data_{$module_id} filter
 * - Does NOT support custom HTML tag attributes (no script_module_loader_tag filter exists yet)
 * - Does NOT support wp_localize_script() or wp_script_add_data() (incompatible with modules)
 * - Handles static and dynamic module dependencies through import maps
 * - Provides automatic module preloading for static dependencies
 *
 * Script modules are completely separate from regular scripts in WordPress - they cannot
 * depend on each other and use different registration/enqueuing systems.
 *
 * NOTE: This trait is experimental and may change in future versions, as module support in WordPress is still in development.
 * One noteable limitation is wp_script_module_is() is not available as of WordPress 6.7, so a temporary shim is provided.
 *
 * @package Ran\PluginLib\EnqueueAccessory
 * @since WordPress 6.5 (Script Modules API)
 * @since WordPress 6.7 (module_data support)
 */
trait ScriptModulesEnqueueTrait {
	use AssetEnqueueBaseTrait;

	/**
	 * Returns the asset type for this trait.
	 *
	 * @return AssetType
	 */
	protected function _get_asset_type(): AssetType {
		return AssetType::ScriptModule;
	}

	/**
	 * Get the array of registered modules.
	 *
	 * @return array<string, array> An associative array of script definitions, keyed by 'assets', 'deferred', and 'inline'.
	 */
	public function get_info() {
		return $this->get_assets_info($this->_get_asset_type());
	}

	/**
	 * Adds one or more script module definitions to the internal queue for processing.
	 *
	 * This method supports adding a single script module definition (associative array) or an
	 * array of script module definitions. Definitions are merged with any existing modules
	 * in the queue. Actual registration and enqueuing occur when `stage()` and `enqueue()`
	 * are called. This method is chainable.
	 *
	 * @param array<string, mixed>|array<int, array<string, mixed>> $modules_to_add A single module definition array or an array of module definition arrays.
	 *     Each script module definition array should include:
	 *     - 'handle' (string, required): The unique module identifier (e.g., '@my-plugin/component').
	 *     - 'src' (string, required): URL to the module resource.
	 *     - 'deps' (array, optional): Array of module dependencies. Can be strings or arrays with 'id' and 'import' keys for dynamic imports. Defaults to empty array.
	 *     - 'version' (string|false|null, optional): Module version. `false` (default) uses plugin version, `null` adds no version, string sets specific version.
	 *     - 'condition' (callable|null, optional): Callback returning boolean. If false, module is not enqueued. Defaults to null.
	 *     - 'module_data' (array, optional): Data to pass to the module via script_module_data_{$handle} filter (WordPress 6.7+). Defaults to empty array.
	 *     - 'hook' (string|null, optional): WordPress hook (e.g., 'wp_enqueue_scripts') to defer enqueuing. Defaults to null for immediate processing.
	 *
	 *     UNSUPPORTED properties (use will generate warnings):
	 *     - 'in_footer': Script modules don't support footer placement - they use import maps and are automatically optimized
	 *     - 'attributes': Custom HTML attributes not yet supported (no script_module_loader_tag filter exists)
	 *     - 'data': Use 'module_data' instead - wp_script_add_data() is incompatible with modules
	 *     - 'inline': Inline modules not yet supported by WordPress Script Modules API
	 *     - 'localize': Use 'module_data' instead - wp_localize_script() is incompatible with modules
	 * @return self Returns the instance of this class for method chaining.
	 *
	 * @see self::stage()
	 * @see self::enqueue()
	 */
	public function add(array $modules_to_add): self {
		return $this->add_assets($modules_to_add, $this->_get_asset_type());
	}


	/**
	 * Registers script modules with WordPress without enqueueing them, handling deferred registration.
	 *
	 * This method iterates through the script module definitions previously added via `add()`.
	 * For each script module:
	 * - If a `hook` is specified in the definition, its registration is deferred. The script module
	 *   is moved to the `$deferred_script_modules` queue, and an action is set up (if not already present)
	 *   to call `_enqueue_deferred_script_modules()` when the specified hook fires.
	 * - Script modules without a `hook` are registered immediately. The registration process
	 *   (handled by `_process_single_asset()`) includes checking any associated
	 *   `$condition` callback and calling `wp_register_script()`.
	 *
	 * Note: This method only *registers* the script modules. Enqueuing is handled by
	 * `enqueue_script_modules()`.
	 *
	 * @return self Returns the instance of this class for method chaining.
	 */
	public function stage(): self {
		return $this->stage_assets($this->_get_asset_type());
	}

	/**
	 * Processes and enqueues all immediate script modules that have been registered.
	 *
	 * This method iterates through the `$this->script_modules` array, which at this stage should only
	 * contain immediate (non-deferred) script modules, as deferred script modules are moved to their own
	 * queue by `stage()`.
	 *
	 * For each immediate script module, it calls `_process_single_asset()` to handle enqueuing and
	 * the processing of any associated inline scripts or attributes.
	 *
	 * After processing, this method clears the `$this->script_modules` array. Deferred script modules stored
	 * in `$this->deferred_script_modules` are not affected.
	 *
	 * @return self Returns the instance of this class for method chaining.
	 * @throws \LogicException If a deferred script module is found in the queue, indicating `stage()` was not called.
	 */
	public function enqueue_immediate(): self {
		return $this->enqueue_immediate_assets($this->_get_asset_type());
	}

	/**
	 * Enqueue script modules that were deferred to a specific hook and priority.
	 *
	 * @internal This is an internal method called by WordPress as an action callback and should not be called directly.
	 * @param string $hook_name The WordPress hook name that triggered this method.
	 * @param int    $priority  The priority of the action that triggered this callback.
	 * @return void
	 */
	public function _enqueue_deferred_modules( string $hook_name, int $priority ): void {
		$this->_enqueue_deferred_assets($this->_get_asset_type(), $hook_name, $priority);
	}

	/**
	 * Dequeue one or more script modules from WordPress.
	 *
	 * This method allows selective dequeuing of script modules while leaving them registered
	 * for potential re-enqueuing later. This is useful for conditional module loading or
	 * temporarily disabling modules without losing their registration.
	 *
	 * Note: WordPress Script Modules API does not provide status checking functions like
	 * wp_script_module_is(). This method will attempt to dequeue all specified modules
	 * silently, and WordPress will handle non-existent modules gracefully without errors.
	 *
	 * This method supports flexible input formats:
	 * - A single string handle
	 * - An array of string handles
	 * - An array of asset definition arrays with optional hook and priority
	 * - A mixed array of strings and asset definition arrays
	 *
	 * @param string|array<string|array> $modules_to_dequeue Script modules to dequeue.
	 * @return self Returns the instance for method chaining
	 *
	 * @since WordPress 6.5.0 (wp_dequeue_script_module function availability)
	 */
	public function dequeue(string|array $modules_to_dequeue): self {
		$logger  = $this->get_logger();
		$context = static::class . '::' . __FUNCTION__;

		// Use shared normalization for consistent input handling
		$normalized_modules = $this->_normalize_asset_input($modules_to_dequeue);

		foreach ($normalized_modules as $module_definition) {
			$handle = $module_definition['handle'];

			\wp_dequeue_script_module($handle);

			if ($logger->is_active()) {
				$logger->debug("{$context} - Attempted dequeue of script module '{$handle}'. WordPress Script Modules API provides no status feedback - operation completed silently.");
			}
		}

		return $this;
	}

	/**
	 * Deregisters one or more script modules from WordPress.
	 *
	 * This method only deregisters script modules, leaving any enqueued instances active.
	 * Use remove() if you want to both dequeue and deregister script modules.
	 *
	 * This method supports flexible input formats:
	 * - A single string handle
	 * - An array of string handles
	 * - An array of asset definition arrays with optional hook and priority
	 * - A mixed array of strings and asset definition arrays
	 *
	 * @param string|array<string|array> $modules_to_deregister Modules to deregister.
	 * @return self Returns the instance of this class for method chaining.
	 */
	public function deregister($modules_to_deregister): self {
		return $this->_deregister_assets($modules_to_deregister, $this->_get_asset_type());
	}

	/**
	 * Removes one or more script modules from WordPress by both dequeuing and deregistering them.
	 *
	 * This method combines dequeue and deregister operations, providing complete removal
	 * of script modules from WordPress. This is equivalent to the previous behavior of deregister().
	 *
	 * This method supports flexible input formats:
	 * - A single string handle
	 * - An array of string handles
	 * - An array of asset definition arrays with optional hook and priority
	 * - A mixed array of strings and asset definition arrays
	 *
	 * @param string|array<string|array> $modules_to_remove Modules to remove.
	 * @return self Returns the instance of this class for method chaining.
	 */
	public function remove($modules_to_remove): self {
		return $this->_remove_assets($modules_to_remove, $this->_get_asset_type());
	}

	/**
	 * Processes a single script module definition, handling registration, enqueuing, and data/attribute additions.
	 *
	 * This is a versatile helper method that underpins the public-facing script module methods. It separates
	 * the logic for handling individual script modules, making the main `stage_script_modules` and `enqueue_script_modules`
	 * methods cleaner. For non-deferred script modules (where `$hook_name` is null), it also handles
	 * processing of attributes, `wp_script_add_data`, and inline scripts. For deferred script modules, this is handled
	 * by the calling `_enqueue_deferred_script_modules` method.
	 *
	 * @param AssetType $asset_type The asset type, expected to be 'script_module'.
	 * @param array{
	 *   handle: string, 		// The unique name of the script module.
	 *   src: string|false, 	// The source URL of the script module or false for registered-only modules.
	 *   deps?: string[], 		// Optional array of module dependencies.
	 *   ver?: string, 			// Optional version string.
	 *   condition?: callable, 	// Optional condition callback.
	 *   replace?: bool, 		// Optional flag to replace existing modules.
	 *   attributes?: array, 	// Optional array of attributes.
	 *   data?: array, 			// Optional array of data.
	 * } $asset_definition The script module definition.
	 * @param string $processing_context The context in which this asset is being processed.
	 * @param string|null $hook_name The hook name if in hook-firing phase.
	 * @param bool $do_register Whether to register the script module.
	 * @param bool $do_enqueue Whether to enqueue the script module.
	 *
	 * @return string|false The handle of the script module on success, false on failure or if a condition is not met.
	 */
	protected function _process_single_asset(
		AssetType $asset_type,
		array $asset_definition,
		string $processing_context,
		?string $hook_name = null,
		bool $do_register = true,
		bool $do_enqueue = false
	): string|false {
		$logger  = $this->get_logger();
		$context = __TRAIT__ . '::' . __FUNCTION__;

		if ($asset_type !== AssetType::ScriptModule) {
			$logger->warning("{$context}Incorrect asset type provided to _process_single_asset. Expected 'script_module', got '{$asset_type->value}'.");
			return false;
		}

		// Prepare module-specific options
		$attributes  = $asset_definition['attributes'] ?? array();
		$module_args = array();

		$handle = $this->_concrete_process_single_asset(
			$asset_type,
			$asset_definition,
			$processing_context,
			$hook_name,
			$do_register,
			$do_enqueue,
			$module_args
		);

		// If processing was successful
		if ($handle !== false) {
			// Process script-specific extras (attributes, localization, etc.)
			$this->_process_module_extras($asset_definition, $handle, $hook_name);
		}

		return $handle;
	}

	/**
	 * Process module-specific extras like data passing and attributes.
	 *
	 * @param array       $asset_definition The module asset definition.
	 * @param string      $handle           The module handle.
	 * @param string|null $hook_name        Optional. The hook name.
	 */
	protected function _process_module_extras(array $asset_definition, string $handle, ?string $hook_name): void {
		$logger  = $this->get_logger();
		$context = __TRAIT__ . '::' . __FUNCTION__;

		if (null === $hook_name && $handle) {
			// Handle module_data for script modules (WP 6.7+)
			if (isset($asset_definition['module_data']) && is_array($asset_definition['module_data'])) {
				$module_data = $asset_definition['module_data'];

				if ($logger->is_active()) {
					$logger->debug("{$context} - Adding module data to '{$handle}' via script_module_data_{$handle} filter.");
				}

				// Register the filter to pass data to the module
				add_filter(
					"script_module_data_{$handle}",
					function(array $data) use ($module_data, $logger, $context, $handle) {
						$merged_data = array_merge($data, $module_data);

						if ($logger->is_active()) {
							$data_keys = implode(', ', array_keys($module_data));
							$logger->debug("{$context} - Module data keys added to '{$handle}': {$data_keys}");
						}

						return $merged_data;
					},
					10,
					1
				);
			}

			// Handle custom attributes (if needed for modules)
			$attributes = $asset_definition['attributes'] ?? array();
			if (!empty($attributes)) {
				// Note: Script modules do not currently support attributes
				if ($logger->is_active()) {
					$logger->warning("{$context} - Processing module '{$handle}' - Custom attributes for modules not yet supported in WordPress, as there currently is no 'script_module_loader_tag' filter or equivalent.");
				}
			}

			// Warn about incompatible script features
			$incompatible_features = array('in_footer', 'inline');
			foreach ($incompatible_features as $feature) {
				if (isset($asset_definition[$feature])) {
					if ($logger->is_active()) {
						$logger->warning("{$context} - Processing module '{$handle}' - Feature '{$feature}' is not compatible with script modules.");
					}
				}
			}

			// Warn about incompatible script features
			$incompatible_features = array('localize', 'data');
			foreach ($incompatible_features as $feature) {
				if (isset($asset_definition[$feature])) {
					if ($logger->is_active()) {
						$logger->warning("{$context} - Processing module '{$handle}' - Feature '{$feature}' is not compatible with script modules. Use 'module_data' instead.");
					}
				}
			}
		}
	}

	/**
	 * Temporary stand-in for the future wp_script_module_is() function (or equivalent).
	 *
	 * WordPress doesn't currently provide wp_script_module_is() for status checking,
	 * so this method acts as a temporary placeholder that allows us to unify the code flow
	 * across all asset types. When WordPress adds wp_script_module_is(), this method
	 * can be easily replaced.
	 *
	 * IMPORTANT LIMITATION: This method only tracks script modules that have been
	 * registered or enqueued through this AssetEnqueueBaseTrait instance. Script modules
	 * registered by other plugins, themes, or WordPress core will not be tracked in our
	 * internal registry and will return false even if they exist in WordPress.
	 *
	 * This limitation is acceptable because:
	 * - WordPress dequeue/deregister functions handle missing modules gracefully
	 * - Our tracking scope is limited to modules we manage
	 * - This behavior will be replaced when WordPress provides wp_script_module_is()
	 *
	 * @param string $handle The script module handle to check.
	 * @param string $status The status to check: 'registered' or 'enqueued'.
	 * @return bool Returns registration status based on internal tracking only.
	 *              Does NOT reflect modules registered by external code.
	 *
	 * @todo Replace with wp_script_module_is() when WordPress core provides it.
	 *
	 * @codeCoverageIgnore This is a temporary shim/placeholder for WordPress core functionality
	 */
	protected function _module_is(string $handle, string $status): bool {
		// TODO: Replace with wp_script_module_is($handle, $status) - or whatever fills this gap when available

		// Initialize internal registry if not exists
		if (!isset($this->_script_module_registry)) {
			$this->_script_module_registry = array('registered' => array(), 'enqueued' => array());
		}

		// Check our internal registry
		$is_tracked = in_array($handle, $this->_script_module_registry[$status] ?? array(), true);

		// Log warning if we're checking status for a module we haven't tracked
		// This alerts developers that the module might be registered externally
		if (!$is_tracked && $this->get_logger()->is_active()) {
			$this->get_logger()->debug(
				"AssetEnqueueBaseTrait::_module_is() - Script module '{$handle}' not found in internal {$status} registry. "
				. "This may indicate the module was {$status} by external code (other plugins/themes/core). "
				. 'WordPress will handle the operation gracefully regardless.'
			);
		}

		return $is_tracked;
	}
}
