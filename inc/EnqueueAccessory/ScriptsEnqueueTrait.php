<?php
declare(strict_types=1);
/**
 * Trait ScriptsEnqueueTrait
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
 * Trait ScriptsEnqueueTrait
 *
 * Manages the registration, enqueuing, and processing of JavaScript assets.
 * This includes handling general scripts, inline scripts, deferred scripts,
 * script attributes, and script data.
 *
 * @package Ran\PluginLib\EnqueueAccessory
 */
trait ScriptsEnqueueTrait {
	/**
	 * Array of script definitions to be processed.
	 *
	 * @var array<int, array<int, mixed>>
	 */
	protected array $scripts = array();

	/**
	 * Array of inline script definitions to be added.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	protected array $inline_scripts = array();

	/**
	 * Array of scripts to be loaded at specific WordPress action hooks.
	 *
	 * The outer array keys are hook names (e.g., 'admin_enqueue_scripts'),
	 * and the inner arrays contain script definitions indexed by their original addition order.
	 *
	 * @var array<string, array<int, array<string, mixed>>>
	 */
	protected array $deferred_scripts = array();

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
		return array(
			'general'  => $this->scripts,
			'deferred' => $this->deferred_scripts,
			'inline'   => $this->inline_scripts,
		);
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
		$logger = $this->get_logger();

		if ( empty( $scripts_to_add ) ) {
			if ( $logger->is_active() ) {
				$logger->debug( 'ScriptsEnqueueTrait::add_scripts - Entered with empty array. No scripts to add.' );
			}
			return $this;
		}

		// Merge single scripts in to the array
		if ( ! is_array( current( $scripts_to_add ) ) ) {
			$scripts_to_add = array( $scripts_to_add );
		}

		if ( $logger->is_active() ) {
			$logger->debug( 'ScriptsEnqueueTrait::add_scripts - Entered. Current script count: ' . count( $this->scripts ) . '. Adding ' . count( $scripts_to_add ) . ' new script(s).' );
			foreach ( $scripts_to_add as $script_key => $script_data ) {
				$handle = $script_data['handle'] ?? 'N/A';
				$src    = $script_data['src']    ?? 'N/A';
				$logger->debug( "ScriptsEnqueueTrait::add_scripts - Adding script. Key: {$script_key}, Handle: {$handle}, Src: {$src}" );
			}
			$logger->debug( 'ScriptsEnqueueTrait::add_scripts - Adding ' . count( $scripts_to_add ) . ' script definition(s). Current total: ' . count( $this->scripts ) );
		}

