<?php
/**
 * Trait ScriptsEnqueueTrait
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
 * Trait ScriptsEnqueueTrait
 *
 * Manages the registration, enqueuing, and processing of JavaScript assets.
 * This includes handling general scripts, inline scripts, deferred scripts,
 * script attributes, and script data.
 *
 * @package Ran\PluginLib\EnqueueAccessory
 */
trait ScriptsEnqueueTrait {
	use AssetEnqueueBaseTrait;

	/**
	 * Get the array of registered scripts.
	 *
	 * @return array<string, array> An associative array of script definitions, keyed by 'general', 'deferred', and 'inline'.
	 */
	public function get_scripts() {
		return $this->get_assets(AssetType::Script);
	}

	/**
	 * Adds one or more script definitions to the internal queue for processing.
	 *
	 * This method supports adding a single script definition (associative array) or an
	 * array of script definitions. Definitions are merged with any existing scripts
	 * in the queue. Actual registration and enqueuing occur when `enqueue()` or
	 * `enqueue_scripts()` is called. This method is chainable.
	 *
	 * @param array<string, mixed>|array<int, array<string, mixed>> $scripts_to_add A single script definition array or an array of script definition arrays.
	 *     Each script definition array should include:
	 *     - 'handle' (string, required): The unique name of the script.
	 *     - 'src' (string, required): URL to the script resource.
	 *     - 'deps' (array, optional): An array of registered script handles this script depends on. Defaults to an empty array.
	 *     - 'version' (string|false|null, optional): Script version. `false` (default) uses plugin version, `null` adds no version, string sets specific version.
	 *     - 'in_footer' (bool, optional): Whether to enqueue the script before `</body>` (true) or in the `<head>` (false). Defaults to `false`.
	 *     - 'condition' (callable|null, optional): Callback returning boolean. If false, script is not enqueued. Defaults to null.
	 *     - 'attributes' (array, optional): Key-value pairs of HTML attributes for the `<script>` tag (e.g., `['async' => true]`). Defaults to an empty array.
	 *     - 'data' (array, optional): Key-value pairs passed to `wp_script_add_data()`. Defaults to an empty array.
	 *     - 'inline' (array, optional): An array of inline scripts to attach to this handle. See `add_inline_scripts()` for the structure of each inline script definition.
	 *     - 'hook' (string|null, optional): WordPress hook (e.g., 'admin_enqueue_scripts') to defer enqueuing. Defaults to null for immediate processing.
	 *     - 'localize' (array, optional): Data to be localized. See `_process_single_script_asset` for structure.
	 * @return self Returns the instance of this class for method chaining.
	 *
	 * @see self::enqueue_scripts()
	 * @see self::enqueue()
	 */
	public function add_scripts( array $scripts_to_add ): self {
		return $this->add_assets($scripts_to_add, AssetType::Script);
	}

	/**
	 * Registers scripts with WordPress without enqueueing them, handling deferred registration.
	 *
	 * This method iterates through the script definitions previously added via `add_scripts()`.
	 * For each script:
	 * - If a `hook` is specified in the definition, its registration is deferred. The script
	 *   is moved to the `$deferred_scripts` queue, and an action is set up (if not already present)
	 *   to call `_enqueue_deferred_scripts()` when the specified hook fires.
	 * - Scripts without a `hook` are registered immediately. The registration process
	 *   (handled by `_process_single_asset()`) includes checking any associated
	 *   `$condition` callback and calling `wp_register_script()`.
	 *
	 * Note: This method only *registers* the scripts. Enqueuing is handled by
	 * `enqueue_scripts()` or `_enqueue_deferred_scripts()`.
	 *
	 * @return self Returns the instance of this class for method chaining.
	 * @see    self::add_scripts()
	 * @see    self::_process_single_asset()
	 * @see    self::enqueue_scripts()
	 * @see    self::_enqueue_deferred_scripts()
	 * @see    wp_register_script()
	 */
	public function stage_scripts(): self {
		return $this->stage_assets(AssetType::Script);
	}


