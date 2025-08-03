<?php
/**
 * Block Registration Helper with Asset Management
 *
 * This class provides unified block registration that integrates with WordPress's
 * register_block_type() API while leveraging our asset management system. It supports
 * both immediate and deferred block registration patterns, following the architectural
 * principle that blocks default to immediate registration (unlike assets which default
 * to deferred loading).
 *
 * IMPORTANT: This class complements (doesn't duplicate) WordPress's built-in conditional
 * asset loading. See examples/wordpress-vs-blockregistrar-assets.php for details.
 *
 * @package Ran\PluginLib\EnqueueAccessory
 * @author  Ran Plugin Lib
 * @license GPL-2.0+ <http://www.gnu.org/licenses/gpl-2.0.txt>
 * @link    https://github.com/RocketsAreNostalgic
 * @since   0.1.0
 */

declare(strict_types=1);

namespace Ran\PluginLib\EnqueueAccessory;

use Ran\PluginLib\Config\ConfigInterface;

/**
 * Class BlockRegistrar
 *
 * Provides unified block + asset registration that integrates with WordPress's
 * block registration system. This class follows the inverted pattern where blocks
 * default to immediate registration (on 'init') while assets default to deferred
 * loading for performance.
 *
 * Features:
 * - Unified block + asset registration with WordPress integration
 * - Success/failure logging for block registration attempts
 * - Collection and storage of successfully registered WP_Block_Type objects
 * - Public access to registered block objects for introspection and advanced use cases
 * - Asset preloading support with configurable conditions
 * - Flattened API supporting all WordPress block properties and arbitrary custom properties
 */
class BlockRegistrar extends AssetEnqueueBaseAbstract {
	use BlockAssetTrait;

	/**
	 * Scripts handler for managing script assets.
	 *
	 * @var ScriptsHandler
	 */
	protected ScriptsHandler $scripts_handler;

	/**
	 * Styles handler for managing style assets.
	 *
	 * @var StylesHandler
	 */
	protected StylesHandler $styles_handler;

	/**
	 * Stores block definitions for deferred registration indexed by hook and priority.
	 * All blocks are deferred, even those with 'init' hook.
	 *
	 * @var array<string, array<int, array<int, array<string, mixed>>>>
	 */
	protected array $blocks = array();

	/**
	 * Tracks registered hooks to avoid duplicate registrations.
	 *
	 * @var array<string, bool>
	 */
	protected array $registered_hooks = array();

	/**
	 * Tracks registered block names to prevent duplicate registrations.
	 *
	 * @var array<string, bool>
	 */
	protected array $registered_block_names = array();

	/**
	 * Array to store successfully registered WP_Block_Type objects.
	 *
	 * This array stores WP_Block_Type instances returned by successful
	 * register_block_type() calls, indexed by block name. This allows
	 * developers to access the registered block objects for introspection,
	 * dynamic rendering, attribute validation, and other advanced use cases.
	 *
	 * @since 1.0.0
	 * @var array<string, \WP_Block_Type>
	 */
	protected array $registered_wp_block_types = array();

	/**
	 * Stores blocks that should have their assets preloaded.
	 *
	 * @var array<string, bool>
	 */
	protected array $preload_blocks = array();

	/**
	 * Stores blocks with conditional preload logic.
	 *
	 * @var array<string, callable>
	 */
	protected array $conditional_preload_blocks = array();

	/**
	 * Constructor.
	 *
	 * @param ConfigInterface $config The configuration object.
	 */
	public function __construct(ConfigInterface $config) {
		parent::__construct($config);

		// Initialize asset handlers for composition
		$this->scripts_handler = new ScriptsHandler($config);
		$this->styles_handler  = new StylesHandler($config);
	}

