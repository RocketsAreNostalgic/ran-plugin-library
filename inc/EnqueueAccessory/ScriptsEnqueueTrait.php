<?php
declare(strict_types=1);

namespace Ran\PluginLib\EnqueueAccessory;

/**
 * Trait ScriptsEnqueueTrait
 *
 * Manages the registration, enqueuing, and processing of JavaScript assets.
 * This includes handling general scripts, inline scripts, deferred scripts,
 * script attributes, and script data.
 */
use Ran\PluginLib\Util\Logger;

trait ScriptsEnqueueTrait {
	/**
	 * Array of scripts to enqueue.
	 *
	 * @var array<int, array<int, mixed>>
	 */
	protected array $scripts = array();

	/**
	 * Array of inline scripts to be added.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	protected array $inline_scripts = array();

	/**
	 * Array of scripts to be enqueued at specific hooks.
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
	 * @return array<string, array<int, mixed>> The registered scripts.
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
	 *     - 'wp_data' (array, optional): Key-value pairs for `wp_script_add_data()`. Defaults to an empty array.
	 *     - 'hook' (string|null, optional): WordPress hook (e.g., 'admin_enqueue_scripts') to defer enqueuing. Defaults to null (immediate processing).
	 * @return self Returns the instance of this class for method chaining.
	 *
	 * @see self::enqueue_scripts()
	 * @see self::enqueue()
	 */
	public function add_scripts( array $scripts_to_add ): self {
		// Diagnostic: Attempt to get logger directly from config
		$logger = $this->config->get_logger();

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
		}

		if ($logger->is_active()) {
			$logger->debug( 'ScriptsEnqueueTrait::add_scripts - Adding ' . count( $scripts_to_add ) . ' script definition(s). Current total: ' . count( $this->scripts ) );
		}

		// Merge new scripts with existing ones.
		// array_values ensures that if $scripts_to_add has string keys, they are discarded and scripts are appended.
		// If $this->scripts was empty, it would just become $scripts_to_add.
		foreach ( $scripts_to_add as $script_definition ) {
			$this->scripts[] = $script_definition; // Simple append.
		}

		if ( $logger->is_active() ) {
			$new_total = count( $this->scripts );
			$logger->debug( "ScriptsEnqueueTrait::add_scripts - Exiting. New total script count: {$new_total}" );
			if ( $new_total > 0 ) {
				$current_handles = array();
				foreach ( $this->scripts as $s ) {
					$current_handles[] = $s['handle'] ?? 'N/A';
				}
				$logger->debug( 'ScriptsEnqueueTrait::add_scripts - All current script handles: ' . implode( ', ', $current_handles ) );
			}
		}

