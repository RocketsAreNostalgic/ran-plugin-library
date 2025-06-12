<?php
/**
 * StylesEnqueueTrait.php
 *
 * @package Ran\PluginLib\EnqueueAccessory
 * @author  Ran Plugin Lib <support@ran.org>
 * @license GPL-2.0+ <http://www.gnu.org/licenses/gpl-2.0.txt>
 * @link    https://github.com/RocketsAreNostalgic
 * @since   0.1.0
 */

namespace Ran\PluginLib\EnqueueAccessory;

use Ran\PluginLib\Util\Logger;

/**
 * Trait StylesEnqueueTrait
 *
 * Manages the registration, enqueuing, and processing of CSS stylesheets,
 * including inline styles and deferred loading.
 *
 * @package Ran\PluginLib\EnqueueAccessory
 */
trait StylesEnqueueTrait {
	/**
	 * Array of stylesheet definitions to be processed.
	 *
	 * Each inner array should conform to the structure expected by `add_styles()`.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	protected array $styles = array();

	/**
	 * Array of inline style definitions to be added.
	 *
	 * Each inner array should conform to the structure expected by `add_inline_styles()`.
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
	 * Retrieves the currently registered array of stylesheet definitions.
	 *
	 * @return array<int, array<string, mixed>> An array of stylesheet definitions.
	 *                                          Each definition is an associative array with keys like 'handle', 'src', 'deps', etc.
	 */
	public function get_styles(): array {
		$logger = $this->get_logger();
		if ( $logger->is_active() ) {
			$count = count( $this->styles );
			$logger->debug( "StylesEnqueueTrait::get_styles - Retrieving {$count} style definition(s)." );
		}
		return array(
			'general'  => $this->styles,
			'deferred' => $this->deferred_styles,
			'inline'   => $this->inline_styles,
		);
	}

	/**
	 * Adds one or more stylesheet definitions to the internal queue for processing.
	 *
	 * This method supports adding a single style definition or an array of definitions.
	 * Each definition is an associative array specifying the style's properties.
	 *
	 * @see    self::enqueue_styles()
	 * @see    self::enqueue()
	 *
	 * @param array<string, mixed>|array<int, array<string, mixed>> $styles_to_add A single style definition array or an array of style definition arrays.
	 *     Each style definition array should include:
	 *     - 'handle' (string, required): Name of the stylesheet. Must be unique.
	 *     - 'src' (string, required): URL to the stylesheet resource.
	 *     - 'deps' (array, optional): An array of registered stylesheet handles this stylesheet depends on. Defaults to an empty array.
	 *     - 'version' (string|false|null, optional): Stylesheet version. `false` (default) uses plugin version, `null` adds no version, string sets specific version.
	 *     - 'media' (string, optional): The media for which this stylesheet has been defined (e.g., 'all', 'screen', 'print'). Defaults to 'all'.
	 *     - 'condition' (callable|null, optional): Callback returning boolean. If false, style is not enqueued. Defaults to null.
	 *     - 'hook' (string|null, optional): WordPress hook (e.g., 'admin_enqueue_scripts') to defer enqueuing. Defaults to null (immediate processing).
	 * @return self Returns the instance of this class for method chaining.
	 */
	public function add_styles( array $styles_to_add ): self {
		$logger = $this->get_logger();
		if ( ! is_array( current( $styles_to_add ) ) ) {
			$styles_to_add = array( $styles_to_add );
		}
		if ($logger->is_active()) {
			$logger->debug( 'StylesEnqueueTrait::add_styles - Adding ' . count( $styles_to_add ) . ' style definition(s). Current total: ' . count( $this->styles ) );
		}
		foreach ( $styles_to_add as $style_definition ) {
			$this->styles[] = $style_definition;
		}
		if ($logger->is_active()) {
			$logger->debug( 'StylesEnqueueTrait::add_styles - Finished adding styles. New total: ' . count( $this->styles ) );
		}
		return $this;
	}

