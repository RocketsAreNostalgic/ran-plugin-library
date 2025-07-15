<?php
/**
 * Trait StylesEnqueueTrait
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
 * Trait StylesEnqueueTrait
 *
 * Manages the registration, enqueuing, and processing of CSS assets.
 * This includes handling general styles, inline styles and deferred styles,
 * style attributes, and style data.
 *
 * @package Ran\PluginLib\EnqueueAccessory
 */
trait StylesEnqueueTrait {
	use AssetEnqueueBaseTrait;

	/**
	 * Get the array of registered stylesheets.
	 *
	 * @return array<string, array> An associative array of stylesheet definitions, keyed by 'general', 'deferred', and 'inline'.
	 */
	public function get_styles(): array {
		return $this->get_assets(AssetType::Style);
	}

	/**
	 * Adds one or more stylesheet definitions to the internal queue for processing.
	 *
	 * This method supports adding a single style definition (associative array) or an
	 * array of style definitions. Definitions are merged with any existing styles
	 * in the queue. Actual registration and enqueuing occur when `enqueue()` or
	 * `stage_styles()` is called. This method is chainable.
	 *
	 * @param array<string, mixed>|array<int, array<string, mixed>> $styles_to_add A single style definition array or an array of style definition arrays.
	 *     Each style definition array can include the following keys:
	 *     - 'handle'     (string, required): The unique name of the stylesheet.
	 *     - 'src'        (string, required): The URL of the stylesheet resource.
	 *     - 'deps'       (array, optional): An array of handles of other stylesheets this one depends on. Default is empty array.
	 *     - 'version'    (string|false, optional): The version of the stylesheet. `false` for plugin version, `null` for no version. Default is `false`.
	 *     - 'media'      (string, optional): The media for which this stylesheet is intended (e.g., 'all', 'screen'). Default is 'all'.
	 *     - 'condition'  (callable, optional): A callable that returns a boolean. If it returns false, the style will not be processed.
	 *     - 'attributes' (array, optional): An array of key-value pairs to add as attributes to the `<link>` tag.
	 *     - 'data'       (array, optional): An array of key-value pairs to add using `wp_style_add_data()`.
	 *     - 'inline'     (string|callable, optional): Inline CSS to add after the style. Can be a string or a callable returning a string.
	 *     - 'hook'       (string, optional): The WordPress action hook on which to enqueue this style (e.g., 'wp_enqueue_scripts'). Defaults to null for immediate processing.
	 * @return self Returns the instance of this class for method chaining.
	 *
	 * @see self::stage_styles()
	 * @see self::enqueue()
	 */
	public function add_styles( array $styles_to_add ): self {
		return $this->add_assets($styles_to_add, AssetType::Style);
	}

	/**
	 * Registers stylesheets with WordPress without enqueueing them, handling deferred registration.
	 *
	 * This method iterates through the style definitions previously added via `add_styles()`.
	 * For each style:
	 * - If a `hook` is specified in the definition, its registration is deferred. The style
	 *   is moved to the `$deferred_styles` queue, and an action is set up (if not already present)
	 *   to call `_enqueue_deferred_styles()` when the specified hook fires.
	 * - Styles without a `hook` are registered immediately. The registration process
	 *   (handled by `_process_single_asset()`) includes checking any associated
	 *   `$condition` callback and calling `wp_register_style()`.
	 *
	 * Note: This method only *registers* the stylesheets. Enqueuing is handled by
	 * `stage_styles()` or `_enqueue_deferred_styles()`.
	 *
	 * @return self Returns the instance of this class for method chaining.
	 * @see    self::add_styles()
	 * @see    self::_process_single_asset()
	 * @see    self::stage_styles()
	 * @see    self::_enqueue_deferred_styles()
	 * @see    wp_register_style()
	 */
	public function stage_styles(): self {
		return $this->stage_assets(AssetType::Style);
	}

	/**
	 * Processes and enqueues all immediate styles that have been registered.
	 *
	 * This method iterates through the `$this->styles` array, which at this stage should only
	 * contain immediate (non-deferred) styles, as deferred styles are moved to their own
	 * queue by `register_styles()`.
	 *
	 * For each immediate style, it calls `_process_single_asset()` to handle enqueuing and
	 * the processing of any associated inline styles.
	 *
	 * After processing, this method clears the `$this->styles` array. Deferred styles stored
	 * in `$this->deferred_styles` are not affected.
	 *
	 * @return self Returns the instance of this class for method chaining.
	 * @throws \LogicException If a deferred style is found in the queue, indicating `register_styles()` was not called.
	 * @see    self::add_styles()
	 * @see    self::register_styles()
	 * @see    self::_process_single_asset()
	 * @see    self::_enqueue_deferred_styles()
	 */
	public function enqueue_immediate_styles(): self {
		return $this->enqueue_immediate_assets(AssetType::Style);
	}