		return $this;
	}

	/** ✅ ⚠️
	 * Registers scripts with WordPress without enqueueing them.
	 *
	 * @todo PARITY WORK WITH REGISTER_STYLE
	 *
	 * @return self Returns the instance for method chaining.
	 */
	public function register_scripts( array $scripts = array() ): self {
		$logger = $this->get_logger();
		// If scripts are directly passed, add them to the internal array and log a notice
		if ( ! empty( $scripts ) ) {
			if ( $logger->is_active() ) {
				$logger->debug( 'ScriptsEnqueueTrait::register_scripts - Scripts directly passed. Consider using add_scripts() first for better maintainability.' );
			}
			$this->add_scripts( $scripts );
		}

		// Always use the internal scripts array for consistency
		$scripts_to_process = $this->scripts;

		if ( $logger->is_active() ) {
			$logger->debug( 'ScriptsEnqueueTrait::register_scripts - Registering ' . count( $scripts_to_process ) . ' script(s).' );
		}

		foreach ( $scripts_to_process as $script ) {
			// Skip scripts with hooks (they'll be registered when the hook fires)
			if ( !empty( $script['hook'] ) ) {
				continue;
			}

			// _process_single_script handles all registration logic including conditions,
			// wp_data, and attributes
			$this->_process_single_script( $script );
		}

		return $this;
	}

	/** ✅ ⚠️
	 * @todo Parity work with enqueue styles
	 *
	 * Processes and enqueues a given array of script definitions.
	 *
	 * This method iterates through an array of script definitions. For each script,
	 * it first checks any associated `$condition` callback. If the condition passes
	 * (or if no condition is set), the script is then processed. If a `$hook` is
	 * specified, the script's processing is deferred by adding it to an internal
	 * queue for that hook via `enqueue_deferred_scripts()`. Otherwise, the script
	 * is processed immediately by `_process_single_script()`.
	 *
	 * The `_process_single_script()` method handles the actual WordPress registration
	 * (`wp_register_script()`), enqueuing (`wp_enqueue_script()`), attribute additions,
	 * and `wp_script_add_data()` calls.
	 *
	 * @param array $scripts
	 * @return self Returns the instance of this class for method chaining.
	 * @see    self::add_scripts()
	 * @see    self::_process_single_script()
	 */
	public function enqueue_scripts( array $scripts ): self {
		$logger = $this->get_logger();
		if ( $logger->is_active() ) {
			$logger->debug( 'ScriptsEnqueueTrait::enqueue_scripts - Entered. Processing ' . count( $scripts ) . ' script definition(s).' );
		}
		// Track which hooks have new scripts added.
		$hooks_with_new_scripts = array();

		foreach ( $scripts as $script ) {
			$hook = $script['hook'] ?? null;

			// If a hook is specified, store the script for later enqueuing.
			if ( ! empty( $hook ) ) {
				if ( $logger->is_active() ) {
					$logger->debug( "ScriptsEnqueueTrait::enqueue_scripts - Script '" . ( $script['handle'] ?? 'N/A' ) . "' identified as deferred to hook: " . $hook );
				}

				if ( ! isset( $this->deferred_scripts[ $hook ] ) ) {
					$this->deferred_scripts[ $hook ] = array();
				}
				$this->deferred_scripts[ $hook ][] = $script;
				$hooks_with_new_scripts[ $hook ]   = true;
				continue;
			}

			// Process the script (register and set up attributes).
			if ( $logger->is_active() ) {
				$logger->debug( 'ScriptsEnqueueTrait::enqueue_scripts - Processing non-deferred script: ' . ( $script['handle'] ?? 'N/A' ) );
			}

			$handle = $this->_process_single_script( $script );

			// Skip empty handles (condition not met).
			if ( empty( $handle ) ) {
				if ( $logger->is_active() ) {
					$logger->debug( 'ScriptsEnqueueTrait::enqueue_scripts - Skipping script as handle is empty (condition likely not met for: ' . ($script['handle'] ?? 'N/A') . ').' );
				}
				continue;
			}

			if ( $logger->is_active() ) {
				$logger->debug( 'ScriptsEnqueueTrait::enqueue_scripts - Enqueuing script (handle from _process_single_script): ' . $handle );
			}

			// Enqueue the script.
			if ( $logger->is_active() ) {
				$logger->debug( 'ScriptsEnqueueTrait::enqueue_scripts - Calling wp_enqueue_script for non-deferred: ' . $handle );
			}
			wp_enqueue_script( $handle );
		}

		// Register hooks for any deferred scripts that were added.
		if ( $logger->is_active() ) {
			$logger->debug( 'ScriptsEnqueueTrait::enqueue_scripts - hooks_with_new_scripts: ' . wp_json_encode( $hooks_with_new_scripts ) );
		}
		if ( ! empty( $hooks_with_new_scripts ) ) {
			foreach ( array_keys( $hooks_with_new_scripts ) as $hook ) {
				// Check if the hook has already fired.
				if ( is_admin() && did_action( $hook ) ) {
					// Hook has already fired, enqueue directly.
					$this->enqueue_deferred_scripts( $hook );
				} else {
					// Create a proper callback with the hook name captured in the closure.
					// This ensures the correct hook name is used when the callback is executed.
					$callback = function () use ( $hook ): void {
						$this->enqueue_deferred_scripts( $hook );
					};

					// Register for future execution with a higher priority (10).
					// This helps ensure it runs before other scripts that might depend on it.
					add_action( $hook, $callback, 10 );
				}
			}
		}

		if ( $logger->is_active() ) {
			$logger->debug( 'ScriptsEnqueueTrait::enqueue_scripts - Exited.' );
		}
		return $this;
	}

	/**
	 * Enqueue scripts that were deferred to a specific hook.
	 *
	 * @param string $hook The WordPress hook name.
	 * @return void This method returns `void` because it's primarily designed as a callback for WordPress action hooks.
	 *              When executed within a hook, its main role is to perform enqueueing operations (side effects),
	 *              rather than being part of a fluent setup chain.
	 */
	public function enqueue_deferred_scripts( string $hook_name ): void {
		$logger = $this->get_logger();
		if ( $logger->is_active() ) {
			$logger->debug( "ScriptsEnqueueTrait::enqueue_deferred_scripts - Entered for hook: \"{$hook_name}\"" );
		}
		if ( ! isset( $this->deferred_scripts[ $hook_name ] ) ) {
			if ( $logger->is_active() ) {
				$logger->debug( "ScriptsEnqueueTrait::enqueue_deferred_scripts - Hook \"{$hook_name}\" not found in deferred scripts. Nothing to process." );
			}
			return; // Hook was never set, nothing to do or unset.
		}

		// Retrieve scripts for the hook and then immediately unset it to mark as processed.
		$scripts_on_this_hook = $this->deferred_scripts[ $hook_name ];
		unset( $this->deferred_scripts[ $hook_name ] ); // Moved unset action here

		if ( empty( $scripts_on_this_hook ) ) {
			if ( $logger->is_active() ) {
				$logger->debug( "ScriptsEnqueueTrait::enqueue_deferred_scripts - Hook \"{$hook_name}\" was set but had no scripts. It has now been cleared." );
			}
			return; // No actual scripts to process for this hook.
		}

		foreach ( $scripts_on_this_hook as $script_definition ) {
			$original_handle = $script_definition['handle'] ?? null;

			if ( empty( $original_handle ) ) {
				if ( $logger->is_active() ) {
					$logger->error( "ScriptsEnqueueTrait::enqueue_deferred_scripts - Script definition missing 'handle' for hook '{$hook_name}'. Skipping this script definition." );
				}
				continue; // Skip this script definition if handle is missing
			}

			$is_registered = wp_script_is( $original_handle, 'registered' );
			$is_enqueued   = wp_script_is( $original_handle, 'enqueued' );
			if ( $logger->is_active() ) {
				$logger->debug( "ScriptsEnqueueTrait::enqueue_deferred_scripts - (Deferred) Script '" . esc_html( $original_handle ) . "' is_registered: " . ( $is_registered ? 'TRUE' : 'FALSE' ) );
				$logger->debug( "ScriptsEnqueueTrait::enqueue_deferred_scripts - (Deferred) Script '" . esc_html( $original_handle ) . "' is_enqueued: " . ( $is_enqueued ? 'TRUE' : 'FALSE' ) );
			}

			$skip_main_registration_and_enqueue = $is_enqueued;

			if ( $skip_main_registration_and_enqueue ) {
				if ( $logger->is_active() ) {
					$logger->debug( "ScriptsEnqueueTrait::enqueue_deferred_scripts - Script '{$original_handle}' is already enqueued. Skipping its registration and main enqueue call on hook '{$hook_name}'. Inline scripts will still be processed." );
				}
			} else {
				if ( $logger->is_active() ) {
					$logger->debug( "ScriptsEnqueueTrait::enqueue_deferred_scripts - Processing deferred script: \"{$original_handle}\" for hook: \"{$hook_name}\"" );
				}
				// _process_single_script returns the original handle, so $original_handle is the one to use.
				$processed_handle_confirmation = $this->_process_single_script( $script_definition );

				if ( empty( $processed_handle_confirmation ) || $processed_handle_confirmation !== $original_handle ) {
					if ( $logger->is_active() ) {
						$logger->warning( "ScriptsEnqueueTrait::enqueue_deferred_scripts - _process_single_script returned an unexpected handle ('{$processed_handle_confirmation}') or empty for original handle '{$original_handle}' on hook \"{$hook_name}\". Skipping main script enqueue and its inline scripts." );
					}
					continue; // Critical issue, skip this script and its inlines.
				}

				if ( $logger->is_active() ) {
					$logger->debug( "ScriptsEnqueueTrait::enqueue_deferred_scripts - Calling wp_enqueue_script for deferred: \"{$original_handle}\" on hook: \"{$hook_name}\"" );
				}
				wp_enqueue_script( $original_handle );
			}

			// Process any inline scripts associated with this deferred script's handle and hook.
			if ( $logger->is_active() ) {
				$logger->debug( "ScriptsEnqueueTrait::enqueue_deferred_scripts - Checking for inline scripts for handle '{$original_handle}' on hook '{$hook_name}'." );
			}
			$inline_scripts_to_remove_keys = array(); // To store keys of inline scripts that are processed.
			foreach ( $this->inline_scripts as $key => $inline_script ) {
				$inline_handle      = $inline_script['handle']      ?? '';
				$inline_content     = $inline_script['content']     ?? '';
				$inline_position    = $inline_script['position']    ?? 'after';
				$inline_condition   = $inline_script['condition']   ?? null;
				$inline_parent_hook = $inline_script['parent_hook'] ?? null;

				// Check if this inline script is for the current deferred script handle AND hook.
				if ( $inline_handle === $original_handle && $inline_parent_hook === $hook_name ) {
					if ( $logger->is_active() ) {
						$logger->debug( "ScriptsEnqueueTrait::enqueue_deferred_scripts - Found inline script for '{$original_handle}' on hook '{$hook_name}'. Processing it now." );
					}

					if ( empty( $inline_content ) ) {
						if ( $logger->is_active() ) {
							$logger->error( "ScriptsEnqueueTrait::enqueue_deferred_scripts - Skipping inline script for '{$original_handle}' on hook '{$hook_name}' due to missing content." );
						}
						$inline_scripts_to_remove_keys[] = $key; // Mark for removal even if skipped.
						continue;
					}

					if ( is_callable( $inline_condition ) && ! $inline_condition() ) {
						if ( $logger->is_active() ) {
							$logger->debug( "ScriptsEnqueueTrait::enqueue_deferred_scripts - Condition not met for inline script '{$original_handle}' on hook '{$hook_name}'. Skipping." );
						}
						$inline_scripts_to_remove_keys[] = $key; // Mark for removal.
						continue;
					}

					// Parent script should now be registered (and enqueued if not skipped).
					// We re-check registration here as a safeguard, though _process_single_script should have handled it.
					if ( ! wp_script_is( $original_handle, 'registered' ) ) {
						if ( $logger->is_active() ) {
							$logger->error( "ScriptsEnqueueTrait::enqueue_deferred_scripts - Cannot add inline script for '{$original_handle}' on hook '{$hook_name}'. Parent script is not registered." );
						}
						$inline_scripts_to_remove_keys[] = $key; // Mark for removal.
						continue;
					}

					if ( $logger->is_active() ) {
						$logger->debug( "ScriptsEnqueueTrait::enqueue_deferred_scripts - Adding inline script for '{$original_handle}' (hook '{$hook_name}'), position '{$inline_position}'." );
					}
					if ( 'before' === $inline_position ) {
						wp_add_inline_script( $original_handle, $inline_content, 'before' );
					} else {
						wp_add_inline_script( $original_handle, $inline_content ); // 'after' is default.
					}
					$inline_scripts_to_remove_keys[] = $key; // Mark as processed and remove.
				}
			}
			// Remove processed inline scripts for this hook and handle.
			foreach ( $inline_scripts_to_remove_keys as $key_to_remove ) {
				unset( $this->inline_scripts[ $key_to_remove ] );
			}
			// re-index to remove any gaps in the array sequence
			$this->inline_scripts = array_values($this->inline_scripts);
		}

		if ( $logger->is_active() ) {
			$logger->debug( "ScriptsEnqueueTrait::enqueue_deferred_scripts - Exited for hook: \"{$hook_name}\"" );
		}
	}

	/**
	 * Chain-able call to add inline scripts.
	 *
	 * @param array<int, array<string, mixed>> $inline_scripts_to_add The array of inline scripts to add. Each script should be an array with the following keys.
	 *     @type string      $handle     (required) Handle of the script to attach the inline script to. Must be already registered.
	 *     @type string      $content    (required) The inline script content.
	 *     @type string      $position   (optional) Whether to add the inline script before or after the registered script. Default 'after'.
	 *     @type callable    $condition  (optional) Callback function that determines if the inline script should be added.
	 *     @type string|null $parent_hook (optional) The WordPress hook name that the parent script is deferred to.
	 */
	public function add_inline_scripts( array $inline_scripts_to_add ): self {
		$logger = $this->get_logger();
		if ( $logger->is_active() ) {
			$logger->debug( 'ScriptsEnqueueTrait::add_inline_scripts - Entered. Current inline script count: ' . count( $this->inline_scripts ) . '. Adding ' . count( $inline_scripts_to_add ) . ' new inline script(s).' );
		}

		$processed_inline_scripts = array();
		foreach ( $inline_scripts_to_add as $inline_script_data ) {
			if ( $logger->is_active() ) {
				$log_handle         = $inline_script_data['handle']   ?? 'N/A';
				$log_position       = $inline_script_data['position'] ?? 'after';
				$log_content_length = strlen($inline_script_data['content'] ?? '');
				$logger->debug( "ScriptsEnqueueTrait::add_inline_scripts - Processing inline script for handle '{$log_handle}', position '{$log_position}', content length {$log_content_length}." );
			}
			$parent_handle = $inline_script_data['handle'] ?? null;
			// Ensure parent_hook is initialized, it might be overridden if the parent script is deferred.
			// If $inline_script_data['parent_hook'] is already set by the caller, respect that.
			if ( ! isset( $inline_script_data['parent_hook'] ) ) {
				$inline_script_data['parent_hook'] = null;
			}

			if ( $parent_handle ) {
				// Check if this parent_handle corresponds to a deferred script to inherit its hook.
				foreach ( $this->scripts as $original_script_definition ) {
					if ( ( $original_script_definition['handle'] ?? null ) === $parent_handle && ! empty( $original_script_definition['hook'] ) ) {
						// Only override parent_hook if it wasn't explicitly set for the inline script.
						if ( null === $inline_script_data['parent_hook'] ) {
							$inline_script_data['parent_hook'] = $original_script_definition['hook'];
						}
						if ( $logger->is_active() ) {
							$logger->debug( "ScriptsEnqueueTrait::add_inline_scripts - Inline script for '{$parent_handle}' associated with parent hook: '{$inline_script_data['parent_hook']}'. Original parent script hook: '" . ( $original_script_definition['hook'] ?? 'N/A' ) . "'." );
						}
						break; // Found the parent script, no need to check further.
					}
				}
			}
			$processed_inline_scripts[] = $inline_script_data;
		}
		// Merge new inline scripts with existing ones.
		$this->inline_scripts = array_merge( $this->inline_scripts, $processed_inline_scripts );

		if ( $logger->is_active() ) {
			$logger->debug( 'ScriptsEnqueueTrait::add_inline_scripts - Exiting. New total inline script count: ' . count( $this->inline_scripts ) );
		}
		return $this;
	}

	/**
	 *
	 * Process and add all registered inline scripts.
	 */
	public function enqueue_inline_scripts(): self {
		$logger = $this->get_logger();
		if ( $logger->is_active() ) {
			$logger->debug( 'ScriptsEnqueueTrait::enqueue_inline_scripts - Entered method.' );
		}
		foreach ( $this->inline_scripts as $inline_script ) {
			$handle      = $inline_script['handle']      ?? '';
			$content     = $inline_script['content']     ?? '';
			$position    = $inline_script['position']    ?? 'after';
			$condition   = $inline_script['condition']   ?? null;
			$parent_hook = $inline_script['parent_hook'] ?? null;

			if ( $logger->is_active() ) {
				$logger->debug( "ScriptsEnqueueTrait::enqueue_inline_scripts - Processing inline script for handle: '" . esc_html( $handle ) . "'. Parent hook: '" . esc_html( $parent_hook ?: 'None' ) . "'." );
			}

			// If this inline script is tied to a parent script on a specific hook, skip it here.
			// It will be handled by enqueue_deferred_scripts.
			if ( ! empty( $parent_hook ) ) {
				if ( $logger->is_active() ) {
					$logger->debug( "ScriptsEnqueueTrait::enqueue_inline_scripts - Deferring inline script for '{$handle}' because its parent is on hook '{$parent_hook}'." );
				}
				continue;
			}

			// Skip if required parameters are missing.
			if ( empty( $handle ) || empty( $content ) ) {
				if ( $logger->is_active() ) {
					$logger->error( 'ScriptsEnqueueTrait::enqueue_inline_scripts - Skipping (non-deferred) inline script due to missing handle or content. Handle: ' . esc_html( $handle ) );
				}
				continue;
			}

			// Check if the condition is met.
			if ( is_callable( $condition ) && ! $condition() ) {
				if ( $logger->is_active() ) {
					$logger->debug( "ScriptsEnqueueTrait::enqueue_inline_scripts - Condition not met for inline script '{$handle}'. Skipping." );
				}
				continue;
			}

			// Check if the parent script is registered or enqueued.
			$is_registered = wp_script_is( $handle, 'registered' );
			$is_enqueued   = wp_script_is( $handle, 'enqueued' );
			if ( $logger->is_active() ) {
				$logger->debug( "ScriptsEnqueueTrait::enqueue_inline_scripts - (Non-deferred) Script '" . esc_html( $handle ) . "' is_registered: " . ( $is_registered ? 'TRUE' : 'FALSE' ) );
				$logger->debug( "ScriptsEnqueueTrait::enqueue_inline_scripts - (Non-deferred) Script '" . esc_html( $handle ) . "' is_enqueued: " . ( $is_enqueued ? 'TRUE' : 'FALSE' ) );
			}
			if ( ! $is_registered && ! $is_enqueued ) {
				if ( $logger->is_active() ) {
					$logger->error( "ScriptsEnqueueTrait::enqueue_inline_scripts - (Non-deferred) Cannot add inline script. Parent script '" . esc_html( $handle ) . "' is not registered or enqueued." );
				}
				continue;
			}

			// Add the inline script using WordPress functions.
			if ( $logger->is_active() ) {
				$logger->debug( "ScriptsEnqueueTrait::enqueue_inline_scripts - (Non-deferred) Attempting to add inline script for '" . esc_html( $handle ) . "' with position '" . esc_html( $position ) . "'." );
			}
			if ( 'before' === $position ) {
				wp_add_inline_script( $handle, $content, 'before' );
			} else {
				wp_add_inline_script( $handle, $content ); // 'after' is the default.
			}
		}
		return $this;
	}

	/**
	 *
	 * Processes a single script definition: registers it, adds attributes, and script data.
	 *
	 * @todo PARITY WORK WTH _process_single_style
	 * @todo re-enable additional logging after inital tests pass
	 *
	 * This method is responsible for the core logic of handling a script. It checks conditions,
	 * registers the script with WordPress using `wp_register_script`, adds any specified
	 * HTML attributes via the `script_loader_tag` filter (using `_modify_script_tag_for_attributes`),
	 * and adds any script data using `wp_script_add_data`.
	 *
	 * @param array $script The script definition array.
	 *     @type string      $handle     (Required) Name of the script. Must be unique.
	 *     @type string      $src        (Required) URL to the script resource.
	 *     @type array       $deps       (Optional) An array of registered script handles this script depends on. Defaults to an empty array.
	 *     @type string|false|null $version    (Optional) Script version. `false` (default) uses plugin's version, `null` adds no version, a string sets a specific version.
	 *     @type bool        $in_footer  (Optional) Whether to enqueue the script before `</body>` (`true`) or in the `<head>` (`false`). Defaults to `false`.
	 *     @type callable|null $condition  (Optional) A callback returning a boolean. If `false`, the script is not enqueued. Defaults to `null` (no condition).
	 *     @type array<string, string|bool> $attributes (Optional) Key-value pairs of HTML attributes to add to the `<script>` tag (e.g., `['async' => true, 'type' => 'module']`). Defaults to an empty array.
	 *     @type array<string, mixed> $wp_data    (Optional) Key-value pairs to pass to `wp_script_add_data()`. Useful for localization or other script data. Defaults to an empty array.
	 * @return string|null The script handle if processed successfully, null otherwise (e.g., condition failed, registration failed).
	 */
	protected function _process_single_script( array $script ): ?string {
		$handle     = $script['handle']     ?? '';
		$src        = $script['src']        ?? '';
		$deps       = $script['deps']       ?? array();
		$ver        = $script['version']    ?? false;
		$in_footer  = $script['in_footer']  ?? false;
		$condition  = $script['condition']  ?? null;
		$attributes = $script['attributes'] ?? array();
		$wp_data    = $script['wp_data']    ?? array();
		$logger     = $this->get_logger();

		if (empty($handle)) {
			if ($logger->is_active()) {
				$logger->warning('ScriptsEnqueueTrait::_process_single_script - Script definition missing handle. Skipping.');
			}
			return null;
		}

		if (is_callable($condition)) {
			if (!$condition()) { // Direct call to callable
				if ($logger->is_active()) {
					$logger->debug("ScriptsEnqueueTrait::_process_single_script - Condition not met for script '{$handle}'. Skipping.");
				}
				return null;
			}
		}

		if ($logger->is_active()) {
			$logger->debug("ScriptsEnqueueTrait::_process_single_script - Registering script '{$handle}' with src '{$src}'.");
		}
		$registered = wp_register_script( $handle, $src, $deps, $ver, $in_footer );

		if (!$registered) {
			if ($logger->is_active()) {
				$logger->error("ScriptsEnqueueTrait::_process_single_script - Failed to register script '{$handle}'.");
			}
			return null;
		}

		// Apply WordPress script data.
		if ( ! empty( $wp_data ) && is_array( $wp_data ) ) {
			if ($logger->is_active()) {
				$logger->debug("ScriptsEnqueueTrait::_process_single_script - Adding script data for '{$handle}'. Data: " . wp_json_encode($wp_data));
			}
			foreach ( $wp_data as $key => $value ) {
				wp_script_add_data( $handle, $key, $value );
			}
		}

		// Apply HTML attributes.
		if ( ! empty( $attributes ) && is_array( $attributes ) ) {
			if ($logger->is_active()) {
				$logger->debug("ScriptsEnqueueTrait::_process_single_script - Adding attributes for script '{$handle}'. Attributes: " . wp_json_encode($attributes));
			}
			add_filter(
				'script_loader_tag',
				function ( $tag, $tag_handle, $tag_src ) use ( $handle, $attributes, $logger ) { // Added logger to use() for internal logging
					return $this->_modify_script_tag_for_attributes(
						$tag,
						$tag_handle,
						$handle,     // Pass the specific handle for *this* script
						$attributes,  // Pass the specific attributes for *this* script
					);
				},
				10,
				3
			);
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
	 * @param \Ran\PluginLib\Util\Logger $logger Logger instance for debugging.
	 * @return string The modified (or original) HTML script tag.
	 */
	protected function _modify_script_tag_for_attributes(
		string $tag,
		string $filter_tag_handle,
		string $script_handle_to_match,
		array $attributes_to_apply,
	): string {
		$logger = $this->get_logger();

		// If the filter is not for the script we're interested in, return the original tag.
		if ( $filter_tag_handle !== $script_handle_to_match ) {
			return $tag;
		}

		if ($logger->is_active()) {
			$logger->debug("ScriptsEnqueueTrait::_modify_script_tag_for_attributes - Modifying tag for handle '{$filter_tag_handle}'. Attributes: " . wp_json_encode($attributes_to_apply));
		}

		// Work on a local copy of attributes to handle modifications like unsetting 'type'
		$local_attributes = $attributes_to_apply;

		// Special handling for module scripts.
		if ( isset( $local_attributes['type'] ) && 'module' === $local_attributes['type'] ) {
			if ($logger->is_active()) {
				$logger->debug("ScriptsEnqueueTrait::_modify_script_tag_for_attributes - Script '{$filter_tag_handle}' is a module. Modifying tag accordingly.");
			}
			// Position type="module" right after <script.
			$tag = preg_replace( '/<script\s/', '<script type="module" ', $tag );
			// Remove type from attributes so it's not added again.
			unset( $local_attributes['type'] );
		}

		// Check for malformed tag (no closing '>') AFTER potential tag modification
		$pos = strpos( $tag, '>' );
		// Check for malformed tag (no opening '<script')
		$script_open_pos = stripos( $tag, '<script' );

		if ( false === $pos || false === $script_open_pos ) {
			if ($logger->is_active()) {
				$logger->warning("ScriptsEnqueueTrait::_modify_script_tag_for_attributes - Malformed script tag for '{$filter_tag_handle}'. Original tag: " . esc_html($tag) . '. Skipping attribute modification.');
			}
			return $tag;
		}

		$attr_str = '';
		foreach ( $local_attributes as $attr => $value ) {
			// Skip src attribute as it's already in the tag.
			if ( 'src' === $attr ) {
				continue;
			}

			// Boolean attributes (value is true).
			if ( true === $value ) {
				$attr_str .= ' ' . esc_attr( $attr );
			} elseif ( false !== $value && null !== $value && '' !== $value ) { // Regular attributes with non-empty, non-false, non-null values.
				$attr_str .= ' ' . esc_attr( $attr ) . '="' . esc_attr( (string) $value ) . '"';
			}
		}

		// Insert attributes before the closing bracket of the <script> tag.
		// Find the first '>' which should be the end of the opening <script ...> tag.
		$first_gt_pos = strpos( $tag, '>' );
		if ( false !== $first_gt_pos ) {
			$modified_tag = substr_replace( $tag, $attr_str, $first_gt_pos, 0 );
			if ($logger->is_active()) {
				$logger->debug("ScriptsEnqueueTrait::_modify_script_tag_for_attributes - Successfully modified tag for '{$filter_tag_handle}'. New tag: " . esc_html($modified_tag));
			}
			return $modified_tag;
		} else {
			if ($logger->is_active()) {
				$logger->warning("ScriptsEnqueueTrait::_modify_script_tag_for_attributes - Could not find closing '>' in script tag for '{$filter_tag_handle}'. Original tag: " . esc_html($tag) . '. Attributes not added.');
			}
			return $tag; // Should not happen if previous checks passed, but as a safeguard.
		}
	}
}
