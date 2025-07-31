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
	 * Stores block definitions for immediate registration.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	protected array $blocks = array();

	/**
	 * Stores block definitions for deferred registration indexed by hook and priority.
	 *
	 * @var array<string, array<int, array<int, array<string, mixed>>>>
	 */
	protected array $deferred_blocks = array();

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
	 *                            - 'block_config' (array, optional): WordPress block configuration
	 *                            - 'assets' (array, optional): Asset definitions for the block
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
			$this->deferred_blocks[$hook][$priority][] = $block_definition;

			// Register block assets if provided
			if (isset($block_definition['assets'])) {
				$this->register_block_assets($block_name, $block_definition['assets']);
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
		foreach ($this->deferred_blocks as $hook_name => $priorities) {
			foreach ($priorities as $priority => $blocks) {
				$hook_key = $hook_name . '_' . $priority;

				if (!isset($this->registered_hooks[$hook_key])) {
					if ($logger->is_active()) {
						$logger->debug("{$context} - Registering action for hook '{$hook_name}' with priority {$priority}.");
					}

					$this->_do_add_action($hook_name, function() use ($hook_name, $priority) {
						$this->_register_deferred_blocks($hook_name, $priority);
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

		return $this;
	}

	/**
	 * Load the block registrar, wrapper for stage()
	 *
	 * This method stages the block registrar and enqueues immediate assets.
	 *
	 * @return void
	 */
	public function load(): void {
		$this->stage();
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
		foreach ($this->block_assets as $block_name => $block_config) {
			if (isset($block_config['editor_scripts'])) {
				$this->scripts_handler->add($block_config['editor_scripts']);
			}

			if (isset($block_config['editor_styles'])) {
				$this->styles_handler->add($block_config['editor_styles']);
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

		$block_config = $this->block_assets[$block_name];

		// Map our asset handles to WordPress expected format
		if (isset($block_config['editor_scripts'][0]['handle'])) {
			$args['editor_script'] = $block_config['editor_scripts'][0]['handle'];
		}

		if (isset($block_config['frontend_scripts'][0]['handle'])) {
			$args['script'] = $block_config['frontend_scripts'][0]['handle'];
		}

		if (isset($block_config['editor_styles'][0]['handle'])) {
			$args['editor_style'] = $block_config['editor_styles'][0]['handle'];
		}

		if (isset($block_config['frontend_styles'][0]['handle'])) {
			$args['style'] = $block_config['frontend_styles'][0]['handle'];
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
	 *
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
	protected function _register_deferred_blocks(string $hook_name, int $priority): void {
		$logger  = $this->get_logger();
		$context = get_class($this) . '::' . __FUNCTION__;

		$blocks = $this->deferred_blocks[$hook_name][$priority] ?? array();

		if ($logger->is_active()) {
			$block_count = count($blocks);
			$logger->debug("{$context} - Processing {$block_count} blocks for hook '{$hook_name}' priority {$priority}.");
		}

		foreach ($blocks as $block_definition) {
			$this->_register_single_block($block_definition);
		}

		// Clean up after processing to prevent re-processing
		unset($this->deferred_blocks[$hook_name][$priority]);
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
		if (\WP_Block_Type_Registry::get_instance()->is_registered($block_name)) {
			if ($logger->is_active()) {
				$logger->debug("{$context} - Block '{$block_name}' already registered with WordPress. Skipping.");
			}
			return;
		}

		// Prepare WordPress block configuration
		$wp_config = $block_definition['block_config'] ?? array();
		$wp_config = $this->_map_assets_to_wordpress_config($wp_config, $block_name);

		if ($logger->is_active()) {
			$logger->debug("{$context} - Registering block '{$block_name}' with WordPress.");
		}

		// Register the block with WordPress
		$result = \register_block_type($block_name, $wp_config);

		if (!$result && $logger->is_active()) {
			$logger->warning("{$context} - Failed to register block '{$block_name}' with WordPress.");
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

		$block_config = $this->block_assets[$block_name];

		// Map asset handles to WordPress expected keys
		$asset_mappings = array(
			'editor_scripts'   => 'editor_script',
			'frontend_scripts' => 'script',
			'editor_styles'    => 'editor_style',
			'frontend_styles'  => 'style',
		);

		foreach ($asset_mappings as $our_key => $wp_key) {
			if (isset($block_config[$our_key][0]['handle']) && !isset($wp_config[$wp_key])) {
				$wp_config[$wp_key] = $block_config[$our_key][0]['handle'];
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

		$block_config = $this->block_assets[$block_name];

		// Process dynamic scripts
		if (isset($block_config['dynamic_scripts'])) {
			$this->scripts_handler->add($block_config['dynamic_scripts']);
		}

		// Process dynamic styles
		if (isset($block_config['dynamic_styles'])) {
			$this->styles_handler->add($block_config['dynamic_styles']);
		}

		// Immediately enqueue the dynamic assets
		$this->scripts_handler->enqueue_immediate();
		$this->styles_handler->enqueue_immediate();
	}
}