	/**
	 * Add block definitions for registration.
	 *
	 * This method processes block definitions and organizes them for either immediate
	 * or deferred registration. Blocks without a 'hook' parameter are registered
	 * immediately on 'init' (default behavior), while blocks with a 'hook' parameter
	 * are deferred until that hook fires.
	 *
	 * Accepts either a single block definition or an array of block definitions.
	 * Single block definitions are automatically normalized to an array.
	 *
	 * @param array $blocks_to_add Single block definition or array of block definitions.
	 *                            Each definition should contain:
	 *                            - 'block_name' (string): The block name (e.g., 'my-plugin/hero-block')
	 *                            - 'hook' (string, optional): WordPress hook for deferred registration. Default: 'init'
	 *                            - 'priority' (int, optional): Hook priority. Default: 10
	 *                            - 'condition' (callable, optional): Condition callback for conditional registration
	 *                            - 'assets' (array, optional): Asset definitions for the block
	 *                            - 'preload' (bool|callable|string, optional): Whether to preload block assets.
	 *                              Can be true for always preload, callable for conditional preload,
	 *                              'inherit' to use the same condition as block registration, or false/omitted for no preload.
	 *
	 *                            Plus any WordPress block properties (passed directly to register_block_type):
	 *                            - 'title' (string): Human-readable block type label
	 *                            - 'description' (string): A detailed block type description
	 *                            - 'category' (string): Block type category classification
	 *                            - 'icon' (string): Block type icon
	 *                            - 'keywords' (array): Additional keywords for search interfaces
	 *                            - 'supports' (array): Supported features
	 *                            - 'attributes' (array): Block type attributes property schemas
	 *                            - 'render_callback' (callable): Block type render callback
	 *                            - 'parent' (array): Setting parent lets a block require nesting within specified blocks
	 *                            - 'ancestor' (array): Makes block available only inside specified block types
	 *                            - 'allowed_blocks' (array): Limits which block types can be inserted as children
	 *                            - 'styles' (array): Alternative block styles
	 *                            - 'variations' (array): Block variations
	 *                            - 'example' (array): Structured data for block preview
	 *                            - 'uses_context' (array): Context values inherited by blocks of this type
	 *                            - 'provides_context' (array): Context provided by blocks of this type
	 *                            - 'textdomain' (string): The translation textdomain
	 *                            - 'api_version' (string): Block API version
	 *                            - Plus any arbitrary custom properties for your plugin's use
	 *
	 * @return self Returns the instance for method chaining.
	 */
	public function add(array $blocks_to_add): self {
		$logger  = $this->get_logger();
		$context = get_class($this) . '::' . __FUNCTION__;

		if ( empty( $blocks_to_add ) ) {
			if ( $logger->is_active() ) {
				$logger->debug( "{$context} - Entered with empty array. No blocks to add." );
			}
			return $this;
		}

		// Normalize single block definition into an array of definitions.
		if ( isset( $blocks_to_add['block_name'] ) && is_string( $blocks_to_add['block_name'] ) ) {
			$blocks_to_add = array( $blocks_to_add );
		}

		foreach ($blocks_to_add as $block_definition) {
			if (!isset($block_definition['block_name'])) {
				if ($logger->is_active()) {
					$logger->warning("{$context} - Block definition missing 'block_name'. Skipping.");
				}
				continue;
			}

			$block_name = $block_definition['block_name'];

			// Check for duplicate block registration
			if (isset($this->registered_block_names[$block_name])) {
				if ($logger->is_active()) {
					$logger->warning("{$context} - Block '{$block_name}' already added for registration. Skipping duplicate.");
				}
				continue;
			}

			$hook     = $block_definition['hook']     ?? 'init';  // Default to immediate registration
			$priority = $block_definition['priority'] ?? 10;

			if ($logger->is_active()) {
				$logger->debug("{$context} - Adding block '{$block_name}' for registration on hook '{$hook}' with priority {$priority}.");
			}

			// Mark this block name as registered to prevent duplicates
			$this->registered_block_names[$block_name] = true;

			// Store in deferred blocks array (even 'init' is considered "deferred" until load() is called)
			$this->blocks[$hook][$priority][] = $block_definition;

			// Register block assets if provided
			if (isset($block_definition['assets'])) {
				$this->register_block_assets($block_name, $block_definition['assets']);
			}

			// Register block for preloading if configured
			if (isset($block_definition['preload'])) {
				$condition = $block_definition['condition'] ?? null;
				$this->_register_block_for_preloading($block_name, $block_definition['preload'], $condition);
			}
		}

		return $this;
	}