	/**
	 * Processes and enqueues all immediate scripts that have been registered.
	 *
	 * This method iterates through the `$this->scripts` array, which at this stage should only
	 * contain immediate (non-deferred) scripts, as deferred scripts are moved to their own
	 * queue by `stage_scripts()`.
	 *
	 * For each immediate script, it calls `_process_single_asset()` to handle enqueuing and
	 * the processing of any associated inline scripts or attributes.
	 *
	 * After processing, this method clears the `$this->scripts` array. Deferred scripts stored
	 * in `$this->deferred_scripts` are not affected.
	 *
	 * @return self Returns the instance of this class for method chaining.
	 * @throws \LogicException If a deferred script is found in the queue, indicating `stage_scripts()` was not called.
	 * @see    self::add_scripts()
	 * @see    self::stage_scripts()
	 * @see    self::_process_single_asset()
	 * @see    self::_enqueue_deferred_scripts()
	 */
	public function enqueue_immediate_scripts(): self {
		return $this->enqueue_immediate_assets(AssetType::Script);
	}

	/**
	 * Enqueue scripts that were deferred to a specific hook and priority.
	 *
	 * @internal This is an internal method called by WordPress as an action callback and should not be called directly.
	 * @param string $hook_name The WordPress hook name that triggered this method.
	 * @param int    $priority  The priority of the action that triggered this callback.
	 * @return void
	 */
	public function _enqueue_deferred_scripts( string $hook_name, int $priority ): void {
		$this->_enqueue_deferred_assets(AssetType::Script, $hook_name, $priority);
	}

	/**
	 * Adds one or more inline script definitions to the internal queue.
	 *
	 * This method supports adding a single inline script definition (associative array) or an
	 * array of inline script definitions. This method is chainable.
	 *
	 * @param array<string, mixed>|array<int, array<string, mixed>> $inline_scripts_to_add A single inline script definition array or an array of them.
	 *     Each definition array can include:
	 *     - 'handle'    (string, required): Handle of the script to attach the inline script to.
	 *     - 'content'   (string, required): The inline script content.
	 *     - 'position'  (string, optional): 'before' or 'after'. Default 'after'.
	 *     - 'condition' (callable, optional): A callable that returns a boolean. If false, the script is not added.
	 *     - 'parent_hook' (string, optional): Explicitly associate with a parent's hook.
	 * @return self Returns the instance of this class for method chaining.
	 */
	public function add_inline_scripts( array $inline_scripts_to_add ): self {
		return $this->add_inline_assets( $inline_scripts_to_add, AssetType::Script );
	}

	/**
	 * Enqueues inline scripts that are not attached to a script being processed in the current lifecycle.
	 *
	 * This method is designed to handle inline scripts that target already registered/enqueued scripts,
	 * such as those belonging to WordPress core or other plugins. It should be called on a hook
	 * like `wp_enqueue_scripts` with a late priority to ensure the target scripts are available.
	 *
	 * @internal This is an internal method called by WordPress as an action callback and should not be called directly.
	 * @return void
	 */
	public function _enqueue_external_inline_scripts(): void {
		$this->_enqueue_external_inline_assets(AssetType::Script);
	}

	/**
	 * Processes inline assets associated with a specific parent asset handle and hook context.
	 *
	 * @param AssetType $asset_type The type of asset (eg styles in this context) this is a flag for the child method to know what type of asset it is processing.
	 * @param string      $parent_handle      The handle of the parent script.
	 * @param string|null $hook_name          (Optional) The hook name if processing for a deferred context.
	 * @param string      $processing_context A string indicating the context for logging purposes.
	 * @return void
	 */
	protected function _process_inline_script_assets(
		AssetType $asset_type,
		string $parent_handle,
		?string $hook_name = null,
		string $processing_context = 'immediate'
	): void {
		// Use the unified implementation from the base trait
		$this->_concrete_process_inline_assets($asset_type, $parent_handle, $hook_name, $processing_context);
	}