	/**
	 * Registers all stylesheets that have been added via `add_styles()` but not yet processed.
	 *
	 * This method iterates through the stored style definitions. For each style:
	 * - It checks any associated `$condition` callback. If the condition passes (or none is set),
	 *   the style is registered using `wp_register_style()`.
	 * - If a `$hook` is specified in the style definition, its registration (and subsequent enqueuing)
	 *   is deferred. The style definition is moved to `$this->deferred_styles` and an action
	 *   is set up (if not already) for `enqueue_deferred_styles()` to handle it when the hook fires.
	 * - Styles without a `$hook` are registered immediately.
	 *
	 * Note: This method only *registers* the styles. Enqueuing is handled by `enqueue_styles()`
	 * or `enqueue_deferred_styles()`.
	 *
	 * @return self Returns the instance of this class for method chaining.
	 * @see    self::add_styles()
	 * @see    self::enqueue_styles()
	 * @see    self::enqueue_deferred_styles()
	 * @see    wp_register_style()
	 */
	public function register_styles(): self {
		$logger = $this->get_logger();
		if ($logger->is_active()) {
			$logger->debug( 'StylesEnqueueTrait::register_styles - Entered. Processing ' . count( $this->styles ) . ' style definition(s) for registration.' );
		}

		// Use a temporary array to iterate over, allowing modification of $this->styles (e.g., for deferral)
		$styles_to_process = $this->styles;
		$this->styles      = array(); // Clear original to re-populate with non-deferred or keep for other ops

		foreach ( $styles_to_process as $index => $style_definition ) {
			$handle_for_log = $style_definition['handle'] ?? 'N/A';
			$hook           = $style_definition['hook']   ?? null;

			if ( ! empty( $hook ) ) {
				if ($logger->is_active()) {
					$logger->debug( "StylesEnqueueTrait::register_styles - Deferring registration of style '{$handle_for_log}' (original index {$index}) to hook: {$hook}." );
				}
				$this->deferred_styles[ $hook ][ $index ] = $style_definition;
				// Ensure the action for deferred styles is added only once per hook.
				if ( ! has_action( $hook, array( $this, 'enqueue_deferred_styles' ) ) ) {
					add_action( $hook, array( $this, 'enqueue_deferred_styles' ), 10, 1 );
					if ($logger->is_active()) {
						$logger->debug( "StylesEnqueueTrait::register_styles - Added action for 'enqueue_deferred_styles' on hook: {$hook}." );
					}
				}
			} else {
				// Process immediately for registration (do_register=true, do_enqueue=false)
				$this->_process_single_style(
					$style_definition,
					'register_styles', // processing_context
					null,             // hook_name (null for immediate registration)
					true,             // do_register
					false,            // do_enqueue (registration only)
					false             // do_process_inline (inline styles handled during enqueue phase)
				);
				// Re-add to $this->styles if it was meant for immediate registration and not deferred.
				// This ensures it's available for enqueue_styles().
				$this->styles[] = $style_definition;
			}
		}
		if ($logger->is_active()) {
			$logger->debug( 'StylesEnqueueTrait::register_styles - Exited. Remaining immediate styles: ' . count($this->styles) . '. Deferred styles: ' . count($this->deferred_styles, COUNT_RECURSIVE) - count($this->deferred_styles) . '.' );
		}
		return $this;
	}