	/**
	 * Stage assets for enqueuing and set up WordPress hooks.
	 *
	 * This method processes all registered assets, separating deferred assets
	 * and preparing immediate assets for enqueuing. It also sets up WordPress
	 * hooks for both block registration and asset management, following the
	 * same pattern as Scripts/Styles handlers.
	 *
	 * @return self Returns the instance for method chaining.
	 */
	public function stage(): self {
		$logger  = $this->get_logger();
		$context = get_class($this) . '::' . __FUNCTION__;

		// Register action hooks for deferred block registration
		foreach ($this->blocks as $hook_name => $priorities) {
			foreach ($priorities as $priority => $blocks) {
				$hook_key = $hook_name . '_' . $priority;

				if (!isset($this->registered_hooks[$hook_key])) {
					if ($logger->is_active()) {
						$logger->debug("{$context} - Registering action for hook '{$hook_name}' with priority {$priority}.");
					}

					$this->_do_add_action($hook_name, function() use ($hook_name, $priority) {
						$this->_register_blocks($hook_name, $priority);
					}, $priority);

					$this->registered_hooks[$hook_key] = true;
				}
			}
		}

		// Enhance external block registrations with our asset system
		$this->_do_add_filter('register_block_type_args', array($this, '_integrate_block_assets'), 10, 2);

		// Set up asset management hooks (inherited from BlockAssetTrait via AssetEnqueueBaseAbstract)
		$this->_setup_asset_hooks();

		// Stage the underlying asset handlers
		$this->scripts_handler->stage();
		$this->styles_handler->stage();

		// Set up preload functionality
		$this->_setup_preload_callbacks();

		return $this;
	}

	/**
	 * Load the block registrar, wrapper for stage()
	 *
	 * This method stages the block registrar and enqueues immediate assets.
	 *
	 * @deprecated Use register() instead for better semantic clarity.
	 * @return void
	 */
	public function load(): void {
		$this->stage();
	}

	/**
	 * Register blocks with WordPress.
	 *
	 * This method stages the block registrar and sets up WordPress hooks
	 * for block registration. This is the preferred method name for
	 * semantic clarity over load().
	 *
	 * @return array<string, \WP_Block_Type|false> Array of registration results indexed by block name.
	 *                                            WP_Block_Type on success, false on failure.
	 */
	public function register(): array {
		$this->stage();

		// Return the registration results
		// Note: This may be empty if blocks are registered on hooks that haven't fired yet
		return $this->_get_registration_results();
	}

	/**
	 * Get all successfully registered WP_Block_Type objects.
	 *
	 * Returns an array of WP_Block_Type instances that were successfully
	 * registered with WordPress, indexed by block name. This allows developers
	 * to access registered block objects for introspection, dynamic rendering,
	 * attribute validation, and other advanced use cases.
	 *
	 * Note: This array is populated during block registration, which typically
	 * occurs on WordPress hooks like 'init'. If called before blocks are
	 * registered, this may return an empty array.
	 *
	 * @since 1.0.0
	 * @return array<string, \WP_Block_Type> Array of WP_Block_Type objects indexed by block name.
	 */
	public function get_registered_block_types(): array {
		return $this->registered_wp_block_types;
	}

	/**
	 * Get a specific registered WP_Block_Type object by block name.
	 *
	 * Returns the WP_Block_Type instance for the specified block name if it
	 * was successfully registered with WordPress, or null if not found.
	 *
	 * @since 1.0.0
	 * @param string $block_name The block name (e.g., 'my-plugin/hero').
	 * @return \WP_Block_Type|null The WP_Block_Type object or null if not found.
	 */
	public function get_registered_block_type(string $block_name): ?\WP_Block_Type {
		return $this->registered_wp_block_types[$block_name] ?? null;
	}

