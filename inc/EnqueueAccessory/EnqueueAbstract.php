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
 * - Utilize a protected `process_single_script()` method to encapsulate the registration
 *   (`wp_register_script`), attribute handling (e.g., `async`, `defer`, `type="module"`),
 *   and `wp_script_add_data()` calls for each script. This centralization helps manage
 *   the greater complexity often associated with script attributes and associated WordPress data.
 * - Inline scripts associated with a parent script can be processed immediately after the parent
 *   by `process_single_script()` if not deferred.
 *
 * Styles:
 * - Do not have a direct `process_single_style()` counterpart. Parent style registration
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
	 * @see    self::process_single_script()
	 */
	public function add_scripts( array $scripts_to_add ): self {
		if ( $this->get_logger()->is_active() ) {
			$this->get_logger()->debug( 'EnqueueAbstract::add_scripts - Entered. Current script count: ' . count( $this->scripts ) . '. Adding ' . count( $scripts_to_add ) . ' new script(s).' );
			foreach ( $scripts_to_add as $script_key => $script_data ) {
				$handle = $script_data['handle'] ?? 'N/A';
				$src    = $script_data['src']    ?? 'N/A';
				$this->get_logger()->debug( "EnqueueAbstract::add_scripts - Adding script. Key: {$script_key}, Handle: {$handle}, Src: {$src}" );
			}
		}

		// Merge new scripts with existing ones.
		// array_values ensures that if $scripts_to_add has string keys, they are discarded and scripts are appended.
		// If $this->scripts was empty, it would just become $scripts_to_add.
		foreach ( $scripts_to_add as $script ) {
			$this->scripts[] = $script; // Simple append.
		}

		if ( $this->get_logger()->is_active() ) {
			$this->get_logger()->debug( 'EnqueueAbstract::add_scripts - Exiting. New total script count: ' . count( $this->scripts ) );
			$current_handles = array();
			foreach ( $this->scripts as $s ) {
				$current_handles[] = $s['handle'] ?? 'N/A';
			}
			$this->get_logger()->debug( 'EnqueueAbstract::add_scripts - All current script handles after add: ' . implode( ', ', $current_handles ) );
		}

		return $this;
	}

	/**
	 * Registers scripts with WordPress without enqueueing them.
	 *
	 * @return self Returns the instance for method chaining.
	 */
	public function register_scripts( array $scripts = array() ): self {
		// If scripts are directly passed, add them to the internal array and log a notice
		if ( ! empty( $scripts ) ) {
			$this->get_logger()->debug( 'EnqueueAbstract::register_scripts - Scripts directly passed. Consider using add_scripts() first for better maintainability.' );
			$this->add_scripts( $scripts );
		}

		// Always use the internal scripts array for consistency
		$scripts = $this->scripts;

		if ( $this->get_logger()->is_active() ) {
			$this->get_logger()->debug( 'EnqueueAbstract::register_scripts - Registering ' . count( $scripts ) . ' script(s).' );
		}

		foreach ( $scripts as $script ) {
			// Skip scripts with hooks (they'll be registered when the hook fires)
			if ( !empty( $script['hook'] ) ) {
				continue;
			}

			// process_single_script handles all registration logic including conditions,
			// wp_data, and attributes
			$this->process_single_script( $script );
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
	 * is processed immediately by `process_single_script()`.
	 *
	 * The `process_single_script()` method handles the actual WordPress registration
	 * (`wp_register_script()`), enqueuing (`wp_enqueue_script()`), attribute additions,
	 * and `wp_script_add_data()` calls.
	 *
	 * @return self Returns the instance of this class for method chaining.
	 * @see    self::add_scripts()
	 * @see    self::process_single_script()
	 * @see    self::enqueue_deferred_scripts()
	 * @see    wp_register_script()
	 * @see    wp_enqueue_script()
	 * @see    wp_script_add_data()
	 */
	public function enqueue_scripts( array $scripts = array() ): self {
		// If scripts are directly passed, add them to the internal array and log a notice
		if ( ! empty( $scripts ) ) {
			$this->get_logger()->debug( 'EnqueueAbstract::enqueue_scripts - Scripts directly passed. Consider using add_scripts() first for better maintainability.' );
			$this->add_scripts( $scripts );
		}

		// Always use the internal scripts array for consistency
		$scripts = $this->scripts;

		$this->get_logger()->debug( 'EnqueueAbstract::enqueue_scripts - Entered. Processing ' . count( $scripts ) . ' script definition(s).' );
		// Track which hooks have new scripts added.
		$hooks_with_new_scripts = array();

		foreach ( $scripts as $script ) {
			$hook = $script['hook'] ?? null;

			// If a hook is specified, store the script for later enqueuing.
			if ( ! empty( $hook ) ) {
				if ( $this->get_logger()->is_active() ) {
					$this->get_logger()->debug( "EnqueueAbstract::enqueue_scripts - Script '" . ( $script['handle'] ?? 'N/A' ) . "' identified as deferred to hook: " . $hook );
				}

				if ( ! isset( $this->deferred_scripts[ $hook ] ) ) {
					$this->deferred_scripts[ $hook ] = array();
				}
				$this->deferred_scripts[ $hook ][] = $script;
				$hooks_with_new_scripts[ $hook ]   = true;
				continue;
			}

			// Process the script (register and set up attributes).
			if ( $this->get_logger()->is_active() ) {
				$this->get_logger()->debug( 'EnqueueAbstract::enqueue_scripts - Processing non-deferred script: ' . ( $script['handle'] ?? 'N/A' ) );
			}


			$handle = $this->process_single_script( $script );

			// Skip empty handles (condition not met).
			if ( empty( $handle ) ) {
				$this->get_logger()->debug( 'EnqueueAbstract::enqueue_scripts - Skipping script as handle is empty (condition likely not met for: ' . ($script['handle'] ?? 'N/A') . ').' );
				continue;
			}

			$this->get_logger()->debug( 'EnqueueAbstract::enqueue_scripts - Enqueuing script (handle from process_single_script): ' . $handle );

			// Enqueue the script.
			if ( $this->get_logger()->is_active() ) {
				$this->get_logger()->debug( 'EnqueueAbstract::enqueue_scripts - Calling wp_enqueue_script for non-deferred: ' . $handle );
			}
			wp_enqueue_script( $handle );
		}

		// Register hooks for any deferred scripts that were added.
		$this->get_logger()->debug( 'EnqueueAbstract::enqueue_scripts - hooks_with_new_scripts: ' . wp_json_encode( $hooks_with_new_scripts ) );
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

		$this->get_logger()->debug( 'EnqueueAbstract::enqueue_scripts - Exited.' );
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
		if ( $this->get_logger()->is_active() ) {
			$this->get_logger()->debug( 'EnqueueAbstract::add_inline_scripts - Entered. Current inline script count: ' . count( $this->inline_scripts ) . '. Adding ' . count( $inline_scripts_to_add ) . ' new inline script(s).' );
		}

		$processed_inline_scripts = array();
		foreach ( $inline_scripts_to_add as $inline_script_data ) {
			if ( $this->get_logger()->is_active() ) {
				$log_handle         = $inline_script_data['handle']   ?? 'N/A';
				$log_position       = $inline_script_data['position'] ?? 'after';
				$log_content_length = strlen($inline_script_data['content'] ?? '');
				$this->get_logger()->debug( "EnqueueAbstract::add_inline_scripts - Processing inline script for handle '{$log_handle}', position '{$log_position}', content length {$log_content_length}." );
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
						if ( $this->get_logger()->is_active() ) {
							$this->get_logger()->debug( "EnqueueAbstract::add_inline_scripts - Inline script for '{$parent_handle}' associated with parent hook: '{$inline_script_data['parent_hook']}'. Original parent script hook: '" . ( $original_script_definition['hook'] ?? 'N/A' ) . "'." );
						}
						break; // Found the parent script, no need to check further.
					}
				}
			}
			$processed_inline_scripts[] = $inline_script_data;
		}
		// Merge new inline scripts with existing ones.
		$this->inline_scripts = array_merge( $this->inline_scripts, $processed_inline_scripts );

		if ( $this->get_logger()->is_active() ) {
			$this->get_logger()->debug( 'EnqueueAbstract::add_inline_scripts - Exiting. New total inline script count: ' . count( $this->inline_scripts ) );
		}
		return $this;
	}

	/**
	 * Process and add all registered inline scripts.
	 */
	public function enqueue_inline_scripts(): self {
		if ( $this->get_logger()->is_active() ) {
			$this->get_logger()->debug( 'EnqueueAbstract::enqueue_inline_scripts - Entered method.' );
		}
		foreach ( $this->inline_scripts as $inline_script ) {
			$handle      = $inline_script['handle']      ?? '';
			$content     = $inline_script['content']     ?? '';
			$position    = $inline_script['position']    ?? 'after';
			$condition   = $inline_script['condition']   ?? null;
			$parent_hook = $inline_script['parent_hook'] ?? null;

			if ( $this->get_logger()->is_active() ) {
				$this->get_logger()->debug( "EnqueueAbstract::enqueue_inline_scripts - Processing inline script for handle: '" . esc_html( $handle ) . "'. Parent hook: '" . esc_html( $parent_hook ?: 'None' ) . "'." );
			}

			// If this inline script is tied to a parent script on a specific hook, skip it here.
			// It will be handled by enqueue_deferred_scripts.
			if ( ! empty( $parent_hook ) ) {
				if ( $this->get_logger()->is_active() ) {
					$this->get_logger()->debug( "EnqueueAbstract::enqueue_inline_scripts - Deferring inline script for '{$handle}' because its parent is on hook '{$parent_hook}'." );
				}
				continue;
			}

			// Skip if required parameters are missing.
			if ( empty( $handle ) || empty( $content ) ) {
				if ( $this->get_logger()->is_active() ) {
					$this->get_logger()->error( 'EnqueueAbstract::enqueue_inline_scripts - Skipping (non-deferred) inline script due to missing handle or content. Handle: ' . esc_html( $handle ) );
				}
				continue;
			}

			// Check if the condition is met.
			if ( is_callable( $condition ) && ! $condition() ) {
				continue;
			}

			// Check if the parent script is registered or enqueued.
			$is_registered = wp_script_is( $handle, 'registered' );
			$is_enqueued   = wp_script_is( $handle, 'enqueued' );
			if ( $this->get_logger()->is_active() ) {
				$this->get_logger()->debug( "EnqueueAbstract::enqueue_inline_scripts - (Non-deferred) Script '" . esc_html( $handle ) . "' is_registered: " . ( $is_registered ? 'TRUE' : 'FALSE' ) );
				$this->get_logger()->debug( "EnqueueAbstract::enqueue_inline_scripts - (Non-deferred) Script '" . esc_html( $handle ) . "' is_enqueued: " . ( $is_enqueued ? 'TRUE' : 'FALSE' ) );
			}
			if ( ! $is_registered && ! $is_enqueued ) {
				if ( $this->get_logger()->is_active() ) {
					$this->get_logger()->error( "EnqueueAbstract::enqueue_inline_scripts - (Non-deferred) Cannot add inline script. Parent script '" . esc_html( $handle ) . "' is not registered or enqueued." );
				}
				continue;
			}

			// Add the inline script using WordPress functions.
			if ( $this->get_logger()->is_active() ) {
				$this->get_logger()->debug( "EnqueueAbstract::enqueue_inline_scripts - (Non-deferred) Attempting to add inline script for '" . esc_html( $handle ) . "' with position '" . esc_html( $position ) . "'." );
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
		if ( $this->get_logger()->is_active() ) {
			$this->get_logger()->debug( "EnqueueAbstract::enqueue_deferred_scripts - Entered for hook: \"{$hook}\"" );
		}
		if ( ! isset( $this->deferred_scripts[ $hook ] ) ) {
			if ( $this->get_logger()->is_active() ) {
				$this->get_logger()->debug( "EnqueueAbstract::enqueue_deferred_scripts - Hook \"{$hook}\" not found in deferred scripts. Nothing to process." );
			}
			return; // Hook was never set, nothing to do or unset.
		}

		// Retrieve scripts for the hook and then immediately unset it to mark as processed.
		$scripts_on_this_hook = $this->deferred_scripts[ $hook ];
		unset( $this->deferred_scripts[ $hook ] ); // Moved unset action here

		if ( empty( $scripts_on_this_hook ) ) {
			if ( $this->get_logger()->is_active() ) {
				$this->get_logger()->debug( "EnqueueAbstract::enqueue_deferred_scripts - Hook \"{$hook}\" was set but had no scripts. It has now been cleared." );
			}
			return; // No actual scripts to process for this hook.
		}

		foreach ( $scripts_on_this_hook as $script_definition ) {
			$original_handle = $script_definition['handle'] ?? null;

			if ( empty( $original_handle ) ) {
				if ( $this->get_logger()->is_active() ) {
					$this->get_logger()->error( "EnqueueAbstract::enqueue_deferred_scripts - Script definition missing 'handle' for hook '{$hook}'. Skipping this script definition." );
				}
				continue; // Skip this script definition if handle is missing
			}

			// Perform wp_script_is checks and log them.
			$is_registered = false;
			$is_enqueued   = false;
			if ( $this->get_logger()->is_active() ) {
				$this->get_logger()->debug( "EnqueueAbstract::enqueue_deferred_scripts - [DIAG_LOG] Checking status for handle: '{$original_handle}' on hook '{$hook}'." );
				$is_registered = wp_script_is( $original_handle, 'registered' );
				$is_enqueued   = wp_script_is( $original_handle, 'enqueued' );
				$this->get_logger()->debug( "EnqueueAbstract::enqueue_deferred_scripts - [DIAG_LOG] For '{$original_handle}': wp_script_is('registered') returned: " . ( $is_registered ? 'true' : 'false' ) );
				$this->get_logger()->debug( "EnqueueAbstract::enqueue_deferred_scripts - [DIAG_LOG] For '{$original_handle}': wp_script_is('enqueued') returned: " . ( $is_enqueued ? 'true' : 'false' ) );
			} else {
				// If logger is not active, still perform the checks for logic execution.
				$is_registered = wp_script_is( $original_handle, 'registered' );
				$is_enqueued   = wp_script_is( $original_handle, 'enqueued' );
			}

			$skip_main_registration_and_enqueue = $is_enqueued;

			if ( $skip_main_registration_and_enqueue ) {
				if ( $this->get_logger()->is_active() ) {
					$this->get_logger()->debug( "EnqueueAbstract::enqueue_deferred_scripts - Script '{$original_handle}' is already enqueued. Skipping its registration and main enqueue call on hook '{$hook}'. Inline scripts will still be processed." );
				}
			} else {
				// Not enqueued, so process and enqueue main script.
				if ( $this->get_logger()->is_active() ) {
					$this->get_logger()->debug( "EnqueueAbstract::enqueue_deferred_scripts - Processing deferred script: \"{$original_handle}\" for hook: \"{$hook}\"" );
				}
				// process_single_script returns the original handle, so $original_handle is the one to use.
				$processed_handle_confirmation = $this->process_single_script( $script_definition );

				if ( empty( $processed_handle_confirmation ) || $processed_handle_confirmation !== $original_handle ) {
					if ( $this->get_logger()->is_active() ) {
						$this->get_logger()->warning( "EnqueueAbstract::enqueue_deferred_scripts - process_single_script returned an unexpected handle ('{$processed_handle_confirmation}') or empty for original handle '{$original_handle}' on hook \"{$hook}\". Skipping main script enqueue and its inline scripts." );
					}
					continue; // Critical issue, skip this script and its inlines.
				}

				if ( $this->get_logger()->is_active() ) {
					$this->get_logger()->debug( "EnqueueAbstract::enqueue_deferred_scripts - Calling wp_enqueue_script for deferred: \"{$original_handle}\" on hook: \"{$hook}\"" );
				}
				wp_enqueue_script( $original_handle ); // Enqueue the main deferred script using its original handle.
			}

			// NOW, process any inline scripts associated with this $original_handle AND this $hook.
			// This block is now reached even if $skip_main_registration_and_enqueue was true.
			if ( $this->get_logger()->is_active() ) {
				$this->get_logger()->debug( "EnqueueAbstract::enqueue_deferred_scripts - Checking for inline scripts for handle '{$original_handle}' on hook '{$hook}'." );
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
						if ( $this->get_logger()->is_active() ) {
							$this->get_logger()->error( "EnqueueAbstract::enqueue_deferred_scripts - Skipping inline script for deferred '{$original_handle}' due to missing content." );
						}
						continue;
					}

					if ( is_callable( $condition ) && ! $condition() ) {
						if ( $this->get_logger()->is_active() ) {
							$this->get_logger()->debug( "EnqueueAbstract::enqueue_deferred_scripts - Condition false for inline script for deferred '{$original_handle}'." );
						}
						continue;
					}

					if ( $this->get_logger()->is_active() ) {
						$this->get_logger()->debug( "EnqueueAbstract::enqueue_deferred_scripts - Adding inline script for deferred '{$original_handle}' (position: {$position}) on hook '{$hook}'." );
					}
					// Always use $original_handle for wp_add_inline_script as it's the handle WordPress knows or will know.
					wp_add_inline_script( $original_handle, $content, $position );

					// Remove the inline script from $this->inline_scripts once processed.
					unset($this->inline_scripts[$inline_script_key]);
				}
			}
		}
		// The hook is unset earlier in the method after its scripts are retrieved.
		$this->get_logger()->debug( "EnqueueAbstract::enqueue_deferred_scripts - Exited for hook: \"{$hook}\"" );
	}

	/**
	 * Process a single script - registers it and sets up attributes and data.
	 *
	 * @param array<string, mixed> $script The script configuration array.
	 * @return string The script handle that was registered, or empty string if conditions not met.
	 */
	protected function process_single_script( array $script ): ?string { // Return type changed to ?string
		$handle     = $script['handle']     ?? '';
		$src        = $script['src']        ?? '';
		$deps       = $script['deps']       ?? array();
		$ver        = $script['version']    ?? false;
		$in_footer  = $script['in_footer']  ?? false;
		$condition  = $script['condition']  ?? null;
		$attributes = $script['attributes'] ?? array();
		$wp_data    = $script['wp_data']    ?? array();

		// Check if the condition is met.
		if ( is_callable( $condition ) && ! $condition() ) {
			return '';
		}

		// Register the script.
		wp_register_script( $handle, $src, $deps, $ver, $in_footer );

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
					// Only modify our specific script.
					if ( $tag_handle !== $handle ) {
						return $tag;
					}

					// Find the position to insert attributes.
					$pos = strpos( $tag, '>' );
					if ( false === $pos ) {
						return $tag; // Malformed tag, return as is.
					}

					// Special handling for module scripts.
					if ( isset( $attributes['type'] ) && 'module' === $attributes['type'] ) {
						// Position type="module" right after <script.
						$tag = preg_replace( '/<script\s/', '<script type="module" ', $tag );

						// Remove type from attributes so it's not added again..
						unset( $attributes['type'] );
					}

					// Build attributes string.
					$attr_str = '';
					foreach ( $attributes as $attr => $value ) {
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
				},
				10,
				3
			);
		}

		return $handle;
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
		if ( $this->get_logger()->is_active() ) {
			$this->get_logger()->debug( 'EnqueueAbstract: Adding ' . count( $styles ) . ' styles to the queue.' );
		}
		$this->styles = $styles;

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
		$this->get_logger()->debug( 'EnqueueAbstract::enqueue_styles - Entered. Processing ' . count( $styles ) . ' style definition(s).' );

		foreach ( $styles as $index => $style_definition ) { // Use $index for unique storage in deferred array.
			$handle_for_log = $style_definition['handle'] ?? 'N/A';
			$this->get_logger()->debug( "EnqueueAbstract::enqueue_styles - Processing style: \"{$handle_for_log}\", original index: {$index}." );

			$hook = $style_definition['hook'] ?? null;

			if ( ! empty( $hook ) ) {
				// Defer this style.
				$this->get_logger()->debug( "EnqueueAbstract::enqueue_styles - Deferring style \"{$handle_for_log}\" (original index {$index}) to hook: \"{$hook}\"." );
				$this->deferred_styles[ $hook ][ $index ] = $style_definition; // Preserve original index.

				if ( ! has_action( $hook, array( $this, 'enqueue_deferred_styles' ) ) ) {
					add_action( $hook, array( $this, 'enqueue_deferred_styles' ), 10, 1 ); // Pass hook name.
					$this->get_logger()->debug( "EnqueueAbstract::enqueue_styles - Added action for 'enqueue_deferred_styles' on hook: \"{$hook}\"." );
				}
			} else {
				// Process immediately.
				$this->get_logger()->debug( "EnqueueAbstract::enqueue_styles - Processing style \"{$handle_for_log}\" immediately." );
				$handle    = $style_definition['handle']    ?? '';
				$src       = $style_definition['src']       ?? '';
				$deps      = $style_definition['deps']      ?? array();
				$ver       = $style_definition['version']   ?? false;
				$media     = $style_definition['media']     ?? 'all';
				$condition = $style_definition['condition'] ?? null;

				if ( empty( $handle ) || empty( $src ) ) {
					$this->get_logger()->warning( "EnqueueAbstract::enqueue_styles - Invalid immediate style definition. Missing handle or src. Skipping. Handle: \"{$handle_for_log}\"." );
					continue;
				}

				if ( is_callable( $condition ) && ! $condition() ) {
					$this->get_logger()->debug( "EnqueueAbstract::enqueue_styles - Condition not met for immediate style \"{$handle}\". Skipping." );
					continue;
				}
				$this->get_logger()->debug( "EnqueueAbstract::enqueue_styles - Registering immediate style: \"{$handle}\"." );
				wp_register_style( $handle, $src, $deps, $ver, $media );
				$this->get_logger()->debug( "EnqueueAbstract::enqueue_styles - Enqueuing immediate style: \"{$handle}\"." );
				wp_enqueue_style( $handle );
			}
		}
		$this->get_logger()->debug( 'EnqueueAbstract::enqueue_styles - Exited.' );
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
		$this->get_logger()->debug( "EnqueueAbstract::enqueue_deferred_styles - Entered for hook: \"{$hook_name}\"." );

		if ( ! isset( $this->deferred_styles[ $hook_name ] ) || empty( $this->deferred_styles[ $hook_name ] ) ) {
			$this->get_logger()->debug( "EnqueueAbstract::enqueue_deferred_styles - No deferred styles found or already processed for hook: \"{$hook_name}\"." );
			return;
		}

		$styles_on_this_hook = $this->deferred_styles[ $hook_name ];

		foreach ( $styles_on_this_hook as $style_definition ) {
			$handle_for_log = $style_definition['handle'] ?? 'N/A';
			$this->get_logger()->debug( "EnqueueAbstract::enqueue_deferred_styles - Processing deferred style: \"{$handle_for_log}\" for hook: \"{$hook_name}\"." );

			$handle    = $style_definition['handle']    ?? '';
			$src       = $style_definition['src']       ?? '';
			$deps      = $style_definition['deps']      ?? array();
			$ver       = $style_definition['version']   ?? false; // Or plugin version
			$media     = $style_definition['media']     ?? 'all';
			$condition = $style_definition['condition'] ?? null;

			if ( empty( $handle ) || empty( $src ) ) {
				$this->get_logger()->warning( "EnqueueAbstract::enqueue_deferred_styles - Invalid style definition for hook \"{$hook_name}\". Missing handle or src. Skipping. Handle: \"{$handle_for_log}\"." );
				continue;
			}

			if ( is_callable( $condition ) && ! $condition() ) {
				$this->get_logger()->debug( "EnqueueAbstract::enqueue_deferred_styles - Condition not met for deferred style \"{$handle}\" on hook \"{$hook_name}\". Skipping." );
				continue;
			}

			$this->get_logger()->debug( "EnqueueAbstract::enqueue_deferred_styles - Registering deferred style: \"{$handle}\" for hook: \"{$hook_name}\"." );
			wp_register_style( $handle, $src, $deps, $ver, $media );

			$this->get_logger()->debug( "EnqueueAbstract::enqueue_deferred_styles - Enqueuing deferred style: \"{$handle}\" for hook: \"{$hook_name}\"." );
			wp_enqueue_style( $handle );

			// Process inline styles associated with this handle AND this hook.
			$this->get_logger()->debug( "EnqueueAbstract::enqueue_deferred_styles - Checking for inline styles for handle '{$handle}' on hook '{$hook_name}'." );
			foreach ( $this->inline_styles as $inline_style_key => $inline_style_data ) {
				$inline_handle      = $inline_style_data['handle']      ?? null;
				$inline_parent_hook = $inline_style_data['parent_hook'] ?? null;

				if ( $inline_handle === $handle && $inline_parent_hook === $hook_name ) {
					$content          = $inline_style_data['content']   ?? '';
					$condition_inline = $inline_style_data['condition'] ?? null;

					if ( empty( $content ) ) {
						$this->get_logger()->error( "EnqueueAbstract::enqueue_deferred_styles - Skipping inline style for deferred '{$handle}' due to missing content." );
						continue;
					}

					if ( is_callable( $condition_inline ) && ! $condition_inline() ) {
						$this->get_logger()->debug( "EnqueueAbstract::enqueue_deferred_styles - Condition false for inline style for deferred '{$handle}'." );
						continue;
					}
					$this->get_logger()->debug( "EnqueueAbstract::enqueue_deferred_styles - Adding inline style for deferred '{$handle}' on hook '{$hook_name}'." );
					wp_add_inline_style( $handle, $content );
					// Remove the inline style from $this->inline_styles once processed.
					unset( $this->inline_styles[ $inline_style_key ] );
				}
			}
		}
		// Clear the processed deferred styles for this hook to prevent re-processing.
		unset( $this->deferred_styles[ $hook_name ] );
		$this->get_logger()->debug( "EnqueueAbstract::enqueue_deferred_styles - Exited for hook: \"{$hook_name}\"." );
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
		if ( $this->get_logger()->is_active() ) {
			$this->get_logger()->debug( 'EnqueueAbstract::add_inline_styles - Entered. Current inline style count: ' . count( $this->inline_styles ) . '. Adding new inline style for handle: ' . esc_html( $handle ) );
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
				if ( $this->get_logger()->is_active() ) {
					$this->get_logger()->debug( "EnqueueAbstract::add_inline_styles - Inline style for '{$handle}' associated with parent hook: '{$inline_style_item['parent_hook']}'. Original parent style hook: '" . ( $original_style_definition['hook'] ?? 'N/A' ) . "'." );
				}
				break; // Found the parent style, no need to check further.
			}
		}

		$this->inline_styles[] = $inline_style_item;

		if ( $this->get_logger()->is_active() ) {
			$this->get_logger()->debug( 'EnqueueAbstract::add_inline_styles - Exiting. New total inline style count: ' . count( $this->inline_styles ) );
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
		if ( $this->get_logger()->is_active() ) {
			$this->get_logger()->debug( 'EnqueueAbstract::enqueue_inline_styles - Entered method.' );
		}
		foreach ( $this->inline_styles as $inline_style ) {
			$handle      = $inline_style['handle']      ?? '';
			$content     = $inline_style['content']     ?? '';
			$condition   = $inline_style['condition']   ?? null;
			$parent_hook = $inline_style['parent_hook'] ?? null;

			if ( $this->get_logger()->is_active() ) {
				$this->get_logger()->debug( "EnqueueAbstract::enqueue_inline_styles - Processing inline style for handle: '" . esc_html( $handle ) . "'. Parent hook: '" . esc_html( $parent_hook ?: 'None' ) . "'." );
			}

			// If this inline style is tied to a parent style on a specific hook, skip it here.
			// It will be handled by a corresponding deferred styles mechanism if implemented.
			if ( ! empty( $parent_hook ) ) {
				if ( $this->get_logger()->is_active() ) {
					$this->get_logger()->debug( "EnqueueAbstract::enqueue_inline_styles - Deferring inline style for '{$handle}' because its parent is on hook '{$parent_hook}'." );
				}
				continue;
			}

			// Skip if required parameters are missing.
			if ( empty( $handle ) || empty( $content ) ) {
				if ( $this->get_logger()->is_active() ) {
					$this->get_logger()->error( 'EnqueueAbstract::enqueue_inline_styles - Skipping (non-deferred) inline style due to missing handle or content. Handle: ' . esc_html( $handle ) );
				}
				continue;
			}

			// Check if the condition is met.
			if ( is_callable( $condition ) && ! $condition() ) {
				if ( $this->get_logger()->is_active() ) {
					$this->get_logger()->debug( "EnqueueAbstract::enqueue_inline_styles - Condition not met for inline style '{$handle}'. Skipping." );
				}
				continue;
			}

			// Check if the parent style is registered or enqueued.
			$is_registered = wp_style_is( $handle, 'registered' );
			$is_enqueued   = wp_style_is( $handle, 'enqueued' );
			if ( $this->get_logger()->is_active() ) {
				$this->get_logger()->debug( "EnqueueAbstract::enqueue_inline_styles - (Non-deferred) Style '" . esc_html( $handle ) . "' is_registered: " . ( $is_registered ? 'TRUE' : 'FALSE' ) );
				$this->get_logger()->debug( "EnqueueAbstract::enqueue_inline_styles - (Non-deferred) Style '" . esc_html( $handle ) . "' is_enqueued: " . ( $is_enqueued ? 'TRUE' : 'FALSE' ) );
			}
			if ( ! $is_registered && ! $is_enqueued ) {
				if ( $this->get_logger()->is_active() ) {
					$this->get_logger()->error( "EnqueueAbstract::enqueue_inline_styles - (Non-deferred) Cannot add inline style. Parent style '" . esc_html( $handle ) . "' is not registered or enqueued." );
				}
				continue;
			}

			// Add the inline style using WordPress functions.
			if ( $this->get_logger()->is_active() ) {
				$this->get_logger()->debug( "EnqueueAbstract::enqueue_inline_styles - (Non-deferred) Attempting to add inline style for '" . esc_html( $handle ) . "'." );
			}
			wp_add_inline_style( $handle, $content );
		}
		return $this;
	}

	// MEDIA HANDLING

	/**
	 * Array of media elements to enqueue.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public array $media = array();

	/**
	 * Array of media items to be enqueued at specific hooks.
	 *
	 * @var array<string, array<int, array<string, mixed>>>
	 */
	private array $deferred_media = array();

	/**
	 * Get the array of registered media.
	 *
	 * @return array<string, array<int, mixed>> The registered media.
	 */
	public function get_media() {
		$media = array(
			'general'  => $this->media,
			'deferred' => $this->deferred_media,
		);
		return $media;
	}

	/**
	 * Adds a collection of media enqueue definitions to an internal queue for later processing.
	 *
	 * This method is chain-able. It stores the provided media definitions, **overwriting**
	 * any media items previously added via this method. The actual enqueuing
	 * occurs when the `enqueue()` or `enqueue_media()` method is subsequently called.
	 *
	 * `wp_enqueue_media()` is typically used to load the JavaScript and CSS required for
	 * the WordPress media uploader and media library interface.
	 *
	 * @param  array<int, array<string, mixed>> $media The array of media items to enqueue. Each item should be an array with the following keys:
	 *     @type array       $args       (Optional) An array of arguments to pass to `wp_enqueue_media()`. For example, `['post' => 123]` to associate the media uploader with a specific post. Defaults to an empty array.
	 *     @type callable|null $condition  (Optional) A callback returning a boolean. If `false`, `wp_enqueue_media()` is not called for this item. Defaults to `null` (no condition).
	 *     @type string|null $hook       (Optional) WordPress hook (e.g., 'admin_enqueue_scripts') to defer enqueuing until. Defaults to `null` (no deferral).
	 * @return self Returns the instance of this class for method chaining.
	 * @see    self::enqueue_media()
	 * @see    self::enqueue()
	 * @see    wp_enqueue_media()
	 */
	public function add_media( array $media ): self {
		$this->get_logger()->debug( 'EnqueueAbstract: Adding ' . count( $media ) . ' media items to the queue.' );
		$this->media = $media;

		return $this;
	}

	/**
	 * Processes and enqueues a given array of media definitions.
	 *
	 * This method iterates through an array of media definitions. For each item,
	 * it checks for a 'hook' parameter.
	 * - If a 'hook' is specified, the media item is deferred: it's stored internally
	 *   and an action is registered (if not already) for the specified hook.
	 *   When the hook fires, `enqueue_deferred_media()` will process the item.
	 * - If no 'hook' is specified, the item is processed immediately: its 'condition'
	 *   callback (if any) is checked, and if it passes, `wp_enqueue_media()` is called.
	 *
	 * @param  array<int, array<string, mixed>> $media The array of media definitions to process.
	 *                                                Each item should follow the structure documented in `add_media()`.
	 *     @type array       $args       (Optional) An array of arguments to pass to `wp_enqueue_media()`. Defaults to an empty array.
	 *     @type callable|null $condition  (Optional) A callback returning a boolean. If `false`, `wp_enqueue_media()` is not called for this item (applies to both immediate and deferred items). Defaults to `null`.
	 *     @type string|null $hook       (Optional) A WordPress action hook name to defer the enqueuing to. If provided, the item is not enqueued immediately. Defaults to `null`.
	 * @return self Returns the instance of this class for method chaining.
	 * @see    self::add_media()
	 * @see    self::enqueue_deferred_media()
	 * @see    wp_enqueue_media()
	 */
	public function enqueue_media( array $media ): self {
		$this->get_logger()->debug( 'EnqueueAbstract::enqueue_media - Entered. Processing ' . count( $media ) . ' media item definition(s).' );

		foreach ( $media as $index => $item_definition ) {
			$this->get_logger()->debug( "EnqueueAbstract::enqueue_media - Processing media item at original index: {$index}." );

			$hook = $item_definition['hook'] ?? null;

			if ( ! empty( $hook ) ) {
				// Defer this media item.
				$this->get_logger()->debug( "EnqueueAbstract::enqueue_media - Deferring media item at original index {$index} to hook: \"{$hook}\"." );
				$this->deferred_media[ $hook ][ $index ] = $item_definition; // Preserve original index for logging in deferred method.

				// Ensure the action for this hook is added only once.
				// Note: `has_action` checks if a specific function is hooked, not just any function.
				// We need a more robust way if multiple EnqueueAbstract instances could exist and use the same hook.
				// For now, assuming one primary instance or that multiple calls to add_action for the same method are okay.
				if ( ! has_action( $hook, array( $this, 'enqueue_deferred_media' ) ) ) {
					add_action( $hook, array( $this, 'enqueue_deferred_media' ), 10, 1 ); // Pass hook name to method.
					$this->get_logger()->debug( "EnqueueAbstract::enqueue_media - Added action for 'enqueue_deferred_media' on hook: \"{$hook}\"." );
				}
			} else {
				// Process immediately.
				$this->get_logger()->debug( "EnqueueAbstract::enqueue_media - Processing media item at original index {$index} immediately." );
				$args      = $item_definition['args']      ?? array();
				$condition = $item_definition['condition'] ?? null;

				// Check if the condition is met.
				if ( is_callable( $condition ) && ! $condition() ) {
					$this->get_logger()->debug( "EnqueueAbstract::enqueue_media - Condition not met for immediate media item at original index {$index}. Skipping." );
					continue;
				}

				$this->get_logger()->debug( "EnqueueAbstract::enqueue_media - Calling wp_enqueue_media() for immediate item at original index {$index}. Args: " . wp_json_encode( $args ) );
				wp_enqueue_media( $args );
			}
		}
		$this->get_logger()->debug( 'EnqueueAbstract::enqueue_media - Exited.' );
		return $this;
	}

	/**
	 * Enqueues media items that were deferred to a specific WordPress hook.
	 *
	 * This method is typically called by an action hook that was registered
	 * when a media item with a 'hook' parameter was processed by `enqueue_media()`.
	 * It iterates through the media items stored in `$this->deferred_media` for the
	 * given hook, checks their conditions, and calls `wp_enqueue_media()` if the
	 * condition passes.
	 *
	 * @param string $hook_name The WordPress hook name that triggered this method.
	 * @return void This method returns `void` because it's primarily designed as a callback for WordPress action hooks.
	 *              When executed within a hook, its main role is to perform enqueueing operations (side effects),
	 *              rather than being part of a fluent setup chain.
	 * @see self::enqueue_media()
	 * @see wp_enqueue_media()
	 */
	public function enqueue_deferred_media( string $hook_name ): void {
		$this->get_logger()->debug( "EnqueueAbstract::enqueue_deferred_media - Entered for hook: \"{$hook_name}\"." );

		if ( ! isset( $this->deferred_media[ $hook_name ] ) || empty( $this->deferred_media[ $hook_name ] ) ) {
			$this->get_logger()->debug( "EnqueueAbstract::enqueue_deferred_media - No deferred media found or already processed for hook: \"{$hook_name}\"." );
			return;
		}

		$media_on_this_hook = $this->deferred_media[ $hook_name ];

		foreach ( $media_on_this_hook as $index => $item_definition ) {
			$this->get_logger()->debug( "EnqueueAbstract::enqueue_deferred_media - Processing deferred media item at original index {$index} for hook: \"{$hook_name}\"." );

			$args      = $item_definition['args']      ?? array();
			$condition = $item_definition['condition'] ?? null;

			// Check if the condition is met.
			if ( is_callable( $condition ) && ! $condition() ) {
				$this->get_logger()->debug( "EnqueueAbstract::enqueue_deferred_media - Condition not met for deferred media item at original index {$index} on hook \"{$hook_name}\". Skipping." );
				continue;
			}

			$this->get_logger()->debug( "EnqueueAbstract::enqueue_deferred_media - Calling wp_enqueue_media() for deferred item at original index {$index} on hook \"{$hook_name}\". Args: " . wp_json_encode( $args ) );
			wp_enqueue_media( $args );
		}

		// Clear the processed deferred media for this hook to prevent re-processing.
		unset( $this->deferred_media[ $hook_name ] );

		$this->get_logger()->debug( "EnqueueAbstract::enqueue_deferred_media - Exited for hook: \"{$hook_name}\"." );
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
