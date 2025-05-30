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
 * This class is meant to be extended and be instantiated via the RegisterServices Class.
 *
 * @package  RanPluginLib
 */
abstract class EnqueueAbstract implements EnqueueInterface {
	/**
	 * The ConfigInterface object holding plugin configuration.
	 *
	 * @var \Ran\PluginLib\Config\ConfigInterface $config // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.UselessAnnotation
	 */
	protected \Ran\PluginLib\Config\ConfigInterface $config;

	/**
	 * Constructor.
	 *
	 * @param \Ran\PluginLib\Config\ConfigInterface $config The configuration object.
	 */
	public function __construct( \Ran\PluginLib\Config\ConfigInterface $config ) {
		$this->config = $config;
	}

	/**
	 * Array of styles to enqueue.
	 *
	 * @var array<int, array<int, mixed>>
	 */
	public array $styles = array();

	/**
	 * Array of urls to enqueue.
	 *
	 * @var array<int, array<int, mixed>>
	 */
	public array $scripts = array();

	/**
	 * Array of media elements to enqueue.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public array $media = array();

	/**
	 * Array of callbacks to execute in the head section.
	 *
	 * @var array<int, callable|array<string, mixed>>
	 */
	protected array $head_callbacks = array();

	/**
	 * Array of callbacks to execute in the footer section.
	 *
	 * @var array<int, callable|array<string, mixed>>
	 */
	protected array $footer_callbacks = array();

	/**
	 * Array of scripts to be enqueued at specific hooks.
	 *
	 * @var array<string, array<int, array<string, mixed>>>
	 */
	protected array $deferred_scripts = array();

	/**
	 * Array of inline scripts to be added.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	protected array $inline_scripts = array();

	/**
	 * Retrieves the logger instance for the plugin.
	 *
	 * @return Logger The logger instance.
	 */
	public function get_logger(): Logger {
		return $this->config->get_logger();
	}

	/**
	 * A class registration function to add admin_enqueue_scripts/wp_enqueue_scripts hooks to WP.
	 * The hook callback function is $this->enqueue()
	 *
	 * It runs: add_action('admin_enqueue_scripts', array($this, 'enqueue'));
	 */
	abstract public function load(): void;

	/**
	 * Chain-able call to add styles to be loaded.
	 *
	 * @param  array<int, array<string, mixed>> $styles - The array of styles to enqueue. Each style should be an array with the following keys.
	 *     @type string      $handle     Required. Name of the stylesheet. Should be unique..
	 *     @type string      $src        URL to the stylesheet resource..
	 *     @type array       $deps       Optional. An array of registered stylesheet handles this stylesheet depends on..
	 *     @type string|false|null $version    Optional. String specifying stylesheet version number. False will default to the plugin version, where 'null' will disable versioning..
	 *     @type string      $media      Optional. The media for which this stylesheet has been defined (e.g. 'all', 'screen', 'print')..
	 *     @type callable    $condition  Optional. Callback function that determines if the stylesheet should be enqueued..
	 *     @type string      $hook       Optional. WordPress hook to use for deferred enqueuing..
	 */
	public function add_styles( array $styles ): self {
		$this->styles = $styles;

		return $this;
	}

	/**
	 * Chain-able call to add scripts to be loaded.
	 *
	 * @param  array<int, array<string, mixed>> $scripts_to_add The array of scripts to enqueue. Each script should be an array with the following keys.
	 *     @type string      $handle     Required. Name of the script. Should be unique.
	 *     @type string      $src        URL to the script resource.
	 *     @type array       $deps       Optional. An array of registered script handles this script depends on.
	 *     @type string|false|null $version    Optional. String specifying script version number. False will default to the plugin version, where 'null' will disable versioning.
	 *     @type bool        $in_footer  Optional. Whether to enqueue the script before </body> instead of in the <head>.
	 *     @type callable    $condition  Optional. Callback function that determines if the script should be enqueued.
	 *     @type array       $attributes Optional. Key-value pairs of attributes to add to the script tag.
	 *     @type string      $hook       Optional. WordPress hook to use for deferred enqueuing.
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
	 * Chain-able call to add media to be loaded.
	 *
	 * @param  array<int, array<string, mixed>> $media - The array of media to enqueue. Each media item should be an array with the following keys.
	 *     @type array       $args       (optional) Arguments to pass to wp_enqueue_media().
	 *     @type callable    $condition  (optional) Callback function that determines if the media should be enqueued.
	 *     @type string      $hook       (optional) WordPress hook to use for deferred enqueuing.
	 */
	public function add_media( array $media ): self {
		$this->media = $media;

		return $this;
	}