	/**
	 * Get registration results for all blocks.
	 *
	 * Returns an array of registration results indexed by block name.
	 * Successful registrations return WP_Block_Type objects, failed
	 * registrations return false.
	 *
	 * @return array<string, \WP_Block_Type|false> Registration results.
	 */
	private function _get_registration_results(): array {
		$results = array();
		
		// Iterate over the nested blocks structure: hook -> priority -> blocks
		foreach ($this->blocks as $hook => $priorities) {
			foreach ($priorities as $priority => $block_definitions) {
				foreach ($block_definitions as $block_definition) {
					$block_name = $block_definition['block_name'];
					
					// If successfully registered, include the WP_Block_Type object
					if (isset($this->registered_wp_block_types[$block_name])) {
						$results[$block_name] = $this->registered_wp_block_types[$block_name];
					} else {
						// If not registered (failed), mark as false
						$results[$block_name] = false;
					}
				}
			}
		}
		
		return $results;
	}

	/**
	 * Get comprehensive status for all blocks.
	 *
	 * Returns detailed status information for all blocks including their
	 * registration state, hook configuration, and registration results.
	 * Useful for debugging and monitoring block registration lifecycle.
	 *
	 * @return array<string, array> Block status information indexed by block name.
	 *                              Each entry contains: status, hook, priority, and additional data.
	 */
	public function get_block_status(): array {
		$status = array();
		
		// Process all staged blocks (including deferred ones)
		foreach ($this->blocks as $hook => $priorities) {
			foreach ($priorities as $priority => $block_definitions) {
				foreach ($block_definitions as $block_definition) {
					$block_name = $block_definition['block_name'];
					
					// Determine current status
					if (isset($this->registered_wp_block_types[$block_name])) {
						// Successfully registered with WordPress
						$status[$block_name] = array(
							'status'        => 'registered',
							'hook'          => $hook,
							'priority'      => $priority,
							'wp_block_type' => $this->registered_wp_block_types[$block_name],
							'registered_at' => $this->registered_wp_block_types[$block_name]->name ?? null
						);
					} elseif ($this->_has_hook_fired($hook)) {
						// Hook has fired but registration failed
						$status[$block_name] = array(
							'status'   => 'failed',
							'hook'     => $hook,
							'priority' => $priority,
							'error'    => 'WordPress registration failed'
						);
					} else {
						// Hook hasn't fired yet - block is pending
						$status[$block_name] = array(
							'status'   => 'pending',
							'hook'     => $hook,
							'priority' => $priority,
							'message'  => "Waiting for '{$hook}' hook to fire"
						);
					}
					
					// Add additional metadata
					$status[$block_name]['config']          = $block_definition;
					$status[$block_name]['has_assets']      = isset($block_definition['assets']);
					$status[$block_name]['has_condition']   = isset($block_definition['condition']);
					$status[$block_name]['preload_enabled'] = isset($block_definition['preload']);
				}
			}
		}
		
		return $status;
	}

	/**
	 * Check if a WordPress hook has already fired.
	 *
	 * Uses WordPress's did_action() function to determine if a hook has been executed.
	 * This works for both core WordPress hooks and custom hooks.
	 *
	 * @param string $hook_name The hook name to check.
	 * @return bool True if the hook has fired, false otherwise.
	 */
	private function _has_hook_fired(string $hook_name): bool {
		// Use did_action() to check if the hook has fired
		// Returns the number of times the hook has been executed
		return did_action($hook_name) > 0;
	}

	/**
	 * Enqueue editor-specific assets.
	 *
	 * This action callback handles the enqueuing of assets that should only load in the
	 * block editor context. It processes editor-scoped assets from registered blocks.
	 *
	 * @internal This is an internal method called by WordPress as an action callback and should not be called directly.
	 * @return self Returns the instance for method chaining.
	 */
	public function _enqueue_editor_assets(): self {
		$logger  = $this->get_logger();
		$context = get_class($this) . '::' . __FUNCTION__;

		if ($logger->is_active()) {
			$logger->debug("{$context} - Processing editor assets for registered blocks.");
		}

		// Process editor assets for all registered blocks
		foreach ($this->block_assets as $block_name => $asset_config) {
			if (isset($asset_config['editor_scripts'])) {
				$this->scripts_handler->add($asset_config['editor_scripts']);
			}

			if (isset($asset_config['editor_styles'])) {
				$this->styles_handler->add($asset_config['editor_styles']);
			}
		}

		// Enqueue immediate assets
		$this->scripts_handler->enqueue_immediate();
		$this->styles_handler->enqueue_immediate();
		return $this;
	}

