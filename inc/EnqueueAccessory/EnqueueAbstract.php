<?php
/**
 * Abstract Enqueue implementation.
 *
 * This class provides functionality for enqueueing scripts, styles, and media in WordPress.
 * TODO: add optional support to add cache busting query param to end of urls.
 * - It will be difficult with our current approach to do this on a per item basis.
 * - It would be easy however to add a flag to enqueue_*($scripts, $cashbust=true)
 *
 * @package  RanPluginLib
 */

declare(strict_types = 1);

namespace Ran\PluginLib\EnqueueAccessory;

use Ran\PluginLib\Util\Logger;

/**
 * Abstract base class for managing the enqueuing of CSS styles, JavaScript and media files in WordPress.
 *
 * This class provides a structured way to register and enqueue assets, supporting deferred loading
 * via WordPress hooks, conditional loading, and the addition of inline scripts and styles.
 * It is designed to be extended by concrete enqueue handler classes within a plugin.
 *
 * Architectural Differences in Asset Handling:
 *
 * While both scripts and styles support similar features (registration, enqueuing, deferral, inline data),
 * their internal processing within this class differs slightly:
 *
 * Scripts:
 * - Utilize a protected `_process_single_script()` method to encapsulate the registration
 *   (`wp_register_script`), attribute handling (e.g., `async`, `defer`, `type="module"`),
 *   and `wp_script_add_data()` calls for each script. This centralization helps manage
 *   the greater complexity often associated with script attributes and associated WordPress data.
 * - Inline scripts associated with a parent script can be processed immediately after the parent
 *   by `_process_single_script()` if not deferred.
 *
 * Styles:
 * - Do not have a direct `_process_single_style()` counterpart. Parent style registration
 *   (`wp_register_style`) and enqueuing (`wp_enqueue_style`) are handled more directly within
 *   the `enqueue_styles()` method (for non-deferred styles) or within dedicated hook callbacks
 *   (for deferred styles).
 * - The `enqueue_inline_styles()` method processes all accumulated inline styles (both deferred
 *   and non-deferred) in a batch. This centralized batch processing for inline styles allows
 *   for consistent management of their deferral logic.
 *
 * This distinction arises from the typically more complex attribute and data requirements for scripts
 * compared to styles, and it allows for optimized handling pathways for each asset type.
 *
 * @package RanPluginLib
 */
abstract class EnqueueAbstract implements EnqueueInterface {
	/**
	 * The ConfigInterface object holding plugin configuration.
	 *
	 * @var \Ran\PluginLib\Config\ConfigInterface $config // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.UselessAnnotation
	 */
	private \Ran\PluginLib\Config\ConfigInterface $config;

	/**
	 * Constructor.
	 *
	 * @param \Ran\PluginLib\Config\ConfigInterface $config The configuration object.
	 */
	public function __construct( \Ran\PluginLib\Config\ConfigInterface $config ) {
		$this->config = $config;
	}

	/**
	 * Retrieves the logger instance for the plugin.
	 *
	 * @return Logger The logger instance.
	 */
	public function get_logger(): Logger {
		// @codeCoverageIgnoreStart
		return $this->config->get_logger();
		// @codeCoverageIgnoreEnd
	}

	/**
	 * Abstract method for hooking into WordPress to enqueue assets.
	 *
	 * Concrete implementations of this method should use WordPress action hooks
	 * (e.g., `admin_enqueue_scripts`, `wp_enqueue_scripts`, `login_enqueue_scripts`)
	 * to call a method (often `enqueue()` from this class, or a custom one)
	 * that will process and enqueue the registered scripts and styles.
	 *
	 * This method is typically called during the plugin's initialization sequence.
	 */
	abstract public function load(): void;

	// SCRIPTS HANDLING

	/**
	 * Array of urls to enqueue.
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
	 * Get the array of registered scripts.
	 *
	 * @return array<string, array<int, mixed>> The registered scripts.
	 */
	public function get_scripts() {
		$scripts = array(
			'general'  => $this->scripts,
			'deferred' => $this->deferred_scripts,
			'inline'   => $this->inline_scripts,
		);
		return $scripts;
	}

	/**
	 * Adds a collection of script definitions to an internal queue for later processing.
	 *
	 * This method is chain-able. It **merges** the provided script definitions with any
	 * scripts previously added via this method. The actual registration and enqueuing
	 * occur when the `enqueue()` or `enqueue_scripts()` method is subsequently called.
	 *
	 * @param  array<int, array<string, mixed>> $scripts_to_add The array of scripts to add. Each script should be an array with the following keys:
	 *     @type string      $handle     (Required) Name of the script. Must be unique.
	 *     @type string      $src        (Required) URL to the script resource.
	 *     @type array<int, string> $deps (Optional) An array of registered script handles this script depends on. Defaults to an empty array.
	 *     @type string|false|null $version    (Optional) Script version. `false` (default) uses plugin's version, `null` adds no version, a string sets a specific version.
	 *     @type bool        $in_footer  (Optional) Whether to enqueue the script before `</body>` (`true`) or in the `<head>` (`false`). Defaults to `false`.
	 *     @type callable|null $condition  (Optional) A callback returning a boolean. If `false`, the script is not enqueued. Defaults to `null` (no condition).
	 *     @type array<string, string|bool> $attributes (Optional) Key-value pairs of HTML attributes to add to the `<script>` tag (e.g., `['async' => true, 'type' => 'module']`). Defaults to an empty array.
	 *     @type array<string, mixed> $wp_data    (Optional) Key-value pairs to pass to `wp_script_add_data()`. Useful for localization or other script data. Defaults to an empty array.
	 *     @type string|null $hook       (Optional) WordPress hook (e.g., 'admin_enqueue_scripts') to defer enqueuing until. Defaults to `null` (no deferral).
	 * @return self Returns the instance of this class for method chaining.
	 * @see    self::enqueue_scripts()
	 * @see    self::enqueue()
	 * @see    self::_process_single_script()
	 */
	public function add_scripts( array $scripts_to_add ): self {
		$logger = $this->get_logger();
		if ( $logger->is_active() ) {
			$logger->debug( 'EnqueueAbstract::add_scripts - Entered. Current script count: ' . count( $this->scripts ) . '. Adding ' . count( $scripts_to_add ) . ' new script(s).' );
			foreach ( $scripts_to_add as $script_key => $script_data ) {
				$handle = $script_data['handle'] ?? 'N/A';
				$src    = $script_data['src']    ?? 'N/A';
				$logger->debug( "EnqueueAbstract::add_scripts - Adding script. Key: {$script_key}, Handle: {$handle}, Src: {$src}" );
			}
		}

		// Merge new scripts with existing ones.
		// array_values ensures that if $scripts_to_add has string keys, they are discarded and scripts are appended.
		// If $this->scripts was empty, it would just become $scripts_to_add.
		foreach ( $scripts_to_add as $script ) {
			$this->scripts[] = $script; // Simple append.
		}

		if ( $logger->is_active() ) {
			$logger->debug( 'EnqueueAbstract::add_scripts - Exiting. New total script count: ' . count( $this->scripts ) );
			$current_handles = array();
			foreach ( $this->scripts as $s ) {
				$current_handles[] = $s['handle'] ?? 'N/A';
			}
			$logger->debug( 'EnqueueAbstract::add_scripts - All current script handles after add: ' . implode( ', ', $current_handles ) );
		}

		return $this;
	}