	/**
	 * Enqueues styles that were deferred to a specific hook and priority.
	 *
	 * @internal This is an internal method called by WordPress as an action callback and should not be called directly.
	 * @param string $hook_name The WordPress hook name that triggered this method.
	 * @param int    $priority  The priority of the action that triggered this callback.
	 * @return void
	 */
	public function _enqueue_deferred_styles( string $hook_name, int $priority ): void {
		$this->_enqueue_deferred_assets( AssetType::Style, $hook_name, $priority );
	}

	/**
	 * Adds one or more inline style definitions to the internal queue.
	 *
	 * This method supports adding a single inline style definition (associative array) or an
	 * array of inline style definitions. This method is chainable.
	 *
	 * @param array<string, mixed>|array<int, array<string, mixed>> $inline_styles_to_add A single inline style definition array or an array of them.
	 *     Each definition array can include:
	 *     - 'handle'    (string, required): Handle of the style to attach the inline style to.
	 *     - 'content'   (string, required): The inline style content.
	 *     - 'position'  (string, optional): 'before' or 'after'. Default 'after'.
	 *     - 'condition' (callable, optional): A callable that returns a boolean. If false, the style is not added.
	 *     - 'parent_hook' (string, optional): Explicitly associate with a parent's hook.
	 * @return self Returns the instance of this class for method chaining.
	 */
	public function add_inline_styles( array $inline_styles_to_add ): self {
		return $this->add_inline_assets( $inline_styles_to_add, AssetType::Style );
	}

	/**
	 * Enqueues inline styles that are not attached to a style being processed in the current lifecycle.
	 *
	 * This method is designed to handle inline styles that target already registered/enqueued styles,
	 * such as those belonging to WordPress core or other plugins. It should be called on a hook
	 * like `wp_enqueue_styles` with a late priority to ensure the target styles are available.
	 *
	 * @internal This is an internal method called by WordPress as an action callback and should not be called directly.
	 * @return void
	 */
	public function _enqueue_external_inline_styles(): void {
		$this->_enqueue_external_inline_assets( AssetType::Style );
	}

	/**
	 * Processes inline assets associated with a specific parent asset handle and hook context.
	 *
	 * @param AssetType $asset_type The type of asset (eg styles in this context) this is a flag for the child method to know what type of asset it is processing.
	 * @param string      $parent_handle      The handle of the parent style.
	 * @param string|null $hook_name          (Optional) The hook name if processing for a deferred context.
	 * @param string      $processing_context A string indicating the context for logging purposes.
	 * @return void
	 */
	protected function _process_inline_style_assets(
		AssetType $asset_type,
		string $parent_handle,
		?string $hook_name = null,
		string $processing_context = 'immediate'
	): void {
		// Use the unified implementation from the base trait
		$this->_concrete_process_inline_assets($asset_type, $parent_handle, $hook_name, $processing_context);
	}