	/**
	 * Integrate block assets with WordPress block registration.
	 *
	 * This filter callback enhances existing register_block_type() calls by
	 * automatically mapping our asset handles to WordPress's expected format.
	 * This allows our asset system to work seamlessly with blocks registered
	 * by other code.
	 *
	 * @internal This is an internal method called by WordPress as a filter callback and should not be called directly.
	 * @param array  $args       Block registration arguments.
	 * @param string $block_name Block name being registered.
	 * @return array Modified block registration arguments.
	 */
	public function _integrate_block_assets(array $args, string $block_name): array {
		if (!isset($this->block_assets[$block_name])) {
			return $args;
		}

		$logger  = $this->get_logger();
		$context = get_class($this) . '::' . __FUNCTION__;

		if ($logger->is_active()) {
			$logger->debug("{$context} - Integrating assets for block '{$block_name}'.");
		}

		$asset_config = $this->block_assets[$block_name];

		// Map our asset handles to WordPress expected format
		if (isset($asset_config['editor_scripts'][0]['handle'])) {
			$args['editor_script'] = $asset_config['editor_scripts'][0]['handle'];
		}

		if (isset($asset_config['frontend_scripts'][0]['handle'])) {
			$args['script'] = $asset_config['frontend_scripts'][0]['handle'];
		}

		if (isset($asset_config['editor_styles'][0]['handle'])) {
			$args['editor_style'] = $asset_config['editor_styles'][0]['handle'];
		}

		if (isset($asset_config['frontend_styles'][0]['handle'])) {
			$args['style'] = $asset_config['frontend_styles'][0]['handle'];
		}

		return $args;
	}

	/**
	 * Maybe enqueue dynamic assets during block rendering.
	 *
	 * This method is registered as a callback on the WordPress `render_block` filter hook and allows
	 * for dynamic asset loading when specific blocks are being rendered.
	 * This is particularly useful for server-side rendered blocks.
	 *
	 * @internal This is an internal method called by WordPress as a filter callback and should not be called directly.
	 * @param string $block_content The block content.
	 * @param array  $block         The block data.
	 * @return string The unmodified block content.
	 */
	public function _maybe_enqueue_dynamic_assets(string $block_content, array $block): string {
		$block_name = $block['blockName'] ?? '';

		if ($block_name && isset($this->block_assets[$block_name])) {
			// Trigger dynamic asset loading for this specific block
			$this->_enqueue_dynamic_block_assets($block_name);
		}

		return $block_content;
	}

	/**
	 * Register blocks that are deferred to a specific hook and priority.
	 *
	 * This protected method handles the actual registration of blocks when their
	 * designated hook fires. It processes conditions, registers assets, and calls
	 * WordPress's register_block_type() function.
	 *
	 * @param string $hook_name The hook name that fired.
	 * @param int    $priority  The priority level being processed.
	 */
	protected function _register_blocks(string $hook_name, int $priority): void {
		$logger  = $this->get_logger();
		$context = get_class($this) . '::' . __FUNCTION__;

		$blocks = $this->blocks[$hook_name][$priority] ?? array();

		if ($logger->is_active()) {
			$block_count = count($blocks);
			$logger->debug("{$context} - Processing {$block_count} blocks for hook '{$hook_name}' priority {$priority}.");
		}

		foreach ($blocks as $block_definition) {
			$this->_register_single_block($block_definition);
		}

		// Clean up after processing to prevent re-processing
		unset($this->blocks[$hook_name][$priority]);
	}