	/**
	 * Enqueue an array of scripts.
	 *
	 * @param  array<int, array<string, mixed>> $scripts - The array of scripts to enqueue. Each script should be an array with the following keys.
	 *     @type string             $handle     (required) Name of the script. Should be unique.
	 *     @type string             $src        (required) URL to the script resource.
	 *     @type array              $deps       (optional) An array of registered script handles this script depends on.
	 *     @type string|false|null  $version    (optional) String specifying script version number. False will default to the plugin version, where 'null' will disable versioning.
	 *     @type bool               $in_footer  (optional) Whether to enqueue the script before </body> instead of in the <head>.
	 *     @type callable           $condition  (optional) Callback function that determines if the script should be enqueued.
	 *     @type array              $attributes (optional) Key-value pairs of HTML attributes to add to the script tag.
	 *     @type array              $wp_data    (optional) Key-value pairs of WordPress script data for wp_script_add_data().
	 *     @type string             $hook       (optional) WordPress hook to use for deferred enqueuing.
	 */
	public function enqueue_scripts( array $scripts ): self {
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
				continue;
			}

			error_log( 'Enqueueing script: ' . $handle );

			// Enqueue the script.
			if ( $this->get_logger()->is_active() ) {
				$this->get_logger()->debug( 'EnqueueAbstract::enqueue_scripts - Calling wp_enqueue_script for non-deferred: ' . $handle );
			}
			wp_enqueue_script( $handle );
		}

		// Register hooks for any deferred scripts that were added.
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
					// This ensures it runs before other scripts that might depend on it.
					add_action( $hook, $callback, 10 );
				}
			}
		}

		return $this;
	}

	/**
	 * Enqueue an array of styles.
	 *
	 * @param  array<int, array<string, mixed>> $styles - The array of styles to enqueue. Each style should be an array with the following keys.
	 *     @type string                 $handle     (required) Name of the stylesheet. Should be unique.
	 *     @type string                 $src        (required) URL to the stylesheet resource.
	 *     @type array                  $deps       (optional) An array of registered stylesheet handles this stylesheet depends on.
	 *     @type string|false|null      $version    (optional) String specifying stylesheet version number. False will default to the plugin version, where 'null' will disable versioning.
	 *     @type string                 $media      (optional) The media for which this stylesheet has been defined (e.g. 'all', 'screen', 'print'). Default 'all'.
	 *     @type callable               $condition  (optional) Callback function that determines if the stylesheet should be enqueued.
	 */
	public function enqueue_styles( array $styles ): self {
		foreach ( $styles as $style ) {
			// Extract style parameters from object format.
			$handle    = $style['handle']    ?? '';
			$src       = $style['src']       ?? '';
			$deps      = $style['deps']      ?? array();
			$ver       = $style['version']   ?? false;
			$media     = $style['media']     ?? 'all';
			$condition = $style['condition'] ?? null;

			// Check if the condition is met.
			if ( is_callable( $condition ) && ! $condition() ) {
				continue;
			}

			// Enqueue the style.
			wp_enqueue_style( $handle, $src, $deps, $ver, $media );
		}
		return $this;
	}

	/**
	 * Enqueue an array of media.
	 *
	 * @param  array<int, array<string, mixed>> $media - The array of media to enqueue. Each media item should be an array with the following keys.
	 *     @type array       $args       (optional) Arguments to pass to wp_enqueue_media()..
	 *     @type callable    $condition  (optional) Callback function that determines if the media should be enqueued..
	 */
	public function enqueue_media( array $media ): self {
		foreach ( $media as $item ) {
			$args      = $item['args']      ?? array();
			$condition = $item['condition'] ?? null;

			// Check if the condition is met.
			if ( is_callable( $condition ) && ! $condition() ) {
				continue;
			}

			// Enqueue the media.
			wp_enqueue_media( $args );
		}

		return $this;
	}

	/**
	 * Enqueue all registered scripts, styles, media, and inline scripts.
	 */
	public function enqueue(): void {
		$this->enqueue_scripts( $this->scripts );
		$this->enqueue_styles( $this->styles );
		$this->enqueue_media( $this->media );
		$this->enqueue_inline_scripts();
	}

	/**
	 * Enqueue scripts that were deferred to a specific hook.
	 *
	 * @param string $hook The WordPress hook name.
	 */
	public function enqueue_deferred_scripts( string $hook ): void {
		if ( $this->get_logger()->is_active() ) {
			$this->get_logger()->debug( "EnqueueAbstract::enqueue_deferred_scripts - Entered for hook: \"{$hook}\"" );
		}
		if ( ! isset( $this->deferred_scripts[ $hook ] ) || empty( $this->deferred_scripts[ $hook ] ) ) {
			if ( $this->get_logger()->is_active() ) {
				$this->get_logger()->debug( "EnqueueAbstract::enqueue_deferred_scripts - No deferred scripts found or already processed for hook: \"{$hook}\"" );
			}
			return;
		}
		$scripts_on_this_hook = $this->deferred_scripts[ $hook ];

		foreach ( $scripts_on_this_hook as $script_definition ) {
			$parent_script_handle_for_log = $script_definition['handle'] ?? 'N/A';
			if ( $this->get_logger()->is_active() ) {
				$this->get_logger()->debug( "EnqueueAbstract::enqueue_deferred_scripts - Processing deferred script: \"{$parent_script_handle_for_log}\" for hook: \"{$hook}\"" );
			}
			$handle = $this->process_single_script( $script_definition ); // This is the actual handle returned after processing.

			if ( empty( $handle ) ) { // $handle is the result of process_single_script.
				if ( $this->get_logger()->is_active() ) {
					$this->get_logger()->warning( "EnqueueAbstract::enqueue_deferred_scripts - process_single_script returned empty handle for a script on hook \"{$hook}\". Original definition handle: \"{$parent_script_handle_for_log}\". Skipping." );
				}
				continue;
			}

			if ( $this->get_logger()->is_active() ) {
				$this->get_logger()->debug( "EnqueueAbstract::enqueue_deferred_scripts - Calling wp_enqueue_script for deferred: \"{$handle}\" on hook: \"{$hook}\"" );
			}
			wp_enqueue_script( $handle ); // Enqueue the main deferred script.

			// NOW, process any inline scripts associated with this handle AND this hook.
			if ( $this->get_logger()->is_active() ) {
				$this->get_logger()->debug( "EnqueueAbstract::enqueue_deferred_scripts - Checking for inline scripts for handle '{$handle}' on hook '{$hook}'." );
			}
			foreach ( $this->inline_scripts as $inline_script_key => $inline_script_data ) {
				// Ensure keys exist before accessing.
				$inline_handle      = $inline_script_data['handle']      ?? null;
				$inline_parent_hook = $inline_script_data['parent_hook'] ?? null;

				if ( $inline_handle === $handle && $inline_parent_hook === $hook ) {
					$content   = $inline_script_data['content']   ?? '';
					$position  = $inline_script_data['position']  ?? 'after';
					$condition = $inline_script_data['condition'] ?? null;

					if ( empty( $content ) ) {
						if ( $this->get_logger()->is_active() ) {
							$this->get_logger()->error( "EnqueueAbstract::enqueue_deferred_scripts - Skipping inline script for deferred '{$handle}' due to missing content." );
						}
						continue;
					}

					if ( is_callable( $condition ) && ! $condition() ) {
						if ( $this->get_logger()->is_active() ) {
							$this->get_logger()->debug( "EnqueueAbstract::enqueue_deferred_scripts - Condition false for inline script for deferred '{$handle}'." );
						}
						continue;
					}

					if ( $this->get_logger()->is_active() ) {
						$this->get_logger()->debug( "EnqueueAbstract::enqueue_deferred_scripts - Adding inline script for deferred '{$handle}' (position: {$position}) on hook '{$hook}'." );
					}
					wp_add_inline_script( $handle, $content, $position );

					// Optional: Remove the inline script from $this->inline_scripts once processed to avoid reprocessing in error scenarios.
					// unset($this->inline_scripts[$inline_script_key]);.
				}
			}
		}
		// Optional: Clear the processed deferred scripts for this hook to prevent re-processing if the hook fires multiple times or is called directly again..
		// unset($this->deferred_scripts[$hook]).
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
	 * Render any callbacks registered for the head section.
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
	 * Render any callbacks registered for the footer section.
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

	/**
	 * Process a single script - registers it and sets up attributes and data.
	 *
	 * @param array<string, mixed> $script The script configuration array.
	 * @return string The script handle that was registered, or empty string if conditions not met.
	 */
	private function process_single_script( array $script ): string {
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
}