		// Merge new scripts with existing ones.
		// array_values ensures that if $scripts_to_add has string keys, they are discarded and scripts are appended.
		// If $this->scripts was empty, it would just become $scripts_to_add.
		foreach ( $scripts_to_add as $script_definition ) {
			$this->scripts[] = $script_definition; // Simple append.
		}
		if ($logger->is_active()) {
			$new_total = count( $this->scripts );
			$logger->debug( 'ScriptsEnqueueTrait::add_scripts - Exiting. New total script count: ' . $new_total );
			if ( $new_total > 0 ) {
				$current_handles = array_map( static fn( $a ) => $a['handle'] ?? 'N/A', $this->scripts );
				$logger->debug( 'ScriptsEnqueueTrait::add_scripts - All current script handles: ' . implode( ', ', $current_handles ) );
			}
		}
		return $this;
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
	 *   (handled by `_process_single_script()`) includes checking any associated
	 *   `$condition` callback and calling `wp_register_script()`.
	 *
	 * Note: This method only *registers* the scripts. Enqueuing is handled by
	 * `enqueue_scripts()` or `enqueue_deferred_scripts()`.
	 *
	 * @return self Returns the instance of this class for method chaining.
	 * @see    self::add_scripts()
	 * @see    self::_process_single_script()
	 * @see    self::enqueue_scripts()
	 * @see    self::enqueue_deferred_scripts()
	 * @see    wp_register_script()
	 */
	public function register_scripts(): self {
		$logger = $this->get_logger();
		if ($logger->is_active()) {
			$logger->debug( 'ScriptsEnqueueTrait::register_scripts - Entered. Processing ' . count( $this->scripts ) . ' script definition(s) for registration.' );
		}

		$scripts_to_process = $this->scripts;
		$this->scripts      = array(); // Clear original to re-populate with non-deferred scripts.

		foreach ( $scripts_to_process as $index => $script_definition ) {
			$handle_for_log = $script_definition['handle'] ?? 'N/A';
			$hook           = $script_definition['hook']   ?? null;

			if ($logger->is_active()) {
				$logger->debug( "ScriptsEnqueueTrait::register_scripts - Processing script: \"{$handle_for_log}\", original index: {$index}." );
			}

			if ( ! empty( $hook ) ) {
				if ($logger->is_active()) {
					$logger->debug( "ScriptsEnqueueTrait::register_scripts - Deferring registration of script '{$handle_for_log}' (original index {$index}) to hook: {$hook}." );
				}
				$this->deferred_scripts[ $hook ][ $index ] = $script_definition;

				// Ensure the action for deferred scripts is added only once per hook.
				$action_exists = has_action( $hook, array( $this, 'enqueue_deferred_scripts' ) );
				if ( ! $action_exists ) {
					add_action( $hook, array( $this, 'enqueue_deferred_scripts' ), 10, 1 );
					if ($logger->is_active()) {
						$logger->debug( "ScriptsEnqueueTrait::register_scripts - Added action for 'enqueue_deferred_scripts' on hook: {$hook}." );
					}
				} else {
					if ($logger->is_active()) {
						$logger->debug( "ScriptsEnqueueTrait::register_scripts - Action for 'enqueue_deferred_scripts' on hook '{$hook}' already exists." );
					}
				}
			} else {
				// Process immediately for registration
				$processed_handle = $this->_process_single_script(
					$script_definition,
					'register_scripts', // processing_context
					null,             // hook_name (null for immediate registration)
					true,             // do_register
					false,            // do_enqueue (registration only)
				);
				// Re-add to $this->scripts if it was meant for immediate registration and not deferred,
				// AND if it was successfully processed (i.e., _process_single_script returned a handle).
				// This ensures it's available for enqueue_scripts().
				if ($processed_handle) {
					$this->scripts[$index] = $script_definition;
				}
			}
		}
		if ($logger->is_active()) {
			$deferred_count = empty($this->deferred_scripts) ? 0 : array_sum(array_map('count', $this->deferred_scripts));
			$logger->debug( 'ScriptsEnqueueTrait::register_scripts - Exited. Remaining immediate scripts: ' . count($this->scripts) . '. Deferred scripts: ' . $deferred_count . '.' );
		}
		return $this;
	}