	/**
	 * Registers scripts with WordPress without enqueueing them.
	 *
	 * @return self Returns the instance for method chaining.
	 */
	public function register_scripts( array $scripts = array() ): self {
		$logger = $this->get_logger();
		// If scripts are directly passed, add them to the internal array and log a notice
		if ( ! empty( $scripts ) ) {
			if ( $logger->is_active() ) {
				$logger->debug( 'EnqueueAbstract::register_scripts - Scripts directly passed. Consider using add_scripts() first for better maintainability.' );
			}
			$this->add_scripts( $scripts );
		}

		// Always use the internal scripts array for consistency
		$scripts = $this->scripts;

		if ( $logger->is_active() ) {
			$logger->debug( 'EnqueueAbstract::register_scripts - Registering ' . count( $scripts ) . ' script(s).' );
		}

		foreach ( $scripts as $script ) {
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

	/**
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
			$logger->debug( 'EnqueueAbstract::enqueue_scripts - Entered. Processing ' . count( $scripts ) . ' script definition(s).' );
		}
		// Track which hooks have new scripts added.
		$hooks_with_new_scripts = array();

		foreach ( $scripts as $script ) {
			$hook = $script['hook'] ?? null;

			// If a hook is specified, store the script for later enqueuing.
			if ( ! empty( $hook ) ) {
				if ( $logger->is_active() ) {
					$logger->debug( "EnqueueAbstract::enqueue_scripts - Script '" . ( $script['handle'] ?? 'N/A' ) . "' identified as deferred to hook: " . $hook );
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
				$logger->debug( 'EnqueueAbstract::enqueue_scripts - Processing non-deferred script: ' . ( $script['handle'] ?? 'N/A' ) );
			}


			$handle = $this->_process_single_script( $script );

			// Skip empty handles (condition not met).
			if ( empty( $handle ) ) {
				if ( $logger->is_active() ) {
					$logger->debug( 'EnqueueAbstract::enqueue_scripts - Skipping script as handle is empty (condition likely not met for: ' . ($script['handle'] ?? 'N/A') . ').' );
				}
				continue;
			}

			if ( $logger->is_active() ) {
				$logger->debug( 'EnqueueAbstract::enqueue_scripts - Enqueuing script (handle from _process_single_script): ' . $handle );
			}

			// Enqueue the script.
			if ( $logger->is_active() ) {
				$logger->debug( 'EnqueueAbstract::enqueue_scripts - Calling wp_enqueue_script for non-deferred: ' . $handle );
			}
			wp_enqueue_script( $handle );
		}

		// Register hooks for any deferred scripts that were added.
		if ( $logger->is_active() ) {
			$logger->debug( 'EnqueueAbstract::enqueue_scripts - hooks_with_new_scripts: ' . wp_json_encode( $hooks_with_new_scripts ) );
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
			$logger->debug( 'EnqueueAbstract::enqueue_scripts - Exited.' );
		}
		return $this;
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
			$logger->debug( 'EnqueueAbstract::add_inline_scripts - Entered. Current inline script count: ' . count( $this->inline_scripts ) . '. Adding ' . count( $inline_scripts_to_add ) . ' new inline script(s).' );
		}

		$processed_inline_scripts = array();
		foreach ( $inline_scripts_to_add as $inline_script_data ) {
			if ( $logger->is_active() ) {
				$log_handle         = $inline_script_data['handle']   ?? 'N/A';
				$log_position       = $inline_script_data['position'] ?? 'after';
				$log_content_length = strlen($inline_script_data['content'] ?? '');
				$logger->debug( "EnqueueAbstract::add_inline_scripts - Processing inline script for handle '{$log_handle}', position '{$log_position}', content length {$log_content_length}." );
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
							$logger->debug( "EnqueueAbstract::add_inline_scripts - Inline script for '{$parent_handle}' associated with parent hook: '{$inline_script_data['parent_hook']}'. Original parent script hook: '" . ( $original_script_definition['hook'] ?? 'N/A' ) . "'." );
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
			$logger->debug( 'EnqueueAbstract::add_inline_scripts - Exiting. New total inline script count: ' . count( $this->inline_scripts ) );
		}
		return $this;
	}

	/**
	 * Process and add all registered inline scripts.
	 */
	public function enqueue_inline_scripts(): self {
		$logger = $this->get_logger();
		if ( $logger->is_active() ) {
			$logger->debug( 'EnqueueAbstract::enqueue_inline_scripts - Entered method.' );
		}
		foreach ( $this->inline_scripts as $inline_script ) {
			$handle      = $inline_script['handle']      ?? '';
			$content     = $inline_script['content']     ?? '';
			$position    = $inline_script['position']    ?? 'after';
			$condition   = $inline_script['condition']   ?? null;
			$parent_hook = $inline_script['parent_hook'] ?? null;

			if ( $logger->is_active() ) {
				$logger->debug( "EnqueueAbstract::enqueue_inline_scripts - Processing inline script for handle: '" . esc_html( $handle ) . "'. Parent hook: '" . esc_html( $parent_hook ?: 'None' ) . "'." );
			}

			// If this inline script is tied to a parent script on a specific hook, skip it here.
			// It will be handled by enqueue_deferred_scripts.
			if ( ! empty( $parent_hook ) ) {
				if ( $logger->is_active() ) {
					$logger->debug( "EnqueueAbstract::enqueue_inline_scripts - Deferring inline script for '{$handle}' because its parent is on hook '{$parent_hook}'." );
				}
				continue;
			}

			// Skip if required parameters are missing.
			if ( empty( $handle ) || empty( $content ) ) {
				if ( $logger->is_active() ) {
					$logger->error( 'EnqueueAbstract::enqueue_inline_scripts - Skipping (non-deferred) inline script due to missing handle or content. Handle: ' . esc_html( $handle ) );
				}
				continue;
			}

			// Check if the condition is met.
			if ( is_callable( $condition ) && ! $condition() ) {
				if ( $logger->is_active() ) {
					$logger->debug( "EnqueueAbstract::enqueue_inline_scripts - Condition not met for inline script '{$handle}'. Skipping." );
				}
				continue;
			}

			// Check if the parent script is registered or enqueued.
			$is_registered = wp_script_is( $handle, 'registered' );
			$is_enqueued   = wp_script_is( $handle, 'enqueued' );
			if ( $logger->is_active() ) {
				$logger->debug( "EnqueueAbstract::enqueue_inline_scripts - (Non-deferred) Script '" . esc_html( $handle ) . "' is_registered: " . ( $is_registered ? 'TRUE' : 'FALSE' ) );
				$logger->debug( "EnqueueAbstract::enqueue_inline_scripts - (Non-deferred) Script '" . esc_html( $handle ) . "' is_enqueued: " . ( $is_enqueued ? 'TRUE' : 'FALSE' ) );
			}
			if ( ! $is_registered && ! $is_enqueued ) {
				if ( $logger->is_active() ) {
					$logger->error( "EnqueueAbstract::enqueue_inline_scripts - (Non-deferred) Cannot add inline script. Parent script '" . esc_html( $handle ) . "' is not registered or enqueued." );
				}
				continue;
			}

			// Add the inline script using WordPress functions.
			if ( $logger->is_active() ) {
				$logger->debug( "EnqueueAbstract::enqueue_inline_scripts - (Non-deferred) Attempting to add inline script for '" . esc_html( $handle ) . "' with position '" . esc_html( $position ) . "'." );
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
	 * Enqueue scripts that were deferred to a specific hook.
	 *
	 * @param string $hook The WordPress hook name.
	 * @return void This method returns `void` because it's primarily designed as a callback for WordPress action hooks.
	 *              When executed within a hook, its main role is to perform enqueueing operations (side effects),
	 *              rather than being part of a fluent setup chain.
	 */
	public function enqueue_deferred_scripts( string $hook ): void {
		$logger = $this->get_logger();
		if ( $logger->is_active() ) {
			$logger->debug( "EnqueueAbstract::enqueue_deferred_scripts - Entered for hook: \"{$hook}\"" );
		}
		if ( ! isset( $this->deferred_scripts[ $hook ] ) ) {
			if ( $logger->is_active() ) {
				$logger->debug( "EnqueueAbstract::enqueue_deferred_scripts - Hook \"{$hook}\" not found in deferred scripts. Nothing to process." );
			}
			return; // Hook was never set, nothing to do or unset.
		}

		// Retrieve scripts for the hook and then immediately unset it to mark as processed.
		$scripts_on_this_hook = $this->deferred_scripts[ $hook ];
		unset( $this->deferred_scripts[ $hook ] ); // Moved unset action here

		if ( empty( $scripts_on_this_hook ) ) {
			if ( $logger->is_active() ) {
				$logger->debug( "EnqueueAbstract::enqueue_deferred_scripts - Hook \"{$hook}\" was set but had no scripts. It has now been cleared." );
			}
			return; // No actual scripts to process for this hook.
		}

		foreach ( $scripts_on_this_hook as $script_definition ) {
			$original_handle = $script_definition['handle'] ?? null;

			if ( empty( $original_handle ) ) {
				if ( $logger->is_active() ) {
					$logger->error( "EnqueueAbstract::enqueue_deferred_scripts - Script definition missing 'handle' for hook '{$hook}'. Skipping this script definition." );
				}
				continue; // Skip this script definition if handle is missing
			}

			$is_registered = wp_script_is( $original_handle, 'registered' );
			$is_enqueued   = wp_script_is( $original_handle, 'enqueued' );
			if ( $logger->is_active() ) {
				$logger->debug( "EnqueueAbstract::enqueue_deferred_scripts - (Deferred) Script '" . esc_html( $original_handle ) . "' is_registered: " . ( $is_registered ? 'TRUE' : 'FALSE' ) );
				$logger->debug( "EnqueueAbstract::enqueue_deferred_scripts - (Deferred) Script '" . esc_html( $original_handle ) . "' is_enqueued: " . ( $is_enqueued ? 'TRUE' : 'FALSE' ) );
			}

			$skip_main_registration_and_enqueue = $is_enqueued;

			if ( $skip_main_registration_and_enqueue ) {
				if ( $logger->is_active() ) {
					$logger->debug( "EnqueueAbstract::enqueue_deferred_scripts - Script '{$original_handle}' is already enqueued. Skipping its registration and main enqueue call on hook '{$hook}'. Inline scripts will still be processed." );
				}
			} else {
				if ( $logger->is_active() ) {
					$logger->debug( "EnqueueAbstract::enqueue_deferred_scripts - Processing deferred script: \"{$original_handle}\" for hook: \"{$hook}\"" );
				}
				// _process_single_script returns the original handle, so $original_handle is the one to use.
				$processed_handle_confirmation = $this->_process_single_script( $script_definition );

				if ( empty( $processed_handle_confirmation ) || $processed_handle_confirmation !== $original_handle ) {
					if ( $logger->is_active() ) {
						$logger->warning( "EnqueueAbstract::enqueue_deferred_scripts - _process_single_script returned an unexpected handle ('{$processed_handle_confirmation}') or empty for original handle '{$original_handle}' on hook \"{$hook}\". Skipping main script enqueue and its inline scripts." );
					}
					continue; // Critical issue, skip this script and its inlines.
				}

				if ( $logger->is_active() ) {
					$logger->debug( "EnqueueAbstract::enqueue_deferred_scripts - Calling wp_enqueue_script for deferred: \"{$original_handle}\" on hook: \"{$hook}\"" );
				}
				wp_enqueue_script( $original_handle ); // Enqueue the main deferred script using its original handle.
			}

			// NOW, process any inline scripts associated with this $original_handle AND this $hook.
			// This block is now reached even if $skip_main_registration_and_enqueue was true.
			if ( $logger->is_active() ) {
				$logger->debug( "EnqueueAbstract::enqueue_deferred_scripts - Checking for inline scripts for handle '{$original_handle}' on hook '{$hook}'." );
			}
			foreach ( $this->inline_scripts as $inline_script_key => $inline_script_data ) {
				// Ensure keys exist before accessing.
				$inline_target_handle = $inline_script_data['handle']      ?? null;
				$inline_parent_hook   = $inline_script_data['parent_hook'] ?? null;

				// Match against $original_handle for the current script definition being processed.
				if ( $inline_target_handle === $original_handle && $inline_parent_hook === $hook ) {
					$content   = $inline_script_data['content']   ?? '';
					$position  = $inline_script_data['position']  ?? 'after';
					$condition = $inline_script_data['condition'] ?? null;

					if ( empty( $content ) ) {
						if ( $logger->is_active() ) {
							$logger->error( "EnqueueAbstract::enqueue_deferred_scripts - Skipping inline script for deferred '{$original_handle}' due to missing content." );
						}
						continue;
					}

					if ( is_callable( $condition ) && ! $condition() ) {
						if ( $logger->is_active() ) {
							$logger->debug( "EnqueueAbstract::enqueue_deferred_scripts - Condition false for inline script for deferred '{$original_handle}'." );
						}
						continue;
					}

					if ( $logger->is_active() ) {
						$logger->debug( "EnqueueAbstract::enqueue_deferred_scripts - Adding inline script for deferred '{$original_handle}' (position: {$position}) on hook '{$hook}'." );
					}
					// Always use $original_handle for wp_add_inline_script as it's the handle WordPress knows or will know.
					$parent_is_registered = wp_script_is($original_handle, 'registered');
					if ($parent_is_registered) {
						wp_add_inline_script( $original_handle, $content, $position );
					}
					// Remove the inline script from $this->inline_scripts once processed.
					unset($this->inline_scripts[$inline_script_key]);
				}
			}
		}
		// The hook is unset earlier in the method after its scripts are retrieved.
		if ($logger->is_active()) {
			$logger->debug( "EnqueueAbstract::enqueue_deferred_scripts - Exited for hook: \"{$hook}\"" );
		}
	}

	/**
	 * Processes a single script definition, handling registration, enqueuing, attributes, and inline data.
	 *
	 * @param array<string, mixed> $script The script configuration array.
	 * @return string The script handle that was registered, or empty string if conditions not met.
	 */
	protected function _process_single_script( array $script ): ?string { // Return type changed to ?string
		$handle     = $script['handle']     ?? '';
		$src        = $script['src']        ?? '';
		$deps       = $script['deps']       ?? array();
		$ver        = $script['version']    ?? false;
		$in_footer  = $script['in_footer']  ?? false;
		$condition  = $script['condition']  ?? null;
		$attributes = $script['attributes'] ?? array();
		$wp_data    = $script['wp_data']    ?? array();

		if (empty($handle)) {
			return null;
		}

		if (is_callable($condition)) {
			if (!$condition()) { // Direct call to callable
				return null;
			}
		}

		$registered = wp_register_script( $handle, $src, $deps, $ver, $in_footer );

		if (!$registered) {
			return null;
		}

		// Apply WordPress script data.
		if ( ! empty( $wp_data ) && is_array( $wp_data ) ) {
			foreach ( $wp_data as $key => $value ) {
				wp_script_add_data( $handle, $key, $value );
			}
		}

		// Apply HTML attributes.
		if ( ! empty( $attributes ) && is_array( $attributes ) ) {
			add_filter(
				'script_loader_tag',
				function ( $tag, $tag_handle, $tag_src ) use ( $handle, $attributes ) {
					return $this->_modify_script_tag_for_attributes(
						$tag,
						$tag_handle,
						$handle,     // Pass the specific handle for *this* script
						$attributes  // Pass the specific attributes for *this* script
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
	 * @return string The modified (or original) HTML script tag.
	 */
	protected function _modify_script_tag_for_attributes(
		string $tag,
		string $filter_tag_handle,
		string $script_handle_to_match,
		array $attributes_to_apply
	): string {
		// If the filter is not for the script we're interested in, return the original tag.
		if ( $filter_tag_handle !== $script_handle_to_match ) {
			return $tag;
		}

		// Work on a local copy of attributes to handle modifications like unsetting 'type'
		$local_attributes = $attributes_to_apply;

		// Special handling for module scripts.
		if ( isset( $local_attributes['type'] ) && 'module' === $local_attributes['type'] ) {
			// Position type="module" right after <script.
			$tag = preg_replace( '/<script\\s/', '<script type="module" ', $tag );
			// Remove type from attributes so it's not added again.
			unset( $local_attributes['type'] );
		}

		// Check for malformed tag (no closing '>') AFTER potential tag modification
		$pos = strpos( $tag, '>' );
		if ( false === $pos ) {
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
			} elseif ( false !== $value && null !== $value ) { // Regular attributes with values.
				$attr_str .= ' ' . esc_attr( $attr ) . '="' . esc_attr( $value ) . '"';
			}
		}

		// Insert attributes before the closing bracket.
		$modified_tag = substr( $tag, 0, $pos ) . $attr_str . substr( $tag, $pos );
		return $modified_tag;
	}

	// STYLES HANDLING

	/**
	 * Array of styles to enqueue.
	 *
	 * @var array<int, array<int, mixed>>
	 */
	protected array $styles = array();

	/**
	 * Array of inline styles to be added.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	protected array $inline_styles = array();

	/**
	 * Array of styles to be enqueued at specific hooks.
	 *
	 * @var array<string, array<int, array<string, mixed>>>
	 */
	protected array $deferred_styles = array();

	/**
	 * Get the array of registered styles.
	 *
	 * @return array<string, array<int, mixed>> The registered styles.
	 */
	public function get_styles() {
		$styles = array(
			'general'  => $this->styles,
			'deferred' => $this->deferred_styles,
			'inline'   => $this->inline_styles,
		);
		return $styles;
	}

	/**
	 * Adds a collection of stylesheet definitions to an internal queue for later processing.
	 *
	 * This method is chain-able. It stores the provided style definitions, **overwriting**
	 * any styles previously added via this method. The actual registration and enqueuing
	 * occur when the `enqueue()` or `enqueue_styles()` method is subsequently called.
	 *
	 * @param  array<int, array<string, mixed>> $styles The array of styles to enqueue. Each style should be an array with the following keys:
	 *     @type string      $handle     (Required) Name of the stylesheet. Must be unique.
	 *     @type string      $src        (Required) URL to the stylesheet resource.
	 *     @type array       $deps       (Optional) An array of registered stylesheet handles this stylesheet depends on. Defaults to an empty array.
	 *     @type string|false|null $version    (Optional) Stylesheet version. `false` (default) uses the plugin's version, `null` adds no version, a string sets a specific version.
	 *     @type string      $media      (Optional) The media for which this stylesheet has been defined (e.g., 'all', 'screen', 'print'). Defaults to 'all'.
	 *     @type callable|null $condition  (Optional) A callback returning a boolean. If `false`, the style is not enqueued. Defaults to null (no condition).
	 *     @type string|null $hook       (Optional) WordPress hook (e.g., 'admin_enqueue_scripts') to defer enqueuing until. Defaults to null (no deferral).
	 * @return self Returns the instance of this class for method chaining.
	 * @see    self::enqueue_styles()
	 * @see    self::enqueue()
	 */
	public function add_styles( array $styles ): self {
		$logger = $this->get_logger();
		if ( $logger->is_active() ) {
			$logger->debug( 'EnqueueAbstract: Adding ' . count( $styles ) . ' styles to the queue.' );
		}
		$this->styles = $styles;

		return $this;
	}

	/**
	 * Registers styles with WordPress without enqueueing them.
	 *
	 * This method provides a way to explicitly register styles, making them known to WordPress
	 * without immediately adding them to the page. This can be useful for styles that might be
	 * enqueued later by other components or conditionally. Styles with a 'hook' defined
	 * will be skipped, as their registration is handled by the deferred enqueueing mechanism.
	 *
	 * @param  array<int, array<string, mixed>> $styles Optional. An array of style definitions to add and register.
	 *                                                  If provided, these styles will be added to the internal
	 *                                                  collection before processing. It's generally recommended
	 *                                                  to use `add_styles()` separately for clarity.
	 * @return self Returns the instance of this class for method chaining.
	 */
	public function register_styles( array $styles = array() ): self {
		$logger = $this->get_logger();
		if ( $logger->is_active() ) {
			$logger->debug( 'EnqueueAbstract::register_styles - Entered.' );
		}

		if ( ! empty( $styles ) ) {
			if ( $logger->is_active() ) {
				$logger->debug( 'EnqueueAbstract::register_styles - Styles directly passed. Adding them to internal collection via add_styles().' );
			}
			$this->add_styles( $styles ); // Use internal add_styles to ensure consistency and hook processing
		}

		// Always use the internal styles array for consistency.
		$current_styles_to_process = $this->styles; // Use a local copy to avoid issues if $this->styles is modified during iteration by add_styles called elsewhere

		if ( $logger->is_active() ) {
			$logger->debug( 'EnqueueAbstract::register_styles - Processing ' . count( $current_styles_to_process ) . ' style definition(s) for registration.' );
		}

		foreach ( $current_styles_to_process as $index => $style_definition ) {
			$handle_for_log = $style_definition['handle'] ?? 'N/A_at_index_' . $index;

			// Log initial processing attempt for this style within register_styles context
			if ( $logger->is_active() ) {
				$logger->debug( "EnqueueAbstract::register_styles - Attempting to process style: \"{$handle_for_log}\", original index: {$index}." );
			}

			// Skip styles with hooks (they'll be registered when the hook fires during enqueue_deferred_styles)
			if ( ! empty( $style_definition['hook'] ) ) {
				if ( $logger->is_active() ) {
					$logger->debug( "EnqueueAbstract::register_styles - Style \"{$handle_for_log}\" has a hook '{$style_definition['hook']}'. Skipping registration here (deferred handling)." );
				}
				continue;
			}

			// Call _process_single_style
			// $hook_name is null because we are skipping hooked styles
			// $do_register = true, $do_enqueue = false, $do_process_inline = false
			$this->_process_single_style(
				$style_definition,
				'register_styles', // processing_context
				null,              // hook_name
				true,              // do_register
				false,             // do_enqueue
				false              // do_process_inline
			);
			// _process_single_style now handles its own internal logging for success/failure/skip.
			// The "Called wp_register_style for..." log is now inside _process_single_style if registration occurs.
		}

		if ( $logger->is_active() ) {
			$logger->debug( 'EnqueueAbstract::register_styles - Exited.' );
		}
		return $this;
	}

	/**
	 * Processes and enqueues a given array of stylesheet definitions.
	 *
	 * This method iterates through an array of stylesheet definitions. For each style,
	 * it first checks any associated `$condition` callback. If the condition passes
	 * (or if no condition is set), the style is then processed.
	 * - If a `$hook` is specified, the style's enqueuing is deferred: it's stored
	 *   internally, and an action is registered (if not already) for the specified hook.
	 *   When the hook fires, `enqueue_deferred_styles()` will process the style and
	 *   any associated inline styles for that hook.
	 * - Otherwise (no `$hook`), the style is registered and enqueued immediately using
	 *   `wp_register_style()` and `wp_enqueue_style()`.
	 *
	 * Inline styles associated with non-deferred main styles are handled by a separate call
	 * to `enqueue_inline_styles()`. Inline styles for deferred main styles are handled by
	 * `enqueue_deferred_styles()`.
	 *
	 * @param  array<int, array<string, mixed>> $styles The array of style definitions to process and enqueue.
	 *                                                Each style definition should follow the structure documented in `add_styles()`.
	 *     @type string      $handle     (Required) Name of the stylesheet. Must be unique.
	 *     @type string      $src        (Required) URL to the stylesheet resource.
	 *     @type array<int, string> $deps (Optional) An array of registered stylesheet handles this stylesheet depends on. Defaults to an empty array.
	 *     @type string|false|null $version    (Optional) Stylesheet version. `false` (default) uses the plugin's version, `null` adds no version, a string sets a specific version.
	 *     @type string      $media      (Optional) The media for which this stylesheet has been defined (e.g., 'all', 'screen', 'print'). Defaults to 'all'.
	 *     @type callable|null $condition  (Optional) A callback returning a boolean. If `false`, the style is not enqueued. Defaults to `null` (no condition).
	 *     @type string|null $hook       (Optional) WordPress hook (e.g., 'admin_enqueue_scripts') to defer enqueuing until. Defaults to `null` (immediate processing).
	 * @return self Returns the instance of this class for method chaining.
	 * @see    self::add_styles()
	 * @see    self::enqueue_deferred_styles()
	 * @see    self::enqueue_inline_styles()
	 * @see    wp_register_style()
	 * @see    wp_enqueue_style()
	 */
	public function enqueue_styles( array $styles ): self {
		$logger = $this->get_logger();
		if ($logger->is_active()) {
			$logger->debug( 'EnqueueAbstract::enqueue_styles - Entered. Processing ' . count( $styles ) . ' style definition(s).' );
		}

		foreach ( $styles as $index => $style_definition ) {
			$handle_for_log = $style_definition['handle'] ?? 'N/A';
			if ($logger->is_active()) {
				$logger->debug( "EnqueueAbstract::enqueue_styles - Processing style: \"{$handle_for_log}\", original index: {$index}." );
			}
			$hook = $style_definition['hook'] ?? null;

			if ( ! empty( $hook ) ) {
				// Defer this style.
				if ($logger->is_active()) {
					$logger->debug( "EnqueueAbstract::enqueue_styles - Deferring style \"{$handle_for_log}\" (original index {$index}) to hook: \"{$hook}\"." );
				}
				$this->deferred_styles[ $hook ][ $index ] = $style_definition;

				// Ensure the action for deferred styles is added only once per hook.
				// The has_action check is crucial here.
				if ( ! has_action( $hook, array( $this, 'enqueue_deferred_styles' ) ) ) {
					$this->_do_add_action( $hook, array( $this, 'enqueue_deferred_styles' ), 10, 1 );
					if ($logger->is_active()) {
						$logger->debug( "EnqueueAbstract::enqueue_styles - Added action for 'enqueue_deferred_styles' on hook: \"{$hook}\"." );
					}
				} else {
					if ($logger->is_active()) {
						$logger->debug( "EnqueueAbstract::enqueue_styles - Action for 'enqueue_deferred_styles' on hook '{$hook}' already exists." );
					}
				}
			} else {
				// Process immediately using _process_single_style
				// $hook_name is null for immediate styles
				// $do_register = true, $do_enqueue = true, $do_process_inline = true
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
			$logger->debug( 'EnqueueAbstract::enqueue_styles - Exited.' );
		}
		return $this;
	}

	/**
	 * Enqueues styles that were deferred to a specific WordPress hook.
	*
	* This method is typically called by an action hook that was registered
	* when a style with a 'hook' parameter was processed by `enqueue_styles()`.
	* It iterates through the styles stored in `$this->deferred_styles` for the
	* given hook, checks their conditions, registers and enqueues them using
	* `wp_register_style()` and `wp_enqueue_style()`.
	*
	* It also processes any inline styles associated with these deferred styles
	* that were also deferred to the same hook.
	*
	* @param string $hook_name The WordPress hook name that triggered this method.
	* @return void This method returns `void` because it's primarily designed as a callback for WordPress action hooks.
	*              When executed within a hook, its main role is to perform enqueueing operations (side effects),
	*              rather than being part of a fluent setup chain.
	* @see self::enqueue_styles()
	* @see wp_register_style()
	* @see wp_enqueue_style()
	* @see wp_add_inline_style()
	 */
	public function enqueue_deferred_styles( string $hook_name ): void {
		$logger = $this->get_logger();
		if ($logger->is_active()) {
			$logger->debug( "EnqueueAbstract::enqueue_deferred_styles - Entered for hook: \"{$hook_name}\"." );
		}

		if ( ! isset( $this->deferred_styles[ $hook_name ] ) || empty( $this->deferred_styles[ $hook_name ] ) ) {
			if ($logger->is_active()) {
				$logger->debug( "EnqueueAbstract::enqueue_deferred_styles - No styles found deferred for hook: \"{$hook_name}\". Exiting." );
			}
			// Unset even if empty to clean up the key, though it might already be unset or never set.
			unset( $this->deferred_styles[ $hook_name ] );
			return;
		}

		$styles_for_hook = $this->deferred_styles[ $hook_name ];

		foreach ( $styles_for_hook as $original_index => $style_definition ) {
			// $handle is extracted inside _process_single_style, use it from there for logging if needed
			// For the initial log here, we can use the handle from the definition.
			$handle_for_log = $style_definition['handle'] ?? 'N/A_at_original_index_' . $original_index;
			if ($logger->is_active()) {
				$logger->debug( "EnqueueAbstract::enqueue_deferred_styles - Processing deferred style: \"{$handle_for_log}\" (original index {$original_index}) for hook: \"{$hook_name}\"." );
			}

			// Call _process_single_style for each deferred style
			// $do_register = true, $do_enqueue = true, $do_process_inline = true
			$this->_process_single_style(
				$style_definition,
				'enqueue_deferred', // processing_context
				$hook_name,         // hook_name
				true,               // do_register
				true,               // do_enqueue
				true                // do_process_inline
			);
			// All detailed logging for registration, enqueueing, conditions, and inline styles
			// is now handled within _process_single_style and _process_inline_styles.
			// The MEMORY abe280e6-5111-4b59-9785-3a87a46f9dd1 details the expected log sequence.
		}

		// Clear the processed deferred styles for this hook to prevent re-processing.
		unset( $this->deferred_styles[ $hook_name ] );
		if ($logger->is_active()) {
			$logger->debug( "EnqueueAbstract::enqueue_deferred_styles - Exited for hook: \"{$hook_name}\"." );
		}
	}

	/**
	 * Chain-able call to add inline styles.
	 *
	 * @param string      $handle     (required) Handle of the style to attach the inline style to. Must be already registered.
	 * @param string      $content    (required) The inline style content.
	 * @param string      $position   (optional) Whether to add the inline style before or after the registered style. Default 'after'.
	 * @param callable|null $condition  (optional) Callback function that determines if the inline style should be added.
	 * @param string|null $parent_hook (optional) The WordPress hook name that the parent style is deferred to.
	 * @return self
	 */
	public function add_inline_styles( string $handle, string $content, string $position = 'after', ?callable $condition = null, ?string $parent_hook = null ): self {
		$logger = $this->get_logger();
		if ( $logger->is_active() ) {
			$logger->debug( 'EnqueueAbstract::add_inline_styles - Entered. Current inline style count: ' . count( $this->inline_styles ) . '. Adding new inline style for handle: ' . esc_html( $handle ) );
		}

		$inline_style_item = array(
			'handle'      => $handle,
			'content'     => $content,
			'position'    => $position,
			'condition'   => $condition,
			'parent_hook' => $parent_hook,
		);

		// Check if this parent_handle corresponds to a deferred style to inherit its hook.
		// This logic assumes $this->styles contains the definitions of all registered styles, including their potential 'hook' for deferral.
		foreach ( $this->styles as $original_style_definition ) {
			if ( ( $original_style_definition['handle'] ?? null ) === $handle && ! empty( $original_style_definition['hook'] ) ) {
				// Only override parent_hook if it wasn't explicitly set for the inline style.
				if ( null === $inline_style_item['parent_hook'] ) {
					$inline_style_item['parent_hook'] = $original_style_definition['hook'];
				}
				if ( $logger->is_active() ) {
					$logger->debug( "EnqueueAbstract::add_inline_styles - Inline style for '{$handle}' associated with parent hook: '{$inline_style_item['parent_hook']}'. Original parent style hook: '" . ( $original_style_definition['hook'] ?? 'N/A' ) . "'." );
				}
				break; // Found the parent style, no need to check further.
			}
		}

		$this->inline_styles[] = $inline_style_item;

		if ( $logger->is_active() ) {
			$logger->debug( 'EnqueueAbstract::add_inline_styles - Exiting. New total inline style count: ' . count( $this->inline_styles ) );
		}
		return $this;
	}

	/**
	 * Process and add all registered inline styles.
	 *
	 * Iterates through the `$inline_styles` property, checks conditions,
	 * and uses `wp_add_inline_style()` to add them.
	 * Similar to `enqueue_inline_scripts` but for styles.
	 *
	 * @return self
	 */
	public function enqueue_inline_styles(): self {
		$logger = $this->get_logger();
		if ($logger->is_active()) {
			$logger->debug( 'EnqueueAbstract::enqueue_inline_styles - Entered method. Attempting to process any remaining immediate inline styles.' );
		}

		// Collect unique parent handles for immediate inline styles
		$immediate_parent_handles = array();
		// Iterate by key to potentially allow unsetting if needed, though _process_inline_styles handles unsetting from $this->inline_styles
		foreach ( $this->inline_styles as $key => $inline_style_data ) {
			// Basic validation of the structure of $inline_style_data
			if (!is_array($inline_style_data)) {
				if ($logger->is_active()) {
					$logger->warning("EnqueueAbstract::enqueue_inline_styles - Invalid inline style data at key '{$key}'. Skipping.");
				}
				continue;
			}
			$parent_hook = $inline_style_data['parent_hook'] ?? null;
			$handle      = $inline_style_data['handle']      ?? null;

			if ( empty( $parent_hook ) && !empty($handle) ) {
				// This is an immediate inline style
				// We only need to call _process_inline_styles once per parent handle
				if ( !in_array($handle, $immediate_parent_handles, true) ) {
					$immediate_parent_handles[] = $handle;
				}
			}
		}

		if (empty($immediate_parent_handles)) {
			if ($logger->is_active()) {
				$logger->debug( 'EnqueueAbstract::enqueue_inline_styles - No immediate inline styles found needing processing.' );
			}
			return $this;
		}

		if ($logger->is_active()) {
			$logger->debug( 'EnqueueAbstract::enqueue_inline_styles - Found ' . count($immediate_parent_handles) . ' unique parent handle(s) with immediate inline styles to process: ' . implode(', ', array_map('esc_html', $immediate_parent_handles) ) );
		}

		foreach ( $immediate_parent_handles as $parent_handle_to_process ) {
			// Call _process_inline_styles for immediate context (hook_name is null)
			// The _process_inline_styles method handles checking parent registration, conditions, adding, and unsetting.
			$this->_process_inline_styles(
				$parent_handle_to_process,
				null, // hook_name (null for immediate)
				'enqueue_inline_styles' // processing_context
			);
		}

		if ($logger->is_active()) {
			$logger->debug( 'EnqueueAbstract::enqueue_inline_styles - Exited method.' );
		}
		return $this;
	}

	/**
	 * Wraps the global add_action function to allow for easier mocking in tests.
	 *
	 * @param string   $hook          The name of the action to which the $function_to_add is hooked.
	 * @param callable $callback      The name of the function you wish to be called.
	 * @param int      $priority      Optional. Used to specify the order in which the functions
	 *                                associated with a particular action are executed. Default 10.
	 * @param int      $accepted_args Optional. The number of arguments the function accepts. Default 1.
	 * @return void
	 */
	protected function _do_add_action( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
		add_action( $hook, $callback, $priority, $accepted_args );
	}

	/**
	 * Processes inline styles associated with a specific parent style handle and hook context.
	 *
	 * This method iterates through the collected inline styles, finds those matching
	 * the parent handle and hook context, checks their conditions, adds them using
	 * `wp_add_inline_style`, logs the actions, and removes them from the collection
	 * to prevent reprocessing.
	 *
	 * @param string      $parent_handle      The handle of the parent style.
	 * @param string|null $hook_name          (Optional) The hook name if processing for a deferred context.
	 *                                        Null for immediate context.
	 * @param string      $processing_context A string indicating the context (e.g., 'immediate', 'deferred')
	 *                                        for logging purposes.
	 * @return void
	 */
	protected function _process_inline_styles(string $parent_handle, ?string $hook_name = null, string $processing_context = 'immediate'): void {
		$logger = $this->get_logger();
		// Corrected log prefix to match the actual method name for clarity
		$log_prefix_base = "EnqueueAbstract::_process_inline_styles (context: {$processing_context}) - ";

		$logger->debug( "{$log_prefix_base}Checking for inline styles for parent handle '{$parent_handle}'" . ($hook_name ? " on hook '{$hook_name}'." : '.') );

		$keys_to_unset = array();

		foreach ( $this->inline_styles as $key => $inline_style_data ) {
			// Basic validation of inline_style_data structure
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
	 * @param string               $processing_context  Context of the call (e.g., 'register', 'enqueue_immediate', 'enqueue_deferred').
	 *                                                  Used for logging and conditional logic (like wp_style_is checks).
	 * @param string|null          $hook_name           The hook name if processing in a deferred context, null otherwise.
	 * @param bool                 $do_register         Whether to register the style.
	 * @param bool                 $do_enqueue          Whether to enqueue the style.
	 * @param bool                 $do_process_inline   Whether to process associated inline styles.
	 * @return bool True if the style was processed successfully, false otherwise (e.g., condition failed, invalid definition).
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

		// Parameter extraction with defaults
		$handle    = $style_definition['handle']    ?? null;
		$src       = $style_definition['src']       ?? null;
		$deps      = $style_definition['deps']      ?? array();
		$version   = $style_definition['version']   ?? false;
		$media     = $style_definition['media']     ?? 'all';
		$condition = $style_definition['condition'] ?? null;

		$log_handle_context = $handle ?? 'N/A';
		$log_hook_context   = $hook_name ? " on hook '{$hook_name}'" : '';

		if ( $logger->is_active() ) {
			$logger->debug( "EnqueueAbstract::_process_single_style - Processing style '{$log_handle_context}'{$log_hook_context} in context '{$processing_context}'." );
		}

		// Validate essential parameters
		if ( empty( $handle ) || ( $do_register && empty( $src ) ) ) {
			if ( $logger->is_active() ) {
				$logger->warning( "EnqueueAbstract::_process_single_style - Invalid style definition. Missing handle or src. Skipping. Handle: '{$log_handle_context}'{$log_hook_context}." );
			}
			return false;
		}

		// Check condition
		if ( is_callable( $condition ) && ! $condition() ) {
			if ( $logger->is_active() ) {
				$logger->debug( "EnqueueAbstract::_process_single_style - Condition not met for style '{$handle}'{$log_hook_context}. Skipping." );
			}
			return false;
		}

		// Registration
		if ( $do_register ) {
			// Specific logic for 'enqueue_deferred' context: check if already registered.
			if ( 'enqueue_deferred' === $processing_context && wp_style_is( $handle, 'registered' ) ) {
				if ( $logger->is_active() ) {
					$logger->debug( "EnqueueAbstract::_process_single_style - Style '{$handle}'{$log_hook_context} already registered. Skipping wp_register_style.");
				}
			} else {
				if ( $logger->is_active() ) {
					$logger->debug( "EnqueueAbstract::_process_single_style - Registering style '{$handle}'{$log_hook_context}." );
				}
				wp_register_style( $handle, $src, $deps, $version, $media );
			}
		}

		// Enqueueing
		if ( $do_enqueue ) {
			// Specific logic for 'enqueue_deferred' context: check if already enqueued.
			if ( 'enqueue_deferred' === $processing_context && wp_style_is( $handle, 'enqueued' ) ) {
				if ( $logger->is_active() ) {
					$logger->debug( "EnqueueAbstract::_process_single_style - Style '{$handle}'{$log_hook_context} already enqueued. Skipping wp_enqueue_style.");
				}
			} else {
				if ( $logger->is_active() ) {
					$logger->debug( "EnqueueAbstract::_process_single_style - Enqueuing style '{$handle}'{$log_hook_context}." );
				}
				wp_enqueue_style( $handle );
			}
		}

		// Inline Styles
		if ( $do_process_inline ) {
			if ( $logger->is_active() ) {
				$logger->debug( "EnqueueAbstract::_process_single_style - Checking for inline styles for '{$handle}'{$log_hook_context}." );
			}
			$this->_process_inline_styles( $handle, $hook_name, $processing_context ); // Pass hook_name and processing_context for context
		}

		if ( $logger->is_active() ) {
			$logger->debug( "EnqueueAbstract::_process_single_style - Finished processing style '{$handle}'{$log_hook_context}." );
		}

		return true;
	}

	// MEDIA HANDLING

	/**
	 * Array of configurations for loading the WordPress media tools (uploader, library interface, etc.).
	 * Each configuration details how and when `wp_enqueue_media()` should be called.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	protected array $media_tool_configs = array();

	/**
	 * Array of media tool configurations to be loaded at specific WordPress action hooks.
	 *
	 * @var array<string, array<int, array<string, mixed>>>
	 */
	protected array $deferred_media_tool_configs = array();

	/**
	 * Gets the array of registered configurations for loading WordPress media tools.
	 *
	 * @return array<string, array<int, mixed>> The registered media tool configurations.
	 */
	public function get_media_tool_configs() {
		$configs = array(
			'general'  => $this->media_tool_configs,
			'deferred' => $this->deferred_media_tool_configs,
		);
		return $configs;
	}

	/**
	 * Adds a collection of configurations for loading WordPress media tools (uploader, library interface, etc.)
	 * to an internal queue for later processing. This method is chain-able.
	 *
	 * It stores the provided configurations, **overwriting** any previously added via this method.
	 * The actual loading of media tools occurs when `enqueue()` or `enqueue_media()` is subsequently called.
	 *
	 * `wp_enqueue_media()` is the WordPress core function used to load the JavaScript and CSS
	 * required for the WordPress media uploader and media library interface.
	 *
	 * @param  array<int, array<string, mixed>> $tool_configs The array of configurations. Each configuration specifies
	 *                                   how and when to load the WordPress media tools via `wp_enqueue_media()`.
	 *                                   Each item is an associative array that can contain the following keys:
	 *     @type array{post?: int|WP_Post} $args (Optional) Arguments for `wp_enqueue_media()`, e.g., `['post' => 123]`.
	 *                                   Defaults to an empty array.
	 *     @type callable|null $condition  (Optional) Callback returning boolean. If `false`, `wp_enqueue_media()` is not called.
	 *                                   Defaults to `null`.
	 *     @type string|null $hook       (Optional) WordPress hook to defer loading to, e.g., 'admin_enqueue_scripts'.
	 *                                   Defaults to `null` (meaning it will be processed by `enqueue_media` logic for default hook).
	 * @return self Returns the instance of this class for method chaining.
	 * @see    self::enqueue_media()
	 * @see    self::enqueue()
	 * @see    wp_enqueue_media()
	 */
	public function add_media( array $tool_configs ): self {
		if ($this->get_logger()->is_active()) {
			$this->get_logger()->debug( 'EnqueueAbstract: Adding ' . count( $tool_configs ) . ' media tool configurations to the queue.' );
		}
		$this->media_tool_configs = $tool_configs;

		return $this;
	}

	/**
	 * Processes an array of media tool configurations, deferring all to WordPress action hooks
	 * for loading the actual WordPress media tools (uploader, library interface, etc.) via `wp_enqueue_media()`.
	 *
	 * For each configuration in the provided array:
	 * - It's added to an internal list of deferred configurations, keyed by a WordPress action hook.
	 * - If a 'hook' is specified in the configuration, that hook is used.
	 * - If no 'hook' is specified, it defaults to the 'admin_enqueue_scripts' hook.
	 * - An action is registered (if not already present) for the determined hook, which will call
	 *   `enqueue_deferred_media_tools()` when the hook fires. This method then handles the actual
	 *   call to `wp_enqueue_media()`, after checking any 'condition' callback.
	 *
	 * @param  array<int, array<string, mixed>> $tool_configs The array of media tool configurations to process.
	 *                                   Each configuration is an associative array that can contain the following keys:
	 *     @type array{post?: int|WP_Post} $args (Optional) An array of arguments to pass to `wp_enqueue_media()`.
	 *                                   The primary argument is `['post' => \$post_id_or_object]` to associate
	 *                                   the media interface with a specific post. Defaults to an empty array.
	 *     @type callable|null $condition  (Optional) A callback function that returns a boolean.
	 *                                   If provided, this callback is executed by `enqueue_deferred_media_tools()`
	 *                                   when the associated hook fires. If the callback returns `false`,
	 *                                   `wp_enqueue_media()` is not called for this configuration. Defaults to `null`.
	 *     @type string|null $hook       (Optional) A WordPress action hook name to defer loading the media tools to.
	 *                                   If not provided or `null`, defaults to 'admin_enqueue_scripts'.
	 *                                   The configuration is always deferred.
	 * @return self Returns the instance of this class for method chaining.
	 * @see    self::add_media()
	 * @see    self::enqueue_deferred_media_tools()
	 * @see    wp_enqueue_media()
	 */
	public function enqueue_media( array $tool_configs ): self {
		$logger = $this->get_logger();
		if ( $logger->is_active() ) {
			$logger->debug( 'EnqueueAbstract::enqueue_media - Entered. Processing ' . count( $tool_configs ) . ' media tool configuration(s).' );
		}
		foreach ( $tool_configs as $index => $item_definition ) {
			if ( $logger->is_active() ) {
				$logger->debug( "EnqueueAbstract::enqueue_media - Processing media tool configuration at original index: {$index}." );
			}

			$hook = $item_definition['hook'] ?? null;

			if ( empty( $hook ) ) {
				if ( $logger->is_active() ) {
					$logger->debug( "EnqueueAbstract::enqueue_media - No hook specified for media tool configuration at original index {$index}. Defaulting to 'admin_enqueue_scripts'." );
				}
				$hook = 'admin_enqueue_scripts';
			}

			if ( $logger->is_active() ) {
				$logger->debug( "EnqueueAbstract::enqueue_media - Deferring media tool configuration at original index {$index} to hook: \"{$hook}\"." );
			}
			$this->deferred_media_tool_configs[ $hook ][ $index ] = $item_definition;

			if ( ! has_action( $hook, array( $this, 'enqueue_deferred_media_tools' ) ) ) {
				add_action( $hook, array( $this, 'enqueue_deferred_media_tools' ), 10, 1 );
				if ( $logger->is_active() ) {
					$logger->debug( "EnqueueAbstract::enqueue_media - Added action for 'enqueue_deferred_media_tools' on hook: \"{$hook}\"." );
				}
			}
		}
		if ( $logger->is_active() ) {
			$logger->debug( 'EnqueueAbstract::enqueue_media - Exited.' );
		}
		return $this;
	}

	/**
	 * Enqueues the WordPress media tools (uploader, library interface, etc.) that were deferred to a specific WordPress hook.
	 *
	 * This method is typically called by an action hook that was registered when a media tool configuration
	 * (with a 'hook' parameter) was processed by `enqueue_media()`.
	 * It iterates through the configurations stored in `$this->deferred_media_tool_configs` for the
	 * given hook, checks their conditions, and calls `wp_enqueue_media()` if the condition passes.
	 *
	 * @param string $hook_name The WordPress hook name that triggered this method.
	 * @return void This method returns `void` as it's primarily a callback for WordPress action hooks,
	 *              performing side effects (enqueueing operations) rather than returning a value for chaining.
	 * @see self::enqueue_media()
	 * @see wp_enqueue_media()
	 */
	public function enqueue_deferred_media_tools( string $hook_name ): void {
		$logger = $this->get_logger();
		if ( $logger->is_active() ) {
			$logger->debug( "EnqueueAbstract::enqueue_deferred_media_tools - Entered for hook: \"{$hook_name}\"." );
		}

		if ( ! isset( $this->deferred_media_tool_configs[ $hook_name ] ) || empty( $this->deferred_media_tool_configs[ $hook_name ] ) ) {
			if ( $logger->is_active() ) {
				$logger->debug( "EnqueueAbstract::enqueue_deferred_media_tools - No deferred media tool configurations found or already processed for hook: \"{$hook_name}\"." );
			}
			return;
		}

		$media_configs_on_this_hook = $this->deferred_media_tool_configs[ $hook_name ];

		foreach ( $media_configs_on_this_hook as $index => $item_definition ) {
			if ( $logger->is_active() ) {
				$logger->debug( "EnqueueAbstract::enqueue_deferred_media_tools - Processing deferred media tool configuration at original index {$index} for hook: \"{$hook_name}\"." );
			}

			$args      = $item_definition['args']      ?? array();
			$condition = $item_definition['condition'] ?? null;

			if ( is_callable( $condition ) && ! $condition() ) {
				if ( $logger->is_active() ) {
					$logger->debug( "EnqueueAbstract::enqueue_deferred_media_tools - Condition not met for deferred media tool configuration at original index {$index} on hook \"{$hook_name}\". Skipping." );
				}
				continue;
			}

			if ( $logger->is_active() ) {
				$logger->debug( "EnqueueAbstract::enqueue_deferred_media_tools - Calling wp_enqueue_media() for deferred configuration at original index {$index} on hook \"{$hook_name}\". Args: " . wp_json_encode( $args ) );
			}
			wp_enqueue_media( $args );
		}

		unset( $this->deferred_media_tool_configs[ $hook_name ] );

		if ( $logger->is_active() ) {
			$logger->debug( "EnqueueAbstract::enqueue_deferred_media_tools - Exited for hook: \"{$hook_name}\"." );
		}
	}

	/**
	 * Enqueues all assets that have been added to the plugin by calling their respective processing methods.
	 *
	 * This is the primary method to be called by a WordPress hook (e.g.,
	 * `wp_enqueue_scripts`, `admin_enqueue_scripts`) via the concrete class's
	 * `load()` method. It orchestrates the enqueuing of all assets that have
	 * been added via the `add_scripts()`, `add_styles()`, and `add_media()`
	 * methods, as well as processing inline scripts added via `add_inline_scripts()`.
	 *
	 * The method sequentially calls:
	 * 1. `enqueue_scripts()`: Processes and enqueues/registers main script files.
	 *    Handles immediate enqueuing and deferral to hooks.
	 * 2. `enqueue_styles()`: Processes and enqueues/registers main style files.
	 *    Handles immediate enqueuing and deferral to hooks.
	 * 3. `enqueue_media()`: Processes and enqueues media assets (e.g., via `wp_enqueue_media()`).
	 *    Handles immediate enqueuing and deferral to hooks.
	 * 4. `enqueue_inline_scripts()`: Processes and adds inline JavaScript
	 *    associated with registered scripts (both non-deferred and those whose
	 *    parent script is not deferred via a hook).
	 *
	 * Note: This method does NOT directly call `enqueue_inline_styles()`. Inline styles
	 * are typically processed by `enqueue_inline_styles()` which should be invoked
	 * appropriately within the WordPress hook lifecycle, or by a deferred style
	 * processing mechanism if the parent style is deferred.
	 *
	 * @return void
	 * @see self::load()
	 * @see self::add_scripts()
	 * @see self::add_styles()
	 * @see self::add_media()
	 * @see self::add_inline_scripts()
	 * @see self::enqueue_scripts()
	 * @see self::enqueue_styles()
	 * @see self::enqueue_media()
	 * @see self::enqueue_inline_scripts()
	 */
	public function enqueue(): void {
		$script_count = count( $this->scripts );
		$style_count  = count( $this->styles );
		$media_count  = count( $this->media );
		// enqueue_inline_scripts() processes $this->inline_scripts internally.
		// We can count it here for the initial log.
		$inline_script_count = count( $this->inline_scripts );

		$this->get_logger()->debug(
			sprintf(
				'EnqueueAbstract::enqueue - Main enqueue process started. Scripts: %d, Styles: %d, Media: %d, Inline Scripts: %d.',
				$script_count,
				$style_count,
				$media_count,
				$inline_script_count
			)
		);

		if ( $script_count > 0 ) {
			$this->enqueue_scripts( $this->scripts );
		} else {
			$this->get_logger()->debug( 'EnqueueAbstract::enqueue - No scripts to process.' );
		}

		if ( $style_count > 0 ) {
			$this->enqueue_styles( $this->styles );
		} else {
			$this->get_logger()->debug( 'EnqueueAbstract::enqueue - No styles to process.' );
		}

		if ( $media_count > 0 ) {
			$this->enqueue_media( $this->media );
		} else {
			$this->get_logger()->debug( 'EnqueueAbstract::enqueue - No media to process.' );
		}

		// enqueue_inline_scripts() has its own internal logging, including for empty cases.
		// It also directly uses $this->inline_scripts.
		$this->enqueue_inline_scripts();

		$this->get_logger()->debug( 'EnqueueAbstract::enqueue - Main enqueue process finished.' );
	}

	// HEADER & FOOTER HANDLING

	/**
	 * Array of callbacks to execute in the head section.
	 *
	 * @var array<int, callable|array<string, mixed>>
	 */
	protected array $head_callbacks = array();

	/**
	 * Executes callbacks registered to output content in the HTML <head> section.
	 *
	 * This method iterates through the `$head_callbacks` array, executing each valid callback.
	 * Callbacks can be used to output arbitrary HTML, such as custom <meta> tags,
	 * <script> tags not managed by the standard enqueue system, or inline <style> blocks.
	 * Each callback can optionally have a conditional callable to control its execution.
	 *
	 * This is typically hooked to `wp_head`.
	 *
	 * @since 1.0.0
	 */
	public function render_head(): void {
		foreach ( $this->head_callbacks as $index => $callback_data ) {
			// Extract callback and condition if provided in array format.
			$callback  = $callback_data;
			$condition = null;

			if ( is_array( $callback_data ) && isset( $callback_data['callback'] ) ) {
				$callback  = $callback_data['callback'];
				$condition = $callback_data['condition'] ?? null;
			}

			// Check if condition is met (if provided).
			if ( is_callable( $condition ) && ! $condition() ) {
				continue;
			}

			// Execute the callback.
			if ( is_callable( $callback ) ) {
				call_user_func( $callback );
			}
		}
	}

	/**
	 * Array of callbacks to execute in the footer section.
	 *
	 * @var array<int, callable|array<string, mixed>>
	 */
	protected array $footer_callbacks = array();

	/**
	 * Executes callbacks registered to output content before the closing </body> tag.
	 *
	 * This method iterates through the `$footer_callbacks` array, executing each valid callback.
	 * Callbacks can be used to output arbitrary HTML, such as tracking scripts,
	 * late-loading JavaScript, or other content suitable for the end of the document.
	 * Each callback can optionally have a conditional callable to control its execution.
	 *
	 * This is typically hooked to `wp_footer`.
	 *
	 * @since 1.0.0
	 */
	public function render_footer(): void {
		foreach ( $this->footer_callbacks as $index => $callback_data ) {
			// Extract callback and condition if provided in array format.
			$callback  = $callback_data;
			$condition = null;

			if ( is_array( $callback_data ) && isset( $callback_data['callback'] ) ) {
				$callback  = $callback_data['callback'];
				$condition = $callback_data['condition'] ?? null;
			}

			// Check if condition is met (if provided).
			if ( is_callable( $condition ) && ! $condition() ) {
				continue;
			}

			// Execute the callback.
			if ( is_callable( $callback ) ) {
				call_user_func( $callback );
			}
		}
	}
}