	/**
	 * Register a single block with WordPress.
	 *
	 * This protected method handles the registration of an individual block,
	 * including condition checking, asset registration, and WordPress integration.
	 *
	 * @param array $block_definition The block definition array.
	 */
	protected function _register_single_block(array $block_definition): void {
		$logger  = $this->get_logger();
		$context = get_class($this) . '::' . __FUNCTION__;

		$block_name = $block_definition['block_name'];

		// Check condition if provided
		if (isset($block_definition['condition']) && is_callable($block_definition['condition'])) {
			if (!call_user_func($block_definition['condition'])) {
				if ($logger->is_active()) {
					$logger->debug("{$context} - Condition failed for block '{$block_name}'. Skipping registration.");
				}
				return;
			}
		}

		// Check if block is already registered with WordPress
		// Note: We cache the registry instance (singleton) but not the is_registered() result
		// to avoid stale results if blocks are registered dynamically during the same request
		$registry = $this->_get_block_registry();
		if ($registry->is_registered($block_name)) {
			if ($logger->is_active()) {
				$logger->debug("{$context} - Block '{$block_name}' already registered with WordPress. Skipping.");
			}
			return;
		}

		// Prepare WordPress block configuration
		// Extract our custom properties that shouldn't go to WordPress
		// Cache the flipped array since it's static and used for every block registration
		$our_properties_flipped = $this->_get_our_properties_flipped();

		// Everything else goes to WordPress (including arbitrary properties)
		$wp_config = array_diff_key($block_definition, $our_properties_flipped);

		$wp_config = $this->_map_assets_to_wordpress_config($wp_config, $block_name);

		if ($logger->is_active()) {
			$logger->debug("{$context} - Registering block '{$block_name}' with WordPress.");
		}

		// Register the block with WordPress
		$result = \register_block_type($block_name, $wp_config);

		if ($result instanceof \WP_Block_Type) {
			// Success: Store the WP_Block_Type object and log success
			$this->registered_wp_block_types[$block_name] = $result;
			if ($logger->is_active()) {
				$logger->debug("{$context} - Successfully registered block '{$block_name}' with WordPress.");
			}
		} else {
			// Failure: Log the failure
			if ($logger->is_active()) {
				$logger->warning("{$context} - Failed to register block '{$block_name}' with WordPress.");
			}
		}
	}

	/**
	 * Map our asset handles to WordPress block configuration format.
	 *
	 * This protected method converts our asset system handles to the format
	 * expected by WordPress's register_block_type() function.
	 *
	 * @param array  $wp_config  WordPress block configuration.
	 * @param string $block_name Block name for asset lookup.
	 *
	 * @return array Modified WordPress configuration with asset handles.
	 */
	protected function _map_assets_to_wordpress_config(array $wp_config, string $block_name): array {
		if (!isset($this->block_assets[$block_name])) {
			return $wp_config;
		}

		$asset_config = $this->block_assets[$block_name];

		// Map asset handles to WordPress expected keys
		// Cache the static mapping array to avoid recreating it for every block
		$asset_mappings = $this->_get_asset_mappings();

		foreach ($asset_mappings as $our_key => $wp_key) {
			if (isset($asset_config[$our_key][0]['handle']) && !isset($wp_config[$wp_key])) {
				$wp_config[$wp_key] = $asset_config[$our_key][0]['handle'];
			}
		}

		return $wp_config;
	}

	/**
	 * Set up asset management hooks.
	 *
	 * This protected method initializes the asset management system hooks
	 * that are inherited from the base asset management infrastructure.
	 */
	protected function _setup_asset_hooks(): void {
		// Early block detection for conditional asset loading
		$this->_do_add_action('wp', array($this, 'detect_block_presence'), 5);

		// Standard asset processing
		$this->_do_add_action('wp_enqueue_scripts', array($this, 'stage'), 10);

		// Editor-specific assets
		$this->_do_add_action('enqueue_block_editor_assets', array($this, '_enqueue_editor_assets'), 10);

		// Dynamic block integration
		$this->_do_add_filter('render_block', array($this, '_maybe_enqueue_dynamic_assets'), 10, 2);
	}

	/**
	 * Enqueue dynamic assets for a specific block.
	 *
	 * This protected method handles the dynamic loading of assets when
	 * a block is being rendered. It processes dynamic-scoped assets
	 * and ensures they are loaded at the appropriate time.
	 *
	 * @param string $block_name The name of the block being rendered.
	 */
	protected function _enqueue_dynamic_block_assets(string $block_name): void {
		if (!isset($this->block_assets[$block_name])) {
			return;
		}

		$logger  = $this->get_logger();
		$context = get_class($this) . '::' . __FUNCTION__;

		if ($logger->is_active()) {
			$logger->debug("{$context} - Enqueuing dynamic assets for block '{$block_name}'.");
		}

		$asset_config = $this->block_assets[$block_name];

		// Process dynamic scripts
		if (isset($asset_config['dynamic_scripts'])) {
			$this->scripts_handler->add($asset_config['dynamic_scripts']);
		}

		// Process dynamic styles
		if (isset($asset_config['dynamic_styles'])) {
			$this->styles_handler->add($asset_config['dynamic_styles']);
		}

		// Immediately enqueue the dynamic assets
		$this->scripts_handler->enqueue_immediate();
		$this->styles_handler->enqueue_immediate();
	}