	/**
	 * Processes and enqueues all immediate scripts that have been registered.
	 *
	 * This method iterates through the `$this->scripts` array, which at this stage should only
	 * contain immediate (non-deferred) scripts, as deferred scripts are moved to their own
	 * queue by `register_scripts()`.
	 *
	 * For each immediate script, it calls `_process_single_script()` to handle enqueuing and
	 * the processing of any associated inline scripts or attributes.
	 *
	 * After processing, this method clears the `$this->scripts` array. Deferred scripts stored
	 * in `$this->deferred_scripts` are not affected.
	 *
	 * @return self Returns the instance of this class for method chaining.
	 * @throws \LogicException If a deferred script is found in the queue, indicating `register_scripts()` was not called.
	 * @see    self::add_scripts()
	 * @see    self::register_scripts()
	 * @see    self::_process_single_script()
	 * @see    self::enqueue_deferred_scripts()
	 */
	public function enqueue_scripts(): self {
		$logger = $this->get_logger();
		if ($logger->is_active()) {
			$logger->debug( 'ScriptsEnqueueTrait::enqueue_scripts - Entered. Processing ' . count( $this->scripts ) . ' script definition(s) from internal queue.' );
		}

		$scripts_to_process = $this->scripts;
		$this->scripts      = array(); // Clear the main queue, as we are processing all of them now.

		foreach ( $scripts_to_process as $index => $script_definition ) {
			$handle_for_log = $script_definition['handle'] ?? 'N/A';

			// Check for mis-queued deferred assets. This is a critical logic error.
			if ( ! empty( $script_definition['hook'] ) ) {
				throw new \LogicException(
					"ScriptsEnqueueTrait::enqueue_scripts - Found a deferred script ('{$handle_for_log}') in the immediate queue. " .
					'The `register_scripts()` method must be called before `enqueue_scripts()` to correctly process deferred scripts.'
				);
			}

			if ($logger->is_active()) {
				$logger->debug( "ScriptsEnqueueTrait::enqueue_scripts - Processing script: \"{$handle_for_log}\", original index: {$index}." );
			}

			// Defensively register and enqueue. The underlying `_process_single_script`
			// will check if the script is already registered/enqueued and skip redundant calls.
			$this->_process_single_script(
				$script_definition,
				'enqueue_scripts', // processing_context
				null,              // hook_name (null for immediate processing)
				true,              // do_register
				true,              // do_enqueue
			);
		}
		if ($logger->is_active()) {
			$deferred_count = empty($this->deferred_scripts) ? 0 : array_sum(array_map('count', $this->deferred_scripts));
			$logger->debug( 'ScriptsEnqueueTrait::enqueue_scripts - Exited. Deferred scripts count: ' . $deferred_count . '.' );
		}
		return $this;
	}

	/**
	 * Enqueue scripts that were deferred to a specific hook.
	 *
	 * @param string $hook_name The WordPress hook name that triggered this method.
	 * @return void
	 */
	public function enqueue_deferred_scripts( string $hook_name ): void {
		$logger = $this->get_logger();
		if ( $logger->is_active() ) {
			$logger->debug( "ScriptsEnqueueTrait::enqueue_deferred_scripts - Entered hook: \"{$hook_name}\"." );
		}

		if ( ! isset( $this->deferred_scripts[ $hook_name ] ) ) {
			if ( $logger->is_active() ) {
				$logger->debug( "ScriptsEnqueueTrait::enqueue_deferred_scripts - Hook \"{$hook_name}\" not found in deferred scripts. Nothing to process." );
			}
			return;
		}

		$scripts_on_this_hook = $this->deferred_scripts[ $hook_name ];
		unset( $this->deferred_scripts[ $hook_name ] ); // Moved unset action here

		if ( empty( $scripts_on_this_hook ) ) {
			if ( $logger->is_active() ) {
				$logger->debug( "ScriptsEnqueueTrait::enqueue_deferred_scripts - Hook \"{$hook_name}\" was set but had no scripts. It has now been cleared." );
			}
			return; // No actual scripts to process for this hook.
		}

		foreach ( $scripts_on_this_hook as $original_index => $script_definition ) {
			$handle_for_log = $script_definition['handle'] ?? 'N/A_at_original_index_' . $original_index;
			if ( $logger->is_active() ) {
				$logger->debug( "ScriptsEnqueueTrait::enqueue_deferred_scripts - Processing deferred script: \"{$handle_for_log}\" (original index {$original_index}) for hook: \"{$hook_name}\"." );
			}
			// _process_single_script handles registration and enqueuing.
			$processed_handle = $this->_process_single_script(
				$script_definition,
				'enqueue_deferred', // processing_context
				$hook_name,         // hook_name
				true,               // do_register
				true                // do_enqueue
			);

			if ( $processed_handle ) {
				$this->_process_inline_scripts($processed_handle, $hook_name, 'deferred from enqueue_deferred_scripts');
			}
		}

		if ( $logger->is_active() ) {
			$logger->debug( "ScriptsEnqueueTrait::enqueue_deferred_scripts - Exited for hook: \"{$hook_name}\"." );
		}
	}

