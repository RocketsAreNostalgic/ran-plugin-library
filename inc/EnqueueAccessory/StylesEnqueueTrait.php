<?php
declare(strict_types=1);
/**
 * Trait StylesEnqueueTrait
 *
 * @package Ran\PluginLib\EnqueueAccessory
 * @author  Ran Plugin Lib
 * @license GPL-2.0+ <http://www.gnu.org/licenses/gpl-2.0.txt>
 * @link    https://github.com/RocketsAreNostalgic
 * @since   0.1.0
 */

namespace Ran\PluginLib\EnqueueAccessory;

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
	/**
	 * Array of stylesheet definitions to be processed.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	protected array $styles = array();

	/**
	 * Array of inline style definitions to be added.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	protected array $inline_styles = array();

	/**
	 * Array of styles to be loaded at specific WordPress action hooks.
	 *
	 * The outer array keys are hook names (e.g., 'admin_enqueue_scripts'),
	 * and the inner arrays contain style definitions indexed by their original addition order.
	 *
	 * @var array<string, array<int, array<string, mixed>>>
	 */
	protected array $deferred_styles = array();

	/**
	 * Abstract method to get the logger instance.
	 *
	 * This ensures that any class using this trait provides a logger.
	 *
	 * @return Logger The logger instance.
	 */
	abstract public function get_logger(): Logger;

	/**
	 * Retrieves the currently registered array of stylesheet definitions.
	 *
	 * @return array<string, array> An associative array of stylesheet definitions, keyed by 'general', 'deferred', and 'inline'.
	 */
	public function get_styles(): array {
		return array(
			'general'  => $this->styles,
			'deferred' => $this->deferred_styles,
			'inline'   => $this->inline_styles,
		);
	}

	/**
	 * Adds one or more stylesheet definitions to the internal queue for processing.
	 *
	 * This method supports adding a single style definition (associative array) or an
	 * array of style definitions. Definitions are merged with any existing styles
	 * in the queue. Actual registration and enqueuing occur when `enqueue()` or
	 * `enqueue_styles()` is called. This method is chainable.
	 *
	 * @param array<string, mixed>|array<int, array<string, mixed>> $styles_to_add A single style definition array or an array of style definition arrays.
	 *     Each style definition array can include the following keys:
	 *     - 'handle'     (string, required): The unique handle for the style.
	 *     - 'src'        (string, required): The URL of the stylesheet.
	 *     - 'deps'       (array, optional): An array of handles of other stylesheets this one depends on. Default is empty array.
	 *     - 'version'    (string|false, optional): The version of the stylesheet. `false` for plugin version, `null` for no version. Default is `false`.
	 *     - 'media'      (string, optional): The media for which this stylesheet is intended (e.g., 'all', 'screen'). Default is 'all'.
	 *     - 'condition'  (callable, optional): A callable that returns a boolean. If it returns false, the style will not be processed.
	 *     - 'attributes' (array, optional): An array of key-value pairs to add as attributes to the `<link>` tag.
	 *     - 'data'       (array, optional): An array of key-value pairs to add using `wp_style_add_data()`.
	 *     - 'inline'     (string|callable, optional): Inline CSS to add after the style. Can be a string or a callable returning a string.
	 *     - 'hook'       (string, optional): The WordPress action hook on which to enqueue this style (e.g., 'wp_enqueue_scripts'). Default is immediate processing.
	 * @return self Returns the instance of this class for method chaining.
	 *
	 * @see self::enqueue_styles()
	 * @see self::enqueue()
	 */
	public function add_styles( array $styles_to_add ): self {
		$logger = $this->get_logger();

		if ( empty( $styles_to_add ) ) {
			if ( $logger->is_active() ) {
				$logger->debug( 'StylesEnqueueTrait::add_styles - Entered with empty array. No styles to add.' );
			}
			return $this;
		}

		// Merge single styles in to the array
		if ( ! is_array( current( $styles_to_add ) ) ) {
			$styles_to_add = array( $styles_to_add );
		}

		if ( $logger->is_active() ) {
			$logger->debug( 'StylesEnqueueTrait::add_styles - Entered. Current style count: ' . count( $this->styles ) . '. Adding ' . count( $styles_to_add ) . ' new style(s).' );
			foreach ( $styles_to_add as $style_key => $style_data ) {
				$handle = $style_data['handle'] ?? 'N/A';
				$src    = $style_data['src']    ?? 'N/A';
				$logger->debug( "StylesEnqueueTrait::add_styles - Adding style. Key: {$style_key}, Handle: {$handle}, Src: {$src}" );
			}
			$logger->debug( 'StylesEnqueueTrait::add_styles - Adding ' . count( $styles_to_add ) . ' style definition(s). Current total: ' . count( $this->styles ) );
		}

		// Merge new styles with existing ones.
		// array_values ensures that if $styles_to_add has string keys, they are discarded and styles are appended.
		// If $this->styles was empty, it would just become $styles_to_add.
		foreach ( $styles_to_add as $style_definition ) {
			$this->styles[] = $style_definition; // Simple append.
		}
		if ($logger->is_active()) {
			$new_total = count( $this->styles );
			$logger->debug( 'StylesEnqueueTrait::add_styles - Exiting. New total style count: ' . $new_total );
			if ( $new_total > 0 ) {
				$current_handles = array_map( static fn( $a ) => $a['handle'] ?? 'N/A', $this->styles );
				$logger->debug( 'StylesEnqueueTrait::add_styles - All current style handles: ' . implode( ', ', $current_handles ) );
			}
		}
		return $this;
	}

	/**
	 * Registers stylesheets with WordPress without enqueueing them, handling deferred registration.
	 *
	 * This method iterates through the style definitions previously added via `add_styles()`.
	 * For each style:
	 * - If a `hook` is specified in the definition, its registration is deferred. The style
	 *   is moved to the `$deferred_styles` queue, and an action is set up (if not already present)
	 *   to call `enqueue_deferred_styles()` when the specified hook fires.
	 * - Styles without a `hook` are registered immediately. The registration process
	 *   (handled by `_process_single_style()`) includes checking any associated
	 *   `$condition` callback and calling `wp_register_style()`.
	 *
	 * Note: This method only *registers* the stylesheets. Enqueuing is handled by
	 * `enqueue_styles()` or `enqueue_deferred_styles()`.
	 *
	 * @return self Returns the instance of this class for method chaining.
	 * @see    self::add_styles()
	 * @see    self::_process_single_style()
	 * @see    self::enqueue_styles()
	 * @see    self::enqueue_deferred_styles()
	 * @see    wp_register_style()
	 */
	public function register_styles(): self {
		$logger = $this->get_logger();
		if ($logger->is_active()) {
			$logger->debug( 'StylesEnqueueTrait::register_styles - Entered. Processing ' . count( $this->styles ) . ' style definition(s) for registration.' );
		}

		$styles_to_process = $this->styles;
		$this->styles      = array(); // Clear original to re-populate with non-deferred or keep for other ops

		foreach ( $styles_to_process as $index => $style_definition ) {
			$handle_for_log = $style_definition['handle'] ?? 'N/A';
			$hook           = $style_definition['hook']   ?? null;

			if ($logger->is_active()) {
				$logger->debug( "StylesEnqueueTrait::register_styles - Processing style: \"{$handle_for_log}\", original index: {$index}." );
			}

			if ( ! empty( $hook ) ) {
				if ($logger->is_active()) {
					$logger->debug( "StylesEnqueueTrait::register_styles - Deferring registration of style '{$handle_for_log}' (original index {$index}) to hook: {$hook}." );
				}
				$this->deferred_styles[ $hook ][ $index ] = $style_definition;

				// Ensure the action for deferred styles is added only once per hook.
				$action_exists = has_action( $hook, array( $this, 'enqueue_deferred_styles' ) );
				if ( ! $action_exists ) {
					add_action( $hook, array( $this, 'enqueue_deferred_styles' ), 10, 1 );
					if ($logger->is_active()) {
						$logger->debug( "StylesEnqueueTrait::register_styles - Added action for 'enqueue_deferred_styles' on hook: {$hook}." );
					}
				} else {
					if ($logger->is_active()) {
						$logger->debug( "StylesEnqueueTrait::register_styles - Action for 'enqueue_deferred_styles' on hook '{$hook}' already exists." );
					}
				}
			} else {
				// Process immediately for registration
				$processed_handle = $this->_process_single_style(
					$style_definition,
					'register_styles', // processing_context
					null,             // hook_name (null for immediate registration)
					true,             // do_register
					false            // do_enqueue (registration only)
				);
				// Re-add to $this->styles if it was meant for immediate registration and not deferred,
				// AND if it was successfully processed (i.e., _process_single_style returned a handle).
				// This ensures it's available for enqueue_styles().
				if ($processed_handle) {
					$this->styles[$index] = $style_definition;
				}
			}
		}
		if ($logger->is_active()) {
			$deferred_count = empty($this->deferred_styles) ? 0 : array_sum(array_map('count', $this->deferred_styles));
			$logger->debug( 'StylesEnqueueTrait::register_styles - Exited. Remaining immediate styles: ' . count($this->styles) . '. Deferred styles: ' . $deferred_count . '.' );
		}
		return $this;
	}

	/**
	 * Processes and enqueues all immediate styles that have been registered.
	 *
	 * This method iterates through the `$this->styles` array, which at this stage should only
	 * contain immediate (non-deferred) styles, as deferred styles are moved to their own
	 * queue by `register_styles()`.
	 *
	 * For each immediate style, it calls `_process_single_style()` to handle enqueuing and
	 * the processing of any associated inline styles.
	 *
	 * After processing, this method clears the `$this->styles` array. Deferred styles stored
	 * in `$this->deferred_styles` are not affected.
	 *
	 * @return self Returns the instance of this class for method chaining.
	 * @throws \LogicException If a deferred style is found in the queue, indicating `register_styles()` was not called.
	 * @see    self::add_styles()
	 * @see    self::register_styles()
	 * @see    self::_process_single_style()
	 * @see    self::enqueue_deferred_styles()
	 */
	public function enqueue_styles(): self {
		$logger = $this->get_logger();
		if ($logger->is_active()) {
			$logger->debug( 'StylesEnqueueTrait::enqueue_styles - Entered. Processing ' . count( $this->styles ) . ' style definition(s) from internal queue.' );
		}

		$styles_to_process = $this->styles;
		$this->styles      = array(); // Clear the main queue, as we are processing all of them now.

		foreach ( $styles_to_process as $index => $style_definition ) {
			$handle_for_log = $style_definition['handle'] ?? 'N/A';

			// Check for mis-queued deferred assets. This is a critical logic error.
			if ( ! empty( $style_definition['hook'] ) ) {
				throw new \LogicException(
					"StylesEnqueueTrait::enqueue_styles - Found a deferred style ('{$handle_for_log}') in the immediate queue. " .
					'The `register_styles()` method must be called before `enqueue_styles()` to correctly process deferred styles.'
				);
			}

			if ($logger->is_active()) {
				$logger->debug( "StylesEnqueueTrait::enqueue_styles - Processing style: \"{$handle_for_log}\", original index: {$index}." );
			}

			// Defensively register and enqueue. The underlying `_process_single_style`
			// will check if the style is already registered/enqueued and skip redundant calls.
			$this->_process_single_style(
				$style_definition,
				'enqueue_styles', // processing_context
				null,             // hook_name (null for immediate registration)
				true,             // do_register
				true              // do_enqueue
			);
		}
		if ($logger->is_active()) {
			$deferred_count = empty($this->deferred_styles) ? 0 : array_sum(array_map('count', $this->deferred_styles));
			$logger->debug( 'StylesEnqueueTrait::enqueue_styles - Exited. Deferred styles count: ' . $deferred_count . '.' );
		}
		return $this;
	}

	/**
	 * Enqueues styles that were deferred to a specific hook.
	 *
	 * @param string $hook_name The WordPress hook name that triggered this method.
	 * @return void
	 */
	public function enqueue_deferred_styles( string $hook_name ): void {
		$logger = $this->get_logger();
		if ($logger->is_active()) {
			$logger->debug( "StylesEnqueueTrait::enqueue_deferred_styles - Entered hook: \"{$hook_name}\"." );
		}

		if ( ! isset( $this->deferred_styles[ $hook_name ] ) ) {
			if ($logger->is_active()) {
				$logger->debug( "StylesEnqueueTrait::enqueue_deferred_styles - Hook \"{$hook_name}\" not found in deferred styles. Nothing to process." );
			}
			return;
		}

		$styles_on_this_hook = $this->deferred_styles[ $hook_name ];
		unset( $this->deferred_styles[ $hook_name ] ); // Moved unset action here

		if ( empty( $styles_on_this_hook ) ) {
			if ( $logger->is_active() ) {
				$logger->debug( "StylesEnqueueTrait::enqueue_deferred_styles - Hook \"{$hook_name}\" was set but had no styles. It has now been cleared." );
			}
			return; // No actual styles to process for this hook.
		}

		foreach ( $styles_on_this_hook as $original_index => $style_definition ) {
			$handle_for_log = $style_definition['handle'] ?? 'N/A_at_original_index_' . $original_index;
			if ($logger->is_active()) {
				$logger->debug( "StylesEnqueueTrait::enqueue_deferred_styles - Processing deferred style: \"{$handle_for_log}\" (original index {$original_index}) for hook: \"{$hook_name}\"." );
			}
			// _process_single_style handles all logic including registration, enqueuing, and conditions.
			$processed_handle = $this->_process_single_style(
				$style_definition,
				'enqueue_deferred', // processing_context
				$hook_name,         // hook_name
				true,               // do_register
				true               // do_enqueue
			);

			if ( $processed_handle ) {
				$this->_process_inline_styles($processed_handle, $hook_name, 'deferred from enqueue_deferred_styles');
			}
		}

		if ($logger->is_active()) {
			$logger->debug( "StylesEnqueueTrait::enqueue_deferred_styles - Exited for hook: \"{$hook_name}\"." );
		}
	}

	/**
	 * Chain-able call to add inline styles.
	 *
	 * @param string      $handle     (required) Handle of the style to attach the inline style to.
	 * @param string      $content    (required) The inline style content.
	 * @param string      $position   (optional) Whether to add the inline style before or after. Default 'after'.
	 * @param callable|null $condition  (optional) Callback that determines if the inline style should be added.
	 * @param string|null $parent_hook (optional) The WordPress hook name that the parent style is deferred to.
	 * @return self
	 */
	public function add_inline_styles( string $handle, string $content, string $position = 'after', ?callable $condition = null, ?string $parent_hook = null ): self {
		$logger = $this->get_logger();
		if ( $logger->is_active() ) {
			$logger->debug( 'StylesEnqueueTrait::add_inline_styles - Entered. Current inline style count: ' . count( $this->inline_styles ) . '. Adding new inline style for handle: ' . \esc_html( $handle ) );
		}

		$inline_style_item = array(
			'handle'      => $handle,
			'content'     => $content,
			'position'    => $position,
			'condition'   => $condition,
			'parent_hook' => $parent_hook,
		);

		// Associate inline style with its parent's hook if the parent is deferred.
		foreach ( $this->styles as $original_style_definition ) {
			if ( ( $original_style_definition['handle'] ?? null ) === $handle && ! empty( $original_style_definition['hook'] ) ) {
				if ( null === $inline_style_item['parent_hook'] ) {
					$inline_style_item['parent_hook'] = $original_style_definition['hook'];
				}
				if ( $logger->is_active() ) {
					$logger->debug( "StylesEnqueueTrait::add_inline_styles - Inline style for '{$handle}' associated with parent hook: '{$inline_style_item['parent_hook']}'. Original parent style hook: '" . ( $original_style_definition['hook'] ?? 'N/A' ) . "'." );
				}
				break;
			}
		}

		$this->inline_styles[] = $inline_style_item;

		if ( $logger->is_active() ) {
			$logger->debug( 'StylesEnqueueTrait::add_inline_styles - Exiting. New total inline style count: ' . count( $this->inline_styles ) );
		}
		return $this;
	}

	/**
	 * Process and add all registered inline styles.
	 *
	 * @return self
	 */
	public function enqueue_inline_styles(): self {
		$logger = $this->get_logger();
		if ($logger->is_active()) {
			$logger->debug( 'StylesEnqueueTrait::enqueue_inline_styles - Entered method.' );
		}

		$immediate_parent_handles = array();
		foreach ( $this->inline_styles as $key => $inline_style_data ) {
			if (!is_array($inline_style_data)) {
				if ($logger->is_active()) {
					$logger->warning("StylesEnqueueTrait::enqueue_inline_styles - Invalid inline style data at key '{$key}'. Skipping.");
				}
				continue;
			}
			$parent_hook = $inline_style_data['parent_hook'] ?? null;
			$handle      = $inline_style_data['handle']      ?? null;

			if ( empty( $parent_hook ) && !empty($handle) ) {
				if ( !in_array($handle, $immediate_parent_handles, true) ) {
					$immediate_parent_handles[] = $handle;
				}
			}
		}

		if (empty($immediate_parent_handles)) {
			if ($logger->is_active()) {
				$logger->debug( 'StylesEnqueueTrait::enqueue_inline_styles - No immediate inline styles found needing processing.' );
			}
			return $this;
		}

		if ($logger->is_active()) {
			$logger->debug( 'StylesEnqueueTrait::enqueue_inline_styles - Found ' . count($immediate_parent_handles) . ' unique parent handle(s) with immediate inline styles to process: ' . implode(', ', array_map('esc_html', $immediate_parent_handles) ) );
		}

		foreach ( $immediate_parent_handles as $parent_handle_to_process ) {
			$this->_process_inline_styles(
				$parent_handle_to_process,
				null, // hook_name (null for immediate)
				'enqueue_inline_styles' // processing_context
			);
		}

		//Clear the processed immediate inline assets from the main queue.
		$this->inline_styles = array_filter($this->inline_styles, function($asset) {
			return !empty($asset['parent_hook']);
		});

		if ( $logger->is_active() ) {
			$remaining_count = count($this->inline_styles);
			$logger->debug('StylesEnqueueTrait::enqueue_inline_styles - Exited. Processed ' . count($immediate_parent_handles) . " parent handle(s). Remaining deferred inline styles: {$remaining_count}.");
		}
		return $this;
	}

	/**
	 * Processes inline styles associated with a specific parent style handle and hook context.
	 *
	 * @param string      $parent_handle      The handle of the parent style.
	 * @param string|null $hook_name          (Optional) The hook name if processing for a deferred context.
	 * @param string      $processing_context A string indicating the context for logging purposes.
	 * @return void
	 */
	protected function _process_inline_styles(string $parent_handle, ?string $hook_name = null, string $processing_context = 'immediate'): void {
		$logger          = $this->get_logger();
		$log_prefix_base = "StylesEnqueueTrait::_process_inline_styles (context: {$processing_context}) - ";

		$logger->debug( "{$log_prefix_base}Checking for inline styles for parent handle '{$parent_handle}'" . ($hook_name ? " on hook '{$hook_name}'." : '.') );

		// Check if the parent style is registered or enqueued before processing its inline styles.
		// This is a crucial check to prevent adding inline styles to a non-existent parent.
		if ( ! wp_style_is( $parent_handle, 'registered' ) && ! wp_style_is( $parent_handle, 'enqueued' ) ) {
			$logger->error( "{$log_prefix_base}Cannot add inline styles. Parent style '{$parent_handle}' is not registered or enqueued." );
			return;
		}

		$keys_to_unset = array();

		foreach ( $this->inline_styles as $key => $inline_style_data ) {
			if (!is_array($inline_style_data)) {
				$logger->warning("{$log_prefix_base} Invalid inline style data at key '{$key}'. Skipping.");
				continue;
			}

			$inline_target_handle = $inline_style_data['handle']      ?? null;
			$inline_parent_hook   = $inline_style_data['parent_hook'] ?? null;
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
				$content          = $inline_style_data['content']   ?? '';
				$position         = $inline_style_data['position']  ?? 'after';
				$condition_inline = $inline_style_data['condition'] ?? null;

				if ( is_callable( $condition_inline ) && ! $condition_inline() ) {
					$logger->debug( "{$log_prefix_base}Condition false for inline style targeting '{$parent_handle}' (key: {$key})" . ($hook_name ? " on hook '{$hook_name}'." : '.') );
					$keys_to_unset[] = $key;
					continue;
				}

				if ( empty( $content ) ) {
					$logger->warning( "{$log_prefix_base}Empty content for inline style targeting '{$parent_handle}' (key: {$key})" . ($hook_name ? " on hook '{$hook_name}'." : '.') . ' Skipping addition.' );
					$keys_to_unset[] = $key;
					continue;
				}

				$logger->debug( "{$log_prefix_base}Adding inline style for '{$parent_handle}' (key: {$key}, position: {$position})" . ($hook_name ? " on hook '{$hook_name}'." : '.') );
				wp_add_inline_style( $parent_handle, $content, $position );
				$keys_to_unset[] = $key;
			}
		}

		if ( ! empty( $keys_to_unset ) ) {
			foreach ( $keys_to_unset as $key_to_unset ) {
				if (isset($this->inline_styles[$key_to_unset])) {
					$removed_handle_for_log = $this->inline_styles[$key_to_unset]['handle'] ?? 'N/A';
					unset( $this->inline_styles[ $key_to_unset ] );
					$logger->debug( "{$log_prefix_base}Removed processed inline style with key '{$key_to_unset}' for handle '{$removed_handle_for_log}'" . ($hook_name ? " on hook '{$hook_name}'." : '.') );
				}
			}
			$this->inline_styles = array_values( $this->inline_styles );
		} else {
			$logger->debug( "{$log_prefix_base}No inline styles found or processed for '{$parent_handle}'" . ($hook_name ? " on hook '{$hook_name}'." : '.') );
		}
	}

	/**
	 * Processes a single style definition, handling registration, enqueuing, and inline styles.
	 *
	 * This is a versatile helper method that underpins the public-facing style methods. It separates
	 * the logic for handling individual styles, making the main `register_styles` and `enqueue_styles`
	 * methods cleaner. For non-deferred styles (where `$hook_name` is null), it also handles inline styles.
	 * For deferred styles, this is handled by the calling `enqueue_deferred_styles` method.
	 *
	 * @param array<string, mixed> $style_definition    The style definition array.
	 * @param string               $processing_context  Context of the call.
	 * @param string|null          $hook_name           The hook name if processing in a deferred context, null otherwise.
	 * @param bool                 $do_register         Whether to register the style.
	 * @param bool                 $do_enqueue          Whether to enqueue the style.
	 *
	 * @return string|false The handle of the style on success, false on failure or if a condition is not met.
	 */
	protected function _process_single_style(
		array $style_definition,
		string $processing_context,
		?string $hook_name = null,
		bool $do_register = true,
		bool $do_enqueue = false
	): string|false {
		$logger = $this->get_logger();

		$handle     = $style_definition['handle']     ?? null;
		$src        = $style_definition['src']        ?? null;
		$deps       = $style_definition['deps']       ?? array();
		$version    = $style_definition['version']    ?? false;
		$media      = $style_definition['media']      ?? 'all';
		$attributes = $style_definition['attributes'] ?? array();
		$data       = $style_definition['data']       ?? array();
		$condition  = $style_definition['condition']  ?? null;

		$log_handle_context = $handle ?? 'N/A';
		$log_hook_context   = $hook_name ? " on hook '{$hook_name}'" : '';

		if ( $logger->is_active() ) {
			$logger->debug( "StylesEnqueueTrait::_process_single_style - Processing style '{$log_handle_context}'{$log_hook_context} in context '{$processing_context}'." );
		}

		if ( empty( $handle ) || ( $do_register && empty( $src ) ) ) {
			if ( $logger->is_active() ) {
				$logger->warning( "StylesEnqueueTrait::_process_single_style - Invalid style definition. Missing handle or src. Skipping. Handle: '{$log_handle_context}'{$log_hook_context}." );
			}
			return false;
		}

		if ( is_callable( $condition ) && ! $condition() ) {
			if ( $logger->is_active() ) {
				$logger->debug( "StylesEnqueueTrait::_process_single_style - Condition not met for style '{$handle}'{$log_hook_context}. Skipping." );
			}
			return false;
		}

		if ( $do_register ) {
			if ( wp_style_is( $handle, 'registered' ) ) {
				if ( $logger->is_active() ) {
					$logger->debug( "StylesEnqueueTrait::_process_single_style - Style '{$handle}'{$log_hook_context} already registered. Skipping wp_register_style." );
				}
			} else {
				if ( $logger->is_active() ) {
					$logger->debug( "StylesEnqueueTrait::_process_single_style - Registering style '{$handle}'{$log_hook_context}." );
				}
				$registration_success = wp_register_style( $handle, $src, $deps, $version, $media );
				if ( ! $registration_success ) {
					if ( $logger->is_active() ) {
						$logger->warning( "StylesEnqueueTrait::_process_single_style - wp_register_style() failed for handle '{$handle}'{$log_hook_context}. Skipping further processing for this style." );
					}
					return false;
				}
			}
		}

		if ( $do_enqueue ) {
			if ( wp_style_is( $handle, 'enqueued' ) ) {
				if ( $logger->is_active() ) {
					$logger->debug( "StylesEnqueueTrait::_process_single_style - Style '{$handle}'{$log_hook_context} already enqueued. Skipping wp_enqueue_style." );
				}
			} else {
				if ( $logger->is_active() ) {
					$logger->debug( "StylesEnqueueTrait::_process_single_style - Enqueuing style '{$handle}'{$log_hook_context}." );
				}
				wp_enqueue_style( $handle );
			}
		}

		// Process extras (like inline styles, attributes) only in a non-deferred context.
		// For deferred styles, the calling method (`enqueue_deferred_styles`) is responsible for inlines.
		if ( null === $hook_name && $handle ) {
			// Process data with wp_style_add_data
			if ( is_array( $data ) && ! empty( $data ) ) {
				foreach ( $data as $key => $value ) {
					if ( $logger->is_active() ) {
						$logger->debug( "StylesEnqueueTrait::_process_single_style - Adding data to style '{$handle}'. Key: '{$key}', Value: '{$value}'." );
					}
					if ( ! wp_style_add_data( $handle, (string) $key, $value ) && $logger->is_active() ) {
						$logger->warning( "StylesEnqueueTrait::_process_single_style - Failed to add data for key '{$key}' to style '{$handle}'." );
					}
				}
			}

			// Process attributes only after a style has been successfully registered.
			// This is because wp_style_add_data() requires a registered handle.
			if ( is_array( $attributes ) && ! empty( $attributes ) ) {
				if ($logger->is_active()) {
					$logger->debug("StylesEnqueueTrait::_process_single_style - Adding attributes for style '{$handle}' via style_loader_tag. Attributes: " . \wp_json_encode($attributes));
				}
				$this->_do_add_filter(
					'style_loader_tag',
					function ( $tag, $tag_handle ) use ( $handle, $attributes ) {
						return $this->_modify_style_tag_for_attributes( $tag, $tag_handle, $handle, $attributes );
					},
					10,
					2
				);
			}

			// Process inline styles after registration/enqueue.
			$this->_process_inline_styles( $handle, null, $processing_context );
		}

		if ( $logger->is_active() ) {
			$logger->debug( "StylesEnqueueTrait::_process_single_style - Finished processing style '{$handle}'{$log_hook_context}." );
		}

		return $handle;
	}

	/**
	 * Modifies the HTML tag for a specific style handle by adding custom attributes.
	 *
	 * This method is used to dynamically add attributes to style tags, such as
	 * adding `media` attributes or other custom attributes. It's typically used
	 * in the `style_loader_tag` filter to modify the output of style tags.
	 *
	 * @param string $tag The original HTML tag for the style.
	 * @param string $filter_tag_handle The handle of the style being filtered.
	 * @param string $style_handle_to_match The handle of the style to modify.
	 * @param array $attributes_to_apply An array of attributes to add to the style tag.
	 *
	 * @return string The modified HTML tag with added attributes.
	 */
	protected function _modify_style_tag_for_attributes(
		string $tag,
		string $tag_handle,
		string $handle_to_match,
		array $attributes_to_apply
	): string {
		$logger = $this->get_logger();

		// If the filter is not for the style we're interested in, return the original tag.
		if ( $tag_handle !== $handle_to_match ) {
			return $tag;
		}

		if ($logger->is_active()) {
			$logger->debug("StylesEnqueueTrait::_modify_style_tag_for_attributes - Modifying tag for handle '{$tag_handle}'. Attributes: " . \wp_json_encode($attributes_to_apply));
		}

		// Find the insertion point for attributes. This also serves as tag validation.
		$closing_bracket_pos = strpos( $tag, '>' );
		$self_closing_pos    = strpos( $tag, '/>' );
		$script_open_pos     = stripos( $tag, '<link' );

		if ( false !== $self_closing_pos ) {
			$insertion_pos = $self_closing_pos;
		} elseif ( false !== $closing_bracket_pos ) {
			$insertion_pos = $closing_bracket_pos;
		} elseif ( false !== $script_open_pos ) {
			$insertion_pos = $script_open_pos;
		} else {
			if ($logger->is_active()) {
				$logger->warning("StylesEnqueueTrait::_modify_style_tag_for_attributes - Malformed style tag for '{$tag_handle}'. Original tag: " . esc_html($tag) . '.  Skipping attribute modification.');
			}
			return $tag;
		}

		$attr_str = '';
		// Define managed attributes that should not be overridden by users.
		$managed_attributes = array( 'href', 'rel', 'id', 'type' );

		foreach ( $attributes_to_apply as $attr => $value ) {
			$attr_lower = strtolower( $attr );

			// Special handling for 'media' attribute to guide user to the correct definition key.
			if ( 'media' === $attr_lower ) {
				if ($logger->is_active()) {
					$logger->warning("StylesEnqueueTrait::_modify_style_tag_for_attributes - Attempted to set 'media' attribute via 'attributes' array for handle '{$tag_handle}'. The 'media' attribute should be set using the dedicated 'media' key in the style definition array.");
				}
				continue;
			}

			// Check for attempts to override other managed attributes.
			if ( in_array( $attr_lower, $managed_attributes, true ) ) {
				if ($logger->is_active()) {
					$logger->warning(
						sprintf(
							"%s - Attempt to override managed attribute '%s' for style handle '%s'. This attribute will be ignored.",
							__METHOD__,
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

		$modified_tag = substr_replace( $tag, $attr_str, $insertion_pos, 0 );

		if ($logger->is_active()) {
			$logger->debug("StylesEnqueueTrait::_modify_style_tag_for_attributes - Successfully modified tag for '{$tag_handle}'. New tag: " . esc_html($modified_tag));
		}
		return $modified_tag;
	}
}