	/**
	 * Processes a single script definition, handling registration, enqueuing, and data/attribute additions.
	 *
	 * This is a versatile helper method that underpins the public-facing script methods. It separates
	 * the logic for handling individual scripts, making the main `stage_scripts` and `enqueue_scripts`
	 * methods cleaner. For non-deferred scripts (where `$hook_name` is null), it also handles
	 * processing of attributes, `wp_script_add_data`, and inline scripts. For deferred scripts, this is handled
	 * by the calling `_enqueue_deferred_scripts` method.
	 *
	 * @param AssetType $asset_type The asset type, expected to be 'script'.
	 * @param array{
	 */
	protected function _process_single_script_asset(
		AssetType $asset_type,
		array $asset_definition,
		string $processing_context,
		?string $hook_name = null,
		bool $do_register = true,
		bool $do_enqueue = false
	): string|false {
		$logger  = $this->get_logger();
		$context = __TRAIT__ . '::' . __FUNCTION__;

		if ($asset_type !== AssetType::Script) {
			$logger->warning("{$context}Incorrect asset type provided to _process_single_script_asset. Expected 'script', got '{$asset_type->value}'.");
			return false;
		}
		
		// Prepare script-specific options
		$in_footer    = $asset_definition['in_footer']  ?? false;
		$attributes   = $asset_definition['attributes'] ?? array();
		$enqueue_args = array('in_footer' => $in_footer);
		
		// Handle strategy (async/defer)
		if (is_array($attributes)) {
			foreach ($attributes as $key => $value) {
				$key_lower = strtolower((string)$key);
				if ($key_lower === 'async' && $value === true) {
					$enqueue_args['strategy'] = 'async';
				} elseif ($key_lower === 'defer' && $value === true) {
					$enqueue_args['strategy'] = 'defer';
				}
			}
		}
		
		$handle = $this->_concrete_process_single_asset(
			$asset_type,
			$asset_definition,
			$processing_context,
			$hook_name,
			$do_register,
			$do_enqueue,
			$enqueue_args
		);
		
		// If processing was successful
		if ($handle !== false) {
			// Process script-specific extras (attributes, localization, etc.)
			$this->_process_script_extras($asset_definition, $handle, $hook_name);
			
			// If we're enqueueing, also process inline scripts
			if ($do_enqueue) {
				// Process any inline scripts attached to this asset definition
				$this->_process_inline_script_assets($asset_type, $handle, $hook_name, 'immediate');
			}
		}
		
		return $handle;
	}
	
	/**
	 * Process script-specific extras like localization and data.
	 *
	 * @param array       $asset_definition The script asset definition.
	 * @param string      $handle           The script handle.
	 * @param string|null $hook_name        Optional. The hook name.
	 */
	protected function _process_script_extras(array $asset_definition, string $handle, ?string $hook_name): void {
		$logger     = $this->get_logger();
		$context    = __TRAIT__ . '::' . __FUNCTION__;
		$asset_type = AssetType::Script;
		
		if (null === $hook_name && $handle) {
			$data       = $asset_definition['data']       ?? array();
			$localize   = $asset_definition['localize']   ?? array();
			$attributes = $asset_definition['attributes'] ?? array();
			
			// Localize script (must be done after registration/enqueue).
			if (!empty($localize) && !empty($localize['object_name']) && is_array($localize['data'])) {
				if ($logger->is_active()) {
					$logger->debug("Localizing script '{$handle}' with JS object '{$localize['object_name']}'");
				}

				wp_localize_script(
					$handle,
					$localize['object_name'],
					$localize['data']
				);
			}

			// Process data with wp_script_add_data
			if (is_array($data) && !empty($data)) {
				foreach ($data as $key => $value) {
					if ($logger->is_active()) {
						$logger->debug("{$context} - Adding data to script '{$handle}'. Key: '{$key}', Value: '{$value}'.");
					}
					if (!wp_script_add_data($handle, (string)$key, $value) && $logger->is_active()) {
						$logger->warning("{$context} - Failed to add data for key '{$key}' to script '{$handle}'.");
					}
				}
			}

			// Add custom attributes to the script tag.
			$custom_attributes = $this->_extract_custom_script_attributes($handle, $attributes);
			
			if (!empty($custom_attributes)) {
				if ($logger->is_active()) {
					$logger->debug("{$context} - Adding attributes to script '{$handle}'.");
				}
				
				$callback = function ($tag, $tag_handle) use ($handle, $custom_attributes) {
					return $this->_modify_script_tag_attributes(AssetType::Script, $tag, $tag_handle, $handle, $custom_attributes);
				};
				$this->_do_add_filter('script_loader_tag', $callback, 10, 2);
			}
		}
	}
	
	/**
	 * Extract custom script attributes that need to be applied via filter.
	 *
	 * @param string $handle     The script handle.
	 * @param array  $attributes The attributes array.
	 *
	 * @return array Custom attributes that need to be applied via filter.
	 */
	protected function _extract_custom_script_attributes(string $handle, array $attributes): array {
		$logger            = $this->get_logger();
		$context           = __TRAIT__ . '::' . __FUNCTION__;
		$custom_attributes = array();
		
		foreach ($attributes as $key => $value) {
			$key_lower = strtolower((string)$key);
			
			if ($key_lower === 'async' || $key_lower === 'defer') {
				// These are handled via the 'strategy' parameter
				continue;
			} elseif (in_array($key_lower, array('src', 'id', 'type'), true)) {
				if ($logger->is_active()) {
					$logger->warning("Ignoring '{$key_lower}' attribute for '{$handle}'");
				}
				continue;
			} else {
				// Collect all other non-managed attributes
				$custom_attributes[$key] = $value;
			}
		}
		
		return $custom_attributes;
	}