	/**
	 * Chain-able call to add inline scripts.
	 *
	 * @param string      $handle     (required) Handle of the script to attach the inline script to.
	 * @param string      $content    (required) The inline script content.
	 * @param string      $position   (optional) Whether to add the inline script before or after. Default 'after'.
	 * @param callable|null $condition  (optional) Callback that determines if the inline script should be added.
	 * @param string|null $parent_hook (optional) The WordPress hook name that the parent script is deferred to.
	 * @return self
	 */
	public function add_inline_scripts( string $handle, string $content, string $position = 'after', ?callable $condition = null, ?string $parent_hook = null ): self {
		$logger = $this->get_logger();
		if ( $logger->is_active() ) {
			$logger->debug( 'ScriptsEnqueueTrait::add_inline_scripts - Entered. Current inline script count: ' . count( $this->inline_scripts ) . '. Adding new inline script for handle: ' . \esc_html( $handle ) );
		}

		$inline_script_item = array(
			'handle'      => $handle,
			'content'     => $content,
			'position'    => $position,
			'condition'   => $condition,
			'parent_hook' => $parent_hook,
		);

		// Associate inline script with its parent's hook if the parent is deferred.
		foreach ( $this->scripts as $original_script_definition ) {
			if ( ( $original_script_definition['handle'] ?? null ) === $handle && ! empty( $original_script_definition['hook'] ) ) {
				if ( null === $inline_script_item['parent_hook'] ) {
					$inline_script_item['parent_hook'] = $original_script_definition['hook'];
				}
				if ( $logger->is_active() ) {
					$logger->debug( "ScriptsEnqueueTrait::add_inline_scripts - Inline script for '{$handle}' associated with parent hook: '{$inline_script_item['parent_hook']}'. Original parent script hook: '" . ( $original_script_definition['hook'] ?? 'N/A' ) . "'." );
				}
				break;
			}
		}

		$this->inline_scripts[] = $inline_script_item;

		if ( $logger->is_active() ) {
			$logger->debug( 'ScriptsEnqueueTrait::add_inline_scripts - Exiting. New total inline script count: ' . count( $this->inline_scripts ) );
		}
		return $this;
	}

	/**
	 * Process and add all registered inline scripts.
	 *
	 * @return self
	 */
	public function enqueue_inline_scripts(): self {
		$logger = $this->get_logger();
		if ($logger->is_active()) {
			$logger->debug( 'ScriptsEnqueueTrait::enqueue_inline_scripts - Entered method.' );
		}

		$immediate_parent_handles = array();
		foreach ( $this->inline_scripts as $key => $inline_script_data ) {
			if (!is_array($inline_script_data)) {
				if ($logger->is_active()) {
					$logger->warning("ScriptsEnqueueTrait::enqueue_inline_scripts - Invalid inline script data at key '{$key}'. Skipping.");
				}
				continue;
			}
			$parent_hook = $inline_script_data['parent_hook'] ?? null;
			$handle      = $inline_script_data['handle']      ?? null;

			if ( empty( $parent_hook ) && !empty($handle) ) {
				if ( !in_array($handle, $immediate_parent_handles, true) ) {
					$immediate_parent_handles[] = $handle;
				}
			}
		}

		if (empty($immediate_parent_handles)) {
			if ($logger->is_active()) {
				$logger->debug( 'ScriptsEnqueueTrait::enqueue_inline_scripts - No immediate inline scripts found needing processing.' );
			}
			return $this;
		}

		if ($logger->is_active()) {
			$logger->debug( 'ScriptsEnqueueTrait::enqueue_inline_scripts - Found ' . count($immediate_parent_handles) . ' unique parent handle(s) with immediate inline scripts to process: ' . implode(', ', array_map('esc_html', $immediate_parent_handles) ) );
		}

		foreach ( $immediate_parent_handles as $parent_handle_to_process ) {
			$this->_process_inline_scripts(
				$parent_handle_to_process,
				null, // hook_name (null for immediate)
				'enqueue_inline_scripts' // processing_context
			);
		}

		// Clear the processed immediate inline assets from the main queue.
		$this->inline_scripts = array_filter($this->inline_scripts, function($asset) {
			return !empty($asset['parent_hook']);
		});

		if ( $logger->is_active() ) {
			$remaining_count = count($this->inline_scripts);
			$logger->debug('ScriptsEnqueueTrait::enqueue_inline_scripts - Exited. Processed ' . count($immediate_parent_handles) . " parent handle(s). Remaining deferred inline scripts: {$remaining_count}.");
		}
		return $this;
	}