	/**
	 * Processes a single style definition, handling registration, enqueuing, and inline styles.
	 *
	 * This is a versatile helper method that underpins the public-facing style methods. It separates
	 * the logic for handling individual styles, making the main `register_styles` and `stage_styles`
	 * methods cleaner. For non-deferred styles (where `$hook_name` is null), it also handles inline styles.
	 * For deferred styles, this is handled by the calling `enqueue_deferred_styles` method.
	 *
	 * @param AssetType $asset_type The type of asset (eg styles in this context) this is a flag for the child method to know what type of asset it is processing.
	 * @param array{
	 *   handle: string, 		// The unique name of the style.
	 *   src: string|false, 	// Full URL of the style, or path of the style relative to the WordPress root directory. Or false for inline styles. Default empty.
	 *   deps?: string[], 		// Array of registered style handles this style depends on. Defaults to an empty array.
	 *   version?: string|false|null, // Style version. `false` (default) uses plugin version, `null` adds no version, string sets specific version.
	 *   media?: string, 		// Media for which this style should be loaded. Defaults to 'all'.
	 *   attributes?: array<string, mixed>, // Key-value pairs of HTML attributes for the `<style>` tag (e.g., `['async' => true]`). Defaults to an empty array.
	 *   data?: array<string, mixed>, // Key-value pairs of data attributes for the `<style>` tag (e.g., `['data-some-attr' => 'value']`). Defaults to an empty array.
	 *   inline?: array{position: 'before'|'after', content: string, condition?: callable}|array<int, array{position: 'before'|'after', content: string, condition?: callable}>, // Inline style content and position.
	 * }					$asset_definition    	The definition of the sylesheet to process.
	 * @param string        $processing_context  	The context in which the style is being processed (e.g., 'register_styles', 'stage_styles'). Used for logging.
	 * @param string|null   $hook_name           	The hook name if processing in a deferred context, null otherwise.
	 * @param bool          $do_register         	Whether to register the style.
	 * @param bool          $do_enqueue          	Whether to enqueue the style.
	 *
	 * @return string|false The handle of the style on success, false on failure or if a condition is not met.
	 */
	protected function _process_single_style_asset(
		AssetType $asset_type,
		array $asset_definition,
		string $processing_context,
		?string $hook_name = null,
		bool $do_register = true,
		bool $do_enqueue = false
	): string|false {
		$logger  = $this->get_logger();
		$context = __TRAIT__ . '::' . __FUNCTION__;

		if ($asset_type !== AssetType::Style) {
			$logger->warning("{$context} - Incorrect asset type provided to _process_single_style_asset. Expected 'style', got '{$asset_type->value}'.");
			return false;
		}
		
		// Prepare style-specific options
		$media      = $asset_definition['media']      ?? 'all';
		$attributes = $asset_definition['attributes'] ?? array();
		
		// Apply style attributes via filter if needed
		if (is_array($attributes) && !empty($attributes)) {
			$handle = $asset_definition['handle'] ?? null;
			if (!empty($handle)) {
				if ($logger->is_active()) {
					$logger->debug("{$context} - Adding attributes to {$asset_type->value} '{$handle}'.");
				}
				
				// Unlike scripts, styles have no native 'strategy' argument for attributes.
				// All attributes must be applied via the 'style_loader_tag' filter.
				$callback = function($tag, $tag_handle) use ($handle, $attributes) {
					return $this->_modify_style_tag_attributes(AssetType::Style, $tag, $tag_handle, $handle, $attributes);
				};
				$this->_do_add_filter('style_loader_tag', $callback, 10, 2);
			}
		}
		
		$handle = $this->_concrete_process_single_asset(
			$asset_type,
			$asset_definition,
			$processing_context,
			$hook_name,
			$do_register,
			$do_enqueue,
			array('media' => $media)
		);
		
		// If processing was successful and we're enqueueing
		if ($handle !== false && $do_enqueue) {
			// Process any inline styles attached to this asset definition
			$this->_process_inline_style_assets($asset_type, $handle, $hook_name, 'immediate');
			
			// Process style-specific extras
			$this->_process_style_extras($asset_definition, $handle, $hook_name);
		}
		
		return $handle;
	}
	
	/**
	 * Process style-specific extras like data and inline styles.
	 *
	 * @param array       $asset_definition The style asset definition.
	 * @param string      $handle           The style handle.
	 * @param string|null $hook_name        Optional. The hook name.
	 */
	protected function _process_style_extras(array $asset_definition, string $handle, ?string $hook_name): void {
		$logger           = $this->get_logger();
		$context          = __TRAIT__ . '::' . __FUNCTION__;
		$log_hook_context = $hook_name ? " on hook '{$hook_name}'" : '';
		
		$data = $asset_definition['data'] ?? array();
		
		// Process extras (like data and inline).
		if (is_array($data) && !empty($data)) {
			foreach ($data as $key => $value) {
				if ($logger->is_active()) {
					$logger->debug("{$context} - Adding data '{$key}' to style '{$handle}'{$log_hook_context}.");
				}
				wp_style_add_data($handle, $key, $value);
			}
		}

		// Process inline styles defined directly in the asset definition.
		if (!empty($asset_definition['inline'])) {
			$inline_styles = $asset_definition['inline'];
			// Ensure it's an array of arrays for consistent processing.
			if (isset($inline_styles['content'])) {
				$inline_styles = array($inline_styles);
			}

			foreach ($inline_styles as $inline_style) {
				$content   = $inline_style['content']   ?? '';
				$position  = $inline_style['position']  ?? 'after';
				$condition = $inline_style['condition'] ?? null;

				if (is_callable($condition) && !$condition()) {
					if ($logger->is_active()) {
						$logger->debug("{$context} - Condition for inline style on '{$handle}' not met. Skipping.");
					}
					continue;
				}

				if ($logger->is_active()) {
					$logger->debug("{$context} - Adding inline style to '{$handle}' (position: {$position}){$log_hook_context}.");
				}
				wp_add_inline_style($handle, $content, array('position' => $position));
			}
		}
	}