	/**
	 * Register a block for preloading.
	 *
	 * This method processes preload configuration for a block, supporting boolean flags,
	 * conditional callable functions, and inheritance from block registration conditions.
	 *
	 * @param string $block_name The name of the block.
	 * @param bool|callable|string $preload_config The preload configuration.
	 * @param callable|null $block_condition The block's registration condition (for inherit mode).
	 */
	protected function _register_block_for_preloading(string $block_name, $preload_config, ?callable $block_condition = null): void {
		$logger  = $this->get_logger();
		$context = get_class($this) . '::' . __FUNCTION__;

		if ($preload_config === true) {
			// Always preload
			$this->preload_blocks[$block_name] = true;
			if ($logger->is_active()) {
				$logger->debug("{$context} - Block '{$block_name}' registered for preloading (always).");
			}
		} elseif ($preload_config === 'inherit') {
			// Inherit condition from block registration
			if ($block_condition && is_callable($block_condition)) {
				$this->conditional_preload_blocks[$block_name] = $block_condition;
				if ($logger->is_active()) {
					$logger->debug("{$context} - Block '{$block_name}' registered for preloading (inherit from block condition).");
				}
			} else {
				// No block condition means always register, so always preload
				$this->preload_blocks[$block_name] = true;
				if ($logger->is_active()) {
					$logger->debug("{$context} - Block '{$block_name}' registered for preloading (inherit - always, no block condition).");
				}
			}
		} elseif (is_callable($preload_config)) {
			// Conditional preload
			$this->conditional_preload_blocks[$block_name] = $preload_config;
			if ($logger->is_active()) {
				$logger->debug("{$context} - Block '{$block_name}' registered for conditional preloading.");
			}
		} else {
			if ($logger->is_active()) {
				$logger->warning("{$context} - Invalid preload configuration for block '{$block_name}'. Expected boolean, callable, or 'inherit'.");
			}
		}
	}



	/**
	 * Set up preload callbacks for registered blocks.
	 *
	 * This method registers a callback with the wp_head hook to generate
	 * preload tags for blocks that have been configured for preloading.
	 */
	protected function _setup_preload_callbacks(): void {
		if (empty($this->preload_blocks) && empty($this->conditional_preload_blocks)) {
			return; // No blocks to preload
		}

		$this->_do_add_action('wp_head', array($this, '_generate_preload_tags'), 2);
	}

	/**
	 * Generate preload tags for all configured blocks.
	 *
	 * This method processes both always-preload and conditional-preload blocks,
	 * generating appropriate <link rel="preload"> tags for their assets.
	 */
	protected function _generate_preload_tags(): void {
		$logger  = $this->get_logger();
		$context = get_class($this) . '::' . __FUNCTION__;

		// Process always-preload blocks
		foreach ($this->preload_blocks as $block_name => $enabled) {
			if ($enabled) {
				$this->_generate_preload_tags_for_block($block_name);
			}
		}

		// Process conditional-preload blocks
		foreach ($this->conditional_preload_blocks as $block_name => $condition) {
			if (is_callable($condition) && $condition()) {
				if ($logger->is_active()) {
					$logger->debug("{$context} - Conditional preload condition met for block '{$block_name}'.");
				}
				$this->_generate_preload_tags_for_block($block_name);
			}
		}
	}