	/**
	 * Processes and enqueues all stylesheets that have been added via `add_styles()`.
	 *
	 * This method iterates through the internally stored stylesheet definitions. For each style,
	 * it first checks any associated `$condition` callback. If the condition passes
	 * (or if no condition is set), the style is then processed.
	 * - If a `$hook` is specified, the style's enqueuing is deferred: it's stored
	 *   in a separate deferred queue, and an action is registered (if not already) for the specified hook.
	 *   When the hook fires, `enqueue_deferred_styles()` will process the style.
	 * - Otherwise (no `$hook`), the style is registered and enqueued immediately.
	 *
	 * After this method runs, the internal style queue (`$this->styles`) will be empty.
	 *
	 * @return self Returns the instance of this class for method chaining.
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
			if ($logger->is_active()) {
				$logger->debug( "StylesEnqueueTrait::enqueue_styles - Processing style: \"{$handle_for_log}\", original index: {$index}." );
			}
			$hook = $style_definition['hook'] ?? null;

			if ( ! empty( $hook ) ) {
				// Defer this style.
				if ($logger->is_active()) {
					$logger->debug( "StylesEnqueueTrait::enqueue_styles - Deferring style \"{$handle_for_log}\" (original index {$index}) to hook: \"{$hook}\"." );
				}
				$this->deferred_styles[ $hook ][ $index ] = $style_definition;

				if ( ! has_action( $hook, array( $this, 'enqueue_deferred_styles' ) ) ) {
					add_action( $hook, array( $this, 'enqueue_deferred_styles' ), 10, 1 );
					if ($logger->is_active()) {
						$logger->debug( "StylesEnqueueTrait::enqueue_styles - Added action for 'enqueue_deferred_styles' on hook: \"{$hook}\"." );
					}
				} else {
					if ($logger->is_active()) {
						$logger->debug( "StylesEnqueueTrait::enqueue_styles - Action for 'enqueue_deferred_styles' on hook '{$hook}' already exists." );
					}
				}
			} else {
				// Process immediately: register, enqueue, and handle inline styles.
				$this->_process_single_style(
					$style_definition,
					'enqueue_styles', // processing_context
					null,             // hook_name
					true,             // do_register
					true,             // do_enqueue
					true              // do_process_inline
				);
			}
		}
		if ($logger->is_active()) {
			$deferred_count = empty($this->deferred_styles) ? 0 : count($this->deferred_styles, COUNT_RECURSIVE) - count($this->deferred_styles);
			$logger->debug( 'StylesEnqueueTrait::enqueue_styles - Exited. Deferred styles count: ' . $deferred_count . '.' );
		}
		return $this;
	}

	/**
	 * Enqueues styles that were deferred to a specific WordPress hook.
	 *
	 * @param string $hook_name The WordPress hook name that triggered this method.
	 * @return void
	 */
	public function enqueue_deferred_styles( string $hook_name ): void {
		$logger = $this->get_logger();
		if ($logger->is_active()) {
			$logger->debug( "StylesEnqueueTrait::enqueue_deferred_styles - Entered for hook: \"{$hook_name}\"." );
		}

		if ( ! isset( $this->deferred_styles[ $hook_name ] ) || empty( $this->deferred_styles[ $hook_name ] ) ) {
			if ($logger->is_active()) {
				$logger->debug( "StylesEnqueueTrait::enqueue_deferred_styles - No styles found deferred for hook: \"{$hook_name}\". Exiting." );
			}
			unset( $this->deferred_styles[ $hook_name ] );
			return;
		}

		$styles_for_hook = $this->deferred_styles[ $hook_name ];

		foreach ( $styles_for_hook as $original_index => $style_definition ) {
			$handle_for_log = $style_definition['handle'] ?? 'N/A_at_original_index_' . $original_index;
			if ($logger->is_active()) {
				$logger->debug( "StylesEnqueueTrait::enqueue_deferred_styles - Processing deferred style: \"{$handle_for_log}\" (original index {$original_index}) for hook: \"{$hook_name}\"." );
			}

			$this->_process_single_style(
				$style_definition,
				'enqueue_deferred', // processing_context
				$hook_name,         // hook_name
				true,               // do_register
				true,               // do_enqueue
				true                // do_process_inline
			);
		}

		unset( $this->deferred_styles[ $hook_name ] );
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
			$logger->debug( 'StylesEnqueueTrait::enqueue_inline_styles - Entered method. Attempting to process any remaining immediate inline styles.' );
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

		if ($logger->is_active()) {
			$logger->debug( 'StylesEnqueueTrait::enqueue_inline_styles - Exited method.' );
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
	 * @param array<string, mixed> $style_definition    The style definition array.
	 * @param string               $processing_context  Context of the call.
	 * @param string|null          $hook_name           The hook name if processing in a deferred context, null otherwise.
	 * @param bool                 $do_register         Whether to register the style.
	 * @param bool                 $do_enqueue          Whether to enqueue the style.
	 * @param bool                 $do_process_inline   Whether to process associated inline styles.
	 * @return bool True if the style was processed successfully, false otherwise.
	 */
	protected function _process_single_style(
		array $style_definition,
		string $processing_context,
		?string $hook_name = null,
		bool $do_register = true,
		bool $do_enqueue = false,
		bool $do_process_inline = false
	): bool {
		$logger = $this->get_logger();

		$handle    = $style_definition['handle']    ?? null;
		$src       = $style_definition['src']       ?? null;
		$deps      = $style_definition['deps']      ?? array();
		$version   = $style_definition['version']   ?? false;
		$media     = $style_definition['media']     ?? 'all';
		$condition = $style_definition['condition'] ?? null;

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
			if ( 'enqueue_deferred' === $processing_context && wp_style_is( $handle, 'registered' ) ) {
				if ( $logger->is_active() ) {
					$logger->debug( "StylesEnqueueTrait::_process_single_style - Style '{$handle}'{$log_hook_context} already registered. Skipping wp_register_style.");
				}
			} else {
				if ( $logger->is_active() ) {
					$logger->debug( "StylesEnqueueTrait::_process_single_style - Registering style '{$handle}'{$log_hook_context}." );
				}
				wp_register_style( $handle, $src, $deps, $version, $media );
			}
		}

		if ( $do_enqueue ) {
			if ( 'enqueue_deferred' === $processing_context && wp_style_is( $handle, 'enqueued' ) ) {
				if ( $logger->is_active() ) {
					$logger->debug( "StylesEnqueueTrait::_process_single_style - Style '{$handle}'{$log_hook_context} already enqueued. Skipping wp_enqueue_style.");
				}
			} else {
				if ( $logger->is_active() ) {
					$logger->debug( "StylesEnqueueTrait::_process_single_style - Enqueuing style '{$handle}'{$log_hook_context}." );
				}
				wp_enqueue_style( $handle );
			}
		}

		if ( $do_process_inline ) {
			if ( $logger->is_active() ) {
				$logger->debug( "StylesEnqueueTrait::_process_single_style - Checking for inline styles for '{$handle}'{$log_hook_context}." );
			}
			$this->_process_inline_styles( $handle, $hook_name, $processing_context );
		}

		if ( $logger->is_active() ) {
			$logger->debug( "StylesEnqueueTrait::_process_single_style - Finished processing style '{$handle}'{$log_hook_context}." );
		}

		return true;
	}

	/**
	 * Abstract method to get the logger instance.
	 *
	 * This method must be implemented by the class using this trait.
	 *
	 * @return Logger The logger instance.
	 */
	abstract protected function get_logger(): Logger;
}