	/**
	 * Modifies the HTML tag for a specific style handle by adding custom attributes.
	 *
	 * This method is used to dynamically add attributes to style tags, such as
	 * adding `media` attributes or other custom attributes. It's typically used
	 * in the `style_loader_tag` filter to modify the output of style tags.
	 *
	 * @param AssetType $asset_type The type of asset (eg styles in this context) this is a flag for the child method to know what type of asset it is processing.
	 * @param string $tag The original HTML tag for the style.
	 * @param string $filter_tag_handle The handle of the style being filtered.
	 * @param string $style_handle_to_match The handle of the style to modify.
	 * @param array $attributes_to_apply An array of attributes to add to the style tag.
	 *
	 * @return string The modified HTML tag with added attributes.
	 */
	protected function _modify_style_tag_attributes(
		AssetType $asset_type,
		string $tag,
		string $tag_handle,
		string $handle_to_match,
		array $attributes_to_apply
	): string {
		$context = __TRAIT__ . '::' . __FUNCTION__;
		$logger  = $this->get_logger();

		if ($asset_type !== AssetType::Style) {
			$logger->warning("{$context}Incorrect asset type provided to _modify_style_tag_attributes. Expected 'style', got '{$asset_type->value}'.");
			return $tag; // Not a style, do not modify.
		}

		// If the filter is not for the style we're interested in, return the original tag.
		if ( $tag_handle !== $handle_to_match ) {
			return $tag;
		}

		if ($logger->is_active()) {
			$logger->debug("{$context} - Modifying {$asset_type->value} tag for handle '{$tag_handle}'. Attributes: " . \wp_json_encode($attributes_to_apply));
		}

		// Find the insertion point for attributes. This also serves as tag validation.
		$closing_bracket_pos = strpos( $tag, '>' );
		$self_closing_pos    = strpos( $tag, '/>' );
		$el_open_pos         = stripos( $tag, '<link' );

		if ( false !== $self_closing_pos ) {
			$insertion_pos = $self_closing_pos;
		} elseif ( false !== $closing_bracket_pos ) {
			$insertion_pos = $closing_bracket_pos;
		} elseif ( false !== $el_open_pos ) {
			$insertion_pos = $el_open_pos;
		} else {
			if ($logger->is_active()) {
				$logger->warning("{$context} - Malformed {$asset_type->value} tag for '{$tag_handle}'. Original tag: " . esc_html($tag) . '.  Skipping attribute modification.');
			}
			return $tag;
		}

		$attr_str = '';
		// Define managed attributes that should not be overridden by users.
		$managed_attributes = array( 'href', 'rel', 'id', 'type' );

		foreach ($attributes_to_apply as $attr => $value) {
			// Handle boolean attributes (indexed array, e.g., ['async'])
			if (is_int($attr)) {
				$attr  = $value;
				$value = true;
			}

			$attr_lower = strtolower((string) $attr);

			// Special handling for 'media' attribute to guide user to the correct definition key.
			if ( 'media' === $attr_lower ) {
				if ($logger->is_active()) {
					$logger->warning("{$context} - Attempted to set 'media' attribute via 'attributes' array for handle '{$tag_handle}'. The 'media' attribute should be set using the dedicated 'media' key in the style definition array.");
				}
				continue;
			}

			// Check for attempts to override other managed attributes.
			if ( in_array( $attr_lower, $managed_attributes, true ) ) {
				if ($logger->is_active()) {
					$logger->warning(
						sprintf(
							"%s - Attempt to override managed attribute '%s' for handle '%s'. This attribute will be ignored.",
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
			if (true === $value) {
				$attr_str .= ' ' . esc_attr($attr_lower);
			} elseif (false !== $value && null !== $value && '' !== $value) { // Regular attributes with non-empty, non-false, non-null values.
				$attr_str .= ' ' . esc_attr($attr_lower) . '="' . esc_attr((string) $value) . '"';
			}
			// Attributes with false, null, or empty string values are skipped.
		}

		$modified_tag = substr_replace( $tag, $attr_str, $insertion_pos, 0 );

		if ($logger->is_active()) {
			$logger->debug("{$context} - Successfully modified {$asset_type->value} tag for '{$tag_handle}'. New tag: " . esc_html($modified_tag));
		}
		return $modified_tag;
	}
}