	/**
	 * Processes inline scripts associated with a specific parent script handle and hook context.
	 *
	 * @param string      $parent_handle      The handle of the parent script.
	 * @param string|null $hook_name          (Optional) The hook name if processing for a deferred context.
	 * @param string      $processing_context A string indicating the context for logging purposes.
	 * @return void
	 */
	protected function _process_inline_scripts(string $parent_handle, ?string $hook_name = null, string $processing_context = 'immediate'): void {
		$logger          = $this->get_logger();
		$log_prefix_base = "ScriptsEnqueueTrait::_process_inline_scripts (context: {$processing_context}) - ";

		$logger->debug( "{$log_prefix_base}Checking for inline scripts for parent handle '{$parent_handle}'" . ($hook_name ? " on hook '{$hook_name}'." : '.') );

		// Check if the parent script is registered or enqueued before processing its inline scripts.
		// This is a crucial check to prevent adding inline scripts to a non-existent parent.
		if ( ! wp_script_is( $parent_handle, 'registered' ) && ! wp_script_is( $parent_handle, 'enqueued' ) ) {
			$logger->error( "{$log_prefix_base}Cannot add inline scripts. Parent script '{$parent_handle}' is not registered or enqueued." );
			return;
		}

		$keys_to_unset = array();

		foreach ( $this->inline_scripts as $key => $inline_script_data ) {
			if (!is_array($inline_script_data)) {
				$logger->warning("{$log_prefix_base} Invalid inline script data at key '{$key}'. Skipping.");
				continue;
			}

			$inline_target_handle = $inline_script_data['handle']      ?? null;
			$inline_parent_hook   = $inline_script_data['parent_hook'] ?? null;
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
				$content          = $inline_script_data['content']   ?? '';
				$position         = $inline_script_data['position']  ?? 'after';
				$condition_inline = $inline_script_data['condition'] ?? null;

				if ( is_callable( $condition_inline ) && ! $condition_inline() ) {
					$logger->debug( "{$log_prefix_base}Condition false for inline script targeting '{$parent_handle}' (key: {$key})" . ($hook_name ? " on hook '{$hook_name}'." : '.') );
					$keys_to_unset[] = $key;
					continue;
				}

				if ( empty( $content ) ) {
					$logger->warning( "{$log_prefix_base}Empty content for inline script targeting '{$parent_handle}' (key: {$key})" . ($hook_name ? " on hook '{$hook_name}'." : '.') . ' Skipping addition.' );
					$keys_to_unset[] = $key;
					continue;
				}

				$logger->debug( "{$log_prefix_base}Adding inline script for '{$parent_handle}' (key: {$key}, position: {$position})" . ($hook_name ? " on hook '{$hook_name}'." : '.') );
				if (wp_add_inline_script($parent_handle, $content, $position)) {
					$logger->debug("{$log_prefix_base}Successfully added inline script for '{$parent_handle}' with wp_add_inline_script.");
					$keys_to_unset[] = $key;
				}
			}
		}