	/**
	 * Modifies a script tag by adding attributes, intended for use with the 'script_loader_tag' filter.
	 *
	 * This method adjusts the script tag by adding attributes as specified in the $attributes_to_apply array.
	 * It's designed to work within the context of the 'script_loader_tag' filter, allowing for dynamic
	 * modification of script tags based on the handle of the script being filtered.
	 *
	 * @param string $_asset_type The type of asset (eg styles in this context) this is a flag for the child method to know what type of asset it is processing.
	 * @param string $tag The original HTML script tag.
	 * @param string $tag_handle The handle of the script currently being filtered by WordPress.
	 * @param string $script_handle_to_match The handle of the script we are targeting for modification.
	 * @param array  $attributes_to_apply The attributes to apply to the script tag.
	 *
	 * @return string The modified (or original) HTML script tag.
	 */
	protected function _modify_script_tag_attributes(
		AssetType $asset_type,
		string $tag,
		string $tag_handle,
		string $handle_to_match,
		array $attributes_to_apply
	): string {
		$context = __TRAIT__ . '::' . __FUNCTION__;
		$logger  = $this->get_logger();

		if ($asset_type !== AssetType::Script) {
			$logger->warning("{$context}Incorrect asset type provided to _modify_script_tag_attributes. Expected 'script', got '{$asset_type->value}'.");
			return $tag; // Not a script, do not modify.
		}


		// If the filter is not for the script we're interested in, return the original tag.
		if ( $tag_handle !== $handle_to_match ) {
			return $tag;
		}

		if ($logger->is_active()) {
			$logger->debug("{$context} - Modifying {$asset_type->value} tag for handle '{$handle_to_match}'. Attributes: " . \wp_json_encode($attributes_to_apply));
		}

		// Special handling for module scripts.
		if ( isset( $attributes_to_apply['type'] ) && 'module' === $attributes_to_apply['type'] ) {
			if ($logger->is_active()) {
				$logger->debug("{$context} - Script '{$handle_to_match}' is a module. Modifying tag accordingly.");
			}
			// Position type="module" right after <script.
			$tag = preg_replace( '/<script\s/', '<script type="module" ', $tag );
			// Remove type from attributes so it's not added again.
			unset( $attributes_to_apply['type'] );
		}

		// Find the insertion point for attributes. This also serves as tag validation.
		$closing_bracket_pos = strpos( $tag, '>' );
		$el_open_pos         = stripos( $tag, '<script' );

		if ( false === $closing_bracket_pos || false === $el_open_pos ) {
			if ($logger->is_active()) {
				$logger->warning("{$context} - Malformed {$asset_type->value} tag for '{$handle_to_match}'. Original tag: " . esc_html($tag) . '. Skipping attribute modification.');
			}
			return $tag;
		}

		$attr_str = '';
		// Define managed attributes that should not be overridden by users.
		$managed_attributes = array( 'src', 'id', 'type' );

		foreach ( $attributes_to_apply as $attr => $value ) {
			$attr_lower = strtolower( (string) $attr );

			// Check for attempts to override other managed attributes.
			if ( in_array( $attr_lower, $managed_attributes, true ) ) {
				if ($logger->is_active()) {
					$logger->warning(
						sprintf(
							"%s - Attempt to override managed attribute '%s' for {$asset_type->value} handle '%s'. This attribute will be ignored.",
							$context,
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

			// Boolean attributes (value is true).
			if ( true === $value ) {
				$attr_str .= ' ' . esc_attr( $attr_lower );
			} elseif ( false !== $value && null !== $value && '' !== $value ) { // Regular attributes with non-empty, non-false, non-null values.
				$attr_str .= ' ' . esc_attr( $attr_lower ) . '="' . esc_attr( (string) $value ) . '"';
			}
			// Attributes with false, null, or empty string values are skipped.
		}

		$modified_tag = substr_replace( $tag, $attr_str, $closing_bracket_pos, 0 );

		if ($logger->is_active()) {
			$logger->debug("{$context} - Successfully modified tag for '{$handle_to_match}'. New tag: " . esc_html($modified_tag));
		}
		return $modified_tag;
	}
}