	/**
	 * Generate preload tags for a specific block.
	 *
	 * This method processes the assets for a specific block and generates
	 * appropriate <link rel="preload"> tags for both CSS and JavaScript assets.
	 *
	 * @param string $block_name The name of the block to generate preload tags for.
	 */
	protected function _generate_preload_tags_for_block(string $block_name): void {
		if (!isset($this->block_assets[$block_name])) {
			return; // No assets registered for this block
		}

		$logger       = $this->get_logger();
		$context      = get_class($this) . '::' . __FUNCTION__;
		$asset_config = $this->block_assets[$block_name];

		if ($logger->is_active()) {
			$logger->debug("{$context} - Generating preload tags for block '{$block_name}'.");
		}

		// Process script assets for preloading
		$script_types = array('scripts', 'editor_scripts', 'dynamic_scripts');
		foreach ($script_types as $script_type) {
			if (isset($asset_config[$script_type])) {
				$this->_generate_preload_tags_for_assets($asset_config[$script_type], 'script');
			}
		}

		// Process style assets for preloading
		$style_types = array('styles', 'editor_styles', 'dynamic_styles');
		foreach ($style_types as $style_type) {
			if (isset($asset_config[$style_type])) {
				$this->_generate_preload_tags_for_assets($asset_config[$style_type], 'style');
			}
		}
	}

	/**
	 * Generate preload tags for a set of assets.
	 *
	 * This method processes an array of asset definitions and generates
	 * <link rel="preload"> tags for each asset.
	 *
	 * @param array  $assets     Array of asset definitions.
	 * @param string $asset_type The type of asset ('script' or 'style').
	 */
	protected function _generate_preload_tags_for_assets(array $assets, string $asset_type): void {
		foreach ($assets as $asset) {
			if (!isset($asset['src'])) {
				continue; // Skip assets without source
			}

			$src          = $this->_resolve_environment_src($asset['src']);
			$as_attribute = $asset_type === 'script' ? 'script' : 'style';

			// Generate the preload tag
			$preload_tag = sprintf(
				'<link rel="preload" href="%s" as="%s"%s>',
				esc_url($src),
				esc_attr($as_attribute),
				$asset_type === 'script' ? '' : ' type="text/css"'
			);

			echo $preload_tag . "\n";
		}
	}

	/**
	 * Get the WordPress Block Type Registry instance with request-level caching.
	 *
	 * This method caches the registry singleton instance to avoid repeated singleton
	 * lookups during the same request. We only cache the instance itself, not the
	 * results of registry queries like is_registered(), to prevent stale results
	 * when blocks are registered dynamically during the request lifecycle.
	 *
	 * @return \WP_Block_Type_Registry The WordPress block type registry instance.
	 */
	protected function _get_block_registry(): \WP_Block_Type_Registry {
		return $this->_cache_for_request('wp_block_registry', function() {
			return \WP_Block_Type_Registry::get_instance();
		});
	}

	/**
	 * Get the flipped array of our custom properties with request-level caching.
	 *
	 * This method caches the result of array_flip() on our static list of custom
	 * properties to avoid repeated array operations during block registration.
	 * The flipped array is used with array_diff_key() to filter out our custom
	 * properties before passing configuration to WordPress.
	 *
	 * @return array<string, int> Flipped array of our custom property names.
	 */
	protected function _get_our_properties_flipped(): array {
		return $this->_cache_for_request('our_properties_flipped', function() {
			return array_flip(array('block_name', 'hook', 'priority', 'condition', 'assets', 'preload'));
		});
	}

	/**
	 * Get the asset mappings configuration with request-level caching.
	 *
	 * This method caches the static asset mapping array that converts our asset
	 * type keys to WordPress's expected block configuration keys. Since this
	 * mapping is static and used for every block with assets, caching it avoids
	 * recreating the same array repeatedly.
	 *
	 * @return array<string, string> Asset type mapping from our keys to WordPress keys.
	 */
	protected function _get_asset_mappings(): array {
		return $this->_cache_for_request('asset_mappings', function() {
			return array(
				'editor_scripts'   => 'editor_script',
				'frontend_scripts' => 'view_script',    // Use WordPress's built-in conditional loading
				'editor_styles'    => 'editor_style',
				'frontend_styles'  => 'view_style',     // Use WordPress's built-in conditional loading
				'scripts'          => 'script',         // Universal scripts (both contexts)
				'styles'           => 'style',          // Universal styles (both contexts)
			);
		});
	}
}