		if ( ! empty( $keys_to_unset ) ) {
			foreach ( $keys_to_unset as $key_to_unset ) {
				if (isset($this->inline_scripts[$key_to_unset])) {
					$removed_handle_for_log = $this->inline_scripts[$key_to_unset]['handle'] ?? 'N/A';
					unset( $this->inline_scripts[ $key_to_unset ] );
					$logger->debug( "{$log_prefix_base}Removed processed inline script with key '{$key_to_unset}' for handle '{$removed_handle_for_log}'" . ($hook_name ? " on hook '{$hook_name}'." : '.') );
				}
			}
			$this->inline_scripts = array_values( $this->inline_scripts );
		} else {
			$logger->debug( "{$log_prefix_base}No inline scripts found or processed for '{$parent_handle}'" . ($hook_name ? " on hook '{$hook_name}'." : '.') );
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
	 * @param array      $script_definition   The script definition array.
	 * @param string     $processing_context  The context in which the script is being processed (e.g., 'register_scripts', 'enqueue_scripts'). Used for logging.
	 * @param string|null $hook_name          The name of the hook if the script is being processed in a deferred context.
	 * @param bool       $do_register         If true, the script will be registered with `wp_register_script()`.
	 * @param bool       $do_enqueue          If true, the script will be enqueued with `wp_enqueue_script()`.
	 *
	 * @return string|false The handle of the script on success, false on failure or if a condition is not met.
	 */
	protected function _process_single_script(
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
			$logger->debug( "ScriptsEnqueueTrait::_process_single_script - Processing script '{$log_handle_context}'{$log_hook_context} in context '{$processing_context}'." );
		}

		if ( empty( $handle ) || ( $do_register && empty( $src ) ) ) {
			if ( $logger->is_active() ) {
				$logger->warning( "ScriptsEnqueueTrait::_process_single_script - Invalid script definition. Missing handle or src. Skipping. Handle: '{$log_handle_context}'{$log_hook_context}." );
			}
			return false;
		}

		if ( is_callable( $condition ) && ! $condition() ) {
			if ( $logger->is_active() ) {
				$logger->debug( "ScriptsEnqueueTrait::_process_single_script - Condition not met for script '{$handle}'{$log_hook_context}. Skipping." );
			}
			return false;
		}

		if ( $do_register ) {
			if ( wp_script_is( $handle, 'registered' ) ) {
				if ( $logger->is_active() ) {
					$logger->debug( "ScriptsEnqueueTrait::_process_single_script - Script '{$handle}'{$log_hook_context} already registered. Skipping wp_register_script." );
				}
			} else {
				if ( $logger->is_active() ) {
					$logger->debug( "ScriptsEnqueueTrait::_process_single_script - Registering script '{$handle}'{$log_hook_context}." );
				}
				$registration_success = wp_register_script( $handle, $src, $deps, $version, $in_footer );
				if ( ! $registration_success ) {
					if ( $logger->is_active() ) {
						$logger->warning( "ScriptsEnqueueTrait::_process_single_script - wp_register_script() failed for handle '{$handle}'{$log_hook_context}. Skipping further processing for this script." );
					}
					return false;
				}
			}
		}

		if ( $do_enqueue ) {
			if ( wp_script_is( $handle, 'enqueued' ) ) {
				if ( $logger->is_active() ) {
					$logger->debug( "ScriptsEnqueueTrait::_process_single_script - Script '{$handle}'{$log_hook_context} already enqueued. Skipping wp_enqueue_script." );
				}
			} else {
				if ( $logger->is_active() ) {
					$logger->debug( "ScriptsEnqueueTrait::_process_single_script - Enqueuing script '{$handle}'{$log_hook_context}." );
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
							$logger->debug( "ScriptsEnqueueTrait::_process_single_script - Routing 'async' attribute for '{$handle}' to wp_script_add_data('strategy', 'async')." );
						}
						if ( ! wp_script_add_data( $handle, 'strategy', 'async' ) && $logger->is_active() ) {
							$logger->warning( "ScriptsEnqueueTrait::_process_single_script - Failed to add 'async' strategy for '{$handle}' via wp_script_add_data." );
						}
					} elseif ( $attr_key_lower === 'defer' && true === $attr_value ) {
						if ( $logger->is_active() ) {
							$logger->debug( "ScriptsEnqueueTrait::_process_single_script - Routing 'defer' attribute for '{$handle}' to wp_script_add_data('strategy', 'defer')." );
						}
						if ( ! wp_script_add_data( $handle, 'strategy', 'defer' ) && $logger->is_active() ) {
							$logger->warning( "ScriptsEnqueueTrait::_process_single_script - Failed to add 'defer' strategy for '{$handle}' via wp_script_add_data." );
						}
					} elseif ( $attr_key_lower === 'src' ) {
						if ( $logger->is_active() ) {
							$logger->debug( "ScriptsEnqueueTrait::_process_single_script - Ignoring 'src' attribute for '{$handle}' as it is managed by WordPress during registration." );
						}
					} elseif ( $attr_key_lower === 'id' ) {
						if ( $logger->is_active() ) {
							$logger->warning( "ScriptsEnqueueTrait::_process_single_script - Attempting to set 'id' attribute for '{$handle}'. WordPress typically manages script IDs. Overriding may lead to unexpected behavior or be ineffective." );
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
					$logger->debug("ScriptsEnqueueTrait::_process_single_script - Adding attributes for script '{$handle}' via script_loader_tag. Attributes: " . \wp_json_encode($attributes_for_tag_modifier));
				}
				$this->_do_add_filter(
					'script_loader_tag',
					function ( $tag, $tag_handle, $_src ) use ( $handle, $attributes_for_tag_modifier ) {
						return $this->_modify_script_tag_for_attributes( $tag, $tag_handle, $handle, $attributes_for_tag_modifier );
					},
					10,
					3
				);
			}

			// Process inline scripts after registration/enqueue.
			$this->_process_inline_scripts($handle, null, 'immediate_after_registration');
		}

		if ( $logger->is_active() ) {
			$logger->debug( "ScriptsEnqueueTrait::_process_single_script - Finished processing script '{$handle}'{$log_hook_context}." );
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
	 * @param string $tag The original HTML script tag.
	 * @param string $filter_tag_handle The handle of the script currently being filtered by WordPress.
	 * @param string $script_handle_to_match The handle of the script we are targeting for modification.
	 * @param array  $attributes_to_apply The attributes to apply to the script tag.
	 *
	 * @return string The modified (or original) HTML script tag.
	 */
	protected function _modify_script_tag_for_attributes(
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
			$logger->debug("ScriptsEnqueueTrait::_modify_script_tag_for_attributes - Modifying tag for handle '{$tag_handle}'. Attributes: " . \wp_json_encode($attributes_to_apply));
		}

		// Special handling for module scripts.
		if ( isset( $attributes_to_apply['type'] ) && 'module' === $attributes_to_apply['type'] ) {
			if ($logger->is_active()) {
				$logger->debug("ScriptsEnqueueTrait::_modify_script_tag_for_attributes - Script '{$tag_handle}' is a module. Modifying tag accordingly.");
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
				$logger->warning("ScriptsEnqueueTrait::_modify_script_tag_for_attributes - Malformed script tag for '{$tag_handle}'. Original tag: " . esc_html($tag) . '. Skipping attribute modification.');
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
							"%s - Attempt to override managed attribute '%s' for script handle '%s'. This attribute will be ignored.",
							'ScriptsEnqueueTrait::_modify_script_tag_for_attributes',
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
			$logger->debug("ScriptsEnqueueTrait::_modify_script_tag_for_attributes - Successfully modified tag for '{$tag_handle}'. New tag: " . esc_html($modified_tag));
		}
		return $modified_tag;
	}
}
