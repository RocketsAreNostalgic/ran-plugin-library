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
	use EnqueueAssetTraitBase;



	/**
	 * Abstract method to get the logger instance.
	 *
	 * This ensures that any class using this trait provides a logger.
	 *
	 * @return Logger The logger instance.
	 */
	abstract public function get_logger(): Logger;

	/**
	 * Get the array of registered scripts.
	 *
	 * @return array<string, array> An associative array of script definitions, keyed by 'general', 'deferred', and 'inline'.
	 */
	public function get_scripts() {
		return $this->get_assets('script');
	}

	/**
	 * Retrieves the registered deferred scripts.
	 *
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public function get_deferred_scripts(): array {
		return $this->get_deferred_assets('script');
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
	 *     - 'handle' (string, required): Name of the script. Must be unique.
	 *     - 'src' (string, required): URL to the script resource.
	 *     - 'deps' (array, optional): An array of registered script handles this script depends on. Defaults to an empty array.
	 *     - 'version' (string|false|null, optional): Script version. `false` (default) uses plugin version, `null` adds no version, string sets specific version.
	 *     - 'in_footer' (bool, optional): Whether to enqueue the script before `</body>` (true) or in the `<head>` (false). Defaults to `false`.
	 *     - 'condition' (callable|null, optional): Callback returning boolean. If false, script is not enqueued. Defaults to null.
	 *     - 'attributes' (array, optional): Key-value pairs of HTML attributes for the `<script>` tag (e.g., `['async' => true]`). Defaults to an empty array.
	 *     - 'hook' (string|null, optional): WordPress hook (e.g., 'admin_enqueue_scripts') to defer enqueuing. Defaults to null (immediate processing).
	 * @return self Returns the instance of this class for method chaining.
	 *
	 * @see self::enqueue_scripts()
	 * @see self::enqueue()
	 */
	public function add_scripts( array $scripts_to_add ): self {
		return $this->add_assets($scripts_to_add, 'script');
	}

	/**
	 * Registers scripts with WordPress without enqueueing them, handling deferred registration.
	 *
	 * This method iterates through the script definitions previously added via `add_scripts()`.
	 * For each script:
	 * - If a `hook` is specified in the definition, its registration is deferred. The script
	 *   is moved to the `$deferred_scripts` queue, and an action is set up (if not already present)
	 *   to call `enqueue_deferred_scripts()` when the specified hook fires.
	 * - Scripts without a `hook` are registered immediately. The registration process
	 *   (handled by `_process_single_asset()`) includes checking any associated
	 *   `$condition` callback and calling `wp_register_script()`.
	 *
	 * Note: This method only *registers* the scripts. Enqueuing is handled by
	 * `enqueue_scripts()` or `enqueue_deferred_scripts()`.
	 *
	 * @return self Returns the instance of this class for method chaining.
	 * @see    self::add_scripts()
	 * @see    self::_process_single_asset()
	 * @see    self::enqueue_scripts()
	 * @see    self::enqueue_deferred_scripts()
	 * @see    wp_register_script()
	 */
	public function register_scripts(): self {
		return $this->register_assets('script');
	}


	/**
	 * Processes and enqueues all immediate scripts that have been registered.
	 *
	 * This method iterates through the `$this->scripts` array, which at this stage should only
	 * contain immediate (non-deferred) scripts, as deferred scripts are moved to their own
	 * queue by `register_scripts()`.
	 *
	 * For each immediate script, it calls `_process_single_asset()` to handle enqueuing and
	 * the processing of any associated inline scripts or attributes.
	 *
	 * After processing, this method clears the `$this->scripts` array. Deferred scripts stored
	 * in `$this->deferred_scripts` are not affected.
	 *
	 * @return self Returns the instance of this class for method chaining.
	 * @throws \LogicException If a deferred script is found in the queue, indicating `register_scripts()` was not called.
	 * @see    self::add_scripts()
	 * @see    self::register_scripts()
	 * @see    self::_process_single_asset()
	 * @see    self::enqueue_deferred_scripts()
	 */
	public function enqueue_scripts(): self {
		return $this->enqueue_assets('script');
	}

	/**
	 * Enqueue scripts that were deferred to a specific hook.
	 *
	 * @param string $hook_name The WordPress hook name that triggered this method.
	 * @return void
	 */
	public function enqueue_deferred_scripts( string $hook_name ): void {
		$this->enqueue_deferred_assets($hook_name, 'script');
	}

	/**
	 * Chain-able call to add multiple inline scripts.
	 *
	 * @param array<string, mixed>|array<int, array<string, mixed>> $inline_scripts_to_add A single inline script definition array or an array of them.
	 * @return self
	 */
	public function add_inline_scripts( array $inline_scripts_to_add ): self {
		return $this->add_inline_assets( $inline_scripts_to_add, 'script' );
	}

	/**
	 * Process and add all registered inline scripts.
	 *
	 * @return self
	 */
	public function enqueue_inline_scripts(): self {
		return $this->enqueue_inline_assets('script');
	}

	/**
	 * Processes inline assets associated with a specific parent asset handle and hook context.
	 *
	 * @param string $_asset_type The type of asset (eg styles in this context) this is a flag for the child method to know what type of asset it is processing.
	 * @param string      $parent_handle      The handle of the parent script.
	 * @param string|null $hook_name          (Optional) The hook name if processing for a deferred context.
	 * @param string      $processing_context A string indicating the context for logging purposes.
	 * @return void
	 */
	protected function _process_inline_assets(
		string $_asset_type,
		string $parent_handle,
		?string $hook_name = null,
		string $processing_context = 'immediate'
	): void {
		$logger          = $this->get_logger();
		$log_prefix_base = "ScriptsEnqueueTrait::_process_inline_assets (context: {$processing_context}) - ";

		$logger->debug( "{$log_prefix_base}Checking for inline assets for parent handle '{$parent_handle}'" . ($hook_name ? " on hook '{$hook_name}'." : '.') );

		// Check if the parent asset is registered or enqueued before processing its inline assets.
		// This is a crucial check to prevent adding inline assets to a non-existent parent.
		if ( ! wp_script_is( $parent_handle, 'registered' ) && ! wp_script_is( $parent_handle, 'enqueued' ) ) {
			$logger->error( "{$log_prefix_base}Cannot add inline assets. Parent asset '{$parent_handle}' is not registered or enqueued." );
			return;
		}

		$keys_to_unset = array();

		foreach ( $this->inline_assets as $key => $inline_asset_data ) {
			if (!is_array($inline_asset_data)) {
				$logger->warning("{$log_prefix_base} Invalid inline asset data at key '{$key}'. Skipping.");
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
				$position         = $inline_asset_data['position']  ?? 'after';
				$condition_inline = $inline_asset_data['condition'] ?? null;

				if ( is_callable( $condition_inline ) && ! $condition_inline() ) {
					$logger->debug( "{$log_prefix_base}Condition false for inline asset targeting '{$parent_handle}' (key: {$key})" . ($hook_name ? " on hook '{$hook_name}'." : '.') );
					$keys_to_unset[] = $key;
					continue;
				}

				if ( empty( $content ) ) {
					$logger->warning( "{$log_prefix_base}Empty content for inline asset targeting '{$parent_handle}'" . ($hook_name ? " on hook '{$hook_name}'." : '.') . ' Skipping addition.' );
					$keys_to_unset[] = $key;
					continue;
				}

				$logger->debug( "{$log_prefix_base}Adding inline asset for '{$parent_handle}' (key: {$key}, position: {$position})" . ($hook_name ? " on hook '{$hook_name}'." : '.') );
				if (wp_add_inline_script($parent_handle, $content, $position)) {
					$logger->debug("{$log_prefix_base}Successfully added inline asset for '{$parent_handle}' with wp_add_inline_script.");
					$keys_to_unset[] = $key;
				}
			}
		}

		if ( ! empty( $keys_to_unset ) ) {
			foreach ( $keys_to_unset as $key_to_unset ) {
				if (isset($this->inline_assets[$key_to_unset])) {
					$removed_handle_for_log = $this->inline_assets[$key_to_unset]['handle'] ?? 'N/A';
					unset( $this->inline_assets[ $key_to_unset ] );
					$logger->debug( "{$log_prefix_base}Removed processed inline asset with key '{$key_to_unset}' for handle '{$removed_handle_for_log}'" . ($hook_name ? " on hook '{$hook_name}'." : '.') );
				}
			}
			$this->inline_assets = array_values( $this->inline_assets );
		} else {
			$logger->debug( "{$log_prefix_base}No inline assets found or processed for '{$parent_handle}'" . ($hook_name ? " on hook '{$hook_name}'." : '.') );
		}
	}

	/**
	 * Processes a single script definition, handling registration, enqueuing, and data/attribute additions.
	 *
	 * This is a versatile helper method that underpins the public-facing script methods. It separates
	 * the logic for handling individual scripts, making the main `register_scripts` and `enqueue_scripts`
	 * methods cleaner. For non-deferred scripts (where `$hook_name` is null), it also handles
	 * processing of attributes, `wp_script_add_data`, and inline scripts. For deferred scripts, this is handled
	 * by the calling `enqueue_deferred_scripts` method.
	 *
	 * @param string $_asset_type The type of asset (eg styles in this context) this is a flag for the child method to know what type of asset it is processing.
	 * @param array      $script_definition   The script definition array.
	 * @param string     $processing_context  The context in which the script is being processed (e.g., 'register_scripts', 'enqueue_scripts'). Used for logging.
	 * @param string|null $hook_name          The name of the hook if the script is being processed in a deferred context.
	 * @param bool       $do_register         If true, the script will be registered with `wp_register_script()`.
	 * @param bool       $do_enqueue          If true, the script will be enqueued with `wp_enqueue_script()`.
	 *
	 * @return string|false The handle of the script on success, false on failure or if a condition is not met.
	 */
	protected function _process_single_asset(
		string $_asset_type,
		array $script_definition,
		string $processing_context,
		?string $hook_name = null,
		bool $do_register = true,
		bool $do_enqueue = false
	): string|false {
		$logger = $this->get_logger();

		$handle     = $script_definition['handle']     ?? null;
		$src        = $script_definition['src']        ?? null;
		$deps       = $script_definition['deps']       ?? array();
		$version    = $script_definition['version']    ?? false;
		$in_footer  = $script_definition['in_footer']  ?? false;
		$attributes = $script_definition['attributes'] ?? array();
		$condition  = $script_definition['condition']  ?? null;

		$log_handle_context = $handle ?? 'N/A';
		$log_hook_context   = $hook_name ? " on hook '{$hook_name}'" : '';

		if ( $logger->is_active() ) {
			$logger->debug( "ScriptsEnqueueTrait::_process_single_asset - Processing script '{$log_handle_context}'{$log_hook_context} in context '{$processing_context}'." );
		}

		if ( empty( $handle ) || ( $do_register && empty( $src ) ) ) {
			if ( $logger->is_active() ) {
				$logger->warning( "ScriptsEnqueueTrait::_process_single_asset - Invalid script definition. Missing handle or src. Skipping. Handle: '{$log_handle_context}'{$log_hook_context}." );
			}
			return false;
		}

		if ( is_callable( $condition ) && ! $condition() ) {
			if ( $logger->is_active() ) {
				$logger->debug( "ScriptsEnqueueTrait::_process_single_asset - Condition not met for script '{$handle}'{$log_hook_context}. Skipping." );
			}
			return false;
		}

		if ( $do_register ) {
			if ( wp_script_is( $handle, 'registered' ) ) {
				if ( $logger->is_active() ) {
					$logger->debug( "ScriptsEnqueueTrait::_process_single_asset - Script '{$handle}'{$log_hook_context} already registered. Skipping wp_register_script." );
				}
			} else {
				if ( $logger->is_active() ) {
					$logger->debug( "ScriptsEnqueueTrait::_process_single_asset - Registering script '{$handle}'{$log_hook_context}." );
				}
				$registration_success = wp_register_script( $handle, $src, $deps, $version, $in_footer );
				if ( ! $registration_success ) {
					if ( $logger->is_active() ) {
						$logger->warning( "ScriptsEnqueueTrait::_process_single_asset - wp_register_script() failed for handle '{$handle}'{$log_hook_context}. Skipping further processing for this asset." );
					}
					return false;
				}
			}
		}

		if ( $do_enqueue ) {
			if ( wp_script_is( $handle, 'enqueued' ) ) {
				if ( $logger->is_active() ) {
					$logger->debug( "ScriptsEnqueueTrait::_process_single_asset - Script '{$handle}'{$log_hook_context} already enqueued. Skipping wp_enqueue_script." );
				}
			} else {
				if ( $logger->is_active() ) {
					$logger->debug( "ScriptsEnqueueTrait::_process_single_asset - Enqueuing script '{$handle}'{$log_hook_context}." );
				}
				wp_enqueue_script( $handle );
			}
		}

		// Process extras (like inline scripts, attributes, data) only in a non-deferred context.
		// For deferred scripts, the calling method (`enqueue_deferred_scripts`) is responsible for inlines.
		if ( null === $hook_name && $handle ) {
			// Process attributes only after a script has been successfully registered.
			// This is because wp_script_add_data() requires a registered handle.
			$attributes_for_tag_modifier = array();
			if ( is_array( $attributes ) && ! empty( $attributes ) ) {
				foreach ( $attributes as $attr_key => $attr_value ) {
					$attr_key_lower = strtolower( (string) $attr_key );
					if ( $attr_key_lower === 'async' && true === $attr_value ) {
						if ( $logger->is_active() ) {
							$logger->debug( "ScriptsEnqueueTrait::_process_single_asset - Routing 'async' attribute for '{$handle}' to wp_script_add_data('strategy', 'async')." );
						}
						if ( ! wp_script_add_data( $handle, 'strategy', 'async' ) && $logger->is_active() ) {
							$logger->warning( "ScriptsEnqueueTrait::_process_single_asset - Failed to add 'async' strategy for '{$handle}' via wp_script_add_data." );
						}
					} elseif ( $attr_key_lower === 'defer' && true === $attr_value ) {
						if ( $logger->is_active() ) {
							$logger->debug( "ScriptsEnqueueTrait::_process_single_asset - Routing 'defer' attribute for '{$handle}' to wp_script_add_data('strategy', 'defer')." );
						}
						if ( ! wp_script_add_data( $handle, 'strategy', 'defer' ) && $logger->is_active() ) {
							$logger->warning( "ScriptsEnqueueTrait::_process_single_asset - Failed to add 'defer' strategy for '{$handle}' via wp_script_add_data." );
						}
					} elseif ( $attr_key_lower === 'src' ) {
						if ( $logger->is_active() ) {
							$logger->debug( "ScriptsEnqueueTrait::_process_single_asset - Ignoring 'src' attribute for '{$handle}' as it is managed by WordPress during registration." );
						}
					} elseif ( $attr_key_lower === 'id' ) {
						if ( $logger->is_active() ) {
							$logger->warning( "ScriptsEnqueueTrait::_process_single_asset - Attempting to set 'id' attribute for '{$handle}'. WordPress typically manages script IDs. Overriding may lead to unexpected behavior or be ineffective." );
						}
						$attributes_for_tag_modifier[ $attr_key ] = $attr_value;
					} else {
						$attributes_for_tag_modifier[ $attr_key ] = $attr_value;
					}
				}
			}

			// Apply attributes that were not routed to wp_script_add_data.
			if ( ! empty( $attributes_for_tag_modifier ) && is_array( $attributes_for_tag_modifier ) ) {
				if ($logger->is_active()) {
					$logger->debug("ScriptsEnqueueTrait::_process_single_asset - Adding attributes for script '{$handle}' via script_loader_tag. Attributes: " . \wp_json_encode($attributes_for_tag_modifier));
				}
				$this->_do_add_filter(
					'script_loader_tag',
					function ( $tag, $tag_handle, $_src ) use ( $handle, $attributes_for_tag_modifier ) {
						return $this->_modify_html_tag_attributes( 'script', $tag, $tag_handle, $handle, $attributes_for_tag_modifier );
					},
					10,
					3
				);
			}

			// Process inline scripts after registration/enqueue.
			$this->_process_inline_assets('script', $handle, null, 'immediate_after_registration');
		}

		if ( $logger->is_active() ) {
			$logger->debug( "ScriptsEnqueueTrait::_process_single_asset - Finished processing script '{$handle}'{$log_hook_context}." );
		}

		return $handle;
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
	protected function _modify_html_tag_attributes(
		string $_asset_type,
		string $tag,
		string $tag_handle,
		string $handle_to_match,
		array $attributes_to_apply
	): string {
		$logger = $this->get_logger();

		// If the filter is not for the script we're interested in, return the original tag.
		if ( $tag_handle !== $handle_to_match ) {
			return $tag;
		}

		if ($logger->is_active()) {
			$logger->debug("ScriptsEnqueueTrait::_modify_html_tag_attributes - Modifying tag for handle '{$handle_to_match}'. Attributes: " . (function_exists('wp_json_encode') ? wp_json_encode($attributes_to_apply) : json_encode($attributes_to_apply)));
		}

		// Special handling for module scripts.
		if ( isset( $attributes_to_apply['type'] ) && 'module' === $attributes_to_apply['type'] ) {
			if ($logger->is_active()) {
				$logger->debug("ScriptsEnqueueTrait::_modify_html_tag_attributes - Script '{$handle_to_match}' is a module. Modifying tag accordingly.");
			}
			// Position type="module" right after <script.
			$tag = preg_replace( '/<script\s/', '<script type="module" ', $tag );
			// Remove type from attributes so it's not added again.
			unset( $attributes_to_apply['type'] );
		}

		// Find the insertion point for attributes. This also serves as tag validation.
		$closing_bracket_pos = strpos( $tag, '>' );
		$script_open_pos     = stripos( $tag, '<script' );

		if ( false === $closing_bracket_pos || false === $script_open_pos ) {
			if ($logger->is_active()) {
				$logger->warning("ScriptsEnqueueTrait::_modify_html_tag_attributes - Malformed script tag for '{$handle_to_match}'. Original tag: " . esc_html($tag) . '. Skipping attribute modification.');
			}
			return $tag;
		}

		$insertion_pos = $closing_bracket_pos;

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
							"%s - Attempt to override managed attribute '%s' for script handle '%s'. This attribute will be ignored.",
							'ScriptsEnqueueTrait::_modify_html_tag_attributes',
							$attr, // Use original case for warning message
							$handle_to_match
						),
						array(
							'handle'    => $handle_to_match,
							'attribute' => $attr,
						)
					);
				}
				continue; // Skip this attribute
			}

			// Boolean attributes (value is true).
			if ( true === $value ) {
				$attr_str .= ' ' . esc_attr( $attr );
			} elseif ( false !== $value && null !== $value && '' !== $value ) { // Regular attributes with non-empty, non-false, non-null values.
				$attr_str .= ' ' . esc_attr( $attr ) . '="' . esc_attr( (string) $value ) . '"';
			}
			// Attributes with false, null, or empty string values are skipped.
		}

		$modified_tag = substr_replace( $tag, $attr_str, $closing_bracket_pos, 0 );

		if ($logger->is_active()) {
			$logger->debug("ScriptsEnqueueTrait::_modify_html_tag_attributes - Successfully modified tag for '{$handle_to_match}'. New tag: " . esc_html($modified_tag));
		}
		return $modified_tag;
	}
}
